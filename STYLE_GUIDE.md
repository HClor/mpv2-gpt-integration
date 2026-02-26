# Гайд по фирменному стилю LMS Test System

> Единый дизайн-стандарт для публичной части сайта.
> Стиль: корпоративный, минималистичный, спокойный.
> Обновлено: 2026-02-26

---

## 1. Философия

- **Спокойствие** — никаких градиентов, прыгающих карточек, геймерских эффектов
- **Солидность** — строгая геометрия, нейтральные цвета
- **Консистентность** — единая система токенов из `ts-variables.css`
- **Функциональность** — каждый элемент выполняет задачу

---

## 2. Цвета (токены из ts-variables.css)

### Primary (корпоративный синий)
```
--color-primary:        #2563EB
--color-primary-dark:   #1E4FCC
--color-primary-light:  #EAF1FD
--color-primary-text:   #1E3A8A
```

### Статусы
```
--color-success:        #059669   --color-success-dark:  #047857
--color-success-light:  #E6F7F2   --color-success-text:  #065F46

--color-danger:         #DC2626   --color-danger-dark:   #B91C1C
--color-danger-light:   #FDECEA   --color-danger-text:   #7F1D1D

--color-warning:        #D97706   --color-warning-dark:  #B45309
--color-warning-light:  #FEF3C7   --color-warning-text:  #78350F
```

### Геймификация (умеренно)
```
--color-xp:    #7C3AED   --color-xp-light:   #EDE9FE
--color-gold:  #CA8A04   --color-gold-light:  #FEF3C7
```

### Grayscale / Фоны / Текст
```
--color-gray-50..900  (от #FAFAFA до #171717)
--color-bg-page:      var(--color-gray-50)
--color-bg-card:      #FFFFFF
--color-bg-hover:     var(--color-gray-100)
--color-bg-subtle:    var(--color-gray-150)
--color-text-primary: var(--color-gray-800)
--color-text-secondary: var(--color-gray-600)
--color-text-muted:   var(--color-gray-400)
--color-border:       var(--color-gray-300)
--color-border-strong: var(--color-gray-400)
--color-disabled-bg:  #F3F4F6   --color-disabled-text: #9CA3AF
```

---

## 3. Типографика

Системный стек: `-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Ubuntu, sans-serif`

| Элемент        | Размер | Вес |
|----------------|--------|-----|
| h1             | 28px   | 600 |
| h2             | 22px   | 600 |
| h3             | 18px   | 600 |
| Основной текст | 14px   | 400 |
| Мелкий текст   | 12px   | 400 |
| Кнопки         | 14px   | 500 |

---

## 4. Геометрия и тени

### Border-radius
```
--radius-badge/btn/input:  6px
--radius-alert:            8px
--radius-card/dropdown:   10px
--radius-modal:           14px
--radius-pill:           999px
```

### Box-shadow
```
--shadow-card:       0 1px 2px rgba(0,0,0,0.06)
--shadow-card-hover: 0 2px 8px rgba(0,0,0,0.08)
--shadow-dropdown:   0 4px 16px rgba(0,0,0,0.10)
--shadow-modal:      0 8px 24px rgba(0,0,0,0.14)
--focus-ring:        0 0 0 3px rgba(37,99,235,0.15)
```

⚠ Hover никогда не вызывает смещения (`transform: translateY` — запрещён).

---

## 5. Компоненты (ts-components.css)

### Кнопки `.ts-btn`
Варианты: `ts-btn-primary`, `ts-btn-secondary`, `ts-btn-success`, `ts-btn-danger`, `ts-btn-warning`, `ts-btn-ghost`, `ts-btn-ghost-danger`, `ts-btn-ghost-success`, `ts-btn-ghost-warning`
Размеры: `ts-btn-sm`, `ts-btn-lg`
Иконка: `ts-btn-icon-only` (круглая, 36×36px)

### Карточки `.ts-card`
```
.ts-card > .ts-card-header / .ts-card-body / .ts-card-footer
```
Hover — только через тень, без transform.

### Формы
`.ts-input`, `.ts-select`, `.ts-textarea` — focus через `--focus-ring`

### Алерты `.ts-alert`
`ts-alert-success`, `ts-alert-danger`, `ts-alert-warning`, `ts-alert-info`
Левая граница 3px, без box-shadow.

---

## 6. Файловая структура

```
ts-variables.css        ← все токены (единственный файл с HEX)
ts-components.css       ← кнопки, карточки, формы, алерты
ts-layout.css           ← body, navbar, footer, breadcrumb, XP-бейдж
tsrunner.css
testsystem-extended.css
categories-and-tests.css
```

### Порядок подключения
1. `ts-variables.css`
2. Bootstrap 5.3
3. Bootstrap Icons 1.11+
4. `ts-components.css`
5. `ts-layout.css`
6. Остальные модульные файлы

---

## 7. Запрещено

- Font Awesome (только `bi bi-*`)
- Inline CSS (`style="..."`)
- HEX-значения вне `ts-variables.css`
- Градиенты
- `transform: translateY()` на hover
- Несогласованные radii / агрессивные тени
- Bootstrap-классы вместо `ts-*` для кастомных компонентов
