<?php
/**
 * Сниппет: myTests - Личные тесты пользователя
 * Вызывается из: MODX ресурсов (страница "Мои тесты")
 * Назначение: Управление личными тестами пользователя (создание, редактирование)
 *
 * @package TestSystem
 */

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

// Получаем ID страницы запуска тестов для JavaScript
$testPageId = (int)$modx->getOption('lms.test_page', null, 0);
$testPageUrl = '';
if ($testPageId > 0) {
    $testPageUrl = $modx->makeUrl($testPageId, 'web', [], 'full');
}

// CSRF Protection: добавляем meta тег с токеном для JavaScript
$output = CsrfProtection::getTokenMeta();

// Добавляем URL страницы тестов для JavaScript
if (!empty($testPageUrl)) {
    $output .= '<meta name="test-page-url" content="' . htmlspecialchars($testPageUrl, ENT_QUOTES, 'UTF-8') . '">';
}
$output .= '<div id="my-tests-container">';
$output .= '<div class="ts-card mb-4 p-3 p-md-4">';
$output .= '<div class="d-flex justify-content-between align-items-start align-items-md-center gap-3 flex-column flex-md-row">';
$output .= '<div>';
$output .= '<h2 class="h4 mb-1"><i class="bi bi-journal-check me-2 text-primary"></i>Мои тесты</h2>';
$output .= '<p class="text-muted mb-0">Создавайте, редактируйте и публикуйте тесты в одном месте</p>';
$output .= '</div>';
if ($createTestPageId > 0) {
    $output .= '<a href="' . htmlspecialchars($createTestUrl, ENT_QUOTES, 'UTF-8') . '" class="ts-btn ts-btn-primary"><i class="bi bi-plus-circle"></i> Создать тест</a>';
} else {
    // Если страница не настроена, используем модальное окно
    $output .= '<button class="ts-btn ts-btn-primary" onclick="showCreateTestModal()"><i class="bi bi-plus-circle"></i> Создать тест</button>';
}
$output .= '</div>';
$output .= '</div>';

$output .= '<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto" role="tablist">';
$output .= '<li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#created"><i class="bi bi-person-check me-1"></i>Созданные мной</a></li>';
$output .= '<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#shared"><i class="bi bi-people me-1"></i>Доступны мне</a></li>';
$output .= '<li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#public"><i class="bi bi-globe2 me-1"></i>Публичные</a></li>';
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
$output .= '<button type="button" class="ts-btn ts-btn-secondary" data-bs-dismiss="modal">Отмена</button>';
$output .= '<button type="button" class="ts-btn ts-btn-primary" onclick="createTest()">Создать</button>';
$output .= '</div>';
$output .= '</div></div></div>';

$output .= '<script src="' . htmlspecialchars($jsPath, ENT_QUOTES, 'UTF-8') . '"></script>';

return $output;
