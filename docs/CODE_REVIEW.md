# Code Review

> Ревью всего проекта Brevity как новой версии перед продолжением разработки.
> Дата: 2026-06-01. Стек: PHP 8.4 / Laravel 13 / PostgreSQL / Filament 5 /
> Sanctum / Horizon / Octane (FrankenPHP) / Hashids / `vaslv/laravel-settings`.

**Что проверено вживую (а не только чтением кода):**

- Поведение правила валидации `url` в Laravel 13.6 (рантайм-проверка в идентичной
  версии фреймворка): `javascript:`, `data:`, `vbscript:` **отклоняются**; `ftp://`
  **проходит**. → XSS через `javascript:`-URL в delayed/manual **не воспроизводится**.
- Сериализация вложенного `JsonResource::make(null)` в Laravel 13.6 → возвращает
  `null` без ошибки. → правило без `condition` корректно даёт `"condition": null`,
  500-й нет.
- Паритет ключей `lang/en` ↔ `lang/ru` (рекурсивно по всем `resources/*.php`,
  `navigation.php`, `widgets.php`): **0 расхождений**.
- Horizon `supervisor-1` слушает очередь `default`; джобы уходят в `default`
  (`QUEUE_CONNECTION=redis`). → клики/колбеки не теряются из-за неслушаемой очереди.
- Отсутствие отдельного индекса `clicks(created_at)` (есть только композитные
  `(service_id, created_at)` и `(link_id, created_at)`).

**Прогон набора (выполнен):** поднят Sail (`sail-8.4/app`, PHP 8.4.21) на уже
работавших `pgsql`/`redis`, создана БД `testing`, прогон `artisan test --compact`:
**48 passed (123 assertions), 6.20s, 0 падений.** Покрытое поведение в порядке —
зелёный базлайн есть. (Кэш `.phpunit.result.cache` устарел и неинформативен; коды 7/8
в нём — risky/deprecation, а не падения.) Найденные ниже баги — про **непокрытые**
краевые случаи и конфиг безопасности, поэтому зелёный набор им не противоречит; именно
поэтому они и в разделе Missing tests.

**Поправка после написания регресс-тестов (важно):** под findings были написаны
red-тесты в `tests/Feature/Regressions/`. Прогон **снял два ложноположительных
Major**:

- **M1 (URL race) — СНЯТО.** Стек-трейс показал, что в Laravel 13 `firstOrCreate`
  уже делегирует в `createOrFirst` → `withSavepointIfNeeded` (savepoint + перехват
  unique-violation + повторный SELECT). То есть путь **race-safe**; обещание
  ARCHITECTURE.md выполняется, просто другим механизмом. Тест-симуляция давала 500
  лишь как артефакт (конкурентная вставка попадала в тот же savepoint и откатывалась).
- **M4 (edit настроек) — СНЯТО.** Filament v5 по умолчанию
  `shouldUniqueValidationIgnoreRecordByDefault = true`, поэтому `->unique()` и без
  `ignoreRecord` игнорирует текущую запись на edit. Тест T9 это подтвердил (зелёный).

Подтверждены red-тестами как реальные: **C1** (подделка IP) и **M2** (дубли
кликов/колбеков при ретрае). Файлы: `TrustedProxyIpSpoofingTest`,
`RecordClickJobIdempotencyTest`, `SettingEditUniqueKeyTest` (green-guard).
Этот эпизод — иллюстрация, почему findings стоит проверять прогоном, а не только
чтением.

**Применённые исправления (C1 + M2):** оба подтверждённых бага закрыты, оба red-теста
**позеленели**, весь набор — **51 passed, 0 падений** (Pint чистый):

- **C1 — FIXED.** `bootstrap/app.php`: `trustProxies(at: '*')` → доверие только подсетям
  `10/8, 172.16/12, 192.168/16` (под реальный прокси сузить в проде).
- **M2 — FIXED.** Введён ключ идемпотентности клика: миграция `clicks.uuid`
  (nullable unique); `ResolveLink` генерирует UUID на резолв и передаёт в джоб;
  `ClickRecorder` — `firstOrCreate(['uuid' => …])`; `CallbackDispatcher` —
  `firstOrCreate(['click_id' => …])` + диспатч `SendCallbackJob` только при
  `wasRecentlyCreated`. Ретрай джоба больше не плодит клики/колбеки.

Остаются открытыми Major: **M3, M5, M6, M7** (см. ниже).

---

## Executive summary

Проект в хорошем инженерном состоянии: тонкие контроллеры и модели, выделенные
сервисы, аккуратная доменная модель, грамотный конечный автомат ретраев колбеков,
базовый SSRF-гард, race-safe резолверы справочников и условий, чистая Octane-гигиена
(состояние не утекает между запросами), образцовый i18n-паритет. Разрабатывать дальше
**можно**.

