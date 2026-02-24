# Аудит и план внедрения фирменного стиля UI

> Файл для отслеживания прогресса внедрения `STYLE_GUIDE.md` в существующую инфраструктуру.
> При старте новой сессии — ссылаться на этот файл для продолжения работы.
> Обновлено: 2026-02-24

---

## Текущее состояние (до внедрения)

| Метрика | Значение |
|---|---|
| Файлов с Font Awesome иконками | 6 (62 вхождения) |
| CSS-файлов с жёсткими hex-цветами | 3 (92 уникальных цвета) |
| Файлов с inline `<style>` | 9 |
| PHP-сниппетов с Bootstrap-классами кнопок | 29 |
| Всего строк CSS | 3 395 |
| Библиотек иконок | 2 (FA + BI — конфликт) |

---

## Этапы внедрения

---

### ЭТАП 1 — CSS-фундамент: переменные и подключение

**Цель:** создать единый источник токенов дизайна. Всё остальное строится на нём.
**Риск:** минимальный — только добавляем новое, ничего не ломаем.
**Ветка:** `claude/ui-stage-1-variables`

#### Задачи

- [ ] **1.1** Создать файл `assets/components/testsystem/css/ts-variables.css`
  — Перенести все CSS Custom Properties из `STYLE_GUIDE.md` (раздел 2)
  — Добавить переменные для типографики, spacing, border-radius, shadow

- [ ] **1.2** Подключить `ts-variables.css` первым в `core/elements/chunks/tsHead.tpl`
  — Добавить `<link>` до всех остальных CSS-файлов системы

- [ ] **1.3** Убедиться, что переменные доступны во всех существующих CSS
  — Проверить каскад: `ts-variables.css` → Bootstrap → `tsrunner.css` → `testsystem-extended.css` → `categories-and-tests.css`

#### Критерий завершения
В браузере: `getComputedStyle(document.documentElement).getPropertyValue('--color-primary')` возвращает `#0095F6`.

#### Статус
`[ ] НЕ НАЧАТ`

---

### ЭТАП 2 — Замена иконок: Font Awesome → Bootstrap Icons

**Цель:** убрать зависимость от Font Awesome, перейти на единственную библиотеку.
**Риск:** средний — визуальные изменения, нужна проверка в браузере.
**Ветка:** `claude/ui-stage-2-icons`

#### Файлы для изменения (62 вхождения FA)

| Файл | FA-вхождений | Приоритет |
|---|---|---|
| `core/elements/chunks/tsHeader.tpl` | 23 | Высокий — виден на каждой странице |
| `core/elements/chunks/tsFooter.tpl` | 13 | Высокий — виден на каждой странице |
| `core/elements/snippets/homePage.php` | 19 | Высокий — главная страница |
| `core/elements/snippets/adminDataIntegrity.php` | 5 | Низкий — только для админов |
| `core/elements/snippets/testRunner.php` | 1 | Средний |
| `core/elements/snippets/usersStats.php` | 1 | Средний |

#### Задачи

- [ ] **2.1** Заменить все `fas fa-*` в `tsHeader.tpl` по таблице из `STYLE_GUIDE.md` (раздел 6)
  ```
  fas fa-graduation-cap  →  bi bi-mortarboard-fill
  fas fa-book            →  bi bi-book
  fas fa-tasks           →  bi bi-card-checklist
  fas fa-route           →  bi bi-signpost-split
  fas fa-trophy          →  bi bi-trophy
  fas fa-brain           →  bi bi-lightbulb
  fas fa-cogs            →  bi bi-gear
  fas fa-user-circle     →  bi bi-person-circle
  fas fa-user            →  bi bi-person
  fas fa-edit            →  bi bi-journal-text
  fas fa-map-signs       →  bi bi-map
  fas fa-star            →  bi bi-bookmark
  fas fa-history         →  bi bi-clock-history
  fas fa-certificate     →  bi bi-patch-check
  fas fa-sign-out-alt    →  bi bi-box-arrow-right
  fas fa-sign-in-alt     →  bi bi-box-arrow-in-right
  fas fa-bell            →  bi bi-bell
  fas fa-chart-bar       →  bi bi-graph-up
  fas fa-users           →  bi bi-people
  fas fa-wrench          →  bi bi-tools
  fas fa-award           →  bi bi-award
  ```

