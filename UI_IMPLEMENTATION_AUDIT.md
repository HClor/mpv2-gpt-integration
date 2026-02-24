# Аудит и план внедрения фирменного стиля UI

> Файл для отслеживания прогресса внедрения `STYLE_GUIDE.md` в существующую инфраструктуру.
> При старте новой сессии — ссылаться на этот файл для продолжения работы.
> Обновлено: 2026-02-24 (Этап 4 завершён)

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

- [x] **1.1** Создать файл `assets/components/testsystem/css/ts-variables.css`  ✓ 2026-02-24
  — Перенести все CSS Custom Properties из `STYLE_GUIDE.md` (раздел 2)
  — Добавить переменные для типографики, spacing, border-radius, shadow

- [x] **1.2** Подключить `ts-variables.css` первым в `core/elements/chunks/tsHead.tpl`  ✓ 2026-02-24
  — Добавить `<link>` до всех остальных CSS-файлов системы

- [x] **1.3** Убедиться, что переменные доступны во всех существующих CSS  ✓ 2026-02-24
  — Проверить каскад: `ts-variables.css` → Bootstrap → `tsrunner.css` → `testsystem-extended.css` → `categories-and-tests.css`

#### Критерий завершения
В браузере: `getComputedStyle(document.documentElement).getPropertyValue('--color-primary')` возвращает `#0095F6`.

#### Статус
`[x] ГОТОВ 2026-02-24`

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

- [x] **2.1** Заменить все `fas fa-*` в `tsHeader.tpl` по таблице из `STYLE_GUIDE.md` (раздел 6)  ✓ 2026-02-24

- [x] **2.2** Заменить все `fas fa-*` / `fab fa-*` в `tsFooter.tpl`  ✓ 2026-02-24

- [x] **2.3** Заменить все `fas fa-*` в `homePage.php`  ✓ 2026-02-24

- [x] **2.4** Заменить все `fas fa-*` в `adminDataIntegrity.php`  ✓ 2026-02-24

- [x] **2.5** Заменить все `fas fa-*` в `testRunner.php`  ✓ 2026-02-24

- [x] **2.6** Заменить все `fas fa-*` в `usersStats.php`  ✓ 2026-02-24

- [x] **2.7** Удалить Font Awesome CDN из `tsHead.tpl`, обновить Bootstrap Icons до `1.11.3`  ✓ 2026-02-24

#### Критерий завершения
Все страницы отображают иконки. Нет сломанных/отсутствующих иконок. В `tsHead.tpl` нет ссылки на Font Awesome CDN.
**Верификация:** `grep -r "fas fa-\|far fa-\|fab fa-" core/elements/` → 0 результатов ✓

#### Статус
`[x] ГОТОВ 2026-02-24`

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

- [x] **3.0** Добавить недостающие переменные в `ts-variables.css`  ✓ 2026-02-24
  — `--color-primary-text`, `--color-success-text`, `--color-danger-text`, `--color-warning-text`
  — `--color-xp-dark` (для градиентов)

- [x] **3.1** Рефакторинг `tsrunner.css`: заменить все hex-цвета на переменные  ✓ 2026-02-24
  — ~59 уникальных hex → `var(--color-*)`, ~40 border-radius → `var(--radius-*)`

- [x] **3.2** Рефакторинг `testsystem-extended.css`: заменить все hex-цвета на переменные  ✓ 2026-02-24
  — 12 уникальных hex → `var(--color-*)`, border-radius → `var(--radius-*)`

- [x] **3.3** Рефакторинг `categories-and-tests.css`: заменить все hex-цвета на переменные  ✓ 2026-02-24
  — 21 уникальных hex → `var(--color-*)`, border-radius → `var(--radius-*)`

- [x] **3.4** Унифицировать значения `border-radius` через `var(--radius-*)`  ✓ 2026-02-24
  — `4px`, `6px` → `var(--radius-badge)` (6px)
  — `8px`, `10px` → `var(--radius-btn)` (8px)
  — `12px` → `var(--radius-card)` (12px)
  — Pill-значения (28px, 32px, 34px, 50%) оставлены как есть (toggle-специфичные)

