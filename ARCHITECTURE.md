# Архитектура LMS Test System

> Консолидированная документация по архитектуре, схеме БД, API и принятым решениям.
> Обновлено: 2026-02-22

---

## 1. Обзор системы

**LMS Test System** — система дистанционного обучения и тестирования на базе MODX Revolution.

**Стек технологий:**
- **CMS:** MODX Revolution 2.8.0+ с шаблонизатором Fenom
- **Backend:** PHP 7.4+, Service Layer + Repository Pattern
- **Frontend:** Vanilla JS (без jQuery), Bootstrap 5, Fetch API
- **БД:** MySQL 5.7+ / MariaDB
- **Безопасность:** CSRF-токены, RBAC (6 уровней), XSS-фильтрация

**Статус:** 17 спринтов, 120 API-эндпоинтов, 13 контроллеров, 14+ сервисов, 51 таблица БД.

---

## 2. Структура проекта

```
/
├── assets/components/testsystem/        # Frontend (публичная часть)
│   ├── ajax/                            # HTTP-точки входа
│   │   ├── testsystem.php               # Главный API-роутер (3729 строк)
│   │   ├── upload-image.php             # Загрузка изображений
│   │   └── upload-document.php          # Загрузка документов
│   ├── controllers/                     # HTTP-контроллеры (15 шт, 6519 строк)
│   │   ├── BaseController.php           # Абстрактный базовый
│   │   ├── ControllerFactory.php        # Роутер action→controller
│   │   ├── SessionController.php        # Сессии тестирования
│   │   ├── TestController.php           # CRUD тестов
│   │   ├── QuestionController.php       # CRUD вопросов
│   │   ├── AdminController.php          # Администрирование
│   │   ├── LearningPathController.php   # Траектории (1617 строк)
│   │   ├── MaterialController.php       # Учебные материалы
│   │   ├── CategoryController.php       # Категории
│   │   ├── CertificateController.php    # Сертификаты
│   │   ├── GamificationController.php   # Геймификация
│   │   ├── NotificationController.php   # Уведомления
│   │   ├── AnalyticsController.php      # Аналитика
│   │   ├── FavoriteController.php       # Избранное
│   │   └── SpecialQuestionController.php
│   ├── js/                              # JS-модули (15 файлов, 12680 строк)
│   │   ├── tsrunner.js                  # Тест-раннер (4003 строк)
│   │   ├── learning-paths.js            # Траектории (1685 строк)
│   │   ├── test-cards.js                # Карточки тестов (1357 строк)
│   │   ├── mytests.js                   # Мои тесты
│   │   ├── knowledge-areas.js           # Области знаний
│   │   ├── gamification.js              # Геймификация
│   │   ├── category-permissions.js      # Права категорий
│   │   ├── learning-materials.js        # Учебные материалы
│   │   ├── special-question-types.js    # Спец. вопросы
│   │   ├── notifications.js             # Уведомления
│   │   ├── category-experts.js          # Эксперты
│   │   ├── test-permissions.js          # Права тестов
│   │   ├── certificates.js              # Сертификаты
│   │   ├── analytics.js                 # Аналитика
│   │   └── tests-search.js             # Поиск (заглушка, 42 строки)
│   ├── css/                             # Стили (3 файла, 3395 строк)
│   │   ├── tsrunner.css                 # Стили тест-раннера
│   │   ├── testsystem-extended.css      # Расширенные стили
│   │   └── categories-and-tests.css     # Стили каталога
│   └── images/                          # Загруженные изображения (user content)
│
├── core/components/testsystem/          # Backend (приватная часть)
│   ├── bootstrap.php                    # Автозагрузчик классов (SPL)
│   ├── services/                        # Бизнес-логика (14 сервисов)
│   │   ├── TestService.php              # Тесты
│   │   ├── SessionService.php           # Сессии
│   │   ├── CertificateService.php       # Сертификаты
│   │   ├── GamificationService.php      # Геймификация
│   │   ├── LearningPathService.php      # Траектории (самый большой)
│   │   ├── LearningMaterialService.php  # Учебные материалы
│   │   ├── AnalyticsService.php         # Аналитика
│   │   ├── NotificationService.php      # Уведомления
│   │   ├── CategoryPermissionService.php
│   │   ├── DataIntegrityService.php     # Целостность данных
│   │   ├── ReportService.php            # Отчёты/экспорт
│   │   ├── QuestionTypeService.php      # Типы вопросов
│   │   ├── AccessService.php            # Контроль доступа
│   │   └── AuthService.php              # Аутентификация
│   ├── repositories/                    # Data Access Layer
│   │   ├── BaseRepository.php
│   │   └── TestRepository.php           # 16 методов
│   ├── helpers/                         # Утилиты (6 шт)
│   │   ├── ResponseHelper.php           # JSON-ответы
│   │   ├── ValidationHelper.php         # Валидация
│   │   ├── PermissionHelper.php         # Проверка прав
│   │   ├── UrlHelper.php                # URL-генератор
│   │   ├── Config.php                   # Конфигурация
│   │   └── QuestionImportHelper.php     # Импорт вопросов
│   ├── exceptions/                      # Иерархия исключений
│   │   ├── TestSystemException.php      # Базовый (500)
│   │   ├── NotFoundException.php        # 404
│   │   ├── ValidationException.php      # 400
│   │   ├── PermissionException.php      # 403
│   │   └── AuthenticationException.php  # 401
│   ├── security/
│   │   └── CsrfProtection.php
│   ├── cron/                            # Cron-задачи
│   ├── migrations/                      # Миграции БД
│   ├── config/                          # Конфигурация
│   └── sql/                             # SQL-схемы
│       └── FULL_INSTALLATION_FIXED.sql  # Каноническая схема
│
├── core/elements/snippets/              # MODX-сниппеты (32+)
│
├── docs/archive/                        # Архивная документация
│
├── ARCHITECTURE.md                      # ← ВЫ ЗДЕСЬ
├── DEVELOPMENT_RULES.md                 # Правила разработки, ошибки и решения
└── README.md                            # Точка входа
```

