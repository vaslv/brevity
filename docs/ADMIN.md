# Админ-панель (Filament 5)

Обслуживается на `/` через `App\Providers\Filament\MainPanelProvider`.
Гвард аутентификации `web`; страница логина — `App\Filament\Pages\Auth\Login`
(кастомный layout, в остальном сток Filament).

## Доступ и авторизация

**Инвариант развёртывания: каждый `User` — полностью доверенный администратор.**
Ролевой модели нет намеренно — это внутренний инструмент для доверенных операторов:

- `User::canAccessPanel()` возвращает `true` для всех аутентифицированных
  пользователей: кто может залогиниться — видит и правит все ресурсы.
- `HorizonServiceProvider::gate()` (`viewHorizon`) делегирует в
  `User::canAccessPanel()` — тот же гейт, что и у панели, поэтому Horizon
  (он раскрывает payload'ы джобов и трейсы упавших задач) держится в связке
  с доступом к панели.
- Пользователи создаются прямо в панели (раздел «Система → Пользователи»), поэтому
  **создание учётки = выдача полного доступа**. Заводите аккаунты только доверенным
  операторам.

Если понадобится разделение прав (напр. read-only роль) — вводите минимальный флаг
`users.is_admin` и гейтите им `canAccessPanel()` и `viewHorizon`
(см. `docs/CODE_REVIEW.md`, M6).

## Навигация

Верхний уровень (без группы): Сервисы, Ссылки, Домены.

Группы (русские названия см. в глоссарии):

- **Аналитика** — Клики, Колбеки
- **Справочники** — URL, Условия, IP-адреса, Рефереры, User agent'ы
- **Система** — Пользователи, Настройки

Порядок внутри групп задаётся `$navigationSort` на каждом Resource.

## Соглашение по раскладке ресурса

Каждый ресурс живёт в `app/Filament/Resources/{Plural}/`:

```
{Resource}Resource.php       Метаданные + обвязка страниц
Pages/                       ListX, CreateX, EditX, ViewX
Schemas/
  {Resource}Form.php         Схема create/edit формы
  {Resource}Infolist.php     Схема страницы просмотра
Tables/
  {Resource}sTable.php       Индексная таблица
RelationManagers/            Опционально (напр. RulesRelationManager)
```

Класс Resource переопределяет:

```php
public static function getNavigationGroup(): ?string  // __('navigation.groups.<key>')
public static function getNavigationLabel(): string   // __('resources/<name>.navigation_label')
public static function getModelLabel(): string        // __('resources/<name>.label')
public static function getPluralModelLabel(): string  // __('resources/<name>.plural_label')
```

## i18n

**Все пользовательские строки идут через `__()`**, источник —
структурированные lang-файлы:

```
lang/
  en/
    navigation.php           groups.{analytics,dictionaries,system}
    resources/
      service.php
      link.php           ← включает rules.*, transition_modes.*
      domain.php
      click.php
      callback.php       ← включает statuses.*
      url.php
      condition.php      ← включает types.*, data_fields.*
      ip_address.php
      referrer.php
      user_agent.php
      user.php
      setting.php
  ru/                          то же дерево, русские значения
  ru.json                      легаси JSON-ключи (оставлены, всё ещё в ходу)
```

Обвязка Filament (лейблы Create/Edit/Delete/View, пагинация,
подтверждения bulk-экшенов, форма логина) резолвится через штатные
переводы Filament в `vendor/filament/*/resources/lang/ru/`.
`APP_LOCALE=ru` выставлен в `.env`.

**Строки ядра Laravel** (валидация, auth, passwords) лежат в
`lang/en/` — опубликованы через `php artisan lang:publish`, чтобы при
необходимости править.

## Добавление ресурса — чек-лист

1. `artisan make:filament-resource {Name} --generate --view` (или аналог).
2. Выстави `$navigationIcon`, `$navigationSort` и переопредели четыре
   метода лейблов (см. выше).
3. Создай `lang/{en,ru}/resources/<snake>.php` с `label`,
   `plural_label`, `navigation_label`, `fields.*`.
4. Обвяжи каждый `->label()` в форме/таблице/инфолисте через
   `__('resources/<snake>.fields.<key>')`.
5. Добавь термин в [GLOSSARY.md](./GLOSSARY.md).
6. Прогоняй `sail test` — без новых падений.

## Заметки по конкретным ресурсам

- **Service** — у `ViewService` кастомный header action «Create API token»;
  строки диалога токена лежат под `resources/service.tokens.*`.
- **Link** — использует soft deletes.
  `LinkResource::getRecordRouteBindingEloquentQuery()` включает trashed
  ссылки, чтобы удалённые записи оставались просматриваемыми.
- **Rule** — не ресурс; управляется только через `RulesRelationManager`
  на странице Link. Переводы в `resources/link.rules.*`.
- **Condition** — форма реактивна по типу: выбор типа подставляет
  нужное поле `data.*`. Ключи в
  `resources/condition.data_fields.<type>.*`. Справочник read-only (есть
  Create, но без Edit).
- **Callback / Click** — только исторические записи. В таблицах есть
  только `ViewAction`, без Edit. Удаление разрешено, но редкая операция.
- **Справочники (URL, Условия, IpAddress, Referrer, UserAgent)** — read-only:
  в таблицах только `ViewAction` (+ удаление), `EditAction`/`EditPage` нет.

## Чип версии

`resources/views/filament/version-chip.blade.php` рендерит бейдж с
`config('app.version')` (источник — `composer.json`). Инжектится через
render hooks в `MainPanelProvider`:

- `PanelsRenderHook::SIDEBAR_LOGO_AFTER`
- `PanelsRenderHook::TOPBAR_LOGO_AFTER`
