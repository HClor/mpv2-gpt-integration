/**
 * LEARNING MATERIALS MANAGER - Sprint 9
 * Система управления учебными материалами
 * Поддерживает: text, image, video, file, quiz блоки
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
    let currentMaterialId = null;
    let currentBlocks = [];
    let userProgress = {};

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
        // Страница списка материалов
        const materialsContainer = document.getElementById('materials-list-container');
        if (materialsContainer) {
            initMaterialsList();
        }

        // Страница просмотра материала
        const materialViewContainer = document.getElementById('material-view-container');
        if (materialViewContainer) {
            const materialId = materialViewContainer.dataset.materialId;
            if (materialId) {
                loadMaterial(materialId);
            }
        }

        // Страница редактирования материала
        const materialEditorContainer = document.getElementById('material-editor-container');
        if (materialEditorContainer) {
            const materialId = materialEditorContainer.dataset.materialId;
            initMaterialEditor(materialId);
        }
    });

    // ==================== MATERIALS LIST ====================

    async function initMaterialsList() {
        loadMaterials();

        // Event listeners
        document.getElementById('create-material-btn')?.addEventListener('click', showCreateMaterialModal);
        document.getElementById('save-material-btn')?.addEventListener('click', saveMaterialInfo);
        document.getElementById('material-search')?.addEventListener('input', filterMaterials);
        document.getElementById('material-category-filter')?.addEventListener('change', filterMaterials);
    }

    async function loadMaterials() {
        try {
            const result = await apiCall('getMaterials', {});

            if (result.success) {
                renderMaterialsList(result.data);
            } else {
                throw new Error(result.message || 'Ошибка загрузки материалов');
            }
        } catch (error) {
            console.error('Load materials error:', error);
            document.getElementById('materials-list-container').innerHTML = `
                <div class="alert alert-danger">
                    Ошибка загрузки материалов: ${escapeHtml(error.message)}
                </div>
            `;
        }
    }

    function renderMaterialsList(materials) {
        const container = document.getElementById('materials-list-container');

        if (!materials || materials.length === 0) {
            container.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-info text-center py-5">
                        <i class="bi bi-journal-text fs-1 d-block mb-3"></i>
                        <h5>Нет доступных учебных материалов</h5>
                        <p class="mb-0">Создайте первый материал для начала обучения</p>
                    </div>
                </div>
            `;
            return;
        }

        let html = '';

        materials.forEach(material => {
            const progressPercent = material.progress_percentage || 0;
            const progressClass = progressPercent === 100 ? 'bg-success' :
                                  progressPercent > 0 ? 'bg-warning' : 'bg-secondary';
            const statusBadge = material.is_published
                ? '<span class="badge bg-success">Опубликован</span>'
                : '<span class="badge bg-secondary">Черновик</span>';

            html += `
                <div class="col-md-6 col-lg-4 material-card"
                     data-category="${material.category_id || ''}"
                     data-title="${escapeHtml(material.title).toLowerCase()}">
                    <div class="card h-100">
                        ${material.thumbnail_url ? `
                            <img src="${escapeHtml(material.thumbnail_url)}"
                                 class="card-img-top"
                                 alt="${escapeHtml(material.title)}"
                                 style="height: 200px; object-fit: cover;">
                        ` : `
                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center"
                                 style="height: 200px;">
                                <i class="bi bi-journal-text text-muted" style="font-size: 3rem;"></i>
                            </div>
                        `}
                        <div class="card-body">
                            <h5 class="card-title">${escapeHtml(material.title)}</h5>
                            <p class="card-text text-muted small">${escapeHtml(material.description || 'Нет описания')}</p>

                            <div class="mb-2">
                                ${statusBadge}
                                ${material.category_name ? `<span class="badge bg-info">${escapeHtml(material.category_name)}</span>` : ''}
                            </div>

                            ${progressPercent > 0 ? `
                                <div class="progress mb-3" style="height: 8px;">
                                    <div class="progress-bar ${progressClass}"
                                         role="progressbar"
                                         style="width: ${progressPercent}%"
                                         aria-valuenow="${progressPercent}"
                                         aria-valuemin="0"
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <p class="small text-muted mb-0">Прогресс: ${progressPercent}%</p>
                            ` : ''}
                        </div>
                        <div class="card-footer bg-white">
                            <div class="d-grid gap-2">
                                <a href="/learning-material?id=${material.id}" class="btn btn-primary">
                                    <i class="bi bi-book-half"></i> Изучать
                                </a>
                                ${material.can_edit ? `
                                    <div class="btn-group btn-group-sm">
                                        <a href="/edit-material?id=${material.id}" class="btn btn-outline-secondary">
                                            <i class="bi bi-pencil"></i> Редактировать
                                        </a>
                                        <button class="btn btn-outline-danger"
                                                onclick="LearningMaterials.deleteMaterial(${material.id}, '${escapeHtml(material.title).replace(/'/g, "\\'")}')">
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

    function filterMaterials() {
        const searchText = document.getElementById('material-search')?.value.toLowerCase() || '';
        const categoryFilter = document.getElementById('material-category-filter')?.value || '';

        const cards = document.querySelectorAll('.material-card');

        cards.forEach(card => {
            const title = card.dataset.title || '';
            const category = card.dataset.category || '';

            const matchesSearch = !searchText || title.includes(searchText);
            const matchesCategory = !categoryFilter || category === categoryFilter;

            card.style.display = matchesSearch && matchesCategory ? '' : 'none';
        });
    }

    function showCreateMaterialModal() {
        currentMaterialId = null;
        document.getElementById('material-modal-title').textContent = 'Создать учебный материал';
        document.getElementById('material-title').value = '';
        document.getElementById('material-description').value = '';
        document.getElementById('material-category').value = '';
        document.getElementById('material-published').checked = false;

        const modal = new bootstrap.Modal(document.getElementById('materialModal'));
        modal.show();
    }

    async function saveMaterialInfo() {
        const title = document.getElementById('material-title').value.trim();
        const description = document.getElementById('material-description').value.trim();
        const categoryId = document.getElementById('material-category').value;
        const isPublished = document.getElementById('material-published').checked;

        if (!title) {
            showNotification('Введите название материала', 'warning');
            return;
        }

        const saveBtn = document.getElementById('save-material-btn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Сохранение...';

        try {
            const action = currentMaterialId ? 'updateMaterial' : 'createMaterial';
            const data = {
                title,
                description,
                category_id: categoryId || null,
                is_published: isPublished ? 1 : 0
            };

            if (currentMaterialId) {
                data.material_id = currentMaterialId;
            }

            const result = await apiCall(action, data);

            if (result.success) {
                showNotification(currentMaterialId ? 'Материал обновлен' : 'Материал создан', 'success');

                const modal = bootstrap.Modal.getInstance(document.getElementById('materialModal'));
                modal.hide();

                if (!currentMaterialId && result.material_id) {
                    // Перенаправляем на редактор для добавления блоков
                    window.location.href = `/edit-material?id=${result.material_id}`;
                } else {
                    loadMaterials();
                }
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Save material error:', error);
            showNotification('Ошибка сохранения: ' + error.message, 'danger');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Сохранить';
        }
    }

    async function deleteMaterial(materialId, materialTitle) {
        if (!confirm(`Вы уверены, что хотите удалить материал "${materialTitle}"?`)) {
            return;
        }

        try {
            const result = await apiCall('deleteMaterial', { material_id: materialId });

            if (result.success) {
                showNotification('Материал удален', 'success');
                loadMaterials();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Delete material error:', error);
            showNotification('Ошибка удаления: ' + error.message, 'danger');
        }
    }

    // ==================== MATERIAL VIEWER ====================

    async function loadMaterial(materialId) {
        try {
            const result = await apiCall('getMaterialWithBlocks', { material_id: materialId });

            if (result.success) {
                currentMaterialId = materialId;
                currentBlocks = result.data.blocks || [];
                userProgress = result.data.progress || {};

                renderMaterialView(result.data);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Load material error:', error);
            document.getElementById('material-view-container').innerHTML = `
                <div class="alert alert-danger">
                    Ошибка загрузки материала: ${escapeHtml(error.message)}
                </div>
            `;
        }
    }

    function renderMaterialView(data) {
        const container = document.getElementById('material-view-container');

        let html = `
            <div class="material-header mb-4">
                <h1>${escapeHtml(data.title)}</h1>
                ${data.description ? `<p class="lead text-muted">${escapeHtml(data.description)}</p>` : ''}

                <div class="progress mb-3" style="height: 20px;">
                    <div class="progress-bar bg-success"
                         role="progressbar"
                         style="width: ${userProgress.percentage || 0}%">
                        ${userProgress.percentage || 0}%
                    </div>
                </div>
            </div>

            <div class="material-blocks">
        `;

        currentBlocks.forEach((block, index) => {
            html += renderBlock(block, index);
        });

        html += '</div>';

        container.innerHTML = html;

        // Инициализируем интерактивные элементы
        initBlockInteractions();
    }

    function renderBlock(block, index) {
        const isCompleted = userProgress.completed_blocks?.includes(block.id);
        const completedClass = isCompleted ? 'block-completed' : '';

        let blockContent = '';

        switch (block.block_type) {
            case 'text':
                blockContent = `
                    <div class="block-text">
                        ${block.content}
                    </div>
                `;
                break;

            case 'image':
                const imageData = JSON.parse(block.content || '{}');
                blockContent = `
                    <div class="block-image text-center">
                        <img src="${escapeHtml(imageData.url)}"
                             alt="${escapeHtml(imageData.caption || '')}"
                             class="img-fluid rounded shadow-sm"
                             style="max-height: 500px;">
                        ${imageData.caption ? `<p class="mt-2 text-muted">${escapeHtml(imageData.caption)}</p>` : ''}
                    </div>
                `;
                break;

            case 'video':
                const videoData = JSON.parse(block.content || '{}');
                blockContent = `
                    <div class="block-video">
                        <div class="ratio ratio-16x9">
                            ${videoData.embed_code || `
                                <video controls class="w-100">
                                    <source src="${escapeHtml(videoData.url)}" type="video/mp4">
                                    Ваш браузер не поддерживает видео.
                                </video>
                            `}
                        </div>
                        ${videoData.caption ? `<p class="mt-2 text-muted">${escapeHtml(videoData.caption)}</p>` : ''}
                    </div>
                `;
                break;

            case 'file':
                const fileData = JSON.parse(block.content || '{}');
                blockContent = `
                    <div class="block-file">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-file-earmark-arrow-down"></i>
                                    ${escapeHtml(fileData.filename || 'Файл')}
                                </h5>
                                ${fileData.description ? `<p class="card-text">${escapeHtml(fileData.description)}</p>` : ''}
                                <a href="${escapeHtml(fileData.url)}"
                                   class="btn btn-primary"
                                   download
                                   target="_blank">
                                    <i class="bi bi-download"></i> Скачать
                                </a>
                                ${fileData.size ? `<small class="text-muted ms-2">(${formatFileSize(fileData.size)})</small>` : ''}
                            </div>
                        </div>
                    </div>
                `;
                break;

            case 'quiz':
                const quizData = JSON.parse(block.content || '{}');
                blockContent = `
                    <div class="block-quiz">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="bi bi-question-circle"></i>
                                    ${escapeHtml(quizData.question || 'Вопрос')}
                                </h5>
                            </div>
                            <div class="card-body">
                                ${renderQuizOptions(quizData, block.id, isCompleted)}
                            </div>
                        </div>
                    </div>
                `;
                break;

            default:
                blockContent = `<div class="alert alert-warning">Неизвестный тип блока: ${block.block_type}</div>`;
        }

        return `
            <div class="material-block mb-4 ${completedClass}"
                 data-block-id="${block.id}"
                 data-block-type="${block.block_type}">
                ${blockContent}
                ${!isCompleted && block.block_type !== 'quiz' ? `
                    <div class="text-end mt-2">
                        <button class="btn btn-sm btn-outline-success mark-complete-btn"
                                onclick="LearningMaterials.markBlockComplete(${block.id})">
                            <i class="bi bi-check-circle"></i> Отметить как изученное
                        </button>
                    </div>
                ` : ''}
                ${isCompleted ? `
                    <div class="text-end mt-2">
                        <span class="badge bg-success">
                            <i class="bi bi-check-circle-fill"></i> Изучено
                        </span>
                    </div>
                ` : ''}
            </div>
        `;
    }

    function renderQuizOptions(quizData, blockId, isCompleted) {
        if (isCompleted) {
            return `<div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i> Вы уже ответили на этот вопрос
            </div>`;
        }

        let html = '<div class="quiz-options">';

        (quizData.options || []).forEach((option, index) => {
            html += `
                <div class="form-check mb-2">
                    <input class="form-check-input"
                           type="${quizData.multiple ? 'checkbox' : 'radio'}"
                           name="quiz-${blockId}"
                           id="quiz-${blockId}-opt-${index}"
                           value="${index}">
                    <label class="form-check-label" for="quiz-${blockId}-opt-${index}">
                        ${escapeHtml(option)}
                    </label>
                </div>
            `;
        });

        html += `
            </div>
            <button class="btn btn-primary mt-3 submit-quiz-btn"
                    onclick="LearningMaterials.submitQuiz(${blockId})">
                <i class="bi bi-send"></i> Ответить
            </button>
            <div class="quiz-feedback mt-3"></div>
        `;

        return html;
    }

    function initBlockInteractions() {
        // Отслеживание просмотра видео
        document.querySelectorAll('.block-video video').forEach(video => {
            video.addEventListener('ended', function() {
                const block = this.closest('.material-block');
                const blockId = parseInt(block.dataset.blockId);
                markBlockComplete(blockId);
            });
        });
    }

    async function markBlockComplete(blockId) {
        try {
            const result = await apiCall('markBlockComplete', {
                material_id: currentMaterialId,
                block_id: blockId
            });

            if (result.success) {
                // Обновляем UI
                const blockElement = document.querySelector(`[data-block-id="${blockId}"]`);
                if (blockElement) {
                    blockElement.classList.add('block-completed');
                    const btn = blockElement.querySelector('.mark-complete-btn');
                    if (btn) {
                        btn.outerHTML = `
                            <span class="badge bg-success">
                                <i class="bi bi-check-circle-fill"></i> Изучено
                            </span>
                        `;
                    }
                }

                // Обновляем прогресс
                if (result.progress) {
                    updateProgressBar(result.progress.percentage);
                }

                showNotification('Прогресс сохранен', 'success');
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Mark complete error:', error);
            showNotification('Ошибка сохранения прогресса', 'danger');
        }
    }

    async function submitQuiz(blockId) {
        const block = document.querySelector(`[data-block-id="${blockId}"]`);
        const selectedInputs = block.querySelectorAll('input[name="quiz-' + blockId + '"]:checked');

        if (selectedInputs.length === 0) {
            showNotification('Выберите хотя бы один вариант ответа', 'warning');
            return;
        }

        const answers = Array.from(selectedInputs).map(input => parseInt(input.value));

        try {
            const result = await apiCall('submitQuizAnswer', {
                material_id: currentMaterialId,
                block_id: blockId,
                answers: answers
            });

            const feedbackDiv = block.querySelector('.quiz-feedback');

            if (result.success) {
                if (result.is_correct) {
                    feedbackDiv.innerHTML = `
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill"></i> Правильно!
                            ${result.explanation ? `<br><small>${escapeHtml(result.explanation)}</small>` : ''}
                        </div>
                    `;

                    // Отмечаем блок как завершенный
                    block.classList.add('block-completed');
                    block.querySelector('.submit-quiz-btn').disabled = true;

                    // Обновляем прогресс
                    if (result.progress) {
                        updateProgressBar(result.progress.percentage);
                    }
                } else {
                    feedbackDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-x-circle-fill"></i> Неправильно. Попробуйте еще раз.
                            ${result.explanation ? `<br><small>${escapeHtml(result.explanation)}</small>` : ''}
                        </div>
                    `;
                }
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Submit quiz error:', error);
            showNotification('Ошибка отправки ответа', 'danger');
        }
    }

    function updateProgressBar(percentage) {
        const progressBar = document.querySelector('.material-header .progress-bar');
        if (progressBar) {
            progressBar.style.width = percentage + '%';
            progressBar.textContent = percentage + '%';
        }
    }

    // ==================== MATERIAL EDITOR ====================

    function initMaterialEditor(materialId) {
        currentMaterialId = materialId;

        if (materialId) {
            loadMaterialForEdit(materialId);
        }

        // Event listeners для добавления блоков
        document.getElementById('add-text-block-btn')?.addEventListener('click', () => addBlockEditor('text'));
        document.getElementById('add-image-block-btn')?.addEventListener('click', () => addBlockEditor('image'));
        document.getElementById('add-video-block-btn')?.addEventListener('click', () => addBlockEditor('video'));
        document.getElementById('add-file-block-btn')?.addEventListener('click', () => addBlockEditor('file'));
        document.getElementById('add-quiz-block-btn')?.addEventListener('click', () => addBlockEditor('quiz'));

        document.getElementById('save-blocks-btn')?.addEventListener('click', saveAllBlocks);
    }

    async function loadMaterialForEdit(materialId) {
        try {
            const result = await apiCall('getMaterialWithBlocks', { material_id: materialId });

            if (result.success) {
                document.getElementById('editor-material-title').textContent = result.data.title;
                currentBlocks = result.data.blocks || [];
                renderBlocksEditor();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Load material for edit error:', error);
            showNotification('Ошибка загрузки материала: ' + error.message, 'danger');
        }
    }

    function renderBlocksEditor() {
        const container = document.getElementById('blocks-editor-container');

        if (currentBlocks.length === 0) {
            container.innerHTML = `
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Блоки не добавлены. Используйте кнопки выше для добавления контента.
                </div>
            `;
            return;
        }

        let html = '<div class="blocks-sortable">';

        currentBlocks.forEach((block, index) => {
            html += renderBlockEditor(block, index);
        });

        html += '</div>';
        container.innerHTML = html;

        // Инициализируем редакторы
        initRichTextEditors();
    }

    function renderBlockEditor(block, index) {
        const blockId = block.id || `new-${index}`;

        return `
            <div class="block-editor card mb-3" data-block-index="${index}" data-block-id="${blockId}">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <i class="bi bi-grip-vertical handle" style="cursor: move;"></i>
                        <strong>Блок ${index + 1}: ${getBlockTypeLabel(block.block_type)}</strong>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-danger"
                                onclick="LearningMaterials.removeBlock(${index})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    ${renderBlockEditorContent(block, index)}
                </div>
            </div>
        `;
    }

    function renderBlockEditorContent(block, index) {
        switch (block.block_type) {
            case 'text':
                return `
                    <textarea class="form-control rich-text-editor"
                              id="block-${index}-content"
                              rows="10">${escapeHtml(block.content || '')}</textarea>
                `;

            case 'image':
                const imageData = JSON.parse(block.content || '{}');
                return `
                    <div class="mb-3">
                        <label class="form-label">URL изображения</label>
                        <input type="text"
                               class="form-control"
                               id="block-${index}-url"
                               value="${escapeHtml(imageData.url || '')}"
                               placeholder="https://example.com/image.jpg">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Подпись (необязательно)</label>
                        <input type="text"
                               class="form-control"
                               id="block-${index}-caption"
                               value="${escapeHtml(imageData.caption || '')}"
                               placeholder="Описание изображения">
                    </div>
                `;

            case 'video':
                const videoData = JSON.parse(block.content || '{}');
                return `
                    <div class="mb-3">
                        <label class="form-label">URL видео или embed код</label>
                        <input type="text"
                               class="form-control"
                               id="block-${index}-url"
                               value="${escapeHtml(videoData.url || '')}"
                               placeholder="https://youtube.com/watch?v=...">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Embed код (для YouTube, Vimeo)</label>
                        <textarea class="form-control"
                                  id="block-${index}-embed"
                                  rows="3">${escapeHtml(videoData.embed_code || '')}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Описание (необязательно)</label>
                        <input type="text"
                               class="form-control"
                               id="block-${index}-caption"
                               value="${escapeHtml(videoData.caption || '')}"
                               placeholder="Описание видео">
                    </div>
                `;

            case 'file':
                const fileData = JSON.parse(block.content || '{}');
                return `
                    <div class="mb-3">
                        <label class="form-label">URL файла</label>
                        <input type="text"
                               class="form-control"
                               id="block-${index}-url"
                               value="${escapeHtml(fileData.url || '')}"
                               placeholder="https://example.com/file.pdf">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Название файла</label>
                        <input type="text"
                               class="form-control"
                               id="block-${index}-filename"
                               value="${escapeHtml(fileData.filename || '')}"
                               placeholder="document.pdf">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Описание (необязательно)</label>
                        <input type="text"
                               class="form-control"
                               id="block-${index}-description"
                               value="${escapeHtml(fileData.description || '')}"
                               placeholder="Описание файла">
                    </div>
                `;

            case 'quiz':
                const quizData = JSON.parse(block.content || '{"options": [""], "correct": [0]}');
                let optionsHtml = '';
                (quizData.options || ['']).forEach((opt, i) => {
                    const isCorrect = (quizData.correct || []).includes(i);
                    optionsHtml += `
                        <div class="input-group mb-2">
                            <div class="input-group-text">
                                <input type="checkbox"
                                       class="form-check-input"
                                       id="block-${index}-correct-${i}"
                                       ${isCorrect ? 'checked' : ''}>
                            </div>
                            <input type="text"
                                   class="form-control"
                                   id="block-${index}-option-${i}"
                                   value="${escapeHtml(opt)}"
                                   placeholder="Вариант ответа ${i + 1}">
                            <button class="btn btn-outline-danger"
                                    onclick="LearningMaterials.removeQuizOption(${index}, ${i})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    `;
                });

                return `
                    <div class="mb-3">
                        <label class="form-label">Вопрос</label>
                        <input type="text"
                               class="form-control"
                               id="block-${index}-question"
                               value="${escapeHtml(quizData.question || '')}"
                               placeholder="Введите вопрос">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Варианты ответа (отметьте правильные)</label>
                        <div id="block-${index}-options">
                            ${optionsHtml}
                        </div>
                        <button class="btn btn-sm btn-outline-primary mt-2"
                                onclick="LearningMaterials.addQuizOption(${index})">
                            <i class="bi bi-plus-circle"></i> Добавить вариант
                        </button>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Пояснение (необязательно)</label>
                        <textarea class="form-control"
                                  id="block-${index}-explanation"
                                  rows="2">${escapeHtml(quizData.explanation || '')}</textarea>
                    </div>
                `;

            default:
                return '<div class="alert alert-warning">Неизвестный тип блока</div>';
        }
    }

    function addBlockEditor(blockType) {
        const newBlock = {
            block_type: blockType,
            content: '',
            order_position: currentBlocks.length
        };

        currentBlocks.push(newBlock);
        renderBlocksEditor();
    }

    function removeBlock(index) {
        if (confirm('Удалить этот блок?')) {
            currentBlocks.splice(index, 1);
            renderBlocksEditor();
        }
    }

    function addQuizOption(blockIndex) {
        const block = currentBlocks[blockIndex];
        const quizData = JSON.parse(block.content || '{"options": [], "correct": []}');
        quizData.options.push('');
        block.content = JSON.stringify(quizData);
        renderBlocksEditor();
    }

    function removeQuizOption(blockIndex, optionIndex) {
        const block = currentBlocks[blockIndex];
        const quizData = JSON.parse(block.content || '{"options": [], "correct": []}');
        quizData.options.splice(optionIndex, 1);
        quizData.correct = quizData.correct.filter(i => i !== optionIndex).map(i => i > optionIndex ? i - 1 : i);
        block.content = JSON.stringify(quizData);
        renderBlocksEditor();
    }

    async function saveAllBlocks() {
        // Собираем данные со всех блоков
        const updatedBlocks = [];

        document.querySelectorAll('.block-editor').forEach((editor, index) => {
            const block = currentBlocks[index];
            if (!block) return;

            const blockId = editor.dataset.blockId;

            let content = '';

            switch (block.block_type) {
                case 'text':
                    content = document.getElementById(`block-${index}-content`)?.value || '';
                    break;

                case 'image':
                    content = JSON.stringify({
                        url: document.getElementById(`block-${index}-url`)?.value || '',
                        caption: document.getElementById(`block-${index}-caption`)?.value || ''
                    });
                    break;

                case 'video':
                    content = JSON.stringify({
                        url: document.getElementById(`block-${index}-url`)?.value || '',
                        embed_code: document.getElementById(`block-${index}-embed`)?.value || '',
                        caption: document.getElementById(`block-${index}-caption`)?.value || ''
                    });
                    break;

                case 'file':
                    content = JSON.stringify({
                        url: document.getElementById(`block-${index}-url`)?.value || '',
                        filename: document.getElementById(`block-${index}-filename`)?.value || '',
                        description: document.getElementById(`block-${index}-description`)?.value || ''
                    });
                    break;

                case 'quiz':
                    const options = [];
                    const correct = [];

                    editor.querySelectorAll('[id^="block-' + index + '-option-"]').forEach((input, i) => {
                        options.push(input.value);
                        const checkbox = document.getElementById(`block-${index}-correct-${i}`);
                        if (checkbox && checkbox.checked) {
                            correct.push(i);
                        }
                    });

                    content = JSON.stringify({
                        question: document.getElementById(`block-${index}-question`)?.value || '',
                        options: options,
                        correct: correct,
                        explanation: document.getElementById(`block-${index}-explanation`)?.value || '',
                        multiple: correct.length > 1
                    });
                    break;
            }

            updatedBlocks.push({
                id: blockId.startsWith('new-') ? null : parseInt(blockId),
                block_type: block.block_type,
                content: content,
                order_position: index
            });
        });

        const saveBtn = document.getElementById('save-blocks-btn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Сохранение...';

        try {
            const result = await apiCall('saveMaterialBlocks', {
                material_id: currentMaterialId,
                blocks: updatedBlocks
            });

            if (result.success) {
                showNotification('Блоки сохранены', 'success');
                loadMaterialForEdit(currentMaterialId);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Save blocks error:', error);
            showNotification('Ошибка сохранения блоков: ' + error.message, 'danger');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Сохранить блоки';
        }
    }

    function initRichTextEditors() {
        // Здесь можно инициализировать rich text редакторы типа TinyMCE или CKEditor
        // Для простоты оставляем textarea
    }

    // ==================== UTILITY FUNCTIONS ====================

    function getBlockTypeLabel(type) {
        const labels = {
            'text': 'Текст',
            'image': 'Изображение',
            'video': 'Видео',
            'file': 'Файл',
            'quiz': 'Тест'
        };
        return labels[type] || type;
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

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

    window.LearningMaterials = {
        loadMaterials,
        deleteMaterial,
        loadMaterial,
        markBlockComplete,
        submitQuiz,
        removeBlock,
        addQuizOption,
        removeQuizOption,
        saveAllBlocks
    };

})();