Жёстких блокеров для продолжения разработки нет. Критичный пункт C1 (доверие ко всем
прокси → подделка IP, обход rate limiting, порча данных кликов/колбеков) **уже
исправлен** в этой итерации вместе с M2 (не-идемпотентный `RecordClickJob`).

Остаётся уровень hardening/эксплуатации: follow-redirect в колбеках (**M3**), хрупкий
приоритет корневого маршрута (**M5**), отсутствие ролевой модели (**M6**),
производительность аналитики (**M7**) и набор UX-шероховатостей в админке (Minor).

**Счёт (исходный):** Critical — 1, Major — 5, Minor — 14, Documentation mismatches — 6,
Missing tests — 10. *(Major было 7: M1 и M4 сняты как ложноположительные.)*

**Статус сейчас: всё закрыто.**
- Critical: C1 ✅.
- Major: M2 ✅, M3 ✅ (follow-redirect; IP-pinning — принят как остаточный риск,
  документировано), M5 ✅, M6 ✅ (by design — инвариант), M7 ✅.
- Minor: **все 14 закрыты** (m1–m14).
- Documentation mismatches: **все 6** (D1, D3, D4, D5, D6; D2 снят).
- Тесты: 48 → **69** (зелёные), Pint чистый.

Открытых задач из ревью не осталось. Единственный осознанно **не**-реализованный
пункт — IP-pinning в M3 (документирован как принятый остаточный риск, т.к.
`callback_url` админский и пиннинг нетестируем через `Http::fake`).

---

## Critical issues

### C1. Доверие ко всем прокси → подделка IP, обход rate limiting, порча данных кликов/колбеков
> **✅ ИСПРАВЛЕНО.** `trustProxies(at: '*')` → доверие подсетям `10/8, 172.16/12,
> 192.168/16`. Red-тест `TrustedProxyIpSpoofingTest` теперь зелёный. В проде сузить до
> реального прокси.
- **Где:** `bootstrap/app.php` → `->withMiddleware(fn (Middleware $m) => $m->trustProxies(at: '*'))`.
- **Что не так:** `at: '*'` означает «доверять всем прокси». Symfony при этом проходит
  всю цепочку `X-Forwarded-For` справа налево, считая каждый хоп доверенным, и берёт
  **самый левый** элемент — а его полностью контролирует клиент. Значит `$request->ip()`
  спуфится любым внешним запросом.
- **Почему важно:**
  - `AppServiceProvider::boot()` строит лимитер `link-resolve` по `$request->ip()`
    (`Limit::perMinute(120)->by($request->ip())` и `8/min` по `ip:code`). Подделав
    `X-Forwarded-For`, атакующий ротациями IP **полностью обходит** per-IP анти-abuse —
    то есть заявленную в `ARCHITECTURE.md` защиту от подбора кодов и накрутки.
  - `RecordClickJob` пишет `ip_address_id` и подставляет `{{click.ip}}` в payload
    колбека. Спуфнутый IP попадает в аналитику и **уходит во внешние системы** —
    компрометирует данные, на которые завязаны клиенты SDK.
  - `ResolveLink::hasDomainMismatch()` сравнивает `$request->host()`, который при
    доверии ко всем прокси берётся из `X-Forwarded-Host` → проверку домена тоже можно
    подделать.
- **Подтверждение:** тест `ResolveLinkTransitionModeTest::test_it_records_forwarded_client_ip_when_request_comes_through_proxy` фиксирует, что `X-Forwarded-For` принимается «как есть» (REMOTE_ADDR=172.18.0.3, XFF=203.0.113.77 → пишется 203.0.113.77). Поведение намеренное, но небезопасное в текущем виде.
- **Как исправить (минимально и с сохранением нужного поведения):** доверять только
  реальной подсети прокси/балансировщика, а не `*`:
  ```php
  // Docker/Sail сеть + ваш L7-балансировщик — подставьте реальные CIDR:
  $middleware->trustProxies(
      at: ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
      headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST
             | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO,
  );
  ```
  Если приложение всегда за одним известным reverse-proxy (FrankenPHP/Caddy/LB) —
  указать его адрес/подсеть. В проде дополнительно убедиться, что фронтовый прокси
  **затирает** входящий `X-Forwarded-For`, а не аппендит.
- **Нужен тест:** **есть** — `tests/Feature/Regressions/TrustedProxyIpSpoofingTest.php`
  (недоверенный клиент с поддельным `X-Forwarded-For` → ожидается запись `REMOTE_ADDR`).
  Сейчас **RED** (пишется спуфнутый `203.0.113.99`); станет зелёным после сужения
  `trustProxies`. NB: существующий `...records_forwarded_client_ip...` использует
  REMOTE_ADDR `172.18.0.3` (внутри `172.16/12`) — при рекомендованном фиксе он остаётся
  зелёным.

---

## Major issues

