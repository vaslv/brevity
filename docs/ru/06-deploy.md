# Продакшен и деплой

Факты об эксплуатации, которые нельзя вывести из кода, плюс ранбук
переезда деплоя (выполнен 2026-06-10, пригодится при смене сервера или
деплой-пользователя).

## Развёртывание своего инстанса

GitHub Actions публикует готовый образ `ghcr.io/vaslv/brevity` для
`linux/amd64`. На сервере не нужны PHP, Composer, Node или GitLab.
Нужны Docker с Compose v2, свободные порты 80/443 и DNS-записи всех
обслуживаемых доменов, направленные на сервер.

1. Скопировать из репозитория `docker-compose.production.yml`,
   `docker-compose.db.yml` и `.env.production.example` в отдельную директорию.
   Создать `.env`: `cp .env.production.example .env`, затем `chmod 600 .env`.
   При обновлении существующей установки сохранить её `.env` и ключи.
2. В `.env` оставить `LARAVEL_IMAGE=ghcr.io/vaslv/brevity:latest`
   или указать опубликованный релизный тег / `sha-<полный SHA коммита>`.
   Для форка заменить владельца и название репозитория.
   Образ появится после первого успешного запуска workflow **Docker image**.
   Для скачивания без авторизации владелец должен сделать пакет Public
   в GitHub → Packages → пакет → Package settings → Change visibility.
   Для приватного пакета выполнить `docker login ghcr.io -u <github-user>`
   и ввести PAT (classic) с `read:packages` в запросе пароля.
3. Сгенерировать **два отдельных значения** для новой установки:

   ```bash
   docker run --rm --entrypoint php ghcr.io/vaslv/brevity:latest -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
   docker run --rm --entrypoint php ghcr.io/vaslv/brevity:latest -r 'echo bin2hex(random_bytes(16)).PHP_EOL;'
   ```

   Записать первое в `APP_KEY`, второе в `HASHIDS_SALT`. Не менять соль
   после создания ссылок. Заполнить `APP_URL=https://<технический-хост>`,
   `APP_HOST` (все домены через запятую), `LETSENCRYPT_EMAIL`, SMTP и `DB_*`.
   Оставить `REDIS_HOST=redis`. Геолокация опциональна (`GEOIP_LICENSE_KEY`).
4. Выбрать **один** вариант PostgreSQL:

   **База в Docker:** `DB_HOST=pgsql`, `DB_PORT=5432`, заполнить
   `DB_DATABASE`, `DB_USERNAME`, непустой `DB_PASSWORD`.

   ```bash
   docker compose --env-file .env -f docker-compose.production.yml -f docker-compose.db.yml up -d --pull always --wait --wait-timeout 300
   ```

   Данные сохраняются в `brevity_pgsql_data`, порт базы наружу не открыт.
   Изменение пароля в `.env` не меняет пароль в уже созданной базе.

   **Существующая внешняя база:** указать доступные из контейнеров
   `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` и,
   при необходимости, `DB_SSLMODE`. База должна существовать, а пользователь
   должен иметь права на миграции. Для PostgreSQL на Docker-хосте используйте
   `host.docker.internal`, а не `localhost`. Отдельный PostgreSQL-контейнер
   не создаётся:

   ```bash
   docker compose --env-file .env -f docker-compose.production.yml up -d --pull always --wait --wait-timeout 300
   ```

   Планировщик применяет миграции и синхронизирует домены перед запуском
   расписания. Web и Horizon ждут его готовности. Прокси получает TLS-сертификаты.
5. Создать первого администратора:

   ```bash
   docker compose --env-file .env -f docker-compose.production.yml exec web php artisan make:filament-user
   ```

   Вход: `https://<технический-хост>/login`. Остальные домены обслуживают
   короткие ссылки, но не админку/API/Horizon.

## Сборка в GitHub и обновления

Workflow `.github/workflows/docker.yml`:

- `pull_request`: проверка обеих Compose-конфигураций и сборка без публикации;
- push в `main`: публикация `latest` и `sha-<полный SHA>` в GHCR;
- push версионного тега вида `1.2.3`: публикация только релизного тега,
  без изменения `latest` и `sha-<полный SHA>`;
