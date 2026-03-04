# План UI-правок — поэтапная реализация

> Файл создан для передачи между сессиями. Всё описано с точными путями и строками — реализовывать без уточнений.

---

## ~~ЭТАП 1~~ ✅ ВЫПОЛНЕНО — `homePage`: кнопка + CTA-блок

### 1.1 Кнопка "Учебные материалы" синяя на синем фоне

**Причина:** `ts-btn-ghost` использует `color: var(--color-primary)` и `border-color: var(--color-primary)`.
Hero-блок `homePage.php` имеет фон `bg-primary` (синий) → кнопка сливается.

**Файл 1:** `assets/components/testsystem/css/ts-components.css`

После строки 154 (после блока `.ts-btn-ghost-warning:hover`) добавить:

```css
.ts-btn-ghost-white {
    background-color: transparent;
    color: #FFFFFF;
    border-color: rgba(255, 255, 255, 0.8);
}

.ts-btn-ghost-white:hover {
    background-color: rgba(255, 255, 255, 0.15);
    border-color: #FFFFFF;
}
```

**Файл 2:** `core/elements/snippets/homePage.php`

Строка 34. Заменить:
```php
$output[] = '<a href="' . $materialsUrl . '" class="ts-btn ts-btn-ghost ts-btn-lg">...
```
На:
```php
$output[] = '<a href="' . $materialsUrl . '" class="ts-btn ts-btn-ghost-white ts-btn-lg">...
```

---

### 1.2 Блок "Присоединяйтесь к обучению!" показывается авторизованным

**Причина:** Сниппет вызывается кешированным (`[[homePage]]`). При первом визите гостя
`$isLoggedIn = false` сохраняется в кэш, и авторизованные пользователи видят тот же
кешированный вывод с CTA-блоком.

**Где менять:** в содержимом ресурса MODX с ID **187** (главная страница).

Найти вызов `[[homePage]]`, изменить на `[[!homePage]]` (добавить `!`).

> Это делается в админ-панели MODX: Ресурсы → ID 187 → вкладка "Содержимое".
> Если сниппет вызывается в шаблоне, а не в контенте — найти в шаблоне `TestSystem.tpl`
> или `LMS_Bootstrap_5.tpl` (папка `core/elements/templates/`).

---

## ЭТАП 2 — Подвал: статистика (информационный, код не менять)

`[[!getSystemStats]]` в `core/elements/chunks/tsFooter.tpl` (строки 62-81) —
это **реальная** статистика из БД. Сниппет `core/elements/snippets/getSystemStats.php` делает:

- `total_users` — уникальных пользователей с хотя бы 1 сессией (не все зарегистрированные!)
- `total_tests` — активных опубликованных тестов (с ресурсом в `site_content`)
- `total_sessions` — завершённых прохождений
- `avg_score` — средний балл по всем завершённым сессиям

Вызов некешированный (`[[!getSystemStats...]]`) → данные всегда актуальные.
**Ничего менять не нужно.**

---

## ~~ЭТАП 3~~ ✅ ВЫПОЛНЕНО — `knowledgeAreasManager`: алерт без `<br>` + отступ снизу

**Файл:** `core/elements/snippets/knowledgeAreasManager.php`

**Строка 60.** Заменить:
```php
$output .= '<div class="ts-alert ts-alert-info">';
```
На:
```php
$output .= '<div class="ts-alert ts-alert-info mb-4">';
```

**Строка 61.** Заменить:
```php
$output .= '<strong>💡 Что такое область знаний?</strong><br>';
```
На:
```php
$output .= '<strong>💡 Что такое область знаний?</strong> ';
```
(убрать `<br>`, заменить на пробел — текст продолжится inline после жирного)

---

## ЭТАП 4 — Убрать заголовки и хлебные крошки из сниппетов

### 4.1 Заголовки `<h1>`/`<h2>` — убрать из этих файлов

Заголовки страниц должны браться из `[[*pagetitle]]` ресурса в шаблоне, а не генерироваться сниппетом.
Удалить следующие строки целиком (вместе с содержимым, оставить пустую строку для читаемости):

