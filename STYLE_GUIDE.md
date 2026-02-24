# Гайд по фирменному стилю LMS Test System

> Единый дизайн-стандарт для публичной части сайта.
> Стиль: современный, спокойный, минималистичный (ориентир — Instagram/Linear).
> Обновлено: 2026-02-24

---

## 1. Философия дизайна

- **Спокойствие:** никаких ярких градиентов, теней, кричащих цветов
- **Минимализм:** достаточно пространства, чёткая иерархия, отсутствие лишних деталей
- **Единообразие:** одна библиотека иконок, одна палитра, одни переменные
- **Функциональность:** каждый элемент выполняет роль, декор вторичен

---

## 2. Цветовая палитра

### CSS Custom Properties (переменные)

```css
:root {
    /* Основные */
    --color-primary:        #0095F6;   /* Акцентный синий (Instagram-style) */
    --color-primary-dark:   #0074CC;   /* Hover / нажатие */
    --color-primary-light:  #E8F4FD;   /* Фон при hover, выделения */

    /* Статусы */
    --color-success:        #00B37E;   /* Успех, правильный ответ */
    --color-success-light:  #E6F7F2;   /* Фон success */
    --color-danger:         #ED4956;   /* Ошибка, удаление */
    --color-danger-light:   #FDECEA;   /* Фон danger */
    --color-warning:        #F59E0B;   /* Предупреждение */
    --color-warning-light:  #FEF3C7;   /* Фон warning */

    /* Текст */
    --color-text-primary:   #262626;   /* Основной текст */
    --color-text-secondary: #737373;   /* Вспомогательный текст */
    --color-text-muted:     #A8A8A8;   /* Плейсхолдеры, метки */

    /* Фоны */
    --color-bg-page:        #FAFAFA;   /* Фон страницы */
    --color-bg-card:        #FFFFFF;   /* Фон карточек */
    --color-bg-hover:       #F5F5F5;   /* Hover по элементам списка */

    /* Границы */
    --color-border:         #DBDBDB;   /* Стандартная граница */
    --color-border-focus:   #0095F6;   /* Граница в фокусе */

    /* Навбар / Футер */
    --color-navbar-bg:      #FFFFFF;
    --color-navbar-border:  #DBDBDB;
    --color-footer-bg:      #262626;
    --color-footer-text:    #A8A8A8;

    /* Геймификация */
    --color-xp:             #8B5CF6;   /* XP / уровень (фиолетовый) */
    --color-xp-light:       #EDE9FE;
    --color-gold:           #F59E0B;   /* Сертификаты, лидеры */
    --color-gold-light:     #FEF3C7;
}
```

### Таблица цветов

| Токен | HEX | Применение |
|---|---|---|
| `--color-primary` | `#0095F6` | Кнопки CTA, ссылки, акценты |
| `--color-primary-dark` | `#0074CC` | Hover на primary |
| `--color-primary-light` | `#E8F4FD` | Фон выделенного элемента |
| `--color-success` | `#00B37E` | Тренировка, правильный ответ, пройден |
| `--color-success-light` | `#E6F7F2` | Фон success-состояний |
| `--color-danger` | `#ED4956` | Ошибка, удаление, неправильный ответ |
| `--color-danger-light` | `#FDECEA` | Фон danger-состояний |
| `--color-warning` | `#F59E0B` | Предупреждения, черновик |
| `--color-text-primary` | `#262626` | Заголовки, основной текст |
| `--color-text-secondary` | `#737373` | Описания, метаинформация |
| `--color-text-muted` | `#A8A8A8` | Плейсхолдеры, подписи |
| `--color-bg-page` | `#FAFAFA` | Фон страницы (`<body>`) |
| `--color-bg-card` | `#FFFFFF` | Карточки, модалки |
| `--color-border` | `#DBDBDB` | Границы полей, карточек |
| `--color-xp` | `#8B5CF6` | XP, уровень пользователя |
| `--color-gold` | `#F59E0B` | Сертификаты, топ-1 в лидерборде |

---

## 3. Типографика

### Шрифт

Системный шрифтовой стек (как в Instagram и Linear — без внешних зависимостей):

```css
font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
             Oxygen, Ubuntu, sans-serif;
```

### Размеры и веса

