<?php
/**
 * Learning Materials Template Handler
 * Обрабатывает отображение учебных материалов в зависимости от режима
 */

// Определяем режим работы
$isEdit = isset($_GET['edit']) && $_GET['edit'] == '1';
$editMaterialId = isset($_GET['edit_material']) ? (int)$_GET['edit_material'] : 0;
$materialId = $modx->resource ? (int)$modx->resource->get('id') : 0;
$parentId = $modx->resource ? (int)$modx->resource->get('parent') : 0;

// Проверяем права на редактирование
require_once MODX_CORE_PATH . 'components/testsystem/helpers/Config.php';
require_once MODX_CORE_PATH . 'components/testsystem/helpers/PermissionHelper.php';
$canEdit = false;
if (PermissionHelper::isAuthenticated($modx) || $modx->user->isAuthenticated('mgr')) {
    $userId = PermissionHelper::getCurrentUserId($modx);
    $isAdmin = PermissionHelper::isAdmin($modx) || $modx->user->isAuthenticated('mgr');
    $canEdit = $modx->resource && (($modx->resource->get('createdby') == $userId) || $isAdmin);
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
$output .= '<script src="/assets/components/testsystem/js/learning-materials.js"></script>';

// Добавляем ID страницы для JS
$output .= '<script>const MATERIAL_PAGE_ID = ' . $materialId . ';</script>';

return $output;
