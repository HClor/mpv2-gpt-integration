<?php
/**
 * Сниппет: usersStats - Статистика пользователей для админов
 * Вызывается из: MODX ресурсов (админ-панель статистики)
 * Назначение: Сводная статистика по всем пользователям системы
 *
 * Показывает статистику для админов и экспертов:
 * - Общая сводка по пользователям
 * - Детальная информация по конкретному пользователю (если передан user_id)
 * - Рейтинги и достижения
 *
 * Параметры:
 * - user_id (int) - ID пользователя для детальной статистики
 *
 * @package TestSystem
 * @version 1.0
 */

if (!$modx instanceof modX) return 'MODX context required';

// Подключаем сервисы
require_once MODX_CORE_PATH . 'components/testsystem/services/CategoryPermissionService.php';

// Проверка авторизации
if (!$modx->user || !$modx->user->hasSessionContext('web')) {
    return '<div class="ts-alert ts-alert-warning">Войдите для просмотра статистики</div>';
}

$currentUserId = (int)$modx->user->get('id');
$isAdmin = CategoryPermissionService::isGlobalAdmin($modx, $currentUserId);
$isExpert = CategoryPermissionService::isGlobalExpert($modx, $currentUserId);

if (!$isAdmin && !$isExpert) {
    return '<div class="ts-alert ts-alert-danger">Доступ запрещён. Требуются права эксперта или администратора.</div>';
}