---

## 3. Паттерны проектирования

### 3.1. Service Layer
Бизнес-логика вынесена из контроллеров в сервисы. Контроллер только валидирует вход и вызывает сервис:

```php
// Контроллер (16 строк вместо 195)
case 'createTestWithPage':
    PermissionHelper::requireAuthentication($modx, 'Login required');
    $userId = PermissionHelper::getCurrentUserId($modx);
    $title = ValidationHelper::requireString($data, 'title', 'Title is required');
    $result = TestService::createTestWithPage($modx, $title, $description, $status, $userId);
    $response = ResponseHelper::success($result, 'Test created');
    break;
```

### 3.2. Repository Pattern
SQL-запросы инкапсулированы в `TestRepository` (16 методов). Устранено дублирование SQL.

### 3.3. Exception Hierarchy
Иерархия исключений с HTTP-кодами: `TestSystemException → NotFoundException, ValidationException, PermissionException, AuthenticationException`.

### 3.4. Strategy Pattern
Типы вопросов (`single`, `multiple`, `matching`, `ordering`, `fill_blank`, `essay`) обрабатываются стратегиями.

### 3.5. Архитектурное решение: без namespace
Отказ от PHP namespace ради совместимости с MODX Revolution. Автозагрузка через `bootstrap.php`. Соответствие PSR-1, PSR-12 (PSR-4 частично).

### 3.6. Архитектурное решение: бизнес-логика в PHP, не в триггерах БД
Логика XP, streak, сертификатов перенесена из триггеров MySQL в PHP-сервисы. Триггер `trg_session_complete_award_xp` удалён — он блокировал UPDATE сессий.

---

## 4. Схема базы данных

### 4.1. Критические замечания по полям

