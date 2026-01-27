# Техническое задание: Доработка страницы "Тесты"

**Дата:** 2026-01-27
**Версия:** 1.0
**Страница:** Тесты (ID: 35)
**Сниппет:** `[[!categoriesAndTests]]`

---

## 1. Общее описание

### 1.1 Цель доработки
Упростить и оптимизировать пользовательский интерфейс страницы "Тесты", убрав промежуточный экран выбора режима и предоставив прямой доступ к запуску тестов из списка.

### 1.2 Затрагиваемые компоненты
- `/core/elements/snippets/categoriesAndTests.php` - основной сниппет страницы
- `/core/elements/snippets/testRunner.php` - экран прохождения теста
- `/assets/components/testsystem/js/tsrunner.js` - клиентская логика
- `/assets/components/testsystem/css/testsystem-extended.css` - стили

---

## 2. Требования к доработке

### 2.1 Изменение отображения списка тестов по умолчанию

#### Текущее состояние:
При загрузке страницы `/tests` (без параметра `category`) в правой части отображается сообщение:
```
Выберите категорию
Выберите категорию слева, чтобы увидеть список тестов.
```

#### Требуемое состояние:
При загрузке страницы `/tests` (без параметра `category`) в правой части должны отображаться **все доступные тесты** из всех категорий.

#### Технические детали:
1. **SQL запрос:**
   ```sql
   SELECT t.id, t.title, t.description, t.mode,
          t.questions_per_session, t.pass_score,
          t.publication_status, t.created_by,
          c.name as category_name,
          COUNT(DISTINCT q.id) AS question_count
   FROM modx_test_tests t
   LEFT JOIN modx_test_questions q ON q.test_id = t.id AND q.published = 1
   LEFT JOIN modx_test_categories c ON c.id = t.category_id
   WHERE t.publication_status = 'public' AND t.is_active = 1
   GROUP BY t.id
   ORDER BY c.name ASC, t.title ASC
   ```

2. **Сортировка:**
   - Первичная сортировка: по названию категории (алфавитный порядок)
   - Вторичная сортировка: по названию теста (алфавитный порядок)

3. **Группировка по категориям:**
   - Тесты должны визуально группироваться по категориям
   - Над каждой группой отображается заголовок категории
   ```html
   <h3 class="category-header">Название категории</h3>
   <div class="tests-grid">
       <!-- Карточки тестов этой категории -->
   </div>
   ```

4. **Пагинация:**
   - Добавить пагинацию с отображением по 20 тестов на страницу
   - Параметр GET `page` для навигации
   - Показывать номера страниц и кнопки "Предыдущая"/"Следующая"

5. **Поиск:**
   - Добавить поле поиска над списком тестов
   - Поиск по названию теста (LIKE запрос)
   - Параметр GET `search` для сохранения состояния
   - Поиск работает как в режиме "Все тесты", так и при выборе конкретной категории

6. **Визуальное выделение выбранной категории:**
   - При выборе категории в левой панели - она подсвечивается
   - Добавить псевдо-категорию "Все тесты" в начало списка категорий
   - При переходе на `/tests` (без параметра) - "Все тесты" активна

---

### 2.2 Новая структура карточки теста

#### Текущая карточка:
```
┌─────────────────────────────────────┐
│ Название теста                      │
│ Описание теста...                   │
│ Вопросов в банке: 50                │
│ Вопросов за попытку: 20             │
│                                     │
│         [Начать тест]               │
└─────────────────────────────────────┘
```

#### Новая карточка:
```
┌─────────────────────────────────────┐
│ Название теста                    ⋮ │  <- Меню управления (справа вверху)
│ Описание теста...                   │
│                                     │
│ 📊 Вопросов в банке: 50             │
│ 📝 Вопросов за попытку: 20          │
│ ✅ Проходной балл: 75%              │
│                                     │
│ ─────────────────────────────────── │
│                                     │
│ Количество вопросов: [20] [Все]     │  <- Только для Тренировки
│                                     │
│ [    🎓 Тренировка    ]             │  <- Большая кнопка
│ [    🎯 Экзамен       ]             │  <- Большая кнопка
└─────────────────────────────────────┘
```

