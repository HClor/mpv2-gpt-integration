/**
 * Test Cards - Управление карточками тестов
 * Обработка выпадающих меню, запуск тестов, кнопка "Все"
 * Редактирование вопросов, настройки теста - всё на странице /testy
 *
 * @package TestSystem
 * @version 3.0
 */

(function() {
    'use strict';

    const API_URL = '/assets/components/testsystem/ajax/testsystem.php';

    // Текущий testId для редактора вопросов
    let currentEditorTestId = null;
    let allQuestionsData = [];
    let currentFilter = 'all';
    let editorCanEdit = false;
    let editorCanDelete = false;
    let selectedQuestionIds = new Set();

    // ============================================
    // API Helper
    // ============================================

    async function apiCall(action, data) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) {
            data = data || {};
            data.csrf_token = csrfToken;
        }

        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: action, data: data })
        });

        return await response.json();
    }

    // ============================================
    // Утилиты
    // ============================================

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function htmlspecialchars(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function stripHtml(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || '';
    }

    function showNotification(message, type) {
        type = type || 'success';
        const notification = document.createElement('div');
        notification.className = 'alert alert-' + type + ' alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3';
        notification.style.zIndex = '9999';
        notification.style.minWidth = '300px';
        notification.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        document.body.appendChild(notification);
        setTimeout(function() { notification.remove(); }, 3000);
    }

    function getStatusName(status) {
        var names = {
            'draft': 'Черновик',
            'private': 'Приватный',
            'unlisted': 'По ссылке',
            'public': 'Публичный'
        };
        return names[status] || status;
    }

    // ============================================
    // Управление выпадающим меню
    // ============================================

    document.addEventListener('click', function(e) {
        var toggle = e.target.closest('.test-menu-toggle');

        if (toggle) {
            e.stopPropagation();
            var testId = toggle.dataset.testId;
            var menu = document.getElementById('menu-' + testId);

            if (!menu) return;

            // Закрыть все остальные меню
            document.querySelectorAll('.test-menu-dropdown').forEach(function(m) {
                if (m !== menu) m.style.display = 'none';
            });

            // Переключить текущее меню
            menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
        } else {
            // Клик вне меню - закрыть все
            if (!e.target.closest('.test-menu-dropdown')) {
                document.querySelectorAll('.test-menu-dropdown').forEach(function(m) {
                    m.style.display = 'none';
                });
            }
        }
    });

    // ============================================
    // Обработка действий меню
    // ============================================

    document.addEventListener('click', function(e) {
        var menuItem = e.target.closest('.menu-item');
        if (!menuItem) return;

        var action = menuItem.dataset.action;

        if (action === 'delete') {
            e.preventDefault();
            var testId = menuItem.dataset.testId;
            handleDeleteTest(testId);
        }
    });

    /**
     * Удаление теста
     */
    function handleDeleteTest(testId) {
        if (!confirm('Вы уверены, что хотите удалить этот тест? Это действие нельзя отменить.')) {
            return;
        }

        showLoadingIndicator('Удаление теста...');

        apiCall('deleteTest', { test_id: testId })
            .then(function(data) {
                hideLoadingIndicator();
                if (data.success) {
                    alert('Тест успешно удален');
                    location.reload();
                } else {
                    alert('Ошибка при удалении теста: ' + (data.message || 'Неизвестная ошибка'));
                }
            })
            .catch(function(error) {
                hideLoadingIndicator();
                alert('Ошибка сети: ' + error.message);
            });
    }

    // ============================================
    // Кнопка "Все вопросы"
    // ============================================

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-all-questions');
        if (!btn) return;

        var testId = btn.dataset.testId;
        var maxValue = btn.dataset.max;
        var input = document.getElementById('questions-count-' + testId);

        if (input) {
            input.value = maxValue;
        }
    });

    // ============================================
    // Запуск тренировки
    // ============================================

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-start-training');
        if (!btn) return;

        e.preventDefault();
        var testId = btn.dataset.testId;
        var questionsInput = document.getElementById('questions-count-' + testId);
        var questionsCount = questionsInput ? parseInt(questionsInput.value) || 20 : 20;

        startTestSession(testId, 'training', questionsCount);
    });

    // ============================================
    // Запуск экзамена
    // ============================================

    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-start-exam');
        if (!btn) return;

        e.preventDefault();
        var testId = btn.dataset.testId;

        startTestSession(testId, 'exam', null);
    });

    // ============================================
    // Универсальная функция запуска сессии
    // ============================================

    async function startTestSession(testId, mode, questionsCount) {
        showLoadingIndicator('Запуск теста...');

        var requestData = {
            test_id: testId,
            mode: mode
        };

        if (mode === 'training' && questionsCount) {
            requestData.questions_count = questionsCount;
        }

        try {
            var data = await apiCall('startSession', requestData);

            if (data.success) {
                var sessionId = data.data.session_id;
                window.location.href = '/test-run?sessionId=' + sessionId;
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

    function showLoadingIndicator(message) {
        message = message || 'Загрузка...';
        hideLoadingIndicator();

        var overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;';

        var spinner = document.createElement('div');
        spinner.style.cssText = 'background:white;padding:30px 40px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);text-align:center;';
        spinner.innerHTML = '<div class="spinner-border text-primary" role="status" style="width:3rem;height:3rem;"><span class="visually-hidden">Загрузка...</span></div><div style="margin-top:15px;font-size:1.1em;color:#333;">' + escapeHtml(message) + '</div>';

        overlay.appendChild(spinner);
        document.body.appendChild(overlay);
    }

    function hideLoadingIndicator() {
        var overlay = document.getElementById('loading-overlay');
        if (overlay) overlay.remove();
    }

    // ============================================
    // ПЕРЕКЛЮЧЕНИЕ ВИДОВ: Список тестов <-> Редактор вопросов
    // ============================================

    function showQuestionsEditor(testId) {
        currentEditorTestId = testId;
        document.getElementById('categories-tests-view').style.display = 'none';
        document.getElementById('questions-editor-view').style.display = 'block';
        updateEditorImportButton(testId);
        loadQuestionsForEditor(testId, 'all');

        // Закрываем меню
        document.querySelectorAll('.test-menu-dropdown').forEach(function(m) {
            m.style.display = 'none';
        });
    }
    window.showQuestionsEditor = showQuestionsEditor;

    function updateEditorImportButton(testId) {
        var importButton = document.getElementById('editor-import-questions-btn');
        if (!importButton) return;

        var container = document.getElementById('tests-page-container');
        var importBaseUrl = container ? (container.dataset.importBaseUrl || '') : '';
        if (!importBaseUrl || !testId) {
            importButton.style.display = 'none';
            importButton.setAttribute('href', '#');
            return;
        }

        var separator = importBaseUrl.indexOf('?') !== -1 ? '&' : '?';
        importButton.setAttribute('href', importBaseUrl + separator + 'test_id=' + encodeURIComponent(testId));
        importButton.style.display = '';
    }

    function backToTestsList() {
        document.getElementById('questions-editor-view').style.display = 'none';
        document.getElementById('categories-tests-view').style.display = 'block';
        updateEditorImportButton(null);
        currentEditorTestId = null;
        allQuestionsData = [];
        selectedQuestionIds.clear();
    }
    window.backToTestsList = backToTestsList;


    async function checkEditorRights() {
        try {
            var result = await apiCall('checkEditRights', {});
            if (result && result.success && result.data) {
                editorCanEdit = !!result.data.canEdit;
                editorCanDelete = !!result.data.isAdmin;
            } else {
                editorCanEdit = false;
                editorCanDelete = false;
            }
        } catch (error) {
            editorCanEdit = false;
            editorCanDelete = false;
        }
    }

    // ============================================
    // ЗАГРУЗКА ВОПРОСОВ ДЛЯ РЕДАКТОРА
    // ============================================

    async function loadQuestionsForEditor(testId, filterStatus) {
        currentFilter = filterStatus || 'all';
        var listContent = document.getElementById('questions-list-content');
        listContent.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2">Загрузка вопросов...</p></div>';

        try {
            await checkEditorRights();

            if (!editorCanEdit) {
                listContent.innerHTML = '<div class="alert alert-warning">Недостаточно прав для редактирования вопросов в этом тесте</div>';
                return;
            }

            var result = await apiCall('getAllQuestions', { test_id: testId });

            if (!result.success) {
                listContent.innerHTML = '<div class="alert alert-danger">Ошибка загрузки вопросов: ' + escapeHtml(result.message) + '</div>';
                return;
            }

            allQuestionsData = result.data;
            renderQuestionsEditor(filterStatus);

        } catch (error) {
            listContent.innerHTML = '<div class="alert alert-danger">Ошибка сети: ' + escapeHtml(error.message) + '</div>';
        }
    }

    function renderQuestionsEditorHeader(sharedRenderer, stats, filterStatus) {
        var headerContainer = document.getElementById('questions-editor-header-content');
        if (!headerContainer) return;

        var controlsHtml = '';
        controlsHtml += '<button class="btn btn-outline-secondary btn-sm" onclick="backToTestsList()">';
        controlsHtml += '<i class="bi bi-arrow-left"></i> Назад к списку тестов';
        controlsHtml += '</button>';
        controlsHtml += '<button class="btn btn-success btn-sm" onclick="openAddQuestionModalFromTests()">';
        controlsHtml += '<i class="bi bi-plus-circle-fill"></i> Добавить вопрос';
        controlsHtml += '</button>';
        controlsHtml += '<a id="editor-import-questions-btn" class="btn btn-outline-success btn-sm" href="#" style="display:none;">';
        controlsHtml += '<i class="bi bi-file-earmark-arrow-down"></i> Импорт вопросов';
        controlsHtml += '</a>';
        controlsHtml += '<div class="d-flex gap-1 align-items-center">';
        controlsHtml += '<input type="number" id="editor-questions-count" class="form-control form-control-sm" style="width: 70px;" value="20" min="1" placeholder="20">';
        controlsHtml += '<button class="btn btn-outline-secondary btn-sm" style="white-space: nowrap;" id="editor-all-questions-btn">Все</button>';
        controlsHtml += '<button class="btn btn-primary btn-sm" style="white-space: nowrap;" onclick="startTestFromEditor(\'training\')"><i class="bi bi-play-circle"></i> Тренировка</button>';
        controlsHtml += '<button class="btn btn-danger btn-sm" style="white-space: nowrap;" onclick="startTestFromEditor(\'exam\')"><i class="bi bi-clipboard-check"></i> Экзамен</button>';
        controlsHtml += '</div>';

        var html = '';
        html += sharedRenderer.buildHeaderHtml({
            title: 'Список вопросов теста',
            controlsHtml: controlsHtml,
            titleClass: 'mb-3',
            controlsClass: 'd-flex flex-column flex-sm-row gap-2 w-100 align-items-stretch align-items-sm-center'
        });
        html += sharedRenderer.buildFiltersHtml(stats, filterStatus);
        headerContainer.innerHTML = html;

        updateEditorImportButton(currentEditorTestId);

        var allQuestionsBtn = document.getElementById('editor-all-questions-btn');
        if (allQuestionsBtn) {
            allQuestionsBtn.onclick = function() {
                var input = document.getElementById('editor-questions-count');
                if (input) input.value = stats.total;
            };
        }
    }

    function renderQuestionsEditor(filterStatus) {
        filterStatus = filterStatus || 'all';
        currentFilter = filterStatus;
        var listContent = document.getElementById('questions-list-content');
        var sharedRenderer = window.QuestionListShared && typeof window.QuestionListShared.requireSharedApi === 'function'
            ? window.QuestionListShared.requireSharedApi(['buildHeaderHtml', 'buildFiltersHtml', 'buildBulkPanelHtml', 'buildQuestionPreview', 'toggleSelectAllCheckboxes'])
            : null;
        if (!sharedRenderer) {
            listContent.innerHTML = '<div class="alert alert-danger">Ошибка: не загружен модуль QuestionListShared. Обновите страницу.</div>';
            return;
        }

        // Фильтрация
        var filteredQuestions = allQuestionsData;
        if (filterStatus === 'published') {
            filteredQuestions = allQuestionsData.filter(function(q) { return parseInt(q.published) === 1; });
        } else if (filterStatus === 'unpublished') {
            filteredQuestions = allQuestionsData.filter(function(q) { return parseInt(q.published) === 0; });
        } else if (filterStatus === 'learning') {
            filteredQuestions = allQuestionsData.filter(function(q) { return parseInt(q.is_learning) === 1; });
        }

        var totalCount = allQuestionsData.length;
        var publishedCount = allQuestionsData.filter(function(q) { return parseInt(q.published) === 1; }).length;
        var unpublishedCount = totalCount - publishedCount;
        var learningCount = allQuestionsData.filter(function(q) { return parseInt(q.is_learning) === 1; }).length;

        renderQuestionsEditorHeader(sharedRenderer, {
            total: totalCount,
            published: publishedCount,
            unpublished: unpublishedCount,
            learning: learningCount
        }, filterStatus);

        var existingIds = new Set(allQuestionsData.map(function(q) { return parseInt(q.id, 10); }));
        selectedQuestionIds = new Set(Array.from(selectedQuestionIds).filter(function(id) { return existingIds.has(id); }));

        if (filteredQuestions.length === 0) {
            listContent.innerHTML = '<div class="alert alert-warning">Нет вопросов для отображения</div>';
            return;
        }

        var html = '';
        var bulkActionsHtml = '';
        bulkActionsHtml += '<button class="btn btn-sm btn-outline-primary" onclick="toggleSelectAllQuestionsFromTests()"><i class="bi bi-check2-square"></i> Выбрать все</button>';
        bulkActionsHtml += '<button class="btn btn-sm btn-outline-success" onclick="bulkPublishQuestionsFromTests()"><i class="bi bi-eye"></i> Опубликовать</button>';
        bulkActionsHtml += '<button class="btn btn-sm btn-outline-warning" onclick="bulkUnpublishQuestionsFromTests()"><i class="bi bi-eye-slash"></i> Снять с публикации</button>';
        if (editorCanDelete) {
            bulkActionsHtml += '<button class="btn btn-sm btn-outline-danger" onclick="bulkDeleteQuestionsFromTests()"><i class="bi bi-trash"></i> Удалить</button>';
        }
        bulkActionsHtml += '<button class="btn btn-sm btn-outline-secondary" onclick="clearBulkSelectionFromTests()"><i class="bi bi-x-circle"></i> Сбросить</button>';

        html += sharedRenderer.buildBulkPanelHtml({
            selectedCount: selectedQuestionIds.size,
            actionsHtml: bulkActionsHtml
        }).replace(' mt-2 mb-0', ' mb-3');

        html += '<div class="list-group">';

        filteredQuestions.forEach(function(question) {
            var preview = sharedRenderer.buildQuestionPreview(question);
            var questionText = preview.shortText;
            var hasExplanation = preview.hasExplanation ? '✓' : '—';
            var isPublished = parseInt(question.published) === 1;
            var isLearning = parseInt(question.is_learning) === 1;
            var realIndex = allQuestionsData.findIndex(function(q) { return q.id === question.id; });
            var publishClass = isPublished ? '' : 'list-group-item-secondary';

            var questionData = htmlspecialchars(JSON.stringify({
                id: question.id,
                text: question.question_text,
                image: question.question_image,
                explanation: question.explanation,
                explanation_image: question.explanation_image,
                type: question.question_type,
                type_name: question.question_type === 'single' ? 'Один правильный ответ' : 'Несколько правильных ответов'
            }));

            html += '<div class="list-group-item ' + publishClass + ' question-list-item-minimal">';
            if (editorCanEdit) {
                var checked = selectedQuestionIds.has(parseInt(question.id, 10)) ? ' checked' : '';
                html += '<div class="mb-2"><label class="d-inline-flex align-items-center gap-2 small text-muted">';
                html += '<input type="checkbox" class="question-select-checkbox" value="' + question.id + '" onchange="toggleQuestionSelectionFromTests(this)"' + checked + '>';
                html += '<span>Выбрать</span></label></div>';
            }
            html += '<div class="d-flex flex-column gap-2">';

            // Текст вопроса
            html += '<div class="question-text-clickable-list" data-question=\'' + questionData + '\' onclick="openQuestionViewModalFromTests(this)">';
            html += '<div class="d-flex align-items-start gap-2">';
            html += '<span class="badge bg-primary flex-shrink-0" style="min-width: 36px; font-size: 0.9rem;">' + (realIndex + 1) + '</span>';
            html += '<div class="flex-grow-1">';
            html += '<p class="mb-1 fw-bold text-dark">' + escapeHtml(questionText) + (preview.isTrimmed ? '...' : '') + '</p>';
            html += '<small class="text-muted">';
            html += preview.questionTypeLabel + ' • ';
            html += 'Объяснение: ' + hasExplanation;
            html += '</small></div></div></div>';

            // Кнопки управления
            html += '<div class="question-actions-minimal">';

            var publishIcon = isPublished ? 'eye-slash' : 'eye';
            var publishTitle = isPublished ? 'Снять с публикации' : 'Опубликовать';
            var publishColor = isPublished ? 'warning' : 'success';

            html += '<button class="btn btn-sm btn-outline-' + publishColor + '" onclick="event.stopPropagation(); togglePublishedFromTests(' + question.id + ', ' + (isPublished ? 1 : 0) + ')" title="' + publishTitle + '">';
            html += '<i class="bi bi-' + publishIcon + '"></i></button>';

            var learningIcon = isLearning ? 'book-fill' : 'book';
            var learningTitle = isLearning ? 'Убрать из обучения' : 'Добавить в обучение';
            var learningBtnClass = isLearning ? 'btn-info' : 'btn-outline-secondary';

            html += '<button class="btn btn-sm ' + learningBtnClass + '" onclick="event.stopPropagation(); toggleLearningFromTests(' + question.id + ', ' + (isLearning ? 1 : 0) + ')" title="' + learningTitle + '">';
            html += '<i class="bi bi-' + learningIcon + '"></i></button>';

            html += '<button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); editQuestionFromTests(' + question.id + ')" title="Редактировать">';
            html += '<i class="bi bi-pencil"></i></button>';

            html += '<button class="btn btn-sm btn-outline-danger" onclick="event.stopPropagation(); deleteQuestionFromTests(' + question.id + ')" title="Удалить">';
            html += '<i class="bi bi-trash"></i></button>';

            html += '</div></div></div>';
        });

        html += '</div>';
        listContent.innerHTML = html;
    }

    // ============================================
    // Обработчик фильтров
    // ============================================

    document.addEventListener('click', function(e) {
        var filterBtn = e.target.closest('.questions-filter-btn');
        if (!filterBtn) return;

        var filter = filterBtn.dataset.filter;
        if (filter && currentEditorTestId) {
            renderQuestionsEditor(filter);
        }
    });


    function updateBulkActionsStateFromTests() {
        var panel = document.getElementById('bulk-actions-panel');
        var countEl = document.getElementById('selected-questions-count');
        var count = selectedQuestionIds.size;

        if (countEl) countEl.textContent = String(count);
        if (panel) {
            if (count > 0) panel.classList.remove('d-none');
            else panel.classList.add('d-none');
        }
    }

    function getSelectedQuestionIdsFromTests() {
        return Array.from(selectedQuestionIds);
    }

    let questionsEditorActions = null;

    function getQuestionsEditorActions() {
        if (questionsEditorActions) {
            return questionsEditorActions;
        }

        var sharedRenderer = window.QuestionListShared && typeof window.QuestionListShared.requireSharedApi === 'function'
            ? window.QuestionListShared.requireSharedApi(['createQuestionListActions', 'toggleSelectAllCheckboxes'])
            : null;
        if (!sharedRenderer) {
            throw new Error('QuestionListShared actions are not available');
        }

        questionsEditorActions = sharedRenderer.createQuestionListActions({
            getSelectedIds: getSelectedQuestionIdsFromTests,
            noSelectionMessage: 'Выберите хотя бы один вопрос',
            onSelectAll: function () {
                var checkboxes = sharedRenderer.toggleSelectAllCheckboxes('.question-select-checkbox');
                checkboxes.forEach(function(cb) {
                    var id = parseInt(cb.value, 10);
                    if (!id) return;
                    if (cb.checked) selectedQuestionIds.add(id);
                    else selectedQuestionIds.delete(id);
                });
                updateBulkActionsStateFromTests();
            },
            onClearSelection: function () {
                selectedQuestionIds.clear();
                document.querySelectorAll('.question-select-checkbox').forEach(function(cb) { cb.checked = false; });
                updateBulkActionsStateFromTests();
            },
            onPublish: function (selectedIds) {
                return bulkSetPublishedFromTests(1, selectedIds);
            },
            onUnpublish: function (selectedIds) {
                return bulkSetPublishedFromTests(0, selectedIds);
            },
            onDelete: function (selectedIds) {
                return bulkDeleteQuestionsByIdsFromTests(selectedIds);
            }
        });

        return questionsEditorActions;
    }

    function toggleQuestionSelectionFromTests(checkbox) {
        var id = parseInt(checkbox.value, 10);
        if (!id) return;
        if (checkbox.checked) selectedQuestionIds.add(id);
        else selectedQuestionIds.delete(id);
        updateBulkActionsStateFromTests();
    }
    window.toggleQuestionSelectionFromTests = toggleQuestionSelectionFromTests;

    function clearBulkSelectionFromTests() {
        try {
            getQuestionsEditorActions().clearSelection();
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }
    window.clearBulkSelectionFromTests = clearBulkSelectionFromTests;

    function toggleSelectAllQuestionsFromTests() {
        try {
            getQuestionsEditorActions().selectAll();
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }
    window.toggleSelectAllQuestionsFromTests = toggleSelectAllQuestionsFromTests;

    async function bulkSetPublishedFromTests(targetPublished, selectedIdsInput) {
        var selectedIds = selectedIdsInput || Array.from(selectedQuestionIds);
        if (selectedIds.length === 0) {
            alert('Выберите хотя бы один вопрос');
            return;
        }

        var selectedSet = new Set(selectedIds);
        var questionsToChange = allQuestionsData.filter(function(q) {
            var id = parseInt(q.id, 10);
            return selectedSet.has(id) && parseInt(q.published, 10) !== targetPublished;
        });

        if (questionsToChange.length === 0) {
            alert(targetPublished === 1 ? 'Все выбранные вопросы уже опубликованы' : 'Все выбранные вопросы уже сняты с публикации');
            return;
        }

        for (var i = 0; i < questionsToChange.length; i++) {
            var q = questionsToChange[i];
            var res = await apiCall('togglePublished', { question_id: q.id });
            if (!res.success) throw new Error(res.message || 'Ошибка при массовом изменении публикации');
        }

        showNotification('Обновлено вопросов: ' + questionsToChange.length, 'success');
        await loadQuestionsForEditor(currentEditorTestId, currentFilter);
    }

    async function bulkPublishQuestionsFromTests() {
        try { await getQuestionsEditorActions().publishSelected(); }
        catch (error) { alert('Ошибка: ' + error.message); }
    }
    window.bulkPublishQuestionsFromTests = bulkPublishQuestionsFromTests;

    async function bulkUnpublishQuestionsFromTests() {
        try { await getQuestionsEditorActions().unpublishSelected(); }
        catch (error) { alert('Ошибка: ' + error.message); }
    }
    window.bulkUnpublishQuestionsFromTests = bulkUnpublishQuestionsFromTests;

    async function bulkDeleteQuestionsByIdsFromTests(ids) {
        if (!editorCanDelete) {
            alert('Недостаточно прав для удаления вопросов');
            return;
        }

        if (ids.length === 0) {
            alert('Выберите хотя бы один вопрос');
            return;
        }

        if (!confirm('Удалить выбранные вопросы (' + ids.length + ' шт.)?')) return;

        for (var i = 0; i < ids.length; i++) {
            var result = await apiCall('deleteQuestion', { question_id: ids[i], session_id: 0 });
            if (!result.success) throw new Error(result.message || ('Ошибка удаления вопроса ID ' + ids[i]));
        }

        showNotification('Удалено вопросов: ' + ids.length, 'success');
        selectedQuestionIds.clear();
        await loadQuestionsForEditor(currentEditorTestId, currentFilter);
    }

    async function bulkDeleteQuestionsFromTests() {
        try { await getQuestionsEditorActions().deleteSelected(); }
        catch (error) { alert('Ошибка: ' + error.message); }
    }
    window.bulkDeleteQuestionsFromTests = bulkDeleteQuestionsFromTests;

    // ============================================
    // CRUD ВОПРОСОВ (для страницы /testy)
    // ============================================

    async function togglePublishedFromTests(questionId, currentStatus) {
        var action = currentStatus ? 'снят с публикации' : 'опубликован';
        try {
            var result = await apiCall('togglePublished', { question_id: questionId });
            if (result.success) {
                showNotification('Вопрос ' + action, 'success');
                await loadQuestionsForEditor(currentEditorTestId, currentFilter);
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }
    window.togglePublishedFromTests = togglePublishedFromTests;

    async function toggleLearningFromTests(questionId, currentStatus) {
        var action = currentStatus ? 'убран из обучения' : 'добавлен в обучение';
        try {
            var result = await apiCall('toggleLearning', { question_id: questionId });
            if (result.success) {
                showNotification('Вопрос ' + action, 'success');
                await loadQuestionsForEditor(currentEditorTestId, currentFilter);
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }
    window.toggleLearningFromTests = toggleLearningFromTests;

    async function editQuestionFromTests(questionId) {
        try {
            var result = await apiCall('getQuestion', { question_id: questionId });
            if (!result.success) {
                throw new Error(result.message || 'Не удалось загрузить вопрос');
            }
            openEditModalFromTests(result.data, questionId);
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }
    window.editQuestionFromTests = editQuestionFromTests;

    async function deleteQuestionFromTests(questionId) {
        if (!confirm('Вы уверены, что хотите удалить этот вопрос?')) {
            return;
        }
        try {
            var result = await apiCall('deleteQuestion', { question_id: questionId, session_id: 0 });
            if (result.success) {
                showNotification('Вопрос удален', 'success');
                await loadQuestionsForEditor(currentEditorTestId, currentFilter);
            } else {
                alert('Ошибка удаления: ' + result.message);
            }
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }
    window.deleteQuestionFromTests = deleteQuestionFromTests;

    // ============================================
    // Просмотр вопроса (модальное окно)
    // ============================================

    function openQuestionViewModalFromTests(element) {
        var questionData = JSON.parse(element.dataset.question);

        var sanitize = typeof DOMPurify !== 'undefined' ? function(html) { return DOMPurify.sanitize(html); } : escapeHtml;

        var html = '<div class="modal fade" id="questionViewModalTests" tabindex="-1" aria-hidden="true">';
        html += '<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">';
        html += '<div class="modal-content">';
        html += '<div class="modal-header bg-primary text-white">';
        html += '<h5 class="modal-title"><i class="bi bi-question-circle-fill"></i> ' + escapeHtml(questionData.type_name) + '</h5>';
        html += '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>';
        html += '</div>';
        html += '<div class="modal-body">';
        html += '<div class="mb-4">' + sanitize(questionData.text) + '</div>';
        if (questionData.image) {
            html += '<div class="mb-3"><img src="' + escapeHtml(questionData.image) + '" class="img-fluid border rounded"></div>';
        }
        if (questionData.explanation) {
            html += '<div class="alert alert-info"><strong>Объяснение:</strong><br>' + sanitize(questionData.explanation) + '</div>';
        }
        if (questionData.explanation_image) {
            html += '<div class="mb-3"><img src="' + escapeHtml(questionData.explanation_image) + '" class="img-fluid border rounded"></div>';
        }
        html += '</div>';
        html += '<div class="modal-footer">';
        html += '<button type="button" class="btn btn-primary" onclick="editQuestionFromTests(' + questionData.id + '); var m = bootstrap.Modal.getInstance(document.getElementById(\'questionViewModalTests\')); if(m) m.hide();">';
        html += '<i class="bi bi-pencil"></i> Редактировать</button>';
        html += '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>';
        html += '</div></div></div></div>';

        var oldModal = document.getElementById('questionViewModalTests');
        if (oldModal) oldModal.remove();

        document.body.insertAdjacentHTML('beforeend', html);
        var modal = new bootstrap.Modal(document.getElementById('questionViewModalTests'));
        modal.show();
    }
    window.openQuestionViewModalFromTests = openQuestionViewModalFromTests;

    // ============================================
    // Quill и загрузка изображений
    // ============================================

    function initQuillEditor(textareaId) {
        var textarea = document.getElementById(textareaId);
        if (!textarea) return null;

        var editorDiv = document.getElementById(textareaId + '-editor');
        if (!editorDiv) return null;

        var initialValue = textarea.value;

        var quill = new Quill('#' + editorDiv.id, {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: [
                        ['bold', 'italic', 'underline'],
                        ['link', 'code-block'],
                        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                        ['image'],
                        ['clean']
                    ],
                    handlers: {
                        image: function() {
                            selectLocalImageForQuill(quill);
                        }
                    }
                }
            },
            placeholder: 'Введите текст...'
        });

        quill.root.innerHTML = initialValue;

        quill.on('text-change', function() {
            textarea.value = quill.root.innerHTML;
        });

        return quill;
    }

    function selectLocalImageForQuill(quill) {
        var input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.setAttribute('accept', 'image/png, image/gif, image/jpeg, image/jpg, image/webp');
        input.click();

        input.onchange = async function() {
            var file = input.files[0];
            if (!file) return;

            if (file.size > 5 * 1024 * 1024) {
                alert('Файл слишком большой (макс. 5MB)');
                return;
            }

            var formData = new FormData();
            formData.append('image', file);

            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) formData.append('csrf_token', csrfToken);

            try {
                var range = quill.getSelection(true);
                quill.insertText(range.index, 'Загрузка...', 'user');

                var response = await fetch('/assets/components/testsystem/ajax/upload-image.php', {
                    method: 'POST',
                    body: formData
                });
                var result = await response.json();

                quill.deleteText(range.index, 11);

                if (result.success) {
                    quill.insertEmbed(range.index, 'image', result.url, 'user');
                    quill.insertText(range.index + 1, '\n', 'user');
                    quill.setSelection(range.index + 2);
                } else {
                    alert('Ошибка загрузки: ' + result.message);
                }
            } catch (error) {
                alert('Ошибка: ' + error.message);
            }
        };
    }

    function setupImageUpload(prefix) {
        var uploadBtn = document.getElementById('upload-' + prefix + '-btn');
        var fileInput = document.getElementById(prefix + '-input');
        var preview = document.getElementById(prefix + '-preview');
        var urlInput = document.getElementById(prefix + '-url');

        if (!uploadBtn || !fileInput) return;

        uploadBtn.onclick = function() { fileInput.click(); };

        fileInput.onchange = async function() {
            var file = fileInput.files[0];
            if (!file) return;

            if (file.size > 5 * 1024 * 1024) {
                alert('Файл слишком большой (макс. 5MB)');
                return;
            }

            var formData = new FormData();
            formData.append('image', file);

            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) formData.append('csrf_token', csrfToken);

            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Загрузка...';

            try {
                var response = await fetch('/assets/components/testsystem/ajax/upload-image.php', {
                    method: 'POST',
                    body: formData
                });
                var result = await response.json();

                if (result.success) {
                    urlInput.value = result.url;
                    preview.innerHTML = '<div><img src="' + result.url + '" class="img-fluid border" style="max-height: 200px;"><br><button type="button" class="btn btn-sm btn-danger mt-2" onclick="window.removeImageTC(\'' + prefix + '\')">Удалить</button></div>';
                } else {
                    alert('Ошибка загрузки: ' + result.message);
                }
            } catch (error) {
                alert('Ошибка: ' + error.message);
            } finally {
                uploadBtn.disabled = false;
                uploadBtn.innerHTML = '<i class="bi bi-upload"></i> Загрузить';
            }
        };
    }

    window.removeImageTC = function(prefix) {
        var preview = document.getElementById(prefix + '-preview');
        var urlInput = document.getElementById(prefix + '-url');
        if (preview) preview.innerHTML = '<small class="text-muted">Изображение не загружено</small>';
        if (urlInput) urlInput.value = '';
    };

    // ============================================
    // РЕДАКТИРОВАНИЕ ВОПРОСА (модальное окно)
    // ============================================

    function openEditModalFromTests(question, questionId) {
        var modalHtml = '<div class="modal fade" id="editQuestionModalTests" tabindex="-1">';
        modalHtml += '<div class="modal-dialog modal-xl"><div class="modal-content">';
        modalHtml += '<div class="modal-header"><h5 class="modal-title">Редактирование вопроса</h5>';
        modalHtml += '<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
        modalHtml += '<div class="modal-body">';

        // Текст вопроса с Quill
        modalHtml += '<div class="mb-3"><label class="form-label fw-bold">Текст вопроса</label>';
        modalHtml += '<div id="tc-edit-question-text-editor" style="min-height: 150px; background: white;"></div>';
        modalHtml += '<textarea id="tc-edit-question-text" style="display:none;">' + escapeHtml(question.question_text) + '</textarea></div>';

        // Изображение к вопросу
        modalHtml += '<div class="mb-3"><label class="form-label fw-bold">Изображение к вопросу</label>';
        modalHtml += '<div class="row g-3"><div class="col-md-4">';
        modalHtml += '<button type="button" class="btn btn-outline-primary w-100" id="upload-tc-question-image-btn"><i class="bi bi-upload"></i> Загрузить</button>';
        modalHtml += '<input type="file" id="tc-question-image-input" accept="image/*" style="display:none;">';
        modalHtml += '<input type="hidden" id="tc-question-image-url" value="' + (question.question_image || '') + '">';
        modalHtml += '</div><div class="col-md-8">';
        modalHtml += '<div id="tc-question-image-preview" class="border rounded p-2 bg-light" style="min-height: 80px;">';
        if (question.question_image) {
            modalHtml += '<div><img src="' + question.question_image + '" class="img-fluid" style="max-height: 150px;"><br><button type="button" class="btn btn-sm btn-danger mt-2" onclick="window.removeImageTC(\'tc-question-image\')">Удалить</button></div>';
        } else {
            modalHtml += '<small class="text-muted">Изображение не загружено</small>';
        }
        modalHtml += '</div></div></div></div>';

        // Тип вопроса
        modalHtml += '<div class="mb-3"><label class="form-label fw-bold">Тип вопроса</label>';
        modalHtml += '<select class="form-select" id="tc-edit-question-type">';
        modalHtml += '<option value="single"' + (question.question_type === 'single' ? ' selected' : '') + '>Один правильный ответ</option>';
        modalHtml += '<option value="multiple"' + (question.question_type === 'multiple' ? ' selected' : '') + '>Несколько правильных ответов</option>';
        modalHtml += '</select></div>';

        // Объяснение с Quill
        modalHtml += '<div class="mb-3"><label class="form-label fw-bold">Объяснение</label>';
        modalHtml += '<div id="tc-edit-explanation-editor" style="min-height: 150px; background: white;"></div>';
        modalHtml += '<textarea id="tc-edit-explanation" style="display:none;">' + (question.explanation || '') + '</textarea></div>';

        // Изображение к объяснению
        modalHtml += '<div class="mb-3"><label class="form-label fw-bold">Изображение к объяснению</label>';
        modalHtml += '<div class="row g-3"><div class="col-md-4">';
        modalHtml += '<button type="button" class="btn btn-outline-primary w-100" id="upload-tc-explanation-image-btn"><i class="bi bi-upload"></i> Загрузить</button>';
        modalHtml += '<input type="file" id="tc-explanation-image-input" accept="image/*" style="display:none;">';
        modalHtml += '<input type="hidden" id="tc-explanation-image-url" value="' + (question.explanation_image || '') + '">';
        modalHtml += '</div><div class="col-md-8">';
        modalHtml += '<div id="tc-explanation-image-preview" class="border rounded p-2 bg-light" style="min-height: 80px;">';
        if (question.explanation_image) {
            modalHtml += '<div><img src="' + question.explanation_image + '" class="img-fluid" style="max-height: 150px;"><br><button type="button" class="btn btn-sm btn-danger mt-2" onclick="window.removeImageTC(\'tc-explanation-image\')">Удалить</button></div>';
        } else {
            modalHtml += '<small class="text-muted">Изображение не загружено</small>';
        }
        modalHtml += '</div></div></div></div>';

        // Варианты ответов
        modalHtml += '<div class="mb-3"><label class="form-label fw-bold">Варианты ответов</label>';
        modalHtml += '<div id="tc-edit-answers-list">';
        question.answers.forEach(function(ans) {
            modalHtml += '<div class="input-group mb-2" data-answer-id="' + ans.id + '">';
            modalHtml += '<div class="input-group-text"><input type="checkbox" class="answer-correct-check"' + (ans.is_correct ? ' checked' : '') + '></div>';
            modalHtml += '<input type="text" class="form-control answer-text-input" value="' + escapeHtml(ans.answer_text) + '">';
            modalHtml += '</div>';
        });
        modalHtml += '</div></div>';

        modalHtml += '</div>'; // modal-body

        // Footer
        modalHtml += '<div class="modal-footer d-flex justify-content-between align-items-center">';
        modalHtml += '<div class="d-flex gap-4">';
        modalHtml += '<div class="form-check form-switch">';
        modalHtml += '<input class="form-check-input" type="checkbox" id="tc-edit-question-published"' + (question.published == 1 ? ' checked' : '') + ' style="width: 3em; height: 1.5em;">';
        modalHtml += '<label class="form-check-label ms-2 fw-bold" for="tc-edit-question-published"><i class="bi bi-eye-fill text-success"></i> Опубликован</label></div>';
        modalHtml += '<div class="form-check form-switch">';
        modalHtml += '<input class="form-check-input" type="checkbox" id="tc-edit-question-learning"' + (question.is_learning == 1 ? ' checked' : '') + ' style="width: 3em; height: 1.5em;">';
        modalHtml += '<label class="form-check-label ms-2 fw-bold" for="tc-edit-question-learning"><i class="bi bi-book-half text-primary"></i> В обучение</label></div>';
        modalHtml += '</div>';
        modalHtml += '<div><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>';
        modalHtml += '<button type="button" class="btn btn-primary" id="tc-save-question-btn"><i class="bi bi-check-circle"></i> Сохранить</button></div>';
        modalHtml += '</div></div></div></div>';

        var oldModal = document.getElementById('editQuestionModalTests');
        if (oldModal) oldModal.remove();

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        var modal = new bootstrap.Modal(document.getElementById('editQuestionModalTests'));
        modal.show();

        setTimeout(function() {
            initQuillEditor('tc-edit-question-text');
            initQuillEditor('tc-edit-explanation');
            setupImageUpload('tc-question-image');
            setupImageUpload('tc-explanation-image');
        }, 100);

        document.getElementById('tc-save-question-btn').addEventListener('click', async function() {
            await saveQuestionFromTests(questionId);
            modal.hide();
        });

        // После закрытия - обновляем список
        document.getElementById('editQuestionModalTests').addEventListener('hidden.bs.modal', function() {
            loadQuestionsForEditor(currentEditorTestId, currentFilter);
        }, { once: true });
    }

    async function saveQuestionFromTests(questionId) {
        var questionText = document.getElementById('tc-edit-question-text').value.trim();
        var questionType = document.getElementById('tc-edit-question-type').value;
        var explanation = document.getElementById('tc-edit-explanation').value.trim();
        var questionImage = document.getElementById('tc-question-image-url').value;
        var explanationImage = document.getElementById('tc-explanation-image-url').value;
        var published = document.getElementById('tc-edit-question-published').checked ? 1 : 0;
        var isLearning = document.getElementById('tc-edit-question-learning').checked ? 1 : 0;

        var answers = [];
        document.querySelectorAll('#tc-edit-answers-list .input-group').forEach(function(group) {
            var answerId = group.dataset.answerId;
            var text = group.querySelector('.answer-text-input').value.trim();
            var isCorrect = group.querySelector('.answer-correct-check').checked ? 1 : 0;
            if (text) {
                answers.push({ id: answerId, text: text, is_correct: isCorrect });
            }
        });

        if (!questionText || answers.length === 0) {
            alert('Заполните текст вопроса и хотя бы один ответ');
            return;
        }

        try {
            var result = await apiCall('updateQuestion', {
                question_id: questionId,
                question_text: questionText,
                question_type: questionType,
                explanation: explanation,
                question_image: questionImage,
                explanation_image: explanationImage,
                published: published,
                is_learning: isLearning,
                answers: answers
            });

            if (result.success) {
                showNotification('Вопрос успешно обновлен!', 'success');
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }

    // ============================================
    // ДОБАВЛЕНИЕ ВОПРОСА (модальное окно)
    // ============================================

    function openAddQuestionModalFromTests() {
        if (!currentEditorTestId) {
            alert('ID теста не определен');
            return;
        }

        var modalHtml = '<div class="modal fade" id="addQuestionModalTests" tabindex="-1">';
        modalHtml += '<div class="modal-dialog modal-xl"><div class="modal-content">';
        modalHtml += '<div class="modal-header bg-success text-white">';
        modalHtml += '<h5 class="modal-title"><i class="bi bi-plus-circle-fill"></i> Добавление нового вопроса</h5>';
        modalHtml += '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>';
        modalHtml += '<div class="modal-body">';

        // Текст вопроса с Quill
        modalHtml += '<div class="mb-3"><label class="form-label fw-bold">Текст вопроса</label>';
        modalHtml += '<div id="tc-add-question-text-editor" style="min-height: 150px; background: white;"></div>';
        modalHtml += '<textarea id="tc-add-question-text" style="display:none;"></textarea></div>';

        // Изображение к вопросу
        modalHtml += '<div class="mb-3"><label class="form-label fw-bold">Изображение к вопросу</label>';
        modalHtml += '<div class="row g-3"><div class="col-md-4">';
        modalHtml += '<button type="button" class="btn btn-outline-primary w-100" id="upload-tc-add-question-image-btn"><i class="bi bi-upload"></i> Загрузить</button>';
        modalHtml += '<input type="file" id="tc-add-question-image-input" accept="image/*" style="display:none;">';
        modalHtml += '<input type="hidden" id="tc-add-question-image-url" value="">';
        modalHtml += '</div><div class="col-md-8">';
        modalHtml += '<div id="tc-add-question-image-preview" class="border rounded p-2 bg-light" style="min-height: 80px;">';
        modalHtml += '<small class="text-muted">Изображение не загружено</small>';
        modalHtml += '</div></div></div></div>';

        // Тип вопроса
        modalHtml += '<div class="mb-3"><label class="form-label fw-bold">Тип вопроса</label>';
        modalHtml += '<select class="form-select" id="tc-add-question-type">';
        modalHtml += '<option value="single">Один правильный ответ</option>';
        modalHtml += '<option value="multiple">Несколько правильных ответов</option>';
        modalHtml += '</select></div>';

        // Объяснение с Quill
        modalHtml += '<div class="mb-3"><label class="form-label fw-bold">Объяснение</label>';
        modalHtml += '<div id="tc-add-explanation-editor" style="min-height: 150px; background: white;"></div>';
        modalHtml += '<textarea id="tc-add-explanation" style="display:none;"></textarea></div>';

        // Изображение к объяснению
        modalHtml += '<div class="mb-3"><label class="form-label fw-bold">Изображение к объяснению</label>';
        modalHtml += '<div class="row g-3"><div class="col-md-4">';
        modalHtml += '<button type="button" class="btn btn-outline-primary w-100" id="upload-tc-add-explanation-image-btn"><i class="bi bi-upload"></i> Загрузить</button>';
        modalHtml += '<input type="file" id="tc-add-explanation-image-input" accept="image/*" style="display:none;">';
        modalHtml += '<input type="hidden" id="tc-add-explanation-image-url" value="">';
        modalHtml += '</div><div class="col-md-8">';
        modalHtml += '<div id="tc-add-explanation-image-preview" class="border rounded p-2 bg-light" style="min-height: 80px;">';
        modalHtml += '<small class="text-muted">Изображение не загружено</small>';
        modalHtml += '</div></div></div></div>';

        // Варианты ответов
        modalHtml += '<div class="mb-3"><label class="form-label fw-bold">Варианты ответов</label>';
        modalHtml += '<div id="tc-add-answers-list">';
        for (var i = 1; i <= 4; i++) {
            modalHtml += '<div class="input-group mb-2" data-answer-id="new' + i + '">';
            modalHtml += '<div class="input-group-text"><input type="checkbox" class="answer-correct-check"></div>';
            modalHtml += '<input type="text" class="form-control answer-text-input" placeholder="Введите вариант ответа">';
            modalHtml += '</div>';
        }
        modalHtml += '</div>';
        modalHtml += '<button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="tc-add-more-answers-btn">';
        modalHtml += '<i class="bi bi-plus-circle"></i> Добавить еще вариант</button>';
        modalHtml += '</div>';

        modalHtml += '</div>'; // modal-body

        // Footer
        modalHtml += '<div class="modal-footer d-flex justify-content-between align-items-center">';
        modalHtml += '<div class="d-flex gap-4">';
        modalHtml += '<div class="form-check form-switch">';
        modalHtml += '<input class="form-check-input" type="checkbox" id="tc-add-question-published" checked style="width: 3em; height: 1.5em;">';
        modalHtml += '<label class="form-check-label ms-2 fw-bold" for="tc-add-question-published"><i class="bi bi-eye-fill text-success"></i> Опубликован</label></div>';
        modalHtml += '<div class="form-check form-switch">';
        modalHtml += '<input class="form-check-input" type="checkbox" id="tc-add-question-learning" style="width: 3em; height: 1.5em;">';
        modalHtml += '<label class="form-check-label ms-2 fw-bold" for="tc-add-question-learning"><i class="bi bi-book-half text-primary"></i> В обучение</label></div>';
        modalHtml += '</div>';
        modalHtml += '<div><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>';
        modalHtml += '<button type="button" class="btn btn-success" id="tc-create-question-btn"><i class="bi bi-check-circle"></i> Создать вопрос</button></div>';
        modalHtml += '</div></div></div></div>';

        var oldModal = document.getElementById('addQuestionModalTests');
        if (oldModal) oldModal.remove();

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        var modal = new bootstrap.Modal(document.getElementById('addQuestionModalTests'));
        modal.show();

        setTimeout(function() {
            initQuillEditor('tc-add-question-text');
            initQuillEditor('tc-add-explanation');
            setupImageUpload('tc-add-question-image');
            setupImageUpload('tc-add-explanation-image');

            document.getElementById('tc-add-more-answers-btn').addEventListener('click', function() {
                var answersList = document.getElementById('tc-add-answers-list');
                var newId = 'new' + Date.now();
                var newHtml = '<div class="input-group mb-2" data-answer-id="' + newId + '">';
                newHtml += '<div class="input-group-text"><input type="checkbox" class="answer-correct-check"></div>';
                newHtml += '<input type="text" class="form-control answer-text-input" placeholder="Введите вариант ответа">';
                newHtml += '<button class="btn btn-outline-danger" type="button" onclick="this.parentElement.remove()"><i class="bi bi-trash"></i></button>';
                newHtml += '</div>';
                answersList.insertAdjacentHTML('beforeend', newHtml);
            });
        }, 100);

        document.getElementById('tc-create-question-btn').addEventListener('click', async function() {
            await createNewQuestionFromTests();
            modal.hide();
        });

        document.getElementById('addQuestionModalTests').addEventListener('hidden.bs.modal', function() {
            loadQuestionsForEditor(currentEditorTestId, currentFilter);
        }, { once: true });
    }
    window.openAddQuestionModalFromTests = openAddQuestionModalFromTests;

    async function createNewQuestionFromTests() {
        var questionText = document.getElementById('tc-add-question-text').value.trim();
        var questionType = document.getElementById('tc-add-question-type').value;
        var explanation = document.getElementById('tc-add-explanation').value.trim();
        var questionImage = document.getElementById('tc-add-question-image-url').value;
        var explanationImage = document.getElementById('tc-add-explanation-image-url').value;
        var published = document.getElementById('tc-add-question-published').checked ? 1 : 0;
        var isLearning = document.getElementById('tc-add-question-learning').checked ? 1 : 0;

        var answers = [];
        document.querySelectorAll('#tc-add-answers-list .input-group').forEach(function(group) {
            var text = group.querySelector('.answer-text-input').value.trim();
            var isCorrect = group.querySelector('.answer-correct-check').checked ? 1 : 0;
            if (text) {
                answers.push({ text: text, is_correct: isCorrect });
            }
        });

        if (!questionText) {
            alert('Заполните текст вопроса');
            return;
        }
        if (answers.length === 0) {
            alert('Добавьте хотя бы один вариант ответа');
            return;
        }

        try {
            var result = await apiCall('createQuestion', {
                test_id: currentEditorTestId,
                question_text: questionText,
                question_type: questionType,
                explanation: explanation,
                question_image: questionImage,
                explanation_image: explanationImage,
                published: published,
                is_learning: isLearning,
                answers: answers
            });

            if (result.success) {
                showNotification('Вопрос создан!', 'success');
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }

    // ============================================
    // ЗАПУСК ТЕСТА ИЗ РЕДАКТОРА ВОПРОСОВ
    // ============================================

    async function startTestFromEditor(mode) {
        if (!currentEditorTestId) {
            alert('ID теста не определен');
            return;
        }

        var questionsInput = document.getElementById('editor-questions-count');
        var questionsCount = questionsInput ? parseInt(questionsInput.value) || 20 : 20;

        await startTestSession(currentEditorTestId, mode, mode === 'training' ? questionsCount : null);
    }
    window.startTestFromEditor = startTestFromEditor;

    // ============================================
    // НАСТРОЙКИ ТЕСТА (модальное окно)
    // ============================================

    async function openTestSettingsModal(testId) {
        showLoadingIndicator('Загрузка настроек...');

        // Закрываем меню
        document.querySelectorAll('.test-menu-dropdown').forEach(function(m) {
            m.style.display = 'none';
        });

        try {
            var results = await Promise.all([
                apiCall('getTestSettings', { test_id: testId }),
                apiCall('getTestPermissions', { test_id: testId })
            ]);

            hideLoadingIndicator();

            var settingsResult = results[0];
            var permissionsResult = results[1];

            if (!settingsResult.success) {
                throw new Error('Ошибка загрузки настроек теста');
            }

            var settings = settingsResult.data;
            var permissions = permissionsResult.success ? permissionsResult.data.permissions : [];
            var currentStatus = settings.publication_status || 'public';

            var modalHtml = '<div class="modal fade" id="testSettingsModalTests" tabindex="-1">';
            modalHtml += '<div class="modal-dialog modal-xl"><div class="modal-content">';
            modalHtml += '<div class="modal-header"><h5 class="modal-title"><i class="bi bi-gear"></i> Управление тестом</h5>';
            modalHtml += '<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
            modalHtml += '<div class="modal-body">';

            // Вкладки
            modalHtml += '<ul class="nav nav-tabs mb-4" role="tablist">';
            modalHtml += '<li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tc-settings-tab" type="button"><i class="bi bi-sliders"></i> Настройки</button></li>';
            if (currentStatus === 'private') {
                modalHtml += '<li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tc-access-tab" type="button"><i class="bi bi-people"></i> Доступ <span class="badge bg-secondary">' + permissions.length + '</span></button></li>';
            }
            modalHtml += '<li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tc-publication-tab" type="button"><i class="bi bi-globe"></i> Публикация</button></li>';
            modalHtml += '</ul>';

            modalHtml += '<div class="tab-content">';

            // Вкладка "Настройки"
            modalHtml += '<div class="tab-pane fade show active" id="tc-settings-tab">';
            modalHtml += '<form id="tc-test-settings-form">';
            modalHtml += '<input type="hidden" name="test_id" value="' + testId + '">';
            modalHtml += '<div class="row">';
            modalHtml += '<div class="col-md-6 mb-3"><label class="form-label">Название теста</label>';
            modalHtml += '<input type="text" name="title" class="form-control" value="' + escapeHtml(settings.title) + '" required></div>';
            modalHtml += '<div class="col-md-6 mb-3"><label class="form-label">Режим теста</label>';
            modalHtml += '<select name="mode" class="form-select">';
            modalHtml += '<option value="training"' + (settings.mode === 'training' ? ' selected' : '') + '>Тренировка</option>';
            modalHtml += '<option value="exam"' + (settings.mode === 'exam' ? ' selected' : '') + '>Экзамен</option>';
            modalHtml += '</select></div></div>';
            modalHtml += '<div class="mb-3"><label class="form-label">Описание</label>';
            modalHtml += '<textarea name="description" class="form-control" rows="3">' + escapeHtml(settings.description || '') + '</textarea></div>';
            modalHtml += '<div class="row">';
            modalHtml += '<div class="col-md-4 mb-3"><label class="form-label">Проходной балл (%)</label>';
            modalHtml += '<input type="number" name="pass_score" class="form-control" value="' + settings.pass_score + '" min="0" max="100"></div>';
            modalHtml += '<div class="col-md-4 mb-3"><label class="form-label">Время (минут, 0 = без ограничений)</label>';
            modalHtml += '<input type="number" name="time_limit" class="form-control" value="' + settings.time_limit + '" min="0"></div>';
            modalHtml += '<div class="col-md-4 mb-3"><label class="form-label">Вопросов за попытку</label>';
            modalHtml += '<input type="number" name="questions_per_session" class="form-control" value="' + settings.questions_per_session + '" min="1"></div>';
            modalHtml += '</div>';
            modalHtml += '<div class="row">';
            modalHtml += '<div class="col-md-6 mb-3"><div class="form-check form-switch">';
            modalHtml += '<input class="form-check-input" type="checkbox" name="randomize_questions" id="tc-randomize-q"' + (settings.randomize_questions ? ' checked' : '') + '>';
            modalHtml += '<label class="form-check-label" for="tc-randomize-q">Перемешивать вопросы</label></div></div>';
            modalHtml += '<div class="col-md-6 mb-3"><div class="form-check form-switch">';
            modalHtml += '<input class="form-check-input" type="checkbox" name="randomize_answers" id="tc-randomize-a"' + (settings.randomize_answers ? ' checked' : '') + '>';
            modalHtml += '<label class="form-check-label" for="tc-randomize-a">Перемешивать ответы</label></div></div>';
            modalHtml += '</div>';
            modalHtml += '<button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Сохранить настройки</button>';
            modalHtml += '</form></div>';

            // Вкладка "Доступ" (только для private)
            if (currentStatus === 'private') {
                modalHtml += '<div class="tab-pane fade" id="tc-access-tab">';
                modalHtml += '<div class="mb-4"><h6>Добавить пользователя</h6>';
                modalHtml += '<div class="input-group mb-2">';
                modalHtml += '<input type="text" class="form-control" id="tc-user-search-input" placeholder="Поиск по имени, email или username...">';
                modalHtml += '<button class="btn btn-primary" onclick="searchUsersForAccessTC(' + testId + ')"><i class="bi bi-search"></i> Найти</button>';
                modalHtml += '</div>';
                modalHtml += '<div id="tc-search-results"></div></div>';
                modalHtml += '<hr><div><h6>Пользователи с доступом (' + permissions.length + ')</h6>';
                modalHtml += '<div id="tc-permissions-list" class="list-group">';
                modalHtml += renderPermissionsListTC(permissions, testId);
                modalHtml += '</div></div></div>';
            }

            // Вкладка "Публикация"
            modalHtml += '<div class="tab-pane fade" id="tc-publication-tab">';
            modalHtml += '<div class="alert alert-info"><strong>Текущий статус:</strong> ' + getStatusName(currentStatus) + '</div>';
            modalHtml += '<p>Выберите новый статус публикации:</p>';
            modalHtml += '<div class="list-group">';
            var statuses = ['draft', 'private', 'unlisted', 'public'];
            var statusLabels = { draft: 'Черновик', 'private': 'Приватный', unlisted: 'По ссылке', 'public': 'Публичный' };
            var statusDescriptions = { draft: 'Тест виден только вам. Используйте для подготовки.', 'private': 'Доступ только по приглашению.', unlisted: 'Доступен всем, у кого есть ссылка.', 'public': 'Доступен всем пользователям системы.' };
            statuses.forEach(function(st) {
                var active = st === currentStatus ? ' active' : '';
                modalHtml += '<button type="button" class="list-group-item list-group-item-action' + active + '" onclick="changePublicationStatusTC(' + testId + ', \'' + st + '\')">';
                modalHtml += '<h6 class="mb-1">' + statusLabels[st] + '</h6>';
                modalHtml += '<small class="d-block">' + statusDescriptions[st] + '</small>';
                modalHtml += '</button>';
            });
            modalHtml += '</div></div>';

            modalHtml += '</div>'; // tab-content
            modalHtml += '</div></div></div></div>'; // modal

            var oldModal = document.getElementById('testSettingsModalTests');
            if (oldModal) oldModal.remove();

            document.body.insertAdjacentHTML('beforeend', modalHtml);

            var modal = new bootstrap.Modal(document.getElementById('testSettingsModalTests'));
            modal.show();

            // Обработчик формы
            var form = document.getElementById('tc-test-settings-form');
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                var formData = new FormData(form);
                var data = {
                    test_id: testId,
                    title: formData.get('title'),
                    description: formData.get('description'),
                    mode: formData.get('mode'),
                    pass_score: parseInt(formData.get('pass_score')),
                    time_limit: parseInt(formData.get('time_limit')),
                    questions_per_session: parseInt(formData.get('questions_per_session')),
                    randomize_questions: formData.get('randomize_questions') ? 1 : 0,
                    randomize_answers: formData.get('randomize_answers') ? 1 : 0
                };

                try {
                    var result = await apiCall('updateTestSettings', data);
                    if (result.success) {
                        showNotification('Настройки сохранены!', 'success');
                        modal.hide();
                        location.reload();
                    } else {
                        alert('Ошибка: ' + (result.message || 'Ошибка сохранения'));
                    }
                } catch (error) {
                    alert('Ошибка: ' + error.message);
                }
            });

        } catch (error) {
            hideLoadingIndicator();
            alert('Ошибка: ' + error.message);
        }
    }
    window.openTestSettingsModal = openTestSettingsModal;

    // ============================================
    // Публикация
    // ============================================

    async function changePublicationStatusTC(testId, newStatus) {
        if (!confirm('Изменить статус публикации на "' + getStatusName(newStatus) + '"?')) {
            return;
        }

        try {
            var result = await apiCall('publishTest', {
                test_id: testId,
                status: newStatus
            });

            if (result.success) {
                showNotification('Статус изменен!', 'success');
                location.reload();
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }
    window.changePublicationStatusTC = changePublicationStatusTC;

    // ============================================
    // Управление доступом
    // ============================================

    function renderPermissionsListTC(permissions, testId) {
        if (permissions.length === 0) {
            return '<div class="alert alert-info">Никому не предоставлен доступ</div>';
        }

        var html = '';
        permissions.forEach(function(perm) {
            var userDisplay = perm.fullname || perm.username;
            var email = perm.email ? '(' + perm.email + ')' : '';
            var canEditBadge = perm.can_edit
                ? '<span class="badge bg-warning">Может редактировать</span>'
                : '<span class="badge bg-secondary">Только просмотр</span>';

            html += '<div class="list-group-item"><div class="d-flex justify-content-between align-items-center"><div>';
            html += '<strong>' + escapeHtml(userDisplay) + '</strong> ' + email + '<br>';
            html += '<small class="text-muted">Доступ предоставлен: ' + new Date(perm.granted_at).toLocaleDateString('ru-RU') + '</small><br>';
            html += canEditBadge;
            html += '</div><div class="btn-group">';
            html += '<button class="btn btn-sm btn-outline-warning" onclick="toggleEditPermissionTC(' + testId + ', ' + perm.user_id + ', ' + (perm.can_edit ? 0 : 1) + ')" title="' + (perm.can_edit ? 'Убрать право редактирования' : 'Дать право редактирования') + '"><i class="bi bi-pencil"></i></button>';
            html += '<button class="btn btn-sm btn-outline-danger" onclick="revokeAccessTC(' + testId + ', ' + perm.user_id + ')"><i class="bi bi-trash"></i></button>';
            html += '</div></div></div>';
        });

        return html;
    }

    async function searchUsersForAccessTC(testId) {
        var query = document.getElementById('tc-user-search-input').value.trim();
        if (query.length < 2) {
            alert('Введите минимум 2 символа для поиска');
            return;
        }

        try {
            var result = await apiCall('searchUsers', { query: query });
            var container = document.getElementById('tc-search-results');

            if (!result.success || !result.data.length) {
                container.innerHTML = '<div class="alert alert-warning">Пользователи не найдены</div>';
                return;
            }

            var html = '<div class="list-group mb-3">';
            result.data.forEach(function(user) {
                html += '<div class="list-group-item d-flex justify-content-between align-items-center">';
                html += '<div><strong>' + escapeHtml(user.fullname || user.username) + '</strong>';
                if (user.email) html += ' <small class="text-muted">(' + escapeHtml(user.email) + ')</small>';
                html += '</div>';
                html += '<button class="btn btn-sm btn-success" onclick="grantAccessTC(' + testId + ', ' + user.id + ', 0)"><i class="bi bi-plus"></i> Добавить</button>';
                html += '</div>';
            });
            html += '</div>';
            container.innerHTML = html;
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }
    window.searchUsersForAccessTC = searchUsersForAccessTC;

    async function grantAccessTC(testId, userId, canEdit) {
        try {
            var result = await apiCall('grantAccess', { test_id: testId, user_id: userId, can_edit: canEdit });
            if (result.success) {
                showNotification('Доступ предоставлен', 'success');
                // Перезагружаем модальное окно
                var modal = bootstrap.Modal.getInstance(document.getElementById('testSettingsModalTests'));
                if (modal) modal.hide();
                openTestSettingsModal(testId);
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }
    window.grantAccessTC = grantAccessTC;

    async function toggleEditPermissionTC(testId, userId, canEdit) {
        if (!confirm((canEdit ? 'Предоставить' : 'Убрать') + ' право редактирования?')) return;
        await grantAccessTC(testId, userId, canEdit);
    }
    window.toggleEditPermissionTC = toggleEditPermissionTC;

    async function revokeAccessTC(testId, userId) {
        if (!confirm('Отозвать доступ у этого пользователя?')) return;
        try {
            var result = await apiCall('revokeAccess', { test_id: testId, user_id: userId });
            if (result.success) {
                showNotification('Доступ отозван', 'info');
                var modal = bootstrap.Modal.getInstance(document.getElementById('testSettingsModalTests'));
                if (modal) modal.hide();
                openTestSettingsModal(testId);
            } else {
                alert('Ошибка: ' + result.message);
            }
        } catch (error) {
            alert('Ошибка: ' + error.message);
        }
    }
    window.revokeAccessTC = revokeAccessTC;

    // ============================================
    // Инициализация
    // ============================================

    console.log('Test Cards module v3.0 initialized');

})();
