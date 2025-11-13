<?php
/**
 * Knowledge Areas Manager v1.2
 * Управление областями знаний пользователя
 */

if (!$modx instanceof modX) {
    return '<div class="alert alert-danger">MODX context required</div>';
}

// Проверка авторизации
if (!$modx->user->hasSessionContext('web')) {
    $authId = (int)$modx->getOption('lms.auth_page', null, 0);
    $authUrl = $authId > 0 ? rtrim($modx->makeUrl($authId, 'web', []), '/') : '#';
    return '<div class="alert alert-warning">
        <p>Для управления областями знаний необходимо <a href="' . htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8') . '">войти в систему</a>.</p>
    </div>';
}

// ============================================
// КОНФИГУРАЦИЯ: ID страниц
// ============================================
$knowledgeAreaPageId = 145; // ID ресурса "Область знаний"
$manageAreasPageId = 125;   // ID ресурса "Мои области знаний"

// Формируем относительные URL без trailing slash
$knowledgeAreaPageUrl = rtrim($modx->makeUrl($knowledgeAreaPageId, 'web', []), '/');
$manageAreasPageUrl = rtrim($modx->makeUrl($manageAreasPageId, 'web', []), '/');
// ============================================

$assetsUrl = rtrim($modx->getOption('assets_url', null, MODX_ASSETS_URL), '/') . '/';
$cssPath = $assetsUrl . 'components/testsystem/css/tsrunner.css';
$jsPath = $assetsUrl . 'components/testsystem/js/knowledge-areas.js';

$output = '';

// Подключаем стили и скрипты
$output .= '<link rel="stylesheet" href="' . htmlspecialchars($cssPath, ENT_QUOTES, 'UTF-8') . '">';

// ВАЖНО: Передаем URL в data-атрибутах
$output .= '<div id="knowledge-areas-container" class="knowledge-areas-wrapper" 
    data-test-page-url="' . htmlspecialchars($knowledgeAreaPageUrl, ENT_QUOTES, 'UTF-8') . '"
    data-manage-page-url="' . htmlspecialchars($manageAreasPageUrl, ENT_QUOTES, 'UTF-8') . '">';

// Заголовок и кнопка создания
$output .= '<div class="d-flex justify-content-between align-items-center mb-4">';
$output .= '<h2><i class="bi bi-collection"></i> Мои области знаний</h2>';
$output .= '<button class="btn btn-success" id="create-area-btn">';
$output .= '<i class="bi bi-plus-circle"></i> Создать область';
$output .= '</button>';
$output .= '</div>';

// Описание
$output .= '<div class="alert alert-info">';
$output .= '<strong>💡 Что такое область знаний?</strong><br>';
$output .= 'Это персональная подборка тестов из разных категорий. ';
$output .= 'Вы выбираете интересующие вас тесты, и система генерирует комбинированный тест со случайными вопросами.';
$output .= '</div>';

// Контейнер для списка областей (будет заполнен через JS)
$output .= '<div id="areas-list-container" class="row g-3">';
$output .= '<div class="col-12 text-center py-5">';
$output .= '<div class="spinner-border text-primary" role="status">';
$output .= '<span class="visually-hidden">Загрузка...</span>';
$output .= '</div>';
$output .= '</div>';
$output .= '</div>';

$output .= '</div>'; // #knowledge-areas-container