### ~~M1. `LinkCreator::resolveUrlId` / `firstOrCreate` не race-safe~~ — СНЯТО (ложноположительное)
- **Вердикт:** не баг. В Laravel 13 `Url::firstOrCreate()` делегирует в `createOrFirst()`
  → `withSavepointIfNeeded()`: вставка идёт под savepoint, `UniqueConstraintViolationException`
  перехватывается, после `ROLLBACK TO SAVEPOINT` выполняется повторный `SELECT`, который
  под READ COMMITTED видит зафиксированную параллельной транзакцией строку. Реальная гонка
  обрабатывается корректно — обещание race-safety из `ARCHITECTURE.md` выполняется, просто
  механизм другой (не дословный «insertOrIgnore + SELECT»).
- **Как выяснилось:** написанный под это red-тест (`UrlDeduplicationRaceTest`) давал 500,
  но это был **артефакт симуляции** — «конкурентная» вставка делалась в том же savepoint и
  откатывалась вместе с провалившимся INSERT, поэтому повторный SELECT ничего не находил.
  В реальности конкурент — отдельная **зафиксированная** транзакция. Тест удалён.
- **Остаточное (тривиально):** см. D2 — формулировка в доке («insertOrIgnore + SELECT»)
  не дословна; по сути и `firstOrCreate`, и `createOrFirst` race-safe. Менять код не нужно.

### M2. `RecordClickJob` не идемпотентен → дубли кликов и **дубли исходящих колбеков** при ретрае
> **✅ ИСПРАВЛЕНО.** Ключ идемпотентности `clicks.uuid` (миграция, nullable unique);
> `ResolveLink` генерит UUID на резолв; `ClickRecorder` → `firstOrCreate(['uuid'…])`;
> `CallbackDispatcher` → `firstOrCreate(['click_id'…])` + диспатч только при
> `wasRecentlyCreated`. Red-тест `RecordClickJobIdempotencyTest` теперь зелёный.
- **Где:** `app/Jobs/RecordClickJob.php::handle()` (`public int $tries = 3;`).
- **Что не так:** `handle()` последовательно: `Link::findOrFail` → `ClickRecorder::record()`
  (коммитит новую `Click` в собственной транзакции) → `CallbackDispatcher::dispatchForClick()`
  (создаёт `Callback` и пушит `SendCallbackJob`). Если что-то падает **после** коммита
  клика (например, `SendCallbackJob::dispatch` не смог записать в Redis, или транзиентная
  ошибка БД при `Callback::create`), джоб ретраится — и `record()` выполняется **заново**,
  создавая **второй** `Click` и **второй** `Callback`/вебхук. У клика нет естественного
  ключа идемпотентности.
- **Почему важно:** искажение аналитики (двойные клики) и **повторная доставка колбеков**
  на сторону клиента сверх документированных «до 5 попыток одного колбека». Вероятность
  невысокая (нужен сбой в узком окне), но Redis-блипы реальны, а последствия видны
  наружу.
- **Как исправить:**
  - **Рекомендуемо (needs decision — требует миграцию):** генерировать `click_uuid` в
    `ResolveLink` и передавать в джоб; в `ClickRecorder` — `Click::firstOrCreate(['uuid' => $uuid], [...])`.
    Тогда ретрай переиспользует клик, а `Callback` сделать идемпотентным по `click_id`
    (`Callback::firstOrCreate(['click_id' => ...], [...])`). Добавить колонку
    `clicks.uuid` (nullable unique).
  - **Лёгкая полумера (без схемы):** оборачивать создание `Click` + `Callback` в одну
    транзакцию и пушить `SendCallbackJob` через `DB::afterCommit()`, чтобы сбой
    диспетчеризации не «отвязывался» от коммита. Дубли при полном ретрае джоба это всё
    равно не закрывает — потому что клик не идемпотентен; поэтому полноценное решение —
    ключ идемпотентности.
- **Нужен тест:** **есть** — `tests/Feature/Regressions/RecordClickJobIdempotencyTest.php`
  (захватывает реальный диспатченный джоб и прогоняет `handle()` дважды). Сейчас **RED**
  (создаётся 2 клика и 2 колбека); станет зелёным после введения ключа идемпотентности.

