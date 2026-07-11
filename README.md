# Brevity

Сервис коротких ссылок: маршрутизация по правилам (условия, приоритеты,
режимы перехода), учёт кликов, исходящие колбеки во внешние системы,
мультидоменность со стратегиями ротации.

Стек: PHP 8.4, Laravel 13, PostgreSQL, Filament 5, Horizon, Octane
(FrankenPHP), Sail.

## Быстрый старт

```bash
vendor/bin/sail up -d
vendor/bin/sail artisan migrate
vendor/bin/sail test
```

## Документация

Канонический справочник — [docs/](./docs/readme.md): архитектура,
глоссарий, HTTP API, админка, разработка, деплой, планы и принятые
решения.
