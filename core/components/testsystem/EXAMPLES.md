# Test System API - Examples

Практические примеры использования API для различных сценариев.

**Версия:** 2.0
**Последнее обновление:** 2025-11-15

---

## Table of Contents

1. [Базовая настройка](#базовая-настройка)
2. [Аутентификация](#аутентификация)
3. [Работа с тестами](#работа-с-тестами)
4. [Учебные материалы](#учебные-материалы)
5. [Траектории обучения](#траектории-обучения)
6. [Геймификация](#геймификация)
7. [Уведомления](#уведомления)
8. [Аналитика и отчеты](#аналитика-и-отчеты)
9. [Сертификаты](#сертификаты)
10. [Обработка ошибок](#обработка-ошибок)
11. [Интеграция с MODX](#интеграция-с-modx)

---

## Базовая настройка

### Конфигурация API клиента

```javascript
// Базовый класс для работы с API
class TestSystemAPI {
    constructor(baseUrl = '/assets/components/testsystem/ajax/testsystem.php') {
        this.baseUrl = baseUrl;
        this.csrfToken = this.getCsrfToken();
    }

    // Получить CSRF токен из meta тега или cookie
    getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.content;

        // Альтернативно из cookie
        const cookies = document.cookie.split(';');
        for (let cookie of cookies) {
            const [name, value] = cookie.trim().split('=');
            if (name === 'csrf_token') return value;
        }
        return '';
    }

    // Универсальный метод для API запросов
    async request(action, data = {}) {
        try {
            const response = await fetch(this.baseUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                },
                body: JSON.stringify({
                    action: action,
                    data: data
                })
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Unknown error');
            }

            return result.data;
        } catch (error) {
            console.error(`API Error [${action}]:`, error);
            throw error;
        }
    }
}

// Создать глобальный экземпляр
const api = new TestSystemAPI();
```

---

## Аутентификация

### Проверка авторизации

```javascript
// MODX автоматически управляет сессиями
// Проверить текущего пользователя
async function checkAuth() {
    try {
        const profile = await api.request('getMyProfile');
        console.log('User:', profile);
        return profile;
    } catch (error) {
        if (error.message.includes('Authentication required')) {
            window.location.href = '/login';
        }
    }
}
```

---

## Работа с тестами

### Полный цикл прохождения теста

```javascript
class TestSession {
    constructor(testId) {
        this.testId = testId;
        this.sessionId = null;
        this.currentQuestion = null;
    }

    // 1. Начать тест
    async start(userId) {
        const result = await api.request('startSession', {
            test_id: this.testId,
            user_id: userId
        });

        this.sessionId = result.session_id;
        console.log('Session started:', result);
        return result;
    }

    // 2. Получить следующий вопрос
    async getNextQuestion() {
        const result = await api.request('getNextQuestion', {
            session_id: this.sessionId
        });

        this.currentQuestion = result.question;
        console.log('Question:', this.currentQuestion);
        return result;
    }

    // 3. Отправить ответ
    async submitAnswer(answer) {
        const result = await api.request('submitAnswer', {
            session_id: this.sessionId,
            question_id: this.currentQuestion.id,
            answer: answer
        });

        console.log('Answer result:', result);
        return result;
    }

    // 4. Завершить тест
    async finish() {
        const result = await api.request('finishTest', {
            session_id: this.sessionId
        });

        console.log('Test completed:', result);
        return result;
    }
}

// Использование
async function takeTest(testId, userId) {
    const session = new TestSession(testId);

    // Начать тест
    await session.start(userId);

    // Цикл прохождения вопросов
    while (true) {
        const questionData = await session.getNextQuestion();

        if (questionData.completed) {
            console.log('All questions answered!');
            break;
        }

        // Показать вопрос пользователю
        displayQuestion(questionData.question);

        // Получить ответ от пользователя
        const answer = await getUserAnswer(questionData.question);

        // Отправить ответ
        const result = await session.submitAnswer(answer);

        // Показать результат (если настроено)
        if (result.is_correct !== undefined) {
            showFeedback(result);
        }
    }

    // Завершить тест
    const finalResult = await session.finish();
    showTestResults(finalResult);
}
```

### Работа с разными типами вопросов

```javascript
// Обработка ответов для разных типов вопросов
function prepareAnswer(question, userInput) {
    switch (question.question_type) {
        case 'single':
            // Одиночный выбор - ID ответа
            return { answer_id: userInput };

        case 'multiple':
            // Множественный выбор - массив ID
            return { answer_ids: userInput }; // [1, 3, 5]

        case 'matching':
            // Сопоставление - объект пар
            return {
                pairs: userInput // {left_1: right_2, left_2: right_1}
            };

        case 'ordering':
            // Упорядочивание - массив ID в правильном порядке
            return {
                order: userInput // [3, 1, 4, 2]
            };

        case 'fill_blank':
            // Заполнение пропусков - массив ответов
            return {
                blanks: userInput // ['ответ1', 'ответ2', 'ответ3']
            };

        case 'essay':
            // Эссе - текст
            return {
                essay_text: userInput
            };

        default:
            throw new Error('Unknown question type');
    }
}

// Пример использования
async function submitQuestionAnswer(session, question, userInput) {
    const answer = prepareAnswer(question, userInput);
    return await session.submitAnswer(answer);
}
```

### Избранные вопросы

```javascript
// Добавить вопрос в избранное
async function toggleFavorite(questionId) {
    const result = await api.request('toggleFavorite', {
        question_id: questionId
    });

    console.log(result.is_favorite ? 'Added to favorites' : 'Removed from favorites');
    return result;
}

// Получить список избранных
async function getFavorites() {
    const result = await api.request('getFavoriteQuestions');
    console.log('Favorite questions:', result.questions);
    return result.questions;
}
```

---

## Учебные материалы

### Просмотр материала с отслеживанием прогресса

```javascript
class MaterialViewer {
    constructor(materialId) {
        this.materialId = materialId;
        this.material = null;
        this.currentProgress = 0;
    }

    // Загрузить материал
    async load() {
        this.material = await api.request('getMaterial', {
            material_id: this.materialId
        });

        console.log('Material loaded:', this.material);
        return this.material;
    }

    // Обновить прогресс
    async updateProgress(progress) {
        if (progress < 0 || progress > 100) {
            throw new Error('Progress must be between 0 and 100');
        }

        const result = await api.request('updateProgress', {
            material_id: this.materialId,
            progress: progress
        });

        this.currentProgress = progress;
        console.log(`Progress updated: ${progress}%`);
        return result;
    }

    // Отследить прокрутку для автоматического обновления прогресса
    trackScrollProgress(contentElement) {
        let lastProgress = 0;

        contentElement.addEventListener('scroll', () => {
            const scrollHeight = contentElement.scrollHeight - contentElement.clientHeight;
            const scrolled = contentElement.scrollTop;
            const progress = Math.round((scrolled / scrollHeight) * 100);

            // Обновлять только при изменении на 5%
            if (progress - lastProgress >= 5) {
                this.updateProgress(progress);
                lastProgress = progress;
            }
        });
    }
}

// Использование
async function viewMaterial(materialId) {
    const viewer = new MaterialViewer(materialId);
    const material = await viewer.load();

    // Отобразить материал
    renderMaterial(material);

    // Отследить прогресс
    const contentDiv = document.getElementById('material-content');
    viewer.trackScrollProgress(contentDiv);
}
```

### Работа с контент-блоками

```javascript
// Создать материал с блоками контента
async function createMaterialWithBlocks(title, categoryId) {
    // 1. Создать материал
    const material = await api.request('createMaterial', {
        title: title,
        category_id: categoryId,
        description: 'Описание материала'
    });

    const materialId = material.material_id;

    // 2. Добавить текстовый блок
    await api.request('addContentBlock', {
        material_id: materialId,
        type: 'text',
        content: JSON.stringify({
            text: '<h2>Введение</h2><p>Текст введения...</p>'
        }),
        order_num: 1
    });

    // 3. Добавить видео блок
    await api.request('addContentBlock', {
        material_id: materialId,
        type: 'video',
        content: JSON.stringify({
            url: 'https://youtube.com/watch?v=xxx',
            provider: 'youtube'
        }),
        order_num: 2
    });

    // 4. Добавить файловый блок
    await api.request('addContentBlock', {
        material_id: materialId,
        type: 'file',
        content: JSON.stringify({
            file_name: 'presentation.pdf',
            file_url: '/uploads/presentation.pdf',
            file_size: 1024000
        }),
        order_num: 3
    });

    // 5. Добавить тестовый блок
    await api.request('addContentBlock', {
        material_id: materialId,
        type: 'quiz',
        content: JSON.stringify({
            test_id: 15
        }),
        order_num: 4
    });

    return material;
}
```

---

## Траектории обучения

### Запись на траекторию и отслеживание прогресса

```javascript
class LearningPath {
    constructor(pathId) {
        this.pathId = pathId;
        this.enrollment = null;
    }

    // Записаться на траекторию
    async enroll() {
        this.enrollment = await api.request('enrollOnPath', {
            path_id: this.pathId
        });

        console.log('Enrolled on path:', this.enrollment);
        return this.enrollment;
    }

    // Получить прогресс
    async getProgress() {
        const progress = await api.request('getPathProgress', {
            path_id: this.pathId
        });

        console.log('Path progress:', progress);
        return progress;
    }

    // Получить следующий шаг
    async getNextStep() {
        const step = await api.request('getNextPathStep', {
            path_id: this.pathId
        });

        console.log('Next step:', step);
        return step;
    }

    // Завершить шаг
    async completeStep(stepId, completionData = {}) {
        const result = await api.request('completePathStep', {
            step_id: stepId,
            ...completionData
        });

        console.log('Step completed:', result);

        // Проверить, открылся ли следующий шаг
        if (result.next_step_unlocked) {
            console.log('Next step unlocked!');
        }

        // Проверить, завершена ли траектория
        if (result.path_completed) {
            console.log('Path completed! Certificate available:', result.certificate_issued);
        }

        return result;
    }

    // Получить все мои траектории
    static async getMyPaths() {
        const result = await api.request('getMyPaths');
        return result.paths;
    }
}

// Использование
async function followLearningPath(pathId) {
    const path = new LearningPath(pathId);

    // Записаться
    await path.enroll();

    // Получить первый шаг
    let nextStep = await path.getNextStep();

    while (nextStep) {
        console.log('Current step:', nextStep.title);

        // Выполнить требования шага
        await performStepRequirements(nextStep);

        // Отметить шаг как выполненный
        const result = await path.completeStep(nextStep.id);

        if (result.path_completed) {
            console.log('Congratulations! Path completed!');
            break;
        }

        // Получить следующий шаг
        nextStep = await path.getNextStep();
    }
}
```

---

## Геймификация

### Профиль и достижения

```javascript
// Получить свой профиль с XP и уровнем
async function getMyGameProfile() {
    const profile = await api.request('getMyProfile');

    console.log(`Level ${profile.current_level}: ${profile.level_name}`);
    console.log(`XP: ${profile.total_xp} / ${profile.next_level_xp}`);
    console.log(`Progress to next level: ${profile.progress_to_next}%`);
    console.log(`Rank: #${profile.rank}`);

    return profile;
}

// Получить достижения
async function getMyAchievements(includeNotEarned = false) {
    const result = await api.request('getMyAchievements', {
        include_not_earned: includeNotEarned
    });

    const earned = result.achievements.filter(a => a.earned_at);
    const notEarned = result.achievements.filter(a => !a.earned_at);

    console.log(`Earned: ${earned.length}, Not earned: ${notEarned.length}`);

    return result.achievements;
}

// Получить серию активности
async function getMyStreak() {
    const streak = await api.request('getMyStreak');

    console.log(`Current streak: ${streak.current_streak} days`);
    console.log(`Longest streak: ${streak.longest_streak} days`);

    return streak;
}
```

### Рейтинговая таблица

```javascript
// Получить рейтинг
async function getLeaderboard(period = 'all_time', categoryId = null, limit = 10) {
    const result = await api.request('getLeaderboard', {
        period: period,        // 'all_time', 'yearly', 'monthly', 'weekly'
        category_id: categoryId,
        limit: limit
    });

    console.log(`Leaderboard (${period}):`, result.leaderboard);

    // Отобразить таблицу
    displayLeaderboardTable(result.leaderboard);

    return result;
}

// Компонент для отображения рейтинга
function displayLeaderboardTable(entries) {
    const html = entries.map((entry, index) => `
        <tr class="${entry.is_current_user ? 'highlight' : ''}">
            <td>${entry.rank}</td>
            <td>${entry.username}</td>
            <td>${entry.total_xp} XP</td>
            <td>Level ${entry.user_level}</td>
            <td>${entry.achievements_count} achievements</td>
        </tr>
    `).join('');

    document.getElementById('leaderboard-tbody').innerHTML = html;
}
```

---

## Уведомления

### Управление уведомлениями

```javascript
class NotificationManager {
    constructor() {
        this.unreadCount = 0;
    }

    // Получить непрочитанные
    async getUnreadCount() {
        const result = await api.request('getUnreadCount');
        this.unreadCount = result.unread_count;

        // Обновить значок
        this.updateBadge(this.unreadCount);

        return this.unreadCount;
    }

    // Получить уведомления
    async getNotifications(filters = {}) {
        const result = await api.request('getMyNotifications', {
            is_read: filters.is_read,
            type: filters.type,
            limit: filters.limit || 20,
            offset: filters.offset || 0
        });

        return result.notifications;
    }

    // Отметить как прочитанное
    async markAsRead(notificationId) {
        await api.request('markAsRead', {
            notification_id: notificationId
        });

        this.unreadCount--;
        this.updateBadge(this.unreadCount);
    }

    // Отметить все как прочитанные
    async markAllAsRead() {
        await api.request('markAllAsRead');

        this.unreadCount = 0;
        this.updateBadge(0);
    }

    // Обновить значок
    updateBadge(count) {
        const badge = document.getElementById('notification-badge');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'inline' : 'none';
        }
    }

    // Polling для новых уведомлений
    startPolling(interval = 30000) {
        this.pollingInterval = setInterval(() => {
            this.getUnreadCount();
        }, interval);
    }

    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
    }
}

// Использование
const notifications = new NotificationManager();

// Загрузить непрочитанные при загрузке страницы
document.addEventListener('DOMContentLoaded', async () => {
    await notifications.getUnreadCount();
    notifications.startPolling(); // Проверять каждые 30 секунд
});
```

### Настройки подписок

```javascript
// Получить настройки
async function getNotificationPreferences() {
    const prefs = await api.request('getMyPreferences');
    console.log('Notification preferences:', prefs.preferences);
    return prefs.preferences;
}

// Обновить подписку
async function updateNotificationPreference(type, channel, enabled) {
    await api.request('updatePreference', {
        notification_type: type,     // 'test_completed', 'achievement_earned', etc.
        channel: channel,            // 'system', 'email', 'push'
        is_enabled: enabled
    });

    console.log(`Updated: ${type} via ${channel} = ${enabled}`);
}

// Пример: Компонент настроек
async function renderPreferencesForm() {
    const prefs = await getNotificationPreferences();

    const form = document.getElementById('preferences-form');

    prefs.forEach(pref => {
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.checked = pref.is_enabled;
        checkbox.addEventListener('change', () => {
            updateNotificationPreference(
                pref.notification_type,
                pref.channel,
                checkbox.checked
            );
        });

        const label = document.createElement('label');
        label.textContent = `${pref.notification_type} (${pref.channel})`;
        label.prepend(checkbox);

        form.appendChild(label);
    });
}
```

---

## Аналитика и отчеты

### Дашборды

```javascript
// Дашборд пользователя
async function loadUserDashboard() {
    const dashboard = await api.request('getMyDashboard');

    // Общая статистика
    console.log('Tests completed:', dashboard.overview.tests_completed);
    console.log('Average score:', dashboard.overview.average_score);
    console.log('Total study time:', dashboard.overview.total_study_time);

    // Последние тесты
    renderRecentTests(dashboard.recent_tests);

    // Прогресс по категориям
    renderCategoryProgress(dashboard.category_progress);

    return dashboard;
}

// Дашборд администратора
async function loadAdminDashboard() {
    const dashboard = await api.request('getAdminDashboard');

    // Системная статистика
    console.log('Total users:', dashboard.system_stats.total_users);
    console.log('Active users (30d):', dashboard.system_stats.active_users_30d);
    console.log('Total tests:', dashboard.system_stats.total_tests);

    // Топ тесты
    renderTopTests(dashboard.top_tests);

    // Топ пользователи
    renderTopUsers(dashboard.top_users);

    // Недавняя активность
    renderRecentActivity(dashboard.recent_activity);

    return dashboard;
}
```

### Статистика

```javascript
// Моя статистика с фильтрацией по периоду
async function getMyStats(period = 'all_time') {
    const stats = await api.request('getMyStatistics', {
        period: period,        // 'all_time', '30_days', '7_days'
        use_cache: true
    });

    console.log('Statistics:', stats);

    return {
        testsCompleted: stats.tests_completed,
        averageScore: stats.average_score,
        bestScore: stats.best_score,
        totalQuestions: stats.total_questions_answered,
        correctAnswers: stats.correct_answers,
        accuracy: stats.accuracy_rate
    };
}

// Сравнение с другими пользователями
async function compareWithOthers(categoryId = null) {
    const comparison = await api.request('getUserComparison', {
        category_id: categoryId
    });

    console.log(`Your percentile: ${comparison.percentile}%`);
    console.log(`Better than ${comparison.users_below} users`);
    console.log(`Average in category: ${comparison.category_average}`);

    return comparison;
}
```

### Генерация отчетов

```javascript
// Сгенерировать отчет
async function generateReport(reportType, format = 'json', filters = {}) {
    const result = await api.request('generateReport', {
        report_type: reportType,  // 'user_progress', 'test_performance', 'question_difficulty', etc.
        format: format,           // 'csv', 'json', 'html'
        filters: filters
    });

    console.log('Report generated:', result.report_id);
    console.log('File path:', result.file_path);
    console.log('Generation time:', result.generation_time);

    // Скачать файл
    if (result.download_url) {
        window.open(result.download_url, '_blank');
    }

    return result;
}

// Пример: Отчет по успеваемости в категории
async function exportCategoryReport(categoryId) {
    const report = await generateReport('test_performance', 'csv', {
        category_id: categoryId,
        start_date: '2025-01-01',
        end_date: '2025-12-31'
    });

    return report;
}

// Получить историю отчетов
async function getReportHistory(limit = 10) {
    const result = await api.request('getReportHistory', {
        limit: limit
    });

    console.log('Recent reports:', result.history);
    return result.history;
}
```

---

## Сертификаты

### Просмотр и верификация

```javascript
// Получить мои сертификаты
async function getMyCertificates(entityType = null) {
    const result = await api.request('getMyCertificates', {
        entity_type: entityType,  // 'test', 'path', 'course' или null
        is_revoked: false
    });

    console.log('My certificates:', result.certificates);
    return result.certificates;
}

// Получить конкретный сертификат
async function getCertificate(certificateId) {
    const cert = await api.request('getCertificate', {
        certificate_id: certificateId
    });

    console.log('Certificate:', cert);
    return cert;
}

// Скачать сертификат
async function downloadCertificate(certificateId) {
    const result = await api.request('downloadCertificate', {
        certificate_id: certificateId
    });

    if (result.file_url) {
        window.open(result.file_url, '_blank');
    }

    return result;
}

// Верифицировать сертификат (публичный endpoint)
async function verifyCertificate(verificationCode) {
    const result = await api.request('verifyCertificate', {
        verification_code: verificationCode
    });

    if (result.valid) {
        console.log('Certificate is valid!');
        console.log('Issued to:', result.certificate.user_name);
        console.log('Issued at:', result.certificate.issued_at);
        console.log('Entity:', result.certificate.entity_title);
    } else {
        console.log('Certificate is invalid or revoked');
    }

    return result;
}
```

### Проверка возможности получения

```javascript
// Проверить, можно ли получить сертификат
async function checkCertificateEligibility(templateId, entityType, entityId) {
    const result = await api.request('checkEligibility', {
        template_id: templateId,
        entity_type: entityType,  // 'test', 'path', 'course'
        entity_id: entityId
    });

    if (result.eligible) {
        console.log('You are eligible for this certificate!');
    } else {
        console.log('Requirements not met:');
        result.missing_requirements.forEach(req => {
            console.log(`- ${req.requirement_type}: ${req.description}`);
        });
    }

    return result;
}
```

---

## Обработка ошибок

### Централизованная обработка

```javascript
// Расширенный API клиент с обработкой ошибок
class TestSystemAPIWithErrors extends TestSystemAPI {
    async request(action, data = {}) {
        try {
            return await super.request(action, data);
        } catch (error) {
            return this.handleError(error, action);
        }
    }

    handleError(error, action) {
        const message = error.message || 'Unknown error';

        // Разные типы ошибок
        if (message.includes('Authentication required')) {
            this.handleAuthError();
            return null;
        }

        if (message.includes('Permission denied') || message.includes('Forbidden')) {
            this.handlePermissionError(action);
            return null;
        }

        if (message.includes('not found') || message.includes('Not Found')) {
            this.handleNotFoundError(action);
            return null;
        }

        if (message.includes('validation') || message.includes('required')) {
            this.handleValidationError(message);
            return null;
        }

        // Общая ошибка сервера
        this.handleServerError(error, action);
        return null;
    }

    handleAuthError() {
        console.error('Authentication required');

        // Показать уведомление
        this.showNotification('Требуется авторизация', 'error');

        // Редирект на страницу входа
        setTimeout(() => {
            window.location.href = '/login?return=' + encodeURIComponent(window.location.pathname);
        }, 2000);
    }

    handlePermissionError(action) {
        console.error('Permission denied for action:', action);
        this.showNotification('У вас нет прав для выполнения этого действия', 'error');
    }

    handleNotFoundError(action) {
        console.error('Resource not found for action:', action);
        this.showNotification('Запрашиваемый ресурс не найден', 'error');
    }

    handleValidationError(message) {
        console.error('Validation error:', message);
        this.showNotification('Ошибка валидации: ' + message, 'warning');
    }

    handleServerError(error, action) {
        console.error('Server error:', error);
        this.showNotification('Произошла ошибка сервера. Попробуйте позже.', 'error');

        // Отправить в систему мониторинга
        this.logErrorToMonitoring(error, action);
    }

    showNotification(message, type = 'info') {
        // Реализация зависит от UI библиотеки
        console.log(`[${type.toUpperCase()}] ${message}`);

        // Пример с нативным уведомлением
        if (window.Notification && Notification.permission === 'granted') {
            new Notification('Test System', { body: message });
        }
    }

    logErrorToMonitoring(error, action) {
        // Отправить в систему мониторинга (Sentry, LogRocket, etc.)
        if (window.Sentry) {
            window.Sentry.captureException(error, {
                tags: { action: action }
            });
        }
    }
}

// Использовать расширенный API клиент
const apiWithErrors = new TestSystemAPIWithErrors();
```

### Retry логика для сетевых ошибок

```javascript
class RobustTestSystemAPI extends TestSystemAPI {
    async requestWithRetry(action, data = {}, maxRetries = 3) {
        let lastError;

        for (let attempt = 0; attempt < maxRetries; attempt++) {
            try {
                return await this.request(action, data);
            } catch (error) {
                lastError = error;

                // Не повторять для ошибок валидации или прав
                if (this.shouldNotRetry(error)) {
                    throw error;
                }

                // Экспоненциальная задержка
                const delay = Math.pow(2, attempt) * 1000;
                console.log(`Retry ${attempt + 1}/${maxRetries} after ${delay}ms`);

                await this.sleep(delay);
            }
        }

        throw lastError;
    }

    shouldNotRetry(error) {
        const message = error.message || '';
        return message.includes('Authentication') ||
               message.includes('Permission') ||
               message.includes('validation');
    }

    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Использование
const robustApi = new RobustTestSystemAPI();

async function fetchWithRetry() {
    try {
        const data = await robustApi.requestWithRetry('getMyStatistics', {
            period: 'all_time'
        });
        return data;
    } catch (error) {
        console.error('Failed after retries:', error);
    }
}
```

---

## Интеграция с MODX

### Использование в MODX темплейтах

```html
<!-- Чанк: testSystemHeader -->
<script>
// Инициализировать API с MODX CSRF токеном
const api = new TestSystemAPI();
api.csrfToken = '[[+modx.user.csrf_token]]';

// Добавить user_id из MODX
const currentUserId = [[+modx.user.id]];
</script>
```

### Snippet для вывода статистики

```php
<?php
/**
 * Snippet: getUserStats
 *
 * Параметры:
 * &userId - ID пользователя (по умолчанию текущий)
 * &period - Период статистики
 * &tpl - Чанк для вывода
 */

$userId = $scriptProperties['userId'] ?? $modx->user->get('id');
$period = $scriptProperties['period'] ?? 'all_time';
$tpl = $scriptProperties['tpl'] ?? 'tplUserStats';

// Загрузить сервис
require_once MODX_CORE_PATH . 'components/testsystem/services/AnalyticsService.php';

// Получить статистику
$stats = AnalyticsService::getUserStatistics($modx, $userId, $period);

if (!$stats) {
    return 'Статистика не найдена';
}

// Обработать через чанк
return $modx->getChunk($tpl, $stats);
```

### Чанк для вывода

```html
<!-- Чанк: tplUserStats -->
<div class="user-stats">
    <h3>Моя статистика</h3>
    <ul>
        <li>Тесты пройдены: <strong>[[+tests_completed]]</strong></li>
        <li>Средний балл: <strong>[[+average_score]]%</strong></li>
        <li>Лучший балл: <strong>[[+best_score]]%</strong></li>
        <li>Точность ответов: <strong>[[+accuracy_rate]]%</strong></li>
        <li>Всего вопросов: <strong>[[+total_questions_answered]]</strong></li>
        <li>Правильных ответов: <strong>[[+correct_answers]]</strong></li>
    </ul>
</div>
```

### Plugin для автоматической обработки

```php
<?php
/**
 * Plugin: TestSystemMaintenance
 *
 * События:
 * - OnBeforeCacheUpdate: Очистка старых сессий
 * - OnUserFormSave: Обновление профиля геймификации
 */

switch ($modx->event->name) {
    case 'OnBeforeCacheUpdate':
        // Очистка старых сессий при обновлении кеша
        require_once MODX_CORE_PATH . 'components/testsystem/services/DataIntegrityService.php';
        DataIntegrityService::cleanupOldSessions($modx, 30);
        break;

    case 'OnUserFormSave':
        // Создать профиль геймификации для нового пользователя
        if ($mode === modSystemEvent::MODE_NEW) {
            require_once MODX_CORE_PATH . 'components/testsystem/services/GamificationService.php';
            GamificationService::initializeUserProfile($modx, $user->get('id'));
        }
        break;
}
```

### AJAX вызов из MODX формы

```javascript
// Отправить форму создания материала через MODX FormIt
document.getElementById('material-form').addEventListener('submit', async (e) => {
    e.preventDefault();

    const formData = new FormData(e.target);

    try {
        const result = await api.request('createMaterial', {
            title: formData.get('title'),
            category_id: parseInt(formData.get('category_id')),
            description: formData.get('description'),
            content_type: formData.get('content_type')
        });

        // Редирект на созданный материал
        window.location.href = `/materials/${result.material_id}`;
    } catch (error) {
        document.getElementById('error-message').textContent = error.message;
    }
});
```

---

## Полный пример: Тестирование с геймификацией

```javascript
// Комплексный пример прохождения теста с отслеживанием всех метрик
class GameifiedTestSession {
    constructor(testId, userId) {
        this.testId = testId;
        this.userId = userId;
        this.session = null;
        this.startTime = null;
        this.questionsAnswered = 0;
    }

    async start() {
        this.startTime = Date.now();

        // Начать сессию
        const result = await api.request('startSession', {
            test_id: this.testId,
            user_id: this.userId
        });

        this.session = result;
        console.log('Test started:', result);

        return result;
    }

    async answerQuestions() {
        while (true) {
            // Получить следующий вопрос
            const questionData = await api.request('getNextQuestion', {
                session_id: this.session.session_id
            });

            if (questionData.completed) {
                break;
            }

            // Показать вопрос
            const question = questionData.question;
            console.log(`Question ${this.questionsAnswered + 1}:`, question.question_text);

            // Получить ответ (в реальности от пользователя)
            const answer = await this.getUserAnswer(question);

            // Отправить ответ
            const answerResult = await api.request('submitAnswer', {
                session_id: this.session.session_id,
                question_id: question.id,
                answer: answer
            });

            this.questionsAnswered++;

            // Показать результат
            if (answerResult.is_correct !== undefined) {
                console.log(answerResult.is_correct ? '✓ Correct!' : '✗ Incorrect');
            }
        }
    }

    async finish() {
        // Завершить тест
        const result = await api.request('finishTest', {
            session_id: this.session.session_id
        });

        console.log('Test finished:', result);

        const duration = Math.round((Date.now() - this.startTime) / 1000);

        // Показать результаты
        this.displayResults(result, duration);

        // Проверить новые достижения
        await this.checkAchievements(result, duration);

        // Обновить профиль
        await this.updateProfile();

        return result;
    }

    displayResults(result, duration) {
        console.log('=== Test Results ===');
        console.log(`Score: ${result.score}%`);
        console.log(`Questions: ${this.questionsAnswered}`);
        console.log(`Duration: ${duration}s`);
        console.log(`XP Earned: ${result.xp_earned || 'N/A'}`);
    }

    async checkAchievements(result, duration) {
        // Проверить достижения
        const achievements = await api.request('checkAchievements', {
            activity_type: 'test_completed',
            activity_data: {
                test_id: this.testId,
                score: result.score,
                duration: duration
            }
        });

        if (achievements.earned && achievements.earned.length > 0) {
            console.log('New achievements earned:');
            achievements.earned.forEach(achievement => {
                console.log(`🏆 ${achievement.name}: ${achievement.description}`);
            });
        }
    }

    async updateProfile() {
        const profile = await api.request('getMyProfile');

        console.log(`Level: ${profile.current_level} - ${profile.level_name}`);
        console.log(`XP: ${profile.total_xp}`);

        if (profile.level_up) {
            console.log('🎉 LEVEL UP! You are now level', profile.current_level);
        }
    }

    async getUserAnswer(question) {
        // В реальном приложении - получить от пользователя через UI
        // Для примера - случайный правильный ответ
        return { answer_id: question.answers[0].id };
    }
}

// Использование
async function runGameifiedTest() {
    const testSession = new GameifiedTestSession(1, 5);

    await testSession.start();
    await testSession.answerQuestions();
    const results = await testSession.finish();

    console.log('Final results:', results);
}

// Запустить
runGameifiedTest();
```

---

## Заключение

Эти примеры охватывают основные сценарии использования Test System API.

### Дополнительные ресурсы

- **API Reference**: См. `API_ENDPOINTS.md` для полного списка всех 120 endpoints
- **Architecture**: См. `README.md` для информации об архитектуре и технологическом стеке
- **SQL Schema**: См. файлы в `core/components/testsystem/sql/` для структуры БД

### Поддержка

При возникновении вопросов обращайтесь к документации или создавайте issue в репозитории проекта.

---

**Последнее обновление:** 2025-11-15
**Версия API:** 2.0
