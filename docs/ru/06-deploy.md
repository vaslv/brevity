# Продакшен и деплой

Факты об эксплуатации, которые нельзя вывести из кода.

## Как проходит релиз

В production-установке два вида Compose-проектов:

- **Стабильный проект `brevity`** (`docker-compose.production.yml`): Caddy
  приложения, Redis и при `BREVITY_DATABASE=bundled` — PostgreSQL
  (`docker-compose.db.yml`). Они переживают релизы.
- **Поколение `brevity-gen-<N>`** (`docker-compose.generation.yml`): web
  (Octane на FrankenPHP), Horizon и планировщик одного digest образа.
  Каждый релиз запускает новое поколение рядом с работающим.

Caddy приложения — веб-сервер перед FrankenPHP и переключатель поколений.
Его конфигурация — `Caddyfile` из репозитория; во время релиза меняется
только `caddy/conf/upstream.caddy` — одна строка с активным поколением.

`sh deploy-host.sh deploy <образ>` выполняет шаги под блокировкой хоста:

1. Скачивает образ и определяет digest. Пока ничего не изменено.
2. Готовит вход: проверяет каталоги и настройки, держит Redis (и
   встроенный PostgreSQL) запущенными без пересоздания, запускает Caddy,
   если он не работает, применяет изменённый `Caddyfile` через
   `caddy validate` и `caddy reload`.
3. Останавливает планировщик и Horizon активного поколения (Horizon
   дорабатывает текущие задачи, до 360 с).
4. Выполняет `migrate --force` и `domains:sync` из нового образа.
5. Запускает новый web и ждёт его health-check.
6. Проверяет новый web изнутри Caddy, записывает маршрут, выполняет
   `caddy validate` и `caddy reload`.
7. Проверяет `/up` и `/login` через Caddy: оба должны ответить 200 с
   `X-Brevity-Generation: <N>`.
8. Запускает новые планировщик и Horizon и ждёт их health-check.
9. Записывает активное поколение в `.deploy/active.env` (прежняя запись
   переходит в `.deploy/previous.env`).
10. Ждёт, пока у Caddy не останется запросов к прежнему web (до 300 с), и
    удаляет прежнее поколение.

Сбой до шага 9 возвращает прежний маршрут, удаляет кандидата и снова
запускает остановленные фоновые роли: прежнее поколение продолжает
работать. **Миграции не откатываются**: они должны оставаться
совместимыми с кодом работающего поколения (сначала добавлять колонки и
таблицы, удалять — в следующем релизе).

Следствия:

- HTTP работает без простоя. Обработка очередей и задачи планировщика
  стоят с шага 3 до шага 8 (в основном это время миграций); задачи ждут в
  Redis.
- Во время переключения одновременно работают два web: хост должен
  вмещать оба.
- Caddy 2.11 при reload может сбросить соединение, ждущее в очереди
  прежнего listener; запросы в полёте завершаются.
- Откат — деплой более раннего digest (`IMAGE` в `.deploy/previous.env`).
  Схема БД более нового релиза остаётся.

## Размещение на сервере

В каталоге установки (`/opt/brevity` в production):

| Путь | Содержимое |
|---|---|
| `docker-compose.*.yml`, `Caddyfile`, `deploy-host.sh` | Загружаются workflow деплоя при каждом релизе |
| `.env` | Настройки и секреты установки, права `600` |
| `.deploy/` | Блокировка, счётчик поколений, `active.env`, `previous.env`, маркер `database-ready` |
| `caddy/conf/` | `Caddyfile` и `upstream.caddy`, их пишет `deploy-host.sh` (`0755` / `0644`) |
| `caddy/data/`, `caddy/config/` | Данные Caddy: ACME-аккаунт и сертификаты. Владелец UID `65532`, права `0700`, создаёт root один раз. В режиме public `caddy/data` входит в бэкап |

Объекты Docker: тома `brevity_redis_data`, `brevity_geoip_data` (база
MaxMind, общая для всех поколений) и `brevity_pgsql_data` (встроенная БД);
сети `brevity_proxy` (Redis, встроенный PostgreSQL) и `brevity-apps`
(Caddy и web, закреплённая подсеть).

## Настройки

`deploy-host.sh` читает эти ключи из `.env` (шаблон —
`.env.production.example`) и отказывает при несогласованных значениях до
любых изменений:

| Ключ | Значение |
|---|---|
| `CADDY_MODE` | `public`: Brevity — единственный сайт на хосте, Caddy занимает 80/443 и получает сертификаты для `APP_HOST`. `behind-host`: веб-сервер хоста завершает TLS и проксирует на `127.0.0.1:CADDY_PORT` |
| `ACME_EMAIL`, `TLS_MAX` | Режим public: контакт ACME; верхняя версия TLS (по умолчанию `tls1.3`) |
| `CADDY_PORT` | Режим behind-host: loopback-порт Caddy приложения (по умолчанию `8082`) |
| `CADDY_TRUSTED_PROXY` | Режим behind-host: шлюз сети `brevity-apps`, с которого подключается веб-сервер хоста. Единственный источник, чьи `X-Forwarded-*` Caddy сохраняет |
| `BREVITY_APPS_SUBNET` | Закреплённая подсеть `brevity-apps` (по умолчанию `172.31.250.0/24`) |
| `TRUSTED_PROXIES` | Равен `BREVITY_APPS_SUBNET`: приложение доверяет только своему Caddy |
| `BREVITY_DATABASE` | `external`, `bundled` или `host`; для `host` нужен ещё `DATABASE_NETWORK` — Docker-сеть PostgreSQL хоста |
| `APP_HOST` | Все обслуживаемые имена, технический домен первым. Caddy обслуживает ровно их; неизвестное имя сертификат не получает |

Версия в панели и `SENTRY_RELEASE` вшиты в образ. Не задавать
`APP_VERSION` в `.env`.

## Веб-сервер хоста (behind-host)

Веб-сервер хоста завершает TLS для всех имён из `APP_HOST`, выставляет
HSTS и редирект с HTTP на HTTPS и проксирует в Caddy приложения. Пример
сайта для Caddy на хосте (`/etc/caddy/sites/brevity.caddy`):

```caddyfile
brevity.example.com, lnk1.example.com {
	tls {
		protocols tls1.2 tls1.3
	}
	header Strict-Transport-Security "max-age=31536000"
	request_body {
		max_size 20MB
	}
	reverse_proxy 127.0.0.1:8082 {
		header_up X-Forwarded-Port 443
		header_up -X-Forwarded-Prefix
	}
}
```

В глобальных опциях Caddy хоста остаются `protocols h1 h2` и
`ocsp_stapling off`, а его `write_idle` больше 60-секундного таймаута
чтения маршрута приложения. Применение — `caddy validate`, затем
`systemctl reload caddy`. Caddy приложения публикует порт только на
`127.0.0.1` и доверяет `X-Forwarded-*` только шлюзу `brevity-apps`.

## Переход со схемы без поколений

До поколений production запускал web, Horizon и планировщик в проекте
`brevity` (`brevity-web-1` на `127.0.0.1:8081`, `brevity-horizon-1`,
`brevity-scheduler-1`), а веб-сервер хоста проксировал на порт 8081. Переход —
один деплой и изменение на хосте; HTTP всё это время работает.

1. Сделать бэкап базы.
2. В `.env` задать `CADDY_MODE=behind-host`, `CADDY_PORT=<свободный loopback-порт>`,
   `BREVITY_APPS_SUBNET=<свободная /24>`, `TRUSTED_PROXIES=<та же
   подсеть>`, `CADDY_TRUSTED_PROXY=<первый адрес подсети>`,
   `BREVITY_DATABASE=host` и `DATABASE_NETWORK=<сеть PostgreSQL хоста>`;
   `DB_HOST` не менять. Старый web прочитал конфигурацию при старте, его
   это не затрагивает.
3. От root, один раз: `install -d -m 700 -o 65532 -g 65532
   /opt/brevity/caddy/data /opt/brevity/caddy/config`.
4. Запустить **Actions → Deploy production** с тегом релиза. Скрипт
   остановит старые Horizon и планировщик (и снимет у них политику
   перезапуска), выполнит миграции, запустит поколение 1 и Caddy на
   `127.0.0.1:<CADDY_PORT>`. Старый web продолжает работать через порт 8081.
5. На хосте перевести все сайты Brevity на `127.0.0.1:<CADDY_PORT>`,
   затем `caddy validate` и `systemctl reload caddy` (с Nginx на хосте —
   поменять только `proxy_pass`, заголовки forwarding оставить, затем
   `nginx -t` и `systemctl reload nginx`). Проверить, что
   `curl -sI https://<технический-домен>/up` возвращает
   `X-Brevity-Generation`.