### M3. SSRF-гард колбеков: следование редиректам и TOCTOU/DNS-rebinding
> **✅ Частично исправлено.** Следование редиректам отключено
> (`->withOptions(['allow_redirects' => false])`), 3xx/4xx трактуются как
> постоянная (не-ретраемая) ошибка. Тест
> `CallbackDispatchTest::test_it_treats_a_redirect_response_as_a_permanent_failure_without_following`.
> **Принято как остаточный риск (документировано, by design):** пиннинг
> резолвнутого IP против TOCTOU/DNS-rebinding **не реализован** намеренно —
> `callback_url` задаёт только доверенный админ (атака = админ против своей же сети),
> редиректы уже отключены, а чистая реализация (CURLOPT_RESOLVE) зависит от
> curl-хендлера и не покрывается тестом (`Http::fake` обходит curl). Включать при
> переходе на пользовательские `callback_url`.
- **Где:** `app/Services/Links/Callbacks/CallbackUrlGuard.php` + `app/Jobs/SendCallbackJob.php::handle()`.
- **Что не так:**
  1. **Follow-redirect.** Гард валидирует только исходный URL. `Http::timeout(10)->post($callbackUrl, ...)`
     использует Guzzle, который **по умолчанию следует за 3xx** (до 5 редиректов). Колбек на
     внешний адрес, отвечающий `302 Location: http://169.254.169.254/...`, будет уведён
     Guzzle на внутренний адрес — SSRF в обход гарда.
  2. **TOCTOU / DNS rebinding.** Гард резолвит хост через `dns_get_record` и проверяет, что
     все IP публичные. Но затем `Http::post` делает **отдельный** DNS-резолв и коннектится;
     между проверкой и запросом ответ DNS может смениться (или round-robin вернёт другой IP).
     Валидированный IP не «пиннится».
- **Почему важно:** классические векторы SSRF. **Смягчающий фактор:** `callback_url`
  задаётся только админом через Filament (`ServiceForm`), а не произвольным API-клиентом,
  поэтому это скорее defense-in-depth, чем дыра под внешним управлением. Тем не менее для
  сервиса, который сам ходит по сети, защиту стоит укрепить.
- **Как исправить (минимально):**
  - Отключить редиректы: `Http::withOptions(['allow_redirects' => false])->timeout(10)->post(...)`
    и трактовать 3xx как неуспех (не следовать).
  - Опционально: резолвить IP в гарде, пиннить его и слать запрос с `curl`-резолвом на
    конкретный IP (`->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]]])`),
    либо повторно валидировать connect-IP.
- **Нужен тест:** да (T5).

### ~~M4. `SettingForm`: `key` без `ignoreRecord` ломает edit~~ — СНЯТО (ложноположительное)
- **Вердикт:** не баг. Filament v5 в `CanBeValidated` по умолчанию имеет
  `protected bool|Closure $shouldUniqueValidationIgnoreRecordByDefault = true;`, и
  `unique()` при `ignoreRecord === null` берёт именно этот дефолт → текущая запись
  игнорируется на edit автоматически. `->unique(ignoreRecord: true)` в `UserForm` —
  избыточно, но безвредно.
- **Как выяснилось:** Livewire-тест `SettingEditUniqueKeyTest` (редактирование настройки
  с тем же ключом → save) **зелёный**: ошибок валидации нет, значение сохраняется. Тест
  оставлен как guard. Менять код не нужно.
- **Опц. UX (не баг):** можно `->disabledOn('edit')` на `key`, раз ключ иммутабелен по
  смыслу (как `type` в `ConditionForm`) — чисто косметика.

### M5. Корневой catch-all `GET /{code}` сосуществует с Filament-панелью на `path('')` и health `/up` — хрупкий приоритет маршрутов
> **✅ Проверено и захардено.** `router->match()` подтвердил: `/up` → health,
> `/login` и `/services` → Filament, и только реальные коды → `ResolveLink`
> (`{code}` регистрируется последним). Приоритет корректен — поломки нет. Добавлен
> constraint `->where('code', '[A-Za-z0-9]{5,8}')` (мусорные пути вроде `/favicon.ico`
> теперь 404 на роутере, не жгут rate-limit) и guard-тест `RootRoutePrecedenceTest`.
> **Остаточное (низкая вероятность):** код, совпавший с зарезервированным слагом
> Filament, будет затенён — структурно решается только префиксом панели/резолвера
> (needs decision, не делал).
- **Где:** `routes/web.php` (`Route::get('/{code}', ResolveLink::class)`), `app/Providers/Filament/MainPanelProvider.php` (`->path('')`), `bootstrap/app.php` (`health: '/up'`).
- **Что не так:** `{code}` — односегментный catch-all без ограничения символов/длины.
  Он делит корневое пространство имён с Filament (логин `/login`, слаги ресурсов
  `/services`, `/links`, …, `/livewire/...`) и с `/up`. Если порядок регистрации
  когда-нибудь окажется «web.php раньше Filament», `/{code}` перехватит `/login`,
  `/up` и т.п. И наоборот: сгенерированный код, совпавший с зарезервированным
  путём, будет перехвачен Filament. Сейчас админка работает (значит, приоритет
  фактически на стороне Filament для известных путей), но это держится на неявном
  порядке загрузки провайдеров. Побочно: любой мусорный односегментный путь
  (`/favicon.ico`, `/robots.txt`) уходит в `ResolveLink` → 404 + расход per-IP бюджета
  лимитера.
