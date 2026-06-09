# Архитектура

## Стек

- PHP 8.4, Laravel 13
- PostgreSQL (jsonb для `callback_data`, `Condition.data`, `Callback.response_body`)
- Filament 5 (админка)
- Laravel Sanctum (API-аутентификация через personal access tokens)
- Laravel Horizon (мониторинг очередей)
- Laravel Octane (долгоживущий воркер — **помни про гочи Octane**:
  никакого состояния в синглтонах, никакой статической аккумуляции)
- Laravel Sail (Docker dev-окружение)
- `vaslv/laravel-settings` (key/value настройки приложения)
- `hashids/hashids` (короткие коды для `Link.code`)
- `sentry/sentry-laravel` (репортинг ошибок; подключён в `bootstrap/app.php`)

## Потоки запросов

### Резолвинг ссылки — `GET /{code}`

Вход: `App\Http\Controllers\ResolveLink` (single-action, invokable).
Rate-limited через `throttle:link-resolve` (per-link + per-IP).

1. Найти `Link` по `code` (иначе 404).
2. Пройти `rules` в порядке приоритета, спросить каждое `Condition`,
   матчится ли оно, через `ConditionRegistry`.
3. Первый матч побеждает → резолвим `Url`.
4. Асинхронно записываем `Click` (`RecordClickJob`) — джоб несёт `uuid`,
   сгенерированный на этом резолве, поэтому повторное выполнение идемпотентно.
5. **Внутри `RecordClickJob`** (после записи клика) `CallbackDispatcher`:
   если `Service.callback_url` задан **и** `Link.callback_data` не null →
   создаёт `Callback` и ставит `SendCallbackJob` в очередь (нужен `click.id`).
6. Рендерим ответ по `Rule.transition_mode`:
   - `direct` → 302
   - `delayed` → Blade-страница с обратным отсчётом
   - `manual` → Blade-страница с кнопкой «продолжить»

### Создание ссылки — `POST /api/links`

Вход: `App\Http\Controllers\Api\LinkController::store` (тонкий,
делегирует). Auth: Sanctum bearer.

1. Валидация запроса (правила зависят от типа условия).
2. `App\Services\Links\LinkCreator::create($validated)` оборачивает
   всё в транзакцию:
   - `resolveDomainId` — только lookup (домен должен существовать).
   - `resolveUrlId` — insertOrIgnore + SELECT (race-safe).
   - `resolveConditionId` — тот же паттерн, ключ `(type, data)`.
   - Вставка `Link`, его `Rule` по порядку.
3. Возвращает 201 с payload'ом созданного (точная форма — см.
   `SDK_API.md`).

## Ключевые подсистемы

### Условия (Conditions)