$h = function($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$prefix = $modx->getOption('table_prefix');
$viewUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$pageUrl = $modx->makeUrl($modx->resource->get('id'), '', '', 'abs');

$T_sessions = $prefix . 'test_sessions';
$T_users = $prefix . 'users';
$T_attrs = $prefix . 'user_attributes';
$T_enrollments = $prefix . 'test_learning_path_enrollments';
$T_progress = $prefix . 'test_learning_path_progress';
$T_paths = $prefix . 'test_learning_paths';

$out = [];

if ($viewUserId) {
    // Детальная статистика конкретного пользователя
    $stmt = $modx->prepare("
        SELECT u.id, u.username, ua.fullname, ua.email, u.createdon as registered_at
        FROM {$T_users} u
        LEFT JOIN {$T_attrs} ua ON ua.internalKey = u.id
        WHERE u.id = ?
    ");
    $stmt->execute([$viewUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return '<div class="ts-alert ts-alert-warning">Пользователь не найден</div>';
    }

    // Статистика тестов
    $stmt = $modx->prepare("
        SELECT
            COUNT(*) as total_tests,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tests,
            SUM(CASE WHEN status = 'completed' AND score >= 70 THEN 1 ELSE 0 END) as passed_tests,
            AVG(CASE WHEN status = 'completed' THEN score ELSE NULL END) as avg_score,
            MAX(CASE WHEN status = 'completed' THEN score ELSE NULL END) as best_score,
            SUM(CASE WHEN status = 'completed' THEN score ELSE 0 END) as total_score
        FROM {$T_sessions}
        WHERE user_id = ?
    ");
    $stmt->execute([$viewUserId]);
    $testStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Статистика траекторий
    $stmt = $modx->prepare("
        SELECT
            COUNT(*) as total_paths,
            SUM(CASE WHEN lpp.status = 'completed' THEN 1 ELSE 0 END) as completed_paths,
            SUM(CASE WHEN lpp.status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_paths,
            AVG(lpp.completion_pct) as avg_progress
        FROM {$T_enrollments} e
        LEFT JOIN {$T_progress} lpp ON lpp.enrollment_id = e.id
        WHERE e.user_id = ? AND e.is_active = 1
    ");
    $stmt->execute([$viewUserId]);
    $pathStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // История последних тестов
    $stmt = $modx->prepare("
        SELECT s.id, s.test_id, s.score, s.status, s.started_at, t.title as test_name
        FROM {$T_sessions} s
        LEFT JOIN {$prefix}test_tests t ON t.id = s.test_id
        WHERE s.user_id = ?
        ORDER BY s.started_at DESC
        LIMIT 10
    ");
    $stmt->execute([$viewUserId]);
    $recentTests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Траектории пользователя
    $stmt = $modx->prepare("
        SELECT lp.id, lp.name, e.enrolled_at, lpp.status, lpp.completion_pct, lpp.last_activity_at
        FROM {$T_enrollments} e
        JOIN {$T_paths} lp ON lp.id = e.path_id
        LEFT JOIN {$T_progress} lpp ON lpp.enrollment_id = e.id
        WHERE e.user_id = ? AND e.is_active = 1
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$viewUserId]);
    $userPaths = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out[] = '
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="'.$h($pageUrl).'">Статистика пользователей</a></li>
        <li class="breadcrumb-item active">'
        .$h($user['fullname'] ?: $user['username'])
        .($user['fullname'] ? ' <span class="text-muted fw-normal small">@'.$h($user['username']).'</span>' : '')
        .'</li>
      </ol>
    </nav>

    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-person-circle me-2"></i>'.$h($user['fullname'] ?: $user['username']).'</h5>
        <small class="text-muted">
          @'.$h($user['username']).' |
          '.$h($user['email']).' |
          Регистрация: '.date('d.m.Y', strtotime($user['registered_at'])).'
        </small>
      </div>
      <div class="card-body">
        <div class="row text-center g-3">
          <div class="col-md-2 col-4">
            <div class="fw-bold fs-4">'.(int)$testStats['total_tests'].'</div>
            <div class="text-muted small">Всего тестов</div>
          </div>
          <div class="col-md-2 col-4">
            <div class="fw-bold fs-4 text-success">'.(int)$testStats['passed_tests'].'</div>
            <div class="text-muted small">Успешно</div>
          </div>
          <div class="col-md-2 col-4">
            <div class="fw-bold fs-4 text-primary">'.round($testStats['avg_score'] ?? 0).'%</div>
            <div class="text-muted small">Средний балл</div>
          </div>
          <div class="col-md-2 col-4">
            <div class="fw-bold fs-4 text-info">'.(int)$pathStats['total_paths'].'</div>
            <div class="text-muted small">Траекторий</div>
          </div>
          <div class="col-md-2 col-4">
            <div class="fw-bold fs-4 text-success">'.(int)$pathStats['completed_paths'].'</div>
            <div class="text-muted small">Завершено</div>
          </div>
          <div class="col-md-2 col-4">
            <div class="fw-bold fs-4 text-warning">'.(int)$testStats['total_score'].'</div>
            <div class="text-muted small">Всего баллов</div>
          </div>
        </div>
      </div>
    </div>';

    // История тестов
    if (!empty($recentTests)) {
        $out[] = '
        <div class="card mb-4">
          <div class="card-header"><h6 class="mb-0">Последние тесты</h6></div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="ts-leaderboard-table">
                <thead>
                  <tr>
                    <th>Тест</th>
                    <th>Дата</th>
                    <th>Статус</th>
                    <th class="ts-leaderboard-col-number">Результат</th>
                  </tr>
                </thead>
                <tbody>';

        foreach ($recentTests as $test) {
            $statusClass = $test['status'] === 'completed' ? ($test['score'] >= 70 ? 'success' : 'warning') : 'secondary';
            $statusLabel = $test['status'] === 'completed' ? ($test['score'] >= 70 ? 'Сдан' : 'Не сдан') : 'В процессе';

            $out[] = '
                <tr>
                  <td>'.$h($test['test_name'] ?: 'Тест #'.$test['test_id']).'</td>
                  <td class="small">'.date('d.m.Y H:i', strtotime($test['started_at'])).'</td>
                  <td><span class="badge bg-'.$statusClass.'">'.$statusLabel.'</span></td>
                  <td class="ts-leaderboard-col-number"><strong>'.($test['status'] === 'completed' ? (int)$test['score'].'%' : '-').'</strong></td>
                </tr>';
        }

        $out[] = '</tbody></table></div></div></div>';
    }

    // Траектории
    if (!empty($userPaths)) {
        $out[] = '
        <div class="card">
          <div class="card-header"><h6 class="mb-0">Траектории обучения</h6></div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="ts-leaderboard-table">
                <thead>
                  <tr>
                    <th>Траектория</th>
                    <th>Записан</th>
                    <th>Статус</th>
                    <th class="ts-leaderboard-col-number">Прогресс</th>
                    <th>Активность</th>
                  </tr>
                </thead>
                <tbody>';

        foreach ($userPaths as $path) {
            $pct = (int)$path['completion_pct'];
            $statusClass = $path['status'] === 'completed' ? 'success' :
                          ($path['status'] === 'in_progress' ? 'primary' : 'secondary');
            $statusLabel = $path['status'] === 'completed' ? 'Завершено' :
                          ($path['status'] === 'in_progress' ? 'В процессе' : 'Не начато');

            $out[] = '
                <tr>
                  <td><strong>'.$h($path['name']).'</strong></td>
                  <td class="small">'.date('d.m.Y', strtotime($path['enrolled_at'])).'</td>
                  <td><span class="badge bg-'.$statusClass.'">'.$statusLabel.'</span></td>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="progress flex-grow-1 me-2" style="height: 8px;">
                        <div class="progress-bar bg-'.$statusClass.'" style="width: '.$pct.'%"></div>
                      </div>
                      <span class="small">'.$pct.'%</span>
                    </div>
                  </td>
                  <td class="small text-muted">'.($path['last_activity_at'] ? date('d.m.Y H:i', strtotime($path['last_activity_at'])) : '-').'</td>
                </tr>';
        }

        $out[] = '</tbody></table></div></div></div>';
    }

} else {
    // Общая статистика всех пользователей

    // Сводка
    $stmt = $modx->prepare("
        SELECT
            (SELECT COUNT(*) FROM {$T_users} WHERE active = 1) as total_users,
            (SELECT COUNT(DISTINCT user_id) FROM {$T_sessions}) as users_with_tests,
            (SELECT COUNT(DISTINCT user_id) FROM {$T_enrollments} WHERE is_active = 1) as users_with_paths,
            (SELECT COUNT(*) FROM {$T_sessions} WHERE status = 'completed') as total_tests,
            (SELECT COUNT(*) FROM {$T_sessions} WHERE status = 'completed' AND score >= 70) as passed_tests,
            (SELECT AVG(score) FROM {$T_sessions} WHERE status = 'completed') as avg_score
    ");
    $stmt->execute();
    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    $out[] = '
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">Общая статистика пользователей</h5></div>
      <div class="card-body">
        <div class="row text-center g-3">
          <div class="col-md col-4">
            <div class="fw-bold fs-4">'.(int)$summary['total_users'].'</div>
            <div class="text-muted small">Всего пользователей</div>
          </div>
          <div class="col-md col-4">
            <div class="fw-bold fs-4 text-primary">'.(int)$summary['users_with_tests'].'</div>
            <div class="text-muted small">Проходили тесты</div>
          </div>
          <div class="col-md col-4">
            <div class="fw-bold fs-4 text-info">'.(int)$summary['users_with_paths'].'</div>
            <div class="text-muted small">На траекториях</div>
          </div>
          <div class="col-md col-4">
            <div class="fw-bold fs-4">'.(int)$summary['total_tests'].'</div>
            <div class="text-muted small">Всего тестов</div>
          </div>
          <div class="col-md col-4">
            <div class="fw-bold fs-4 text-success">'.(int)$summary['passed_tests'].'</div>
            <div class="text-muted small">Успешных тестов</div>
          </div>
          <div class="col-md col-4">
            <div class="fw-bold fs-4 text-warning">'.round($summary['avg_score'] ?? 0).'%</div>
            <div class="text-muted small">Средний балл</div>
          </div>
        </div>
      </div>
    </div>';

    // Топ пользователей по баллам
    $stmt = $modx->prepare("
        SELECT
            u.id, u.username, ua.fullname,
            COUNT(DISTINCT s.id) as tests_count,
            SUM(CASE WHEN s.status = 'completed' THEN s.score ELSE 0 END) as total_score,
            AVG(CASE WHEN s.status = 'completed' THEN s.score ELSE NULL END) as avg_score,
            MAX(s.started_at) as last_activity
        FROM {$T_users} u
        LEFT JOIN {$T_attrs} ua ON ua.internalKey = u.id
        LEFT JOIN {$T_sessions} s ON s.user_id = u.id
        WHERE u.active = 1
        GROUP BY u.id, u.username, ua.fullname
        HAVING tests_count > 0
        ORDER BY total_score DESC
        LIMIT 20
    ");
    $stmt->execute();
    $topUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($topUsers)) {
        $out[] = '
        <div class="card mb-4">
          <div class="card-header"><h6 class="mb-0">Топ пользователей по баллам</h6></div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="ts-leaderboard-table">
                <thead>
                  <tr>
                    <th style="width: 50px; padding-left: 1rem;">#</th>
                    <th>Пользователь</th>
                    <th class="ts-leaderboard-col-number">Тестов</th>
                    <th class="ts-leaderboard-col-number">Ср. балл</th>
                    <th class="ts-leaderboard-col-number">Всего баллов</th>
                    <th>Активность</th>
                    <th style="padding-right: 1rem;"></th>
                  </tr>
                </thead>
                <tbody>';

        $rank = 0;
        foreach ($topUsers as $user) {
            $rank++;
            $rankBadge = $rank <= 3 ? '<span class="badge bg-'.($rank == 1 ? 'warning' : ($rank == 2 ? 'secondary' : 'danger')).'">'.$rank.'</span>' : $rank;

            $out[] = '
                <tr>
                  <td class="ts-leaderboard-col-number" style="padding-left: 1rem;">'.$rankBadge.'</td>
                  <td>
                    <strong>'.$h($user['fullname'] ?: $user['username']).'</strong>
                    '.($user['fullname'] ? '<br><small class="text-muted">@'.$h($user['username']).'</small>' : '').'
                  </td>
                  <td class="ts-leaderboard-col-number">'.(int)$user['tests_count'].'</td>
                  <td class="ts-leaderboard-col-number">'.round($user['avg_score'] ?? 0).'%</td>
                  <td class="ts-leaderboard-col-number"><strong>'.(int)$user['total_score'].'</strong></td>
                  <td class="small text-muted">'.($user['last_activity'] ? date('d.m.Y', strtotime($user['last_activity'])) : '-').'</td>
                  <td style="padding-right: 1rem;">
                    <a href="'.$h($pageUrl).'?user_id='.(int)$user['id'].'" class="ts-btn ts-btn-sm ts-btn-ghost" title="Подробнее">
                      <i class="bi bi-person"></i>
                    </a>
                  </td>
                </tr>';
        }

        $out[] = '</tbody></table></div></div></div>';
    }

    // Последняя активность
    $stmt = $modx->prepare("
        SELECT
            s.id, s.user_id, s.test_id, s.score, s.status, s.started_at,
            u.username, ua.fullname, t.title as test_name
        FROM {$T_sessions} s
        JOIN {$T_users} u ON u.id = s.user_id
        LEFT JOIN {$T_attrs} ua ON ua.internalKey = u.id
        LEFT JOIN {$prefix}test_tests t ON t.id = s.test_id
        ORDER BY s.started_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($recentActivity)) {
        $out[] = '
        <div class="card">
          <div class="card-header"><h6 class="mb-0">Последняя активность</h6></div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="ts-leaderboard-table">
                <thead>
                  <tr>
                    <th style="padding-left: 1rem;">Пользователь</th>
                    <th>Тест</th>
                    <th>Дата</th>
                    <th>Статус</th>
                    <th class="ts-leaderboard-col-number" style="padding-right: 1rem;">Результат</th>
                  </tr>
                </thead>
                <tbody>';

        foreach ($recentActivity as $activity) {
            $statusClass = $activity['status'] === 'completed' ? ($activity['score'] >= 70 ? 'success' : 'warning') : 'secondary';
            $statusLabel = $activity['status'] === 'completed' ? ($activity['score'] >= 70 ? 'Сдан' : 'Не сдан') : 'В процессе';

            $out[] = '
                <tr>
                  <td style="padding-left: 1rem;">
                    <a href="'.$h($pageUrl).'?user_id='.(int)$activity['user_id'].'">'
                    .$h($activity['fullname'] ?: $activity['username']).'</a>
                  </td>
                  <td>'.$h($activity['test_name'] ?: 'Тест #'.$activity['test_id']).'</td>
                  <td class="small">'.date('d.m.Y H:i', strtotime($activity['started_at'])).'</td>
                  <td><span class="badge bg-'.$statusClass.'">'.$statusLabel.'</span></td>
                  <td class="ts-leaderboard-col-number" style="padding-right: 1rem;"><strong>'.($activity['status'] === 'completed' ? (int)$activity['score'].'%' : '-').'</strong></td>
                </tr>';
        }

        $out[] = '</tbody></table></div></div></div>';
    }
}

return implode('', $out);