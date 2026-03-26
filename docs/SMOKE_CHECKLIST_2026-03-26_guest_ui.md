# Smoke-checklist для гостевого UI

Дата фиксации: **2026-03-26**

## Цель
Быстрая проверка после рефакторинга этапов 1–3, что гостевой сценарий не содержит служебного UI и дубли инфраструктуры.

## Страницы
- `/learning-articles`
- `/learning-paths`
- `/tests`
- `/moi-oblasti-znanij`
- `/achievements`

## Чек-лист

### 1) DOM и роли
- [ ] Гость не получает служебные модалки создания/редактирования.
- [ ] На `learning-paths` отсутствует `#pathModal` в гостевом DOM.
- [ ] На `tests` отсутствует `#questions-editor-view` в гостевом DOM.

### 2) Скрипты
- [ ] `tsScripts` грузит только base-часть для гостя (без heavy feature-модулей по умолчанию).
- [ ] `learning-paths.js` грузится только на релевантных страницах (`learning-paths`, `learning-articles`).
- [ ] На `tests` для гостя не грузятся Quill/DOMPurify и shared editor scripts.

### 3) CSRF
- [ ] На странице ровно один `meta[name="csrf-token"]`.
- [ ] Нет дублирующих вставок CSRF meta из `learningPaths`, `categoriesAndTests`, `learningMaterialsTemplate`.

### 4) Регрессии
- [ ] Для авторизованных пользователей не сломаны сценарии управления тестами и траекториями.
- [ ] На шаговом контенте траекторий fallback-панель продолжает отображаться и завершать шаг.

## Как проверять вручную (DevTools)
1. Открыть страницу в режиме гостя.
2. Проверить DOM-поиск:
   - `document.querySelectorAll('#questions-editor-view').length`
   - `document.querySelectorAll('#pathModal').length`
   - `document.querySelectorAll('meta[name="csrf-token"]').length`
3. Проверить вкладку Network → JS, что отсутствуют нерелевантные heavy-модули.
