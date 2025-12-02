<?php
/**
 * Learning Materials Template Handler
 * Обрабатывает отображение учебных материалов в зависимости от режима
 */

// Определяем режим работы
$isEdit = isset($_GET['edit']) && $_GET['edit'] == '1';
$editMaterialId = isset($_GET['edit_material']) ? (int)$_GET['edit_material'] : 0;
$materialId = (int)$modx->resource->get('id');
$parentId = (int)$modx->resource->get('parent');

// Проверяем права на редактирование
require_once MODX_CORE_PATH . 'components/testsystem/helpers/PermissionHelper.php';
$canEdit = false;
if (PermissionHelper::isAuthenticated($modx)) {
    $userId = PermissionHelper::getCurrentUserId($modx);
    $isAdmin = PermissionHelper::isAdmin($modx);
    $canEdit = ($modx->resource->get('createdby') == $userId) || $isAdmin;
}

// РЕЖИМ 1: Редирект на редактирование (через старый параметр ?edit=1)
if ($isEdit && $canEdit) {
    $rootUrl = $modx->makeUrl(149); // ID корневой страницы learning-articles
    $redirectUrl = $rootUrl . '?edit_material=' . $materialId;

    $output = '<script>window.location.href = "' . htmlspecialchars($redirectUrl, ENT_QUOTES, 'UTF-8') . '";</script>';
    $output .= '<p>Перенаправление на редактирование...</p>';
    return $output;
}

