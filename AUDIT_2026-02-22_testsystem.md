# Аудит компонента TestSystem — 2026-02-22

## Общее описание

Компонент **TestSystem** — система тестирования на базе MODX Revolution. Состоит из двух частей:
- `assets/components/testsystem/` — публичный слой (AJAX-обработчик, контроллеры, JS, CSS)
- `core/components/testsystem/` — приватный слой (сервисы, хелперы, SQL, безопасность)

**Итого файлов:** ~148 (91 в assets, 57 в core)
**Главный AJAX-обработчик:** `assets/components/testsystem/ajax/testsystem.php` — **3 734 строки**, **69 case-операторов**

---

## 1. КРИТИЧНЫЕ ПРОБЛЕМЫ

### 1.1 Мёртвый код в testsystem.php (24 inline-case дублируют ControllerFactory)

`ControllerFactory` перехватывает действие **до** switch-блока (строки 196-197). Все case-ы, дублирующие маппинг ControllerFactory, являются **мёртвым кодом** — они никогда не выполняются.

**Мёртвые case-ы (24 шт.):**

| Case | Строка | Контроллер |
|------|--------|-----------|
| `getTestInfo` | 214 | TestController |
| `createQuestion` | 252 | QuestionController |
| `startSession` | 372 | SessionController |
| `cleanupOldSessions` | 389 | SessionController |
| `getNextQuestion` | 444 | SessionController |
| `togglePublished` | 535 | QuestionController |
| `toggleLearning` | 555 | QuestionController |
| `submitAnswer` | 577 | SessionController |
| `finishTest` | 589 | SessionController |
| `getAllQuestions` | 709 | QuestionController |
| `getQuestion` | 736 | QuestionController |
| `updateQuestion` | 782 | QuestionController |
| `deleteQuestion` | 837 | QuestionController |
| `getTestSettings` | 889 | TestController |
| `getQuestionAnswers` | 915 | QuestionController |
| `updateTestSettings` | 999 | TestController |
| `toggleFavorite` | 1039 | FavoriteController |
| `getFavoriteStatus` | 1076 | FavoriteController |
| `getFavoriteQuestions` | 1096 | FavoriteController |
| `getUserCategories` | 1522 | CategoryController |
| `checkCategoryPermission` | 1564 | CategoryController |
| `deleteTest` | 2963 | TestController |
| `updateTest` | 3001 | TestController |
| `deleteMaterial` | 3319 | MaterialController |

**Действие:** Удалить все 24 мёртвых case-блока из switch. Это сразу уберёт ~1500 строк.

### 1.2 BUG: Дубликат case 'getTestPermissions'

В switch два case с одинаковым именем `getTestPermissions`:
- **Строка 1726** — работает (PHP матчит первый)
- **Строка 2816** — мёртвый код, никогда не выполнится

Реализации **различаются** — вторая использует `getUserRights()`, первая нет. Нужно проверить, какая из них корректная, объединить логику и удалить дубликат.

### 1.3 Отсутствует .htaccess в директории ajax/

`/assets/components/testsystem/ajax/` не имеет .htaccess. Хотя прямой доступ к PHP-файлам здесь — штатная работа (это AJAX endpoint), стоит добавить защиту от листинга директории и запрет доступа к .log файлам (если они случайно создадутся).

**Действие:** Создать `.htaccess`:
```apache
Options -Indexes
<FilesMatch "\.(log|bak|sql|md)$">
    Require all denied
</FilesMatch>
```

---

## 2. АКТИВНЫЕ INLINE-CASE-Ы (45 шт. — требуют миграции в контроллеры)

### 2.1 Группа: Knowledge Areas (7 case-ов) → НОВЫЙ KnowledgeAreaController

