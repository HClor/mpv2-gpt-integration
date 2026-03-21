<?php
/**
 * Сниппет: learningMaterialsTemplate - Шаблон учебных материалов
 * Вызывается из: learning-materials-template-v3.html
 * Назначение: Обрабатывает отображение/редактирование учебных материалов
 *
 * @package TestSystem
 */

require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

if (!$modx->resource) {
    return '<div class="ts-alert ts-alert-danger">Материал недоступен вне контекста ресурса</div>';
}

// Определяем режим работы
$isEdit = isset($_GET['edit']) && $_GET['edit'] == '1';
$editMaterialId = isset($_GET['edit_material']) ? (int)$_GET['edit_material'] : 0;
$materialId = (int)$modx->resource->get('id');
$parentId = (int)$modx->resource->get('parent');

// ID корневой страницы учебных материалов
$rootPageId = 149;

// Проверяем права на редактирование
$canEdit = false;
$canCreate = false; // Право создавать новые материалы

if (PermissionHelper::isAuthenticated($modx) || $modx->user->isAuthenticated('mgr')) {
    $userId = PermissionHelper::getCurrentUserId($modx);
    $isAdmin = PermissionHelper::isAdmin($modx) || $modx->user->isAuthenticated('mgr');
    $isExpert = PermissionHelper::isExpert($modx);

    // Право создавать материалы: Admin или Expert
    $canCreate = $isAdmin || $isExpert;

    // Право редактировать/удалять: автор, Admin или Expert
    $canEdit = $modx->resource && (
        ($modx->resource->get('createdby') == $userId) ||
        $isAdmin ||
        $isExpert
    );
}

// Генерация CSRF токена для всех режимов
require_once MODX_CORE_PATH . 'components/testsystem/security/CsrfProtection.php';
try {
    $csrfToken = CsrfProtection::getToken();
} catch (Exception $e) {
    $csrfToken = '';
    $modx->log(modX::LOG_LEVEL_ERROR, '[learningMaterialsTemplate] Failed to generate CSRF token: ' . $e->getMessage());
}

// РЕЖИМ 1: Редирект на редактирование (через старый параметр ?edit=1)
if ($isEdit && $canEdit) {
    $redirectUrl = $modx->makeUrl($materialId) . '?edit_material=' . $materialId;
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
        // Редактирование открывается на текущей странице материала
        $output .= '<button class="ts-btn ts-btn-primary" onclick="editMaterial(' . $materialId . ')">';
        $output .= '<i class="bi bi-pencil"></i> Редактировать материал';
        $output .= '</button>';
    }

    $output .= '</div>';

    $output .= '<div class="material-content">';
    $output .= $modx->resource->get('content');
    $output .= '</div>';

    $output .= '</div></div></div>';

    // Добавляем скрипты для редактирования на странице материала
    if ($canEdit) {
        // Quill editor
        $output .= '<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">';
        $output .= '<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>';

        // CSRF Token meta tag
        if ($csrfToken) {
            $output .= '<meta name="csrf-token" content="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';
        }

        // Константы
        $output .= '<script>const MATERIAL_PAGE_ID = ' . $rootPageId . ';</script>';
        $output .= '<script>const MATERIAL_PAGE_URL = "' . $modx->makeUrl($rootPageId) . '";</script>';

        // Скрипты управления материалами (с версией для обхода кэша)
        $output .= '<script src="/assets/components/testsystem/js/learning-materials.js?v=' . time() . '"></script>';

        // Модальное окно для редактирования (копируем из режима 3)
        $output .= '<div class="modal fade" id="materialEditorModal" tabindex="-1">';
        $output .= '<div class="modal-dialog modal-xl">';
        $output .= '<div class="modal-content">';
        $output .= '<div class="modal-header">';
        $output .= '<h5 class="modal-title" id="materialModalTitle">Редактировать материал</h5>';
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

        // Получаем категории для выпадающего списка (для режима 2)
        $prefix = $modx->getOption('table_prefix');
        $categoriesStmt = $modx->query("
            SELECT id, name
            FROM {$prefix}test_categories
            ORDER BY sort_order, name
        ");
        $categoriesList = $categoriesStmt ? $categoriesStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $output .= '<div class="mb-3">';
        $output .= '<label for="material-category" class="form-label">Категория</label>';
        $output .= '<select class="form-select" id="material-category">';
        $output .= '<option value="">Без категории</option>';
        foreach ($categoriesList as $cat) {
            $output .= '<option value="' . $cat['id'] . '">' . htmlspecialchars($cat['name']) . '</option>';
        }
        $output .= '</select>';
        $output .= '</div>';

        $output .= '<div class="mb-3">';
        $output .= '<label class="form-label">Содержимое (поддерживает HTML)</label>';
        $output .= '<div id="material-quill-editor" style="min-height: 300px; background: white;"></div>';
        $output .= '<textarea id="material-content" style="display:none;"></textarea>';
        $output .= '</div>';
        $output .= '<div class="form-check form-switch mb-3">';
        $output .= '<input class="form-check-input" type="checkbox" id="material-published" style="width: 3em; height: 1.5em;">';
        $output .= '<label class="form-check-label ms-2 fw-bold" for="material-published">Опубликовать материал</label>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '<div class="modal-footer">';
        $output .= '<button type="button" class="ts-btn ts-btn-danger me-auto" onclick="deleteMaterial(' . $materialId . ', \'' . htmlspecialchars($modx->resource->get('pagetitle'), ENT_QUOTES) . '\')">';
        $output .= '<i class="bi bi-trash"></i> Удалить материал';
        $output .= '</button>';
        $output .= '<button type="button" class="ts-btn ts-btn-secondary" data-bs-dismiss="modal">Отмена</button>';
        $output .= '<button type="button" class="ts-btn ts-btn-primary" onclick="saveMaterialFromModal()">';
        $output .= '<i class="bi bi-check-circle"></i> Сохранить';
        $output .= '</button>';
        $output .= '</div>';
        $output .= '</div></div></div>';
    }

    return $output;
}

