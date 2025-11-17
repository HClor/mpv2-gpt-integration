# Test System API Endpoints

Полный список всех 120 API endpoints системы.

**Base URL:** `/assets/components/testsystem/ajax/testsystem.php`

**Формат запроса:**
```json
{
    "action": "actionName",
    "data": { /* параметры */ }
}
```

---

## SessionController (5 endpoints)

| Action | Описание | Права | Параметры |
|--------|----------|-------|-----------|
| `startSession` | Начать новую сессию тестирования | Auth | test_id, user_id |
| `getNextQuestion` | Получить следующий вопрос | Auth | session_id |
| `submitAnswer` | Отправить ответ на вопрос | Auth | session_id, question_id, answer |
| `finishTest` | Завершить тест | Auth | session_id |
| `cleanupOldSessions` | Очистить старые сессии | Admin | days |

---

## FavoriteController (3 endpoints)

| Action | Описание | Права | Параметры |
|--------|----------|-------|-----------|
| `toggleFavorite` | Добавить/удалить из избранного | Auth | question_id |
| `getFavoriteStatus` | Проверить статус избранного | Auth | question_id |
| `getFavoriteQuestions` | Получить список избранных | Auth | - |

---

## QuestionController (8 endpoints)

| Action | Описание | Права | Параметры |
|--------|----------|-------|-----------|
| `createQuestion` | Создать вопрос | Edit | test_id, question_text, ... |
| `getQuestion` | Получить вопрос | Auth | question_id |
| `updateQuestion` | Обновить вопрос | Edit | question_id, ... |
| `deleteQuestion` | Удалить вопрос | Edit | question_id |
| `getAllQuestions` | Список всех вопросов теста | Auth | test_id |
| `getQuestionAnswers` | Получить варианты ответов | Auth | question_id |
| `togglePublished` | Переключить публикацию | Edit | question_id |
| `toggleLearning` | Переключить режим обучения | Edit | question_id |

---

## TestController (5 endpoints)

| Action | Описание | Права | Параметры |
|--------|----------|-------|-----------|
| `getTestInfo` | Получить информацию о тесте | Auth | test_id |
| `getTestSettings` | Получить настройки теста | Edit | test_id |
| `updateTestSettings` | Обновить настройки | Edit | test_id, ... |
| `updateTest` | Обновить тест | Edit | test_id, ... |
| `deleteTest` | Удалить тест | Admin | test_id |

---

## AdminController (8 endpoints)

| Action | Описание | Права | Параметры |
|--------|----------|-------|-----------|
| `checkIntegrity` | Проверить целостность данных | Admin | - |
| `cleanOrphanedData` | Очистить orphaned данные | Admin | - |
| `cleanOrphanedTests` | Очистить orphaned тесты | Admin | - |
| `cleanOrphanedQuestions` | Очистить orphaned вопросы | Admin | - |
| `cleanOrphanedAnswers` | Очистить orphaned ответы | Admin | - |
| `cleanOrphanedSessions` | Очистить orphaned сессии | Admin | - |
| `cleanOldSessions` | Очистить старые сессии | Admin | days |
| `getSystemStats` | Системная статистика | Admin | - |

---

## MaterialController (15 endpoints)

| Action | Описание | Права | Параметры |
|--------|----------|-------|-----------|
| `createMaterial` | Создать материал | Edit | title, category_id, ... |
| `getMaterial` | Получить материал | Auth | material_id |
| `updateMaterial` | Обновить материал | Edit | material_id, ... |
| `deleteMaterial` | Удалить материал | Edit | material_id |
| `getMaterialsList` | Список материалов | Auth | category_id (opt) |
| `addContentBlock` | Добавить блок контента | Edit | material_id, type, content |
| `updateContentBlock` | Обновить блок | Edit | block_id, ... |
| `deleteContentBlock` | Удалить блок | Edit | block_id |
| `addAttachment` | Добавить вложение | Edit | material_id, ... |
| `deleteAttachment` | Удалить вложение | Edit | attachment_id |
| `updateProgress` | Обновить прогресс | Auth | material_id, progress |
| `getUserProgress` | Получить прогресс | Auth | material_id |
| `setTags` | Установить теги | Edit | material_id, tags |
| `linkTest` | Связать с тестом | Edit | material_id, test_id |
| `unlinkTest` | Отвязать тест | Edit | material_id, test_id |

