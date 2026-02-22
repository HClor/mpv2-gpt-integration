# Руководство по интеграции фронтенда LMS системы

## Оглавление
- [Общая информация](#общая-информация)
- [Подключение файлов](#подключение-файлов)
- [CSRF защита](#csrf-защита)
- [Интеграция по спринтам](#интеграция-по-спринтам)
- [Примеры HTML страниц](#примеры-html-страниц)
- [API Endpoints](#api-endpoints)

---

## Общая информация

Все JavaScript модули являются самодостаточными и подключаются независимо друг от друга.

### Требования
- **Bootstrap 5.x** (для UI компонентов)
- **Bootstrap Icons** (для иконок)
- **jQuery** не требуется (все на Vanilla JS)
- **PHP Backend** с соответствующими API endpoints

---

## Подключение файлов

### В `<head>` секции:

```html
<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

<!-- Основные стили testsystem -->
<link href="/assets/components/testsystem/css/tsrunner.css" rel="stylesheet">

<!-- Расширенные стили для спринтов 9-17 -->
<link href="/assets/components/testsystem/css/testsystem-extended.css" rel="stylesheet">

<!-- CSRF токен для безопасности -->
<meta name="csrf-token" content="<?php echo $csrfToken; ?>">
```

### Перед закрывающим `</body>`:

```html
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Подключайте только нужные модули -->

<!-- Sprint 9: Учебные материалы -->
<script src="/assets/components/testsystem/js/learning-materials.js"></script>

<!-- Sprint 10: Права доступа -->
<script src="/assets/components/testsystem/js/category-permissions.js"></script>

<!-- Sprint 11: Траектории обучения -->
<script src="/assets/components/testsystem/js/learning-paths.js"></script>

<!-- Sprint 12: Расширенные типы вопросов -->
<script src="/assets/components/testsystem/js/special-question-types.js"></script>

<!-- Sprint 13: Геймификация -->
<script src="/assets/components/testsystem/js/gamification.js"></script>

<!-- Sprint 14: Уведомления -->
<script src="/assets/components/testsystem/js/notifications.js"></script>

<!-- Sprint 15: Аналитика -->
<script src="/assets/components/testsystem/js/analytics.js"></script>

<!-- Sprint 16: Сертификаты -->
<script src="/assets/components/testsystem/js/certificates.js"></script>
```

---

## CSRF защита

Все модули автоматически читают CSRF токен из meta тега:

```html
<meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
```

**Генерация токена в PHP:**

```php
// В начале сессии
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// В шаблоне
$csrfToken = $_SESSION['csrf_token'];
```

---

## Интеграция по спринтам

### Sprint 9: Учебные материалы

#### Страница списка материалов

```html
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Учебные материалы</h2>
        <button class="btn btn-primary" id="create-material-btn">
            <i class="bi bi-plus-circle"></i> Создать материал
        </button>
    </div>

    <!-- Фильтры -->
    <div class="row mb-3">
        <div class="col-md-6">
            <input type="text" class="form-control" id="material-search" placeholder="Поиск...">
        </div>
        <div class="col-md-6">
            <select class="form-select" id="material-category-filter">
                <option value="">Все категории</option>
                <!-- Опции заполняются динамически -->
            </select>
        </div>
    </div>

    <!-- Список материалов (заполняется JS) -->
    <div class="row g-4" id="materials-list-container"></div>
</div>

<!-- Модальное окно создания -->
<div class="modal fade" id="materialModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="material-modal-title">Создать материал</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Название *</label>
                    <input type="text" class="form-control" id="material-title" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Описание</label>
                    <textarea class="form-control" id="material-description" rows="3"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Категория</label>
                    <select class="form-select category-select" id="material-category"></select>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="material-published">
                    <label class="form-check-label" for="material-published">
                        Опубликовать сразу
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="save-material-btn">
                    <i class="bi bi-check-circle"></i> Сохранить
                </button>
            </div>
        </div>
    </div>
</div>
```

#### Страница просмотра материала

```html
<div class="container mt-4">
    <!-- Контейнер для материала (заполняется JS) -->
    <div id="material-view-container" data-material-id="<?php echo $materialId; ?>"></div>
</div>
```

#### Страница редактирования материала

```html
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 id="editor-material-title">Редактирование материала</h2>
        </div>
        <div>
            <button class="btn btn-success" id="save-blocks-btn">
                <i class="bi bi-check-circle"></i> Сохранить блоки
            </button>
        </div>
    </div>

    <!-- Кнопки добавления блоков -->
    <div class="btn-group mb-4" role="group">
        <button class="btn btn-outline-primary" id="add-text-block-btn">
            <i class="bi bi-text-paragraph"></i> Текст
        </button>
        <button class="btn btn-outline-primary" id="add-image-block-btn">
            <i class="bi bi-image"></i> Изображение
        </button>
        <button class="btn btn-outline-primary" id="add-video-block-btn">
            <i class="bi bi-play-circle"></i> Видео
        </button>
        <button class="btn btn-outline-primary" id="add-file-block-btn">
            <i class="bi bi-file-earmark"></i> Файл
        </button>
        <button class="btn btn-outline-primary" id="add-quiz-block-btn">
            <i class="bi bi-question-circle"></i> Тест
        </button>
    </div>

    <!-- Редактор блоков (заполняется JS) -->
    <div id="blocks-editor-container"></div>
</div>

<!-- Контейнер для редактора -->
<div id="material-editor-container" data-material-id="<?php echo $materialId; ?>"></div>
```

---

### Sprint 10: Права доступа

```html
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Управление правами доступа</h2>
        <button class="btn btn-primary" id="add-permission-btn">
            <i class="bi bi-plus-circle"></i> Добавить права
        </button>
    </div>

    <!-- Фильтры -->
    <div class="row mb-3">
        <div class="col-md-4">
            <select class="form-select category-select" id="category-filter">
                <option value="">Все категории</option>
            </select>
        </div>
        <div class="col-md-4">
            <select class="form-select" id="role-filter">
                <option value="">Все роли</option>
                <option value="admin">Администратор</option>
                <option value="expert">Эксперт</option>
                <option value="viewer">Просмотр</option>
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" class="form-control" id="user-search" placeholder="Поиск пользователя...">
        </div>
    </div>

    <div class="mb-3">
        <button class="btn btn-outline-info" id="view-audit-log-btn">
            <i class="bi bi-clock-history"></i> Журнал изменений
        </button>
    </div>

    <!-- Список прав (заполняется JS) -->
    <div id="category-permissions-container"></div>
    <div id="permissions-list-container"></div>
</div>

<!-- Модальное окно добавления прав -->
<div class="modal fade" id="addPermissionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Добавить права доступа</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Категория *</label>
                    <select class="form-select category-select" id="permission-category"></select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Пользователь *</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="permission-user-search" placeholder="Поиск по имени или email">
                        <button class="btn btn-outline-primary" id="search-users-btn">
                            <i class="bi bi-search"></i> Найти
                        </button>
                    </div>
                    <div id="user-search-results" class="mt-3"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Роль *</label>
                    <select class="form-select" id="permission-role">
                        <option value="viewer">👀 Просмотр - только чтение</option>
                        <option value="expert">🎓 Эксперт - создание и редактирование</option>
                        <option value="admin">👑 Администратор - полный доступ</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="save-permission-btn">
                    <i class="bi bi-check-circle"></i> Добавить
                </button>
            </div>
        </div>
    </div>
</div>
```

---

### Sprint 11: Траектории обучения

#### Список траекторий

```html
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Траектории обучения</h2>
        <button class="btn btn-primary" id="create-path-btn">
            <i class="bi bi-plus-circle"></i> Создать траекторию
        </button>
    </div>

    <!-- Фильтры -->
    <div class="row mb-3">
        <div class="col-md-6">
            <select class="form-select" id="filter-category">
                <option value="">Все категории</option>
            </select>
        </div>
        <div class="col-md-6">
            <select class="form-select" id="filter-difficulty">
                <option value="">Все уровни</option>
                <option value="beginner">Начальный</option>
                <option value="intermediate">Средний</option>
                <option value="advanced">Продвинутый</option>
            </select>
        </div>
    </div>

    <!-- Список траекторий (заполняется JS) -->
    <div class="row g-4" id="learning-paths-container"></div>
</div>
```

#### Просмотр траектории

```html
<div class="container mt-4">
    <!-- Заполняется JS -->
    <div id="path-view-container" data-path-id="<?php echo $pathId; ?>"></div>
</div>
```

---

### Sprint 12: Расширенные типы вопросов

Эти типы вопросов интегрируются в существующий `tsrunner.js`. Нужно модифицировать рендеринг вопросов:

```javascript
// В tsrunner.js добавьте проверку типа вопроса:

function renderQuestion(question) {
    const questionData = JSON.parse(question.question_data || '{}');

    // Используем специальные типы из Sprint 12
    if (question.question_type === 'matching') {
        return SpecialQuestionTypes.renderMatchingQuestion(question, questionData);
    } else if (question.question_type === 'ordering') {
        return SpecialQuestionTypes.renderOrderingQuestion(question, questionData);
    } else if (question.question_type === 'fill_blank') {
        return SpecialQuestionTypes.renderFillBlankQuestion(question, questionData);
    } else if (question.question_type === 'essay') {
        return SpecialQuestionTypes.renderEssayQuestion(question, questionData);
    }

    // Существующий код для single/multiple choice...
}

// При сборе ответа:
function collectAnswer() {
    const questionElement = document.querySelector('.current-question');
    const questionType = questionElement.dataset.questionType;

    if (questionType === 'matching') {
        return SpecialQuestionTypes.getMatchingAnswer(questionElement);
    } else if (questionType === 'ordering') {
        return SpecialQuestionTypes.getOrderingAnswer(questionElement);
    }
    // и т.д.
}
```

---

### Sprint 13: Геймификация

#### Виджет в хедере (всегда видимый)

```html
<!-- В шапке сайта -->
<header class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <a class="navbar-brand" href="/">LMS</a>

        <!-- ... другие элементы навигации ... -->

        <!-- Виджет геймификации -->
        <div id="gamification-header-widget"></div>
    </div>
</header>
```

#### Страница профиля пользователя

```html
<div class="container mt-4">
    <h2 class="mb-4">Мой профиль</h2>

    <!-- Заполняется JS -->
    <div id="gamification-profile-container"></div>
</div>
```

#### Страница достижений

```html
<div class="container mt-4">
    <h2 class="mb-4">Мои достижения</h2>

    <!-- Заполняется JS -->
    <div id="achievements-container"></div>
</div>
```

#### Страница рейтинга

```html
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Рейтинг</h2>
        <select class="form-select w-auto" id="leaderboard-period">
            <option value="day">За день</option>
            <option value="week">За неделю</option>
            <option value="month">За месяц</option>
            <option value="all_time" selected>За все время</option>
        </select>
    </div>

    <!-- Заполняется JS -->
    <div id="leaderboard-container"></div>
</div>
```

#### Вызов уведомлений после тестов

```javascript
// После завершения теста в tsrunner.js:

if (result.xp_earned) {
    Gamification.showXPNotification(result.xp_earned, 'Тест завершен');
}

if (result.level_up) {
    Gamification.showLevelUpNotification(result.new_level);
}

if (result.achievement_unlocked) {
    Gamification.showAchievementNotification(result.achievement);
}
```

---

### Sprint 14: Уведомления

#### Колокольчик в хедере

```html
<header class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container">
        <!-- ... -->

        <!-- Колокольчик уведомлений -->
        <div class="dropdown">
            <a href="#" class="position-relative" id="notifications-bell" data-bs-toggle="dropdown">
                <i class="bi bi-bell fs-4"></i>
                <span class="badge bg-danger position-absolute" id="notifications-badge" style="display: none;">0</span>
            </a>
            <div class="dropdown-menu dropdown-menu-end" id="notifications-dropdown" style="min-width: 350px;">
                <!-- Заполняется JS -->
            </div>
        </div>
    </div>
</header>
```

#### Страница всех уведомлений

```html
<div class="container mt-4">
    <h2 class="mb-4">Мои уведомления</h2>

    <!-- Заполняется JS -->
    <div id="all-notifications-container"></div>
</div>
```

#### Страница настроек уведомлений

```html
<div class="container mt-4">
    <h2 class="mb-4">Настройки уведомлений</h2>

    <!-- Заполняется JS -->
    <div id="notification-settings-container"></div>
</div>
```

---

### Sprint 15: Аналитика

```html
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Аналитика</h2>
        <div>
            <select class="form-select d-inline-block w-auto me-2" id="analytics-period-select">
                <option value="day">За день</option>
                <option value="week">За неделю</option>
                <option value="month" selected>За месяц</option>
                <option value="year">За год</option>
            </select>
            <button class="btn btn-success" id="export-report-btn">
                <i class="bi bi-download"></i> Экспорт
            </button>
        </div>
    </div>

    <!-- Выбор формата экспорта (скрыт по умолчанию) -->
    <select class="form-select w-auto mb-3" id="export-format" style="display: none;">
        <option value="csv">CSV</option>
        <option value="json">JSON</option>
        <option value="html">HTML</option>
    </select>

    <!-- Заполняется JS -->
    <div id="analytics-container"></div>
</div>
```

---

### Sprint 16: Сертификаты

#### Мои сертификаты

```html
<div class="container mt-4">
    <h2 class="mb-4">Мои сертификаты</h2>

    <!-- Заполняется JS -->
    <div id="certificates-container"></div>
</div>
```

#### Публичная страница верификации

```html
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h3 class="text-center mb-4">Проверка сертификата</h3>

                    <div class="mb-3">
                        <label class="form-label">Номер сертификата</label>
                        <input type="text"
                               class="form-control"
                               id="certificate-number-input"
                               placeholder="CERT-XXXX-XXXX-XXXX">
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-primary" id="verify-certificate-btn">
                            <i class="bi bi-shield-check"></i> Проверить
                        </button>
                    </div>

                    <div id="verification-result" class="mt-4"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="certificate-verify-container"></div>
```

---

## API Endpoints

Все модули ожидают следующие API endpoints в файле `/assets/components/testsystem/ajax/testsystem.php`:

### Sprint 9: Учебные материалы

```
getMaterials                    - Получить список материалов
getMaterialWithBlocks           - Получить материал с блоками
createMaterial                  - Создать материал
updateMaterial                  - Обновить материал
deleteMaterial                  - Удалить материал
saveMaterialBlocks              - Сохранить блоки материала
markBlockComplete               - Отметить блок как завершенный
submitQuizAnswer                - Отправить ответ на квиз в материале
```

### Sprint 10: Права доступа

```
getCategories                   - Получить категории
getCategoryPermissions          - Получить права доступа
grantCategoryPermission         - Предоставить права
updateCategoryPermission        - Обновить права
revokeCategoryPermission        - Отозвать права
getCategoryPermissionAuditLog   - Получить журнал изменений
searchUsers                     - Поиск пользователей
```

### Sprint 11: Траектории обучения

```
getLearningPaths                - Получить список траекторий
getLearningPathWithSteps        - Получить траекторию с шагами
createLearningPath              - Создать траекторию
updateLearningPath              - Обновить траекторию
deleteLearningPath              - Удалить траекторию
addStepToPath                   - Добавить шаг
removeStepFromPath              - Удалить шаг
updateStepsOrder                - Обновить порядок шагов
getStepContent                  - Получить содержимое шага
```

### Sprint 12: Специальные типы вопросов

Эти endpoints уже должны быть в `QuestionController`:

```
getQuestion                     - Получить вопрос (с типом)
submitAnswer                    - Отправить ответ (обработка специальных типов)
```

### Sprint 13: Геймификация

```
getUserGamificationProfile      - Полный профиль пользователя
getUserGamificationSummary      - Краткая сводка для виджета
getUserAchievements             - Получить достижения
getLeaderboard                  - Получить рейтинг
```

### Sprint 14: Уведомления

```
getUnreadNotificationsCount     - Количество непрочитанных
getRecentNotifications          - Последние уведомления
getAllNotifications             - Все уведомления
markNotificationAsRead          - Отметить прочитанным
getNotificationSettings         - Получить настройки
saveNotificationSettings        - Сохранить настройки
```

### Sprint 15: Аналитика

```
getAnalyticsDashboard           - Получить дашборд
exportAnalyticsReport           - Экспорт отчета
```

### Sprint 16: Сертификаты

```
getMyCertificates               - Мои сертификаты
verifyCertificate               - Проверить сертификат
generateCertificatePDF          - Генерация PDF
```

---

## Пример MODX ресурса (страницы)

### Страница "Учебные материалы"

```html
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Учебные материалы - LMS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/components/testsystem/css/testsystem-extended.css" rel="stylesheet">

    <meta name="csrf-token" content="[[+csrf_token]]">
</head>
<body>
    [[+header]]

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Учебные материалы</h2>
            [[+canCreate:is=`1`:then=`
                <button class="btn btn-primary" id="create-material-btn">
                    <i class="bi bi-plus-circle"></i> Создать материал
                </button>
            `]]
        </div>

        <div class="row g-4" id="materials-list-container">
            <!-- Заполняется JS -->
        </div>
    </div>

    [[+footer]]

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/components/testsystem/js/learning-materials.js"></script>
</body>
</html>
```

---

## Отладка

### Проверка подключения

Откройте консоль браузера (F12) и проверьте:

```javascript
// Должны быть доступны глобальные объекты:
console.log(LearningMaterials);      // Sprint 9
console.log(CategoryPermissions);     // Sprint 10
console.log(LearningPaths);           // Sprint 11
console.log(SpecialQuestionTypes);    // Sprint 12
console.log(Gamification);            // Sprint 13
console.log(Notifications);           // Sprint 14
console.log(Analytics);               // Sprint 15
console.log(Certificates);            // Sprint 16
```

### Типичные ошибки

1. **"Cannot read property of undefined"**
   - Проверьте наличие контейнера с нужным ID
   - Убедитесь, что JS загружен после HTML

2. **"CSRF token missing"**
   - Добавьте `<meta name="csrf-token">` в `<head>`

3. **"API endpoint not found"**
   - Проверьте, что backend API реализован
   - Убедитесь в правильности URL (`/assets/components/testsystem/ajax/testsystem.php`)

---

## Дополнительные настройки

### Локализация

Все тексты на русском языке встроены в код. Для перевода измените строки напрямую в JS файлах.

### Кастомизация стилей

Переопределите стили в вашем собственном CSS файле после подключения `testsystem-extended.css`:

```css
/* custom.css */
.achievement-card {
    border-radius: 15px;
}

.level-badge {
    color: #your-color;
}
```

---

## Поддержка

Для вопросов и багов обращайтесь к разработчикам backend API системы.

**Дата создания:** 2025-11-17
**Версия:** 1.0
