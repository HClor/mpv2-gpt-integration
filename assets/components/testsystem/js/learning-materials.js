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
        throw new Error(\`HTTP error! status: \${response.status}\`);
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
        container.innerHTML = \`<div class="alert alert-danger">Ошибка загрузки статей: \${escapeHtml(error.message)}</div>\`;
    }
}

function renderArticles(materials) {
    const container = document.getElementById('articles-container');
    if (!materials || materials.length === 0) {
        container.innerHTML = \`<div class="alert alert-info text-center py-5"><i class="bi bi-journal-text fs-1 d-block mb-3"></i><h5>Нет учебных статей</h5><p class="mb-0">Создайте первую статью через кнопку "Создать материал"</p></div>\`;
        return;
    }
    let html = '<div class="row g-4">';
    materials.forEach(material => {
        const statusBadge = material.published == 1 ? '<span class="badge bg-success">Опубликован</span>' : '<span class="badge bg-secondary">Черновик</span>';
        html += \`<div class="col-md-6 col-lg-4"><div class="card h-100"><div class="card-body"><h5 class="card-title">\${escapeHtml(material.pagetitle)}</h5><p class="card-text text-muted small">\${escapeHtml(material.introtext || material.description || 'Нет описания')}</p><div class="mb-3">\${statusBadge}</div></div><div class="card-footer bg-white"><div class="d-grid gap-2"><a href="\${material.url}" class="btn btn-primary"><i class="bi bi-book-half"></i> Читать</a>\${material.can_edit ? \`<button class="btn btn-outline-secondary btn-sm" onclick="editMaterial(\${material.id})"><i class="bi bi-pencil"></i> Редактировать</button>\` : ''}</div></div></div></div>\`;
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
    materialQuillEditor = new Quill('#material-quill-editor', {
        theme: 'snow',
        modules: { toolbar: [['bold', 'italic', 'underline', 'strike'],['blockquote', 'code-block'],[{ 'header': 1 }, { 'header': 2 }],[{ 'list': 'ordered'}, { 'list': 'bullet' }],[{ 'indent': '-1'}, { 'indent': '+1' }],[{ 'size': ['small', false, 'large', 'huge'] }],[{ 'header': [1, 2, 3, 4, 5, 6, false] }],[{ 'color': [] }, { 'background': [] }],[{ 'align': [] }],['link', 'image'],['clean']] }
    });
    if (content) {
        materialQuillEditor.clipboard.dangerouslyPasteHTML(content);
    }
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
