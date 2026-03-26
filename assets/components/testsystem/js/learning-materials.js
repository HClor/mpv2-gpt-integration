let materialQuillEditor = null;
let currentEditMaterialId = null;
const API_URL = '/assets/components/testsystem/ajax/testsystem.php';

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

async function apiCall(action, data) {
    const csrfToken = getCsrfToken();
    if (csrfToken) {
        data.csrf_token = csrfToken;
    }

    const response = await fetch(API_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ action, data })
    });

    if (!response.ok) {
        // Попытка получить текст ошибки
        let errorText = '';
        try {
            errorText = await response.text();
        } catch (e) {
            errorText = 'No error details available';
        }
        throw new Error(`HTTP ${response.status}: ${errorText.substring(0, 200)}`);
    }

    // Проверяем, что ответ действительно JSON
    const contentType = response.headers.get('content-type');
    if (!contentType || !contentType.includes('application/json')) {
        const textResponse = await response.text();
        throw new Error(`Server returned non-JSON response: ${textResponse.substring(0, 200)}`);
    }

    return await response.json();
}

document.addEventListener('DOMContentLoaded', function() {
    const articlesTab = document.getElementById('articles-tab');
    articlesTab?.addEventListener('shown.bs.tab', function() {
        loadArticles();
    });

    const urlParams = new URLSearchParams(window.location.search);
    const editMaterialId = urlParams.get('edit_material');
    if (editMaterialId) {
        const articlesTab = document.getElementById('articles-tab');
        articlesTab?.click();
        setTimeout(() => {
            editMaterial(parseInt(editMaterialId));
        }, 500);
    }
});

async function loadArticles() {
    const container = document.getElementById('articles-container');
    try {
        const result = await apiCall('getMaterialsList', { parent_id: MATERIAL_PAGE_ID });
        if (!result.success) {
            throw new Error(result.message || 'Ошибка загрузки статей');
        }
        renderArticles(result.data || []);
    } catch (error) {
        console.error('Load error:', error);
        container.innerHTML = `<div class="alert alert-danger">Ошибка загрузки статей: ${escapeHtml(error.message)}</div>`;
    }
}

function renderArticles(materials) {
    const container = document.getElementById('articles-container');
    if (!materials || materials.length === 0) {
        container.innerHTML = `<div class="alert alert-info text-center py-5"><i class="bi bi-journal-text fs-1 d-block mb-3"></i><h5>Нет учебных статей</h5><p class="mb-0">Создайте первую статью через кнопку "Создать материал"</p></div>`;
        return;
    }
    let html = '<div class="row g-4">';
    materials.forEach(material => {
        const statusBadge = material.published == 1 ? '<span class="badge bg-success">Опубликован</span>' : '<span class="badge bg-secondary">Черновик</span>';
        const isPublished = material.published == 1;

        html += `<div class="col-md-6 col-lg-4"><div class="card h-100"><div class="card-body"><h5 class="card-title">${escapeHtml(material.pagetitle)}</h5><p class="card-text text-muted small">${escapeHtml(material.introtext || material.description || 'Нет описания')}</p><div class="mb-3">${statusBadge}</div></div><div class="card-footer bg-white"><div class="d-grid gap-2">`;

        // Кнопка "Читать" только для опубликованных материалов
        if (isPublished) {
            html += `<a href="${material.url}" class="btn btn-primary"><i class="bi bi-book-half"></i> Читать</a>`;
        }

        // Кнопки редактирования и удаления для авторов
        if (material.can_edit) {
            html += `<button class="btn btn-outline-secondary btn-sm" data-action="edit" data-id="${material.id}"><i class="bi bi-pencil"></i> Редактировать</button>`;
            html += `<button class="btn btn-outline-danger btn-sm" data-action="delete" data-id="${material.id}" data-title="${escapeHtml(material.pagetitle)}"><i class="bi bi-trash"></i> Удалить</button>`;
        }

        html += `</div></div></div></div>`;
    });
    html += '</div>';
    container.innerHTML = html;

    // Добавляем обработчики событий для кнопок редактирования и удаления
    container.querySelectorAll('[data-action="edit"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const materialId = parseInt(btn.getAttribute('data-id'));
            editMaterial(materialId);
        });
    });

    container.querySelectorAll('[data-action="delete"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const materialId = parseInt(btn.getAttribute('data-id'));
            const materialTitle = btn.getAttribute('data-title');
            deleteMaterial(materialId, materialTitle);
        });
    });
}