| Элемент | Размер | Вес | Цвет |
|---|---|---|---|
| Заголовок страницы `h1` | 28px / 1.75rem | 700 | `--color-text-primary` |
| Заголовок раздела `h2` | 22px / 1.375rem | 600 | `--color-text-primary` |
| Заголовок карточки `h3` | 18px / 1.125rem | 600 | `--color-text-primary` |
| Основной текст | 14px / 0.875rem | 400 | `--color-text-primary` |
| Вспомогательный текст | 14px / 0.875rem | 400 | `--color-text-secondary` |
| Мелкий текст / метки | 12px / 0.75rem | 400 | `--color-text-muted` |
| Кнопки | 14px / 0.875rem | 500 | — |

---

## 4. Геометрия (Border-radius и отступы)

### Border-radius

| Элемент | Значение |
|---|---|
| Кнопки | `8px` |
| Поля ввода | `8px` |
| Карточки | `12px` |
| Dropdown / Popover | `12px` |
| Модальные окна | `16px` |
| Бейджи / Теги | `6px` |
| Круглые кнопки (иконка) | `50%` |

### Отступы (padding)

| Элемент | Значение |
|---|---|
| Кнопка (sm) | `6px 12px` |
| Кнопка (md, по умолчанию) | `10px 20px` |
| Кнопка (lg) | `14px 28px` |
| Поле ввода | `10px 14px` |
| Карточка | `20px` |
| Секция страницы | `32px 0` |

---

## 5. Тени (Box-shadow)

| Применение | Значение |
|---|---|
| Карточка (default) | `0 1px 3px rgba(0, 0, 0, 0.08)` |
| Карточка (hover) | `0 4px 12px rgba(0, 0, 0, 0.12)` |
| Навбар | `0 1px 0 var(--color-border)` |
| Dropdown меню | `0 4px 16px rgba(0, 0, 0, 0.12)` |
| Модальное окно | `0 8px 32px rgba(0, 0, 0, 0.16)` |

---

## 6. Иконки

### Единственная библиотека: Bootstrap Icons

**Использовать только `bi bi-*` (Bootstrap Icons 1.11+)**

Запрещено смешивать с Font Awesome. Font Awesome подлежит удалению из подключения.

### Таблица иконок системы

| Контекст | Иконка Bootstrap Icons | Класс |
|---|---|---|
| **Навигация** | | |
| Учебные материалы | `book` | `bi bi-book` |
| Тесты | `card-checklist` | `bi bi-card-checklist` |
| Траектории | `signpost-split` | `bi bi-signpost-split` |
| Лидеры | `trophy` | `bi bi-trophy` |
| Области знаний | `lightbulb` | `bi bi-lightbulb` |
| Логотип (шапка) | `mortarboard-fill` | `bi bi-mortarboard-fill` |
| **Пользователь** | | |
| Профиль | `person-circle` | `bi bi-person-circle` |
| Мои тесты | `journal-text` | `bi bi-journal-text` |
| Мои траектории | `map` | `bi bi-map` |
| Достижения | `award` | `bi bi-award` |
| Избранное | `bookmark` | `bi bi-bookmark` |
| История | `clock-history` | `bi bi-clock-history` |
| Сертификаты | `patch-check` | `bi bi-patch-check` |
| Вход | `box-arrow-in-right` | `bi bi-box-arrow-in-right` |
| Выход | `box-arrow-right` | `bi bi-box-arrow-right` |
| **Управление** | | |
| Настройки / Управление | `gear` | `bi bi-gear` |
| Вопросы | `question-circle` | `bi bi-question-circle` |
| Импорт | `file-earmark-arrow-down` | `bi bi-file-earmark-arrow-down` |
| Удалить | `trash` | `bi bi-trash` |
| Редактировать | `pencil` | `bi bi-pencil` |
| Добавить | `plus-circle` | `bi bi-plus-circle` |
| Сохранить | `check-circle` | `bi bi-check-circle` |
| Отмена | `x-circle` | `bi bi-x-circle` |
| **Статусы и уведомления** | | |
| Уведомление | `bell` | `bi bi-bell` |
| Уведомление (активное) | `bell-fill` | `bi bi-bell-fill` |
| Успех | `check-circle-fill` | `bi bi-check-circle-fill` |
| Ошибка | `x-circle-fill` | `bi bi-x-circle-fill` |
| Предупреждение | `exclamation-triangle` | `bi bi-exclamation-triangle` |
| Информация | `info-circle` | `bi bi-info-circle` |
| **Тестирование** | | |
| Тренировка | `play-circle` | `bi bi-play-circle` |
| Экзамен | `pencil-square` | `bi bi-pencil-square` |
| Таймер | `alarm` | `bi bi-alarm` |
| Вопросов | `list-ol` | `bi bi-list-ol` |
| Проходной балл | `bar-chart` | `bi bi-bar-chart` |
| **Геймификация** | | |
| XP / Очки | `star-fill` | `bi bi-star-fill` |
| Уровень | `lightning-fill` | `bi bi-lightning-fill` |
| Стрик | `fire` | `bi bi-fire` |
| Достижение | `award-fill` | `bi bi-award-fill` |
| **Контент** | | |
| Статья (материал) | `file-text` | `bi bi-file-text` |
| Видео | `play-btn` | `bi bi-play-btn` |
| Документ | `file-earmark-pdf` | `bi bi-file-earmark-pdf` |
| Поиск | `search` | `bi bi-search` |
| Фильтр | `funnel` | `bi bi-funnel` |
| **Прочее** | | |
| Меню (три точки) | `three-dots-vertical` | `bi bi-three-dots-vertical` |
| Сортировка (drag) | `grip-vertical` | `bi bi-grip-vertical` |
| Ссылка / Верификация | `link-45deg` | `bi bi-link-45deg` |
| Пользователи | `people` | `bi bi-people` |
| Аналитика | `graph-up` | `bi bi-graph-up` |
| Статистика | `bar-chart-line` | `bi bi-bar-chart-line` |