// Модальное окно создания/редактирования области
$output .= '
<div class="modal fade" id="areaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="areaModalTitle">
                    <i class="bi bi-plus-circle"></i> Создание области знаний
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Название области -->
                <div class="mb-3">
                    <label for="area-name" class="form-label fw-bold">Название области знаний</label>
                    <input type="text" class="form-control" id="area-name" 
                           placeholder="Например: Психология и саморазвитие" maxlength="255" required>
                </div>
                
                <!-- Описание (опционально) -->
                <div class="mb-3">
                    <label for="area-description" class="form-label fw-bold">Описание (необязательно)</label>
                    <textarea class="form-control" id="area-description" rows="2" 
                              placeholder="Краткое описание области знаний"></textarea>
                </div>
                
                <hr>
                
                <!-- Поиск по тестам -->
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-search"></i> Поиск тестов
                    </label>
                    <input type="text" class="form-control" id="test-search-input" 
                           placeholder="Введите название теста или категории...">
                </div>
                
                <!-- Дерево тестов -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Выберите тесты:</label>
                    <div id="tests-tree-container" class="tests-tree border rounded p-3" style="max-height: 400px; overflow-y: auto;">
                        <div class="text-center py-4">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Загрузка тестов...</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Статистика выбора -->
                <div class="alert alert-light border">
                    <div class="row text-center">
                        <div class="col-6">
                            <strong class="d-block text-muted small">Выбрано тестов</strong>
                            <span class="fs-4 text-primary" id="selected-tests-count">0</span>
                        </div>
                        <div class="col-6">
                            <strong class="d-block text-muted small">Примерно вопросов</strong>
                            <span class="fs-4 text-success" id="estimated-questions-count">0</span>
                        </div>
                    </div>
                </div>
                
                <!-- Количество вопросов за сессию -->
                <div class="mb-3">
                    <label for="questions-per-session" class="form-label fw-bold">
                        Количество вопросов за одну сессию
                    </label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="questions-per-session" 
                               min="5" max="200" value="20">
                        <button class="btn btn-outline-secondary" type="button" id="set-max-questions">
                            Максимум
                        </button>
                    </div>
                    <small class="form-text text-muted">
                        Рекомендуется 20-50 вопросов. Максимум: <span id="max-questions-hint">—</span>
                    </small>
                </div>

                <!-- Распределение вопросов по тестам -->
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-sliders"></i> Распределение вопросов по тестам
                    </label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="distribution-mode" 
                               id="distribution-proportional" value="proportional" checked>
                        <label class="form-check-label" for="distribution-proportional">
                            <strong>Пропорционально</strong>
                            <small class="d-block text-muted">
                                Из тестов с большим количеством вопросов берётся больше вопросов
                            </small>
                        </label>
                    </div>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="radio" name="distribution-mode" 
                               id="distribution-equal" value="equal">
                        <label class="form-check-label" for="distribution-equal">
                            <strong>Поровну из каждого</strong>
                            <small class="d-block text-muted">
                                Из каждого теста берётся одинаковое количество вопросов
                            </small>
                        </label>
                    </div>
                </div>
                
                <!-- Минимум вопросов из теста -->
                <div class="mb-3">
                    <label for="min-questions-per-test" class="form-label fw-bold">
                        Минимум вопросов из каждого теста
                    </label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="min-questions-per-test" 
                               min="1" max="20" value="3">
                        <span class="input-group-text">вопросов</span>
                    </div>
                    <small class="form-text text-muted">
                        Если в тесте меньше вопросов, берутся все доступные
                    </small>
                </div>
                
                <!-- Превью распределения -->
                <div class="alert alert-light border" id="distribution-preview">
                    <strong class="d-block mb-2">📊 Предварительное распределение:</strong>
                    <div id="distribution-details" class="small text-muted">
                        Выберите тесты и укажите количество вопросов
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <input type="hidden" id="editing-area-id" value="">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Отмена
                </button>
                <button type="button" class="btn btn-success" id="save-area-btn">
                    <i class="bi bi-check-circle"></i> Сохранить область
                </button>
            </div>
        </div>
    </div>
</div>';

// Модальное окно подтверждения удаления
$output .= '
<div class="modal fade" id="deleteAreaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle-fill"></i> Удаление области знаний
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Вы уверены, что хотите удалить область знаний "<strong id="delete-area-name"></strong>"?</p>
                <p class="text-muted mb-0">Это действие нельзя отменить.</p>
            </div>
            <div class="modal-footer">
                <input type="hidden" id="deleting-area-id" value="">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-area-btn">
                    <i class="bi bi-trash"></i> Удалить
                </button>
            </div>
        </div>
    </div>
</div>';

// Подключаем JS
$output .= '<script src="' . htmlspecialchars($jsPath, ENT_QUOTES, 'UTF-8') . '"></script>';

return $output;