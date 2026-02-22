# LMS Test System for MODX Revolution

Система дистанционного обучения и тестирования на базе MODX Revolution.

## Документация

| Файл | Содержание |
|------|-----------|
| **[ARCHITECTURE.md](ARCHITECTURE.md)** | Архитектура, схема БД, API, паттерны, структура проекта |
| **[DEVELOPMENT_RULES.md](DEVELOPMENT_RULES.md)** | Правила MODX+Fenom, ошибки и решения, чеклисты, недокументированные особенности |

> Детальные гайды (установка, миграции, настройка модулей) находятся в `docs/archive/`.

## Стек технологий

- **MODX Revolution 2.8+** — CMS с Fenom-шаблонизатором
- **PHP 7.4+** — Service Layer, Repository Pattern
- **MySQL 5.7+** — 51 таблица
- **JavaScript** — Vanilla JS, Bootstrap 5, Fetch API

## Структура проекта

```
assets/components/testsystem/    # Frontend: JS, CSS, AJAX-точка входа, шаблоны
core/components/testsystem/      # Backend: сервисы, контроллеры, репозитории, SQL
core/elements/snippets/          # MODX-сниппеты (32+)
docs/archive/                    # Архивная документация
```

## Основные возможности

- 120 API-эндпоинтов, 13 контроллеров, 14 сервисов
- 6 типов вопросов (single, multiple, matching, ordering, fill_blank, essay)
- Образовательные траектории (Learning Paths)
- Геймификация (XP, уровни, достижения, лидерборд)
- Сертификаты с верификацией
- Система экспертов по категориям
- RBAC с 6 уровнями доступа
- CSRF-защита для всех state-changing операций

## Лицензия

Проприетарное ПО
