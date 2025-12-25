# LMS Test System for MODX Revolution

Система дистанционного обучения и тестирования на базе MODX Revolution.

## Быстрый старт

Для начала работы с системой смотрите:
- **[Руководство по быстрому старту](docs/guides/QUICKSTART.md)** - установка и запуск за 15-30 минут
- **[Руководство по установке MODX](docs/guides/MODX_INSTALLATION_GUIDE.md)** - детальная установка

## Документация

### Для разработчиков

- **[Руководство по реализации](docs/guides/IMPLEMENTATION_GUIDE.md)** - архитектура, использование сервисов
- **[Руководство по отладке](docs/guides/DEBUGGING_GUIDE.md)** - решение проблем
- **[Руководство по стилю UI](docs/guides/UI_STYLE_GUIDE.md)** - стандарты интерфейса

### Установка и настройка

- **[Установка шаблонов](docs/guides/TEMPLATE_INSTALLATION.md)**
- **[Настройка системных параметров MODX](docs/guides/MODX_SYSTEM_SETTINGS_GUIDE.md)**
- **[Настройка прав доступа](docs/guides/DEPLOYMENT_GUIDE_PERMISSIONS.md)**
- **[Установка меню администратора](docs/guides/ADMIN_MENU_INSTALLATION.md)**

### Функциональные модули

- **[Настройка экспертов по категориям](docs/guides/CATEGORY_EXPERTS_GUIDE.md)**
- **[Настройка образовательных траекторий](docs/guides/LEARNING_PATHS_SETUP.md)**
- **[Настройка страницы "Мои тесты"](docs/guides/MY_TESTS_PAGE_SETUP.md)**

### Исправления и миграции

- **[Быстрое руководство по миграции](docs/guides/QUICK_MIGRATION_GUIDE.md)**
- **[Исправление работы чанков](docs/guides/FIX_CHUNKS_NOT_WORKING.md)**
- **[Исправление выхода из системы](docs/guides/LOGOUT_FIX_INSTALLATION.md)**

### Дополнительная документация

- **[Полное руководство по настройке LMS](docs/LMS_FULL_SETUP_GUIDE.md)**
- **[Утилиты и инструменты](docs/README.md)** - SQL скрипты, экспорт БД

## Структура проекта

```
/
├── assets/components/testsystem/  # Frontend компоненты
│   ├── ajax/                      # AJAX обработчики
│   ├── controllers/               # Frontend контроллеры
│   ├── templates/                 # Шаблоны
│   └── css/                       # Стили
│
├── core/components/testsystem/    # Backend компоненты
│   ├── bootstrap.php              # Инициализация
│   ├── services/                  # Сервисный слой (14 сервисов)
│   ├── repositories/              # Репозитории данных
│   ├── controllers/               # Backend контроллеры
│   └── sql/                       # SQL миграции
│
└── docs/                          # Документация
    ├── guides/                    # Руководства
    └── archive/                   # Архивная документация
```

## Технологии

- **MODX Revolution** - CMS платформа
- **PHP** - Backend
- **MySQL/MariaDB** - База данных
- **JavaScript** - Frontend
- **Fenom** - Шаблонизатор

## Основные возможности

- Создание и управление тестами
- Образовательные траектории (Learning Paths)
- Система экспертов по категориям
- Управление правами доступа
- Статистика и отчеты
- AJAX интерфейс

## Поддержка

Для получения помощи обращайтесь к документации в папке `docs/guides/`.

## Лицензия

Проприетарное ПО
