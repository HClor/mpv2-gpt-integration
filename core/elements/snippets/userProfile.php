<?php
/**
 * Сниппет: userProfile - Профиль пользователя
 * Вызывается из: MODX ресурсов (страница профиля)
 * Назначение: Отображает и редактирует профиль пользователя (обзор, траектории, настройки)
 *
 * @package TestSystem
 * @version 2.0
 */
if (!$modx instanceof modX) return 'MODX context required';

// Подключаем сервис траекторий
require_once MODX_CORE_PATH . 'components/testsystem/services/LearningPathService.php';

// 0) Auth
if (!$modx->user || !$modx->user->hasSessionContext('web')) {
    $authUrl = $modx->makeUrl(24, '', '', 'abs');
    return '<div class="ts-alert ts-alert-warning">Войдите, чтобы просмотреть профиль.<br><a class="ts-btn ts-btn-primary mt-2" href="'.htmlspecialchars($authUrl,ENT_QUOTES,'UTF-8').'">Войти</a></div>';
}

// 1) Init
$prefix = $modx->getOption('table_prefix');
$userId = (int)$modx->user->get('id');
$username = (string)$modx->user->get('username');
$errors = [];
$success = '';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];

$h = function($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$getp = function($key){ return trim((string)($_POST[$key] ?? '')); };

// 2) Update profile (fullname/email)
if (!empty($_POST) && isset($_POST['update_profile'])) {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $errors[] = 'Неверный CSRF-токен, обновите страницу.';
    } else {
        $fullname = $getp('fullname');
        $email    = $getp('email');

        if ($email === '') {
            $errors[] = 'Email обязателен';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Неверный формат email';
        } else {
            $sql = "SELECT COUNT(*) FROM `{$prefix}user_attributes` WHERE email = ? AND internalKey != ?";
            $stmt = $modx->prepare($sql);
            $stmt->execute([$email, $userId]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'Email уже используется другим пользователем';
            } else {
                $profile = $modx->user->getOne('Profile');
                if ($profile) {
                    $profile->set('email', $email);
                    $profile->set('fullname', $fullname);
                    if ($profile->save()) $success = 'Данные профиля обновлены';
                    else $errors[] = 'Не удалось сохранить профиль';
                } else {
                    $errors[] = 'Профиль пользователя не найден';
                }
            }
        }
    }
}

// 3) Change password
if (!empty($_POST) && isset($_POST['change_password'])) {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        $errors[] = 'Неверный CSRF-токен, обновите страницу.';
    } else {
        $pass_old = $getp('password_old');
        $pass_new = $getp('password_new');
        $pass_new2= $getp('password_new2');

        if ($pass_new === '' || $pass_new2 === '') {
            $errors[] = 'Укажите новый пароль (два раза)';
        } elseif ($pass_new !== $pass_new2) {
            $errors[] = 'Новый пароль и подтверждение не совпадают';
        } else {
            $sql = "SELECT password FROM `{$prefix}users` WHERE id = ?";
            $stmt = $modx->prepare($sql); $stmt->execute([$userId]);
            $hash = (string)$stmt->fetchColumn();
            if ($hash === '' || !password_verify($pass_old, $hash)) {
                $errors[] = 'Старый пароль неверен';
            } else {
                $newHash = password_hash($pass_new, PASSWORD_DEFAULT);
                $sql = "UPDATE `{$prefix}users` SET password=? WHERE id=?";
                $st2 = $modx->prepare($sql);
                if ($st2->execute([$newHash, $userId])) $success = 'Пароль обновлён';
                else $errors[] = 'Не удалось обновить пароль';
            }
        }
    }
}

// 4) Profile data
$profile = $modx->user->getOne('Profile');
$fullname = $profile ? $profile->get('fullname') : '';
$email    = $profile ? $profile->get('email') : '';

// 5) Stats & History (Обзор) - Тесты
$T_sessions = $prefix.'test_sessions';
$T_tests    = $prefix.'test_tests';
$S          = $prefix.'site_content';

