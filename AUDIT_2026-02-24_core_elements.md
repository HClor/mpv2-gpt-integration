# Аудит core/elements (chunks, templates, plugin) — 2026-02-24

> **Статус:** Аудит завершён. Критические исправления применены в коммите `34d6fc3`.

## Область аудита

- `core/elements/chunks/` — 21 файл (Fenom-шаблоны)
- `core/elements/templates/` — 7 файлов (MODX-шаблоны)
- `core/elements/plugins/` — 1 файл (PHP-плагин)

**Итого:** 29 файлов

---

## 1. ИСПРАВЛЕННЫЕ ПРОБЛЕМЫ

### 1.1 XSS через незаэкранированный вывод Fenom (10 файлов)

Почти все чанки выводили ресурсные поля (`pagetitle`, `longtitle`, `subtitle`, `username`, `description`, `keywords`) в HTML-атрибуты и текстовые узлы **без `| escape`**. Символы `"`, `<`, `>` в заголовках могли вызвать инъекцию атрибутов или XSS.

**Исправлено в:**
- `aside.tpl` — `{$pagetitle}`, `{$longtitle}`, `{$subtitle}` в inline-шаблонах pdoResources
- `block.gallery.tpl` — `{$galItem.title}` в `title=` и `alt=`
- `child_list.tpl` — `{$pagetitle}`, `{$longtitle}` в inline-шаблоне pdoPage
- `gallery.tpl` — `{$galItem.title}` в `title=` и `alt=`
- `content_spec.tpl` — `{$pagetitle}` заменено на `{$_modx->resource.pagetitle | escape}`
- `content_spec_list.tpl` — `{$pagetitle}`, `{$longtitle}`, `{$subtitle}`
- `head.tpl` — `keywords` meta-тег (был `| strip_tags` без `| escape`), `og:title`, `og:site_name`
- `tsHead.tpl` — `description` и `keywords` meta-теги
- `tsHeader.tpl` — `{$_modx->user.username}` в меню пользователя
- `tpl.contact_form.tpl` — `{$name}`, `{$phone}`, `{$message}` в email-шаблоне

### 1.2 DOM XSS (2 файла)

- **scripts.tpl** — `response.message` вставлялся через конкатенацию в `$.fancybox.open()` без санитизации. Исправлено: значение экранируется через `$('<span>').text(...).html()`.
- **tsScripts.tpl** — `showLpNotification()` использовал `innerHTML` для вставки сообщения. Исправлено: заменено на `textContent`.

### 1.3 CSS-комментарий (tsHead.tpl)

Строка 96: `/* Стили для уведомлений -->` — HTML-окончание `-->` внутри CSS-комментария. CSS-парсер не находил `*/`, что **ломало все стили** ниже (`.alert`, `.user-xp`).

**Исправлено:** `/* Стили для уведомлений */`

### 1.4 Switch fallthrough (content.tpl)

В `{switch}` блоках отсутствовали `{break}`, из-за чего при `resource.id == 1` выполнялись оба case (1 и 4) — двойной include чанков.

**Исправлено:** добавлены `{break}` в каждый case.

### 1.5 Ссылка на чанк `[[*tsFooter]]` (2 шаблона)

В `learning-materials-template.html` и `learning-materials-template-v2.html` использовался `[[*tsFooter]]` (поле ресурса) вместо `[[$tsFooter]]` (чанк). Footer не рендерился.

**Исправлено:** `[[*tsFooter]]` → `[[$tsFooter]]`

### 1.6 Отсутствие CSRF в notification polling (tsScripts.tpl)

Автообновление уведомлений (каждые 30 сек) не отправляло CSRF-токен, в отличие от всех остальных API-запросов через `tsApiRequest()`.

**Исправлено:** добавлен заголовок `'X-CSRF-Token': getCSRFToken()`.

---

## 2. НЕРЕШЁННЫЕ ПРОБЛЕМЫ (требуют отдельных задач)

### 2.1 Смешение Fenom и MODX-тегов

**Файлы:** `tsFooter.tpl`, `tsHead.tpl`, `tsHeader.tpl`, `LMS_Bootstrap_5.tpl`, `TestSystem.tpl`

Эти файлы смешивают Fenom-синтаксис (`{$_modx->...}`, `{if}`) с MODX-тегами (`[[!snippet]]`, `[[+placeholder]]`). Это усложняет поддержку и может вызвать проблемы с порядком парсинга.

**Рекомендация:** Унифицировать на Fenom через `$_modx->runSnippet()` / `$_modx->getPlaceholder()`.

### 2.2 Bootstrap 3 / Bootstrap 5 конфликт

Чанки siteExtra (`aside.tpl`, `header.tpl`, `child_list.tpl`, `content_spec_list.tpl`, `menu.tpl`, `footer.tpl`) используют классы **Bootstrap 3** (`glyphicon`, `media`, `thumbnail`, `navbar-default`, `col-xs-*`), тогда как `head.tpl` подключает **Bootstrap 5** CSS.

**Результат:** Bootstrap 3 компоненты отображаются некорректно.

**Рекомендация:** Мигрировать siteExtra-чанки на Bootstrap 5 или подключить Bootstrap 3 CSS.

