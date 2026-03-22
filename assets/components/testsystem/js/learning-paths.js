/**
 * LEARNING PATHS MANAGER - Sprint 11
 * Управление траекториями обучения
 * Последовательные шаги, unlock условия, прогресс, сертификаты
 */

(function() {
    'use strict';

    const API_URL = '/assets/components/testsystem/ajax/testsystem.php';

    // Get base path for URLs (e.g., /learning-paths)
    function getBasePath() {
        return window.location.pathname;
    }

    // CSRF Protection
    function getCsrfToken() {
        return window.TestSystemCSRF ? window.TestSystemCSRF.getToken() : null;
    }

    // XSS Protection
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // Escape string for use inside JS onclick attribute (single-quoted string literal)
    function escapeForOnclick(text) {
        return escapeHtml(text)
            .replace(/\\/g, '\\\\')
            .replace(/"/g, '&quot;')
            .replace(/'/g, "\\'")
            .replace(/\r/g, '')
            .replace(/\n/g, ' ');
    }

    // State
    let currentPathId = null;
    let pathSteps = [];
    let userProgress = {};
    let isEnrolled = false;

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
            return await window.TestSystemCSRF.apiCall(action, data, { apiUrl: API_URL });
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

        // Filter listeners - reload from server
        document.getElementById('filter-status')?.addEventListener('change', loadPaths);
        document.getElementById('filter-template')?.addEventListener('change', loadPaths);

        // Client-side filters
        document.getElementById('filter-difficulty')?.addEventListener('change', filterPathsClient);
        document.getElementById('filter-search')?.addEventListener('input', filterPathsClient);
    }

    async function loadPaths() {
        const container = document.getElementById('learning-paths-container');
        container.innerHTML = `
            <div class="col-12 text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
            </div>
        `;

        try {
            // Collect filter values
            const filters = {};

            const statusFilter = document.getElementById('filter-status')?.value;
            if (statusFilter) {
                filters.status = statusFilter;
            }

            const templateFilter = document.getElementById('filter-template')?.value;
            if (templateFilter !== '' && templateFilter !== undefined) {
                filters.is_template = parseInt(templateFilter);
            }

            const result = await apiCall('getPathsList', filters);

            if (result.success) {
                renderPathsList(result.data || []);
                // Apply client-side filters after rendering
                filterPathsClient();
            } else {
                throw new Error(result.message || 'Ошибка загрузки траекторий');
            }
        } catch (error) {
            console.error('Load paths error:', error);
            container.innerHTML = `
                <div class="ts-alert ts-alert-danger">Ошибка загрузки траекторий: ${escapeHtml(error.message)}</div>
            `;
        }
    }

    function filterPathsClient() {
        const difficulty = document.getElementById('filter-difficulty')?.value || '';
        const search = (document.getElementById('filter-search')?.value || '').toLowerCase();

        document.querySelectorAll('.path-card').forEach(card => {
            const matchesDifficulty = !difficulty || card.dataset.difficulty === difficulty;
            const cardTitle = card.querySelector('.card-title')?.textContent?.toLowerCase() || '';
            const cardDesc = card.querySelector('.card-text')?.textContent?.toLowerCase() || '';
            const matchesSearch = !search || cardTitle.includes(search) || cardDesc.includes(search);

            card.style.display = matchesDifficulty && matchesSearch ? '' : 'none';
        });
    }

    function renderPathsList(paths) {
        const container = document.getElementById('learning-paths-container');

        if (!paths || paths.length === 0) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="ts-empty-state ts-learning-paths-empty-state">
                        <i class="bi bi-signpost ts-empty-state-icon"></i>
                        <h3 class="ts-empty-state-title">Траектории обучения не найдены</h3>
                        <p class="ts-empty-state-text">Создайте первую траекторию для структурированного обучения.</p>
                    </div>
                </div>
            `;
            return;
        }

        let html = '';

        paths.forEach(path => {
            const progress = path.completion_pct || 0;
            const progressClass = progress === 100 ? 'ts-learning-path-progress-bar-success' :
                                  progress > 0 ? 'ts-learning-path-progress-bar-active' : 'ts-learning-path-progress-bar-idle';

            const difficultyBadge = {
                'beginner': '<span class="ts-learning-path-tag ts-learning-path-tag-success">Начальный</span>',
                'intermediate': '<span class="ts-learning-path-tag ts-learning-path-tag-warning">Средний</span>',
                'advanced': '<span class="ts-learning-path-tag ts-learning-path-tag-danger">Продвинутый</span>',
                'expert': '<span class="ts-learning-path-tag ts-learning-path-tag-neutral">Эксперт</span>'
            }[path.difficulty_level] || '';

            const statusBadge = path.status === 'published'
                ? '<span class="ts-learning-path-tag ts-learning-path-tag-success">Опубликован</span>'
                : '<span class="ts-learning-path-tag ts-learning-path-tag-neutral">Черновик</span>';

            // Badge для шаблона
            const templateBadge = path.is_template
                ? '<span class="ts-learning-path-tag ts-learning-path-tag-primary"><i class="bi bi-file-earmark-text"></i> Шаблон</span>'
                : '';

            // Если это клон - показываем ссылку на родителя
            const cloneBadge = path.parent_id
                ? '<span class="ts-learning-path-tag ts-learning-path-tag-muted"><i class="bi bi-copy"></i> Клон</span>'
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
                        ${isOverdue ? '<span class="ts-learning-path-tag ts-learning-path-tag-danger">Просрочено</span>' : ''}
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
                <div class="col-md-6 col-xl-4 path-card"
                     data-category="${path.category_id || ''}"
                     data-difficulty="${path.difficulty_level || ''}"
                     data-is-template="${path.is_template || 0}">
                    <div class="card h-100 ts-learning-path-card ${path.is_template ? 'ts-learning-path-card-template' : ''}">
                        ${path.thumbnail_url ? `
                            <img src="${escapeHtml(path.thumbnail_url)}"
                                 class="card-img-top ts-learning-path-thumb"
                                 alt="${escapeHtml(path.name)}"
                                >
                        ` : `
                            <div class="card-img-top ts-learning-path-cover d-flex align-items-center justify-content-center ${path.is_template ? 'ts-learning-path-cover-template' : ''}">
                                <i class="bi bi-${path.is_template ? 'file-earmark-text' : 'signpost'} ts-learning-path-cover-icon"></i>
                            </div>
                        `}
                        <div class="card-body ts-learning-path-body">
                            <h5 class="card-title ts-learning-path-title">${escapeHtml(path.name)}</h5>
                            <p class="card-text text-muted small ts-learning-path-description">${escapeHtml(path.description || 'Нет описания')}</p>

                            <div class="mb-2 ts-learning-path-badges">
                                ${templateBadge}
                                ${cloneBadge}
                                ${statusBadge}
                                ${difficultyBadge}
                                ${path.certificate_template ? '<span class="ts-learning-path-tag ts-learning-path-tag-primary"><i class="bi bi-patch-check"></i> С сертификатом</span>' : ''}
                            </div>

                            ${assignmentInfo}

                            <div class="d-flex justify-content-between small mb-2 ts-learning-path-meta">
                                <span><i class="bi bi-list-ol"></i> ${path.steps_count || 0} шагов</span>
                                <span><i class="bi bi-clock"></i> ~${path.estimated_hours || 0} ч</span>
                            </div>

                            ${progress > 0 ? `
                                <div class="progress mb-1 ts-learning-path-progress">
                                    <div class="progress-bar ${progressClass}"
                                         role="progressbar"
                                         style="width: ${progress}%">
                                    </div>
                                </div>
                                <p class="small ts-learning-path-progress-text mb-0">Прогресс: ${progress}%</p>
                            ` : ''}
                        </div>
                        <div class="card-footer ts-learning-path-footer">
                            <div class="d-grid gap-2">
                                ${path.is_template ? `
                                    <button class="ts-btn ts-btn-secondary ts-btn-sm w-100"
                                            onclick="LearningPaths.showCloneModal(${path.id}, '${escapeForOnclick(path.name)}')">
                                        <i class="bi bi-copy"></i> Клонировать шаблон
                                    </button>
                                ` : `
                                    <a href="${getBasePath()}?mode=view&id=${path.id}" class="ts-btn ts-btn-primary w-100">
                                        <i class="bi bi-play-fill"></i>
                                        ${progress > 0 ? 'Продолжить' : 'Начать'}
                                    </a>
                                `}
                                ${path.can_edit ? `
                                    <button class="ts-btn ts-btn-secondary ts-btn-sm w-100"
                                            onclick="LearningPaths.showEnrollModal(${path.id}, '${escapeForOnclick(path.name)}')">
                                        <i class="bi bi-person-plus"></i> Назначить студентам
                                    </button>
                                    <div class="w-100 ts-learning-path-actions">
                                        <a href="${getBasePath()}?mode=edit&id=${path.id}" class="ts-btn ts-btn-secondary ts-btn-sm">
                                            <i class="bi bi-pencil"></i> Редакт.
                                        </a>
                                        ${path.is_template ? '' : `
                                            <button class="ts-btn ts-btn-secondary ts-btn-sm"
                                                    onclick="LearningPaths.showCloneModal(${path.id}, '${escapeForOnclick(path.name)}')"
                                                    title="Клонировать">
                                                <i class="bi bi-files"></i> Клон
                                            </button>
                                        `}
                                        <button class="ts-btn ts-btn-ghost-danger ts-btn-sm"
                                                onclick="LearningPaths.deletePath(${path.id}, '${escapeForOnclick(path.name)}')">
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

    // ==================== BULK ENROLLMENT ====================

    let enrollPathId = null;
    let enrollPathName = '';
    let availableStudents = [];

    async function showEnrollModal(pathId, pathName) {
        enrollPathId = pathId;
        enrollPathName = pathName;

        // Создаём модалку если её нет
        let enrollModal = document.getElementById('enrollModal');
        if (!enrollModal) {
            enrollModal = document.createElement('div');
            enrollModal.innerHTML = `
                <div class="modal fade" id="enrollModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Назначить траекторию студентам</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    Траектория: <strong id="enroll-path-name"></strong>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Дедлайн (опционально)</label>
                                    <input type="datetime-local" class="form-control" id="enroll-deadline">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Выберите студентов</label>
                                    <div class="input-group mb-2">
                                        <input type="text" class="form-control" id="enroll-search"
                                               placeholder="Поиск по имени или email...">
                                        <button class="btn btn-outline-secondary" type="button" id="enroll-select-all">
                                            Выбрать всех
                                        </button>
                                    </div>
                                    <div id="enroll-students-list" class="border rounded p-2"
                                         style="max-height: 300px; overflow-y: auto;">
                                        <div class="text-center py-3">
                                            <span class="spinner-border spinner-border-sm"></span> Загрузка...
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-secondary" id="enroll-selected-count">
                                    Выбрано: <strong>0</strong> студентов
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                                <button type="button" class="btn btn-primary" id="enroll-submit-btn">
                                    <i class="bi bi-person-plus"></i> Назначить
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(enrollModal.firstElementChild);

            // Обработчики
            document.getElementById('enroll-submit-btn').addEventListener('click', submitBulkEnroll);
            document.getElementById('enroll-search').addEventListener('input', filterStudentsList);
            document.getElementById('enroll-select-all').addEventListener('click', toggleSelectAllStudents);
        }

        document.getElementById('enroll-path-name').textContent = pathName;
        document.getElementById('enroll-deadline').value = '';
        document.getElementById('enroll-search').value = '';

        const modal = new bootstrap.Modal(document.getElementById('enrollModal'));
        modal.show();

        // Загружаем список студентов
        loadAvailableStudents(pathId);
    }

    async function loadAvailableStudents(pathId) {
        const container = document.getElementById('enroll-students-list');
        container.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span> Загрузка...</div>';

        try {
            const result = await apiCall('getAvailableStudents', { path_id: pathId });

            if (result.success) {
                availableStudents = result.data || [];
                renderStudentsList(availableStudents);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Load students error:', error);
            container.innerHTML = `<div class="alert alert-danger mb-0">Ошибка загрузки: ${escapeHtml(error.message)}</div>`;
        }
    }

    function renderStudentsList(students) {
        const container = document.getElementById('enroll-students-list');

        if (!students || students.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-3">Нет доступных студентов</div>';
            return;
        }

        let html = '';
        students.forEach(student => {
            const isEnrolled = student.is_enrolled == 1;
            const displayName = student.fullname || student.username;

            html += `
                <div class="form-check student-item mb-2 p-2 rounded ${isEnrolled ? 'bg-light' : ''}"
                     data-name="${escapeHtml(displayName.toLowerCase())}"
                     data-email="${escapeHtml((student.email || '').toLowerCase())}">
                    <input class="form-check-input student-checkbox" type="checkbox"
                           value="${student.id}"
                           id="student-${student.id}"
                           ${isEnrolled ? 'disabled checked' : ''}>
                    <label class="form-check-label w-100" for="student-${student.id}">
                        <div class="d-flex justify-content-between">
                            <div>
                                <strong>${escapeHtml(displayName)}</strong>
                                ${student.email ? `<br><small class="text-muted">${escapeHtml(student.email)}</small>` : ''}
                            </div>
                            <div>
                                ${isEnrolled ? `
                                    <span class="badge bg-success">Уже записан</span>
                                    ${student.completion_pct ? `<br><small class="text-muted">Прогресс: ${student.completion_pct}%</small>` : ''}
                                ` : ''}
                            </div>
                        </div>
                    </label>
                </div>
            `;
        });

        container.innerHTML = html;

        // Добавляем обработчик изменения для обновления счётчика
        container.querySelectorAll('.student-checkbox').forEach(cb => {
            cb.addEventListener('change', updateSelectedCount);
        });

        updateSelectedCount();
    }

    function filterStudentsList() {
        const query = document.getElementById('enroll-search').value.toLowerCase();
        const items = document.querySelectorAll('.student-item');

        items.forEach(item => {
            const name = item.dataset.name || '';
            const email = item.dataset.email || '';
            const visible = name.includes(query) || email.includes(query);
            item.style.display = visible ? '' : 'none';
        });
    }

    function toggleSelectAllStudents() {
        const checkboxes = document.querySelectorAll('.student-checkbox:not(:disabled)');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);

        checkboxes.forEach(cb => {
            if (cb.closest('.student-item').style.display !== 'none') {
                cb.checked = !allChecked;
            }
        });

        updateSelectedCount();
    }

    function updateSelectedCount() {
        const checkboxes = document.querySelectorAll('.student-checkbox:not(:disabled):checked');
        document.getElementById('enroll-selected-count').innerHTML = `Выбрано: <strong>${checkboxes.length}</strong> студентов`;
    }

    async function submitBulkEnroll() {
        const selectedIds = [];
        document.querySelectorAll('.student-checkbox:not(:disabled):checked').forEach(cb => {
            selectedIds.push(parseInt(cb.value));
        });

        if (selectedIds.length === 0) {
            showNotification('Выберите хотя бы одного студента', 'warning');
            return;
        }

        const deadline = document.getElementById('enroll-deadline').value || null;

        const submitBtn = document.getElementById('enroll-submit-btn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Назначение...';

        try {
            const result = await apiCall('bulkEnrollOnPath', {
                path_id: enrollPathId,
                user_ids: selectedIds,
                deadline: deadline
            });

            if (result.success) {
                const msg = `Назначено ${result.data.enrolled_count} из ${result.data.total_users} студентов`;
                showNotification(msg, 'success');

                const modal = bootstrap.Modal.getInstance(document.getElementById('enrollModal'));
                modal.hide();

                loadPaths();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Bulk enroll error:', error);
            showNotification('Ошибка назначения: ' + error.message, 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-person-plus"></i> Назначить';
        }
    }

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
                    window.location.href = `${getBasePath()}?mode=edit&id=${result.data.path_id}`;
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

                // API возвращает path_id в result.data.path_id
                const newPathId = result.data?.path_id;

                // Если мы на странице редактирования - обновляем данные
                const editorContainer = document.getElementById('path-editor-container');
                const pathsContainer = document.getElementById('learning-paths-container');

                if (editorContainer && currentPathId) {
                    loadPathForEdit(currentPathId);
                } else if (!currentPathId && newPathId) {
                    // Новая траектория - переходим в редактор
                    window.location.href = `${getBasePath()}?mode=edit&id=${newPathId}`;
                } else if (pathsContainer) {
                    // На странице списка - обновляем список
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
                isEnrolled = result.data.is_enrolled || false;
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

                ${isEnrolled ? `
                    <div class="d-flex align-items-center mb-4">
                        <div class="progress flex-grow-1 me-3" style="height: 25px;">
                            <div class="progress-bar bg-success progress-bar-striped ${progress < 100 ? 'progress-bar-animated' : ''}"
                                 role="progressbar"
                                 style="width: ${progress}%">
                                ${progress}%
                            </div>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm" onclick="LearningPaths.unenrollFromPath(${data.id})">
                            <i class="bi bi-x-circle me-1"></i> Отписаться
                        </button>
                    </div>
                ` : `
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-info-circle me-2"></i>
                        Вы ещё не записаны на эту траекторию обучения.
                        <button class="btn btn-primary btn-sm ms-3" onclick="LearningPaths.enrollOnPath(${data.id})">
                            <i class="bi bi-plus-circle me-1"></i> Записаться
                        </button>
                    </div>
                `}
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
        const isContentUnavailable = step.content_available === false;

        const statusIcon = isCompleted ? '<i class="bi bi-check-circle-fill text-success fs-4"></i>' :
                          isLocked ? '<i class="bi bi-lock-fill text-muted fs-4"></i>' :
                          isContentUnavailable ? '<i class="bi bi-exclamation-triangle-fill text-danger fs-4"></i>' :
                          '<i class="bi bi-circle text-primary fs-4"></i>';

        const cardClass = isCompleted ? 'border-success' :
                         isCurrent && !isContentUnavailable ? 'border-primary' :
                         isContentUnavailable ? 'border-danger' :
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

        // Предупреждение о недоступном контенте
        let contentUnavailableWarning = '';
        if (isContentUnavailable) {
            contentUnavailableWarning = `
                <div class="alert alert-danger small mb-0 mt-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>Контент недоступен:</strong><br>
                    ${escapeHtml(step.content_error || 'Контент был удален или не опубликован. Обратитесь к администратору.')}
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
                            ${contentUnavailableWarning}

                            ${!isLocked && isEnrolled && !isContentUnavailable ? `
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

                // Перенаправляем на URL контента, возвращённый сервером
                if (step.content_url) {
                    window.location.href = step.content_url;
                } else {
                    throw new Error('URL контента не получен от сервера');
                }
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Start step error:', error);
            showNotification('Ошибка запуска шага: ' + error.message, 'danger');
        }
    }

    async function enrollOnPath(pathId) {
        try {
            const result = await apiCall('enrollOnPath', {
                path_id: pathId
            });

            if (result.success) {
                showNotification('Вы успешно записались на траекторию!', 'success');
                // Перезагружаем страницу, чтобы обновить состояние
                await loadPath(pathId);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Enroll error:', error);
            showNotification('Ошибка записи на траекторию: ' + error.message, 'danger');
        }
    }

    async function unenrollFromPath(pathId) {
        if (!confirm('Вы уверены, что хотите отписаться от траектории? Ваш прогресс будет сохранён.')) {
            return;
        }

        try {
            const result = await apiCall('unenrollFromPath', {
                path_id: pathId
            });

            if (result.success) {
                showNotification('Вы отписались от траектории', 'success');
                // Перезагружаем страницу, чтобы обновить состояние
                await loadPath(pathId);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Unenroll error:', error);
            showNotification('Ошибка отписки: ' + error.message, 'danger');
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

    // Store current path data for editing
    let currentPathData = null;

    async function loadPathForEdit(pathId) {
        try {
            const result = await apiCall('getPath', { path_id: pathId, with_steps: 1 });

            if (result.success) {
                currentPathData = result.data;
                currentPathId = pathId;

                // Update UI
                document.getElementById('editor-path-title').textContent = result.data.name;

                const descEl = document.getElementById('editor-path-description');
                if (descEl) {
                    descEl.textContent = result.data.description || 'Нет описания';
                }

                const statusEl = document.getElementById('editor-path-status');
                if (statusEl) {
                    const statusLabels = { published: 'Опубликован', draft: 'Черновик', archived: 'В архиве' };
                    const statusColors = { published: 'success', draft: 'secondary', archived: 'warning' };
                    statusEl.textContent = statusLabels[result.data.status] || result.data.status;
                    statusEl.className = `badge bg-${statusColors[result.data.status] || 'secondary'}`;
                }

                const diffEl = document.getElementById('editor-path-difficulty');
                if (diffEl) {
                    const diffLabels = { beginner: 'Начальный', intermediate: 'Средний', advanced: 'Продвинутый', expert: 'Эксперт' };
                    diffEl.textContent = diffLabels[result.data.difficulty_level] || result.data.difficulty_level;
                }

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

    // Show settings modal for current path
    function showEditPathSettings() {
        if (!currentPathData) {
            showNotification('Данные траектории не загружены', 'warning');
            return;
        }

        document.getElementById('path-modal-title').textContent = 'Настройки траектории';
        document.getElementById('path-title').value = currentPathData.name || '';
        document.getElementById('path-description').value = currentPathData.description || '';
        document.getElementById('path-difficulty').value = currentPathData.difficulty_level || 'beginner';
        document.getElementById('path-estimated-hours').value = currentPathData.estimated_hours || '';
        document.getElementById('path-has-certificate').checked = !!currentPathData.certificate_template;
        document.getElementById('path-published').checked = currentPathData.status === 'published';

        const templateCheckbox = document.getElementById('path-is-template');
        if (templateCheckbox) {
            templateCheckbox.checked = currentPathData.is_template == 1;
        }

        const modal = new bootstrap.Modal(document.getElementById('pathModal'));
        modal.show();
    }

    // Show students management modal
    function showManageStudents() {
        if (!currentPathId) {
            showNotification('Траектория не выбрана', 'warning');
            return;
        }

        showEnrollModal(currentPathId, currentPathData?.name || 'Траектория');
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
            const isContentUnavailable = step.content_available === false;
            const warningBadge = isContentUnavailable
                ? '<span class="badge bg-danger ms-2"><i class="bi bi-exclamation-triangle-fill"></i> Контент недоступен</span>'
                : '';
            const warningMessage = isContentUnavailable
                ? `<div class="alert alert-danger mb-2 mt-2 small">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    ${escapeHtml(step.content_error || 'Контент был удален или не опубликован')}
                   </div>`
                : '';

            html += `
                <div class="card mb-3 step-editor-card ${isContentUnavailable ? 'border-danger' : ''}" data-step-id="${step.id}">
                    <div class="card-header ${isContentUnavailable ? 'bg-danger bg-opacity-10' : 'bg-light'} d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-grip-vertical handle" style="cursor: move;"></i>
                            <strong>Шаг ${index + 1}: ${escapeHtml(step.name)}</strong>
                            <span class="badge bg-secondary ms-2">${STEP_TYPE_LABELS[step.step_type]}</span>
                            ${warningBadge}
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
                        ${warningMessage}
                        <p class="text-muted mb-0">${escapeHtml(step.description || 'Нет описания')}</p>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;

        // Инициализируем drag-and-drop для шагов
        initStepsDragAndDrop();
    }

    /**
     * Инициализация drag-and-drop для шагов траектории
     * Использует нативный HTML5 Drag and Drop API
     */
    function initStepsDragAndDrop() {
        const container = document.querySelector('.steps-sortable');
        if (!container) return;

        const cards = container.querySelectorAll('.step-editor-card');
        let draggedElement = null;

        cards.forEach(card => {
            // Делаем карточку перетаскиваемой
            card.setAttribute('draggable', 'true');

            // Обработчик начала перетаскивания
            card.addEventListener('dragstart', function(e) {
                draggedElement = this;
                this.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', this.innerHTML);
            });

            // Обработчик окончания перетаскивания
            card.addEventListener('dragend', function(e) {
                this.classList.remove('dragging');
                // Удаляем все визуальные индикаторы
                container.querySelectorAll('.step-editor-card').forEach(c => {
                    c.classList.remove('drag-over');
                });
            });

            // Обработчик входа элемента в зону
            card.addEventListener('dragenter', function(e) {
                if (this !== draggedElement) {
                    this.classList.add('drag-over');
                }
            });

            // Обработчик выхода элемента из зоны
            card.addEventListener('dragleave', function(e) {
                this.classList.remove('drag-over');
            });

            // Обработчик перемещения над элементом
            card.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                if (this === draggedElement) return;

                // Определяем, куда вставить элемент (до или после текущего)
                const afterElement = getDragAfterElement(container, e.clientY);
                if (afterElement == null) {
                    container.appendChild(draggedElement);
                } else {
                    container.insertBefore(draggedElement, afterElement);
                }
            });

            // Обработчик отпускания элемента
            card.addEventListener('drop', function(e) {
                e.stopPropagation();
                e.preventDefault();
                this.classList.remove('drag-over');
                return false;
            });
        });
    }

    /**
     * Получить элемент, после которого нужно вставить перетаскиваемый элемент
     */
    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.step-editor-card:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    // Content selection state
    let selectedContentId = null;
    let selectedContentName = '';
    let availableContent = { tests: [], materials: [] };

    function showAddStepModal() {
        document.getElementById('step-modal-title').textContent = 'Добавить шаг';
        document.getElementById('step-title').value = '';
        document.getElementById('step-description').value = '';
        document.getElementById('step-type').value = 'material';
        document.getElementById('step-content-id').value = '';
        document.getElementById('step-content-search').value = '';
        document.getElementById('step-unlock-previous').checked = true;
        document.getElementById('step-unlock-min-score').value = '';

        // Reset content selection
        selectedContentId = null;
        selectedContentName = '';
        document.getElementById('step-content-selected').classList.add('d-none');
        document.getElementById('step-content-list').innerHTML = `
            <div class="text-muted text-center py-3">
                <i class="bi bi-arrow-up"></i> Начните поиск или нажмите кнопку для загрузки списка
            </div>
        `;

        // Add event listeners for content selection
        const stepType = document.getElementById('step-type');
        const searchBtn = document.getElementById('step-content-search-btn');
        const searchInput = document.getElementById('step-content-search');

        stepType.onchange = () => loadAvailableContent();
        searchBtn.onclick = () => loadAvailableContent();
        searchInput.onkeypress = (e) => { if (e.key === 'Enter') loadAvailableContent(); };

        const modal = new bootstrap.Modal(document.getElementById('stepModal'));
        modal.show();
    }

    async function loadAvailableContent() {
        const stepType = document.getElementById('step-type').value;
        const search = document.getElementById('step-content-search').value.trim();
        const container = document.getElementById('step-content-list');

        container.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span> Загрузка...</div>';

        try {
            const result = await apiCall('getAvailableContent', {
                step_type: stepType,
                search: search
            });

            if (result.success) {
                availableContent = result.data;
                renderContentList(stepType);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Load content error:', error);
            container.innerHTML = `<div class="alert alert-danger mb-0">Ошибка загрузки: ${escapeHtml(error.message)}</div>`;
        }
    }

    function renderContentList(stepType) {
        const container = document.getElementById('step-content-list');
        let items = [];

        if (stepType === 'material') {
            items = availableContent.materials || [];
        } else if (stepType === 'test' || stepType === 'quiz') {
            items = availableContent.tests || [];
        } else {
            items = [...(availableContent.materials || []), ...(availableContent.tests || [])];
        }

        if (items.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-3">Контент не найден</div>';
            return;
        }

        let html = '';
        items.forEach(item => {
            const isSelected = selectedContentId === item.id;
            const extra = item.questions_count ? `<small class="text-muted">${item.questions_count} вопр.</small>` :
                          item.parent_name ? `<small class="text-muted">${escapeHtml(item.parent_name)}</small>` : '';

            html += `
                <div class="content-item d-flex justify-content-between align-items-center p-2 mb-1 rounded
                            ${isSelected ? 'bg-primary text-white' : 'bg-light'}"
                     style="cursor: pointer;"
                     onclick="LearningPaths.selectContent(${item.id}, '${escapeForOnclick(item.name)}')">
                    <div>
                        <strong>${escapeHtml(item.name)}</strong>
                        <br>
                        <small class="text-${isSelected ? 'white-50' : 'muted'}">${escapeHtml(item.description || item.introtext || 'Нет описания')}</small>
                    </div>
                    <div>
                        ${extra}
                        <span class="badge bg-${isSelected ? 'light text-primary' : 'secondary'} ms-2">ID: ${item.id}</span>
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
    }

    function selectContent(id, name) {
        selectedContentId = id;
        selectedContentName = name;

        document.getElementById('step-content-id').value = id;
        document.getElementById('step-content-selected-name').textContent = name + ' (ID: ' + id + ')';
        document.getElementById('step-content-selected').classList.remove('d-none');

        // Автозаполнение названия шага
        const titleInput = document.getElementById('step-title');
        if (!titleInput.value) {
            titleInput.value = name;
        }

        // Обновляем визуальное выделение
        const stepType = document.getElementById('step-type').value;
        renderContentList(stepType);
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
        const order = {};

        stepElements.forEach((el, index) => {
            const stepId = parseInt(el.dataset.stepId);
            order[stepId] = index + 1; // step_number начинается с 1
        });

        try {
            const result = await apiCall('reorderSteps', {
                path_id: currentPathId,
                step_order: order
            });

            if (result.success) {
                showNotification('Порядок шагов сохранен', 'success');
                // Перезагружаем шаги для обновления нумерации
                await loadPathForEdit(currentPathId);
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
        enrollOnPath,
        unenrollFromPath,
        editStep,
        removeStep,
        saveStepsOrder,
        showCloneModal,
        clonePath,
        showEnrollModal,
        submitBulkEnroll,
        selectContent,
        loadAvailableContent,
        showEditPathSettings,
        showManageStudents
    };

})();
