<?php
/**
 * Сниппет: learningPaths - Траектории обучения
 * Вызывается из: MODX ресурсов (страница траекторий)
 * Назначение: Управление траекториями обучения (список, просмотр, создание, запись)
 *
 * Использование в MODX:
 *   [[learningPaths]]                         - Список всех траекторий
 *   [[learningPaths? &mode=`view` &pathId=`1`]] - Просмотр траектории
 *   [[learningPaths? &mode=`edit` &pathId=`1`]] - Редактирование траектории
 *   [[learningPaths? &mode=`my`]]             - Мои траектории (записан)
 *
 * @package TestSystem
 * @version 1.0
 */

// Подключаем bootstrap для CSRF защиты
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

// ВАЖНО: активируем request/session для корректной CSRF-защиты
$modx->getRequest();
$csrfMeta = CsrfProtection::getTokenMeta();

// Подключаем сервисы
require_once MODX_CORE_PATH . 'components/testsystem/services/LearningPathService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/CategoryPermissionService.php';

// Fallback: парсим параметры из REQUEST_URI если $_GET пустой (проблема кэширования MODX)
$urlParams = [];
if (isset($_SERVER['QUERY_STRING']) && !empty($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $urlParams);
}

// mode: GET параметр имеет приоритет над snippet параметром
$snippetMode = $modx->getOption('mode', $scriptProperties, '');

// Пробуем получить mode из разных источников (включая распарсенный QUERY_STRING)
$mode = $snippetMode ?: 'list';
if (isset($_GET['mode']) && !empty($_GET['mode'])) {
    $mode = $_GET['mode'];
} elseif (isset($urlParams['mode']) && !empty($urlParams['mode'])) {
    $mode = $urlParams['mode'];
} elseif (isset($_REQUEST['mode']) && !empty($_REQUEST['mode'])) {
    $mode = $_REQUEST['mode'];
}

// Получаем pathId из разных источников
$pathId = (int)$modx->getOption('pathId', $scriptProperties, 0);
if (isset($_GET['id']) && $_GET['id'] > 0) {
    $pathId = (int)$_GET['id'];
} elseif (isset($urlParams['id']) && $urlParams['id'] > 0) {
    $pathId = (int)$urlParams['id'];
} elseif (isset($_REQUEST['id']) && $_REQUEST['id'] > 0) {
    $pathId = (int)$_REQUEST['id'];
}

$userId = $modx->user->get('id');
$isLoggedIn = $userId > 0;

$prefix = $modx->getOption('table_prefix');

// Проверяем права редактирования (админ или эксперт)
$canCreate = false;
if ($isLoggedIn) {
    $canCreate = CategoryPermissionService::isGlobalAdmin($modx, $userId) ||
                 CategoryPermissionService::isGlobalExpert($modx, $userId);
}

$output = '';

switch ($mode) {
    case 'list':
        // Список всех опубликованных траекторий
        $output = renderPathsList($modx, $prefix, $userId, $canCreate);
        break;

    case 'my':
        // Мои траектории (на которые записан)
        if (!$isLoggedIn) {
            $output = '<div class="ts-alert ts-alert-warning">Войдите для просмотра своих траекторий</div>';
        } else {
            $output = renderMyPaths($modx, $prefix, $userId);
        }
        break;

    case 'view':
        // Просмотр конкретной траектории
        if ($pathId > 0) {
            $output = renderPathView($modx, $prefix, $pathId, $userId, $isLoggedIn);
        } else {
            $output = '<div class="ts-alert ts-alert-warning">Траектория не найдена</div>';
        }
        break;

    case 'edit':
        // Редактирование траектории
        if (!$canCreate) {
            $output = '<div class="ts-alert ts-alert-danger">Нет прав для редактирования</div>';
        } elseif ($pathId > 0) {
            $output = renderPathEditor($modx, $prefix, $pathId, $userId);
        } else {
            $output = '<div class="ts-alert ts-alert-warning">Траектория не найдена</div>';
        }
        break;

    default:
        $output = '<div class="ts-alert ts-alert-warning">Неизвестный режим</div>';
}

return $csrfMeta . $output;

// ============================================
// ФУНКЦИИ РЕНДЕРИНГА
// ============================================

/**
 * Список всех траекторий
 */