- [ ] **2.2** Заменить все `fas fa-*` в `tsFooter.tpl`

- [ ] **2.3** Заменить все `fas fa-*` в `homePage.php`

- [ ] **2.4** Заменить все `fas fa-*` в `adminDataIntegrity.php`

- [ ] **2.5** Заменить все `fas fa-*` в `testRunner.php`

- [ ] **2.6** Заменить все `fas fa-*` в `usersStats.php`

- [ ] **2.7** Удалить строку подключения Font Awesome из `tsHead.tpl`:
  ```html
  <!-- УДАЛИТЬ эту строку -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  ```
  Убедиться, что Bootstrap Icons подключены: `bootstrap-icons@1.11+`

#### Критерий завершения
Все страницы отображают иконки. Нет сломанных/отсутствующих иконок. В `tsHead.tpl` нет ссылки на Font Awesome CDN.

#### Статус
`[ ] НЕ НАЧАТ`

---

### ЭТАП 3 — Рефакторинг CSS: замена hex на переменные

**Цель:** все CSS-файлы переходят на `var(--color-*)` вместо жёстких значений.
**Риск:** низкий — только CSS, функциональность не затрагивается.
**Ветка:** `claude/ui-stage-3-css-vars`

#### Файлы

| Файл | Строк | Уникальных hex | Приоритет |
|---|---|---|---|
| `assets/components/testsystem/css/tsrunner.css` | 2 607 | 59 | Высокий |
| `assets/components/testsystem/css/testsystem-extended.css` | 450 | 12 | Средний |
| `assets/components/testsystem/css/categories-and-tests.css` | 338 | 21 | Средний |

#### Таблица замен (общая для всех файлов)

| Было | Станет |
|---|---|
| `#0d6efd`, `#007bff`, `#0095F6` | `var(--color-primary)` |
| `#0b5ed7`, `#0074CC`, `#0a58ca`, `#1976d2` | `var(--color-primary-dark)` |
| `#e7f3ff`, `#cfe2ff`, `#E8F4FD` | `var(--color-primary-light)` |
| `#198754`, `#28a745`, `#4caf50`, `#00B37E` | `var(--color-success)` |
| `#157347`, `#146c43`, `#45a049`, `#157347` | `var(--color-success-dark)` |
| `#d4edda`, `#E6F7F2` | `var(--color-success-light)` |
| `#dc3545`, `#d32f2f`, `#ED4956` | `var(--color-danger)` |
| `#f8d7da`, `#ffebee`, `#FDECEA` | `var(--color-danger-light)` |
| `#721c24` | `var(--color-danger-dark)` |
| `#ffc107`, `#F59E0B` | `var(--color-warning)` |
| `#fff3cd`, `#FEF3C7` | `var(--color-warning-light)` |
| `#664d03` | `var(--color-warning-dark)` |
| `#262626`, `#343a40`, `#333` | `var(--color-text-primary)` |
| `#737373`, `#666` | `var(--color-text-secondary)` |
| `#A8A8A8`, `#adb5bd` | `var(--color-text-muted)` |
| `#FAFAFA`, `#f8f9fa` | `var(--color-bg-page)` |
| `#FFFFFF`, `#fff`, `white` | `var(--color-bg-card)` |
| `#f5f5f5`, `#F5F5F5`, `#f0f0f0` | `var(--color-bg-hover)` |
| `#DBDBDB`, `#e0e0e0`, `#ddd`, `#dee2e6` | `var(--color-border)` |
| `#8B5CF6`, `#667eea`, `#764ba2` | `var(--color-xp)` |

#### Задачи

- [ ] **3.1** Рефакторинг `tsrunner.css`: заменить все hex-цвета на переменные

- [ ] **3.2** Рефакторинг `testsystem-extended.css`: заменить все hex-цвета на переменные

- [ ] **3.3** Рефакторинг `categories-and-tests.css`: заменить все hex-цвета на переменные

- [ ] **3.4** Унифицировать значения `border-radius`:
  — `4px`, `6px` → `8px` (кнопки, поля, бейджи)
  — `8px` для карточек → `12px`
  — Все как переменные через `var(--radius-*)` или напрямую