function openCreateMaterialModal() {
    currentEditMaterialId = null;
    document.getElementById('materialModalTitle').textContent = 'Создать материал';
    document.getElementById('material-pagetitle').value = '';
    document.getElementById('material-introtext').value = '';
    document.getElementById('material-published').checked = false;

    // Сброс категории
    const categorySelect = document.getElementById('material-category');
    if (categorySelect) {
        categorySelect.value = '';
    }

    initMaterialQuill('');
    const modal = new bootstrap.Modal(document.getElementById('materialEditorModal'));
    modal.show();
}

async function editMaterial(materialId) {
    currentEditMaterialId = materialId;
    document.getElementById('materialModalTitle').textContent = 'Редактировать материал';
    try {
        const resource = await apiCall('getMaterial', { material_id: materialId });
        if (!resource.success) {
            throw new Error(resource.message || 'Материал не найден');
        }
        const material = resource.data;
        document.getElementById('material-pagetitle').value = material.pagetitle || '';
        document.getElementById('material-introtext').value = material.introtext || '';
        document.getElementById('material-published').checked = material.published == 1;

        // Устанавливаем категорию
        const categorySelect = document.getElementById('material-category');
        if (categorySelect && material.category_id) {
            categorySelect.value = material.category_id;
        }

        initMaterialQuill(material.content || '');
        const modal = new bootstrap.Modal(document.getElementById('materialEditorModal'));
        modal.show();
    } catch (error) {
        console.error('Edit error:', error);
        alert('Ошибка загрузки материала: ' + error.message);
    }
}

function initMaterialQuill(content) {
    const editorContainer = document.getElementById('material-quill-editor');

    // ИСПРАВЛЕНИЕ: Уничтожаем предыдущий экземпляр редактора
    if (materialQuillEditor) {
        materialQuillEditor = null;
    }

    // Очищаем контейнер (удаляем старые toolbar и editor)
    editorContainer.innerHTML = '';
    // Также удаляем toolbar который Quill создает рядом
    const existingToolbar = editorContainer.previousElementSibling;
    if (existingToolbar && existingToolbar.classList.contains('ql-toolbar')) {
        existingToolbar.remove();
    }

    // Создаем toolbar БЕЗ кнопки document (добавим её вручную)
    const toolbarOptions = [
        ['bold', 'italic', 'underline', 'strike'],
        ['blockquote', 'code-block'],
        [{ 'header': 1 }, { 'header': 2 }],
        [{ 'list': 'ordered'}, { 'list': 'bullet' }],
        [{ 'indent': '-1'}, { 'indent': '+1' }],
        [{ 'size': ['small', false, 'large', 'huge'] }],
        [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
        [{ 'color': [] }, { 'background': [] }],
        [{ 'align': [] }],
        ['link', 'image'],
        ['clean']
    ];

    materialQuillEditor = new Quill('#material-quill-editor', {
        theme: 'snow',
        modules: { toolbar: toolbarOptions }
    });

    // Настраиваем обработчик для изображений
    const toolbar = materialQuillEditor.getModule('toolbar');
    toolbar.addHandler('image', imageHandler);

    // Добавляем кнопку для документов вручную после инициализации Quill
    setTimeout(() => {
        const toolbarElement = editorContainer.previousElementSibling;
        console.log('[Document Button] Toolbar element:', toolbarElement);

        if (toolbarElement && toolbarElement.classList.contains('ql-toolbar')) {
            // Находим группу с link и image
            const linkImageGroup = Array.from(toolbarElement.querySelectorAll('.ql-formats')).find(group =>
                group.querySelector('.ql-link') && group.querySelector('.ql-image')
            );

            console.log('[Document Button] Link/Image group:', linkImageGroup);

            if (linkImageGroup) {
                // Создаем кнопку для документов
                const docButton = document.createElement('button');
                docButton.type = 'button';
                docButton.className = 'ql-document-upload';
                docButton.innerHTML = '<i class="bi bi-file-earmark-arrow-up"></i>';
                docButton.title = 'Загрузить документ (PDF, DOC, XLS и т.д.)';
                docButton.style.width = 'auto';
                docButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    console.log('[Document Button] Clicked!');
                    documentHandler();
                });

                // Добавляем кнопку после image
                const imageButton = linkImageGroup.querySelector('.ql-image');
                if (imageButton && imageButton.nextSibling) {
                    linkImageGroup.insertBefore(docButton, imageButton.nextSibling);
                } else {
                    linkImageGroup.appendChild(docButton);
                }

                console.log('[Document Button] Added successfully!');
            } else {
                console.error('[Document Button] Link/Image group not found!');
            }
        } else {
            console.error('[Document Button] Toolbar element not found or invalid!');
        }
    }, 100);

    if (content) {
        materialQuillEditor.clipboard.dangerouslyPasteHTML(content);
    }
}