### 2.3 Хардкод ресурсных ID (30+)

В чанках и шаблонах жёстко прописаны ID ресурсов: 1, 4, 14, 15, 24, 28, 34, 35, 115, 149, 157, 159, 169, 180, 181–186, 191, 193, 194, 200–202.

**Рекомендация:** Вынести в системные настройки MODX (`system_settings`).

### 2.4 PHP в шаблонах

`learning-materials-template.html` (v1) и `learning-materials-template-v2.html` содержат PHP-код прямо в шаблоне — анти-паттерн в MODX. Шаблон v3 исправляет это, делегируя логику сниппету `learningMaterialsTemplate`.

**Рекомендация:** Удалить v1 и v2 после полного перехода на v3.

### 2.5 Устаревшие шаблоны

- `base.tpl` — стоковый шаблон MODX Revolution (welcome page), не используется в LMS
- `learning-materials-template.html` (v1) — заменён v3
- `learning-materials-template-v2.html` — заменён v3

**Рекомендация:** Удалить или отключить в продакшене.

### 2.6 Сырой вывод TV-полей (jsTV, cssTV)

В `tsScripts.tpl` (`{$_modx->resource.jsTV}`) и `tsHead.tpl` (`{$_modx->resource.cssTV}`) TV-поля выводятся без экранирования. Если эти поля доступны для редактирования не-админами — это XSS-вектор.

**Рекомендация:** Ограничить редактирование этих TV ролью admin; добавить CSP-заголовки.

### 2.7 Дублирование кода

- `block.gallery.tpl` и `gallery.tpl` — почти идентичны
- `child_list.tpl` и `content_spec_list.tpl` — дублируют шаблон пагинации
- `aside.tpl` — два одинаковых вызова pdoResources с разным offset

**Рекомендация:** Извлечь общие шаблоны пагинации в отдельный чанк.

### 2.8 `<script>` в `<head>` (head.tpl)

Bootstrap JS загружается в `<head>` (строка 57), что блокирует рендеринг. Должен быть перед `</body>`.

---

## 3. ПОЛНЫЙ ПЕРЕЧЕНЬ ФАЙЛОВ

### Chunks (21)

| Файл | Строк | Исправлен | Проблемы |
|---|---|---|---|
| aside.tpl | 77 | Да | XSS, хардкод ID, BS3 классы |
| block.gallery.tpl | 21 | Да | XSS |
| child_list.tpl | 44 | Да | XSS, BS3 классы |
| contact_form.tpl | 29 | Нет | Чистый |
| content.tpl | 23 | Да | Switch fallthrough |
| content_default.tpl | 26 | Нет | `| raw` на content (намеренно) |
| content_main.tpl | 9 | Нет | Чистый |
| content_spec.tpl | 14 | Да | XSS, неверная переменная |
| content_spec_list.tpl | 48 | Да | XSS, BS3 классы |
| footer.tpl | 22 | Нет | BS3 классы |
| form.contact_form.tpl | 53 | Нет | Нет CSRF (зависит от AjaxForm) |
| gallery.tpl | 17 | Да | XSS |
| head.tpl | 58 | Да | XSS в meta, script в head |
| header.tpl | 24 | Нет | `<nobr>` deprecated, BS3 |
| menu.tpl | 12 | Нет | BS3 классы |
| scripts.tpl | 23 | Да | DOM XSS |
| tpl.contact_form.tpl | 12 | Да | XSS в email |
| tsFooter.tpl | 104 | Нет | Смешение Fenom/MODX, хардкод ID |
| tsHead.tpl | 114 | Да | CSS баг, XSS в meta |
| tsHeader.tpl | 143 | Да | XSS username, смешение синтаксиса |
| tsScripts.tpl | 353 | Да | DOM XSS, CSRF, raw jsTV |

### Templates (7)

| Файл | Строк | Исправлен | Проблемы |
|---|---|---|---|
| LMS_Bootstrap_5.tpl | 82 | Нет | Смешение, raw вывод, хардкод ID |
| TestSystem.tpl | 28 | Нет | Кэшируемый pdoCrumbs |
| base.tpl | 330 | Нет | Устаревший (удалить) |
| learning-materials-template.html | 225 | Да | `[[*tsFooter]]` баг, PHP в шаблоне |
| learning-materials-template-v2.html | 433 | Да | `[[*tsFooter]]` баг, PHP в шаблоне |
| learning-materials-template-v3.html | 19 | Нет | Эталонный шаблон |
| siteExtra.tpl | 22 | Нет | Чистый |

### Plugins (1)

| Файл | Строк | Исправлен | Проблемы |
|---|---|---|---|
| testSystemCascadeDelete.php | 136 | Нет | Используется параметризованный SQL (безопасно). Возможна оптимизация batch-удаления. |

---

## 4. СТАТИСТИКА ИСПРАВЛЕНИЙ

- **Файлов исправлено:** 15
- **XSS уязвимостей закрыто:** ~30 точек вывода
- **Логических багов исправлено:** 3 (switch fallthrough, CSS comment, chunk reference)
- **CSRF:** 1 (notification polling)
