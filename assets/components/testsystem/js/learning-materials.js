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
        throw new Error(`HTTP error! status: ${response.status}`);
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
        html += `<div class="col-md-6 col-lg-4"><div class="card h-100"><div class="card-body"><h5 class="card-title">${escapeHtml(material.pagetitle)}</h5><p class="card-text text-muted small">${escapeHtml(material.introtext || material.description || 'Нет описания')}</p><div class="mb-3">${statusBadge}</div></div><div class="card-footer bg-white"><div class="d-grid gap-2"><a href="${material.url}" class="btn btn-primary"><i class="bi bi-book-half"></i> Читать</a>${material.can_edit ? `<button class="btn btn-outline-secondary btn-sm" onclick="editMaterial(${material.id})"><i class="bi bi-pencil"></i> Редактировать</button>` : ''}</div></div></div></div>`;
    });
    html += '</div>';
    container.innerHTML = html;
}

function openCreateMaterialModal() {
    currentEditMaterialId = null;
    document.getElementById('materialModalTitle').textContent = 'Создать материал';
    document.getElementById('material-pagetitle').value = '';
    document.getElementById('material-introtext').value = '';
    document.getElementById('material-published').checked = false;
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
    editorContainer.innerHTML = '';

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
        if (toolbarElement && toolbarElement.classList.contains('ql-toolbar')) {
            // Находим группу с link и image
            const linkImageGroup = Array.from(toolbarElement.querySelectorAll('.ql-formats')).find(group =>
                group.querySelector('.ql-link') && group.querySelector('.ql-image')
            );

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
                    documentHandler();
                });

                // Добавляем кнопку после image
                const imageButton = linkImageGroup.querySelector('.ql-image');
                if (imageButton && imageButton.nextSibling) {
                    linkImageGroup.insertBefore(docButton, imageButton.nextSibling);
                } else {
                    linkImageGroup.appendChild(docButton);
                }
            }
        }
    }, 100);

    if (content) {
        materialQuillEditor.clipboard.dangerouslyPasteHTML(content);
    }
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
                const base64Image = e.target.result;

                try {
                    // Определяем resource_id: при редактировании - currentEditMaterialId, иначе MATERIAL_PAGE_ID
                    const resourceId = currentEditMaterialId || (typeof MATERIAL_PAGE_ID !== 'undefined' ? MATERIAL_PAGE_ID : 0);

                    // Отправляем на сервер
                    const result = await apiCall('uploadImage', {
                        image: base64Image,
                        resource_id: resourceId
                    });

                    if (result.success && result.data.url) {
                        // Вставляем изображение в редактор
                        const range = materialQuillEditor.getSelection(true);
                        materialQuillEditor.insertEmbed(range.index, 'image', result.data.url);
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
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar');
    input.click();

    input.onchange = async () => {
        const file = input.files[0];
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

                try {
                    // Определяем resource_id: при редактировании - currentEditMaterialId, иначе MATERIAL_PAGE_ID
                    const resourceId = currentEditMaterialId || (typeof MATERIAL_PAGE_ID !== 'undefined' ? MATERIAL_PAGE_ID : 0);

                    // Отправляем на сервер
                    const result = await apiCall('uploadDocument', {
                        document: base64Document,
                        filename: file.name,
                        resource_id: resourceId
                    });

                    if (result.success && result.data.url) {
                        // Определяем иконку по расширению
                        const extension = result.data.extension || '';
                        let icon = 'bi-file-earmark';
                        if (extension === 'pdf') icon = 'bi-file-earmark-pdf';
                        else if (['doc', 'docx'].includes(extension)) icon = 'bi-file-earmark-word';
                        else if (['xls', 'xlsx'].includes(extension)) icon = 'bi-file-earmark-excel';
                        else if (['ppt', 'pptx'].includes(extension)) icon = 'bi-file-earmark-ppt';
                        else if (['zip', 'rar'].includes(extension)) icon = 'bi-file-earmark-zip';

                        // Создаем красивую ссылку на документ
                        const originalName = result.data.original_name || result.data.filename;
                        const linkHtml = `<p><a href="${result.data.url}" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi ${icon}"></i> ${escapeHtml(originalName)}</a></p>`;

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
    if (!pagetitle) {
        alert('Введите название материала');
        return;
    }
    try {
        const data = { pagetitle, introtext, content, published, template: 6, parent: MATERIAL_PAGE_ID };
        if (currentEditMaterialId) { data.material_id = currentEditMaterialId; }
        const result = await apiCall('saveMaterial', data);
        if (result.success) {
            alert(currentEditMaterialId ? 'Материал обновлен!' : 'Материал создан!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('materialEditorModal'));
            modal?.hide();
            loadArticles();
            const url = new URL(window.location);
            url.searchParams.delete('edit_material');
            window.history.replaceState({}, '', url);
        } else {
            throw new Error(result.message || 'Ошибка сохранения');
        }
    } catch (error) {
        console.error('Save error:', error);
        alert('Ошибка сохранения: ' + error.message);
    }
}

window.openCreateMaterialModal = openCreateMaterialModal;
window.editMaterial = editMaterial;
window.saveMaterialFromModal = saveMaterialFromModal;