#### HTML структура:
```html
<div class="test-card" data-test-id="123">
    <!-- Заголовок с меню -->
    <div class="test-card-header">
        <h3 class="test-title">Название теста</h3>
        <div class="test-menu-toggle" data-test-id="123">⋮</div>

        <!-- Выпадающее меню управления -->
        <div class="test-menu-dropdown" id="menu-123" style="display: none;">
            <a href="#" class="menu-item" data-action="import" data-test-id="123">
                📥 Импорт вопросов
            </a>
            <a href="#" class="menu-item" data-action="questions" data-test-id="123">
                ❓ Управление вопросами
            </a>
            <a href="#" class="menu-item" data-action="manage" data-test-id="123">
                ⚙️ Настройки теста
            </a>
            <a href="#" class="menu-item menu-item-danger" data-action="delete" data-test-id="123">
                🗑️ Удалить тест
            </a>
        </div>
    </div>

    <!-- Описание -->
    <p class="test-description">Описание теста...</p>

    <!-- Метаинформация -->
    <div class="test-meta">
        <span class="meta-item">
            <span class="meta-icon">📊</span>
            <span class="meta-label">Вопросов в банке:</span>
            <span class="meta-value">50</span>
        </span>
        <span class="meta-item">
            <span class="meta-icon">📝</span>
            <span class="meta-label">Вопросов за попытку:</span>
            <span class="meta-value">20</span>
        </span>
        <span class="meta-item">
            <span class="meta-icon">✅</span>
            <span class="meta-label">Проходной балл:</span>
            <span class="meta-value">75%</span>
        </span>
    </div>

    <hr class="test-divider">

    <!-- Контролы запуска (только для режима Training) -->
    <div class="test-training-controls">
        <label for="questions-count-123">Количество вопросов:</label>
        <div class="questions-input-group">
            <input type="number"
                   id="questions-count-123"
                   class="questions-count-input"
                   min="1"
                   max="50"
                   value="20"
                   data-test-id="123">
            <button class="btn-all-questions" data-test-id="123">Все</button>
        </div>
    </div>

    <!-- Кнопки запуска -->
    <div class="test-action-buttons">
        <button class="btn-start-training btn-large" data-test-id="123">
            <span class="btn-icon">🎓</span>
            <span class="btn-text">Тренировка</span>
        </button>
        <button class="btn-start-exam btn-large" data-test-id="123">
            <span class="btn-icon">🎯</span>
            <span class="btn-text">Экзамен</span>
        </button>
    </div>
</div>
```

#### CSS классы:
```css
.test-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    background: #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: relative;
}

.test-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    position: relative;
}

.test-title {
    margin: 0;
    font-size: 1.4em;
    font-weight: 600;
    flex: 1;
}

.test-menu-toggle {
    cursor: pointer;
    font-size: 24px;
    padding: 0 8px;
    user-select: none;
    color: #666;
}

.test-menu-toggle:hover {
    color: #333;
}

.test-menu-dropdown {
    position: absolute;
    top: 30px;
    right: 0;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 100;
    min-width: 220px;
}

.menu-item {
    display: block;
    padding: 12px 16px;
    text-decoration: none;
    color: #333;
    transition: background 0.2s;
}

.menu-item:hover {
    background: #f5f5f5;
}

.menu-item-danger {
    color: #d32f2f;
}

.menu-item-danger:hover {
    background: #ffebee;
}

.test-description {
    color: #666;
    margin-bottom: 16px;
    line-height: 1.5;
}

.test-meta {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 16px;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95em;
}

.meta-icon {
    font-size: 1.1em;
}

.meta-label {
    color: #666;
}

.meta-value {
    font-weight: 600;
    color: #333;
}

.test-divider {
    border: 0;
    border-top: 1px solid #e0e0e0;
    margin: 16px 0;
}

.test-training-controls {
    margin-bottom: 16px;
}

.test-training-controls label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.questions-input-group {
    display: flex;
    gap: 8px;
}

.questions-count-input {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1em;
}

.btn-all-questions {
    padding: 10px 20px;
    background: #f5f5f5;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
}

.btn-all-questions:hover {
    background: #e0e0e0;
}

.test-action-buttons {
    display: flex;
    gap: 12px;
}

.btn-large {
    flex: 1;
    padding: 16px;
    font-size: 1.1em;
    font-weight: 600;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s;
}

.btn-start-training {
    background: #4caf50;
    color: white;
}

.btn-start-training:hover {
    background: #45a049;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(76, 175, 80, 0.3);
}

.btn-start-exam {
    background: #2196f3;
    color: white;
}

.btn-start-exam:hover {
    background: #1976d2;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(33, 150, 243, 0.3);
}

.btn-icon {
    font-size: 1.2em;
}

/* Адаптивность для мобильных */
@media (max-width: 768px) {
    .test-action-buttons {
        flex-direction: column;
    }

    .test-meta {
        font-size: 0.9em;
    }
}
```

