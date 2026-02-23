# Аудит компонента TestSystem — 2026-02-22

> **Обновлено:** 2026-02-23 — зафиксированы результаты выполнения Фаз 1-6.

## Общее описание

Компонент **TestSystem** — система тестирования на базе MODX Revolution. Состоит из двух частей:
- `assets/components/testsystem/` — публичный слой (AJAX-обработчик, контроллеры, JS, CSS)
- `core/components/testsystem/` — приватный слой (сервисы, хелперы, SQL, безопасность)

**Итого файлов:** ~149 (92 в assets, 57 в core)
**Главный AJAX-обработчик:** `assets/components/testsystem/ajax/testsystem.php` — **540 строк**, **5 inline case-операторов** (было 3 734 строк, 69 case-ов)

---

## 1. КРИТИЧНЫЕ ПРОБЛЕМЫ — ВЫПОЛНЕНО

### 1.1 ~~Мёртвый код в testsystem.php (24 inline-case дублируют ControllerFactory)~~ ИСПРАВЛЕНО

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

### 1.2 ~~BUG: Дубликат case 'getTestPermissions'~~ ИСПРАВЛЕНО

В switch два case с одинаковым именем `getTestPermissions`:
- **Строка 1726** — работает (PHP матчит первый)
- **Строка 2816** — мёртвый код, никогда не выполнится

Реализации **различаются** — вторая использует `getUserRights()`, первая нет. Нужно проверить, какая из них корректная, объединить логику и удалить дубликат.

### 1.3 ~~Отсутствует .htaccess в директории ajax/~~ ИСПРАВЛЕНО

`/assets/components/testsystem/ajax/` не имеет .htaccess. Хотя прямой доступ к PHP-файлам здесь — штатная работа (это AJAX endpoint), стоит добавить защиту от листинга директории и запрет доступа к .log файлам (если они случайно создадутся).

**Действие:** Создать `.htaccess`:
```apache
Options -Indexes
<FilesMatch "\.(log|bak|sql|md)$">
    Require all denied
</FilesMatch>
```

---

## 2. АКТИВНЫЕ INLINE-CASE-Ы — ВЫПОЛНЕНО (мигрированы в контроллеры)

### 2.1 ~~Knowledge Areas (7 case-ов) → KnowledgeAreaController~~ ВЫПОЛНЕНО

| Case | Строка | Описание |
|------|--------|----------|
| `getKnowledgeAreas` | 1130 | Список областей знаний |
| `createKnowledgeArea` | 1171 | Создание области |
| `updateKnowledgeArea` | 1241 | Обновление области |
| `deleteKnowledgeArea` | 1310 | Удаление области |
| `getKnowledgeAreaDetails` | 1928 | Детали области |
| `getAvailableTestsTree` | 1977 | Дерево доступных тестов |
| `startKnowledgeAreaSession` | 2086 | Запуск сессии по области |

### 2.2 ~~Category Experts (5 case-ов) → CategoryController~~ ВЫПОЛНЕНО

| Case | Строка | Описание |
|------|--------|----------|
| `assignCategoryExpert` | 1347 | Назначить эксперта |
| `removeCategoryExpert` | 1412 | Убрать эксперта |
| `createCategory` | 1432 | Создать категорию |
| `getCategoryExperts` | 1463 | Список экспертов |
| `getAvailableExperts` | 1493 | Доступные эксперты |

### 2.3 ~~Test Access / Permissions (7 case-ов) → TestController~~ ВЫПОЛНЕНО

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

### 2.4 ~~Test CRUD & Discovery (9 case-ов) → TestController~~ ВЫПОЛНЕНО

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

### 2.5 ~~Notifications (3 case-а) → NotificationController~~ ВЫПОЛНЕНО

| Case | Строка | Описание |
|------|--------|----------|
| `getNotifications` | 2857 | Список уведомлений |
| `markNotificationRead` | 2896 | Пометить прочитанным |
| `markAllNotificationsRead` | 2911 | Пометить все прочитанными |

**Примечание:** В NotificationController уже есть `getMyNotifications`, `markAsRead`, `markAllAsRead`. Эти inline case-ы используют **другие имена действий** — нужно либо добавить алиасы, либо обновить вызовы в JS.

### 2.6 Materials (MODX Resources) (3 case-а) — ОСТАВЛЕНЫ INLINE (архитектурное решение 8.2)

| Case | Строка | Описание |
|------|--------|----------|
| `getMaterialsList` | 3047 | Список материалов (через site_content) |
| `getMaterial` | 3091 | Получить материал (через site_content) |
| `saveMaterial` | 3135 | Сохранить материал (184 строки!) |

**Контекст:** В MaterialController эти действия **отключены** комментарием, т.к. материалы мигрировали на MODX-ресурсы (site_content). Inline-код работает с site_content напрямую.

### 2.7 ~~Uploads (2 case-а) → UploadController~~ ВЫПОЛНЕНО

| Case | Строка | Описание |
|------|--------|----------|
| `uploadImage` | 3385 | Загрузка изображения (70 строк) |
| `uploadDocument` | 3455 | Загрузка документа (154 строки) |

### 2.8 ~~Utility / Misc (9 case-ов)~~ ВЫПОЛНЕНО

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
| .htaccess ajax/ | OK | Создан (защита от листинга и доступа к .log) |
| .gitignore uploads | OK | Паттерн `q_*` для user-uploaded images |

---

## 7. ПЛАН ИСПРАВЛЕНИЙ

### Фаза 1 — Очистка мёртвого кода — ВЫПОЛНЕНО (2026-02-22)

