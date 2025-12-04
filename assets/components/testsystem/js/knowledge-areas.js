/* KNOWLEDGE AREAS MANAGER v1.1 - WITH DISTRIBUTION MODES */

(function() {
    const API_URL = "/assets/components/testsystem/ajax/testsystem.php";

    // CSRF Protection: получаем токен из meta тега
    function getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.content : null;
    }

    const container = document.getElementById('knowledge-areas-container');
    const testPageUrl = container ? container.dataset.testPageUrl : '/tests/oblast-znanij/';
    const managePageUrl = container ? container.dataset.managePageUrl : '/moi-oblasti-znanij/';
    
    let allAreas = [];
    let allTestsTree = [];
    let selectedTestIds = new Set();
    let editingAreaId = null;
    let distributionMode = 'proportional';
    let minQuestionsPerTest = 3;
    
    // Инициализация
    document.addEventListener('DOMContentLoaded', function() {
        // Проверяем наличие контейнера - если его нет, это не страница областей знаний
        if (!container) {
            console.log('Knowledge areas container not found, skipping initialization');
            return;
        }

        loadAreas();

        document.getElementById('create-area-btn')?.addEventListener('click', openCreateModal);
        document.getElementById('save-area-btn')?.addEventListener('click', saveArea);
        document.getElementById('confirm-delete-area-btn')?.addEventListener('click', confirmDeleteArea);
        document.getElementById('set-max-questions')?.addEventListener('click', setMaxQuestions);
        document.getElementById('test-search-input')?.addEventListener('input', filterTests);
        
        // НОВОЕ: Обработчики для режима распределения
        document.getElementById('distribution-proportional')?.addEventListener('change', function() {
            distributionMode = 'proportional';
            updateDistributionPreview();
        });
        
        document.getElementById('distribution-equal')?.addEventListener('change', function() {
            distributionMode = 'equal';
            updateDistributionPreview();
        });
        
        document.getElementById('min-questions-per-test')?.addEventListener('input', function() {
            minQuestionsPerTest = parseInt(this.value) || 3;
            updateDistributionPreview();
        });
        
        document.getElementById('questions-per-session')?.addEventListener('input', updateDistributionPreview);
        
        const areaModal = document.getElementById('areaModal');
        if (areaModal) {
            areaModal.addEventListener('show.bs.modal', function() {
                if (!allTestsTree.length) {
                    loadTestsTree();
                }
            });
        }
    });


    // API вызовы
    async function apiCall(action, data = {}) {
        try {
            // CSRF Protection: добавляем токен в данные
            const csrfToken = getCsrfToken();
            if (csrfToken) {
                data.csrf_token = csrfToken;
            }

            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, data })
            });
            
            const result = await response.json();
            return result;
        } catch (error) {
            console.error('API Error:', error);
            showNotification('Ошибка соединения с сервером', 'danger');
            throw error;
        }
    }
    
    // Загрузка списка областей
    async function loadAreas() {
        try {
            const result = await apiCall('getKnowledgeAreas');
            
            if (result.success) {
                allAreas = result.data;
                renderAreas();
            } else {
                throw new Error(result.message || 'Ошибка загрузки областей');
            }
        } catch (error) {
            console.error('Load areas error:', error);
            // XSS Protection: экранируем сообщение об ошибке
            document.getElementById('areas-list-container').innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        Ошибка загрузки областей знаний: ${escapeHtml(error.message)}
                    </div>
                </div>
            `;
        }
    }
    
    // Отрисовка списка областей
    function renderAreas() {
        const container = document.getElementById('areas-list-container');
        
        if (!allAreas || allAreas.length === 0) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-light border text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted d-block mb-3"></i>
                        <h5 class="text-muted">У вас пока нет областей знаний</h5>
                        <p class="text-muted mb-0">Создайте первую область, чтобы начать комбинированное тестирование</p>
                    </div>
                </div>
            `;
            return;
        }
        
        let html = '';
        
        allAreas.forEach(area => {
            const testIds = JSON.parse(area.test_ids || '[]');
            const testsCount = area.tests_count || testIds.length;
            const questionsCount = area.questions_count || 0;
            const distModeText = area.question_distribution_mode === 'equal' ? '⚖️ Поровну' : '📊 Пропорц.';
            
            html += `
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 knowledge-area-card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-collection-fill text-primary"></i> 
                                ${escapeHtml(area.name)}
                            </h5>
                            ${area.description ? `<p class="card-text text-muted small">${escapeHtml(area.description)}</p>` : ''}
                            
                            <div class="area-stats mt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Тестов:</span>
                                    <strong>${testsCount}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Вопросов:</span>
                                    <strong class="text-success">~${questionsCount}</strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">За сессию:</span>
                                    <strong class="text-primary">${area.questions_per_session}</strong>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Режим:</span>
                                    <strong class="text-info">${distModeText}</strong>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white">
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="window.KAManager.startArea(${area.id})">
                                    <i class="bi bi-play-fill"></i> Пройти тест
                                </button>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-secondary" onclick="window.KAManager.editArea(${area.id})">
                                        <i class="bi bi-pencil"></i> Редактировать
                                    </button>
                                    <button class="btn btn-outline-danger" onclick="window.KAManager.deleteArea(${area.id}, '${escapeHtml(area.name).replace(/'/g, "\\'")}')">
                                        <i class="bi bi-trash"></i> Удалить
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
    // Загрузка дерева тестов
    async function loadTestsTree() {
        const container = document.getElementById('tests-tree-container');
        
        try {
            const result = await apiCall('getAvailableTestsTree');
            
            if (result.success) {
                allTestsTree = result.data;
                renderTestsTree();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Load tests tree error:', error);
            container.innerHTML = `<div class="alert alert-danger">Ошибка загрузки тестов</div>`;
        }
    }
    
    // Отрисовка дерева тестов
    function renderTestsTree(filterText = '') {
        const container = document.getElementById('tests-tree-container');
        
        if (!allTestsTree || allTestsTree.length === 0) {
            container.innerHTML = `<div class="alert alert-warning">Нет доступных тестов</div>`;
            return;
        }
        
        const lowerFilter = filterText.toLowerCase();
        let html = '';
        let totalTests = 0;
        let totalQuestions = 0;
        
        allTestsTree.forEach(category => {
            const filteredTests = category.tests.filter(test => {
                if (!filterText) return true;
                return test.test_title.toLowerCase().includes(lowerFilter) ||
                       category.category_title.toLowerCase().includes(lowerFilter);
            });
            
            if (filteredTests.length === 0) return;
            
            const categoryId = `category-${category.category_id}`;
            const allSelected = filteredTests.every(t => selectedTestIds.has(t.test_id));
            const someSelected = filteredTests.some(t => selectedTestIds.has(t.test_id));
            
            html += `
                <div class="category-block mb-3">
                    <div class="form-check category-header">
                        <input class="form-check-input" type="checkbox" 
                               id="${categoryId}" 
                               ${allSelected ? 'checked' : ''}
                               ${someSelected && !allSelected ? 'indeterminate' : ''}
                               onchange="window.KAManager.toggleCategory(${category.category_id})">
                        <label class="form-check-label fw-bold" for="${categoryId}">
                            <i class="bi bi-folder-fill text-warning"></i> 
                            ${escapeHtml(category.category_title)} 
                            <span class="badge bg-secondary">${filteredTests.length}</span>
                        </label>
                    </div>
                    <div class="tests-list ms-4">
            `;
            
            filteredTests.forEach(test => {
                const testId = test.test_id;
                const isChecked = selectedTestIds.has(testId);
                
                html += `
                    <div class="form-check test-item">
                        <input class="form-check-input" type="checkbox" 
                               id="test-${testId}" 
                               ${isChecked ? 'checked' : ''}
                               onchange="window.KAManager.toggleTest(${testId})">
                        <label class="form-check-label" for="test-${testId}">
                            ${escapeHtml(test.test_title)}
                            <span class="text-muted small">(~${test.questions_count} вопр.)</span>
                        </label>
                    </div>
                `;
                
                if (isChecked) {
                    totalTests++;
                    totalQuestions += test.questions_count;
                }
            });
            
            html += `
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html || '<div class="alert alert-warning">Ничего не найдено</div>';
        
        updateSelectionStats(totalTests, totalQuestions);
    }
    
    // НОВОЕ: Обновление статистики выбора
    function updateSelectionStats(testsCount = null, questionsCount = null) {
        if (testsCount === null) {
            testsCount = selectedTestIds.size;
            questionsCount = 0;
            
            allTestsTree.forEach(category => {
                category.tests.forEach(test => {
                    if (selectedTestIds.has(test.test_id)) {
                        questionsCount += test.questions_count;
                    }
                });
            });
        }
        
        document.getElementById('selected-tests-count').textContent = testsCount;
        document.getElementById('estimated-questions-count').textContent = questionsCount;
        document.getElementById('max-questions-hint').textContent = questionsCount;
        
        const questionsInput = document.getElementById('questions-per-session');
        if (questionsInput) {
            questionsInput.max = questionsCount || 200;
        }
        
        updateDistributionPreview();
    }
    
    // НОВОЕ: Превью распределения вопросов
    function updateDistributionPreview() {
        const previewContainer = document.getElementById('distribution-details');
        
        if (!previewContainer || selectedTestIds.size === 0) {
            if (previewContainer) {
                previewContainer.innerHTML = '<span class="text-muted">Выберите тесты и укажите количество вопросов</span>';
            }
            return;
        }
        
        const totalQuestions = parseInt(document.getElementById('questions-per-session')?.value) || 20;
        const minPerTest = parseInt(document.getElementById('min-questions-per-test')?.value) || 3;
        
        // Собираем данные о выбранных тестах
        const selectedTests = [];
        allTestsTree.forEach(category => {
            category.tests.forEach(test => {
                if (selectedTestIds.has(test.test_id)) {
                    selectedTests.push({
                        id: test.test_id,
                        title: test.test_title,
                        totalQuestions: test.questions_count
                    });
                }
            });
        });
        
        if (selectedTests.length === 0) {
            previewContainer.innerHTML = '<span class="text-muted">Выберите хотя бы один тест</span>';
            return;
        }
        
        // Рассчитываем распределение
        const distribution = calculateDistribution(selectedTests, totalQuestions, distributionMode, minPerTest);
        
        // Отрисовываем превью
        let html = '<div class="distribution-list">';
        
        distribution.forEach((item, idx) => {
            const percentage = Math.round((item.questions / totalQuestions) * 100);
            
            html += `
                <div class="mb-2 ${idx > 0 ? 'border-top pt-2' : ''}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <strong class="d-block">${escapeHtml(item.title)}</strong>
                            <small class="text-muted">Всего вопросов: ${item.totalQuestions}</small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-primary">${item.questions} вопр.</span>
                            <small class="d-block text-muted">${percentage}%</small>
                        </div>
                    </div>
                    <div class="progress mt-1" style="height: 4px;">
                        <div class="progress-bar" style="width: ${percentage}%"></div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        
        const actualTotal = distribution.reduce((sum, item) => sum + item.questions, 0);
        if (actualTotal < totalQuestions) {
            html += `<div class="alert alert-warning mt-2 mb-0 py-2 small">
                <i class="bi bi-exclamation-triangle"></i> 
                Недостаточно вопросов в выбранных тестах. Будет использовано ${actualTotal} из ${totalQuestions}
            </div>`;
        }
        
        previewContainer.innerHTML = html;
    }
    
    // НОВОЕ: Расчёт распределения вопросов
    function calculateDistribution(tests, totalQuestions, mode, minPerTest) {
        const distribution = [];
        
        if (mode === 'equal') {
            // Равное распределение
            const questionsPerTest = Math.floor(totalQuestions / tests.length);
            const remainder = totalQuestions % tests.length;
            
            tests.forEach((test, idx) => {
                let allocated = questionsPerTest;
                
                // Добавляем остаток первым тестам
                if (idx < remainder) {
                    allocated++;
                }
                
                // Не можем взять больше чем есть
                allocated = Math.min(allocated, test.totalQuestions);
                
                // Гарантируем минимум
                allocated = Math.max(allocated, Math.min(minPerTest, test.totalQuestions));
                
                distribution.push({
                    id: test.id,
                    title: test.title,
                    totalQuestions: test.totalQuestions,
                    questions: allocated
                });
            });
        } else {
            // Пропорциональное распределение
            const totalAvailable = tests.reduce((sum, t) => sum + t.totalQuestions, 0);
            let allocated = 0;
            
            tests.forEach((test, idx) => {
                let questionsFromTest;
                
                if (idx === tests.length - 1) {
                    // Последний тест получает остаток
                    questionsFromTest = totalQuestions - allocated;
                } else {
                    // Пропорция
                    const proportion = test.totalQuestions / totalAvailable;
                    questionsFromTest = Math.round(totalQuestions * proportion);
                }
                
                // Не можем взять больше чем есть
                questionsFromTest = Math.min(questionsFromTest, test.totalQuestions);
                
                // Гарантируем минимум
                questionsFromTest = Math.max(questionsFromTest, Math.min(minPerTest, test.totalQuestions));
                
                allocated += questionsFromTest;
                
                distribution.push({
                    id: test.id,
                    title: test.title,
                    totalQuestions: test.totalQuestions,
                    questions: questionsFromTest
                });
            });
        }
        
        return distribution;
    }
    
    // Переключение всей категории
    function toggleCategory(categoryId) {
        const category = allTestsTree.find(c => c.category_id === categoryId);
        if (!category) return;
        
        const allSelected = category.tests.every(t => selectedTestIds.has(t.test_id));
        
        category.tests.forEach(test => {
            if (allSelected) {
                selectedTestIds.delete(test.test_id);
            } else {
                selectedTestIds.add(test.test_id);
            }
        });
        
        renderTestsTree();
    }
    
    // Переключение отдельного теста
    function toggleTest(testId) {
        if (selectedTestIds.has(testId)) {
            selectedTestIds.delete(testId);
        } else {
            selectedTestIds.add(testId);
        }
        
        renderTestsTree();
    }
    
    // Фильтрация тестов
    function filterTests(e) {
        const filterText = e.target.value;
        renderTestsTree(filterText);
    }
    
    // Установка максимального количества вопросов
    function setMaxQuestions() {
        const maxQuestions = parseInt(document.getElementById('max-questions-hint').textContent) || 20;
        document.getElementById('questions-per-session').value = maxQuestions;
        updateDistributionPreview();
    }
    
    // Открытие модального окна создания
    function openCreateModal() {
        editingAreaId = null;
        selectedTestIds.clear();
        distributionMode = 'proportional';
        minQuestionsPerTest = 3;
        
        document.getElementById('areaModalTitle').innerHTML = '<i class="bi bi-plus-circle"></i> Создание области знаний';
        document.getElementById('area-name').value = '';
        document.getElementById('area-description').value = '';
        document.getElementById('questions-per-session').value = 20;
        document.getElementById('min-questions-per-test').value = 3;
        document.getElementById('editing-area-id').value = '';
        document.getElementById('distribution-proportional').checked = true;
        document.getElementById('distribution-equal').checked = false;
        
        renderTestsTree();
        
        const modal = new bootstrap.Modal(document.getElementById('areaModal'));
        modal.show();
    }
    
    // Открытие модального окна редактирования
    async function editArea(areaId) {
        try {
            const result = await apiCall('getKnowledgeAreaDetails', { area_id: areaId });
            
            if (!result.success) {
                throw new Error(result.message);
            }
            
            const area = result.data;
            editingAreaId = areaId;
            
            selectedTestIds = new Set(JSON.parse(area.test_ids || '[]'));
            distributionMode = area.question_distribution_mode || 'proportional';
            minQuestionsPerTest = area.min_questions_per_test || 3;
            
            document.getElementById('areaModalTitle').innerHTML = '<i class="bi bi-pencil"></i> Редактирование области знаний';
            document.getElementById('area-name').value = area.name;
            document.getElementById('area-description').value = area.description || '';
            document.getElementById('questions-per-session').value = area.questions_per_session;
            document.getElementById('min-questions-per-test').value = minQuestionsPerTest;
            document.getElementById('editing-area-id').value = areaId;
            
            // Устанавливаем режим распределения
            if (distributionMode === 'equal') {
                document.getElementById('distribution-equal').checked = true;
                document.getElementById('distribution-proportional').checked = false;
            } else {
                document.getElementById('distribution-proportional').checked = true;
                document.getElementById('distribution-equal').checked = false;
            }
            
            if (!allTestsTree.length) {
                await loadTestsTree();
            } else {
                renderTestsTree();
            }
            
            const modal = new bootstrap.Modal(document.getElementById('areaModal'));
            modal.show();
            
        } catch (error) {
            console.error('Edit area error:', error);
            showNotification('Ошибка загрузки области: ' + error.message, 'danger');
        }
    }
    
    // Сохранение области
    async function saveArea() {
        const name = document.getElementById('area-name').value.trim();
        const description = document.getElementById('area-description').value.trim();
        const questionsPerSession = parseInt(document.getElementById('questions-per-session').value) || 20;
        const minPerTest = parseInt(document.getElementById('min-questions-per-test').value) || 3;
        
        if (!name) {
            showNotification('Введите название области', 'warning');
            return;
        }
        
        if (selectedTestIds.size === 0) {
            showNotification('Выберите хотя бы один тест', 'warning');
            return;
        }
        
        const saveBtn = document.getElementById('save-area-btn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Сохранение...';
        
        try {
            const action = editingAreaId ? 'updateKnowledgeArea' : 'createKnowledgeArea';
            const data = {
                name,
                description,
                test_ids: Array.from(selectedTestIds),
                questions_per_session: questionsPerSession,
                question_distribution_mode: distributionMode,
                min_questions_per_test: minPerTest
            };
            
            if (editingAreaId) {
                data.area_id = editingAreaId;
            }
            
            const result = await apiCall(action, data);
            
            if (result.success) {
                showNotification(editingAreaId ? 'Область обновлена' : 'Область создана', 'success');

                const modalEl = document.getElementById('areaModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                modal.hide();

                // ИСПРАВЛЕНИЕ: Удаляем backdrop принудительно
                setTimeout(() => {
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }, 300);

                await loadAreas();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Save area error:', error);
            showNotification('Ошибка сохранения: ' + error.message, 'danger');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Сохранить область';
        }
    }
    
    // Удаление области
    function deleteArea(areaId, areaName) {
        document.getElementById('deleting-area-id').value = areaId;
        document.getElementById('delete-area-name').textContent = areaName;
        
        const modal = new bootstrap.Modal(document.getElementById('deleteAreaModal'));
        modal.show();
    }
    
    async function confirmDeleteArea() {
        const areaId = parseInt(document.getElementById('deleting-area-id').value);
        
        const confirmBtn = document.getElementById('confirm-delete-area-btn');
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Удаление...';
        
        try {
            const result = await apiCall('deleteKnowledgeArea', { area_id: areaId });
            
            if (result.success) {
                showNotification('Область удалена', 'success');

                const modalEl = document.getElementById('deleteAreaModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                modal.hide();

                // ИСПРАВЛЕНИЕ: Удаляем backdrop принудительно
                setTimeout(() => {
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                }, 300);

                await loadAreas();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Delete area error:', error);
            showNotification('Ошибка удаления: ' + error.message, 'danger');
        } finally {
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="bi bi-trash"></i> Удалить';
        }
    }
    
    function startArea(areaId) {
        window.location.href = `${testPageUrl}?knowledge_area=${areaId}`;
    }
    
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
        notification.style.zIndex = '9999';
        notification.style.minWidth = '300px';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => notification.remove(), 4000);
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    window.KAManager = {
        toggleCategory,
        toggleTest,
        editArea,
        deleteArea,
        startArea
    };
    
})();