---

### 2.3 Выпадающее меню управления тестом

#### Функциональность:
1. **Кнопка "⋮" (три точки)** в правом верхнем углу карточки
2. При клике открывается выпадающее меню с опциями
3. Клик вне меню - закрывает его
4. Отображение кнопок зависит от прав доступа пользователя

#### Элементы меню:
| Кнопка | Действие | Доступ |
|--------|----------|---------|
| 📥 Импорт вопросов | Переход на страницу импорта CSV для этого теста | Владелец теста, Эксперты, Админы |
| ❓ Управление вопросами | Переход на страницу списка вопросов теста | Владелец теста, Эксперты, Админы |
| ⚙️ Настройки теста | Переход на страницу редактирования теста | Владелец теста, Эксперты, Админы |
| 🗑️ Удалить тест | Удаление теста с подтверждением | Владелец теста, Админы |

#### Логика проверки прав доступа (PHP):
```php
// В categoriesAndTests.php
$currentUserId = $modx->user->get('id');
$userGroups = $modx->user->getUserGroups(); // Массив ID групп

$isAdmin = in_array($configHelper->getUserGroupId('admins'), $userGroups);
$isExpert = in_array($configHelper->getUserGroupId('experts'), $userGroups);
$isOwner = ($test['created_by'] == $currentUserId);

// Право на управление тестом
$canManageTest = $isAdmin || $isExpert || $isOwner;

// Право на удаление теста
$canDeleteTest = $isAdmin || $isOwner;

// Передаем в шаблон
$testData = [
    'id' => $test['id'],
    'title' => $test['title'],
    // ...
    'canManageTest' => $canManageTest,
    'canDeleteTest' => $canDeleteTest,
];
```

#### JavaScript логика (новый файл):
Создать файл: `/assets/components/testsystem/js/test-cards.js`

```javascript
// Управление меню карточек тестов
(function() {
    'use strict';

    // Открытие/закрытие меню
    document.addEventListener('click', function(e) {
        const toggle = e.target.closest('.test-menu-toggle');

        if (toggle) {
            e.stopPropagation();
            const testId = toggle.dataset.testId;
            const menu = document.getElementById('menu-' + testId);

            // Закрыть все остальные меню
            document.querySelectorAll('.test-menu-dropdown').forEach(m => {
                if (m !== menu) m.style.display = 'none';
            });

            // Переключить текущее меню
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        } else {
            // Клик вне меню - закрыть все
            if (!e.target.closest('.test-menu-dropdown')) {
                document.querySelectorAll('.test-menu-dropdown').forEach(m => {
                    m.style.display = 'none';
                });
            }
        }
    });

    // Обработка действий меню
    document.addEventListener('click', function(e) {
        const menuItem = e.target.closest('.menu-item');
        if (!menuItem) return;

        e.preventDefault();
        const action = menuItem.dataset.action;
        const testId = menuItem.dataset.testId;

        switch(action) {
            case 'import':
                window.location.href = '/import-csv?testId=' + testId;
                break;
            case 'questions':
                window.location.href = '/my-tests?action=questions&testId=' + testId;
                break;
            case 'manage':
                window.location.href = '/my-tests?action=edit&testId=' + testId;
                break;
            case 'delete':
                handleDeleteTest(testId);
                break;
        }
    });

    // Удаление теста
    function handleDeleteTest(testId) {
        if (!confirm('Вы уверены, что хотите удалить этот тест? Это действие нельзя отменить.')) {
            return;
        }

        fetch('/assets/components/testsystem/ajax/testsystem.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'deleteTest',
                data: { test_id: testId }
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Тест успешно удален');
                location.reload();
            } else {
                alert('Ошибка при удалении теста: ' + (data.message || 'Неизвестная ошибка'));
            }
        })
        .catch(error => {
            alert('Ошибка сети: ' + error.message);
        });
    }

    // Кнопка "Все вопросы"
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-all-questions');
        if (!btn) return;

        const testId = btn.dataset.testId;
        const input = document.getElementById('questions-count-' + testId);
        const maxValue = input.getAttribute('max');
        input.value = maxValue;
    });

})();
```

