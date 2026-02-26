<?php
/**
 * Сниппет: learningPathsStats - Статистика траекторий обучения
 * Вызывается из: MODX ресурсов (админ-панель статистики)
 * Назначение: Сводная статистика по траекториям для админов и экспертов
 *
 * Показывает статистику для админов и экспертов:
 * - Общая сводка по всем траекториям
 * - Статистика конкретной траектории (если передан path_id)
 * - Список студентов с прогрессом
 *
 * Параметры:
 * - path_id (int) - ID траектории для детальной статистики
 *
 * @package TestSystem
 * @version 1.0
 */

if (!$modx instanceof modX) return 'MODX context required';

// Подключаем сервисы
require_once MODX_CORE_PATH . 'components/testsystem/services/LearningPathService.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/CategoryPermissionService.php';

// Проверка авторизации
if (!$modx->user || !$modx->user->hasSessionContext('web')) {
    return '<div class="ts-alert ts-alert-warning">Войдите для просмотра статистики</div>';
}

$userId = (int)$modx->user->get('id');
$isAdmin = CategoryPermissionService::isGlobalAdmin($modx, $userId);
$isExpert = CategoryPermissionService::isGlobalExpert($modx, $userId);

if (!$isAdmin && !$isExpert) {
    return '<div class="ts-alert ts-alert-danger">Доступ запрещён. Требуются права эксперта или администратора.</div>';
}