// РЕЖИМ 2: Просмотр отдельного материала (дочерняя страница)
if ($parentId > 0) {
    $output = '<div class="container-fluid mt-4">';
    $output .= '<div class="row">';
    $output .= '<div class="col-12">';

    $output .= '<div class="d-flex justify-content-between align-items-center mb-4">';
    $output .= '<div>';
    $output .= '<h1>' . htmlspecialchars($modx->resource->get('pagetitle'), ENT_QUOTES, 'UTF-8') . '</h1>';

    $introtext = $modx->resource->get('introtext');
    if (!empty($introtext)) {
        $output .= '<p class="lead text-muted">' . htmlspecialchars($introtext, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $output .= '</div>';

    if ($canEdit) {
        $editUrl = $modx->makeUrl(149) . '?edit_material=' . $materialId;
        $output .= '<a href="' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary">';
        $output .= '<i class="bi bi-pencil"></i> Редактировать материал';
        $output .= '</a>';
    }

    $output .= '</div>';

    $output .= '<div class="material-content">';
    $output .= $modx->resource->get('content');
    $output .= '</div>';

    $output .= '</div></div></div>';

    return $output;
}

// РЕЖИМ 3: Корневая страница со вкладками
$output = '<div class="container-fluid mt-4">';
$output .= '<div class="row">';
$output .= '<div class="col-12">';

$output .= '<div class="d-flex justify-content-between align-items-center mb-4">';
$output .= '<h1>' . htmlspecialchars($modx->resource->get('pagetitle'), ENT_QUOTES, 'UTF-8') . '</h1>';

if ($canEdit) {
    $output .= '<button class="btn btn-success" onclick="openCreateMaterialModal()">';
    $output .= '<i class="bi bi-plus-circle"></i> Создать материал';
    $output .= '</button>';
}

$output .= '</div>';

// Tabs Navigation
$output .= '<ul class="nav nav-tabs mb-4" id="learningTabs" role="tablist">';
$output .= '<li class="nav-item" role="presentation">';
$output .= '<button class="nav-link active" id="questions-tab" data-bs-toggle="tab" data-bs-target="#questions" type="button" role="tab">';
$output .= '<i class="bi bi-collection"></i> Вопросы из тестов';
$output .= '</button>';
$output .= '</li>';
$output .= '<li class="nav-item" role="presentation">';
$output .= '<button class="nav-link" id="articles-tab" data-bs-toggle="tab" data-bs-target="#articles" type="button" role="tab">';
$output .= '<i class="bi bi-journal-text"></i> Учебные статьи';
$output .= '</button>';
$output .= '</li>';
$output .= '</ul>';

// Tabs Content
$output .= '<div class="tab-content" id="learningTabsContent">';

// Вкладка "Вопросы из тестов"
$output .= '<div class="tab-pane fade show active" id="questions" role="tabpanel">';
$output .= $modx->runSnippet('learningMaterials');
$output .= '</div>';

// Вкладка "Учебные статьи"
$output .= '<div class="tab-pane fade" id="articles" role="tabpanel">';
$output .= '<div id="articles-container">';
$output .= '<div class="text-center py-5">';
$output .= '<div class="spinner-border" role="status">';
$output .= '<span class="visually-hidden">Загрузка...</span>';
$output .= '</div>';
$output .= '</div>';
$output .= '</div>';
$output .= '</div>';

$output .= '</div>'; // tab-content
$output .= '</div></div></div>';

// МОДАЛЬНОЕ ОКНО для создания/редактирования
$output .= '<div class="modal fade" id="materialEditorModal" tabindex="-1">';
$output .= '<div class="modal-dialog modal-xl">';
$output .= '<div class="modal-content">';

$output .= '<div class="modal-header">';
$output .= '<h5 class="modal-title" id="materialModalTitle">Создать материал</h5>';
$output .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
$output .= '</div>';

$output .= '<div class="modal-body">';

$output .= '<div class="mb-3">';
$output .= '<label for="material-pagetitle" class="form-label">Название материала</label>';
$output .= '<input type="text" class="form-control" id="material-pagetitle" required>';
$output .= '</div>';

$output .= '<div class="mb-3">';
$output .= '<label for="material-introtext" class="form-label">Краткое описание</label>';
$output .= '<textarea class="form-control" id="material-introtext" rows="2"></textarea>';
$output .= '</div>';

$output .= '<div class="mb-3">';
$output .= '<label class="form-label">Содержимое (поддерживает HTML)</label>';
$output .= '<div id="material-quill-editor" style="min-height: 300px; background: white;"></div>';
$output .= '<textarea id="material-content" style="display:none;"></textarea>';
$output .= '</div>';

$output .= '<div class="form-check form-switch mb-3">';
$output .= '<input class="form-check-input" type="checkbox" id="material-published" style="width: 3em; height: 1.5em;">';
$output .= '<label class="form-check-label ms-2 fw-bold" for="material-published">';
$output .= 'Опубликовать материал';
$output .= '</label>';
$output .= '</div>';

$output .= '</div>'; // modal-body

$output .= '<div class="modal-footer">';
$output .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>';
$output .= '<button type="button" class="btn btn-primary" onclick="saveMaterialFromModal()">';
$output .= '<i class="bi bi-check-circle"></i> Сохранить';
$output .= '</button>';
$output .= '</div>';

$output .= '</div></div></div>';

// Подключение Quill
$output .= '<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">';
$output .= '<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>';

// JavaScript для работы с материалами
$output .= <<<'JAVASCRIPT'
<script>
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

// Загрузка списка материалов
document.addEventListener('DOMContentLoaded', function() {
    const articlesTab = document.getElementById('articles-tab');
    articlesTab?.addEventListener('shown.bs.tab', function() {
        loadArticles();
    });

    // Автооткрытие редактирования
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
        container.innerHTML = `
            <div class="alert alert-danger">
                Ошибка загрузки статей: ${escapeHtml(error.message)}
            </div>
        `;
    }
}

function renderArticles(materials) {
    const container = document.getElementById('articles-container');

    if (!materials || materials.length === 0) {
        container.innerHTML = `
            <div class="alert alert-info text-center py-5">
                <i class="bi bi-journal-text fs-1 d-block mb-3"></i>
                <h5>Нет учебных статей</h5>
                <p class="mb-0">Создайте первую статью через кнопку "Создать материал"</p>
            </div>
        `;
        return;
    }

    let html = '<div class="row g-4">';

    materials.forEach(material => {
        const statusBadge = material.published == 1
            ? '<span class="badge bg-success">Опубликован</span>'
            : '<span class="badge bg-secondary">Черновик</span>';

        html += `
            <div class="col-md-6 col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">${escapeHtml(material.pagetitle)}</h5>
                        <p class="card-text text-muted small">
                            ${escapeHtml(material.introtext || material.description || 'Нет описания')}
                        </p>
                        <div class="mb-3">${statusBadge}</div>
                    </div>
                    <div class="card-footer bg-white">
                        <div class="d-grid gap-2">
                            <a href="${material.url}" class="btn btn-primary">
                                <i class="bi bi-book-half"></i> Читать
                            </a>
                            ${material.can_edit ? `
                                <button class="btn btn-outline-secondary btn-sm" onclick="editMaterial(${material.id})">
                                    <i class="bi bi-pencil"></i> Редактировать
                                </button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
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
        modules: {
            toolbar: [
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
            ]
        }
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
        const data = {
            pagetitle: pagetitle,
            introtext: introtext,
            content: content,
            published: published,
            template: 6,
            parent: MATERIAL_PAGE_ID
        };

        if (currentEditMaterialId) {
            data.material_id = currentEditMaterialId;
        }

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
</script>
JAVASCRIPT;

// Добавляем ID страницы для JS
$output .= '<script>const MATERIAL_PAGE_ID = ' . $materialId . ';</script>';

return $output;