---

### 2.4 Запуск теста напрямую из карточки

#### Текущая логика:
1. Клик "Начать тест" → Переход на `/test?testId=123`
2. Загружается `testRunner.php` с промежуточным экраном
3. Пользователь выбирает режим (Training/Exam)
4. Нажимает кнопку "Начать"
5. JavaScript вызывает API `startSession`

#### Новая логика:
1. Клик "Тренировка" или "Экзамен" → JavaScript перехватывает событие
2. **Без перезагрузки страницы** вызывается API `startSession`
3. При успехе → редирект на `/test?sessionId=456` (сразу к прохождению)
4. `testRunner.php` определяет, что есть `sessionId` → пропускает экран выбора режима

#### JavaScript (добавить в test-cards.js):
```javascript
// Запуск тренировки
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-start-training');
    if (!btn) return;

    e.preventDefault();
    const testId = btn.dataset.testId;
    const questionsInput = document.getElementById('questions-count-' + testId);
    const questionsCount = parseInt(questionsInput.value) || 20;

    startTestSession(testId, 'training', questionsCount);
});

// Запуск экзамена
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-start-exam');
    if (!btn) return;

    e.preventDefault();
    const testId = btn.dataset.testId;

    startTestSession(testId, 'exam', null);
});

// Универсальная функция запуска сессии
async function startTestSession(testId, mode, questionsCount) {
    // Показать индикатор загрузки
    showLoadingIndicator();

    try {
        const response = await fetch('/assets/components/testsystem/ajax/testsystem.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'startSession',
                data: {
                    test_id: testId,
                    mode: mode,
                    questions_count: questionsCount
                }
            })
        });

        const data = await response.json();

        if (data.success) {
            // Редирект на страницу теста с sessionId
            const sessionId = data.data.session_id;
            window.location.href = '/test?sessionId=' + sessionId;
        } else {
            alert('Ошибка при запуске теста: ' + (data.message || 'Неизвестная ошибка'));
            hideLoadingIndicator();
        }
    } catch (error) {
        alert('Ошибка сети: ' + error.message);
        hideLoadingIndicator();
    }
}

function showLoadingIndicator() {
    // Создать overlay с индикатором загрузки
    const overlay = document.createElement('div');
    overlay.id = 'loading-overlay';
    overlay.innerHTML = '<div class="spinner">Загрузка теста...</div>';
    document.body.appendChild(overlay);
}

function hideLoadingIndicator() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) overlay.remove();
}
```

#### Модификация testRunner.php:
```php
// В начале файла testRunner.php
$sessionId = isset($_GET['sessionId']) ? intval($_GET['sessionId']) : 0;
$testId = isset($_GET['testId']) ? intval($_GET['testId']) : 0;

if ($sessionId > 0) {
    // Есть sessionId - сразу загружаем интерфейс прохождения теста
    // Пропускаем экран выбора режима
    $skipModeSelection = true;

    // Получаем данные сессии
    $session = $modx->getObject('TestSession', $sessionId);
    if (!$session) {
        return 'Сессия не найдена';
    }

    // Проверка прав доступа к сессии
    if ($session->get('user_id') != $modx->user->get('id')) {
        return 'Нет доступа к этой сессии';
    }

    $testId = $session->get('test_id');
    $mode = $session->get('mode');

    // Загружаем данные теста
    $test = $modx->getObject('TestTest', $testId);
    // ...

} elseif ($testId > 0) {
    // Есть только testId - показываем упрощенный интерфейс выбора режима
    $skipModeSelection = false;
    // ... текущая логика
}

// В шаблоне:
if ($skipModeSelection) {
    // Сразу рендерим интерфейс прохождения теста
    echo $tpl->render('test-runner-interface.tpl', $testData);
} else {
    // Показываем упрощенный выбор режима (для прямых ссылок)
    echo $tpl->render('test-mode-selection-simple.tpl', $testData);
}
```