---

## CategoryController (8 endpoints)

| Action | Описание | Права | Параметры |
|--------|----------|-------|-----------|
| `grantCategoryPermission` | Выдать права | Admin/CategoryAdmin | category_id, user_id, role |
| `revokeCategoryPermission` | Отозвать права | Admin/CategoryAdmin | category_id, user_id |
| `getCategoryUsers` | Пользователи категории | View | category_id |
| `getUserCategories` | Категории пользователя | View | user_id |
| `checkCategoryPermission` | Проверить права | Auth | category_id, role |
| `getPermissionHistory` | История прав | Admin | category_id (opt) |
| `bulkGrantPermissions` | Массовая выдача прав | Admin | category_id, user_ids, role |
| `bulkRevokePermissions` | Массовый отзыв | Admin | category_id, user_ids |

---

## LearningPathController (17 endpoints)

| Action | Описание | Права | Параметры |
|--------|----------|-------|-----------|
| `createPath` | Создать траекторию | Edit | title, description, ... |
| `getPath` | Получить траекторию | Auth | path_id |
| `updatePath` | Обновить траекторию | Edit | path_id, ... |
| `deletePath` | Удалить траекторию | Edit | path_id |
| `addStep` | Добавить шаг | Edit | path_id, title, ... |
| `updateStep` | Обновить шаг | Edit | step_id, ... |
| `deleteStep` | Удалить шаг | Edit | step_id |
| `reorderSteps` | Изменить порядок шагов | Edit | path_id, step_orders |
| `enrollOnPath` | Записаться на траекторию | Auth | path_id |
| `unenrollFromPath` | Отписаться от траектории | Auth | enrollment_id |
| `getMyPaths` | Мои траектории | Auth | - |
| `getPathProgress` | Прогресс по траектории | Auth | path_id |
| `completePathStep` | Завершить шаг | Auth | step_id, ... |
| `getNextPathStep` | Следующий шаг | Auth | path_id |
| `getPathsList` | Список траекторий | Auth | category_id (opt) |
| `bulkEnrollOnPath` | Массовая запись | Admin | path_id, user_ids |
| `getPathStatistics` | Статистика траектории | View | path_id |

---

## SpecialQuestionController (4 endpoints)

| Action | Описание | Права | Параметры |
|--------|----------|-------|-----------|
| `getQuestionTypeData` | Данные спец. типа вопроса | Auth | question_id |
| `reviewEssay` | Проверить эссе | Edit | review_id, score, comment |
| `getEssaysForReview` | Эссе для проверки | Edit | test_id (opt) |
| `getMyEssays` | Мои эссе | Auth | - |

---

## GamificationController (10 endpoints)

| Action | Описание | Права | Параметры |
|--------|----------|-------|-----------|
| `getMyProfile` | Мой профиль (XP, уровень) | Auth | - |
| `getMyAchievements` | Мои достижения | Auth | include_not_earned |
| `getLeaderboard` | Рейтинг | Public | period, category_id, limit |
| `getMyStreak` | Моя серия активности | Auth | - |
| `awardXP` | Начислить XP | Admin | user_id, xp_amount, reason |
| `checkAchievements` | Проверить достижения | Auth | activity_type, activity_data |
| `getXPHistory` | История XP | Auth | limit, offset |
| `getLevelStats` | Статистика по уровням | Public | - |
| `getRarestAchievements` | Редкие достижения | Public | limit |
| `updateLeaderboard` | Обновить рейтинг | Admin | period |

---

## NotificationController (12 endpoints)

| Action | Описание | Права | Параметры |
|--------|----------|-------|-----------|
| `getMyNotifications` | Мои уведомления | Auth | is_read, type, limit, offset |
| `getUnreadCount` | Кол-во непрочитанных | Auth | - |
| `markAsRead` | Пометить прочитанным | Auth | notification_id |
| `markAllAsRead` | Все прочитанными | Auth | - |
| `deleteNotification` | Удалить уведомление | Auth | notification_id |
| `createNotification` | Создать уведомление | Edit | user_id, type, title, message |
| `sendEmail` | Отправить email | Admin | user_id, template_key, placeholders |
| `getMyPreferences` | Настройки подписок | Auth | - |
| `updatePreference` | Обновить подписку | Auth | notification_type, channel, is_enabled |
| `processQueue` | Обработать очередь | Admin | batch_size |
| `cleanupOld` | Очистить старые | Admin | days_to_keep |
| `getStatistics` | Статистика уведомлений | Admin/Auth | - |

