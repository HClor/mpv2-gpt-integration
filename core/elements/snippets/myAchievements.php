<?php
/**
 * Сниппет: myAchievements - Мои достижения
 * Вызывается из: MODX ресурсов (страница достижений)
 * Назначение: Отображает список достижений пользователя
 *
 * @package TestSystem
 */

if (!$modx instanceof modX) {
    return '';
}

$userId = $modx->user->id;

if (!$userId) {
    $authUrl = $modx->makeUrl(24, '', '', 'abs');
    return '<div class="ts-alert ts-alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Войдите, чтобы видеть свои достижения.
        <br><a class="ts-btn ts-btn-primary mt-2" href="' . htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8') . '">Войти</a>
    </div>';
}

// Подключаем сервис траекторий
require_once MODX_CORE_PATH . 'components/testsystem/services/LearningPathService.php';

$prefix = $modx->getOption('table_prefix');
$h = function($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

// Получаем достижения пользователя через сервис
$achievements = LearningPathService::getUserAchievements($modx, $userId);

$html = [];
$html[] = '<div class="achievements-container">';

$html[] = '<div class="d-flex justify-content-between align-items-center mb-4">';
$html[] = '<h2><i class="bi bi-trophy me-2"></i>Мои достижения</h2>';
$html[] = '</div>';

// Статистика
$totalAchievements = count($achievements);
$html[] = '<div class="row mb-4">';
$html[] = '<div class="col-md-4">';
$html[] = '<div class="card bg-warning text-dark">';
$html[] = '<div class="card-body text-center">';
$html[] = '<h3 class="mb-0">' . $totalAchievements . '</h3>';
$html[] = '<p class="mb-0">Всего достижений</p>';
$html[] = '</div></div></div>';
$html[] = '</div>';

if (empty($achievements)) {
    $pathsUrl = $modx->makeUrl(125, '', '', 'abs');
    $html[] = '
        <div class="ts-alert ts-alert-info text-center py-5">
            <i class="bi bi-trophy fs-1 d-block mb-3"></i>
            <h5>У вас пока нет достижений</h5>
            <p class="mb-3">Проходите траектории обучения, чтобы получить достижения</p>
            <a href="' . $h($pathsUrl) . '" class="ts-btn ts-btn-primary">
                <i class="bi bi-mortarboard me-1"></i>Перейти к траекториям
            </a>
        </div>
    ';
} else {
    $html[] = '<div class="row g-4">';

    foreach ($achievements as $achievement) {
        $icon = $achievement['badge_icon'] ?? 'bi-star-fill';
        $earnedDate = $achievement['earned_at'] ? date('d.m.Y', strtotime($achievement['earned_at'])) : '';

        $html[] = '
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-warning">
                <div class="card-body text-center">
                    <div class="achievement-badge mb-3">
                        <i class="bi ' . $h($icon) . ' text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title">' . $h($achievement['name']) . '</h5>
                    <p class="text-muted small">' . $h($achievement['description'] ?? '') . '</p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-mortarboard me-1"></i>
                        ' . $h($achievement['path_name'] ?? 'Общее достижение') . '
                    </p>
                </div>
                <div class="card-footer bg-white text-center">
                    <small class="text-muted">
                        <i class="bi bi-calendar-check me-1"></i>
                        Получено: ' . $h($earnedDate) . '
                    </small>
                </div>
            </div>
        </div>';
    }

    $html[] = '</div>';
}

// Доступные достижения (которые можно получить)
$html[] = '<hr class="my-5">';
$html[] = '<h3 class="mb-4"><i class="bi bi-stars me-2"></i>Доступные достижения</h3>';

// Получаем все достижения из траекторий, на которые записан пользователь
$sqlAvailable = "
    SELECT DISTINCT
        a.id,
        a.name,
        a.description,
        a.badge_icon,
        a.condition_type,
        a.condition_value,
        lp.name as path_name,
        lp.id as path_id
    FROM `{$prefix}test_learning_path_achievements` a
    JOIN `{$prefix}test_learning_paths` lp ON lp.id = a.path_id
    JOIN `{$prefix}test_learning_path_enrollments` e ON e.path_id = lp.id
    WHERE e.user_id = ? AND e.is_active = 1
    AND a.id NOT IN (
        SELECT achievement_id FROM `{$prefix}test_learning_path_user_achievements` WHERE user_id = ?
    )
    ORDER BY lp.name, a.id
";

$stmtAvailable = $modx->prepare($sqlAvailable);
if ($stmtAvailable) {
    $stmtAvailable->execute([$userId, $userId]);
    $availableAchievements = $stmtAvailable->fetchAll(PDO::FETCH_ASSOC);
} else {
    $availableAchievements = [];
}

if (empty($availableAchievements)) {
    $html[] = '<div class="ts-alert ts-alert-info">
        <i class="bi bi-info-circle me-2"></i>
        Нет доступных достижений для получения. Запишитесь на новые траектории!
    </div>';
} else {
    $html[] = '<div class="row g-4">';

    foreach ($availableAchievements as $achievement) {
        $icon = $achievement['badge_icon'] ?? 'bi-star';
        $conditionText = '';
        switch ($achievement['condition_type']) {
            case 'complete_path':
                $conditionText = 'Завершить траекторию';
                break;
            case 'score_threshold':
                $conditionText = 'Набрать ' . (int)$achievement['condition_value'] . '% баллов';
                break;
            case 'perfect_score':
                $conditionText = 'Набрать 100% баллов';
                break;
            case 'time_limit':
                $conditionText = 'Завершить за ' . (int)$achievement['condition_value'] . ' дней';
                break;
            default:
                $conditionText = 'Выполнить условие';
        }

        $html[] = '
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-secondary opacity-75">
                <div class="card-body text-center">
                    <div class="achievement-badge mb-3">
                        <i class="bi ' . $h($icon) . ' text-secondary" style="font-size: 3rem;"></i>
                    </div>
                    <h5 class="card-title text-muted">' . $h($achievement['name']) . '</h5>
                    <p class="text-muted small">' . $h($achievement['description'] ?? '') . '</p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-mortarboard me-1"></i>
                        ' . $h($achievement['path_name']) . '
                    </p>
                </div>
                <div class="card-footer bg-light text-center">
                    <small class="text-muted">
                        <i class="bi bi-unlock me-1"></i>
                        ' . $h($conditionText) . '
                    </small>
                </div>
            </div>
        </div>';
    }

    $html[] = '</div>';
}

$html[] = '</div>';

return implode('', $html);