// Функция ресайза изображения
function resizeImage(base64Image, maxSize = 1280) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => {
            let width = img.width;
            let height = img.height;

            // Определяем нужно ли ресайзить
            if (width <= maxSize && height <= maxSize) {
                resolve(base64Image); // Изображение уже меньше лимита
                return;
            }

            // Вычисляем новые размеры сохраняя пропорции
            if (width > height) {
                if (width > maxSize) {
                    height = Math.round((height * maxSize) / width);
                    width = maxSize;
                }
            } else {
                if (height > maxSize) {
                    width = Math.round((width * maxSize) / height);
                    height = maxSize;
                }
            }

            // Создаем canvas и ресайзим
            const canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, width, height);

            // Возвращаем ресайзнутое изображение в base64
            resolve(canvas.toDataURL('image/jpeg', 0.9));
        };
        img.onerror = reject;
        img.src = base64Image;
    });
}

async function imageHandler() {
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/jpeg,image/jpg,image/png,image/gif,image/webp');
    input.click();

    input.onchange = async () => {
        const file = input.files[0];
        if (!file) return;

        // Проверка размера (макс 5MB)
        if (file.size > 5 * 1024 * 1024) {
            alert('Размер изображения не должен превышать 5MB');
            return;
        }

        try {
            // Читаем файл как base64
            const reader = new FileReader();
            reader.onload = async (e) => {
                try {
                    // Ресайзим изображение до 1280px
                    const resizedImage = await resizeImage(e.target.result, 1280);

                    // Определяем resource_id: при редактировании - currentEditMaterialId, иначе MATERIAL_PAGE_ID
                    const resourceId = currentEditMaterialId || (typeof MATERIAL_PAGE_ID !== 'undefined' ? MATERIAL_PAGE_ID : 0);

                    // Отправляем на сервер
                    const result = await apiCall('uploadImage', {
                        image: resizedImage,
                        resource_id: resourceId
                    });

                    if (result.success && result.data.url) {
                        // Вставляем изображение в редактор с bootstrap классом
                        const range = materialQuillEditor.getSelection(true);
                        materialQuillEditor.insertEmbed(range.index, 'image', result.data.url);

                        // Добавляем bootstrap класс к только что вставленному изображению
                        setTimeout(() => {
                            const images = materialQuillEditor.root.querySelectorAll('img');
                            images.forEach(img => {
                                if (!img.classList.contains('img-fluid')) {
                                    img.classList.add('img-fluid');
                                }
                            });
                        }, 100);

                        materialQuillEditor.setSelection(range.index + 1);
                    } else {
                        throw new Error(result.message || 'Ошибка загрузки изображения');
                    }
                } catch (error) {
                    console.error('Upload error:', error);
                    alert('Ошибка загрузки: ' + error.message);
                }
            };
            reader.readAsDataURL(file);
        } catch (error) {
            console.error('File read error:', error);
            alert('Ошибка чтения файла');
        }
    };
}