| Case | Строка | Описание |
|------|--------|----------|
| `getKnowledgeAreas` | 1130 | Список областей знаний |
| `createKnowledgeArea` | 1171 | Создание области |
| `updateKnowledgeArea` | 1241 | Обновление области |
| `deleteKnowledgeArea` | 1310 | Удаление области |
| `getKnowledgeAreaDetails` | 1928 | Детали области |
| `getAvailableTestsTree` | 1977 | Дерево доступных тестов |
| `startKnowledgeAreaSession` | 2086 | Запуск сессии по области |

### 2.2 Группа: Category Experts (5 case-ов) → расширить CategoryController

| Case | Строка | Описание |
|------|--------|----------|
| `assignCategoryExpert` | 1347 | Назначить эксперта |
| `removeCategoryExpert` | 1412 | Убрать эксперта |
| `createCategory` | 1432 | Создать категорию |
| `getCategoryExperts` | 1463 | Список экспертов |
| `getAvailableExperts` | 1493 | Доступные эксперты |

### 2.3 Группа: Test Access / Permissions (7 case-ов) → расширить TestController или НОВЫЙ TestPermissionController

| Case | Строка | Описание |
|------|--------|----------|
| `grantTestAccess` | 1618 | Выдать доступ к тесту |
| `revokeTestAccess` | 1689 | Отозвать доступ |
| `getTestPermissions` | 1726 | Список разрешений |
| `getAvailableUsersForTest` | 1781 | Доступные пользователи |
| `checkTestAccess` | 1830 | Проверка доступа |
| `grantAccess` | 2737 | Выдать доступ (дубль?) |
| `revokeAccess` | 2787 | Отозвать доступ (дубль?) |

**Внимание:** `grantAccess`/`revokeAccess` (строки 2737/2787) могут дублировать `grantTestAccess`/`revokeTestAccess` (строки 1618/1689). Требуется анализ — возможно, часть из них мёртвый код.

### 2.4 Группа: Test CRUD & Discovery (9 case-ов) → расширить TestController

| Case | Строка | Описание |
|------|--------|----------|
| `publishTest` | 2243 | Публикация теста |
| `getPublicTestBySlug` | 2260 | Получить тест по slug |
| `checkResourcePermissions` | 2305 | Проверка прав ресурса |
| `createTestWithPage` | 2328 | Создать тест + страницу |
| `createTest` | 2345 | Создать тест |
| `createTestPage` | 2400 | Создать страницу теста |
| `getMyTests` | 2585 | Мои тесты |
| `getSharedWithMe` | 2611 | Тесты, которыми поделились |
| `getPublicTests` | 2642 | Публичные тесты |

### 2.5 Группа: Notifications (3 case-а) → привязать к NotificationController

| Case | Строка | Описание |
|------|--------|----------|
| `getNotifications` | 2857 | Список уведомлений |
| `markNotificationRead` | 2896 | Пометить прочитанным |
| `markAllNotificationsRead` | 2911 | Пометить все прочитанными |

**Примечание:** В NotificationController уже есть `getMyNotifications`, `markAsRead`, `markAllAsRead`. Эти inline case-ы используют **другие имена действий** — нужно либо добавить алиасы, либо обновить вызовы в JS.

### 2.6 Группа: Materials (MODX Resources) (3 case-а) → расширить MaterialController

| Case | Строка | Описание |
|------|--------|----------|
| `getMaterialsList` | 3047 | Список материалов (через site_content) |
| `getMaterial` | 3091 | Получить материал (через site_content) |
| `saveMaterial` | 3135 | Сохранить материал (184 строки!) |

**Контекст:** В MaterialController эти действия **отключены** комментарием, т.к. материалы мигрировали на MODX-ресурсы (site_content). Inline-код работает с site_content напрямую.

### 2.7 Группа: Uploads (2 case-а) → НОВЫЙ UploadController или расширить MaterialController

| Case | Строка | Описание |
|------|--------|----------|
| `uploadImage` | 3385 | Загрузка изображения (70 строк) |
| `uploadDocument` | 3455 | Загрузка документа (154 строки) |

### 2.8 Группа: Utility / Misc (9 case-ов)

