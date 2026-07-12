# Разработка

## Запуск

Все команды идут через Sail.

```bash
vendor/bin/sail up -d          # поднять контейнеры
vendor/bin/sail artisan migrate
vendor/bin/sail npm run dev    # dev-сервер Vite (если трогаешь фронт)
```

Для первого запуска дополнительно:

```bash
vendor/bin/sail composer install
cp .env.example .env
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan lang:publish   # публикует строки ядра Laravel en/
```

## Тесты

```bash
vendor/bin/sail test                            # весь набор
vendor/bin/sail test --filter=LinkResolveRate   # по имени
vendor/bin/sail test tests/Feature/Callbacks/   # по пути
```

Все тесты должны проходить до любого коммита, который уходит в `main`.
Регресс-тесты в `tests/Feature/Regressions/` — файл на исторический баг
(docstring ссылается на ID находки в
[08-decisions.md](./08-decisions.md)); их нельзя удалять без решения
владельца.

## Стиль кода

```bash
vendor/bin/sail bin pint --dirty --format agent
```

Автоматически гоняется в CI; запускай локально перед коммитом.

Соглашения (из `AGENTS.md` / Laravel Boost):

- PHP 8.4; constructor property promotion везде.
- Явные типы возврата и параметров на каждом публичном методе.
- Тесты вместо tinker-скриптов.
- Не выдумывай структуру каталогов; зеркаль соседей.

## Процесс i18n

1. Трогаешь админ-копирайт? Правь соответствующий
   `lang/en/resources/<x>.php` и `lang/ru/resources/<x>.php` — обе
   стороны.
2. Меняешь термин? Сначала обнови [docs/02-glossary.md](./02-glossary.md).
3. Обвязка Filament / строки ядра Laravel приходят из vendor-файлов —
   не изобретай заново.

Полный i18n-раскладу см. в [docs/04-admin.md](./04-admin.md).

## Релизы

Версионирование: semver **без** префикса `v` (`1.4.1`, не `v1.4.1`).
Источник истины: поле `version` в `composer.json` плюс соответствующий
git-тег.

Интерактивную команду релиза даёт dev-пакет
[`example/release`](https://git.example.com/example/release) (composer-плагин,
подключён через VCS-репозиторий `https://git.example.com/release.git`;
общий для проектов Example, локального скрипта в репо больше нет):

```bash
composer release
```

Запускать там, где есть git + SSH-ключи для пуша; из Sail можно
создать коммит и тег, но на вопрос о пуше ответить `n` и запушить
с хоста (`git push origin main && git push origin <тег>`).

Команда:
1. Проверит, что дерево чистое на ожидаемой ветке.
2. Покажет текущий тег и предложит patch / minor / major / custom.
3. Обновит версию в `composer.json` (через `composer config version`).
4. Создаст релизный коммит и аннотированный тег.
5. Спросит перед пушем.

Чип версии в топбаре админки читает `config('app.version')`, который
тянет из `composer.json` — бампа версии достаточно, пересобирать
приложение не нужно.

## Соглашения по git

- Conventional commits: `feat(...)`, `fix(...)`, `refactor(...)`,
  `chore(...)`.
- **Сообщения коммитов — на английском** (и заголовок, и тело), даже
  если общение по задаче идёт по-русски.
- Одно логическое изменение на коммит.
- Заголовок в повелительном наклонении, ≤ 72 символов.
- Тело объясняет *почему*; код объясняет *что*.
- Не форс-пушить в `main`.

## Подсказки по агентскому воркфлоу

(Для Claude Code / других AI-агентов, работающих в этом репо.)

- Доверяй штатным переводам Filament — не дублируй ключи в `ru.json`,
  которые уже лежат в `vendor/filament/*/lang/ru/`.
- Всегда используй `Read` перед `Write` (enforced).
- Прогоняй `sail test` перед коммитом; не бандли большие UI-правки.
- Предпочитай коммиты по ресурсу/подсистеме одному гигантскому —
  ревёрт дешевле.
- Добавляя новую строку, добавляй перевод и в `en/`, и в `ru/`
  *в том же коммите*, чтобы локали не расходились.