// РЕЖИМ 3: Корневая страница с вертикальной структурой
$output = '<div class="container-fluid mt-4">';
$output .= '<div class="row">';
$output .= '<div class="col-12">';

$output .= '<h1 class="mb-4">' . htmlspecialchars($modx->resource->get('pagetitle'), ENT_QUOTES, 'UTF-8') . '</h1>';

// СЕКЦИЯ 1: Вопросы из тестов
$output .= '<section class="mb-5">';
$output .= '<div class="section-header mb-4 pb-3 border-bottom">';
$output .= '<h2 class="h3 mb-2">';
$output .= '<i class="bi bi-collection text-primary"></i> Вопросы из тестов';
$output .= '</h2>';
$output .= '<p class="text-muted mb-0">Учебные материалы из тестовых вопросов, сгруппированные по категориям</p>';
$output .= '</div>';
$output .= $modx->runSnippet('learningMaterials');
$output .= '</section>';

// СЕКЦИЯ 2: Учебные статьи
$output .= '<section class="mb-5">';
$output .= '<div class="section-header mb-4 pb-3 border-bottom d-flex justify-content-between align-items-center">';
$output .= '<div>';
$output .= '<h2 class="h3 mb-2">';
$output .= '<i class="bi bi-journal-text text-success"></i> Учебные статьи';
$output .= '</h2>';
$output .= '<p class="text-muted mb-0">Статьи и материалы, созданные экспертами, сгруппированные по категориям</p>';
$output .= '</div>';

if ($canCreate) {
    $output .= '<button class="ts-btn ts-btn-success" onclick="openCreateMaterialModal()">';
    $output .= '<i class="bi bi-plus-circle"></i> Создать материал';
    $output .= '</button>';
}

$output .= '</div>';