| Case | Строка | Описание | Рекомендуемый контроллер |
|------|--------|----------|-------------------------|
| `getApiVersion` | 203 | Версия API | Оставить inline (утилита) |
| `getSessionInfo` | 401 | Информация о сессии | SessionController |
| `checkEditRights` | 730 | Проверка прав редактирования | TestController |
| `searchUsers` | 2665 | Поиск пользователей (72 строки) | AdminController |
| `checkSiteSettings` | 2923 | Настройки сайта | AdminController |
| `getParentUri` | 2933 | URI родителя | Оставить inline (утилита) |
| `cleanupResourceFiles` | 3609 | Очистка файлов ресурсов | AdminController |
| `diagnoseMaterialsAuth` | 3650 | Диагностика авторизации | AdminController |
| `getTestPermissions` (дубль) | 2816 | Дубликат строки 1726 | УДАЛИТЬ |

---

## 3. ДУБЛИРОВАНИЕ КОДА В UPLOAD-ФАЙЛАХ

**Файлы:**
- `assets/components/testsystem/ajax/upload-image.php` (180 строк)
- `assets/components/testsystem/ajax/upload-document.php` (164 строки)

**Дублированная логика:**

| Функциональность | Строки в каждом файле | Действие |
|------------------|-----------------------|----------|
| Загрузка config и bootstrap | ~15 строк | Вынести в shared include |
| CSRF-валидация | ~5 строк | Вынести в shared include |
| Проверка авторизации | ~10 строк | Вынести в shared include |
| Создание директории | ~5 строк | Вынести в shared include |
| JSON-ответ и error handling | ~10 строк | Вынести в shared include |

**Рекомендация:** Создать `core/components/testsystem/services/UploadService.php` с общей логикой валидации и безопасности.

---

## 4. CSS — ДУБЛИРОВАНИЕ И ОБЪЁМ

| Файл | Строк | Назначение |
|------|-------|------------|
| `categories-and-tests.css` | 338 | Карточки тестов, фильтры, редактор вопросов |
| `testsystem-extended.css` | 450 | Утилиты, анимации, бэджи, прогресс-бары |
| `tsrunner.css` | 2 607 | Интерфейс запуска тестов |
| **Итого** | **3 395** | |

**Дублирование:**
- `.btn` стили: `testsystem-extended.css` и `tsrunner.css`
- Card hover/shadow: все три файла
- Анимации (fadeIn): `testsystem-extended.css` и `tsrunner.css`

**Рекомендация:** Вынести общие стили (кнопки, карточки, анимации) в CSS custom properties. Не является приоритетом.

---

## 5. JAVASCRIPT — СОСТОЯНИЕ

| Файл | Размер | Назначение |
|------|--------|------------|
| `tsrunner.js` | 190K | Запуск тестов |
| `learning-paths.js` | 75K | Траектории обучения |
| `test-cards.js` | 68K | Карточки тестов |
| `mytests.js` | 37K | Мои тесты |
| `knowledge-areas.js` | 32K | Области знаний |
| `gamification.js` | 28K | Геймификация |
| `learning-materials.js` | 28K | Учебные материалы |
| `category-permissions.js` | 26K | Права на категории |
| `notifications.js` | 19K | Уведомления |
| `special-question-types.js` | 18K | Спец. типы вопросов |
| `category-experts.js` | 12K | Эксперты категорий |
| `test-permissions.js` | 12K | Права на тесты |
| `certificates.js` | 8.4K | Сертификаты |
| `analytics.js` | 7.0K | Аналитика |
| `tests-search.js` | 1.1K | Поиск тестов |

**Оценка:** JS-файлы хорошо организованы — один файл на фичу. Дублирования нет. Можно улучшить: создать общий модуль для API-вызовов и CSRF-токенов. Не является приоритетом.

---

## 6. БЕЗОПАСНОСТЬ — ТЕКУЩЕЕ СОСТОЯНИЕ