---

## 7. Кнопки

### Варианты

```html
<!-- Primary: основное действие (одно на экран) -->
<button class="ts-btn ts-btn-primary">Начать тест</button>

<!-- Secondary: второстепенное действие -->
<button class="ts-btn ts-btn-secondary">Отмена</button>

<!-- Success: позитивное действие (тренировка) -->
<button class="ts-btn ts-btn-success">
  <i class="bi bi-play-circle me-1"></i> Тренировка
</button>

<!-- Danger: деструктивное действие -->
<button class="ts-btn ts-btn-danger">
  <i class="bi bi-trash me-1"></i> Удалить
</button>

<!-- Ghost: без фона, только граница -->
<button class="ts-btn ts-btn-ghost">Подробнее</button>

<!-- Размеры -->
<button class="ts-btn ts-btn-primary ts-btn-sm">Маленький</button>
<button class="ts-btn ts-btn-primary">Стандартный</button>
<button class="ts-btn ts-btn-primary ts-btn-lg">Большой</button>

<!-- Иконка + текст -->
<button class="ts-btn ts-btn-primary">
  <i class="bi bi-plus-circle me-1"></i> Создать тест
</button>

<!-- Только иконка (круглая) -->
<button class="ts-btn ts-btn-icon-only" title="Настройки">
  <i class="bi bi-gear"></i>
</button>
```

### Состояния

| Состояние | Описание |
|---|---|
| Default | Основной вид |
| Hover | Темнее на 8%, нет подъёма (`transform`) |
| Active | Темнее на 12% |
| Disabled | `opacity: 0.5`, `cursor: not-allowed` |
| Loading | Spinner внутри + `disabled` |

**Запрещено:** `transform: translateY(-2px)` на hover у кнопок — вызывает «прыжок», не подходит для минималистичного стиля.

---

## 8. Поля ввода и формы

```html
<!-- Стандартное поле -->
<div class="ts-field">
  <label class="ts-label">Название теста</label>
  <input type="text" class="ts-input" placeholder="Введите название">
</div>

<!-- Поле с ошибкой -->
<div class="ts-field ts-field-error">
  <label class="ts-label">Email</label>
  <input type="email" class="ts-input" value="bad@">
  <span class="ts-field-hint">Неверный формат email</span>
</div>

<!-- Поле с подсказкой -->
<div class="ts-field">
  <label class="ts-label">Количество вопросов</label>
  <input type="number" class="ts-input" value="10">
  <span class="ts-field-hint">От 1 до 100</span>
</div>

<!-- Select -->
<select class="ts-select">
  <option>Выберите категорию</option>
</select>

<!-- Textarea -->
<textarea class="ts-textarea" rows="4" placeholder="Описание"></textarea>
```

### Правила для форм

- Метка (`label`) всегда над полем, не внутри (placeholder — только подсказка)
- Состояния: default → focus (синяя граница) → error (красная граница + сообщение)
- Группировка полей через `mb-3` (Bootstrap)
- Кнопки формы выровнены по правому краю или растянуты на полную ширину

---

## 9. Карточки