---

### 2.5 Упрощенный экран выбора режима (для прямых ссылок)

Когда пользователь переходит напрямую по ссылке `/test?testId=123` (не через страницу категорий), показывается упрощенный интерфейс:

#### HTML структура:
```html
<div class="test-start-page">
    <div class="test-info-card">
        <h1 class="test-title"><?= $test['title'] ?></h1>
        <p class="test-description"><?= $test['description'] ?></p>

        <div class="test-meta-grid">
            <div class="meta-item">
                <span class="meta-icon">📊</span>
                <span class="meta-label">Вопросов в банке:</span>
                <span class="meta-value"><?= $questionCount ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-icon">📝</span>
                <span class="meta-label">Вопросов за попытку:</span>
                <span class="meta-value"><?= $test['questions_per_session'] ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-icon">✅</span>
                <span class="meta-label">Проходной балл:</span>
                <span class="meta-value"><?= $test['pass_score'] ?>%</span>
            </div>
        </div>

        <hr class="divider">

        <!-- Контролы запуска (аналогично карточке) -->
        <div class="test-training-controls">
            <label for="questions-count">Количество вопросов:</label>
            <div class="questions-input-group">
                <input type="number"
                       id="questions-count"
                       class="questions-count-input"
                       min="1"
                       max="<?= $questionCount ?>"
                       value="20">
                <button class="btn-all-questions"
                        data-max="<?= $questionCount ?>">Все</button>
            </div>
        </div>

        <!-- Кнопки запуска -->
        <div class="test-action-buttons">
            <button class="btn-start-training btn-large"
                    data-test-id="<?= $testId ?>">
                <span class="btn-icon">🎓</span>
                <span class="btn-text">Тренировка</span>
            </button>
            <button class="btn-start-exam btn-large"
                    data-test-id="<?= $testId ?>">
                <span class="btn-icon">🎯</span>
                <span class="btn-text">Экзамен</span>
            </button>
        </div>

        <!-- Ссылка назад -->
        <div class="back-link">
            <a href="/tests">← Вернуться к списку тестов</a>
        </div>
    </div>
</div>
```

#### CSS для страницы запуска:
```css
.test-start-page {
    max-width: 800px;
    margin: 40px auto;
    padding: 20px;
}

.test-info-card {
    background: #fff;
    border-radius: 8px;
    padding: 40px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.test-info-card .test-title {
    font-size: 2em;
    margin-bottom: 16px;
    color: #333;
}

.test-info-card .test-description {
    font-size: 1.1em;
    color: #666;
    line-height: 1.6;
    margin-bottom: 24px;
}

.test-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.test-meta-grid .meta-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 12px;
    background: #f9f9f9;
    border-radius: 6px;
}

.back-link {
    text-align: center;
    margin-top: 24px;
}

.back-link a {
    color: #666;
    text-decoration: none;
    font-size: 0.95em;
}

.back-link a:hover {
    color: #333;
    text-decoration: underline;
}
```

---

### 2.6 Поиск и пагинация

#### Поиск:
```html
<div class="tests-search-bar">
    <input type="text"
           id="tests-search-input"
           class="search-input"
           placeholder="Поиск тестов по названию..."
           value="<?= htmlspecialchars($searchQuery) ?>">
    <button id="tests-search-btn" class="btn-search">🔍 Найти</button>
    <?php if ($searchQuery): ?>
        <a href="?" class="btn-clear-search">✖ Очистить</a>
    <?php endif; ?>
</div>
```