function renderPathsList($modx, $prefix, $userId, $canCreate) {
    $html = '
    <section class="ts-leaderboard-page-header">
        <p class="ts-leaderboard-page-header-kicker">Персональные образовательные маршруты</p>
        <h1 class="ts-leaderboard-page-header-title">Траектории обучения</h1>
        <p class="ts-leaderboard-page-header-subtitle">Собирайте структурированные программы обучения, отслеживайте прогресс и назначайте траектории студентам.</p>
    </section>

    <div class="ts-learning-paths-summary">
        <div>
            <h2 class="ts-learning-paths-summary-title">Каталог траекторий</h2>
            <p class="ts-learning-paths-summary-subtitle">Фильтруйте по сложности, статусу публикации и типу, чтобы быстрее найти нужный маршрут.</p>
        </div>
        ' . ($canCreate ? '
        <button class="ts-btn ts-btn-primary" id="create-path-btn">
            <i class="bi bi-plus-circle"></i> Создать траекторию
        </button>
        ' : '') . '
    </div>

    <div class="ts-card ts-learning-paths-filters-card">
        <div class="ts-card-body">
            <div class="row g-2">
                <div class="col-md-3">
                    <select class="form-select" id="filter-difficulty">
                        <option value="">Все уровни сложности</option>
                        <option value="beginner">Начальный</option>
                        <option value="intermediate">Средний</option>
                        <option value="advanced">Продвинутый</option>
                        <option value="expert">Эксперт</option>
                    </select>
                </div>
                ' . ($canCreate ? '
                <div class="col-md-3">
                    <select class="form-select" id="filter-status">
                        <option value="">Все статусы</option>
                        <option value="published">Опубликованные</option>
                        <option value="draft">Черновики</option>
                        <option value="archived">Архивные</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="filter-template">
                        <option value="">Все траектории</option>
                        <option value="1">Только шаблоны</option>
                        <option value="0">Только обычные</option>
                    </select>
                </div>
                ' : '') . '
                <div class="col-md-' . ($canCreate ? '3' : '9') . '">
                    <input type="text" class="form-control" id="filter-search" placeholder="Поиск по названию...">
                </div>
            </div>
        </div>
    </div>

    <div class="ts-card ts-learning-paths-list-card">
        <div class="ts-card-body ts-learning-paths-list-card-body">
            <div class="row g-4" id="learning-paths-container">
                <div class="col-12 text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    ';

    // Модальное окно создания траектории
    $html .= renderPathModal();

    return $html;
}

/**
 * Мои траектории
 */