// Вкладки для опубликованных и неопубликованных материалов
if ($canCreate) {
    // Показываем вкладки только авторам/админам/экспертам
    $output .= '<ul class="nav nav-tabs mb-4" id="materialsTab" role="tablist">';
    $output .= '<li class="nav-item" role="presentation">';
    $output .= '<button class="nav-link active" id="published-tab" data-bs-toggle="tab" data-bs-target="#published-materials" type="button" role="tab">';
    $output .= '<i class="bi bi-check-circle"></i> Опубликованные';
    $output .= '</button>';
    $output .= '</li>';
    $output .= '<li class="nav-item" role="presentation">';
    $output .= '<button class="nav-link" id="draft-tab" data-bs-toggle="tab" data-bs-target="#draft-materials" type="button" role="tab">';
    $output .= '<i class="bi bi-file-earmark-text"></i> Черновики';
    $output .= '</button>';
    $output .= '</li>';
    $output .= '</ul>';

    $output .= '<div class="tab-content" id="materialsTabContent">';

    // Вкладка опубликованных материалов
    $output .= '<div class="tab-pane fade show active" id="published-materials" role="tabpanel">';
    $output .= $modx->runSnippet('learningArticles', array('rootPageId' => $rootPageId, 'showDrafts' => 0));
    $output .= '</div>';

    // Вкладка черновиков
    $output .= '<div class="tab-pane fade" id="draft-materials" role="tabpanel">';
    $output .= $modx->runSnippet('learningArticles', array('rootPageId' => $rootPageId, 'showDrafts' => 1));
    $output .= '</div>';

    $output .= '</div>';
} else {
    // Обычным пользователям показываем только опубликованные материалы
    $output .= $modx->runSnippet('learningArticles', array('rootPageId' => $rootPageId, 'showDrafts' => 0));
}

$output .= '</section>';

$output .= '</div></div></div>';

// МОДАЛЬНОЕ ОКНО для просмотра черновиков
$output .= '<div class="modal fade" id="draftViewerModal" tabindex="-1">';
$output .= '<div class="modal-dialog modal-xl">';
$output .= '<div class="modal-content">';
$output .= '<div class="modal-header">';
$output .= '<h5 class="modal-title" id="draftViewerTitle">Просмотр черновика</h5>';
$output .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
$output .= '</div>';
$output .= '<div class="modal-body">';
$output .= '<div id="draft-content" class="material-content"></div>';
$output .= '</div>';
$output .= '<div class="modal-footer">';
$output .= '<button type="button" class="ts-btn ts-btn-secondary" data-bs-dismiss="modal">Закрыть</button>';
$output .= '<button type="button" class="ts-btn ts-btn-primary" id="editDraftButton" onclick="editDraftFromViewer()">';
$output .= '<i class="bi bi-pencil"></i> Редактировать';
$output .= '</button>';
$output .= '</div>';
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

// Получаем категории для выпадающего списка
$prefix = $modx->getOption('table_prefix');
$categoriesStmt = $modx->query("
    SELECT id, name
    FROM {$prefix}test_categories
    ORDER BY sort_order, name
");
$categoriesList = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$output .= '<div class="mb-3">';
$output .= '<label for="material-category" class="form-label">Категория</label>';
$output .= '<select class="form-select" id="material-category">';
$output .= '<option value="">Без категории</option>';
foreach ($categoriesList as $cat) {
    $output .= '<option value="' . $cat['id'] . '">' . htmlspecialchars($cat['name']) . '</option>';
}
$output .= '</select>';
$output .= '<small class="form-text text-muted">Выберите категорию для группировки материала. Категориями можно управлять на странице "Управление категориями".</small>';
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
$output .= '<button type="button" class="ts-btn ts-btn-secondary" data-bs-dismiss="modal">Отмена</button>';
$output .= '<button type="button" class="ts-btn ts-btn-primary" onclick="saveMaterialFromModal()">';
$output .= '<i class="bi bi-check-circle"></i> Сохранить';
$output .= '</button>';
$output .= '</div>';

$output .= '</div></div></div>';

// Подключение Quill
$output .= '<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">';
$output .= '<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>';

// CSRF Token meta tag
if ($csrfToken) {
    $output .= '<meta name="csrf-token" content="' . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';
}

// ВАЖНО: Определяем ID корневой страницы ДО загрузки скрипта
$output .= '<script>const MATERIAL_PAGE_ID = ' . $rootPageId . ';</script>';
$output .= '<script>const MATERIAL_PAGE_URL = "' . $modx->makeUrl($rootPageId) . '";</script>';

// JavaScript для работы с материалами (с версией для обхода кэша)
$output .= '<script src="/assets/components/testsystem/js/learning-materials.js?v=' . time() . '"></script>';

return $output;
