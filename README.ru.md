<img src="./public/images/logo.svg" alt="Brevity" width="280">

Self-hosted сокращатель ссылок: маршрутизация по правилам, аналитика
кликов, исходящие колбеки и мультидоменность.

**English version: [README.md](./README.md)**

## Возможности

- **Маршрутизация по правилам** — у каждой ссылки упорядоченный список
  правил; побеждает первое совпавшее условие. Режимы перехода на
  правило: мгновенный 302-редирект, страница с обратным отсчётом или
  страница с кнопкой «продолжить».
- **A/B-сплит** — детерминированный sticky-выбор варианта без cookie и
  состояния на сервере.
- **Аналитика кликов** — асинхронная запись кликов (посетитель не
  ждёт), детекция ботов, счётчики по ссылкам, геолокация кликов
  (MaxMind GeoIP2) с гео-картой в админке.
- **Исходящие колбеки** — POST-уведомления во внешние системы с
  шаблонизируемым payload'ом (переменные `{{click.*}}` / `{{link.*}}`),
  ретраями с backoff и защитой от SSRF.
- **Мультидоменность** — пул доменов коротких ссылок со стратегиями
  ротации и группами доменов; мониторинг репутации доменов.
- **Жизненный цикл ссылки** — окно активности (`valid_since` /
  `valid_until`) и лимит кликов (`max_clicks`).
- **Админка** — Filament 5, двуязычная (EN/RU).
- **HTTP API** — токен-аутентификация (Laravel Sanctum), рассчитан на
  программное создание ссылок; см. [docs/ru/03-api.md](./docs/ru/03-api.md).
  Официальный PHP-клиент:
  [vaslv/brevity-php-sdk](https://github.com/vaslv/brevity-php-sdk).

## Стек

PHP 8.4, Laravel 13, PostgreSQL, Filament 5, Horizon, Octane
(FrankenPHP), Redis. Локальная разработка — Laravel Sail.

## Быстрый старт

```bash
git clone https://github.com/vaslv/brevity.git && cd brevity

# Установка зависимостей без локального PHP (одноразовый бутстрап):
docker run --rm -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest composer install

cp .env.example .env
vendor/bin/sail up -d
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate
vendor/bin/sail test
```

Админка отдаётся только на техническом хосте (хост из `APP_URL`);
остальные настроенные хосты работают исключительно как домены коротких
ссылок.

## Документация

Канонический справочник — [docs/](./docs/readme.md) (EN), русское
зеркало — [docs/ru/](./docs/ru/readme.md): архитектура, глоссарий,
HTTP API, админка, разработка, деплой, планы и принятые решения.

## Деплой

GitHub Actions собирает production-образ (FrankenPHP + Octane, готовые
ассеты) и публикует его в `ghcr.io/vaslv/brevity`. Для первого запуска
нужны Docker с Compose v2, сервер с открытыми портами 80/443 и домен,
направленный на сервер. Образ из GitHub рассчитан на `linux/amd64`.

```bash
cp .env.production.example .env
# Заполнить APP_KEY, HASHIDS_SALT, APP_URL, APP_HOST,
# LETSENCRYPT_EMAIL, DB_PASSWORD и SMTP-настройки.
# LARAVEL_IMAGE уже указывает на ghcr.io/vaslv/brevity:latest.

# Вариант 1: PostgreSQL в Docker, DB_HOST=pgsql
docker compose --env-file .env \
    -f docker-compose.production.yml -f docker-compose.db.yml up -d --pull always --wait --wait-timeout 300

# Вариант 2: существующий PostgreSQL — заполнить DB_HOST/PORT/DATABASE/USERNAME/PASSWORD,
# затем запустить только основной файл вместо команды выше:
# docker compose --env-file .env -f docker-compose.production.yml up -d --pull always --wait --wait-timeout 300

docker compose --env-file .env -f docker-compose.production.yml exec web php artisan make:filament-user
```

Планировщик автоматически применяет миграции; web и Horizon ждут его
готовности. Встроенная база хранит данные в отдельном Docker volume.
Внешняя база должна быть доступна из контейнеров; `localhost` указывает
на сам контейнер, а для базы на Docker-хосте используйте `host.docker.internal`.
Админка: `https://<ваш-хост>/login`.

Генерация ключей, доступ к GHCR и обновление приложения:
[инструкция деплоя](./docs/ru/06-deploy.md). Старый GitLab CI отключён.

## Назначение

Brevity создан для легитимного управления ссылками: брендированные
короткие ссылки, маршрутизация кампаний, учёт кликов и партнёрские
интеграции для трафика, которым вы владеете или управляете с
разрешения. Не используйте его для клоакинга, спама, фишинга или обхода
правил рекламных сетей и других платформ.

## Лицензия

[MIT](./LICENSE)
