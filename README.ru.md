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

GitHub Actions собирает production-образ (FrankenPHP + Octane, собранные
ассеты) и публикует его в `ghcr.io/vaslv/brevity` для `linux/amd64`.
На сервере нужен Docker с Compose v2. Собственный Caddy приложения стоит
перед приложением и переключает трафик между релизами, поэтому
обновления проходят без простоя. Он либо сам занимает порты 80/443 и
получает сертификаты (`CADDY_MODE=public`), либо работает за веб-сервером
хоста (`CADDY_MODE=behind-host`).

```bash
# В каталоге установки, рядом с compose-файлами, Caddyfile и deploy-host.sh:
cp .env.production.example .env && chmod 600 .env
# Заполнить APP_KEY, HASHIDS_SALT, APP_URL, APP_HOST, ACME_EMAIL, DB_PASSWORD
# и SMTP. BREVITY_DATABASE=bundled запускает PostgreSQL в Docker.
sudo install -d -m 700 -o 65532 -g 65532 caddy/data caddy/config
mkdir -p .deploy && touch .deploy/database-ready
sh deploy-host.sh deploy ghcr.io/vaslv/brevity:<релиз>
sh deploy-host.sh artisan make:filament-user
```

Каждый релиз запускается новым поколением: миграции, затем новый web,
затем Caddy переключается на него, а прежнее поколение дожидается
завершения запросов и удаляется. Миграции должны оставаться совместимыми
с работающим релизом. Панель — `https://<ваш-домен>/login`.

Генерация ключей, доступ к GHCR, настройка веб-сервера хоста и
обновления — в [руководстве по деплою](./docs/ru/06-deploy.md).

## Назначение

Brevity создан для легитимного управления ссылками: брендированные
короткие ссылки, маршрутизация кампаний, учёт кликов и партнёрские
интеграции для трафика, которым вы владеете или управляете с
разрешения. Не используйте его для клоакинга, спама, фишинга или обхода
правил рекламных сетей и других платформ.

## Лицензия

[MIT](./LICENSE)
