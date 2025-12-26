/**
 * LEARNING PATHS MANAGER - Sprint 11
 * Управление траекториями обучения
 * Последовательные шаги, unlock условия, прогресс, сертификаты
 */

(function() {
    'use strict';

    const API_URL = '/assets/components/testsystem/ajax/testsystem.php';

    // CSRF Protection
    function getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.content : null;
    }

    // XSS Protection
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // State
    let currentPathId = null;
    let pathSteps = [];
    let userProgress = {};

    // Step Types
    const STEP_TYPE_LABELS = {
        'material': '📚 Учебный материал',
        'test': '📝 Тест',
        'quiz': '❓ Викторина',
        'assignment': '✍️ Задание'
    };

    const STEP_TYPE_ICONS = {
        'material': 'bi-journal-text',
        'test': 'bi-clipboard-check',
        'quiz': 'bi-question-circle',
        'assignment': 'bi-pencil-square'
    };

    // API Call Helper
    async function apiCall(action, data = {}) {
        try {
            const csrfToken = getCsrfToken();
            if (csrfToken) {
                data.csrf_token = csrfToken;
            }

            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, data })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            showNotification('Ошибка соединения с сервером', 'danger');
            throw error;
        }
    }

    // ==================== INITIALIZATION ====================

    document.addEventListener('DOMContentLoaded', function() {
        // Страница списка траекторий
        const pathsContainer = document.getElementById('learning-paths-container');
        if (pathsContainer) {
            initPathsList();
        }

        // Страница просмотра траектории
        const pathViewContainer = document.getElementById('path-view-container');
        if (pathViewContainer) {
            const pathId = pathViewContainer.dataset.pathId;
            if (pathId) {
                loadPath(pathId);
            }
        }

        // Страница редактирования траектории
        const pathEditorContainer = document.getElementById('path-editor-container');
        if (pathEditorContainer) {
            const pathId = pathEditorContainer.dataset.pathId;
            initPathEditor(pathId);
        }
    });

    // ==================== PATHS LIST ====================

    async function initPathsList() {
        loadPaths();

        // Event listeners
        document.getElementById('create-path-btn')?.addEventListener('click', showCreatePathModal);
        document.getElementById('save-path-info-btn')?.addEventListener('click', savePathInfo);
        document.getElementById('filter-category')?.addEventListener('change', filterPaths);
        document.getElementById('filter-difficulty')?.addEventListener('change', filterPaths);
    }

    async function loadPaths() {
        try {
            const result = await apiCall('getPathsList', {});

            if (result.success) {
                renderPathsList(result.data || []);
            } else {
                throw new Error(result.message || 'Ошибка загрузки траекторий');
            }
        } catch (error) {
            console.error('Load paths error:', error);
            document.getElementById('learning-paths-container').innerHTML = `
                <div class="alert alert-danger">
                    Ошибка загрузки траекторий: ${escapeHtml(error.message)}
                </div>
            `;
        }
    }

    function renderPathsList(paths) {
        const container = document.getElementById('learning-paths-container');

        if (!paths || paths.length === 0) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-info text-center py-5">
                        <i class="bi bi-signpost fs-1 d-block mb-3"></i>
                        <h5>Траектории обучения не найдены</h5>
                        <p class="mb-0">Создайте первую траекторию для структурированного обучения</p>
                    </div>
                </div>
            `;
            return;
        }

        let html = '';

        paths.forEach(path => {
            const progress = path.completion_pct || 0;
            const progressClass = progress === 100 ? 'bg-success' :
                                  progress > 0 ? 'bg-warning' : 'bg-secondary';

            const difficultyBadge = {
                'beginner': '<span class="badge bg-success">Начальный</span>',
                'intermediate': '<span class="badge bg-warning text-dark">Средний</span>',
                'advanced': '<span class="badge bg-danger">Продвинутый</span>',
                'expert': '<span class="badge bg-dark">Эксперт</span>'
            }[path.difficulty_level] || '';

            const statusBadge = path.status === 'published'
                ? '<span class="badge bg-success">Опубликован</span>'
                : '<span class="badge bg-secondary">Черновик</span>';

            // Badge для шаблона
            const templateBadge = path.is_template
                ? '<span class="badge bg-info"><i class="bi bi-file-earmark-text"></i> Шаблон</span>'
                : '';

            // Если это клон - показываем ссылку на родителя
            const cloneBadge = path.parent_id
                ? '<span class="badge bg-light text-dark"><i class="bi bi-copy"></i> Клон</span>'
                : '';

            // Информация о дедлайне и назначении
            let assignmentInfo = '';
            if (path.deadline) {
                const deadlineDate = new Date(path.deadline);
                const now = new Date();
                const isOverdue = deadlineDate < now;
                assignmentInfo += `
                    <div class="small ${isOverdue ? 'text-danger' : 'text-muted'} mb-2">
                        <i class="bi bi-calendar-event"></i>
                        Дедлайн: ${deadlineDate.toLocaleDateString('ru-RU')}
                        ${isOverdue ? '<span class="badge bg-danger">Просрочено</span>' : ''}
                    </div>
                `;
            }
            if (path.enrolled_by_name) {
                assignmentInfo += `
                    <div class="small text-muted mb-2">
                        <i class="bi bi-person"></i>
                        Назначено: ${escapeHtml(path.enrolled_by_name)}
                    </div>
                `;
            }

            html += `
                <div class="col-md-6 col-lg-4 path-card"
                     data-category="${path.category_id || ''}"
                     data-difficulty="${path.difficulty_level || ''}"
                     data-is-template="${path.is_template || 0}">
                    <div class="card h-100 shadow-sm ${path.is_template ? 'border-info' : ''}">
                        ${path.thumbnail_url ? `
                            <img src="${escapeHtml(path.thumbnail_url)}"
                                 class="card-img-top"
                                 alt="${escapeHtml(path.name)}"
                                 style="height: 180px; object-fit: cover;">
                        ` : `
                            <div class="card-img-top bg-gradient d-flex align-items-center justify-content-center"
                                 style="height: 180px; background: linear-gradient(135deg, ${path.is_template ? '#17a2b8 0%, #138496 100%' : '#667eea 0%, #764ba2 100%'});">
                                <i class="bi bi-${path.is_template ? 'file-earmark-text' : 'signpost'} text-white" style="font-size: 3rem;"></i>
                            </div>
                        `}
                        <div class="card-body">
                            <h5 class="card-title">${escapeHtml(path.name)}</h5>
                            <p class="card-text text-muted small">${escapeHtml(path.description || 'Нет описания')}</p>

                            <div class="mb-3">
                                ${templateBadge}
                                ${cloneBadge}
                                ${statusBadge}
                                ${difficultyBadge}
                                ${path.certificate_template ? '<span class="badge bg-primary">🎓 С сертификатом</span>' : ''}
                            </div>

                            ${assignmentInfo}

                            <div class="d-flex justify-content-between text-muted small mb-3">
                                <span><i class="bi bi-list-ol"></i> ${path.steps_count || 0} шагов</span>
                                <span><i class="bi bi-clock"></i> ~${path.estimated_hours || 0} ч</span>
                            </div>

                            ${progress > 0 ? `
                                <div class="progress mb-2" style="height: 10px;">
                                    <div class="progress-bar ${progressClass}"
                                         role="progressbar"
                                         style="width: ${progress}%">
                                    </div>
                                </div>
                                <p class="small text-muted mb-0">Прогресс: ${progress}%</p>
                            ` : ''}
                        </div>
                        <div class="card-footer bg-white">
                            <div class="d-grid gap-2">
                                ${path.is_template ? `
                                    <button class="btn btn-info"
                                            onclick="LearningPaths.showCloneModal(${path.id}, '${escapeHtml(path.name).replace(/'/g, "\\'")}')">
                                        <i class="bi bi-copy"></i> Клонировать шаблон
                                    </button>
                                ` : `
                                    <a href="/learning-path?id=${path.id}" class="btn btn-primary">
                                        <i class="bi bi-play-fill"></i>
                                        ${progress > 0 ? 'Продолжить' : 'Начать'}
                                    </a>
                                `}
                                ${path.can_edit ? `
                                    <div class="btn-group btn-group-sm">
                                        <a href="/edit-path?id=${path.id}" class="btn btn-outline-secondary">
                                            <i class="bi bi-pencil"></i> Редактировать
                                        </a>
                                        ${path.is_template ? '' : `
                                            <button class="btn btn-outline-info"
                                                    onclick="LearningPaths.showCloneModal(${path.id}, '${escapeHtml(path.name).replace(/'/g, "\\'")}')"
                                                    title="Клонировать">
                                                <i class="bi bi-copy"></i>
                                            </button>
                                        `}
                                        <button class="btn btn-outline-danger"
                                                onclick="LearningPaths.deletePath(${path.id}, '${escapeHtml(path.name).replace(/'/g, "\\'")}')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    function filterPaths() {
        const category = document.getElementById('filter-category')?.value || '';
        const difficulty = document.getElementById('filter-difficulty')?.value || '';

        document.querySelectorAll('.path-card').forEach(card => {
            const matchesCategory = !category || card.dataset.category === category;
            const matchesDifficulty = !difficulty || card.dataset.difficulty === difficulty;

            card.style.display = matchesCategory && matchesDifficulty ? '' : 'none';
        });
    }

    function showCreatePathModal() {
        currentPathId = null;
        document.getElementById('path-modal-title').textContent = 'Создать траекторию обучения';
        document.getElementById('path-title').value = '';
        document.getElementById('path-description').value = '';
        document.getElementById('path-difficulty').value = 'beginner';
        document.getElementById('path-estimated-hours').value = '';
        document.getElementById('path-has-certificate').checked = false;
        document.getElementById('path-published').checked = false;

        // Чекбокс "Шаблон" (если есть)
        const templateCheckbox = document.getElementById('path-is-template');
        if (templateCheckbox) {
            templateCheckbox.checked = false;
        }

        const modal = new bootstrap.Modal(document.getElementById('pathModal'));
        modal.show();
    }

    // ==================== CLONE FUNCTIONALITY ====================

    let cloneSourceId = null;
    let cloneSourceName = '';

    function showCloneModal(pathId, pathName) {
        cloneSourceId = pathId;
        cloneSourceName = pathName;

        // Создаём модалку если её нет
        let cloneModal = document.getElementById('cloneModal');
        if (!cloneModal) {
            cloneModal = document.createElement('div');
            cloneModal.innerHTML = `
                <div class="modal fade" id="cloneModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Клонировать траекторию</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    Будет создана копия траектории "<strong id="clone-source-name"></strong>" со всеми шагами.
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Название новой траектории</label>
                                    <input type="text" class="form-control" id="clone-new-name" placeholder="Оставьте пустым для автогенерации">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Описание (опционально)</label>
                                    <textarea class="form-control" id="clone-new-description" rows="2"></textarea>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="clone-achievements">
                                    <label class="form-check-label" for="clone-achievements">
                                        Клонировать достижения
                                    </label>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                <button type="button" class="btn btn-info" id="clone-path-btn">
                                    <i class="bi bi-copy"></i> Клонировать
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(cloneModal.firstElementChild);

            document.getElementById('clone-path-btn').addEventListener('click', clonePath);
        }

        document.getElementById('clone-source-name').textContent = pathName;
        document.getElementById('clone-new-name').value = '';
        document.getElementById('clone-new-description').value = '';
        document.getElementById('clone-achievements').checked = false;

        const modal = new bootstrap.Modal(document.getElementById('cloneModal'));
        modal.show();
    }

    async function clonePath() {
        if (!cloneSourceId) return;

        const newName = document.getElementById('clone-new-name').value.trim();
        const newDescription = document.getElementById('clone-new-description').value.trim();
        const cloneAchievements = document.getElementById('clone-achievements').checked;

        const cloneBtn = document.getElementById('clone-path-btn');
        cloneBtn.disabled = true;
        cloneBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Клонирование...';

        try {
            const data = {
                path_id: cloneSourceId,
                clone_achievements: cloneAchievements
            };

            if (newName) {
                data.name = newName;
            }
            if (newDescription) {
                data.description = newDescription;
            }

            const result = await apiCall('clonePath', data);

            if (result.success) {
                showNotification('Траектория клонирована успешно', 'success');

                const modal = bootstrap.Modal.getInstance(document.getElementById('cloneModal'));
                modal.hide();

                // Открываем редактор клона
                if (result.data && result.data.path_id) {
                    window.location.href = `/edit-path?id=${result.data.path_id}`;
                } else {
                    loadPaths();
                }
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Clone path error:', error);
            showNotification('Ошибка клонирования: ' + error.message, 'danger');
        } finally {
            cloneBtn.disabled = false;
            cloneBtn.innerHTML = '<i class="bi bi-copy"></i> Клонировать';
        }
    }

    async function savePathInfo() {
        const title = document.getElementById('path-title').value.trim();
        const description = document.getElementById('path-description').value.trim();
        const difficulty = document.getElementById('path-difficulty').value;
        const estimatedHours = parseInt(document.getElementById('path-estimated-hours').value) || 0;
        const hasCertificate = document.getElementById('path-has-certificate').checked;
        const isPublished = document.getElementById('path-published').checked;

        // Чекбокс "Шаблон"
        const templateCheckbox = document.getElementById('path-is-template');
        const isTemplate = templateCheckbox ? templateCheckbox.checked : false;

        if (!title) {
            showNotification('Введите название траектории', 'warning');
            return;
        }

        const saveBtn = document.getElementById('save-path-info-btn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Сохранение...';

        try {
            const action = currentPathId ? 'updatePath' : 'createPath';
            const data = {
                name: title,
                description,
                difficulty_level: difficulty,
                estimated_hours: estimatedHours,
                certificate_template: hasCertificate ? 'default' : null,
                status: isPublished ? 'published' : 'draft',
                is_template: isTemplate ? 1 : 0
            };

            if (currentPathId) {
                data.path_id = currentPathId;
            }

            const result = await apiCall(action, data);

            if (result.success) {
                showNotification(currentPathId ? 'Траектория обновлена' : 'Траектория создана', 'success');

                const modal = bootstrap.Modal.getInstance(document.getElementById('pathModal'));
                modal.hide();

                if (!currentPathId && result.path_id) {
                    window.location.href = `/edit-path?id=${result.path_id}`;
                } else {
                    loadPaths();
                }
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Save path error:', error);
            showNotification('Ошибка сохранения: ' + error.message, 'danger');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Сохранить';
        }
    }

    async function deletePath(pathId, pathTitle) {
        if (!confirm(`Вы уверены, что хотите удалить траекторию "${pathTitle}"?`)) {
            return;
        }

        try {
            const result = await apiCall('deletePath', { path_id: pathId });

            if (result.success) {
                showNotification('Траектория удалена', 'success');
                loadPaths();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Delete path error:', error);
            showNotification('Ошибка удаления: ' + error.message, 'danger');
        }
    }

    // ==================== PATH VIEWER ====================

    async function loadPath(pathId) {
        try {
            const result = await apiCall('getPath', { path_id: pathId, with_steps: 1 });

            if (result.success) {
                currentPathId = pathId;
                pathSteps = result.data.steps || [];
                userProgress = result.data.user_progress || {};

                renderPathView(result.data);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Load path error:', error);
            document.getElementById('path-view-container').innerHTML = `
                <div class="alert alert-danger">
                    Ошибка загрузки траектории: ${escapeHtml(error.message)}
                </div>
            `;
        }
    }

    function renderPathView(data) {
        const container = document.getElementById('path-view-container');

        const progress = userProgress.percentage || 0;
        const completedSteps = userProgress.completed_steps_count || 0;
        const totalSteps = pathSteps.length;

        let html = `
            <div class="path-header mb-4">
                <h1>${escapeHtml(data.name)}</h1>
                ${data.description ? `<p class="lead text-muted">${escapeHtml(data.description)}</p>` : ''}

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="bi bi-list-ol fs-2 text-primary"></i>
                                <h5 class="mt-2">${totalSteps}</h5>
                                <p class="small text-muted mb-0">Всего шагов</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="bi bi-check-circle fs-2 text-success"></i>
                                <h5 class="mt-2">${completedSteps}</h5>
                                <p class="small text-muted mb-0">Завершено</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="bi bi-clock fs-2 text-warning"></i>
                                <h5 class="mt-2">~${data.estimated_hours}ч</h5>
                                <p class="small text-muted mb-0">Время</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <i class="bi bi-trophy fs-2 text-danger"></i>
                                <h5 class="mt-2">${progress}%</h5>
                                <p class="small text-muted mb-0">Прогресс</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="progress mb-4" style="height: 25px;">
                    <div class="progress-bar bg-success progress-bar-striped ${progress < 100 ? 'progress-bar-animated' : ''}"
                         role="progressbar"
                         style="width: ${progress}%">
                        ${progress}%
                    </div>
                </div>
            </div>

            <div class="path-steps">
        `;

        pathSteps.forEach((step, index) => {
            html += renderStep(step, index);
        });

        html += '</div>';

        container.innerHTML = html;
    }

    function renderStep(step, index) {
        const isCompleted = userProgress.completed_steps?.includes(step.id);
        const isLocked = step.is_locked && !isCompleted;
        const isCurrent = !isCompleted && !isLocked;

        const statusIcon = isCompleted ? '<i class="bi bi-check-circle-fill text-success fs-4"></i>' :
                          isLocked ? '<i class="bi bi-lock-fill text-muted fs-4"></i>' :
                          '<i class="bi bi-circle text-primary fs-4"></i>';

        const cardClass = isCompleted ? 'border-success' :
                         isCurrent ? 'border-primary' :
                         'border-secondary';

        const typeIcon = STEP_TYPE_ICONS[step.step_type] || 'bi-circle';
        const typeLabel = STEP_TYPE_LABELS[step.step_type] || step.step_type;

        let unlockRequirements = '';
        if (isLocked && step.unlock_requirements) {
            const reqs = JSON.parse(step.unlock_requirements || '{}');
            unlockRequirements = `
                <div class="alert alert-warning small mb-0">
                    <i class="bi bi-info-circle"></i>
                    <strong>Требования для разблокировки:</strong><br>
                    ${reqs.previous_step ? '✓ Завершите предыдущий шаг<br>' : ''}
                    ${reqs.min_score ? `✓ Минимальный балл: ${reqs.min_score}%<br>` : ''}
                    ${reqs.required_steps ? `✓ Завершите шаги: ${reqs.required_steps.join(', ')}<br>` : ''}
                </div>
            `;
        }

        return `
            <div class="step-card card mb-3 ${cardClass}" data-step-id="${step.id}">
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0 me-3">
                            ${statusIcon}
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title mb-2">
                                        <span class="badge bg-secondary me-2">Шаг ${index + 1}</span>
                                        ${escapeHtml(step.name)}
                                    </h5>
                                    <p class="text-muted mb-2">
                                        <i class="${typeIcon}"></i>
                                        ${typeLabel}
                                    </p>
                                    ${step.description ? `<p class="card-text">${escapeHtml(step.description)}</p>` : ''}
                                </div>
                                <div>
                                    ${isCompleted ? `
                                        <span class="badge bg-success">Завершено</span>
                                        ${step.completion_date ? `<br><small class="text-muted">${new Date(step.completion_date).toLocaleDateString('ru-RU')}</small>` : ''}
                                    ` : isLocked ? `
                                        <span class="badge bg-secondary">Заблокировано</span>
                                    ` : `
                                        <span class="badge bg-primary">Доступно</span>
                                    `}
                                </div>
                            </div>

                            ${unlockRequirements}

                            ${!isLocked ? `
                                <div class="mt-3">
                                    <button class="btn ${isCompleted ? 'btn-outline-primary' : 'btn-primary'}"
                                            onclick="LearningPaths.startStep(${step.id})">
                                        <i class="bi bi-${isCompleted ? 'arrow-repeat' : 'play-fill'}"></i>
                                        ${isCompleted ? 'Пройти снова' : 'Начать шаг'}
                                    </button>
                                    ${isCompleted && step.score !== null ? `
                                        <span class="ms-3 text-muted">
                                            Ваш результат: <strong>${step.score}%</strong>
                                        </span>
                                    ` : ''}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async function startStep(stepId) {
        try {
            const result = await apiCall('getStepContent', {
                path_id: currentPathId,
                step_id: stepId
            });

            if (result.success) {
                const step = result.data;

                // Перенаправляем на соответствующую страницу в зависимости от типа шага
                switch (step.step_type) {
                    case 'material':
                        window.location.href = `/learning-material?id=${step.content_id}&path=${currentPathId}&step=${stepId}`;
                        break;
                    case 'test':
                        window.location.href = `/test-runner?test=${step.content_id}&path=${currentPathId}&step=${stepId}`;
                        break;
                    case 'quiz':
                        window.location.href = `/quiz?id=${step.content_id}&path=${currentPathId}&step=${stepId}`;
                        break;
                    case 'assignment':
                        window.location.href = `/assignment?id=${step.content_id}&path=${currentPathId}&step=${stepId}`;
                        break;
                    default:
                        throw new Error('Неизвестный тип шага');
                }
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Start step error:', error);
            showNotification('Ошибка запуска шага: ' + error.message, 'danger');
        }
    }

    // ==================== PATH EDITOR ====================

    function initPathEditor(pathId) {
        currentPathId = pathId;

        if (pathId) {
            loadPathForEdit(pathId);
        }

        // Event listeners
        document.getElementById('add-step-btn')?.addEventListener('click', showAddStepModal);
        document.getElementById('save-step-btn')?.addEventListener('click', saveStep);
        document.getElementById('save-steps-order-btn')?.addEventListener('click', saveStepsOrder);
    }

    async function loadPathForEdit(pathId) {
        try {
            const result = await apiCall('getPath', { path_id: pathId, with_steps: 1 });

            if (result.success) {
                document.getElementById('editor-path-title').textContent = result.data.name;
                pathSteps = result.data.steps || [];
                renderStepsEditor();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Load path for edit error:', error);
            showNotification('Ошибка загрузки траектории: ' + error.message, 'danger');
        }
    }

    function renderStepsEditor() {
        const container = document.getElementById('steps-editor-container');

        if (pathSteps.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Шаги не добавлены. Добавьте первый шаг для создания траектории обучения.
                </div>
            `;
            return;
        }

        let html = '<div class="steps-sortable">';

        pathSteps.forEach((step, index) => {
            html += `
                <div class="card mb-3 step-editor-card" data-step-id="${step.id}">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-grip-vertical handle" style="cursor: move;"></i>
                            <strong>Шаг ${index + 1}: ${escapeHtml(step.name)}</strong>
                            <span class="badge bg-secondary ms-2">${STEP_TYPE_LABELS[step.step_type]}</span>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary"
                                    onclick="LearningPaths.editStep(${index})"
                                    title="Редактировать">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger"
                                    onclick="LearningPaths.removeStep(${index})"
                                    title="Удалить">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-0">${escapeHtml(step.description || 'Нет описания')}</p>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;

        // Инициализируем сортировку (если есть библиотека Sortable.js)
        // const sortable = new Sortable(container.querySelector('.steps-sortable'), { ... });
    }

    function showAddStepModal() {
        document.getElementById('step-modal-title').textContent = 'Добавить шаг';
        document.getElementById('step-title').value = '';
        document.getElementById('step-description').value = '';
        document.getElementById('step-type').value = 'material';
        document.getElementById('step-content-id').value = '';
        document.getElementById('step-unlock-previous').checked = true;
        document.getElementById('step-unlock-min-score').value = '';

        const modal = new bootstrap.Modal(document.getElementById('stepModal'));
        modal.show();
    }

    async function saveStep() {
        const title = document.getElementById('step-title').value.trim();
        const description = document.getElementById('step-description').value.trim();
        const stepType = document.getElementById('step-type').value;
        const contentId = document.getElementById('step-content-id').value;
        const unlockPrevious = document.getElementById('step-unlock-previous').checked;
        const unlockMinScore = parseInt(document.getElementById('step-unlock-min-score').value) || null;

        if (!title) {
            showNotification('Введите название шага', 'warning');
            return;
        }

        if (!contentId) {
            showNotification('Укажите ID контента', 'warning');
            return;
        }

        const saveBtn = document.getElementById('save-step-btn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Сохранение...';

        try {
            const unlockRequirements = {
                previous_step: unlockPrevious,
                min_score: unlockMinScore
            };

            const result = await apiCall('addStep', {
                path_id: currentPathId,
                name: title,
                description: description,
                step_type: stepType,
                item_id: parseInt(contentId),
                unlock_condition: unlockRequirements
            });

            if (result.success) {
                showNotification('Шаг добавлен', 'success');

                const modal = bootstrap.Modal.getInstance(document.getElementById('stepModal'));
                modal.hide();

                await loadPathForEdit(currentPathId);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Save step error:', error);
            showNotification('Ошибка сохранения шага: ' + error.message, 'danger');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Добавить';
        }
    }

    let editingStepIndex = null;

    function editStep(index) {
        const step = pathSteps[index];
        editingStepIndex = index;

        document.getElementById('step-modal-title').textContent = 'Редактировать шаг';
        document.getElementById('step-title').value = step.name || '';
        document.getElementById('step-description').value = step.description || '';
        document.getElementById('step-type').value = step.step_type || 'material';
        document.getElementById('step-content-id').value = step.item_id || '';

        // Unlock conditions
        const unlockCondition = step.unlock_condition || {};
        document.getElementById('step-unlock-previous').checked = unlockCondition.previous_step !== false;
        document.getElementById('step-unlock-min-score').value = unlockCondition.min_score || '';

        // Показываем кнопку обновления вместо добавления
        const saveBtn = document.getElementById('save-step-btn');
        saveBtn.textContent = 'Обновить';
        saveBtn.onclick = () => updateStepFromModal();

        const modal = new bootstrap.Modal(document.getElementById('stepModal'));
        modal.show();
    }

    async function updateStepFromModal() {
        if (editingStepIndex === null) return;

        const step = pathSteps[editingStepIndex];
        const title = document.getElementById('step-title').value.trim();
        const description = document.getElementById('step-description').value.trim();
        const stepType = document.getElementById('step-type').value;
        const contentId = document.getElementById('step-content-id').value;
        const unlockPrevious = document.getElementById('step-unlock-previous').checked;
        const unlockMinScore = parseInt(document.getElementById('step-unlock-min-score').value) || null;

        if (!title) {
            showNotification('Введите название шага', 'warning');
            return;
        }

        if (!contentId) {
            showNotification('Укажите ID контента', 'warning');
            return;
        }

        const saveBtn = document.getElementById('save-step-btn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Сохранение...';

        try {
            const unlockCondition = {
                previous_step: unlockPrevious,
                min_score: unlockMinScore
            };

            const result = await apiCall('updateStep', {
                step_id: step.id,
                name: title,
                description: description,
                step_type: stepType,
                item_id: parseInt(contentId),
                unlock_condition: unlockCondition
            });

            if (result.success) {
                showNotification('Шаг обновлён', 'success');

                const modal = bootstrap.Modal.getInstance(document.getElementById('stepModal'));
                modal.hide();

                editingStepIndex = null;
                await loadPathForEdit(currentPathId);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Update step error:', error);
            showNotification('Ошибка обновления шага: ' + error.message, 'danger');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Обновить';
        }
    }

    // Сбрасываем состояние модалки при её закрытии
    document.addEventListener('DOMContentLoaded', function() {
        const stepModal = document.getElementById('stepModal');
        if (stepModal) {
            stepModal.addEventListener('hidden.bs.modal', function() {
                editingStepIndex = null;
                const saveBtn = document.getElementById('save-step-btn');
                if (saveBtn) {
                    saveBtn.textContent = 'Добавить';
                    saveBtn.onclick = saveStep;
                }
            });
        }
    });

    function removeStep(index) {
        if (confirm('Удалить этот шаг?')) {
            const step = pathSteps[index];

            apiCall('deleteStep', {
                step_id: step.id
            }).then(result => {
                if (result.success) {
                    showNotification('Шаг удален', 'success');
                    loadPathForEdit(currentPathId);
                } else {
                    throw new Error(result.message);
                }
            }).catch(error => {
                console.error('Remove step error:', error);
                showNotification('Ошибка удаления шага: ' + error.message, 'danger');
            });
        }
    }

    async function saveStepsOrder() {
        const stepElements = document.querySelectorAll('.step-editor-card');
        const order = [];

        stepElements.forEach((el, index) => {
            order.push({
                step_id: parseInt(el.dataset.stepId),
                order_position: index
            });
        });

        try {
            const result = await apiCall('reorderSteps', {
                path_id: currentPathId,
                step_order: order
            });

            if (result.success) {
                showNotification('Порядок шагов сохранен', 'success');
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Save order error:', error);
            showNotification('Ошибка сохранения порядка: ' + error.message, 'danger');
        }
    }

    // ==================== UTILITY FUNCTIONS ====================

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        notification.style.zIndex = '9999';
        notification.style.minWidth = '300px';
        notification.innerHTML = `
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);

        setTimeout(() => notification.remove(), 4000);
    }

    // ==================== PUBLIC API ====================

    window.LearningPaths = {
        loadPaths,
        deletePath,
        loadPath,
        startStep,
        editStep,
        removeStep,
        saveStepsOrder,
        showCloneModal,
        clonePath
    };

})();