> **ВНИМАНИЕ!** Старая документация и скрипты содержат неправильные имена полей. Ниже — актуальные:

| Старое (НЕВЕРНО) | Актуальное (ВЕРНО) | Таблица |
|---|---|---|
| `is_public = 1` | `publication_status = 'public'` | `modx_test_tests` |
| `c.title` | `c.name` | `modx_test_categories` |
| `t.category_id` | `t.resource_id` | `modx_test_tests` |
| `modx_membergroup` | `modx_member_groups` | Группы пользователей |

> **`resource_id`** в `modx_test_tests` хранит **ID категории** из `modx_test_categories`, а НЕ ID ресурса MODX!

### 4.2. Основные таблицы

#### `modx_test_tests` — Тесты
| Поле | Тип | Описание |
|------|-----|----------|
| `id` | int(11) | PK |
| `resource_id` | int(11) | **ID категории** (не ресурса MODX!) |
| `title` | varchar(255) | Название |
| `description` | text | Описание |
| `mode` | enum('training','exam') | Режим |
| `time_limit` | int(11) | Минуты |
| `pass_score` | int(11) | Проходной балл (%) |
| `questions_per_session` | int(11) | Вопросов за сессию |
| `is_active` | tinyint(1) | Активен |
| `publication_status` | enum('draft','private','unlisted','public') | Статус публикации |
| `created_by` | int(11) | ID автора |
| `created_at` | datetime | Дата создания |

#### `modx_test_categories` — Категории
| Поле | Тип | Описание |
|------|-----|----------|
| `id` | int(11) | PK |
| `parent_id` | int(11) | Родитель (вложенность) |
| `name` | varchar(255) | **Название (не `title`!)** |
| `icon` | varchar(50) | Иконка |
| `sort_order` | int(11) | Сортировка |

#### `modx_test_questions` — Вопросы
| Поле | Тип | Описание |
|------|-----|----------|
| `id` | int(11) | PK |
| `test_id` | int(11) | FK → tests |
| `question_text` | text | Текст вопроса |
| `question_type` | enum('single','multiple','matching','ordering','fill_blank','essay') | Тип |
| `explanation` | text | Пояснение |
| `published` | tinyint(1) | Опубликован |

#### `modx_test_answers` — Ответы
| Поле | Тип | Описание |
|------|-----|----------|
| `id` | int(11) | PK |
| `question_id` | int(11) | FK → questions |
| `answer_text` | text | Текст |
| `is_correct` | tinyint(1) | Правильный |

#### `modx_test_sessions` — Сессии тестирования
| Поле | Тип | Описание |
|------|-----|----------|
| `id` | int(11) | PK |
| `user_id` | int(11) | FK → users |
| `test_id` | int(11) | FK → tests |
| `score` | decimal(5,2) | Балл (%) |
| `passed` | tinyint(1) | Пройден |
| `status` | enum('in_progress','completed','abandoned') | Статус |

#### `modx_test_user_answers` — Ответы пользователей
| Поле | Тип | Описание |
|------|-----|----------|
| `session_id` | int(11) | FK → sessions |
| `question_id` | int(11) | FK → questions |
| `answer_id` | int(11) | FK → answers |
| `is_correct` | tinyint(1) | Правильно |

#### Другие таблицы
- `modx_test_achievements` — Достижения
- `modx_test_level_config` — Уровни (10 уровней)
- `modx_test_permissions` — Права доступа (test_id + user_id/user_group_id)
- `modx_test_notifications` — Уведомления (8 шаблонов)
- `modx_test_certificates` — Сертификаты
- `modx_test_xp_history` — История XP
- `modx_test_category_experts` — Эксперты по категориям
- `modx_test_learning_paths` — Образовательные траектории
- `modx_test_learning_materials` — Учебные материалы
- `modx_test_favorites` — Избранное

### 4.3. Связи между таблицами

```
categories (id) ←── tests.resource_id
tests (id)      ←── questions.test_id
questions (id)  ←── answers.question_id
users (id)      ←── sessions.user_id
tests (id)      ←── sessions.test_id
sessions (id)   ←── user_answers.session_id
```