6. `sh /opt/brevity/deploy-host.sh retire-legacy` удалит старые web,
   Horizon и планировщик (и `nginx-proxy` / `acme-companion`, если есть).
7. Удалить оставшиеся тома старой схемы (`brevity_nginx_*`) после
   проверки.

До шага 6 путь назад такой: вернуть сайт хоста на 8081, затем
`docker update --restart=always brevity-horizon-1 brevity-scheduler-1` и
`docker start brevity-horizon-1 brevity-scheduler-1`.

## Развёртывание своего инстанса

GitHub Actions публикует `ghcr.io/vaslv/brevity` для `linux/amd64`. На
сервере нужен Docker с Compose v2. В режиме public — ещё свободные порты
80/443 и DNS-записи A всех обслуживаемых имён на сервер; AAAA-записи не
публиковать, пока сети Docker только IPv4: иначе все IPv6-посетители
приходят в Caddy с одного адреса моста.

1. Скопировать `docker-compose.production.yml`,
   `docker-compose.generation.yml`, `docker-compose.db.yml`,
   `docker-compose.host.yml`, `Caddyfile`, `deploy-host.sh` и
   `.env.production.example` из тега релиза в отдельный каталог.
   Выполнить `cp .env.production.example .env` и `chmod 600 .env`. При
   обновлении сохранить существующий `.env` и ключи.
2. Образ доступен после первого успешного запуска workflow **Docker
   image**. Для анонимного pull владелец делает пакет Public: GitHub →
   Packages → пакет → Package settings → Change visibility. Для
   приватного пакета — `docker login ghcr.io -u <github-user>` и classic
   PAT с `read:packages` в ответ на запрос пароля.
3. Для новой установки сгенерировать два независимых секрета:

   ```bash
   docker run --rm --entrypoint php ghcr.io/vaslv/brevity:<релиз> -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
   docker run --rm --entrypoint php ghcr.io/vaslv/brevity:<релиз> -r 'echo bin2hex(random_bytes(16)).PHP_EOL;'
   ```

   Первый — `APP_KEY`, второй — `HASHIDS_SALT`. Соль не менять после
   появления ссылок. Заполнить `APP_URL=https://<технический-домен>`,
   `APP_HOST` (все домены через запятую), настройки режима выше, SMTP и
   `DB_*`. `REDIS_HOST=redis` оставить. GeoIP необязателен
   (`GEOIP_LICENSE_KEY`).
4. Выбрать **один** вариант PostgreSQL:
   - **Встроенный:** `BREVITY_DATABASE=bundled`, `DB_HOST=pgsql`,
     `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME` и непустой
     `DB_PASSWORD`. Данные — в `brevity_pgsql_data`, порт наружу не
     публикуется. Изменение `.env` не меняет пароль уже
     инициализированной базы.
   - **Внешний:** `BREVITY_DATABASE=external` и доступные `DB_HOST`,
     `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` и при
     необходимости `DB_SSLMODE`. База должна существовать, у пользователя
     — права на миграции. `localhost` — это сам контейнер; для PostgreSQL
     на Docker-хосте — `host.docker.internal`.
5. Один раз подготовить каталоги и разрешить первую миграцию:

   ```bash
   sudo install -d -m 700 -o 65532 -g 65532 caddy/data caddy/config
   mkdir -p .deploy && chmod 700 .deploy && touch .deploy/database-ready
   ```

   `.deploy/database-ready` создавать **только когда база готова**; после
   восстановления — сначала проверить бэкап. Без маркера скрипт не
   запускает миграции.
6. Выкатить и создать первого администратора:

   ```bash
   sh deploy-host.sh deploy ghcr.io/vaslv/brevity:<релиз>
   sh deploy-host.sh artisan make:filament-user
   ```

   Вход — `https://<технический-домен>/login`. Остальные домены
   обслуживают только короткие ссылки, без панели, API и Horizon.

## Сборка в GitHub и обновления

Workflow `.github/workflows/docker.yml`:

- `pull_request`: проверка Compose-конфигураций, тест скрипта деплоя и
  сборка без публикации;
- push в `main`: публикация `latest` и `sha-<полный SHA>` в GHCR;
- push версионного тега вида `1.2.3`: публикация только релизного тега,
  без изменения `latest` и `sha-<полный SHA>`;
- `workflow_dispatch`: ручной запуск; публикация только для `main` и тегов.

Используется штатный `GITHUB_TOKEN` с `packages: write`; дополнительные
секреты registry, SSH или базы не нужны, к серверу workflow не
подключается. Отдельный workflow `CI` запускает тесты и статический анализ.