| Файл | Строка | Что удалить |
|------|--------|-------------|
| `core/elements/snippets/myTests.php` | 45 | `$output .= '<h2>Мои тесты</h2>';` |
| `core/elements/snippets/categoriesList.php` | 29 | `$html[] = "<h2 class=\"mb-0\">Категории тестов</h2>";` |
| `core/elements/snippets/manageUsers.php` | 158 | `$output .= '<h2 class="mb-4">Управление пользователями</h2>';` |
| `core/elements/snippets/myCertificates.php` | 82 | `$html[] = '<h2><i class="bi bi-award me-2"></i>Мои сертификаты</h2>';` |
| `core/elements/snippets/myAchievements.php` | 38 | `$html[] = '<h2><i class="bi bi-trophy me-2"></i>Мои достижения</h2>';` |
| `core/elements/snippets/myFavorites.php` | 65 | `$output .= '<h1><i class="bi bi-star-fill text-warning"></i> Мои избранные вопросы</h1>';` |
| `core/elements/snippets/knowledgeAreasManager.php` | 53 | `$output .= '<h2><i class="bi bi-collection"></i> Мои области знаний</h2>';` |
| `core/elements/snippets/csvImportForm.php` | 381 | `$output .= '<h2><i class="bi bi-file-earmark-spreadsheet"></i> Импорт вопросов</h2>';` |
| `core/elements/snippets/adminDataIntegrity.php` | 37 | строка `<h2 class="mb-4">🔍 Проверка целостности данных</h2>` внутри heredoc |

> **НЕ ТРОГАТЬ** (это не дублирующие заголовки, а контент):
> - `homePage.php:30` — h1 внутри hero-блока (часть дизайна секции)
> - `testRunner.php` — заголовки тестов и результатов (динамический контент)
> - `learningMaterialsTemplate.php` — заголовки статей
> - `testsList.php:44` — название категории как заголовок группы
> - `categoriesAndTests.php:361` — то же самое
> - `learningPaths.php:317` — заголовок внутри модального окна редактирования

### 4.2 Хлебные крошки — оставить, но улучшить

Breadcrumb встречается только в двух местах, и оба оправданы (навигация внутри одной страницы):

**`core/elements/snippets/usersStats.php`, строки 120-125** — детальная статистика пользователя.
Текущий вид:
```
Статистика пользователей → Иван Иванов
```
Улучшить: добавить в breadcrumb username и количество тестов:

Строку 123 заменить с:
```php
'<li class="breadcrumb-item active">'.$h($user['fullname'] ?: $user['username']).'</li>
```
На:
```php
'<li class="breadcrumb-item active">'
  .$h($user['fullname'] ?: $user['username'])
  .($user['fullname'] ? ' <span class="text-muted">@'.$h($user['username']).'</span>' : '')
.'</li>
```

**`core/elements/snippets/learningPathsStats.php`, строки 63-66** — оставить как есть, достаточно информативно.

---

## ЭТАП 5 — Верхнее главное меню: динамические названия через pdoMenu

### Проблема
В `core/elements/chunks/tsHeader.tpl` основное меню (строки 27-52) жёстко задано:
- ID ресурсов: 149, 35, 193, 159, 185
- Текст названий захардкожен — переименование страницы в MODX не обновляет меню

### Решение: pdoMenu + поле `description` для иконок

**Шаг A — в MODX Admin** заполнить поле **"Описание (description)"** для каждого из 5 ресурсов:

| ID | Текущее название | Значение description |
|----|-----------------|---------------------|
| 149 | Учебные материалы | `bi-book` |
| 35  | Тесты | `bi-card-checklist` |
| 193 | Траектории | `bi-signpost-split` |
| 159 | Лидеры | `bi-trophy` |
| 185 | Области знаний | `bi-lightbulb` |

**Шаг B — в `tsHeader.tpl`** заменить строки 27-52 (5 хардкоженных `<li class="nav-item">`) на:

```smarty
{$_modx->runSnippet('pdoMenu', [
    'resources' => '149,35,193,159,185',
    'parents' => '-1',
    'level' => 1,
    'showHidden' => 1,
    'sortby' => 'menuindex',
    'sortdir' => 'ASC',
    'tplOuter' => '@INLINE [[+wrapper]]',
    'tpl' => '@INLINE <li class="nav-item"><a class="nav-link" href="[[+link]]"><i class="bi [[+description]] me-1"></i> [[+menutitle]]</a></li>',
    'tplHere' => '@INLINE <li class="nav-item"><a class="nav-link active" href="[[+link]]"><i class="bi [[+description]] me-1"></i> [[+menutitle]]</a></li>',
    'tplParentRow' => '@INLINE <li class="nav-item"><a class="nav-link" href="[[+link]]"><i class="bi [[+description]] me-1"></i> [[+menutitle]]</a></li>',
    'tplParentRowHere' => '@INLINE <li class="nav-item"><a class="nav-link active" href="[[+link]]"><i class="bi [[+description]] me-1"></i> [[+menutitle]]</a></li>'
])}
```