### 4.4. Типовые SQL-запросы

```sql
-- Публичные активные тесты
SELECT * FROM modx_test_tests
WHERE publication_status = 'public' AND is_active = 1;

-- Категории с количеством тестов
SELECT c.id, c.name, COUNT(t.id) as test_count
FROM modx_test_categories c
LEFT JOIN modx_test_tests t ON t.resource_id = c.id
  AND t.publication_status = 'public' AND t.is_active = 1
GROUP BY c.id, c.name
HAVING test_count > 0;

-- Результаты пользователя
SELECT s.score, s.passed, t.title, c.name as category
FROM modx_test_sessions s
JOIN modx_test_tests t ON t.id = s.test_id
LEFT JOIN modx_test_categories c ON c.id = t.resource_id
WHERE s.user_id = :userId AND s.status = 'completed'
ORDER BY s.end_time DESC;
```

---

## 5. API

### 5.1. Маршрутизация запросов

Все API-запросы идут через `assets/components/testsystem/ajax/testsystem.php` (3729 строк).

**Двухуровневый роутинг:**
```
Запрос → testsystem.php
  → ControllerFactory.canHandle(action)?
    → ДА: controller.handle(action, data)  // ~35 actions через контроллеры
    → НЕТ: switch(action) { case... }      // ~40 actions inline (техдолг)
```

`ControllerFactory` содержит маппинг action → Controller. Actions, которых нет в маппинге, обрабатываются inline в switch — это незавершённый рефакторинг (см. раздел 10 "Техдолг").

**Загрузка файлов** обрабатывается отдельно:
- `upload-image.php` — загрузка изображений к вопросам
- `upload-document.php` — загрузка документов

Формат запроса:
```json
{
  "action": "actionName",
  "data": { ... },
  "csrf_token": "..."
}
```

Формат ответа:
```json
{
  "success": true,
  "data": { ... },
  "message": "..."
}
```

### 5.2. Контроллеры и эндпоинты (120 всего)

| Контроллер | Примеры actions | Уровень доступа |
|---|---|---|
| **Session** | startSession, submitAnswer, finishTest | Auth |
| **Test** | getTests, getTestInfo, createTestWithPage | Auth/Admin |
| **Question** | getQuestions, addQuestion, deleteQuestion | Edit/Admin |
| **Admin** | getTestsAdmin, publishTest, deleteTest | Admin |
| **Material** | getMaterials, createMaterial | Auth/Admin |
| **Category** | getCategories, createCategory | Public/Admin |
| **LearningPath** | getPaths, createPath | Auth/Admin |
| **Gamification** | getProfile, getLeaderboard | Auth |
| **Notification** | getNotifications, markRead | Auth |
| **Analytics** | getDashboard, getTestAnalytics | Admin |
| **Certificate** | getCertificates, verifyCertificate | Auth/Public |
| **Favorite** | getFavorites, toggleFavorite | Auth |

### 5.3. Уровни доступа (RBAC)
1. **Public** — без авторизации
2. **Auth** — авторизованный пользователь
3. **View** — право просмотра
4. **Edit** — право редактирования
5. **Admin** — администратор
6. **CategoryAdmin** — эксперт по категории

### 5.4. CSRF-защита
Обязательна для всех POST/PUT/DELETE запросов. Токен генерируется через `CsrfProtection::getTokenMeta()` и передаётся в поле `csrf_token`.

---

## 6. Frontend-архитектура

### 6.1. Модули (8 модулей, спринты 9-17)
1. **Учебные материалы** — просмотр, создание, редактирование
2. **Права доступа** — управление permissions
3. **Образовательные траектории** — learning paths
4. **Специальные вопросы** — matching, ordering, fill_blank
5. **Геймификация** — XP, уровни, достижения, лидерборд
6. **Уведомления** — real-time уведомления
7. **Аналитика** — дашборды, статистика
8. **Сертификаты** — генерация, верификация