---

## AnalyticsController (16 endpoints)

| Action | Описание | Права | Параметры |
|--------|----------|-------|-----------|
| `getMyStatistics` | Моя статистика | Auth | period, use_cache |
| `getUserStatistics` | Статистика пользователя | View | user_id, period |
| `getTestStatistics` | Статистика теста | Auth | test_id, period |
| `getCategoryStatistics` | Статистика категории | Auth | category_id (opt) |
| `getQuestionStatistics` | Статистика вопроса | View | question_id |
| `getTopUsers` | Топ пользователей | Auth | limit, category_id |
| `getHardestQuestions` | Сложные вопросы | View | limit, test_id |
| `getScoreDistribution` | Распределение баллов | Auth | test_id |
| `getCohortAnalysis` | Когортный анализ | Admin | start_date, end_date |
| `getUserActivitySummary` | Сводка активности | View | start_date, end_date |
| `getAdminDashboard` | Дашборд админа | View | - |
| `getMyDashboard` | Мой дашборд | Auth | - |
| `getUserComparison` | Сравнение с другими | Auth | category_id (opt) |
| `generateReport` | Сгенерировать отчет | View | report_type, format, filters |
| `getReportHistory` | История отчетов | Auth | limit |
| `cleanupCache` | Очистить кеш аналитики | Admin | - |

---

## CertificateController (9 endpoints)

| Action | Описание | Права | Параметры |
|--------|----------|-------|-----------|
| `getMyCertificates` | Мои сертификаты | Auth | entity_type, is_revoked |
| `getCertificate` | Получить сертификат | Auth/Public | certificate_id |
| `verifyCertificate` | Верифицировать | Public | verification_code |
| `issueCertificate` | Выдать сертификат | Edit | template_id, user_id, ... |
| `revokeCertificate` | Отозвать сертификат | Admin | certificate_id, reason |
| `checkEligibility` | Проверить возможность | Auth | template_id, entity_type, entity_id |
| `downloadCertificate` | Скачать сертификат | Auth | certificate_id |
| `getCertificateStatistics` | Статистика сертификатов | Admin | - |
| `cleanupExpired` | Очистить истекшие | Admin | - |

---

## Легенда прав доступа

- **Public** - Доступен без авторизации
- **Auth** - Требуется авторизация
- **View** - Требуется роль admin или expert
- **Edit** - Требуется роль admin или expert с правами на редактирование
- **Admin** - Только администраторы
- **CategoryAdmin** - Админ категории или глобальный админ

---

## Общий формат ответа

### Успешный ответ:
```json
{
    "success": true,
    "message": "Operation completed successfully",
    "data": { /* результат */ }
}
```

### Ошибка:
```json
{
    "success": false,
    "message": "Error description",
    "code": 400
}
```

---

## HTTP коды ответов

| Код | Значение |
|-----|----------|
| 200 | OK - Успешно |
| 400 | Bad Request - Ошибка валидации |
| 401 | Unauthorized - Требуется авторизация |
| 403 | Forbidden - Нет прав доступа |
| 404 | Not Found - Ресурс не найден |
| 500 | Internal Server Error - Ошибка сервера |

---

## CSRF Protection

Все действия, изменяющие данные, требуют CSRF токен в заголовке:

```javascript
headers: {
    'X-CSRF-Token': getCsrfToken()
}
```

---

## Rate Limiting

Рекомендуется настроить rate limiting на уровне веб-сервера:
- Не более 100 запросов в минуту для авторизованных пользователей
- Не более 20 запросов в минуту для неавторизованных

---

## Версионирование API

Текущая версия: **v2.0**

В будущих версиях планируется добавить версионирование через URL:
`/assets/components/testsystem/ajax/v2/testsystem.php`

---

**Последнее обновление:** 2025-11-15
**Всего endpoints:** 120