> `parents='-1'` + `resources='...'` — стандартный способ pdoMenu для вывода конкретных ресурсов
> в заданном порядке без привязки к родителю. Иконка берётся из `[[+description]]`.
> После этого `menutitle` в MODX Admin будет автоматически обновлять пункт меню.

---

## ЭТАП 6 — `manageCategories`: кнопки + форма добавления в модале

### 6.1 Кнопки в строке таблицы — исправить перенос на большом экране

**Файл:** `core/elements/snippets/manageCategories.php`

Строка 248: `$html[] = "<td>";` → заменить на:
```php
$html[] = "<td><div class=\"d-flex flex-wrap gap-1 align-items-center\">";
```

Строку 263 `$html[] = "</td>";` → заменить на:
```php
$html[] = "</div></td>";
```

### 6.2 Tooltip для заблокированной кнопки удаления

Строки 257-261. Текущий код:
```php
if ($cat["test_count"] == 0) {
    $html[] = "<button class=\"btn btn-sm btn-danger\" data-bs-toggle=\"modal\" data-bs-target=\"#deleteModal" . $cat["id"] . "\"><i class=\"bi bi-trash\"></i> Удалить</button>";
} else {
    $html[] = "<button class=\"btn btn-sm btn-secondary\" disabled title=\"Есть тесты\"><i class=\"bi bi-trash\"></i> Удалить</button>";
}
```

Заменить на:
```php
if ($cat["test_count"] == 0) {
    $html[] = "<button class=\"btn btn-sm btn-danger\" data-bs-toggle=\"modal\" data-bs-target=\"#deleteModal" . $cat["id"] . "\"><i class=\"bi bi-trash\"></i> Удалить</button>";
} else {
    $html[] = "<span data-bs-toggle=\"tooltip\" data-bs-placement=\"top\" title=\"Категория содержит " . $cat["test_count"] . " тестов — удаление невозможно\">";
    $html[] = "<button class=\"btn btn-sm btn-secondary\" disabled style=\"pointer-events: none;\"><i class=\"bi bi-trash\"></i> Удалить</button>";
    $html[] = "</span>";
}
```

В конце файла перед `return implode("", $html);` добавить инициализацию тултипов:
```php
$html[] = "<script>document.querySelectorAll('[data-bs-toggle=\"tooltip\"]').forEach(el => new bootstrap.Tooltip(el));</script>";
```

### 6.3 Форма добавления → в модальное окно (убрать левую колонку)

**Текущая структура** (строки 181-215):
- `<div class="row">` → левая колонка `col-md-4` с формой, правая `col-md-8` с таблицей

**Новая структура:** убрать обе колонки, оставить только `col-12` для таблицы.
Форму добавления перенести в modal.

**Что изменить:**

1. Строку 181 `$html[] = "<div class=\"row\">";` заменить на кнопку и убрать layout:
```php
$html[] = "<div class=\"d-flex justify-content-between align-items-center mb-3\">";
$html[] = "<div></div>"; // spacer (если нет другого элемента слева)
$html[] = "<button class=\"btn btn-success\" data-bs-toggle=\"modal\" data-bs-target=\"#addCategoryModal\">";
$html[] = "<i class=\"bi bi-plus-circle\"></i> Добавить категорию";
$html[] = "</button>";
$html[] = "</div>";
```

2. Удалить строки 184-215 (вся левая колонка `col-md-4` с формой).

3. Строку 217 `$html[] = "<div class=\"col-md-8\">";` заменить на `$html[] = "<div>";` (убрать ограничение колонки).

4. Строку 404 (после закрытия `</div></div>` таблицы) добавить modal с формой добавления
   (перед строкой `$html[] = "</div>"; // end row`):

```php
// Modal добавления категории
$html[] = "<div class=\"modal fade\" id=\"addCategoryModal\" tabindex=\"-1\">";
$html[] = "<div class=\"modal-dialog\">";
$html[] = "<div class=\"modal-content\">";
$html[] = "<form method=\"POST\">";
$html[] = CsrfProtection::getTokenField();
$html[] = "<input type=\"hidden\" name=\"add_category\" value=\"1\">";
$html[] = "<div class=\"modal-header\">";
$html[] = "<h5 class=\"modal-title\"><i class=\"bi bi-plus-circle\"></i> Добавить категорию</h5>";
$html[] = "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button>";
$html[] = "</div>";
$html[] = "<div class=\"modal-body\">";
$html[] = "<div class=\"mb-3\"><label class=\"form-label\">Название *</label>";
$html[] = "<input type=\"text\" name=\"name\" class=\"form-control\" required></div>";
$html[] = "<div class=\"mb-3\"><label class=\"form-label\">Описание</label>";
$html[] = "<textarea name=\"description\" class=\"form-control\" rows=\"3\"></textarea></div>";
$html[] = "<div class=\"mb-3\"><label class=\"form-label\">Порядок сортировки</label>";
$html[] = "<input type=\"number\" name=\"sort_order\" class=\"form-control\" value=\"99\">";
$html[] = "<small class=\"form-text text-muted\">Чем меньше число, тем выше в списке</small></div>";
$html[] = "</div>";
$html[] = "<div class=\"modal-footer\">";
$html[] = "<button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Отмена</button>";
$html[] = "<button type=\"submit\" class=\"btn btn-success\"><i class=\"bi bi-check-circle\"></i> Добавить</button>";
$html[] = "</div>";
$html[] = "</form></div></div></div>";
```