- `workflow_dispatch`: ручной запуск; публикация только для `main` и тегов.

Используется штатный `GITHUB_TOKEN` с `packages: write`; дополнительные
секреты registry, SSH или базы не нужны. Политика организации должна
разрешать GitHub Actions создавать пакеты. Workflow не подключается к серверу.
Отдельный workflow `CI` продолжает запускать тесты и статический анализ.

Версия в панели и `SENTRY_RELEASE` вшиты в образ: релизный Git-тег либо
`sha-<полный SHA>`. Не задавать `APP_VERSION` в серверном `.env`.
`latest` указывает на сборку из `main`; его имя не является версией приложения.
Релизные и SHA-теги могут перезаписываться при повторной сборке. Для фиксации
конкретного образа задать `LARAVEL_IMAGE=ghcr.io/vaslv/brevity@sha256:<digest>`,
подставив digest опубликованного образа.

Для обновления сделать резервную копию базы, изменить `LARAVEL_IMAGE`
в серверном `.env` и повторить выбранную команду `up` выше. Это обычное
обновление Compose с возможным перерывом обслуживания. Не выполнять
`down -v`: эта команда удалит постоянные тома. Возврат старого образа
не отменяет миграции базы.

Старый `.gitlab-ci.yml` отключён через `workflow.rules: when: never` и
сохранён как исторический пример. Раздел про перенос старого деплоя ниже
описывает прежнюю инфраструктуру, а не текущую процедуру запуска.

## Топология

- Прод: Docker Compose, проект **`brevity`** (имя зафиксировано в
  `docker-compose.production.yml`, от директории запуска не зависит).