#### JavaScript поиска:
```javascript
// Поиск тестов
document.getElementById('tests-search-btn')?.addEventListener('click', function() {
    const query = document.getElementById('tests-search-input').value.trim();
    if (query) {
        const url = new URL(window.location);
        url.searchParams.set('search', query);
        url.searchParams.delete('page'); // Сбросить на первую страницу
        window.location.href = url.toString();
    }
});

// Enter для поиска
document.getElementById('tests-search-input')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        document.getElementById('tests-search-btn').click();
    }
});
```

#### PHP логика поиска:
```php
// В categoriesAndTests.php
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// SQL с поиском
$where = "t.publication_status = 'public' AND t.is_active = 1";
if ($searchQuery) {
    $searchEscaped = $modx->quote('%' . $searchQuery . '%');
    $where .= " AND t.title LIKE {$searchEscaped}";
}

// Подсчет общего количества
$totalQuery = "SELECT COUNT(DISTINCT t.id) as total
               FROM modx_test_tests t
               WHERE {$where}";
$totalResult = $modx->query($totalQuery);
$totalTests = $totalResult->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalTests / $perPage);

// Получение тестов с пагинацией
$query = "SELECT t.*, c.name as category_name, COUNT(DISTINCT q.id) AS question_count
          FROM modx_test_tests t
          LEFT JOIN modx_test_questions q ON q.test_id = t.id AND q.published = 1
          LEFT JOIN modx_test_categories c ON c.id = t.category_id
          WHERE {$where}
          GROUP BY t.id
          ORDER BY c.name ASC, t.title ASC
          LIMIT {$perPage} OFFSET {$offset}";
```

#### HTML пагинации:
```html
<?php if ($totalPages > 1): ?>
<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>"
           class="pagination-btn">← Предыдущая</a>
    <?php endif; ?>

    <div class="pagination-numbers">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
                <span class="pagination-number active"><?= $i ?></span>
            <?php else: ?>
                <a href="?page=<?= $i ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>"
                   class="pagination-number"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>

    <?php if ($page < $totalPages): ?>
        <a href="?page=<?= $page + 1 ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>"
           class="pagination-btn">Следующая →</a>
    <?php endif; ?>
</div>
<?php endif; ?>
```

---

## 3. Структура файлов

### 3.1 Файлы для модификации:
```
/core/elements/snippets/
├── categoriesAndTests.php          [MODIFY] Основной сниппет
└── testRunner.php                  [MODIFY] Экран теста

/assets/components/testsystem/js/
├── test-cards.js                   [CREATE] Новый файл для карточек
└── tsrunner.js                     [MODIFY] Поддержка sessionId

/assets/components/testsystem/css/
└── testsystem-extended.css         [MODIFY] Стили для новых элементов

/core/components/testsystem/templates/
├── test-card.tpl                   [CREATE] Шаблон карточки теста
├── test-runner-interface.tpl       [CREATE] Интерфейс прохождения
└── test-mode-selection-simple.tpl  [CREATE] Упрощенный выбор режима
```

### 3.2 API endpoints (без изменений):
- `startSession` - запуск новой сессии
- `getNextQuestion` - получение вопроса
- `submitAnswer` - отправка ответа
- `finishTest` - завершение теста

---

## 4. Права доступа

### 4.1 Матрица доступа к кнопкам управления:

| Роль | Импорт | Вопросы | Настройки | Удалить |
|------|--------|---------|-----------|---------|
| **Админ** | ✅ Все тесты | ✅ Все тесты | ✅ Все тесты | ✅ Все тесты |
| **Эксперт** | ✅ Свои тесты | ✅ Свои тесты | ✅ Свои тесты | ❌ |
| **Владелец теста** | ✅ Свой тест | ✅ Свой тест | ✅ Свой тест | ✅ Свой тест |
| **Студент** | ❌ | ❌ | ❌ | ❌ |