- **Почему важно:** тихие коллизии — либо ломается ссылка, либо элемент админки/health,
  без явной ошибки. Это эксплуатационный риск.
- **Как исправить:**
  - Проверить фактический приоритет: `artisan route:list` (needs verification).
  - Ограничить код по алфавиту/длине Hashids: `->where('code', '[A-Za-z]{5,8}')` — как
    минимум выводит из-под перехвата короткие пути вроде `/up` и режет мусорные lookups.
    (Полностью коллизии с буквенными слагами Filament это не убирает — `login` тоже
    подходит под паттерн.)
  - Радикально и надёжно: вынести админку под префикс (`->path('admin')`) **или**
    резолвер под префикс (`/r/{code}`), развязав пространства имён.
- **Нужен тест:** да — feature-тест, что `/up` и `/login` доступны при наличии ссылок,
  и что валидный код резолвится (фиксирует решение).

### M6. Авторизация «всё или ничего»: любой пользователь = полный админ + Horizon + выпуск токенов
> **✅ РЕШЕНО (by design).** Владелец выбрал зафиксировать инвариант, а не вводить роли:
> «каждый `User` — полностью доверенный администратор». Задокументировано в
> `docs/ADMIN.md` → «Доступ и авторизация» (включая предупреждение, что создание учётки
> = выдача полного доступа, и путь отхода — флаг `users.is_admin`, если понадобится
> разделение прав). Кода не трогаем.
- **Где:** `app/Models/User.php::canAccessPanel()` → `return true;`; `app/Providers/HorizonServiceProvider.php::gate()` → `return $user !== null;`.
- **Что не так:** ролевой модели нет. Любой аутентифицированный пользователь видит все
  ресурсы, может править Services/Links/Settings/Users, выпускать API-токены сервисов
  (`ViewService::createToken`) и заходить в Horizon.
- **Почему важно:** нет least-privilege. Для одно-админского внутреннего инструмента
  приемлемо, но при добавлении второго пользователя это сразу эскалация. Поскольку
  пользователи заводятся прямо в панели — риск реальный.
- **Как исправить (needs decision):** если многопользовательский режим планируется —
  ввести хотя бы флаг `is_admin`/`role` и проверять его в `canAccessPanel()` и
  Horizon-gate; иначе — явно задокументировать «единственный доверенный админ» как
  инвариант развёртывания.
- **Нужен тест:** при введении ролей — да.

### M7. Аналитика по `clicks.created_at` без поддерживающего индекса + неэффективные бейджи на каждой странице
> **✅ ИСПРАВЛЕНО.** Добавлен индекс `clicks_created_at_index` (миграция); бейдж
> `ClickResource::getNavigationBadge()` переведён с `whereDate()` на range-фильтр
> `where('created_at', '>=', today())` (sargable). `StatsOverview`/`ClicksChart` уже
> были на range — теперь опираются на индекс. Guard-тест `ClickNavigationBadgeTest`.
- **Где:** `app/Filament/Widgets/StatsOverview.php`, `app/Filament/Widgets/ClicksChart.php`, бейджи `ClickResource::getNavigationBadge()` (`whereDate('created_at', today())->count()`), `CallbackResource` (по `status` — индекс есть), миграции `clicks`.
- **Что не так:** на `clicks` есть только композитные `(service_id, created_at)` и
  `(link_id, created_at)`; **отдельного `clicks(created_at)` нет**. Дашборд и бейджи
  фильтруют по `created_at` **без** ведущего `service_id`/`link_id`, поэтому композитные
  индексы не применяются → seq scan. Хуже: `ClickResource` бейдж использует
  `whereDate('created_at', ...)` (функция по колонке — не sargable) и выполняется на
  **каждой** загрузке любой страницы админки (бейджи в сайдбаре глобальны).
- **Почему важно:** для трекера кликов `clicks` — самая растущая таблица; на объёме
  дашборд и навигация по админке будут заметно тормозить.
- **Как исправить:**
  - Миграция: `clicks->index('created_at')` (или partial/BRIN при больших объёмах).
  - Бейдж клика: `->where('created_at', '>=', today())` вместо `whereDate(...)`
    (как уже сделано в `StatsOverview`), чтобы индекс работал.
- **Нужен тест:** нет (производительность); достаточно миграции + ручной EXPLAIN.

---

## Minor issues

> **✅ Все Minor закрыты.** m1 (убрана мёртвая `Callback::link`); m2 (`url:http,https`);
> m3 (лимиты `rules`/`callback_data` + стрип лишних ключей `condition.data`); m4
> (токены скоупятся `links:create` + enforcement на роуте); m5 (валидация уникального
> `priority`); m6 (дедуп `Condition` на create вместо 500); m7 (убран `ForceDelete`
> ссылок); m8 (`email_verified_at` в `$fillable`); m9 (UTC-группировка — комментарий,
> поведение корректно); m10 (фильтр ссылок — relationship+searchable); m11 (backoff
> 4 шага); m12 (нотификация «нет правил» после create); m13 (callback_data → JSON-редактор,
> nested round-trip); m14 (убрана лишняя транзакция в `ClickRecorder`). Каждый с тестом,
> кроме чисто-конфигурационных/комментарных (m7, m9, m10, m14 — покрыты существующими).