- Именованные тома: `brevity_nginx_html`, `brevity_nginx_certs`,
  `brevity_nginx_acme` (Let's Encrypt), `brevity_redis_data`. Потеря
  томов certs/acme = перевыпуск сертификатов.
- Приложение: Octane (FrankenPHP), очереди — Horizon (`redis`), отдельная
  очередь `callbacks` (изоляция от записи кликов).

## Ручные операции на сервере

Перед остановкой контейнеров дать Horizon дообработать очередь:

```bash
docker exec laravel-horizon php artisan horizon:terminate
```

Проверка здоровья:

```bash
docker compose --env-file .env -f docker-compose.production.yml ps
curl -I https://brevity.example.com/up
docker exec laravel-horizon php artisan horizon:status
```

## Незакрытые пост-деплой шаги (этапы развития 1–4)

Выполнить **один раз** при первом деплое веток с этими этапами; после —
вычеркнуть отсюда. Миграции применяются автоматически (scheduler).

**Деплой этапов 1–3 — в тихое окно.** Причины (ревью 2026-07-12):

- Миграции строят на `clicks` четыре индекса обычным `CREATE INDEX`
  (блокирующим). На таблице в миллионы строк это придерживает запись
  кликов на время сборки — редиректы не страдают (учёт асинхронный), но
  Horizon копит бэклог. При боевом масштабе имеет смысл заранее создать
  их `CREATE INDEX CONCURRENTLY` руками до `migrate` (тогда миграция
  пройдёт мгновенно) — либо просто деплоить в окно низкого трафика.
- Миграция этапа 3 **дропает `rules.condition_id`** (перенос на pivot
  `condition_rule`). Старый код этой колонки ещё ждёт, поэтому в окне
  «новая схема + не обновлённый код» он мисроутит условные правила и
  роняет 500 на запись. Держать окно несовместимости коротким:
  миграции и выкладка кода — единым деплоем, без долгого промежутка.
- `clicks:rebuild-counters` берёт `ACCESS EXCLUSIVE` на счётчики на весь
  агрегат по `clicks` — на это время встают и редиректы ссылок с
  `max_clicks` (их `isAlive` читает сумму счётчиков). Гонять в то же
  окно, а не под нагрузкой.

**Этапы 1 и 3 (боты + счётчики)** — строго в этом порядке, после
миграций:

```bash
docker compose --env-file .env -f docker-compose.production.yml exec web php artisan user-agents:detect-bots
docker compose --env-file .env -f docker-compose.production.yml exec web php artisan clicks:rebuild-counters   # только ПОСЛЕ detect-bots
```

Порядок обязателен: `rebuild-counters` берёт флаг бота из `user_agents`,
поэтому флаги должны быть проставлены раньше. `rebuild-counters`
идемпотентен (TRUNCATE + пересчёт), можно повторять. **Этап 3 сменил
библиотеку детекции ботов** (crawler-detect → device-detector), поэтому
`detect-bots` обязателен и при деплое этапа 3: вердикт для части UA
меняется, счётчики надо пересобрать.

**Этап 2 (жизненный цикл + API)** — новые env-ключи серверного `.env`
(значения по умолчанию безопасны, можно не задавать сразу):

- `TRACKING_IGNORED_SOURCES=` — офисные/мониторинговые IP и CIDR через
  запятую (пусто = трекать всё);
- `TRACKING_DISABLE_PARAM=` — имя query-параметра отключения трекинга
  (пусто = выкл). **Это фактически секрет:** знание имени = обход учёта
  клика, колбека и лимита `max_clicks` (визит с параметром не тратит
  бюджет ссылки). Задавать неугадываемое имя и ротировать при утечке;
  не публиковать в партнёрских доках. Параметр вырезается из
  проброшенного `forward_query`, но виден в браузере/истории.
- `TRACKING_IP_RETENTION_DAYS=90` — срок хранения сырых IP;
- `LINK_DELETE_CONFIRM_THRESHOLD=15` — порог предупреждения при удалении.

`ips:prune` включён в scheduler (daily) — при первом прогоне на старой
базе обезличит все клики старше срока за один заход; специальных
действий не требует.

**Этап 4 (геолокация) — в то же тихое окно, что этапы 1 и 3.** Вопреки
первоначальной оценке, миграция НЕ мгновенная (ревью 2026-07-13, r26):
`clicks.geo_location_id` добавляется nullable (это дёшево — метаданные, без
переписи), но частичный индекс `WHERE geo_location_id IS NOT NULL` всё равно
сканирует весь heap `clicks` (предикат лишь фильтрует, какие кортежи попадут в
индекс — страницы Postgres не пропускает), а FK `restrictOnDelete` — второй
полный скан (RI_Initial_Check), и оба идут в транзакции миграции под ACCESS
EXCLUSIVE от `ADD COLUMN`. На таблице в миллионы строк это придерживает запись
кликов на время двух сканов. Как и на этапах 1/3: либо деплоить в окно низкого
трафика, либо при боевом масштабе заранее до `migrate` руками — `ADD COLUMN`
отдельно, FK как `ADD CONSTRAINT … NOT VALID` + отдельный `VALIDATE CONSTRAINT`
(берёт лишь SHARE UPDATE EXCLUSIVE), и `CREATE INDEX CONCURRENTLY` (тогда
миграция пройдёт мгновенно). После миграции:

- Новые env-ключи серверного `.env` (уже в `.env.example`; без ключа гео просто
  выключено, клики остаются без локации — click-запись не страдает):
  - `GEOIP_LICENSE_KEY=` — лицензионный ключ MaxMind; пусто = загрузка и
    автообновление базы выключены;
  - `GEOIP_DATABASE_PATH=` — путь к `.mmdb` (пусто = `storage/app/geoip/GeoLite2-City.mmdb`);
  - `GEOIP_MAX_AGE_DAYS=30` — порог автообновления «по трафику».
- Первичная загрузка базы (после установки ключа):

  ```bash
  docker compose --env-file .env -f docker-compose.production.yml exec web php artisan geo:download-db
  ```

  База лежит на общем named-volume `geoip_data` (смонтирован во все
  laravel-контейнеры), поэтому загрузка из `laravel-web` сразу видна ридеру в
  `laravel-horizon`, где происходят lookups, и переживает пересоздание
  контейнеров при деплое. Дальше база держится свежей сама: клик асинхронно
  пингует проверку возраста и при устаревании ставит фоновую перекачку (cron не
  нужен). Первичную установку воркер, ещё не открывавший ридер, подхватывает
  сразу; при **ручной перезакачке поверх уже открытой** базы octane-воркеры
  продолжат читать старый inode до рестарта — если нужна немедленная замена,
  `docker compose --env-file .env -f docker-compose.production.yml exec web php artisan octane:reload` (гео-данные меняются
  медленно, обычно не требуется).
- Догеолокация исторических кликов — **после** установки базы (без неё команда
  сразу выходит, ничего не сканируя). Идемпотентно и возобновляемо, можно гонять
  в фоне отдельными заходами:

  ```bash
  docker compose --env-file .env -f docker-compose.production.yml exec web php artisan geo:locate-clicks
  ```

**`trustProxies` — сузить до реального прокси.** `bootstrap/app.php`
сейчас доверяет всем приватным подсетям (`10/8`, `172.16/12`,
`192.168/16`). За Docker-шлюзом `REMOTE_ADDR` каждого запроса — приватный
адрес шлюза, так что `X-Forwarded-For` спуфится. Это влияет на
IP-условия (`ip_address`), список `TRACKING_IGNORED_SOURCES` и per-IP
лимитер резолва. Перед активным использованием IP-условий как гейта
сузить список до CIDR реального фронт-прокси. До тех пор IP-условия —
эвристика, не средство доступа.

Контрактные изменения для интеграторов: метка `is_bot` в каждом колбеке
(этап 1), пути перешли на `/api/v1` с RFC 7807-ошибками, legacy `/api/*`
deprecated (этап 2). Уведомить партнёров — см. [03-api.md](./03-api.md).

## Секреты и конфиги вне git

Серверный `.env`: `DB_PASSWORD`, `APP_KEY`, `HASHIDS_SALT` (независим от
`APP_KEY` — см. [08-decisions.md](./08-decisions.md)), SMTP-креды.
Конвенция: ключи (не значения) `.env` / `.env.example` / `.env.production`
держатся синхронными; `.env.production.example` (публичный шаблон)
пополняется, когда новый ключ актуален для прода.

## Исторический ранбук GitLab: переезд деплоя (новый сервер / деплой-пользователь)

Впервые выполнен 2026-06-10 (root → `brevity`). Окно простоя между
остановкой и подъёмом — 2–5 минут.

1. **Пользователь** (на сервере, под root): `useradd -m -s /bin/bash
   <user>`, `usermod -aG docker <user>`, скопировать
   `authorized_keys` в `~/.ssh` (права 700/600). Проверка с локальной
   машины: `ssh <user>@<host> docker ps` — без sudo.
2. **Остановить старый проект, не трогая тома** (сначала
   `horizon:terminate`, дождаться остановки контейнера):

   ```bash
   cd <старая-директория>
   LARAVEL_IMAGE=dummy docker compose -p <старый-проект> --env-file .env \
     -f docker-compose.production.yml down     # БЕЗ -v!
   ```

3. **Перенести тома под новый префикс** (переименования томов в Docker
   нет — копируем содержимое):

   ```bash
   for v in nginx_html nginx_certs nginx_acme redis_data; do
     docker volume create brevity_$v
     docker run --rm -v <старый>_$v:/from:ro -v brevity_$v:/to alpine \
       sh -c "cp -a /from/. /to/"
   done
   ```

4. **CI-переменные GitLab** (Settings → CI/CD → Variables):
   `DEPLOY_USER=<user>`, `HEALTHCHECK_URL=https://<host>/up`. Флаг
   Protected — только если деплойные теги protected.
5. **Задеплоить**: создать и запушить новый тег.
6. **Проверка**: `docker compose --env-file .env -f docker-compose.production.yml ps` (up/healthy), `curl -I
   .../up` (200), `horizon:status` (running), `docker logs
   acme-companion --since 10m` (**не** выпускает новые сертификаты —
   признак, что тома certs переехали), `docker exec redis redis-cli
   dbsize` (не пустой).
7. **Уборка** — только через день-два после успешной проверки: удалить
   старые тома и `.env`/compose-файл из старой директории.

**Откат**: пока старые тома целы — поднять старый проект из старой
директории (`LARAVEL_IMAGE=<реальный-образ> docker compose -p
<старый-проект> ... up -d`).

**Грабли первого прогона**: `LARAVEL_IMAGE` обязателен даже для `down`
(compose падает на валидации); строка с `: ` внутри plain-скаляра в
`script:` `.gitlab-ci.yml` парсится как словарь — оборачивать в одинарные
кавычки целиком; пайплайн по существующему тегу сам не перезапустится.