- [ ] **3.5** Убрать `transform: translateY(-2px)` из всех hover-состояний кнопок
  (оставить только у `.ts-card:hover`)

- [ ] **3.6** Убрать градиентные фоны у кнопок (`.btn-start-training`, `.btn-start-exam`)

#### Критерий завершения
Grep по CSS-файлам на `#[0-9a-fA-F]{3,6}` возвращает 0 результатов.

#### Статус
`[ ] НЕ НАЧАТ`

---

### ЭТАП 4 — Вынос inline `<style>` из чанков и сниппетов

**Цель:** все стили — в CSS-файлах, никакого CSS в PHP/Fenom.
**Риск:** средний — нужно убедиться, что CSS файлы подключены до использования классов.
**Ветка:** `claude/ui-stage-4-no-inline-styles`

#### Файлы с inline `<style>`

| Файл | Тип | Приоритет |
|---|---|---|
| `core/elements/chunks/tsHead.tpl` | Чанк | **Критический** — глобальный |
| `core/elements/snippets/myTests.php` | Сниппет | Высокий |
| `core/elements/snippets/myFavorites.php` | Сниппет | Высокий |
| `core/elements/snippets/learningPaths.php` | Сниппет | Высокий |
| `core/elements/snippets/knowledgeAreasManager.php` | Сниппет | Средний |
| `core/elements/snippets/manageCategories.php` | Сниппет | Средний |
| `core/elements/snippets/adminDataIntegrity.php` | Сниппет | Низкий (только admin) |
| `core/elements/templates/base.tpl` | Шаблон | Средний |
| `core/elements/templates/LMS_Bootstrap_5.tpl` | Шаблон | Средний |

#### Задачи

- [ ] **4.1** `tsHead.tpl`: вынести блок `<style>` в `ts-layout.css`
  — `.navbar-brand` стили
  — `footer` стили
  — `.breadcrumb` стили
  — `.card` hover стили
  — `.alert` стили
  — `.user-xp` стили

- [ ] **4.2** `myTests.php`: вынести inline стили в `testsystem-extended.css` или новый файл

- [ ] **4.3** `myFavorites.php`: вынести inline стили

- [ ] **4.4** `learningPaths.php`: вынести inline стили

- [ ] **4.5** `knowledgeAreasManager.php`: вынести inline стили

- [ ] **4.6** `manageCategories.php`: вынести inline стили

- [ ] **4.7** `adminDataIntegrity.php`: вынести inline стили

- [ ] **4.8** `base.tpl`: вынести inline стили

- [ ] **4.9** `LMS_Bootstrap_5.tpl`: вынести inline стили

- [ ] **4.10** Создать `assets/components/testsystem/css/ts-layout.css`
  — Навбар, футер, breadcrumb, фон страницы

#### Критерий завершения
Grep по `<style>` в `core/elements/` возвращает 0 результатов.

#### Статус
`[ ] НЕ НАЧАТ`

---

### ЭТАП 5 — CSS-компоненты: создание `ts-components.css`

**Цель:** создать полноценную библиотеку компонентов (`ts-btn-*`, `ts-card`, `ts-badge-*` и т.д.) по спецификации `STYLE_GUIDE.md`.
**Риск:** низкий — создаём новое, не трогаем существующее.
**Ветка:** `claude/ui-stage-5-components`

#### Задачи

- [ ] **5.1** Создать `assets/components/testsystem/css/ts-components.css`

- [ ] **5.2** Реализовать `.ts-btn` и все варианты:
  — `.ts-btn-primary`, `.ts-btn-secondary`, `.ts-btn-success`, `.ts-btn-danger`, `.ts-btn-ghost`
  — Размеры: `.ts-btn-sm`, `.ts-btn-lg`
  — `.ts-btn-icon-only` (круглая кнопка с иконкой)
  — Состояния: default, hover, active, disabled, loading

- [ ] **5.3** Реализовать `.ts-card`, `.ts-card-header`, `.ts-card-body`, `.ts-card-footer`

- [ ] **5.4** Реализовать `.ts-badge` и варианты:
  — `.ts-badge-primary`, `.ts-badge-success`, `.ts-badge-danger`, `.ts-badge-warning`
  — `.ts-badge-neutral`, `.ts-badge-xp`, `.ts-badge-gold`

