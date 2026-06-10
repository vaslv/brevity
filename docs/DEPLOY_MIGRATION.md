# Перенос деплоя с root на непривилегированного пользователя

Ранбук по переводу продакшен-деплоя с `root@SERVER_IP` на выделенного
пользователя `brevity`. Впервые выполнен 2026-06-10 (аудит, Low / CI,
блок B — см. [AUDIT_2026-06.md](./AUDIT_2026-06.md)). Пригодится повторно
при переезде на новый сервер или смене деплой-пользователя.

## Контекст

- Пайплайн (`.gitlab-ci.yml`) деплоит по SSH под пользователем из
  CI-переменной `DEPLOY_USER` (по умолчанию `root`). Файлы `.env` и
  `docker-compose.production.yml` копируются в **домашнюю директорию**
  этого пользователя, оттуда же запускается `docker compose up`.
- Имя compose-проекта зафиксировано в `docker-compose.production.yml`
  (`name: brevity`), поэтому от директории запуска оно не зависит. Но
  именованные тома исторического деплоя из `/root` имели префикс
  `root_*` — при переезде их нужно перенести под новый префикс, иначе
  потеряются Let's Encrypt-сертификаты (`nginx_certs`, `nginx_acme`) и
  данные Redis (`redis_data`).
- `LARAVEL_IMAGE` **не хранится** в серверном `.env` — CI экспортирует
  её в команде деплоя. Поэтому любые ручные `docker compose`-команды на
  сервере требуют подставить её явно (для `down`/`ps` годится заглушка).

## Шаг 0. Подготовка пользователя (на сервере, под root)

```bash
useradd -m -s /bin/bash brevity        # если ещё не создан
usermod -aG docker brevity
mkdir -p /home/brevity/.ssh
cp /root/.ssh/authorized_keys /home/brevity/.ssh/   # или отдельный ключ CI
chown -R brevity:brevity /home/brevity/.ssh
chmod 700 /home/brevity/.ssh && chmod 600 /home/brevity/.ssh/authorized_keys
```

Проверка с локальной машины (должно работать без sudo):

```bash
ssh brevity@<SERVER_IP> docker ps
```

## Шаг 1. Окно простоя

Между шагами 2 и 5 сайт лежит (порядка 2–5 минут). Перед остановкой дай
Horizon дообработать очередь:

```bash
docker exec laravel-horizon php artisan horizon:terminate
```

и дождись, пока контейнер остановится.

## Шаг 2. Остановить старый проект, не трогая тома

```bash
cd /root
LARAVEL_IMAGE=dummy docker compose -p root --env-file .env -f docker-compose.production.yml down
```

- `LARAVEL_IMAGE=dummy` обязателен: без него compose падает с
  «service "migrate" has neither an image nor a build context» ещё на
  валидации. Для `down` реальное значение образа не нужно.
- **Без флага `-v`** — тома `root_*` должны остаться.

## Шаг 3. Перенести тома под новый префикс

Переименования томов в Docker нет, копируем содержимое:

```bash
for v in nginx_html nginx_certs nginx_acme redis_data; do
  docker volume create brevity_$v
  docker run --rm -v root_$v:/from:ro -v brevity_$v:/to alpine sh -c "cp -a /from/. /to/"
done
docker volume ls | grep brevity   # должны быть все 4
```

## Шаг 4. CI-переменные GitLab

Settings → CI/CD → Variables:

| Переменная | Значение | Зачем |
|---|---|---|
| `DEPLOY_USER` | `brevity` | переопределяет дефолт `root` из `.gitlab-ci.yml` |
| `HEALTHCHECK_URL` | `https://brevity.example.com/up` | включает post-deploy smoke-тест |

Флаг Protected ставить только если деплойные теги protected, иначе
переменные не попадут в пайплайн.

## Шаг 5. Задеплоить

Создай и запушь новый тег — пайплайн зайдёт под `brevity`, скопирует
`.env` и compose-файл в `/home/brevity` и поднимет проект `brevity` с
перенесёнными томами.

Если тег уже существовал до пуша фиксов в `.gitlab-ci.yml`: GitLab не
перезапустит теговый пайплайн сам — пересоздай тег
(`git tag -f <tag> && git push -f origin <tag>`) или запусти пайплайн по
тегу вручную (CI/CD → Run pipeline).

## Шаг 6. Проверка

```bash
docker compose -p brevity ps                              # всё up/healthy
curl -I https://brevity.example.com/up                  # HTTP 200
docker exec laravel-horizon php artisan horizon:status    # running
docker logs acme-companion --since 10m                    # НЕ выпускает новые сертификаты
docker exec redis redis-cli dbsize                        # не пустой
```

Признак корректного переноса томов: acme-companion не пытается
перевыпустить сертификаты, Redis содержит данные.

## Шаг 7. Уборка — только через день-два после успешной проверки

```bash
docker volume rm root_nginx_html root_nginx_certs root_nginx_acme root_redis_data
rm /root/.env /root/docker-compose.production.yml
```

## Откат

Пока тома `root_*` целы, можно вернуться на старую схему:

```bash
cd /root
LARAVEL_IMAGE=<образ> docker compose -p root --env-file .env -f docker-compose.production.yml up -d
```

Реальный образ берётся из последнего контейнера:
`docker inspect laravel-web --format '{{.Config.Image}}'`.

## Грабли, собранные при первом прогоне

- **`LARAVEL_IMAGE` не в `.env`** — любая ручная compose-команда на
  сервере требует её явно (см. шаги 2 и «Откат»).
- **`: ` внутри plain-скаляра YAML** — строка вида
  `- echo "tag: $VAR"` в `script:` парсится как словарь, и GitLab
  отклоняет пайплайн («script config should be a string»). Оборачивай
  такие строки в одинарные кавычки целиком (исправлено в `07fb622`).
- **Пайплайн по уже существующему тегу не перезапускается** после пуша
  фиксов в `.gitlab-ci.yml` — см. шаг 5.