| Аспект | Статус | Комментарий |
|--------|--------|-------------|
| Логи ошибок | OK | Пишутся в `core/cache/logs/` (вне webroot) |
| `display_errors` | OK | Отключён (`0`) |
| CSRF-защита | OK | Реализована через `CsrfProtection.php` |
| Загрузка изображений | OK | MIME-валидация, GD-ресайз, .htaccess |
| Загрузка документов | OK | MIME-валидация, сканирование PHP-кода |
| .htaccess images/ | OK | Запрещает исполнение PHP |
| .htaccess ajax/ | НЕТ | Отсутствует (см. п.1.3) |
| .gitignore uploads | OK | Паттерн `q_*` для user-uploaded images |

---

## 7. ПЛАН ИСПРАВЛЕНИЙ

### Фаза 1 — Очистка мёртвого кода (приоритет: ВЫСОКИЙ)

**Цель:** Убрать ~1 500 строк мёртвого кода, устранить баг с дубликатом case.

1. **Удалить 24 мёртвых case-блока** из switch в testsystem.php (все, которые дублируют ControllerFactory)
2. **Удалить дубликат** `case 'getTestPermissions'` на строке 2816 (после сверки логики с первым на строке 1726)
3. **Проверить** `grantAccess` vs `grantTestAccess` и `revokeAccess` vs `revokeTestAccess` — возможно, ещё дубликаты
4. **Создать .htaccess** в `assets/components/testsystem/ajax/`

**Ожидаемый результат:** testsystem.php уменьшится с 3 734 до ~2 200 строк.

### Фаза 2 — Миграция Notifications (приоритет: ВЫСОКИЙ)

**Цель:** Устранить расхождение имён между inline case-ами и NotificationController.

1. **Проверить**, какие имена действий использует JS (`getNotifications` vs `getMyNotifications`, `markNotificationRead` vs `markAsRead`)
2. **Добавить алиасы** в ControllerFactory или обновить JS
3. **Удалить** 3 inline case-а после миграции

### Фаза 3 — Knowledge Areas (приоритет: СРЕДНИЙ)

**Цель:** Вынести 7 case-ов в новый KnowledgeAreaController.

1. Создать `assets/components/testsystem/controllers/KnowledgeAreaController.php`
2. Перенести логику из 7 inline case-ов
3. Добавить маппинг в ControllerFactory
4. Удалить inline case-ы

### Фаза 4 — Test CRUD & Permissions (приоритет: СРЕДНИЙ)

**Цель:** Расширить TestController оставшимися 16 case-ами.

1. Добавить в TestController: `publishTest`, `getPublicTestBySlug`, `checkResourcePermissions`, `createTestWithPage`, `createTest`, `createTestPage`, `getMyTests`, `getSharedWithMe`, `getPublicTests`
2. Добавить в TestController или создать TestPermissionController: `grantTestAccess`, `revokeTestAccess`, `getTestPermissions`, `getAvailableUsersForTest`, `checkTestAccess`, `grantAccess`, `revokeAccess`
3. Обновить маппинг в ControllerFactory
4. Удалить inline case-ы

### Фаза 5 — Category Experts + Остальное (приоритет: СРЕДНИЙ)

1. Добавить в CategoryController: `assignCategoryExpert`, `removeCategoryExpert`, `createCategory`, `getCategoryExperts`, `getAvailableExperts`
2. Добавить в AdminController или SessionController: `getSessionInfo`, `checkEditRights`, `searchUsers`, `checkSiteSettings`, `cleanupResourceFiles`, `diagnoseMaterialsAuth`
3. Materials (site_content): решить — обновить MaterialController для работы с MODX-ресурсами или оставить inline
4. Uploads: вынести `uploadImage`/`uploadDocument` case-ы в контроллер

### Фаза 6 — Upload Service (приоритет: НИЗКИЙ)

1. Создать `core/components/testsystem/services/UploadService.php`
2. Рефакторить `upload-image.php` и `upload-document.php` для использования общего сервиса

### Фаза 7 — CSS (приоритет: НИЗКИЙ)