function renderMyPaths($modx, $prefix, $userId) {
    $html = '
    <div class="mb-4">
        <h2><i class="bi bi-bookmark-star"></i> Мои траектории обучения</h2>
        <p class="text-muted">Траектории, на которые вы записаны</p>
    </div>

    <div class="row g-4" id="my-paths-container">
        <div class="col-12 text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        loadMyPaths();
    });

    async function loadMyPaths() {
        try {
            // Получаем CSRF токен из meta тега
            const csrfToken = document.querySelector("meta[name=\'csrf-token\']")?.content || "";

            const response = await fetch(window.TS_API_URL || "/assets/components/testsystem/ajax/testsystem.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-Token": csrfToken
                },
                body: JSON.stringify({ action: "getMyPaths", data: {} })
            });
            const result = await response.json();
            const container = document.getElementById("my-paths-container");

            if (result.success && result.data && result.data.length > 0) {
                container.innerHTML = result.data.map(function(path) { return renderMyPathCard(path); }).join("");
            } else {
                container.innerHTML = "<div class=\"col-12\">" +
                    "<div class=\"alert alert-info text-center py-5\">" +
                        "<i class=\"bi bi-signpost fs-1 d-block mb-3\"></i>" +
                        "<h5>Вы пока не записаны ни на одну траекторию</h5>" +
                        "<p class=\"mb-3\">Просмотрите доступные траектории и запишитесь на интересующие</p>" +
                        "<a href=\"/learning-paths\" class=\"btn btn-primary\">Просмотреть траектории</a>" +
                    "</div>" +
                "</div>";
            }
        } catch (error) {
            console.error("Error loading my paths:", error);
        }
    }

    function renderMyPathCard(path) {
        var progress = parseInt(path.completion_pct) || 0;
        var progressClass = progress === 100 ? "bg-success" : progress > 0 ? "bg-primary" : "bg-secondary";
        var btnText = progress > 0 ? "Продолжить" : "Начать";
        // user_status - статус прогресса пользователя (not_started, in_progress, completed)
        var userStatus = path.user_status || "not_started";

        return "<div class=\"col-md-6 col-lg-4\">" +
            "<div class=\"card h-100 shadow-sm\">" +
                "<div class=\"card-body\">" +
                    "<h5 class=\"card-title\">" + escapeHtml(path.name) + "</h5>" +
                    "<p class=\"text-muted small\">" + escapeHtml(path.description || "") + "</p>" +
                    "<div class=\"progress mb-3\" style=\"height: 20px;\">" +
                        "<div class=\"progress-bar " + progressClass + "\" style=\"width: " + progress + "%\">" +
                            progress + "%" +
                        "</div>" +
                    "</div>" +
                    "<div class=\"d-flex justify-content-between text-muted small mb-3\">" +
                        "<span><i class=\"bi bi-check-circle\"></i> " + (path.completed_steps || 0) + "/" + (path.total_steps || 0) + " шагов</span>" +
                        "<span class=\"badge bg-" + getStatusColor(userStatus) + "\">" + getStatusText(userStatus) + "</span>" +
                    "</div>" +
                "</div>" +
                "<div class=\"card-footer bg-white\">" +
                    "<a href=\"/learning-paths?mode=view&id=" + (path.path_id || path.id) + "\" class=\"btn btn-primary w-100\">" +
                        "<i class=\"bi bi-play-fill\"></i> " + btnText +
                    "</a>" +
                "</div>" +
            "</div>" +
        "</div>";
    }

    function getStatusColor(status) {
        const colors = { completed: "success", in_progress: "primary", not_started: "secondary", created: "info" };
        return colors[status] || "secondary";
    }

    function getStatusText(status) {
        const texts = { completed: "Завершено", in_progress: "В процессе", not_started: "Не начато", created: "Создано мной" };
        return texts[status] || status;
    }

    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text || "";
        return div.innerHTML;
    }
    </script>
    ';

    return $html;
}

/**
 * Просмотр траектории
 */
function renderPathView($modx, $prefix, $pathId, $userId, $isLoggedIn) {
    $html = '
    <div id="path-view-container" data-path-id="' . $pathId . '">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
            </div>
            <p class="mt-3 text-muted">Загрузка траектории...</p>
        </div>
    </div>

    <!-- Секция достижений -->
    <div id="achievements-section" class="mt-5" style="display: none;">
        <h4><i class="bi bi-trophy"></i> Достижения траектории</h4>
        <div id="achievements-container" class="row g-3 mt-2"></div>
    </div>
    ';

    return $html;
}

/**
 * Редактор траектории
 */
function renderPathEditor($modx, $prefix, $pathId, $userId) {
    // Получаем текущий URL для корректных ссылок
    $currentUrl = $modx->makeUrl($modx->resource->get('id'));

    $html = '
    <div id="path-editor-container" data-path-id="' . $pathId . '">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="' . $currentUrl . '" class="text-muted text-decoration-none">
                    <i class="bi bi-arrow-left"></i> К списку траекторий
                </a>
                <h2 class="mt-2"><i class="bi bi-pencil-square"></i> Редактирование траектории</h2>
                <h4 id="editor-path-title" class="text-muted">Загрузка...</h4>
            </div>
            <div class="btn-group">
                <button class="ts-btn ts-btn-ghost" id="edit-path-info-btn"
                        onclick="LearningPaths.showEditPathSettings()">
                    <i class="bi bi-gear"></i> Настройки
                </button>
                <button class="ts-btn ts-btn-ghost-success" id="manage-students-btn"
                        onclick="LearningPaths.showManageStudents()">
                    <i class="bi bi-people"></i> Студенты
                </button>
                <a href="' . $currentUrl . '?mode=view&id=' . $pathId . '" class="ts-btn ts-btn-ghost">
                    <i class="bi bi-eye"></i> Просмотр
                </a>
            </div>
        </div>

        <!-- Информация о траектории -->
        <div class="card mb-4" id="path-info-card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <p id="editor-path-description" class="text-muted mb-0">Загрузка описания...</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <span id="editor-path-status" class="ts-badge ts-badge-neutral">Загрузка...</span>
                        <span id="editor-path-difficulty" class="ts-badge ts-badge-primary ms-1">Загрузка...</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Шаги -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ol"></i> Шаги траектории</h5>
                <div>
                    <button class="ts-btn ts-btn-secondary ts-btn-sm me-2" id="save-steps-order-btn">
                        <i class="bi bi-arrows-move"></i> Сохранить порядок
                    </button>
                    <button class="ts-btn ts-btn-primary ts-btn-sm" id="add-step-btn">
                        <i class="bi bi-plus-circle"></i> Добавить шаг
                    </button>
                </div>
            </div>
            <div class="card-body" id="steps-editor-container">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                </div>
            </div>
        </div>
    </div>
    ';

    // Добавляем модальные окна
    $html .= renderPathModal();  // Для настроек
    $html .= renderStepModal();

    return $html;
}