- **m1. Битая/мёртвая связь `Callback::link`.** `app/Models/Callback.php` подключает
  `BelongsToLink`, а в таблице `callbacks` нет `link_id` (см. миграцию) → `$callback->link`
  всегда `null`, а PHPDoc `@property-read Link|null $link` вводит в заблуждение. В
  Filament/сервисах не используется (проверено грепом) — рендерер берёт ссылку через
  `$click->link`. **Fix:** убрать трейт и PHPDoc, либо реализовать `hasOneThrough(Link, Click)`.
- **m2. `rules.*.url` без ограничения схемы.** `app/Http/Requests/StoreLinkRequest.php` —
  правило `url` (проверено в рантайме L13.6) пропускает `ftp://` и прочие не-веб-схемы как
  цель редиректа. `javascript:`/`data:` уже отклоняются, так что XSS в delayed/manual
  **не воспроизводится** — это hardening. **Fix:** `'url:http,https'` (и как
  defense-in-depth для `href="{{ $targetUrl }}"` в `resources/views/link/redirect.blade.php`).
- **m3. Нет верхних границ во входной валидации.** `rules` — `required|array|min:1` без
  `max`; `callback_data` — `array` без ограничения размера/глубины; лишние ключи в
  `condition.data` не вычищаются (попадают в jsonb и плодят почти-дубликаты в дедупе
  `conditions`). **Fix:** `rules` → `max:50` (или разумный предел); опционально валидировать
  `callback_data` по размеру; рассматривать только ожидаемые поля `condition.data`.
- **m4. Токены сервисов бессрочные и со всеми abilities.** `config/sanctum.php`
  `expiration => null`; `ViewService::createToken` → `createToken('service-token')` без
  abilities = `['*']`. **Fix (needs decision):** срок жизни и/или скоупы (`['links:create']`).
- **m5. Дубликат `priority` в `RulesRelationManager`.** Поле `priority` — свободный
  `TextInput` (default 1), а в БД `unique(['link_id','priority'])`. Две роли с одинаковым
  приоритетом → сырой DB-error. **Fix:** валидация уникальности в форме или авто-назначение
  `max(priority)+1`.
- **m6. Сырые ошибки БД на destructive-действиях справочников.** Создание `Condition` через
  Filament при дубле `(type, data)` упрётся в unique-индекс; удаление справочников
  (`Url`/`Condition`/`IpAddress`/`Referrer`/`UserAgent`), на которые ссылаются rules/clicks —
  в FK `restrictOnDelete`. Всплывёт 500 вместо понятного сообщения. **Fix:** дружелюбная
  обработка/`->before()`-проверка или `insertOrIgnore` на создание условия в админке.
- **m7. `ForceDeleteBulkAction` на ссылке с кликами.** `LinksTable` предлагает force-delete,
  но `clicks.link_id` — `restrictOnDelete`, поэтому форс-удаление ссылки с кликами кинет
  FK-ошибку. Это и есть задокументированный инвариант «клики переживают ссылку», но UX —
  сырой эксепшен. **Fix:** скрыть ForceDelete либо ловить ошибку и показывать нотификацию.
- **m8. `email_verified_at` не в `User::$fillable`.** `UserForm` имеет `DateTimePicker::make('email_verified_at')`,
  но поле отсутствует в `$fillable` → при сохранении тихо отбрасывается (поле выглядит
  редактируемым, но не сохраняется). **Fix:** добавить в `$fillable` или убрать из формы.
- **m9. Часовой пояс в агрегации кликов.** `ClicksChart` (`date(created_at)`) и бейдж
  (`whereDate`) зависят от session TimeZone БД; группировка по дню может «разъезжаться»
  UTC vs локаль. `app.timezone=UTC`, так что сейчас консистентно, но при вводе таймзоны
  пользователя стоит учесть.
- **m10. Фильтр `link_id` в `ClicksTable` грузит все ссылки.** `Link::query()->orderBy('code')->pluck('code','id')`
  тянет все ссылки в options. **Fix:** заменить на relationship/searchable-фильтр с
  ленивым поиском.
- **m11. Off-by-one в backoff колбеков.** `SendCallbackJob::backoff()` = 5 значений, но
  `tries=5` → используются только первые 4 задержки (между 5 попытками 4 промежутка). SDK
  документирует «5 шагов backoff». **Fix:** согласовать (`tries=6` под 5 задержек, либо
  убрать 5-й элемент и поправить доку).