1. Вынести общие стили в CSS custom properties
2. Консолидировать дублирующиеся стили кнопок и карточек

---

## 8. АРХИТЕКТУРНЫЕ РЕШЕНИЯ (зафиксированные)

### 8.1 Контроллеры остаются в assets/

Контроллеры расположены в `assets/components/testsystem/controllers/`. Это **осознанное решение**: контроллеры — тонкие HTTP-обёртки, загружаемые из `testsystem.php` (который сам находится в `assets/ajax/`). Бизнес-логика — в сервисах (`core/services/`). Перенос контроллеров в `core/` потребовал бы перестройки механизма загрузки без реальной пользы.

### 8.2 getMaterial / getMaterialsList работают через site_content

Материалы мигрировали с таблицы `test_learning_materials` на MODX-ресурсы (`site_content`). В MaterialController эти действия отключены. Inline-реализация работает напрямую с `site_content` — это корректно и на данный момент остаётся inline.

### 8.3 База данных: без триггеров

Все триггеры БД удалены, логика перенесена в PHP (сервисы). Триггер `trg_session_complete_award_xp` заменён вызовом в SessionController. Не создавать новые триггеры.

---

## 9. МЕТРИКИ ДО/ПОСЛЕ (ожидаемые)

| Метрика | До аудита | После Фазы 1 | После всех фаз |
|---------|-----------|--------------|----------------|
| testsystem.php строк | 3 734 | ~2 200 | ~200 |
| Inline case-ов | 69 | ~45 | 2-3 (утилиты) |
| Мёртвый код (case-ов) | 24 | 0 | 0 |
| Контроллеров | 15 | 15 | 17 |
| Действий в ControllerFactory | 113 | 113 | ~155 |

---

## 10. ФАЙЛОВАЯ СТРУКТУРА (справка)

```
assets/components/testsystem/
├── ajax/
│   ├── testsystem.php          ← Главный AJAX-обработчик (3 734 строки)
│   ├── upload-image.php        ← Загрузка изображений (180 строк)
│   └── upload-document.php     ← Загрузка документов (164 строки)
├── controllers/
│   ├── BaseController.php      ← Базовый класс (2.4K)
│   ├── ControllerFactory.php   ← Фабрика маршрутизации (11K, 113 действий)
│   ├── AdminController.php     ← Администрирование (10K)
│   ├── AnalyticsController.php ← Аналитика (15K)
│   ├── CategoryController.php  ← Категории (16K)
│   ├── CertificateController.php ← Сертификаты (13K)
│   ├── FavoriteController.php  ← Избранное (4.8K)
│   ├── GamificationController.php ← Геймификация (12K)
│   ├── LearningPathController.php ← Траектории (63K!)
│   ├── MaterialController.php  ← Материалы (17K)
│   ├── NotificationController.php ← Уведомления (14K)
│   ├── QuestionController.php  ← Вопросы (19K)
│   ├── SessionController.php   ← Сессии тестов (20K)
│   ├── SpecialQuestionController.php ← Спец. вопросы (7.2K)
│   └── TestController.php      ← Тесты (8.9K)
├── css/                        ← 3 файла, 3 395 строк
├── js/                         ← 15 файлов
├── images/                     ← User-uploaded, защищён .htaccess
│   └── .htaccess
└── templates/                  ← НЕ СУЩЕСТВУЕТ (удалена ранее)

core/components/testsystem/
├── bootstrap.php               ← Инициализация компонента
├── config/site.config.php      ← Конфигурация
├── cron/integrity-check.php    ← Крон-задача целостности
├── exceptions/                 ← 5 классов исключений
├── helpers/                    ← 6 хелперов
├── repositories/               ← BaseRepository, TestRepository
├── security/CsrfProtection.php ← CSRF-защита
├── services/                   ← 14 бизнес-сервисов
├── sql/                        ← 20 SQL-файлов
└── migrations/                 ← Миграции сниппетов
```