- [ ] **5.5** Реализовать `.ts-field`, `.ts-label`, `.ts-input`, `.ts-select`, `.ts-textarea`
  — Состояния: default, focus, error, disabled

- [ ] **5.6** Реализовать `.ts-alert` и варианты:
  — `.ts-alert-success`, `.ts-alert-danger`, `.ts-alert-warning`, `.ts-alert-info`
  — С левой цветной полосой (border-left: 3px)

- [ ] **5.7** Реализовать `.ts-meta`, `.ts-meta-item` для метаинформации карточек

- [ ] **5.8** Реализовать `.ts-dropdown-menu` (контекстное меню карточек)

- [ ] **5.9** Подключить `ts-components.css` в `tsHead.tpl`

#### Критерий завершения
Все описанные компоненты существуют в CSS, задокументированы в `STYLE_GUIDE.md` и визуально корректны.

#### Статус
`[ ] НЕ НАЧАТ`

---

### ЭТАП 6 — Миграция PHP-сниппетов: Bootstrap-классы → ts-классы

**Цель:** все PHP-сниппеты используют новые `ts-` классы вместо прямых Bootstrap-классов для кастомных компонентов.
**Риск:** высокий — 29 файлов, много HTML. Делать группами, каждая группа — отдельный коммит.
**Ветка:** `claude/ui-stage-6-snippets`

#### Группа A — Высокий трафик (видны всем пользователям)

| Файл | Задача |
|---|---|
| `homePage.php` | Hero-секция, кнопки CTA, карточки статистики |
| `categoriesAndTests.php` | Карточки тестов, кнопки запуска, меню |
| `testRunner.php` | Кнопки управления тестом, алерты |
| `testResults.php` | Результаты, кнопки навигации |
| `leaderboard.php` | Таблица лидеров, бейджи |

- [ ] **6.A.1** Мигрировать `homePage.php`
- [ ] **6.A.2** Мигрировать `categoriesAndTests.php`
- [ ] **6.A.3** Мигрировать `testRunner.php`
- [ ] **6.A.4** Мигрировать `testResults.php`
- [ ] **6.A.5** Мигрировать `leaderboard.php`

#### Группа B — Профиль и личный кабинет

| Файл | Задача |
|---|---|
| `userProfile.php` | Форма профиля, кнопки, поля |
| `myTests.php` | Список тестов, кнопки действий |
| `myFavorites.php` | Карточки избранного |
| `testHistory.php` | История, фильтры, бейджи статусов |
| `myCertificates.php` | Карточки сертификатов |
| `myAchievements.php` | Карточки достижений, бейджи |
| `achievements.php` | Публичная страница достижений |

- [ ] **6.B.1** Мигрировать `userProfile.php`
- [ ] **6.B.2** Мигрировать `myTests.php`
- [ ] **6.B.3** Мигрировать `myFavorites.php`
- [ ] **6.B.4** Мигрировать `testHistory.php`
- [ ] **6.B.5** Мигрировать `myCertificates.php`
- [ ] **6.B.6** Мигрировать `myAchievements.php` + `achievements.php`

#### Группа C — Учебный контент

| Файл | Задача |
|---|---|
| `learningMaterialsTemplate.php` | Страница материала, навигация |
| `learningPaths.php` | Карточки траекторий, шаги |
| `categoriesList.php` | Список категорий |
| `testsList.php` | Список тестов |
| `getUserStats.php` | Статистика пользователя |
| `addTestForm.php` | Форма создания теста |
| `csvImportForm.php` | Форма импорта |

- [ ] **6.C.1** Мигрировать `learningMaterialsTemplate.php`
- [ ] **6.C.2** Мигрировать `learningPaths.php`
- [ ] **6.C.3** Мигрировать `categoriesList.php` + `testsList.php`
- [ ] **6.C.4** Мигрировать `addTestForm.php` + `csvImportForm.php`
- [ ] **6.C.5** Мигрировать `getUserStats.php`

#### Группа D — Авторизация

| Файл | Задача |
|---|---|
| `authHandler.php` | Форма входа/регистрации |
| `forgotPasswordHandler.php` | Форма восстановления пароля |
| `resetPasswordHandler.php` | Форма сброса пароля |
| `activateAccount.php` | Страница активации |