async function documentHandler() {
    console.log('[Document Handler] Started');

    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar');
    input.click();

    input.onchange = async () => {
        const file = input.files[0];
        console.log('[Document Handler] File selected:', file);

        if (!file) return;

        // Проверка размера (макс 20MB)
        if (file.size > 20 * 1024 * 1024) {
            alert('Размер документа не должен превышать 20MB');
            return;
        }

        try {
            // Читаем файл как base64
            const reader = new FileReader();
            reader.onload = async (e) => {
                const base64Document = e.target.result;
                console.log('[Document Handler] File read as base64, length:', base64Document.length);

                try {
                    // Определяем resource_id: при редактировании - currentEditMaterialId, иначе MATERIAL_PAGE_ID
                    const resourceId = currentEditMaterialId || (typeof MATERIAL_PAGE_ID !== 'undefined' ? MATERIAL_PAGE_ID : 0);
                    console.log('[Document Handler] Using resource_id:', resourceId);

                    // Отправляем на сервер
                    console.log('[Document Handler] Sending to server...');
                    const result = await apiCall('uploadDocument', {
                        document: base64Document,
                        filename: file.name,
                        resource_id: resourceId
                    });

                    console.log('[Document Handler] Server response:', result);

                    if (result.success && result.data.url) {
                        // Определяем emoji иконку по расширению
                        const extension = result.data.extension || '';
                        let emoji = '📄'; // По умолчанию - документ

                        // Документы
                        if (extension === 'pdf') emoji = '📕'; // Красная книга
                        else if (['doc', 'docx'].includes(extension)) emoji = '📘'; // Синяя книга (Word)
                        else if (['xls', 'xlsx', 'csv'].includes(extension)) emoji = '📗'; // Зеленая книга (Excel)
                        else if (['ppt', 'pptx'].includes(extension)) emoji = '📙'; // Оранжевая книга (PowerPoint)
                        else if (extension === 'txt') emoji = '📝'; // Блокнот с карандашом

                        // Архивы
                        else if (['zip', 'rar', '7z', 'tar', 'gz'].includes(extension)) emoji = '📦'; // Коробка

                        // Изображения
                        else if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'].includes(extension)) emoji = '🖼️'; // Картина в рамке

                        // Видео
                        else if (['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'].includes(extension)) emoji = '🎬'; // Кинохлопушка

                        // Аудио
                        else if (['mp3', 'wav', 'ogg', 'flac', 'm4a'].includes(extension)) emoji = '🎵'; // Музыкальная нота

                        // Код
                        else if (['js', 'ts', 'jsx', 'tsx', 'json', 'html', 'css', 'php', 'py', 'java', 'cpp', 'c', 'cs'].includes(extension)) emoji = '💻'; // Ноутбук

                        // Создаем ссылку на документ с emoji (без CSS классов, которые Quill удаляет)
                        const originalName = result.data.original_name || result.data.filename;
                        const linkHtml = `<p><a href="${result.data.url}" target="_blank">${emoji}${escapeHtml(originalName)}</a></p>`;

                        // Вставляем в редактор
                        const range = materialQuillEditor.getSelection(true);
                        materialQuillEditor.clipboard.dangerouslyPasteHTML(range.index, linkHtml);
                        materialQuillEditor.setSelection(range.index + linkHtml.length);
                    } else {
                        throw new Error(result.message || 'Ошибка загрузки документа');
                    }
                } catch (error) {
                    console.error('Upload error:', error);
                    alert('Ошибка загрузки: ' + error.message);
                }
            };
            reader.readAsDataURL(file);
        } catch (error) {
            console.error('File read error:', error);
            alert('Ошибка чтения файла');
        }
    };
}