### 4.2 Проверка прав (PHP):
```php
// Получение роли пользователя
$configHelper = new \TestSystem\Helpers\Config($modx);
$userGroups = $modx->user->getUserGroups();

$roles = [
    'isAdmin' => in_array($configHelper->getUserGroupId('admins'), $userGroups),
    'isExpert' => in_array($configHelper->getUserGroupId('experts'), $userGroups),
    'isOwner' => ($test['created_by'] == $modx->user->get('id')),
];

// Права
$permissions = [
    'canImport' => $roles['isAdmin'] || $roles['isExpert'] || $roles['isOwner'],
    'canManageQuestions' => $roles['isAdmin'] || $roles['isExpert'] || $roles['isOwner'],
    'canEditSettings' => $roles['isAdmin'] || $roles['isExpert'] || $roles['isOwner'],
    'canDelete' => $roles['isAdmin'] || $roles['isOwner'],
];
```

---

## 5. Тестирование

### 5.1 Функциональные тесты:

#### Тест 1: Отображение всех тестов по умолчанию
- **Шаги:**
  1. Открыть `/tests` (без параметров)
  2. Проверить, что отображаются все публичные тесты
  3. Проверить группировку по категориям
- **Ожидаемый результат:** Все тесты сгруппированы по категориям

#### Тест 2: Выбор категории
- **Шаги:**
  1. Кликнуть на категорию в левой панели
  2. Проверить, что URL изменился на `/tests?category=X`
  3. Проверить, что отображаются только тесты выбранной категории
- **Ожидаемый результат:** Фильтрация работает

#### Тест 3: Запуск тренировки
- **Шаги:**
  1. Установить количество вопросов (например, 10)
  2. Кликнуть "Тренировка"
  3. Проверить, что создалась сессия
  4. Проверить, что открылся первый вопрос
- **Ожидаемый результат:** Тест запущен в режиме тренировки с 10 вопросами

#### Тест 4: Запуск экзамена
- **Шаги:**
  1. Кликнуть "Экзамен"
  2. Проверить, что создалась сессия
  3. Проверить, что открылся первый вопрос
  4. Проверить, что используются все вопросы теста
- **Ожидаемый результат:** Тест запущен в режиме экзамена

#### Тест 5: Кнопка "Все"
- **Шаги:**
  1. Кликнуть кнопку "Все"
  2. Проверить, что input заполнился максимальным значением
- **Ожидаемый результат:** Поле содержит максимальное количество вопросов

#### Тест 6: Выпадающее меню (Админ)
- **Шаги:**
  1. Войти как админ
  2. Кликнуть "⋮" на любой карточке
  3. Проверить, что отображаются все 4 кнопки
- **Ожидаемый результат:** Все кнопки видны

#### Тест 7: Выпадающее меню (Студент)
- **Шаги:**
  1. Войти как студент
  2. Кликнуть "⋮" на карточке
  3. Проверить, что меню не отображается или пустое
- **Ожидаемый результат:** Нет кнопок управления

#### Тест 8: Прямая ссылка на тест
- **Шаги:**
  1. Открыть `/test?testId=123`
  2. Проверить, что отображается упрощенный интерфейс
  3. Запустить тренировку
- **Ожидаемый результат:** Тест запускается корректно

#### Тест 9: Поиск
- **Шаги:**
  1. Ввести название теста в поле поиска
  2. Нажать "Найти"
  3. Проверить, что отображаются только подходящие тесты
- **Ожидаемый результат:** Фильтрация работает

#### Тест 10: Пагинация
- **Шаги:**
  1. Убедиться, что тестов > 20
  2. Проверить, что отображается пагинация
  3. Перейти на вторую страницу
- **Ожидаемый результат:** Отображаются следующие 20 тестов

### 5.2 Проверка безопасности:
- [ ] Проверка прав доступа к API `startSession`
- [ ] Проверка прав доступа к кнопкам управления
- [ ] Защита от XSS в поле поиска
- [ ] Защита от SQL injection в запросах
- [ ] CSRF токены для действий удаления

### 5.3 Тестирование производительности:
- [ ] Загрузка страницы с 100+ тестами
- [ ] Время отклика API `startSession`
- [ ] Работа с кешем категорий

### 5.4 Кросс-браузерное тестирование:
- [ ] Chrome (последняя версия)
- [ ] Firefox (последняя версия)
- [ ] Safari (последняя версия)
- [ ] Edge (последняя версия)
- [ ] Мобильные браузеры (iOS Safari, Chrome Mobile)