```html
<div class="ts-card">
  <div class="ts-card-header">
    <h3 class="ts-card-title">Название теста</h3>
    <button class="ts-btn ts-btn-icon-only">
      <i class="bi bi-three-dots-vertical"></i>
    </button>
  </div>
  <div class="ts-card-body">
    <p class="ts-card-description">Описание теста...</p>
    <!-- метаданные -->
    <div class="ts-meta">
      <span class="ts-meta-item">
        <i class="bi bi-list-ol text-muted"></i>
        <span>20 вопросов</span>
      </span>
      <span class="ts-meta-item">
        <i class="bi bi-alarm text-muted"></i>
        <span>30 мин</span>
      </span>
    </div>
  </div>
  <div class="ts-card-footer">
    <button class="ts-btn ts-btn-success ts-btn-lg w-100">
      <i class="bi bi-play-circle me-1"></i> Начать
    </button>
  </div>
</div>
```

---

## 10. Навигационная панель

```
┌──────────────────────────────────────────────────────────────────────┐
│  🎓 LMS Test System  │  Материалы  Тесты  Траектории  Лидеры        │  🔔  👤 Иван ▾  │
└──────────────────────────────────────────────────────────────────────┘
```

- Фон: белый (`--color-navbar-bg: #FFFFFF`)
- Нижняя граница: `1px solid var(--color-border)`
- Нет тени (только граница)
- Логотип: `bi bi-mortarboard-fill` + название сайта (жирный)
- Активный пункт меню: цвет `--color-primary`, нет подчёркивания

---

## 11. Алерты и уведомления

| Тип | Иконка | Цвет фона | Цвет рамки |
|---|---|---|---|
| Success | `bi bi-check-circle-fill` | `--color-success-light` | `--color-success` |
| Danger | `bi bi-x-circle-fill` | `--color-danger-light` | `--color-danger` |
| Warning | `bi bi-exclamation-triangle` | `--color-warning-light` | `--color-warning` |
| Info | `bi bi-info-circle` | `--color-primary-light` | `--color-primary` |

- Нет `box-shadow`
- `border-radius: 8px`
- Левая граница `3px solid` вместо полного `border` (для information-style alertов)

---

## 12. Бейджи и теги

| Тип | CSS | Применение |
|---|---|---|
| Primary | `bg: --color-primary-light`, `color: --color-primary` | Режим: экзамен |
| Success | `bg: --color-success-light`, `color: --color-success` | Режим: тренировка |
| Warning | `bg: --color-warning-light`, `color: --color-warning` | Статус: черновик |
| Neutral | `bg: #F5F5F5`, `color: --color-text-secondary` | Категория, теги |
| XP | `bg: --color-xp-light`, `color: --color-xp` | Очки опыта |
| Gold | `bg: --color-gold-light`, `color: --color-gold` | Сертификаты |

```html
<span class="ts-badge ts-badge-success">Тренировка</span>
<span class="ts-badge ts-badge-primary">Экзамен</span>
<span class="ts-badge ts-badge-xp"><i class="bi bi-star-fill me-1"></i>+50 XP</span>
```

---

## 13. Что запрещено

| Запрещено | Почему |
|---|---|
| `fas fa-*` (Font Awesome) | Используем только `bi bi-*` |
| Inline CSS стили (`style="..."`) | Только CSS-классы |
| Inline `<style>` в сниппетах/чанках PHP | Стили — в CSS-файлы |
| Жёсткие цвета (`#0d6efd`, `#28a745`) | Только через переменные |
| `transform: translateY(-2px)` у кнопок | Нарушает минимализм |
| Крупные тени `box-shadow: 0 8px 30px` | Только тонкие тени |
| Несколько разных `border-radius` | Только из палитры (6/8/12/16px) |
| Градиентные кнопки | Плоские цвета |
| Bootstrap-утилитарные классы для кастомных компонентов | `btn-primary` → `ts-btn-primary` |

---

## 14. Файловая структура стилей (целевая)

```
assets/components/testsystem/css/
├── ts-variables.css      # CSS Custom Properties (палитра, размеры)
├── ts-base.css           # Типографика, body, базовые сбросы
├── ts-components.css     # Кнопки, поля, карточки, бейджи, алерты
├── ts-layout.css         # Навбар, футер, сетка страниц
├── ts-runner.css         # Специфика тест-раннера
└── ts-modules.css        # Специфика отдельных модулей (пути, материалы...)
```

Все три текущих файла (`tsrunner.css`, `testsystem-extended.css`, `categories-and-tests.css`) будут рефакторизованы в эту структуру на этапе внедрения.