5. После добавления категории (при `$success`) — открыть modal с успехом или показать alert.
   Если `$success` не пустой, добавить JS для показа toast или оставить alert как есть.
   **Если форма вернула ошибку** (POST + ошибка) — нужно автоматически открыть modal обратно.
   Добавить перед `return`:
```php
if (!empty($errors) && isset($_POST['add_category'])) {
    $html[] = "<script>document.addEventListener('DOMContentLoaded', () => { new bootstrap.Modal(document.getElementById('addCategoryModal')).show(); });</script>";
}
```

---

## ЭТАП 7 — `usersStats`: padding первого и последнего столбца в таблицах

**Файл:** `core/elements/snippets/usersStats.php`

### Таблица "Последняя активность" (строки 385-412)

**Заголовок `<thead>` (строка 386):** Первый `<th>`:
```php
<th>Пользователь</th>
```
→ заменить на:
```php
<th style="padding-left: 1rem;">Пользователь</th>
```

**Последний `<th>` (строка 390):**
```php
<th class="text-end">Результат</th>
```
→ заменить на:
```php
<th class="text-end" style="padding-right: 1rem;">Результат</th>
```

**Строки данных (строка 401-408):** Первый `<td>`:
```php
<td>
  <a href="...">имя</a>
</td>
```
→ добавить `style="padding-left: 1rem;"` к `<td>`.

Последний `<td>` строки 408:
```php
<td class="text-end"><strong>...</strong></td>
```
→ заменить на:
```php
<td class="text-end" style="padding-right: 1rem;"><strong>...</strong></td>
```

### Таблица "Топ пользователей по баллам" (строки 322-357)

Аналогично — первый и последний столбец в `<thead>` и `<tbody>`:

- `<th style="width: 50px;">#</th>` (строка 324) → добавить `style="width: 50px; padding-left: 1rem;"`
- Последний `<th></th>` (строка 330, пустой столбец кнопок) → добавить `style="padding-right: 1rem;"`
- В каждой строке `<tbody>`: `<td class="text-center">` (ранг) → `<td class="text-center" style="padding-left: 1rem;">`
- Последний `<td>` с кнопкой (строка 351-355) → добавить `style="padding-right: 1rem;"`

---

## Порядок выполнения

```
Этап 3  → 1 файл, 2 строки — начать с него (самый быстрый)
Этап 1  → 2 файла + действие в MODX Admin
Этап 7  → 1 файл, ~6 строк
Этап 4  → 9 файлов, по 1 строке в каждом
Этап 6  → 1 файл, существенный рефакторинг (тестировать отдельно)
Этап 5  → tsHeader.tpl + действия в MODX Admin (5 ресурсов)
```

## Файлы-участники (сводка)

| Файл | Этапы |
|------|-------|
| `assets/components/testsystem/css/ts-components.css` | 1.1 |
| `core/elements/snippets/homePage.php` | 1.1, 1.2 |
| `core/elements/snippets/knowledgeAreasManager.php` | 3, 4.1 |
| `core/elements/snippets/usersStats.php` | 4.2, 7 |
| `core/elements/snippets/myTests.php` | 4.1 |
| `core/elements/snippets/categoriesList.php` | 4.1 |
| `core/elements/snippets/manageUsers.php` | 4.1 |
| `core/elements/snippets/myCertificates.php` | 4.1 |
| `core/elements/snippets/myAchievements.php` | 4.1 |
| `core/elements/snippets/myFavorites.php` | 4.1 |
| `core/elements/snippets/csvImportForm.php` | 4.1 |
| `core/elements/snippets/adminDataIntegrity.php` | 4.1 |
| `core/elements/chunks/tsHeader.tpl` | 5 |
| `core/elements/snippets/manageCategories.php` | 6 |
| MODX Admin: ресурс ID 187 (контент) | 1.2 |
| MODX Admin: ресурсы ID 149, 35, 193, 159, 185 (description) | 5 |