1. ~~Удалить 24 мёртвых case-блока~~ — удалено, testsystem.php: 3734 → 924 строки
2. ~~Удалить дубликат `case 'getTestPermissions'`~~ — удалён
3. ~~Проверить `grantAccess` vs `grantTestAccess`~~ — проверено, оба нужны (разные API)
4. ~~Создать .htaccess в ajax/~~ — создан

### Фаза 2 — Миграция Notifications — ВЫПОЛНЕНО (2026-02-22)

1. ~~Проверить JS action names~~ — JS использует `getUnreadNotificationsCount`, `getRecentNotifications`, `markNotificationAsRead`, `getAllNotifications`, `getNotificationSettings`, `saveNotificationSettings`
2. ~~Добавить маппинг в ControllerFactory~~ — все алиасы зарегистрированы
3. ~~Удалить inline case-ы~~ — удалены

### Фаза 3 — Knowledge Areas — ВЫПОЛНЕНО (2026-02-22)

1. ~~Создать KnowledgeAreaController.php~~ — создан (7 actions)
2. ~~Добавить маппинг в ControllerFactory~~ — добавлено
3. ~~Удалить inline case-ы~~ — удалены

### Фаза 4 — Test CRUD & Permissions — ВЫПОЛНЕНО (2026-02-22)

1. ~~Расширить TestController~~ — добавлены publishTest, getPublicTestBySlug, checkResourcePermissions, createTestWithPage, createTest, createTestPage, getMyTests, getSharedWithMe, getPublicTests, grantTestAccess, revokeTestAccess, getTestPermissions, getAvailableUsersForTest, checkTestAccess, searchUsers, grantAccess, revokeAccess
2. ~~Обновить маппинг в ControllerFactory~~ — добавлено
3. ~~Удалить inline case-ы~~ — удалены

### Фаза 5 — Category Experts + Остальное — ВЫПОЛНЕНО (2026-02-22 / 2026-02-23)

1. ~~CategoryController: assignCategoryExpert, removeCategoryExpert, createCategory, getCategoryExperts, getAvailableExperts~~ — выполнено (2026-02-22)
2. ~~SessionController: getSessionInfo~~ — зарегистрирован в ControllerFactory (2026-02-23)
3. ~~TestController: checkEditRights~~ — добавлен (2026-02-23)
4. ~~AdminController: checkSiteSettings, cleanupResourceFiles, diagnoseMaterialsAuth~~ — добавлены (2026-02-23)
5. Materials (site_content): оставлены inline — архитектурное решение 8.2

### Фаза 6 — Uploads — ВЫПОЛНЕНО (2026-02-23)

1. ~~Создать UploadController.php~~ — создан (uploadImage, uploadDocument)
2. ~~Зарегистрировать в ControllerFactory~~ — добавлено
3. ~~Удалить inline case-ы из testsystem.php~~ — удалены
4. ~~Удалить debug error_log() из uploadDocument~~ — очищено
5. `upload-image.php` и `upload-document.php` — остаются как альтернативные точки входа (для FormData uploads)

### Фаза 7 — CSS (приоритет: НИЗКИЙ) — НЕ ВЫПОЛНЕНО

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

## 9. МЕТРИКИ ДО/ПОСЛЕ (фактические)

| Метрика | До аудита | После Фазы 1 | Факт (2026-02-23) |
|---------|-----------|--------------|-------------------|
| testsystem.php строк | 3 734 | 924 | **540** |
| Inline case-ов | 69 | 12 | **5** (getApiVersion, getParentUri, getMaterialsList, getMaterial, saveMaterial) |
| Мёртвый код (case-ов) | 24 | 0 | **0** |
| Контроллеров (файлов) | 15 | 15 | **17** (+KnowledgeAreaController, +UploadController) |
| Действий в ControllerFactory | 113 | 144 | **~151** |

---

## 10. ФАЙЛОВАЯ СТРУКТУРА (справка, обновлено 2026-02-23)

```
assets/components/testsystem/
├── ajax/
│   ├── testsystem.php          ← Главный AJAX-обработчик (540 строк, 5 inline case-ов)
│   ├── .htaccess               ← Защита от листинга и доступа к .log файлам
│   ├── upload-image.php        ← Загрузка изображений (180 строк, альтернативная точка входа)
│   └── upload-document.php     ← Загрузка документов (164 строки, альтернативная точка входа)
├── controllers/                ← 17 файлов, ~151 действий через ControllerFactory
│   ├── BaseController.php      ← Базовый класс
│   ├── ControllerFactory.php   ← Фабрика маршрутизации (~151 действий)
│   ├── AdminController.php     ← Администрирование (11 actions)
│   ├── AnalyticsController.php ← Аналитика (16 actions)
│   ├── CategoryController.php  ← Категории (13 actions)
│   ├── CertificateController.php ← Сертификаты (9 actions)
│   ├── FavoriteController.php  ← Избранное (3 actions)
│   ├── GamificationController.php ← Геймификация (10 actions)
│   ├── KnowledgeAreaController.php ← Области знаний (7 actions) [NEW]
│   ├── LearningPathController.php ← Траектории (25 actions)
│   ├── MaterialController.php  ← Материалы (14 actions)
│   ├── NotificationController.php ← Уведомления (18 actions)
│   ├── QuestionController.php  ← Вопросы (8 actions)
│   ├── SessionController.php   ← Сессии тестов (6 actions)
│   ├── SpecialQuestionController.php ← Спец. вопросы (4 actions)
│   ├── TestController.php      ← Тесты (23 actions)
│   └── UploadController.php    ← Загрузка файлов (2 actions) [NEW]
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