/**
 * Модальное окно создания/редактирования траектории
 */
function renderPathModal() {
    return '
    <div class="modal fade" id="pathModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="path-modal-title">Создать траекторию</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название *</label>
                        <input type="text" class="form-control" id="path-title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Описание</label>
                        <textarea class="form-control" id="path-description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Уровень сложности</label>
                        <select class="form-select" id="path-difficulty">
                            <option value="beginner">Начальный</option>
                            <option value="intermediate">Средний</option>
                            <option value="advanced">Продвинутый</option>
                            <option value="expert">Эксперт</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Примерное время (часов)</label>
                        <input type="number" class="form-control" id="path-estimated-hours" min="1" value="10">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="path-has-certificate">
                        <label class="form-check-label" for="path-has-certificate">
                            Выдавать сертификат по завершению
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="path-is-template">
                        <label class="form-check-label" for="path-is-template">
                            <i class="bi bi-file-earmark-text text-info"></i> Сохранить как шаблон
                        </label>
                        <small class="form-text text-muted d-block">
                            Шаблоны можно клонировать для создания индивидуальных траекторий
                        </small>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="path-published">
                        <label class="form-check-label" for="path-published">
                            Опубликовать сразу
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ts-btn ts-btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="ts-btn ts-btn-primary" id="save-path-info-btn">
                        <i class="bi bi-check-circle"></i> Сохранить
                    </button>
                </div>
            </div>
        </div>
    </div>
    ';
}

/**
 * Модальное окно добавления/редактирования шага
 */
function renderStepModal() {
    return '
    <div class="modal fade" id="stepModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="step-modal-title">Добавить шаг</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Название шага *</label>
                        <input type="text" class="form-control" id="step-title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Описание</label>
                        <textarea class="form-control" id="step-description" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Тип контента *</label>
                        <select class="form-select" id="step-type">
                            <option value="material">Учебный материал</option>
                            <option value="test">Тест</option>
                        </select>
                    </div>

                    <!-- Выбор контента -->
                    <div class="mb-3">
                        <label class="form-label">Выберите контент *</label>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="step-content-search"
                                   placeholder="Поиск по названию...">
                            <button class="ts-btn ts-btn-secondary" type="button" id="step-content-search-btn">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <div id="step-content-list" class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                            <div class="text-muted text-center py-3">
                                <i class="bi bi-arrow-up"></i> Начните поиск или нажмите кнопку для загрузки списка
                            </div>
                        </div>
                        <input type="hidden" id="step-content-id">
                        <div id="step-content-selected" class="ts-alert ts-alert-success mt-2 d-none">
                            Выбрано: <strong id="step-content-selected-name"></strong>
                        </div>
                    </div>

                    <hr>
                    <h6>Условия разблокировки</h6>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="step-unlock-previous" checked>
                        <label class="form-check-label" for="step-unlock-previous">
                            Требовать завершение предыдущего шага
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Минимальный балл на предыдущем шаге</label>
                        <input type="number" class="form-control" id="step-unlock-min-score"
                               min="0" max="100" placeholder="Оставьте пустым если не требуется">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ts-btn ts-btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="ts-btn ts-btn-primary" id="save-step-btn">
                        <i class="bi bi-check-circle"></i> Добавить
                    </button>
                </div>
            </div>
        </div>
    </div>
    ';

    return $html;
}
