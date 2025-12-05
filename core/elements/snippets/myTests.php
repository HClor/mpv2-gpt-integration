<?php
// Snippet: myTests
// Описание: Управление личными тестами пользователя

// Подключаем bootstrap для CSRF защиты
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

try {
    PermissionHelper::requireAuthentication($modx);
} catch (AuthenticationException $e) {
    return $e->renderAlert($modx, 'Для управления тестами необходимо войти в систему.');
}

$assetsUrl = rtrim($modx->getOption('assets_url', null, MODX_ASSETS_URL), '/') . '/';
$jsPath = $assetsUrl . 'components/testsystem/js/mytests.js';

// Получаем ID страницы создания теста
$createTestPageId = (int)$modx->getOption('lms.create_test_page', null, 0);
$createTestUrl = '#';
if ($createTestPageId > 0) {
    $createTestUrl = $modx->makeUrl($createTestPageId, 'web', [], 'full');
}

// CSRF Protection: добавляем meta тег с токеном для JavaScript
$output = CsrfProtection::getTokenMeta();
$output .= '<div id="my-tests-container">';
$output .= '<div class="d-flex justify-content-between align-items-center mb-4">';
$output .= '<h2>Мои тесты</h2>';
if ($createTestPageId > 0) {
    $output .= '<a href="' . htmlspecialchars($createTestUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Создать тест</a>';
} else {
    // Если страница не настроена, используем модальное окно
    $output .= '<button class="btn btn-primary" onclick="showCreateTestModal()"><i class="bi bi-plus-circle"></i> Создать тест</button>';
}
$output .= '</div>';

$output .= '<ul class="nav nav-tabs mb-3" role="tablist">';
$output .= '<li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#created">Созданные мной</a></li>';
$output .= '<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#shared">Доступны мне</a></li>';
$output .= '<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#public">Публичные</a></li>';
$output .= '</ul>';

$output .= '<div class="tab-content">';
$output .= '<div class="tab-pane fade show active" id="created"><div class="text-center py-5"><div class="spinner-border" role="status"></div></div></div>';
$output .= '<div class="tab-pane fade" id="shared"><div class="text-center py-5"><div class="spinner-border" role="status"></div></div></div>';
$output .= '<div class="tab-pane fade" id="public"><div class="text-center py-5"><div class="spinner-border" role="status"></div></div></div>';
$output .= '</div>';

$output .= '</div>';

// Модальное окно создания теста
$output .= '<div class="modal fade" id="createTestModal" tabindex="-1">';
$output .= '<div class="modal-dialog">';
$output .= '<div class="modal-content">';
$output .= '<div class="modal-header">';
$output .= '<h5 class="modal-title">Создать тест</h5>';
$output .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>';
$output .= '</div>';
$output .= '<div class="modal-body">';
$output .= '<div class="mb-3">';
$output .= '<label class="form-label">Название теста *</label>';
$output .= '<input type="text" class="form-control" id="new-test-title" required>';
$output .= '</div>';
$output .= '<div class="mb-3">';
$output .= '<label class="form-label">Описание</label>';
$output .= '<textarea class="form-control" id="new-test-description" rows="3"></textarea>';
$output .= '</div>';
$output .= '<div class="mb-3">';
$output .= '<label class="form-label">Статус публикации</label>';
$output .= '<select class="form-select" id="new-test-publication-status">';
$output .= '<option value="private">🔒 Приватный (только по приглашению)</option>';
$output .= '<option value="draft">📝 Черновик (только я)</option>';
$output .= '</select>';
$output .= '<small class="form-text text-muted">Вы сможете изменить статус позже</small>';
$output .= '</div>';
$output .= '</div>';
$output .= '<div class="modal-footer">';
$output .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>';
$output .= '<button type="button" class="btn btn-primary" onclick="createTest()">Создать</button>';
$output .= '</div>';
$output .= '</div></div></div>';

$output .= '<script src="' . htmlspecialchars($jsPath, ENT_QUOTES, 'UTF-8') . '"></script>';

return $output;