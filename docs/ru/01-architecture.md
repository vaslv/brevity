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
   В одной транзакции со вставкой клика инкрементируется слотовый счётчик
   (см. «Боты и счётчики кликов»).
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
   - `resolveDomainId` — явный домен (lookup), либо автоподбор по
     `domain_strategy` через `DomainSelector` (опц. в рамках
     `domain_group` — код группы), либо домен по умолчанию. Пустой пул при заданной
     стратегии → `ValidationException` (422).
   - `resolveUrlId` — insertOrIgnore + SELECT (race-safe).
   - `resolveConditionId` — тот же паттерн, ключ `(type, data)`.
   - Вставка `Link`, его `Rule` по порядку.
3. Возвращает 201 с payload'ом созданного (точная форма — см.
   `docs/03-api.md`).

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
5. Обновить `docs/03-api.md` и `docs/02-glossary.md`.

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
`IpAddress`, `Url`); user agent резолвится выделенной веткой (см. ниже).

**Не каскадить soft-delete на аналитику.** Клики и колбеки — это
исторические факты и должны пережить `Link`, на который ссылаются.

Ответы резолва (302 и интерстишалы) несут `Cache-Control: no-store`:
закэшированный браузером переход не доходит до трекера, а каждый клик —
это колбек партнёру.

### Боты и счётчики кликов

`app/Services/Links/Clicks/` + `app/Console/Commands/`.

- **Флаг бота живёт на справочнике `user_agents`** (`is_bot`), не на
  клике: детекция детерминирована по UA и вычисляется один раз при
  создании строки (`BotDetector` — интерфейс, реализация
  `DeviceDetectorBotDetector` поверх matomo/device-detector; синглтон в
  `AppServiceProvider`). Существующие строки не пере-детектируются на
  горячем пути — пере-детекция после обновления библиотеки паттернов:
  `artisan user-agents:detect-bots` (keyset + лок). Клик без UA — не бот.
- **Слотовые счётчики** (`link_click_counters`): `(link_id, is_bot,
  slot 1..100, count)`, unique по тройке. `ClickCounterIncrementer`
  делает UPSERT со случайным слотом **в одной транзакции со вставкой
  клика** и только при `wasRecentlyCreated` — ретрай джоба не двоит
  счёт; 100 слотов убирают hot row под конкуренцией. Итог ссылки =
  сумма слотов.
- **Инвариант:** все «числа кликов ссылки» в списках/сортировках/сводках
  читаются из счётчиков, не COUNT по `clicks`; счётчик меняется только
  в транзакции с кликом либо командой `clicks:rebuild-counters`
  (TRUNCATE + агрегатный INSERT..SELECT — первичный backfill и
  реконсиляция). Time-windowed запросы дашборда («сегодня», «за
  неделю», график) остаются на индексированных COUNT по
  `clicks(created_at)` — у счётчиков нет временного измерения, это
  осознанно.
- **Колбеки**: каждый payload несёт `is_bot` в корне (ключ
  зарезервирован, клиентское значение перекрывается) + плейсхолдер
  `{{click.is_bot}}` — контракт в [03-api.md](./03-api.md) §10.

### Генерация кода

`App\Services\Links\CodeStrategy\HashidCodeGenerator` оборачивает
`Hashids` с фиксированным алфавитом из 52 символов, минимальной длиной
5, хранится как `varchar(8)`. Биективно — коллизий не бывает по
построению.

### Выбор домена (Domain selection)

`App\Services\Links\Domains\`

- `DomainSelectionStrategy` — enum: `random`, `round_robin`, `coldest`.
- Интерфейс `DomainSelectionStrategyHandler`: `select(Builder $pool): ?Domain`
  плюс статический `strategy(): DomainSelectionStrategy`.
- `RandomDomainStrategy` / `RoundRobinDomainStrategy` / `ColdestDomainStrategy`.
  round_robin — наименее недавно использованный (из истории ссылок, без
  курсора); coldest — наименьший счётчик за окно
  `config('domains.coldest_period_days')`.
- `DomainStrategyRegistry` — мапа `strategy → handler` из tagged-сервисов.
- `DomainSelector` — строит пул (группа по коду `domain_group` либо все
  домены) и делегирует хендлеру; `null` при пустом пуле.
- Подключение: `DomainStrategyServiceProvider` (тег `domain.strategy`),
  вызывается из `LinkCreator::resolveDomainId`.

Статистика round_robin/coldest — глобальная (по всем сервисам), без
soft-deleted ссылок.

**Добавление новой стратегии:**
1. Реализовать `DomainSelectionStrategyHandler`.
2. Добавить значение в enum `DomainSelectionStrategy`.
3. Затегировать класс в `DomainStrategyServiceProvider` (`domain.strategy`).
4. Обновить `docs/03-api.md`, `docs/02-glossary.md`.

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
  Filament/                 Админка (см. docs/04-admin.md)
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
  en/, ru/                  Структурированные lang-файлы (см. docs/04-admin.md)
  ru.json                   Легаси JSON-ключи, всё ещё в ходу
routes/
  web.php                   GET /{code}
  api.php                   POST /api/links
scripts/
  release.sh                Интерактивный тэггер (см. docs/05-development.md)
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

- **Публичный API** → `docs/03-api.md`.
- **Payload'ы колбеков** → тоже `docs/03-api.md` (§10).
- **Админка Filament** → только внутреннее.