`App\Services\Links\Conditions\`

- `ConditionRegistry` — список хендлеров по ключу `type`.
- Интерфейс `ConditionHandler`: инстанс-метод `matches(Condition $c, ConditionContext $ctx): bool`
  плюс **статические** `rules(): array`, `type(): string`, `validate(array $data): array`
  (вызываются как `$handler::rules()` / `::validate()`).
- `AbstractConditionHandler` — общая обвязка.
- `TimeBeforeConditionHandler` — единственный текущий тип.

**Добавление нового типа условия:**
1. Создать класс-хендлер, наследующий `AbstractConditionHandler`.
2. Зарегистрировать в сервис-контейнере (`ConditionRegistry` сам
   подхватит через constructor injection — см. как подключен
   `TimeBeforeConditionHandler`).
3. Добавить ветку рендера в схему `ConditionForm`.
4. Перевести `types.<new_type>` и `data_fields.<new_type>.*` в
   `lang/{en,ru}/resources/condition.php`.
5. Обновить `SDK_API.md` и `docs/GLOSSARY.md`.

### Колбеки (Callbacks)

`App\Services\Links\Callbacks\` + `app/Jobs/SendCallbackJob.php`.

- `CallbackDataRenderer` — подставляет `{{click.*}}` / `{{link.*}}`
  переменные в строковые значения payload'а колбека.
- `CallbackUrlGuard` — блокирует приватные/внутренние IP и невалидные
  схемы на момент отправки.
- `CallbackDispatcher` — создаёт `Callback` (идемпотентно по `click_id`) и
  ставит `SendCallbackJob`.
- `SendCallbackJob` — HTTP POST **без следования редиректам**
  (`allow_redirects = false`; 3xx/4xx — постоянная ошибка). Расписание ретраев:
  1м → 5м → 15м → 1ч (5 попыток, 4 интервала).
- Response body хранится обрезанным (10_000 символов) и санитизированным:
  `mb_convert_encoding($body, 'UTF-8', 'UTF-8')` + стрип NUL-байтов
  (text-колонки Postgres не принимают NUL).

### Запись кликов

`app/Jobs/RecordClickJob.php` + `app/Services/Links/Clicks/`.

Работает вне основного потока, чтобы редирект посетителя был быстрым.
Резолверы справочников (`DictionaryValueResolver`) используют
insertOrIgnore + SELECT на каждый справочник (`Referrer`, `UserAgent`,
`IpAddress`, `Url`).

**Не каскадить soft-delete на аналитику.** Клики и колбеки — это
исторические факты и должны пережить `Link`, на который ссылаются.

### Генерация кода

`App\Services\Links\CodeStrategy\HashidCodeGenerator` оборачивает
`Hashids` с фиксированным алфавитом из 52 символов, минимальной длиной
5, хранится как `varchar(8)`. Биективно — коллизий не бывает по
построению.

### Rate limiting

`bootstrap/app.php` (или ServiceProvider) определяет `link-resolve`:
per-link + per-IP. Защищает от скрейпа и подбора кодов.

## Модель данных

Авторитетная схема — в `database/migrations/`. Заметные ограничения:

- `conditions` — уникальный `(type, data)` для дедупа.
- `clicks(service_id, created_at)`, `clicks(link_id, created_at)` —
  композитные индексы для аналитики в админке.
- `callbacks(service_id, created_at)` — то же.
- `links` — soft deletes. **Trashed-ссылка считается отключённой:**
  `Link::findByCode` применяет глобальный scope SoftDeletes, поэтому публичный
  резолв удалённой ссылки даёт 404. (Админ всё ещё видит trashed —
  `LinkResource::getRecordRouteBindingEloquentQuery` снимает scope.)

## Раскладка каталогов

```
app/
  Filament/                 Админка (см. docs/ADMIN.md)
  Http/Controllers/
    ResolveLink.php         GET /{code}
    Api/LinkController.php  POST /api/links
  Jobs/
    RecordClickJob.php
    SendCallbackJob.php
  Models/                   Eloquent-модели (тонкие)
  Services/Links/           Бизнес-логика
    Callbacks/              Исходящие колбеки
    Clicks/                 Запись кликов, резолверы справочников
    CodeStrategy/           Генерация коротких кодов
    Conditions/             Предикаты матчинга правил
    LinkCreator.php         Создание ссылки через API
    LinkRuleResolver.php    Выбор победившего правила на визите
    TransitionMode.php      Enum
config/
database/migrations/
docs/                       Эта папка
lang/
  en/, ru/                  Структурированные lang-файлы (см. docs/ADMIN.md)
  ru.json                   Легаси JSON-ключи, всё ещё в ходу
routes/
  web.php                   GET /{code}
  api.php                   POST /api/links
scripts/
  release.sh                Интерактивный тэггер (см. docs/DEVELOPMENT.md)
tests/                      Feature + unit тесты
```

**Связи моделей (соглашение).** Каждая Eloquent-связь живёт в отдельном
одно-методном трейте в `app/Models/Relations/` (`BelongsToService`,
`HasManyClicks`, …); модели подключают их через `use`. Связи **никогда** не
объявляются инлайн в модели — даже однострочные и даже если трейт использует
ровно одна модель. Это держит модели тонкими и даёт прочитать весь набор
связей модели прямо из блока `use`. (Дублируется в `CLAUDE.md`; осознанно
расходится с рекомендацией аудита «заинлайнить тривиальные».)

## Внешние контракты

- **Публичный API** → `SDK_API.md` в корне репо.
- **Payload'ы колбеков** → тоже `SDK_API.md`.
- **Админка Filament** → только внутреннее.