$st = $modx->prepare("SELECT COUNT(*) FROM `{$T_sessions}` WHERE user_id = ?");
$st->execute([$userId]); $attemptsTotal = (int)$st->fetchColumn();

$st = $modx->prepare("SELECT AVG(score) FROM `{$T_sessions}` WHERE user_id = ?");
$st->execute([$userId]); $avgScore = (float)$st->fetchColumn(); $avgScore = $avgScore ? round($avgScore,1) : 0;

$st = $modx->prepare("SELECT
    SUM(CASE WHEN status = 'completed' AND score >= 70 THEN 1 ELSE 0 END),
    SUM(CASE WHEN status = 'completed' AND score < 70 THEN 1 ELSE 0 END)
    FROM `{$T_sessions}` WHERE user_id = ?");
$st->execute([$userId]); [$passed,$failed] = array_map('intval', $st->fetch(PDO::FETCH_NUM) ?: [0,0]);

$st = $modx->prepare("SELECT COUNT(*)
  FROM `{$T_tests}` t
  JOIN `{$S}` sc ON sc.alias = CONCAT('test-', t.id) AND sc.published=1 AND sc.deleted=0");
$st->execute(); $testsAvailable=(int)$st->fetchColumn();

$sql = "SELECT s.id, s.test_id, s.score, s.status, s.started_at,
               CASE WHEN s.status = 'completed' AND s.score >= 70 THEN 1 ELSE 0 END AS passed,
               t.title, sc.id AS rid
        FROM `{$T_sessions}` s
        JOIN `{$T_tests}` t ON t.id=s.test_id
        LEFT JOIN `{$S}` sc ON sc.alias = CONCAT('test-', s.test_id) AND sc.published=1 AND sc.deleted=0
        WHERE s.user_id = ? AND s.status = 'completed'
        ORDER BY s.started_at DESC
        LIMIT 10";
$st=$modx->prepare($sql); $st->execute([$userId]);
$lastAttempts = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

// 6) Статистика траекторий обучения
$learningStats = LearningPathService::getUserLearningStats($modx, $userId);
$pathsUrl = $modx->makeUrl(125, '', '', 'abs'); // ID страницы траекторий

// Уровни сложности
$difficultyLabels = [
    'beginner' => 'Начальный',
    'intermediate' => 'Средний',
    'advanced' => 'Продвинутый',
    'expert' => 'Эксперт'
];

// 7) UI (tabs)
$out = [];
if ($success) $out[] = '<div class="ts-alert ts-alert-success">'.$h($success).'</div>';
if ($errors) {
  $out[] = '<div class="ts-alert ts-alert-danger"><ul class="mb-0">';
  foreach ($errors as $e) $out[] = '<li>'.$h($e).'</li>';
  $out[] = '</ul></div>';
}

$out[] = '
<ul class="nav nav-tabs" id="profileTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="tab-overview" data-bs-toggle="tab" data-bs-target="#pane-overview" type="button" role="tab">Обзор</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-paths" data-bs-toggle="tab" data-bs-target="#pane-paths" type="button" role="tab">
      Траектории
      '.($learningStats['summary']['enrolled_paths'] > 0 ? '<span class="ts-badge ts-badge-primary ms-1">'.(int)$learningStats['summary']['enrolled_paths'].'</span>' : '').'
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="tab-settings" data-bs-toggle="tab" data-bs-target="#pane-settings" type="button" role="tab">Изменение данных</button>
  </li>
</ul>

<div class="tab-content pt-3" id="profileTabsContent">
  <!-- Обзор -->
  <div class="tab-pane fade show active" id="pane-overview" role="tabpanel" aria-labelledby="tab-overview">
    <div class="card mb-3"><div class="card-body">
      <h5 class="card-title">Пользователь</h5>
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label">Имя пользователя</label>
          <input class="form-control" value="'.$h($username).'" disabled>
          <div class="form-text">Имя пользователя не редактируется</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Имя (из профиля)</label>
          <input class="form-control" value="'.$h($fullname).'" disabled>
        </div>
        <div class="col-md-4">
          <label class="form-label">Email (из профиля)</label>
          <input class="form-control" value="'.$h($email).'" disabled>
        </div>
      </div>
    </div></div>

    <div class="card mb-3"><div class="card-body">
      <h5 class="card-title">Моя статистика (Тесты)</h5>
      <div class="row text-center">
        <div class="col-6 col-md-3"><div class="fw-bold fs-4">'.$h($testsAvailable).'</div><div class="text-muted">Доступно тестов</div></div>
        <div class="col-6 col-md-3"><div class="fw-bold fs-4">'.$h($attemptsTotal).'</div><div class="text-muted">Всего попыток</div></div>
        <div class="col-6 col-md-3"><div class="fw-bold fs-4">'.$h($avgScore).'%</div><div class="text-muted">Средний балл</div></div>
        <div class="col-6 col-md-3"><div class="fw-bold fs-4">'.$h($passed).'/'.$h($failed).'</div><div class="text-muted">Пройдено / Нет</div></div>
      </div>
    </div></div>

    <div class="card"><div class="card-body">
      <h5 class="card-title">История тестов (последние 10)</h5>';
if (!$lastAttempts) {
  $out[] .= '<div class="text-muted">Пока нет попыток</div>';
} else {
  $out[] .= '<div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th>Тест</th><th class="text-end">Балл</th><th class="text-center">Статус</th><th class="text-end">Дата</th></tr></thead><tbody>';
  foreach($lastAttempts as $a){
    $title = $h($a['title'] ?? ('Тест #'.$a['test_id']));
    $score = (int)$a['score'].'%';
    $status= ((int)$a['passed']===1) ? '<span class="ts-badge ts-badge-success">пройден</span>' : '<span class="ts-badge ts-badge-neutral">нет</span>';
    $date  = $h($a['started_at'] ?? '');
    $link  = !empty($a['rid']) ? $h($modx->makeUrl((int)$a['rid'],'','', 'abs')) : '#';
    $cellTitle = $link!=='#' ? '<a href="'.$link.'">'.$title.'</a>' : $title;
    $out[] .= '<tr><td>'.$cellTitle.'</td><td class="text-end">'.$score.'</td><td class="text-center">'.$status.'</td><td class="text-end">'.$date.'</td></tr>';
  }
  $out[] .= '</tbody></table></div>';
}
$out[] .= '</div></div>
  </div>

  <!-- Траектории обучения -->
  <div class="tab-pane fade" id="pane-paths" role="tabpanel" aria-labelledby="tab-paths">
    <div class="card mb-3"><div class="card-body">
      <h5 class="card-title">Статистика обучения</h5>
      <div class="row text-center">
        <div class="col-6 col-md-3">
          <div class="fw-bold fs-4 text-primary">'.(int)$learningStats['summary']['enrolled_paths'].'</div>
          <div class="text-muted">Записей на траектории</div>
        </div>
        <div class="col-6 col-md-3">
          <div class="fw-bold fs-4 text-success">'.(int)$learningStats['summary']['completed_paths'].'</div>
          <div class="text-muted">Завершено</div>
        </div>
        <div class="col-6 col-md-3">
          <div class="fw-bold fs-4 text-info">'.(int)$learningStats['summary']['completed_steps'].'</div>
          <div class="text-muted">Шагов пройдено</div>
        </div>
        <div class="col-6 col-md-3">
          <div class="fw-bold fs-4 text-warning">'.(int)$learningStats['summary']['certificates_earned'].'</div>
          <div class="text-muted">Сертификатов</div>
        </div>
      </div>
    </div></div>';

if (empty($learningStats['paths'])) {
    $out[] .= '
    <div class="ts-alert ts-alert-info">
      <i class="bi bi-info-circle me-2"></i>
      Вы ещё не записаны ни на одну траекторию обучения.
      <a href="'.$h($pathsUrl).'" class="alert-link">Посмотреть доступные траектории</a>
    </div>';
} else {
    $out[] .= '<div class="row g-3">';
    foreach ($learningStats['paths'] as $path) {
        $pct = (int)$path['completion_pct'];
        $statusClass = $path['status'] === 'completed' ? 'success' : ($path['status'] === 'in_progress' ? 'primary' : 'secondary');
        $statusLabel = $path['status'] === 'completed' ? 'Завершено' : ($path['status'] === 'in_progress' ? 'В процессе' : 'Не начато');
        $tsBadgeClass = $path['status'] === 'completed' ? 'ts-badge-success' : ($path['status'] === 'in_progress' ? 'ts-badge-primary' : 'ts-badge-neutral');
        $tsBtnVariant = $path['status'] === 'completed' ? 'ts-btn-ghost-success' : ($path['status'] === 'in_progress' ? 'ts-btn-ghost' : 'ts-btn-secondary');
        $diffLabel = $difficultyLabels[$path['difficulty_level']] ?? $path['difficulty_level'];
        $pathUrl = $pathsUrl . '?mode=view&id=' . (int)$path['id'];

        $out[] .= '
        <div class="col-md-6">
          <div class="card h-100 border-'.$statusClass.'">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h6 class="card-title mb-0">
                  <a href="'.$h($pathUrl).'" class="text-decoration-none">'.$h($path['name']).'</a>
                </h6>
                <span class="ts-badge '.$tsBadgeClass.'">'.$statusLabel.'</span>
              </div>
              <p class="text-muted small mb-2">
                <i class="bi bi-bar-chart me-1"></i>'.$diffLabel.'
                '.($path['estimated_hours'] ? '<span class="ms-2"><i class="bi bi-clock me-1"></i>'.(int)$path['estimated_hours'].' ч.</span>' : '').'
              </p>
              <div class="progress mb-2" style="height: 8px;">
                <div class="progress-bar bg-'.$statusClass.'" role="progressbar" style="width: '.$pct.'%"></div>
              </div>
              <div class="d-flex justify-content-between small text-muted">
                <span>'.(int)$path['completed_steps'].' / '.(int)$path['total_steps'].' шагов</span>
                <span>'.$pct.'%</span>
              </div>
              '.($path['certificate_issued'] ? '<div class="mt-2"><span class="ts-badge ts-badge-warning"><i class="bi bi-award me-1"></i>Сертификат получен</span></div>' : '').'
            </div>
            <div class="card-footer bg-transparent">
              <a href="'.$h($pathUrl).'" class="ts-btn ts-btn-sm '.$tsBtnVariant.'">
                '.($path['status'] === 'completed' ? '<i class="bi bi-eye me-1"></i>Просмотреть' : '<i class="bi bi-play-fill me-1"></i>Продолжить').'
              </a>
            </div>
          </div>
        </div>';
    }
    $out[] .= '</div>';
}

$out[] .= '
    <div class="mt-3">
      <a href="'.$h($pathsUrl).'" class="ts-btn ts-btn-primary">
        <i class="bi bi-mortarboard me-1"></i>Все траектории обучения
      </a>
    </div>
  </div>

  <!-- Изменение данных -->
  <div class="tab-pane fade" id="pane-settings" role="tabpanel" aria-labelledby="tab-settings">
    <div class="card mb-3"><div class="card-body">
      <h5 class="card-title">Профиль</h5>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="'.$h($csrf).'">
        <div class="mb-3">
          <label class="form-label">Имя</label>
          <input class="form-control" name="fullname" value="'.$h($fullname).'">
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input class="form-control" name="email" value="'.$h($email).'" required>
        </div>
        <button class="ts-btn ts-btn-primary" type="submit" name="update_profile" value="1">Сохранить</button>
      </form>
    </div></div>

    <div class="card"><div class="card-body">
      <h5 class="card-title">Смена пароля</h5>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="'.$h($csrf).'">
        <div class="mb-3">
          <label class="form-label">Текущий пароль</label>
          <input type="password" class="form-control" name="password_old" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Новый пароль</label>
          <input type="password" class="form-control" name="password_new" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Повторите новый пароль</label>
          <input type="password" class="form-control" name="password_new2" required>
        </div>
        <button class="ts-btn ts-btn-warning" type="submit" name="change_password" value="1">Обновить пароль</button>
      </form>
    </div></div>
  </div>
</div>';

return implode('', $out);