$h = function($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$pathId = isset($_GET['path_id']) ? (int)$_GET['path_id'] : null;
$pathsUrl = $modx->makeUrl($modx->resource->get('id'), '', '', 'abs');

$difficultyLabels = [
    'beginner' => 'Начальный',
    'intermediate' => 'Средний',
    'advanced' => 'Продвинутый',
    'expert' => 'Эксперт'
];

$out = [];

if ($pathId) {
    // Детальная статистика траектории
    $stats = LearningPathService::getPathStatistics($modx, $pathId);

    if (!$stats) {
        return '<div class="ts-alert ts-alert-warning">Траектория не найдена</div>';
    }

    $path = $stats['path'];
    $enroll = $stats['enrollments'];

    $out[] = '
    <nav aria-label="breadcrumb" class="mb-3">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="'.$h($pathsUrl).'">Статистика траекторий</a></li>
        <li class="breadcrumb-item active">'.$h($path['name']).'</li>
      </ol>
    </nav>

    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">'.$h($path['name']).'</h5>
        <small class="text-muted">
          Автор: '.$h($path['author_name']).' |
          Категория: '.$h($path['category_name'] ?: 'Без категории').' |
          Сложность: '.$h($difficultyLabels[$path['difficulty_level']] ?? $path['difficulty_level']).'
        </small>
      </div>
      <div class="card-body">
        <div class="row text-center g-3">
          <div class="col-md-2 col-4">
            <div class="fw-bold fs-4">'.(int)$enroll['total_enrollments'].'</div>
            <div class="text-muted small">Всего записей</div>
          </div>
          <div class="col-md-2 col-4">
            <div class="fw-bold fs-4 text-primary">'.(int)$enroll['active_enrollments'].'</div>
            <div class="text-muted small">Активных</div>
          </div>
          <div class="col-md-2 col-4">
            <div class="fw-bold fs-4 text-success">'.(int)$enroll['completed_count'].'</div>
            <div class="text-muted small">Завершили</div>
          </div>
          <div class="col-md-2 col-4">
            <div class="fw-bold fs-4 text-info">'.(int)$enroll['in_progress_count'].'</div>
            <div class="text-muted small">В процессе</div>
          </div>
          <div class="col-md-2 col-4">
            <div class="fw-bold fs-4">'.round($enroll['avg_completion'] ?? 0).'%</div>
            <div class="text-muted small">Средний прогресс</div>
          </div>
          <div class="col-md-2 col-4">
            <div class="fw-bold fs-4 text-warning">'.(int)$enroll['certificates_issued'].'</div>
            <div class="text-muted small">Сертификатов</div>
          </div>
        </div>
      </div>
    </div>';

    // Статистика по шагам
    if (!empty($stats['steps'])) {
        $out[] = '
        <div class="card mb-4">
          <div class="card-header"><h6 class="mb-0">Статистика по шагам</h6></div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>#</th>
                    <th>Название</th>
                    <th>Тип</th>
                    <th class="text-center">Обязательный</th>
                    <th class="text-end">Попыток</th>
                    <th class="text-end">Завершили</th>
                    <th class="text-end">% завершения</th>
                    <th class="text-end">Ср. балл</th>
                  </tr>
                </thead>
                <tbody>';

        $stepTypes = [
            'material' => 'Материал',
            'test' => 'Тест',
            'quiz' => 'Квиз',
            'assignment' => 'Задание'
        ];

        foreach ($stats['steps'] as $step) {
            $completionRate = $step['completion_rate'] ?? 0;
            $barClass = $completionRate >= 70 ? 'success' : ($completionRate >= 40 ? 'warning' : 'danger');

            $out[] .= '
                <tr>
                  <td>'.(int)$step['step_number'].'</td>
                  <td>'.$h($step['name']).'</td>
                  <td><span class="ts-badge ts-badge-neutral">'.($stepTypes[$step['step_type']] ?? $step['step_type']).'</span></td>
                  <td class="text-center">'.($step['is_required'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-dash text-muted"></i>').'</td>
                  <td class="text-end">'.(int)$step['total_attempts'].'</td>
                  <td class="text-end">'.(int)$step['completed_count'].'</td>
                  <td class="text-end">
                    <div class="d-flex align-items-center justify-content-end">
                      <div class="progress me-2" style="width: 60px; height: 6px;">
                        <div class="progress-bar bg-'.$barClass.'" style="width: '.$completionRate.'%"></div>
                      </div>
                      '.$completionRate.'%
                    </div>
                  </td>
                  <td class="text-end">'.($step['avg_score'] ? round($step['avg_score']).'%' : '-').'</td>
                </tr>';
        }

        $out[] .= '</tbody></table></div></div></div>';
    }

    // Список студентов
    if (!empty($stats['students'])) {
        $out[] = '
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">Студенты ('.(count($stats['students'])).')</h6>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Пользователь</th>
                    <th>Записан</th>
                    <th>Статус</th>
                    <th class="text-center">Прогресс</th>
                    <th class="text-end">Шагов</th>
                    <th>Последняя активность</th>
                  </tr>
                </thead>
                <tbody>';

        foreach ($stats['students'] as $student) {
            $pct = (int)$student['completion_pct'];
            $statusClass = $student['status'] === 'completed' ? 'success' :
                          ($student['status'] === 'in_progress' ? 'primary' : 'secondary');
            $statusLabel = $student['status'] === 'completed' ? 'Завершено' :
                          ($student['status'] === 'in_progress' ? 'В процессе' : 'Не начато');

            $out[] .= '
                <tr>
                  <td>
                    <strong>'.$h($student['username']).'</strong>
                    '.($student['fullname'] ? '<br><small class="text-muted">'.$h($student['fullname']).'</small>' : '').'
                  </td>
                  <td class="small">'.($student['enrolled_at'] ? date('d.m.Y', strtotime($student['enrolled_at'])) : '-').'</td>
                  <td><span class="badge bg-'.$statusClass.'">'.$statusLabel.'</span></td>
                  <td>
                    <div class="d-flex align-items-center">
                      <div class="progress flex-grow-1 me-2" style="height: 8px;">
                        <div class="progress-bar bg-'.$statusClass.'" style="width: '.$pct.'%"></div>
                      </div>
                      <span class="small">'.$pct.'%</span>
                    </div>
                  </td>
                  <td class="text-end">'.(int)$student['completed_steps'].' / '.(int)$path['total_steps'].'</td>
                  <td class="small text-muted">'.($student['last_activity_at'] ? date('d.m.Y H:i', strtotime($student['last_activity_at'])) : '-').'</td>
                </tr>';
        }

        $out[] .= '</tbody></table></div></div></div>';
    }

} else {
    // Общая статистика всех траекторий
    $stats = LearningPathService::getAllPathsStatistics($modx);
    $summary = $stats['summary'];

    $out[] = '
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">Общая статистика траекторий обучения</h5></div>
      <div class="card-body">
        <div class="row text-center g-3">
          <div class="col-md col-4">
            <div class="fw-bold fs-4">'.(int)$summary['total_paths'].'</div>
            <div class="text-muted small">Всего траекторий</div>
          </div>
          <div class="col-md col-4">
            <div class="fw-bold fs-4 text-success">'.(int)$summary['published_paths'].'</div>
            <div class="text-muted small">Опубликовано</div>
          </div>
          <div class="col-md col-4">
            <div class="fw-bold fs-4 text-primary">'.(int)$summary['total_enrollments'].'</div>
            <div class="text-muted small">Записей</div>
          </div>
          <div class="col-md col-4">
            <div class="fw-bold fs-4 text-info">'.(int)$summary['unique_students'].'</div>
            <div class="text-muted small">Студентов</div>
          </div>
          <div class="col-md col-4">
            <div class="fw-bold fs-4 text-success">'.(int)$summary['total_completions'].'</div>
            <div class="text-muted small">Завершений</div>
          </div>
          <div class="col-md col-4">
            <div class="fw-bold fs-4 text-warning">'.(int)$summary['certificates_issued'].'</div>
            <div class="text-muted small">Сертификатов</div>
          </div>
        </div>
      </div>
    </div>';

    // Топ траекторий
    if (!empty($stats['top_paths'])) {
        $out[] = '
        <div class="card mb-4">
          <div class="card-header"><h6 class="mb-0">Топ траекторий по популярности</h6></div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Название</th>
                    <th>Статус</th>
                    <th>Сложность</th>
                    <th class="text-end">Записей</th>
                    <th class="text-end">Завершений</th>
                    <th class="text-end">% завершения</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>';

        foreach ($stats['top_paths'] as $path) {
            $statusClass = $path['status'] === 'published' ? 'success' : 'secondary';
            $statusLabel = $path['status'] === 'published' ? 'Опубликовано' : 'Черновик';
            $diffLabel = $difficultyLabels[$path['difficulty_level']] ?? $path['difficulty_level'];
            $completionRate = $path['completion_rate'] ?? 0;

            $out[] .= '
                <tr>
                  <td><strong>'.$h($path['name']).'</strong></td>
                  <td><span class="badge bg-'.$statusClass.'">'.$statusLabel.'</span></td>
                  <td>'.$diffLabel.'</td>
                  <td class="text-end">'.(int)$path['enrollments'].'</td>
                  <td class="text-end">'.(int)$path['completions'].'</td>
                  <td class="text-end">
                    <div class="d-flex align-items-center justify-content-end">
                      <div class="progress me-2" style="width: 60px; height: 6px;">
                        <div class="progress-bar" style="width: '.$completionRate.'%"></div>
                      </div>
                      '.$completionRate.'%
                    </div>
                  </td>
                  <td>
                    <a href="'.$h($pathsUrl).'?path_id='.(int)$path['id'].'" class="ts-btn ts-btn-sm ts-btn-ghost">
                      <i class="bi bi-bar-chart"></i>
                    </a>
                  </td>
                </tr>';
        }

        $out[] .= '</tbody></table></div></div></div>';
    }

    // Активность за 30 дней
    if (!empty($stats['activity'])) {
        $out[] = '
        <div class="card">
          <div class="card-header"><h6 class="mb-0">Активность за последние 30 дней</h6></div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Дата</th>
                    <th class="text-end">Новых записей</th>
                    <th class="text-end">Завершений</th>
                  </tr>
                </thead>
                <tbody>';

        foreach (array_slice($stats['activity'], 0, 14) as $day) {
            $out[] .= '
                <tr>
                  <td>'.date('d.m.Y', strtotime($day['date'])).'</td>
                  <td class="text-end"><span class="ts-badge ts-badge-primary">'.(int)$day['new_enrollments'].'</span></td>
                  <td class="text-end"><span class="ts-badge ts-badge-success">'.(int)$day['completions'].'</span></td>
                </tr>';
        }

        $out[] .= '</tbody></table></div></div></div>';
    }
}

return implode('', $out);