- [ ] **6.D.1** Мигрировать `authHandler.php`
- [ ] **6.D.2** Мигрировать `forgotPasswordHandler.php` + `resetPasswordHandler.php` + `activateAccount.php`

#### Группа E — Управление (только для админов/экспертов)

| Файл | Задача |
|---|---|
| `manageCategories.php` | Управление категориями |
| `manageUsers.php` | Управление пользователями |
| `knowledgeAreasManager.php` | Управление областями знаний |
| `adminDataIntegrity.php` | Проверка целостности данных |

- [ ] **6.E.1** Мигрировать `manageCategories.php`
- [ ] **6.E.2** Мигрировать `manageUsers.php`
- [ ] **6.E.3** Мигрировать `knowledgeAreasManager.php`
- [ ] **6.E.4** Мигрировать `adminDataIntegrity.php`

#### Критерий завершения
Grep по `class="btn btn-` в PHP-сниппетах возвращает 0 результатов.

#### Статус
`[ ] НЕ НАЧАТ`

---

### ЭТАП 7 — Финальный аудит и чистка

**Цель:** убедиться, что всё соответствует `STYLE_GUIDE.md`. Удалить мусор.
**Риск:** минимальный.
**Ветка:** `claude/ui-stage-7-cleanup`

#### Задачи

- [ ] **7.1** Grep-проверки:
  ```bash
  # Не должно быть FA иконок
  grep -r "fas fa-\|far fa-\|fab fa-" core/elements/ assets/components/testsystem/

  # Не должно быть hex-цветов в CSS
  grep -E "#[0-9a-fA-F]{3,6}" assets/components/testsystem/css/

  # Не должно быть inline style тегов
  grep -r "<style>" core/elements/

  # Не должно быть Bootstrap-классов кнопок в сниппетах
  grep -r "class=\"btn btn-" core/elements/snippets/
  ```

- [ ] **7.2** Обновить Bootstrap Icons до `1.11+` в `tsHead.tpl` (с `1.10.0`)

- [ ] **7.3** Удалить дублирующиеся CSS-правила, оставшиеся после рефакторинга

- [ ] **7.4** Проверить адаптивность (мобильные, планшеты) всех затронутых страниц

- [ ] **7.5** Обновить `STYLE_GUIDE.md` — добавить финальную файловую структуру CSS

- [ ] **7.6** Добавить в `DEVELOPMENT_RULES.md` grep-команды из п.7.1 как часть чеклиста

#### Критерий завершения
Все 4 grep-команды из п.7.1 возвращают 0 результатов.

#### Статус
`[ ] НЕ НАЧАТ`

---

## Сводная таблица прогресса

| Этап | Название | Файлов | Статус | Ветка |
|---|---|---|---|---|
| 1 | CSS-фундамент: переменные | 2 | `[ ]` | `claude/ui-stage-1-variables` |
| 2 | Замена иконок FA → BI | 6 | `[ ]` | `claude/ui-stage-2-icons` |
| 3 | Hex-цвета → переменные в CSS | 3 | `[ ]` | `claude/ui-stage-3-css-vars` |
| 4 | Вынос inline `<style>` | 9 | `[ ]` | `claude/ui-stage-4-no-inline-styles` |
| 5 | CSS-компоненты (`ts-components.css`) | 1 (новый) | `[ ]` | `claude/ui-stage-5-components` |
| 6 | Миграция сниппетов PHP | 29 | `[ ]` | `claude/ui-stage-6-snippets` |
| 7 | Финальный аудит и чистка | — | `[ ]` | `claude/ui-stage-7-cleanup` |

---

## Как обновлять этот файл

При завершении задачи:
```
- [ ] 2.1 Заменить все fas fa-* в tsHeader.tpl
→
- [x] 2.1 Заменить все fas fa-* в tsHeader.tpl  ✓ 2026-02-25
```

При завершении этапа — обновить строку в сводной таблице:
```
| 2 | Замена иконок FA → BI | 6 | `[ ]` | ...
→
| 2 | Замена иконок FA → BI | 6 | `[x] ГОТОВ 2026-02-25` | ...
```