- [x] **3.5** Убрать `transform: translateY(-2px)` из всех hover-состояний кнопок  ✓ 2026-02-24
  — Удалено из: `.btn:hover`, `#start-test-unified:hover`, `.btn-group .btn:hover`,
    `#submit-answer-btn:hover`, `#prev-card-btn:hover`, `.auth-required-alert .btn-primary:hover`,
    `.start-test-btn-compact:hover`, `.test-actions-compact .btn-test-action:hover`,
    `#my-tests-container .btn-primary:hover`, `.btn-start-training:hover`, `.btn-start-exam:hover`
  — Оставлено на карточках: `.mode-card:hover`, `.knowledge-area-card:hover`, `.test-card:hover`

- [x] **3.6** Убрать градиентные фоны у кнопок (`.btn-start-training`, `.btn-start-exam`)  ✓ 2026-02-24
  — Теперь используют `var(--color-success)` / `var(--color-primary)` вместо Material Design hex

#### Критерий завершения
Grep по CSS-файлам на `#[0-9a-fA-F]{3,6}` возвращает 0 результатов (кроме `ts-variables.css` — определения токенов).
**Верификация:** hex-коды остались только в `ts-variables.css` (определения переменных) и 1 комментарий в `tsrunner.css` ✓

#### Статус
`[x] ГОТОВ 2026-02-24`

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

- [x] **4.1** `tsHead.tpl`: вынести блок `<style>` в `ts-layout.css`  ✓ 2026-02-24
  — `.navbar-brand` стили
  — `footer` стили
  — `.breadcrumb` стили
  — `.card` hover стили
  — `.alert` стили
  — `.user-xp` стили

- [x] **4.2** `myTests.php`: вынести inline стили в `testsystem-extended.css`  ✓ 2026-02-24
  — Удалены избыточные переопределения Bootstrap-кнопок
  — Функциональные стили `.btn-test-action`, `.test-title-clickable` → `testsystem-extended.css`

- [x] **4.3** `myFavorites.php`: вынести inline стили  ✓ 2026-02-24
  — `.favorite-question-clickable`, `.removing-item`, стили модального изображения → `testsystem-extended.css`

- [x] **4.4** `learningPaths.php`: вынести inline стили  ✓ 2026-02-24
  — `.step-timeline`, `.step-card`, drag-and-drop стили → `testsystem-extended.css`

- [x] **4.5** `knowledgeAreasManager.php`: вынести inline стили  ✓ 2026-02-24
  — Удалены избыточные переопределения Bootstrap-кнопок
  — `.knowledge-areas-wrapper .card` hover → `testsystem-extended.css`

- [x] **4.6** `manageCategories.php`: вынести inline стили  ✓ 2026-02-24
  — Удалены избыточные переопределения Bootstrap-кнопок
  — `.table tbody tr:hover` → `testsystem-extended.css` (с уточнённым селектором)

- [x] **4.7** `adminDataIntegrity.php`: вынести inline стили  ✓ 2026-02-24
  — `#data-integrity-admin .issue-card`, `.stat-card` → `testsystem-extended.css`

- [x] **4.8** `base.tpl`: вынести inline стили  ✓ 2026-02-24
  — Создан `assets/templates/css/base-layout.css`; `[[++manager_url]]` заменён на `/manager/...`

- [x] **4.9** `LMS_Bootstrap_5.tpl`: вынести inline стили  ✓ 2026-02-24
  — Подключены `ts-variables.css` + `ts-layout.css`; inline `<style>` удалён

- [x] **4.10** Создать `assets/components/testsystem/css/ts-layout.css`  ✓ 2026-02-24
  — Навбар, футер, breadcrumb, фон страницы, `.card`, `.alert`, `.user-xp`
  — Подключён в `tsHead.tpl` и `LMS_Bootstrap_5.tpl`

#### Критерий завершения
Grep по `<style>` в `core/elements/` возвращает 0 результатов.
**Верификация:** `grep -rn "<style" core/elements/` → 0 результатов ✓

#### Статус
`[x] ГОТОВ 2026-02-24`

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
| 1 | CSS-фундамент: переменные | 2 | `[x] ГОТОВ 2026-02-24` | `claude/ui-stage-1-variables` |
| 2 | Замена иконок FA → BI | 6 | `[x] ГОТОВ 2026-02-24` | `claude/ui-stage-2-icons` |
| 3 | Hex-цвета → переменные в CSS | 3 | `[x] ГОТОВ 2026-02-24` | `claude/ui-stage-3-css-vars` |
| 4 | Вынос inline `<style>` | 9 | `[x] ГОТОВ 2026-02-24` | `claude/ui-implementation-stage-4-z3JKU` |
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