async function saveMaterialFromModal() {
    const pagetitle = document.getElementById('material-pagetitle').value.trim();
    const introtext = document.getElementById('material-introtext').value.trim();
    const published = document.getElementById('material-published').checked ? 1 : 0;
    const content = materialQuillEditor.root.innerHTML;

    // Получаем категорию
    const categorySelect = document.getElementById('material-category');
    const categoryId = categorySelect ? categorySelect.value : '';

    if (!pagetitle) {
        alert('Введите название материала');
        return;
    }
    try {
        const data = {
            pagetitle,
            introtext,
            content,
            published,
            template: 9,
            parent: MATERIAL_PAGE_ID,
            category_id: categoryId
        };
        if (currentEditMaterialId) { data.material_id = currentEditMaterialId; }
        const result = await apiCall('saveMaterial', data);
        if (result.success) {
            alert(currentEditMaterialId ? 'Материал обновлен!' : 'Материал создан!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('materialEditorModal'));
            modal?.hide();

            // Очищаем URL параметры и перезагружаем страницу
            // (статьи рендерятся PHP-сниппетом, AJAX-обновление не работает)
            const url = new URL(window.location);
            url.searchParams.delete('edit_material');
            window.history.replaceState({}, '', url);
            window.location.reload();
        } else {
            throw new Error(result.message || 'Ошибка сохранения');
        }
    } catch (error) {
        console.error('Save error:', error);
        alert('Ошибка сохранения: ' + error.message);
    }
}

async function deleteMaterial(materialId, materialTitle) {
    if (!confirm(`Вы уверены, что хотите удалить материал "${materialTitle}"?\n\nМатериал будет помечен как удаленный и скрыт из списка.`)) {
        return;
    }

    try {
        const result = await apiCall('deleteMaterial', { material_id: materialId });

        if (result.success) {
            // Закрываем модальное окно если оно открыто
            const modalElement = document.getElementById('materialEditorModal');
            if (modalElement) {
                const modalInstance = bootstrap.Modal.getInstance(modalElement);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }

            alert('Материал удален');

            // Определяем контекст: на странице материала или на корневой
            const articlesContainer = document.getElementById('articles-container');

            if (articlesContainer) {
                // Мы на корневой странице (Mode 3) - перезагружаем страницу
                // (т.к. статьи рендерятся PHP-сниппетом, а не JavaScript)
                window.location.reload();
            } else {
                // Мы на странице материала (Mode 2) - редирект на родительскую страницу
                if (typeof MATERIAL_PAGE_URL !== 'undefined' && MATERIAL_PAGE_URL) {
                    window.location.href = MATERIAL_PAGE_URL;
                } else {
                    // Фоллбэк: просто перезагружаем текущую страницу (не идеально, но работает)
                    window.location.reload();
                }
            }
        } else {
            throw new Error(result.message || 'Ошибка удаления');
        }
    } catch (error) {
        console.error('Delete error:', error);
        alert('Ошибка удаления: ' + error.message);
    }
}

// === ПРОСМОТР ЧЕРНОВИКОВ ===

let currentDraftMaterialId = null;

/**
 * Просмотр черновика в модальном окне
 */
async function viewDraftMaterial(materialId) {
    try {
        currentDraftMaterialId = materialId;

        // Загружаем данные материала через существующий API
        const result = await apiCall('getMaterial', { material_id: materialId });

        if (!result.success) {
            throw new Error(result.message || 'Не удалось загрузить материал');
        }

        const material = result.data;

        // Проверяем права доступа
        if (!material.can_edit && material.published == 0) {
            alert('У вас нет прав для просмотра этого черновика');
            return;
        }

        // Заполняем модальное окно
        const title = document.getElementById('draftViewerTitle');
        const content = document.getElementById('draft-content');
        const editButton = document.getElementById('editDraftButton');

        if (title) {
            title.textContent = material.pagetitle;
        }

        if (content) {
            let html = '';

            // Показываем краткое описание если есть
            if (material.introtext) {
                html += '<p class="lead text-muted">' + escapeHtml(material.introtext) + '</p>';
                html += '<hr class="my-4">';
            }

            // Показываем содержимое (оно уже содержит HTML)
            html += material.content;

            content.innerHTML = html;
        }

        // Показываем/скрываем кнопку редактирования в зависимости от прав
        if (editButton) {
            editButton.style.display = material.can_edit ? 'inline-block' : 'none';
        }

        // Открываем модальное окно
        const modalElement = document.getElementById('draftViewerModal');
        if (modalElement) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }

    } catch (error) {
        console.error('View draft error:', error);
        alert('Ошибка загрузки черновика: ' + error.message);
    }
}

/**
 * Переход от просмотра черновика к редактированию
 */
function editDraftFromViewer() {
    if (!currentDraftMaterialId) {
        alert('Ошибка: ID материала не определен');
        return;
    }

    // Закрываем окно просмотра
    const viewerModal = document.getElementById('draftViewerModal');
    if (viewerModal) {
        const modal = bootstrap.Modal.getInstance(viewerModal);
        if (modal) {
            modal.hide();
        }
    }

    // Открываем окно редактирования
    editMaterial(currentDraftMaterialId);
}

window.openCreateMaterialModal = openCreateMaterialModal;
window.editMaterial = editMaterial;
window.saveMaterialFromModal = saveMaterialFromModal;
window.deleteMaterial = deleteMaterial;
window.viewDraftMaterial = viewDraftMaterial;
window.editDraftFromViewer = editDraftFromViewer;