Релизные и SHA-теги могут перезаписываться при повторной сборке;
`deploy-host.sh` при скачивании переводит тег в digest и запускает именно
его.

Production обновляется через **Actions → Deploy production → Run
workflow** на `main` с опубликованным тегом (`1.12.0`) или
`sha256:<digest>`. Новый образ сам по себе сервер не обновляет. Workflow
работает в GitHub Environment `production`:

- Variables: `DEPLOY_HOST` (адрес сервера), `DEPLOY_USER` (SSH-пользователь).
- Secrets: `DEPLOY_SSH_KEY` (отдельный приватный ключ деплоя),
  `DEPLOY_KNOWN_HOSTS` (проверенная запись SSH host key сервера).
- Публичный ключ — в `authorized_keys` этого пользователя, у него есть
  доступ к `/opt/brevity` и Docker.

Workflow загружает файлы деплоя выбранного коммита и запускает
`deploy-host.sh deploy`. Свой инстанс запускает ту же команду вручную.
Никогда не выполнять `docker compose down -v` для стабильного проекта:
команда удалит постоянные тома.

## Ручные операции на сервере

```bash
cd /opt/brevity
sh deploy-host.sh status                  # активное поколение, маршрут, контейнеры
sh deploy-host.sh artisan horizon:status  # php artisan в web активного поколения
docker compose -p brevity-gen-<N> logs --tail 100 web horizon scheduler
curl -sI https://brevity.example.com/up   # X-Brevity-Generation: <N>
```

`sh deploy-host.sh entrance` применяет изменённое описание сервиса Caddy
(новый digest образа, порты, лимиты): проверяет конфигурацию и пересоздаёт
Caddy. Вход прерывается на несколько секунд — выполнять в окно.
Изменённый `Caddyfile` окна не требует: каждый деплой применяет его через
reload. После любого изменения `Caddyfile`, сервиса Caddy или доверия
прокси запускать локально `sh tests/caddy-chain.sh`.

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
sh deploy-host.sh artisan user-agents:detect-bots
sh deploy-host.sh artisan clicks:rebuild-counters   # только ПОСЛЕ detect-bots
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
  sh deploy-host.sh artisan geo:download-db
  ```

  База лежит на общем named-volume `geoip_data` (смонтирован в каждое
  поколение), поэтому загрузка из web сразу видна ридеру в Horizon, где
  происходят lookups, и переживает каждый релиз. Дальше база держится свежей сама: клик асинхронно
  пингует проверку возраста и при устаревании ставит фоновую перекачку (cron не
  нужен). Первичную установку воркер, ещё не открывавший ридер, подхватывает
  сразу; при **ручной перезакачке поверх уже открытой** базы octane-воркеры
  продолжат читать старый inode до рестарта — если нужна немедленная замена,
  `sh deploy-host.sh artisan octane:reload` (гео-данные меняются
  медленно, обычно не требуется).
- Догеолокация исторических кликов — **после** установки базы (без неё команда
  сразу выходит, ничего не сканируя). Идемпотентно и возобновляемо, можно гонять
  в фоне отдельными заходами:

  ```bash
  sh deploy-host.sh artisan geo:locate-clicks
  ```

**`trustProxies`.** Приложение доверяет `X-Forwarded-*` из
`TRUSTED_PROXIES`, а `deploy-host.sh` требует, чтобы он совпадал с
подсетью `brevity-apps`, откуда запросы шлёт только свой Caddy. При пустой
переменной код всё ещё доверяет всем приватным диапазонам (`10/8`,
`172.16/12`, `192.168/16`); этот fallback уходит в пункте A1
[07-plans.md](./07-plans.md).

Контрактные изменения для интеграторов: метка `is_bot` в каждом колбеке
(этап 1), пути перешли на `/api/v1` с RFC 7807-ошибками, legacy `/api/*`
deprecated (этап 2). Уведомить партнёров — см. [03-api.md](./03-api.md).

## Секреты и конфиги вне git

Серверный `.env`: `DB_PASSWORD`, `APP_KEY`, `HASHIDS_SALT` (независим от
`APP_KEY` — см. [08-decisions.md](./08-decisions.md)), SMTP-креды.
Конвенция: ключи (не значения) `.env` / `.env.example` / `.env.production`
держатся синхронными; `.env.production.example` (публичный шаблон)
пополняется, когда новый ключ актуален для прода.