- **m12. Ссылка из админки рождается без правил.** `CreateLink` создаёт `Link` без rules
  → визит даёт 404, пока правило не добавят через relation manager. **Fix:** подсказка/
  требование ≥1 правила, либо предупреждение в UI.
- **m13. `callback_data` в админке — только плоская карта строк.** `LinkForm` использует
  `KeyValue`, тогда как API принимает вложенные объекты (пример в `SDK_API.md`:
  `meta.referrer`). Админ не сможет точно отобразить/отредактировать вложенный шаблон.
  **Fix:** заменить на JSON-редактор (`Textarea`/code-field) либо задокументировать
  ограничение.
- **m14. Лишняя транзакция в `ClickRecorder::record`.** Один `INSERT` + резолверы
  справочников обёрнуты в `DB::transaction`. Под конкурентностью это сериализует вставки
  через блокировки `ON CONFLICT` без выигрыша в корректности (каждый резолвер уже
  race-safe сам по себе). **Fix (опционально):** убрать обёртку или оставить осознанно.

---

## Documentation mismatches

> **✅ Исправлено в этой итерации:** D1 (soft-delete = 404, доку привели к коду),
> D3 (справочники read-only), D4 (колбек ставится из `RecordClickJob`), D5
> (`ConditionHandler::validate()`), D6 (`condition: null` в ответе + backoff-шаги).
> D2 — уже снят (firstOrCreate race-safe).