### 6.2. Зависимости
- Bootstrap 5 (CSS + JS)
- Bootstrap Icons
- Vanilla JavaScript (без jQuery)

### 6.3. Интеграция с MODX
JS и CSS подключаются через сниппеты:
```php
$modx->regClientCSS('/assets/components/testsystem/css/styles.css');
$modx->regClientScript('/assets/components/testsystem/js/module.js');
```

---

## 7. Ключевые страницы MODX

| ID | Alias | Сниппет | Назначение |
|----|-------|---------|------------|
| 35 | /testy | `[[!categoriesAndTests]]` | Список тестов |
| — | /test-run | `[[!testRunner]]` | Прохождение теста |
| — | /add-test | `[[!addTestForm]]` | Создание теста |
| — | /results | `[[!testResults]]` | Результаты |
| — | /history | `[[!testHistory]]` | История |
| — | /achievements | `[[!myAchievements]]` | Достижения |
| — | /certificates | `[[!myCertificates]]` | Сертификаты |
| — | /profile | `[[!userProfile]]` | Профиль |

---

## 8. Cron-задачи

```bash
# Очистка устаревших сессий (каждый час)
0 * * * * php /path/to/core/components/testsystem/cron/cleanup_sessions.php

# Обновление лидерборда (каждые 30 минут)
*/30 * * * * php /path/to/core/components/testsystem/cron/update_leaderboard.php

# Обработка уведомлений (каждые 5 минут)
*/5 * * * * php /path/to/core/components/testsystem/cron/process_notifications.php
```

---

## 9. Требования к серверу

- MODX Revolution 2.8.0+
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.3+
- Composer (для PhpSpreadsheet)
- SSL/HTTPS (обязательно для production)

---

## 10. Техдолг и план рефакторинга

### 10.1. testsystem.php — незавершённый рефакторинг (приоритет: высокий)

**Проблема:** Файл 3729 строк. ~35 actions делегируются в контроллеры через `ControllerFactory`, ~40 actions обрабатываются inline в switch с прямыми SQL-запросами и бизнес-логикой.

**Inline actions, требующие миграции в контроллеры:**
- `getKnowledgeAreas`, `createKnowledgeArea`, `updateKnowledgeArea`, `deleteKnowledgeArea` → KnowledgeAreaController (новый)
- `getMaterialsList`, `getMaterial`, `saveMaterial`, `deleteMaterial` → MaterialController (дополнить)
- `uploadImage`, `uploadDocument` → вынести в отдельные контроллеры или MaterialController
- `createTestWithPage`, `createTest`, `createTestPage` → TestController (дополнить)
- `getMyTests`, `getSharedWithMe`, `getPublicTests` → TestController (дополнить)
- `searchUsers`, `grantAccess`, `revokeAccess` → PermissionController (новый)
- `getNotifications`, `markNotificationRead`, `markAllNotificationsRead` → уже есть NotificationController
- Диагностические: `diagnoseMaterialsAuth`, `cleanupResourceFiles`, `checkSiteSettings` → AdminController

**Цель:** `testsystem.php` должен сократиться до ~200 строк (загрузка, роутинг через ControllerFactory, error handling).

### 10.2. LearningPathController (1617 строк) — разбить

Самый большой контроллер. Содержит логику CRUD траекторий, шагов, прогресса, рекомендаций. Разбить на:
- `LearningPathController` — CRUD траекторий
- `LearningPathStepController` — шаги и прогресс

### 10.3. Дублирование actions

В `testsystem.php` есть два `case 'getTestPermissions'` (строки ~1721 и ~2811) и два `case 'deleteTest'` (закомментированный + рабочий). Требуется чистка.

### 10.4. `display_errors` в production

`testsystem.php` строки 4-5 содержат `error_reporting(E_ALL)` и `display_errors = 1`. Необходимо убрать для production или сделать условно по окружению.
