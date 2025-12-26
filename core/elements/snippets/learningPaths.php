<?php
/**
 * learningPaths - Сниппет для отображения траекторий обучения
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

// Подключаем сервисы
require_once MODX_CORE_PATH . 'components/testsystem/services/LearningPathService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/CategoryPermissionService.php';

$mode = $modx->getOption('mode', $scriptProperties, 'list');
$pathId = (int)$modx->getOption('pathId', $scriptProperties, $_GET['id'] ?? 0);
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
            $output = '<div class="alert alert-warning">Войдите для просмотра своих траекторий</div>';
        } else {
            $output = renderMyPaths($modx, $prefix, $userId);
        }
        break;

    case 'view':
        // Просмотр конкретной траектории
        if ($pathId > 0) {
            $output = renderPathView($modx, $prefix, $pathId, $userId, $isLoggedIn);
        } else {
            $output = '<div class="alert alert-warning">Траектория не найдена</div>';
        }
        break;

    case 'edit':
        // Редактирование траектории
        if (!$canCreate) {
            $output = '<div class="alert alert-danger">Нет прав для редактирования</div>';
        } elseif ($pathId > 0) {
            $output = renderPathEditor($modx, $prefix, $pathId, $userId);
        } else {
            $output = '<div class="alert alert-warning">Траектория не найдена</div>';
        }
        break;

    default:
        $output = '<div class="alert alert-warning">Неизвестный режим</div>';
}

return $output;

// ============================================
// ФУНКЦИИ РЕНДЕРИНГА
// ============================================

/**
 * Список всех траекторий
 */
function renderPathsList($modx, $prefix, $userId, $canCreate) {
    $html = '
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="bi bi-signpost"></i> Траектории обучения</h2>
        ' . ($canCreate ? '
        <button class="btn btn-primary" id="create-path-btn">
            <i class="bi bi-plus-circle"></i> Создать траекторию
        </button>' : '') . '
    </div>

    <!-- Фильтры -->
    <div class="row mb-3">
        <div class="col-md-6">
            <select class="form-select" id="filter-difficulty">
                <option value="">Все уровни сложности</option>
                <option value="beginner">Начальный</option>
                <option value="intermediate">Средний</option>
                <option value="advanced">Продвинутый</option>
                <option value="expert">Эксперт</option>
            </select>
        </div>
        <div class="col-md-6">
            <input type="text" class="form-control" id="filter-search" placeholder="Поиск по названию...">
        </div>
    </div>

    <!-- Контейнер для траекторий (заполняется через JS) -->
    <div class="row g-4" id="learning-paths-container">
        <div class="col-12 text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Загрузка...</span>
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
            const response = await fetch(window.TS_API_URL || "/assets/components/testsystem/ajax/testsystem.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "getMyPaths", data: {} })
            });
            const result = await response.json();
            const container = document.getElementById("my-paths-container");

            if (result.success && result.data && result.data.length > 0) {
                container.innerHTML = result.data.map(path => renderMyPathCard(path)).join("");
            } else {
                container.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-info text-center py-5">
                            <i class="bi bi-signpost fs-1 d-block mb-3"></i>
                            <h5>Вы пока не записаны ни на одну траекторию</h5>
                            <p class="mb-3">Просмотрите доступные траектории и запишитесь на интересующие</p>
                            <a href="' . $modx->makeUrl($modx->resource->get('id')) . '?mode=list" class="btn btn-primary">
                                Просмотреть траектории
                            </a>
                        </div>
                    </div>
                `;
            }
        } catch (error) {
            console.error("Error loading my paths:", error);
        }
    }

    function renderMyPathCard(path) {
        const progress = path.completion_pct || 0;
        const progressClass = progress === 100 ? "bg-success" : progress > 0 ? "bg-primary" : "bg-secondary";

        return `
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">${escapeHtml(path.name)}</h5>
                        <p class="text-muted small">${escapeHtml(path.description || "")}</p>

                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar ${progressClass}" style="width: ${progress}%">
                                ${progress}%
                            </div>
                        </div>

                        <div class="d-flex justify-content-between text-muted small mb-3">
                            <span><i class="bi bi-check-circle"></i> ${path.completed_steps || 0}/${path.total_steps || 0} шагов</span>
                            <span class="badge bg-${getStatusColor(path.status)}">${getStatusText(path.status)}</span>
                        </div>
                    </div>
                    <div class="card-footer bg-white">
                        <a href="?mode=view&id=${path.path_id}" class="btn btn-primary w-100">
                            <i class="bi bi-play-fill"></i> ${progress > 0 ? "Продолжить" : "Начать"}
                        </a>
                    </div>
                </div>
            </div>
        `;
    }

    function getStatusColor(status) {
        const colors = { completed: "success", in_progress: "primary", not_started: "secondary" };
        return colors[status] || "secondary";
    }

    function getStatusText(status) {
        const texts = { completed: "Завершено", in_progress: "В процессе", not_started: "Не начато" };
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

    <style>
        .step-timeline { position: relative; padding-left: 40px; }
        .step-timeline::before {
            content: ""; position: absolute; left: 15px; top: 0; bottom: 0;
            width: 2px; background: #dee2e6;
        }
        .step-card { position: relative; margin-bottom: 1.5rem; }
        .step-card::before {
            content: ""; position: absolute; left: -33px; top: 50%;
            transform: translateY(-50%); width: 16px; height: 16px;
            border-radius: 50%; background: #fff; border: 2px solid #dee2e6; z-index: 1;
        }
        .step-card.completed::before { background: #198754; border-color: #198754; }
        .step-card.in-progress::before { background: #0d6efd; border-color: #0d6efd; }
        .step-card.available::before { background: #fff; border-color: #0d6efd; }
        .step-card.locked::before { background: #adb5bd; border-color: #adb5bd; }
    </style>
    ';

    return $html;
}

/**
 * Редактор траектории
 */
function renderPathEditor($modx, $prefix, $pathId, $userId) {
    $html = '
    <div id="path-editor-container" data-path-id="' . $pathId . '">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-pencil-square"></i> Редактирование траектории</h2>
                <h4 id="editor-path-title" class="text-muted">Загрузка...</h4>
            </div>
            <div class="btn-group">
                <button class="btn btn-outline-primary" id="edit-path-info-btn">
                    <i class="bi bi-gear"></i> Настройки
                </button>
                <button class="btn btn-outline-success" id="manage-students-btn">
                    <i class="bi bi-people"></i> Студенты
                </button>
                <a href="?mode=view&id=' . $pathId . '" class="btn btn-outline-info">
                    <i class="bi bi-eye"></i> Просмотр
                </a>
            </div>
        </div>

        <!-- Шаги -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-list-ol"></i> Шаги траектории</h5>
                <div>
                    <button class="btn btn-outline-secondary btn-sm me-2" id="save-steps-order-btn">
                        <i class="bi bi-arrows-move"></i> Сохранить порядок
                    </button>
                    <button class="btn btn-primary btn-sm" id="add-step-btn">
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="save-path-info-btn">
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
        <div class="modal-dialog">
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
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Тип контента *</label>
                            <select class="form-select" id="step-type">
                                <option value="material">Учебный материал</option>
                                <option value="test">Тест</option>
                                <option value="quiz">Викторина</option>
                                <option value="assignment">Задание</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ID контента *</label>
                            <input type="number" class="form-control" id="step-content-id" min="1">
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="button" class="btn btn-primary" id="save-step-btn">
                        <i class="bi bi-check-circle"></i> Добавить
                    </button>
                </div>
            </div>
        </div>
    </div>
    ';
}