- **D1. `ARCHITECTURE.md` (модель данных):** ✅ исправлено — `ARCHITECTURE.md` теперь
  явно говорит, что trashed-ссылка = отключена (публичный резолв 404), а админ всё ещё
  видит её. Выбрано: чинить **документацию** (поведение кода логично). Было: «`links` —
  soft deletes (trashed ссылки всё равно резолвятся в route binding'е `ResolveLink`)» —
  что не соответствовало `Link::findByCode()` (scope SoftDeletes → 404). **needs decision:** какое поведение
  целевое — если soft-delete должен «отключать» ссылку (логично, учитывая FK и просмотр
  trashed в админке), исправить **документацию**; если удалённые ссылки должны продолжать
  редиректить — менять `findByCode` (`withTrashed()`).
- **D2. `ARCHITECTURE.md` (создание ссылки):** «`resolveUrlId` — insertOrIgnore + SELECT
  (race-safe)» — формулировка неточна: код использует `firstOrCreate` (которая в Laravel
  13 race-safe через `createOrFirst` + savepoint). Итог (race-safety) верен, механизм —
  другой. Тривиально: либо поправить описание, либо привести к буквальному
  `insertOrIgnore + SELECT` ради единообразия с `resolveConditionId`. **Не баг** (см.
  снятый M1).
- **D3. `ADMIN.md` (заметки по ресурсам):** «Справочники (IpAddress, Referrer, UserAgent) —
  нет `EditPage`; в их таблицах остался устаревший `EditAction` (висит на зачистку)» —
  **уже не так**: таблицы используют только `ViewAction` (зачистка сделана коммитом
  `9e7e5d5`). Также `Condition` теперь read-only (нет edit) — доку стоит обновить под
  «read-only справочники».
- **D4. `ARCHITECTURE.md` (резолвинг, шаг 5):** колбек ставится в очередь «из `ResolveLink`».
  Фактически — из `RecordClickJob` через `CallbackDispatcher` (нужен `click.id`). Уточнить.
- **D5. `ARCHITECTURE.md` (Conditions):** интерфейс описан как `matches/rules/type`, но в
  `ConditionHandler` есть ещё `validate(array): array`. Дописать.
- **D6. `SDK_API.md`:** (а) в примере ответа `condition` всегда заполнен; для правила без
  условия API возвращает `"condition": null` (проверено) — стоит показать этот случай.
  (б) «5 шагов backoff» vs `tries=5` (см. m11).

> Примечание: `DEVELOPMENT.md` «Сейчас 48 тестов» — **актуально** (прогон дал ровно
> 48 passed). Расхождения нет.

---

## Missing tests

Уже написаны (в `tests/Feature/Regressions/`):

- **T1 (C1) — `TrustedProxyIpSpoofingTest`** — RED, проверяет, что поддельный
  `X-Forwarded-For` от недоверенного клиента игнорируется. Зелёным станет после фикса C1.
- **T3 (M2) — `RecordClickJobIdempotencyTest`** — RED, повторный прогон джоба не должен
  плодить второй `Click`/`Callback`. Зелёным станет после ключа идемпотентности.
- **T9 (guard) — `SettingEditUniqueKeyTest`** — GREEN, фиксирует, что edit настройки
  работает (M4 снят).
- ~~T2 (M1)~~ — удалён (M1 снят как ложноположительное).

Ещё нужно добавить:

- **T4:** `SendCallbackJob` на `5xx`/timeout → ретраи с backoff, после исчерпания `tries`
  статус `Failed` и `attempts` корректен. Сейчас покрыты только success и 4xx.
- **T5 (M3):** `callback_url`, отвечающий `30x` на внутренний IP, **не** приводит к запросу
  на внутренний адрес (после отключения follow-redirect).
- **T6:** `LinkRuleResolver` — выбор первого подходящего правила по `priority`: несколько
  правил, часть с `time_before`, проверить, что выбирается верное и срабатывает fall-through
  на следующее/404. Прямого теста резолвера нет.
- **T7:** `TimeBeforeConditionHandler::matches()` — матч при `now < before`, не-матч при
  истёкшем; невалидная `data` → fail-closed (`report` + `false`).
- **T8 (D1):** зафиксировать решённое поведение резолва soft-deleted ссылки.
- **T10:** контракт `POST /api/links` — полная форма 201-ответа против `SDK_API.md`
  (включая `condition: null`). Сейчас проверяется только валидация, не сериализация.
- **T11:** семантика `forward_query` — параметры цели имеют приоритет, входящие
  допишутся; для `forward_query=false` query визитёра игнорируется.

---

## Suggested refactoring plan

Порядок — от безопасности к документации.

1. **Безопасность / критичное**
   1. C1 — сузить `trustProxies` до подсети прокси (red-тест `TrustedProxyIpSpoofingTest`
      уже ждёт).
   2. M3 — отключить follow-redirect в колбеках; опционально пиннить IP (+ T5).
   3. M5 — проверить `route:list`, ограничить `{code}` (`[A-Za-z]{5,8}`) или вынести
      панель/резолвер под префикс.
   4. M6 — принять решение по ролевой модели (или зафиксировать инвариант в доке).
2. **Баги поведения**
   1. M2 — идемпотентность `RecordClickJob` (ключ идемпотентности; needs decision по
      миграции `clicks.uuid`) — red-тест `RecordClickJobIdempotencyTest` уже ждёт.
   2. m2 — `url:http,https`; m5/m6/m7 — дружелюбная обработка constraint-ошибок; m8 —
      `email_verified_at` в `$fillable` или из формы.
3. **Тесты** — T4, T6, T7, T10, T11 (закрыть резолвер, condition-handler, контракт API,
   forward_query, ретраи колбеков).
4. **Рефакторинг / производительность** — M7 (индекс `clicks(created_at)` + бейдж через
   range); m1 (убрать мёртвую `Callback::link`); m10 (фильтр ссылок); m14 (транзакция);
   m4/m13 (токены, nested callback_data).
5. **Документация** — D1–D6 в тех же коммитах, что и соответствующие правки.

---

## Files likely to change

- `bootstrap/app.php` — trusted proxies (C1).
- `app/Jobs/RecordClickJob.php`, `app/Services/Links/Clicks/ClickRecorder.php`
  (+ возможная миграция `clicks.uuid`), `app/Http/Controllers/ResolveLink.php`,
  `app/Services/Links/Callbacks/CallbackDispatcher.php` — идемпотентность (M2).
- `app/Jobs/SendCallbackJob.php` — отключить follow-redirect (M3), backoff/tries (m11).
- `app/Filament/Resources/Settings/Schemas/SettingForm.php` — опц. `disabledOn('edit')`
  на `key` (косметика; M4 снят — баг отсутствует).
- `routes/web.php` и/или `app/Providers/Filament/MainPanelProvider.php` — приоритет/
  префикс маршрутов (M5).
- `app/Models/User.php`, `app/Providers/HorizonServiceProvider.php` — авторизация (M6).
- `database/migrations/*` — индекс `clicks(created_at)` (M7); опц. `clicks.uuid` (M2).
- `app/Filament/Resources/Clicks/ClickResource.php` — бейдж по range (M7).
- `app/Http/Requests/StoreLinkRequest.php` — `url:http,https`, лимиты (m2, m3).
- `app/Models/Callback.php` — убрать `BelongsToLink` (m1).
- `app/Filament/Resources/Links/RelationManagers/RulesRelationManager.php` — priority (m5).
- `app/Filament/Resources/Links/Tables/LinksTable.php` — ForceDelete UX (m7).
- `app/Filament/Resources/Users/Schemas/UserForm.php` + `app/Models/User.php` — `email_verified_at` (m8).
- `app/Filament/Resources/Clicks/Tables/ClicksTable.php` — фильтр ссылок (m10).
- `config/sanctum.php` / `ViewService` — токены (m4).
- `docs/ARCHITECTURE.md`, `docs/ADMIN.md`, `SDK_API.md` — D1–D6.
- `tests/Feature/Regressions/**` — T1, T3 (написаны, RED), T9 (написан, green-guard);
  далее `tests/Feature/**`, `tests/Unit/**` — T4, T6, T7, T10, T11.