---

## 6. План внедрения

### Этап 1: Бэкенд (categoriesAndTests.php)
**Время:** 4 часа

1. Модификация SQL запросов для отображения всех тестов
2. Добавление логики поиска
3. Реализация пагинации
4. Проверка прав доступа для кнопок управления
5. Генерация новой HTML структуры карточек

### Этап 2: Фронтенд (test-cards.js, CSS)
**Время:** 4 часа

1. Создание файла `test-cards.js`
2. Реализация выпадающего меню
3. Обработка кликов на кнопки "Тренировка" и "Экзамен"
4. Вызов API `startSession` без перезагрузки
5. Добавление индикатора загрузки
6. Стили для новых элементов

### Этап 3: testRunner.php
**Время:** 3 часа

1. Добавление поддержки параметра `sessionId`
2. Пропуск экрана выбора режима при наличии sessionId
3. Создание упрощенного интерфейса для прямых ссылок
4. Рефакторинг существующей логики

### Этап 4: Тестирование
**Время:** 3 часа

1. Функциональное тестирование (все 10 тестов)
2. Проверка безопасности
3. Кросс-браузерное тестирование
4. Тестирование на мобильных устройствах

### Этап 5: Документация и деплой
**Время:** 2 часа

1. Обновление документации
2. Создание миграций (если нужно)
3. Деплой на staging
4. Финальное тестирование
5. Деплой на production

**Общее время:** ~16 часов (2 рабочих дня)

---

## 7. Возможные риски и решения

| Риск | Вероятность | Решение |
|------|-------------|---------|
| Конфликт с существующим JS кодом | Средняя | Использовать namespace, тщательное тестирование |
| Проблемы с производительностью при большом количестве тестов | Низкая | Пагинация, кеширование, индексы БД |
| Проблемы с мобильной версией | Средняя | Адаптивный дизайн, тестирование на реальных устройствах |
| Нарушение прав доступа | Низкая | Двойная проверка (фронтенд + бэкенд) |
| Проблемы с обратной совместимостью | Низкая | Сохранение старых URL, редиректы |

---

## 8. Критерии приемки

### 8.1 Обязательные требования:
- ✅ По умолчанию отображаются все тесты (без выбора категории)
- ✅ Тесты сгруппированы по категориям
- ✅ Поиск работает корректно
- ✅ Пагинация работает корректно
- ✅ Карточки тестов содержат все необходимые элементы
- ✅ Выпадающее меню управления работает
- ✅ Права доступа к кнопкам управления соблюдаются
- ✅ Запуск теста происходит без промежуточного экрана
- ✅ Режим "Тренировка" позволяет выбрать количество вопросов
- ✅ Режим "Экзамен" использует все вопросы теста
- ✅ Прямые ссылки на тесты работают с упрощенным интерфейсом
- ✅ Мобильная версия работает корректно

### 8.2 Дополнительные требования:
- ✅ Код соответствует существующим стандартам проекта
- ✅ Все функции документированы
- ✅ Пройдены все тесты из раздела 5
- ✅ Нет регрессий в существующей функциональности

---

## 9. Примечания

### 9.1 Совместимость с существующими функциями:
- Геймификация (начисление XP) - без изменений
- Статистика прохождения - без изменений
- Сертификаты - без изменений
- Области знаний - без изменений
- Типы вопросов - без изменений

### 9.2 Будущие улучшения (вне текущего ТЗ):
- Фильтры по уровню сложности
- Сортировка (по популярности, рейтингу, дате)
- Избранные тесты
- История прохождений на карточке
- Рекомендуемые тесты
- Социальные функции (поделиться, комментарии)

---

## 10. Контакты и утверждение

**Автор ТЗ:** Claude Code
**Дата создания:** 2026-01-27
**Версия:** 1.0

**Согласовано:**
- [ ] Заказчик
- [ ] Технический директор
- [ ] Ведущий разработчик

**Готовность к разработке:** ⬜ Да / ⬜ Нет

---

_Конец документа_