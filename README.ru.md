# Brevity

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

Для self-hosted запуска на любом сервере с Docker в репозитории есть
всё: продовый `Dockerfile` (FrankenPHP + Octane), полностью
параметризованный `docker-compose.production.yml` (nginx-proxy +
автоматический Let's Encrypt, one-shot мигратор, web, scheduler,
Horizon, Redis), опциональный оверлей `docker-compose.db.yml`,
добавляющий PostgreSQL в стек, и шаблон продового окружения
(`.env.production.example`). Кратко:

```bash
docker build -t you/brevity:latest .
cp .env.production.example .env   # заполнить пропуски
docker compose --env-file .env \
    -f docker-compose.production.yml -f docker-compose.db.yml up -d
docker exec -it laravel-web php artisan make:filament-user
```

Админка отдаётся в корне технического хоста
(`https://<ваш-хост>/login`). `.gitlab-ci.yml` — референсный пайплайн
сборки и деплоя для любого GitLab и container registry: вся
инфраструктурная специфика передаётся через CI-переменные. Полный
разбор: [docs/ru/06-deploy.md](./docs/ru/06-deploy.md).

## Назначение

Brevity создан для легитимного управления ссылками: брендированные
короткие ссылки, маршрутизация кампаний, учёт кликов и партнёрские
интеграции для трафика, которым вы владеете или управляете с
разрешения. Не используйте его для клоакинга, спама, фишинга или обхода
правил рекламных сетей и других платформ.

## Лицензия

[MIT](./LICENSE)
