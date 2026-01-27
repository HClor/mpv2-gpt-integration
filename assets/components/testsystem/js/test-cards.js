/**
 * Test Cards - Управление карточками тестов
 * Обработка выпадающих меню, запуск тестов, кнопка "Все"
 *
 * @package TestSystem
 * @version 2.0
 */

(function() {
    'use strict';

    const API_URL = '/assets/components/testsystem/ajax/testsystem.php';

    // ============================================
    // Управление выпадающим меню
    // ============================================

    document.addEventListener('click', function(e) {
        const toggle = e.target.closest('.test-menu-toggle');

        if (toggle) {
            e.stopPropagation();
            const testId = toggle.dataset.testId;
            const menu = document.getElementById('menu-' + testId);

            if (!menu) return;

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

    // ============================================
    // Обработка действий меню
    // ============================================

    document.addEventListener('click', function(e) {
        const menuItem = e.target.closest('.menu-item');
        if (!menuItem) return;

        const action = menuItem.dataset.action;

        if (action === 'delete') {
            e.preventDefault();
            const testId = menuItem.dataset.testId;
            handleDeleteTest(testId);
        }
        // Остальные действия (импорт, вопросы, настройки) обрабатываются через href
    });

    /**
     * Удаление теста
     */
    function handleDeleteTest(testId) {
        if (!confirm('Вы уверены, что хотите удалить этот тест? Это действие нельзя отменить.')) {
            return;
        }

        showLoadingIndicator('Удаление теста...');

        fetch(API_URL, {
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
            hideLoadingIndicator();
            if (data.success) {
                alert('Тест успешно удален');
                location.reload();
            } else {
                alert('Ошибка при удалении теста: ' + (data.message || 'Неизвестная ошибка'));
            }
        })
        .catch(error => {
            hideLoadingIndicator();
            alert('Ошибка сети: ' + error.message);
        });
    }

    // ============================================
    // Кнопка "Все вопросы"
    // ============================================

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-all-questions');
        if (!btn) return;

        const testId = btn.dataset.testId;
        const maxValue = btn.dataset.max;
        const input = document.getElementById('questions-count-' + testId);

        if (input) {
            input.value = maxValue;
        }
    });

    // ============================================
    // Запуск тренировки
    // ============================================

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-start-training');
        if (!btn) return;

        e.preventDefault();
        const testId = btn.dataset.testId;
        const questionsInput = document.getElementById('questions-count-' + testId);
        const questionsCount = questionsInput ? parseInt(questionsInput.value) || 20 : 20;

        startTestSession(testId, 'training', questionsCount);
    });

    // ============================================
    // Запуск экзамена
    // ============================================

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-start-exam');
        if (!btn) return;

        e.preventDefault();
        const testId = btn.dataset.testId;

        startTestSession(testId, 'exam', null);
    });

    // ============================================
    // Универсальная функция запуска сессии
    // ============================================

    async function startTestSession(testId, mode, questionsCount) {
        showLoadingIndicator('Запуск теста...');

        const requestData = {
            action: 'startSession',
            data: {
                test_id: testId,
                mode: mode
            }
        };

        if (mode === 'training' && questionsCount) {
            requestData.data.questions_count = questionsCount;
        }

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            });

            const data = await response.json();

            if (data.success) {
                // Редирект на страницу теста с sessionId
                const sessionId = data.data.session_id;
                window.location.href = '/test?sessionId=' + sessionId;
            } else {
                hideLoadingIndicator();
                alert('Ошибка при запуске теста: ' + (data.message || 'Неизвестная ошибка'));
            }
        } catch (error) {
            hideLoadingIndicator();
            alert('Ошибка сети: ' + error.message);
        }
    }

    // ============================================
    // Индикатор загрузки
    // ============================================

    function showLoadingIndicator(message = 'Загрузка...') {
        // Удаляем старый overlay если есть
        hideLoadingIndicator();

        const overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        `;

        const spinner = document.createElement('div');
        spinner.className = 'spinner-container';
        spinner.style.cssText = `
            background: white;
            padding: 30px 40px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            text-align: center;
        `;

        spinner.innerHTML = `
            <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Загрузка...</span>
            </div>
            <div style="margin-top: 15px; font-size: 1.1em; color: #333;">${message}</div>
        `;

        overlay.appendChild(spinner);
        document.body.appendChild(overlay);
    }

    function hideLoadingIndicator() {
        const overlay = document.getElementById('loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    }

    // ============================================
    // Инициализация
    // ============================================

    console.log('Test Cards module initialized');

})();
