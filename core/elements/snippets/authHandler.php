<?php
/**
 * Сниппет: authHandler - Обработчик авторизации
 * Вызывается из: MODX ресурсов (страница входа/регистрации)
 * Назначение: Обрабатывает вход, регистрацию, выход пользователя
 *
 * @package TestSystem
 * @version 2.4
 */

require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];
$success = [];
$mode = $_GET['mode'] ?? ($_POST['mode'] ?? 'login');
$prefillResendEmail = '';

$flash = $_SESSION['auth_handler_flash'] ?? null;
if (is_array($flash)) {
    foreach (($flash['errors'] ?? []) as $msg) {
        $errors[] = $msg;
    }
    foreach (($flash['success'] ?? []) as $msg) {
        $success[] = $msg;
    }
    unset($_SESSION['auth_handler_flash']);
}

// ВЫХОД
if (isset($_POST['login_logout'])) {
    if (!CsrfProtection::validateRequest($_POST)) {
        $modx->log(modX::LOG_LEVEL_WARN, '[authHandler] Logout with invalid CSRF token, proceeding with web context logout');
    }

    if ($modx->user->hasSessionContext('web')) {
        $modx->user->removeSessionContext('web');
    }

    $modx->sendRedirect($modx->makeUrl($modx->getOption('site_start')));
    return;
}

// Если уже авторизован
if ($modx->user->hasSessionContext('web') && $modx->user->id > 0) {
    $testsUrl = $modx->makeUrl(35);

    $output = '<div class="alert alert-success">';
    $output .= '<h4>Вы авторизованы: ' . htmlspecialchars((string)$modx->user->username, ENT_QUOTES, 'UTF-8') . '</h4>';
    $output .= '<p><a href="' . $testsUrl . '" class="btn btn-primary">Перейти к тестам</a></p>';
    $output .= '<form method="post">';
    $output .= CsrfProtection::getTokenField();
    $output .= '<input type="hidden" name="login_logout" value="1">';
    $output .= '<button type="submit" class="btn btn-danger">Выйти</button>';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}

$ensurePendingEmailsTable = static function (modX $modx): bool {
    static $checked = false;
    static $ok = false;

    if ($checked) {
        return $ok;
    }

    $checked = true;
    $prefix = $modx->getOption('table_prefix');
    $sql = "CREATE TABLE IF NOT EXISTS {$prefix}pending_emails (
"
        . "  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
"
        . "  email_type VARCHAR(64) NOT NULL,
"
        . "  recipient_email VARCHAR(191) NOT NULL,
"
        . "  subject VARCHAR(255) NOT NULL,
"
        . "  body_html MEDIUMTEXT NOT NULL,
"
        . "  status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
"
        . "  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
"
        . "  max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,
"
        . "  available_at DATETIME NOT NULL,
"
        . "  sent_at DATETIME NULL,
"
        . "  last_error TEXT NULL,
"
        . "  created_at DATETIME NOT NULL,
"
        . "  updated_at DATETIME NOT NULL,
"
        . "  PRIMARY KEY (id),
"
        . "  KEY idx_pending_status_available (status, available_at),
"
        . "  KEY idx_pending_recipient (recipient_email),
"
        . "  KEY idx_pending_type (email_type)
"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    try {
        $ok = $modx->exec($sql) !== false;
    } catch (Throwable $e) {
        $ok = false;
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Failed to ensure pending_emails table: ' . $e->getMessage());
    }

    if (!$ok) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] pending_emails table is unavailable.');
    }

    return $ok;
};

$queueEmail = static function (modX $modx, string $emailType, string $recipientEmail, string $subject, string $bodyHtml, int $maxAttempts = 5) use ($ensurePendingEmailsTable): bool {
    if (!$ensurePendingEmailsTable($modx)) {
        return false;
    }

    $prefix = $modx->getOption('table_prefix');
    $stmt = $modx->prepare(
        "INSERT INTO {$prefix}pending_emails (email_type, recipient_email, subject, body_html, status, attempts, max_attempts, available_at, created_at, updated_at)
"
        . "VALUES (:email_type, :recipient_email, :subject, :body_html, 'pending', 0, :max_attempts, :available_at, :created_at, :updated_at)"
    );

    if ($stmt === false) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] queueEmail prepare failed');
        return false;
    }

    $now = date('Y-m-d H:i:s');
    $stmt->bindValue(':email_type', $emailType, PDO::PARAM_STR);
    $stmt->bindValue(':recipient_email', $recipientEmail, PDO::PARAM_STR);
    $stmt->bindValue(':subject', $subject, PDO::PARAM_STR);
    $stmt->bindValue(':body_html', $bodyHtml, PDO::PARAM_STR);
    $stmt->bindValue(':max_attempts', max(1, $maxAttempts), PDO::PARAM_INT);
    $stmt->bindValue(':available_at', $now, PDO::PARAM_STR);
    $stmt->bindValue(':created_at', $now, PDO::PARAM_STR);
    $stmt->bindValue(':updated_at', $now, PDO::PARAM_STR);

    if (!$stmt->execute()) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] queueEmail execute failed: ' . implode(' | ', $stmt->errorInfo()));
        return false;
    }

    return true;
};

$queueActivationEmail = static function (modX $modx, string $email, string $username, string $activationToken) use ($queueEmail): bool {
    $activationResourceId = (int)$modx->getOption('auth_activation_resource_id', null, 0);
    if ($activationResourceId <= 0) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Config error: auth_activation_resource_id is empty or invalid. Activation email queue skipped.');
        return false;
    }

    $activationTtlHours = max(1, (int)$modx->getOption('auth_activation_ttl_hours', null, 24));
    $activationUrl = $modx->makeUrl($activationResourceId, '', [
        'mode' => 'activate',
        'token' => $activationToken,
    ], 'full');

    $subject = 'Активация аккаунта - ' . $modx->getOption('site_name');
    $body = '
        <h2>Подтверждение регистрации</h2>
        <p>Здравствуйте, ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '!</p>
        <p>Для завершения регистрации подтвердите email по ссылке:</p>
        <p><a href="' . htmlspecialchars($activationUrl, ENT_QUOTES, 'UTF-8') . '">Активировать аккаунт</a></p>
        <p>Ссылка действует ' . $activationTtlHours . ' часа.</p>
        <p>Если вы не регистрировались, просто проигнорируйте это письмо.</p>
    ';

    return $queueEmail($modx, 'activation', $email, $subject, $body);
};

$queueForgotPasswordEmail = static function (modX $modx, string $email, string $resetUrl) use ($queueEmail): bool {
    $subject = 'Восстановление пароля';
    $body = '
        <h3>Восстановление пароля</h3>
        <p>Вы запросили восстановление пароля на сайте ' . htmlspecialchars((string)$modx->getOption('site_name'), ENT_QUOTES, 'UTF-8') . '.</p>
        <p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Нажмите здесь для установки нового пароля</a></p>
        <p>Ссылка действительна 1 час.</p>
        <p>Если вы не запрашивали восстановление пароля, проигнорируйте это письмо.</p>
    ';

    return $queueEmail($modx, 'forgot_password', $email, $subject, $body);
};

$registerUser = static function (modX $modx, array $post) use ($queueActivationEmail): array {
    $modx->log(modX::LOG_LEVEL_INFO, '[authHandler] start register');
    $errors = [];
    $success = [];

    $username = trim((string)($post['username'] ?? ''));
    $email = trim((string)($post['email'] ?? ''));
    $password = (string)($post['password'] ?? '');
    $passwordConfirm = (string)($post['password_confirm'] ?? '');

    $fieldErrors = [];
    if ($username === '') {
        $fieldErrors['username'] = 'Введите логин';
    }
    if ($email === '') {
        $fieldErrors['email'] = 'Введите email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = 'Неверный формат email';
    }
    if ($password === '') {
        $fieldErrors['password'] = 'Введите пароль';
    } elseif (strlen($password) < 6) {
        $fieldErrors['password'] = 'Пароль минимум 6 символов';
    }
    if ($password !== $passwordConfirm) {
        $fieldErrors['password_confirm'] = 'Пароли не совпадают';
    }

    if (!empty($fieldErrors)) {
        return ['errors' => array_values($fieldErrors), 'success' => $success];
    }

    if ($modx->getObject('modUser', ['username' => $username])) {
        $errors[] = 'Логин уже занят';
        return ['errors' => $errors, 'success' => $success];
    }

    $prefix = $modx->getOption('table_prefix');
    $stmt = $modx->prepare("SELECT COUNT(*) FROM {$prefix}user_attributes WHERE email = :email");
    if ($stmt === false) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] registerUser: email uniqueness prepare failed');
        return ['errors' => ['Временная ошибка базы данных. Попробуйте позже.'], 'success' => $success];
    }

    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    if (!$stmt->execute()) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] registerUser: email uniqueness execute failed: ' . implode(' | ', $stmt->errorInfo()));
        return ['errors' => ['Временная ошибка базы данных. Попробуйте позже.'], 'success' => $success];
    }

    if ((int)$stmt->fetchColumn() > 0) {
        $errors[] = 'Email уже используется';
        return ['errors' => $errors, 'success' => $success];
    }

    $activationToken = bin2hex(random_bytes(32));
    $modx->log(modX::LOG_LEVEL_INFO, '[authHandler] token generated for registration');
    $activationTtlHours = (int)$modx->getOption('auth_activation_ttl_hours', null, 24);
    $sentAt = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . max(1, $activationTtlHours) . ' hours'));

    $user = $modx->newObject('modUser');
    $user->set('username', $username);
    $user->set('password', $password);
    $user->set('active', 0);

    $profile = $modx->newObject('modUserProfile');
    $profile->set('email', $email);
    $profile->set('fullname', $username);
    $profile->set('blocked', 0);
    $profile->set('extended', [
        'activation_token' => $activationToken,
        'activation_sent_at' => $sentAt,
        'activation_expires_at' => $expiresAt,
    ]);
    $user->addOne($profile);

    $modx->beginTransaction();
    try {
        if (!$user->save()) {
            throw new RuntimeException('user save failed');
        }
        $modx->log(modX::LOG_LEVEL_INFO, '[authHandler] user saved, userId=' . (int)$user->id);

        $studentGroup = $modx->getObject('modUserGroup', ['name' => 'LMS Students']);
        if (!$studentGroup) {
            throw new RuntimeException('LMS Students group not found');
        }

        $membership = $modx->newObject('modUserGroupMember');
        $membership->set('user_group', $studentGroup->id);
        $membership->set('member', $user->id);
        $membership->set('role', 1);
        $membership->set('rank', 0);
        if (!$membership->save()) {
            throw new RuntimeException('membership save failed');
        }
        $modx->log(modX::LOG_LEVEL_INFO, '[authHandler] group assigned, userId=' . (int)$user->id . ', groupId=' . (int)$studentGroup->id);

        $modx->commit();
    } catch (Throwable $e) {
        $modx->rollBack();
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] registerUser transaction failed: ' . $e->getMessage());
        return ['errors' => ['Ошибка создания пользователя'], 'success' => $success];
    }

    $queued = $queueActivationEmail($modx, $email, $username, $activationToken);
    if ($queued) {
        $modx->log(modX::LOG_LEVEL_INFO, '[authHandler] activation email queued, userId=' . (int)$user->id);
        $success[] = 'Аккаунт создан. Проверьте email: письмо активации будет отправлено в ближайшее время.';
    } else {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] activation email queue failed, userId=' . (int)$user->id);
        $errors[] = 'Аккаунт создан, но письмо активации не поставлено в очередь. Пожалуйста, свяжитесь с администратором сайта.';
    }

    $modx->log(modX::LOG_LEVEL_INFO, '[authHandler] registerUser completed for user #' . (int)$user->id . ', queued=' . ($queued ? '1' : '0'));

    $checkEmailResourceId = (int)$modx->getOption('auth_check_email_resource_id', null, 0);
    $prgRedirect = $checkEmailResourceId > 0
        ? $modx->makeUrl($checkEmailResourceId)
        : $modx->makeUrl((int)$modx->resource->id, '', ['mode' => 'login']);

    return [
        'errors' => $errors,
        'success' => $success,
        'prg_redirect' => $prgRedirect,
    ];
};

$activateUserByToken = static function (modX $modx, string $activationToken): array {
    $errors = [];
    $success = [];

    if ($activationToken === '') {
        return ['errors' => ['Неверная ссылка активации.'], 'success' => $success];
    }

    $prefix = $modx->getOption('table_prefix');
    $stmt = $modx->prepare("SELECT internalKey FROM {$prefix}user_attributes WHERE JSON_UNQUOTE(JSON_EXTRACT(extended, '$.activation_token')) = :token LIMIT 1");
    if ($stmt === false) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] activateUserByToken prepare failed');
        return ['errors' => ['Временная ошибка базы данных. Попробуйте позже.'], 'success' => $success];
    }

    $stmt->bindValue(':token', $activationToken, PDO::PARAM_STR);
    if (!$stmt->execute()) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] activateUserByToken execute failed: ' . implode(' | ', $stmt->errorInfo()));
        return ['errors' => ['Временная ошибка базы данных. Попробуйте позже.'], 'success' => $success];
    }

    $userId = (int)$stmt->fetchColumn();
    if ($userId <= 0) {
        return ['errors' => ['Токен активации недействителен, уже использован или повреждён.'], 'success' => $success];
    }

    $user = $modx->getObject('modUser', $userId);
    $profile = $modx->getObject('modUserProfile', ['internalKey' => $userId]);
    if (!$user || !$profile) {
        return ['errors' => ['Пользователь для активации не найден.'], 'success' => $success];
    }

    $extended = $profile->get('extended') ?: [];
    $storedToken = (string)($extended['activation_token'] ?? '');
    $expiresAt = (string)($extended['activation_expires_at'] ?? '');

    if ($storedToken === '' || !hash_equals($storedToken, $activationToken)) {
        return ['errors' => ['Токен активации недействителен, уже использован или повреждён.'], 'success' => $success];
    }

    if ($expiresAt === '' || strtotime($expiresAt) < time()) {
        $modx->log(modX::LOG_LEVEL_WARN, '[authHandler] activation token expired, userId=' . $userId);
        return ['errors' => ['Срок действия ссылки активации истёк. Запросите новую ссылку.'], 'success' => $success];
    }

    if ((int)$user->get('active') === 1) {
        return ['errors' => [], 'success' => ['Аккаунт уже активирован. Можете войти.']];
    }

    unset($extended['activation_token'], $extended['activation_sent_at'], $extended['activation_expires_at']);
    $profile->set('extended', $extended);
    $user->set('active', 1);

    if (!$profile->save() || !$user->save()) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] activateUserByToken save failed for user #' . $userId);
        return ['errors' => ['Ошибка активации аккаунта. Попробуйте запросить письмо повторно.'], 'success' => $success];
    }

    $modx->log(modX::LOG_LEVEL_INFO, '[authHandler] activateUserByToken success for user #' . $userId);
    return ['errors' => [], 'success' => ['Email подтверждён. Аккаунт активирован, теперь можете войти.']];
};

$resendActivationEmail = static function (modX $modx, string $email) use ($queueActivationEmail): array {
    $genericSuccess = 'Если аккаунт существует и не активирован, письмо будет отправлено.';
    $modx->log(modX::LOG_LEVEL_INFO, '[authHandler] resend requested');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['errors' => ['Введите корректный email.'], 'success' => []];
    }

    $prefix = $modx->getOption('table_prefix');
    $stmt = $modx->prepare("SELECT internalKey FROM {$prefix}user_attributes WHERE email = :email LIMIT 1");
    if ($stmt === false) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] resendActivationEmail prepare failed');
        return ['errors' => [], 'success' => [$genericSuccess]];
    }

    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    if (!$stmt->execute()) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] resendActivationEmail execute failed: ' . implode(' | ', $stmt->errorInfo()));
        return ['errors' => [], 'success' => [$genericSuccess]];
    }

    $userId = (int)$stmt->fetchColumn();
    if ($userId <= 0) {
        return ['errors' => [], 'success' => [$genericSuccess]];
    }

    $user = $modx->getObject('modUser', $userId);
    $profile = $user ? $user->getOne('Profile') : null;
    if (!$user || !$profile) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] resend missing user/profile for userId=' . $userId);
        return ['errors' => [], 'success' => [$genericSuccess]];
    }

    if ((int)$user->get('active') === 1) {
        return ['errors' => [], 'success' => [$genericSuccess]];
    }

    $extended = $profile->get('extended') ?: [];
    $lastSentAt = isset($extended['activation_sent_at']) ? strtotime((string)$extended['activation_sent_at']) : false;
    if ($lastSentAt !== false && (time() - $lastSentAt) < 300) {
        $modx->log(modX::LOG_LEVEL_WARN, '[authHandler] resend rate limited, userId=' . $userId);
        return ['errors' => [], 'success' => [$genericSuccess]];
    }

    $activationToken = bin2hex(random_bytes(32));
    $activationTtlHours = (int)$modx->getOption('auth_activation_ttl_hours', null, 24);
    $extended['activation_token'] = $activationToken;
    $extended['activation_sent_at'] = date('Y-m-d H:i:s');
    $extended['activation_expires_at'] = date('Y-m-d H:i:s', strtotime('+' . max(1, $activationTtlHours) . ' hours'));
    $profile->set('extended', $extended);

    if ($profile->save()) {
        $queued = $queueActivationEmail($modx, $email, (string)$user->get('username'), $activationToken);
        if ($queued) {
            $modx->log(modX::LOG_LEVEL_INFO, '[authHandler] resend activation email queued, emailHash=' . sha1($email));
        } else {
            $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] resend activation email queue failed, emailHash=' . sha1($email));
        }
    } else {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] resend token save failed, emailHash=' . sha1($email));
    }

    $modx->log(modX::LOG_LEVEL_INFO, '[authHandler] resendActivationEmail processed for email hash=' . sha1($email));
    return ['errors' => [], 'success' => [$genericSuccess]];
};

$loginUser = static function (modX $modx, array $post): array {
    $errors = [];
    $username = trim((string)($post['username'] ?? ''));
    $password = (string)($post['password'] ?? '');
    $rememberme = !empty($post['rememberme']);

    if ($username === '') {
        $errors[] = 'Введите логин';
    }
    if ($password === '') {
        $errors[] = 'Введите пароль';
    }

    if (!empty($errors)) {
        return ['errors' => $errors, 'success' => []];
    }

    $user = $modx->getObject('modUser', ['username' => $username]);
    if ($user && (int)$user->get('active') !== 1) {
        return ['errors' => ['Аккаунт не активирован. Проверьте email и перейдите по ссылке активации или запросите письмо повторно.'], 'success' => []];
    }

    $response = $modx->runProcessor('security/login', [
        'username' => $username,
        'password' => $password,
        'rememberme' => $rememberme,
        'login_context' => 'web',
    ]);

    if (!$response || $response->isError()) {
        return ['errors' => ['Неверный логин или пароль'], 'success' => []];
    }

    $afterLoginResourceId = (int)$modx->getOption('auth_after_login_resource_id', null, 35);
    return ['errors' => [], 'success' => [], 'redirect' => $modx->makeUrl($afterLoginResourceId > 0 ? $afterLoginResourceId : 35)];
};

// Action handlers
if ($mode === 'activate') {
    $result = $activateUserByToken($modx, trim((string)($_GET['token'] ?? '')));
    $errors = array_merge($errors, $result['errors']);
    $success = array_merge($success, $result['success']);
    $mode = 'login';
}

if ($_POST && $mode === 'login') {
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        $result = $loginUser($modx, $_POST);
        $errors = array_merge($errors, $result['errors']);
        $success = array_merge($success, $result['success']);
        if (!empty($result['redirect'])) {
            $modx->sendRedirect($result['redirect']);
            exit;
        }
    }
}

if ($_POST && $mode === 'register') {
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        $result = $registerUser($modx, $_POST);
        if (!empty($result['prg_redirect'])) {
            $_SESSION['auth_handler_flash'] = [
                'errors' => $result['errors'] ?? [],
                'success' => $result['success'] ?? [],
            ];
            $modx->sendRedirect($result['prg_redirect']);
            exit;
        }
        $errors = array_merge($errors, $result['errors'] ?? []);
        $success = array_merge($success, $result['success'] ?? []);
    }
}

if ($_POST && $mode === 'resend_activation') {
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        $prefillResendEmail = trim((string)($_POST['email'] ?? ''));
        $result = $resendActivationEmail($modx, $prefillResendEmail);
        $errors = array_merge($errors, $result['errors']);
        $success = array_merge($success, $result['success']);
    }
}

// ВОССТАНОВЛЕНИЕ ПАРОЛЯ - запрос
if ($_POST && $mode === 'forgot') {
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));

        if ($email === '') {
            $errors[] = 'Введите email';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Неверный формат email';
        } else {
            $prefix = $modx->getOption('table_prefix');
            $stmt = $modx->prepare("SELECT internalKey FROM {$prefix}user_attributes WHERE email = :email");
            if ($stmt === false) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Forgot password prepare failed');
                $errors[] = 'Временная ошибка базы данных. Попробуйте позже.';
                $userId = 0;
            } else {
                $stmt->bindValue(':email', $email, PDO::PARAM_STR);
                if (!$stmt->execute()) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Forgot password execute failed: ' . implode(' | ', $stmt->errorInfo()));
                    $errors[] = 'Временная ошибка базы данных. Попробуйте позже.';
                    $userId = 0;
                } else {
                    $userId = (int)$stmt->fetchColumn();
                }
            }

            if ($userId > 0) {
                $user = $modx->getObject('modUser', $userId);
                $profile = $user ? $user->getOne('Profile') : null;

                if ($user && $profile) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $extended = $profile->get('extended') ?: [];
                    $extended['reset_token'] = $token;
                    $extended['reset_expiry'] = $expiry;
                    $profile->set('extended', $extended);
                    if (!$profile->save()) {
                        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] forgot token save failed, userId=' . $userId);
                        $errors[] = 'Не удалось подготовить восстановление пароля. Попробуйте позже.';
                    } else {

                        $resetUrl = $modx->makeUrl((int)$modx->resource->id, '', [
                            'mode' => 'reset',
                            'token' => $token,
                        ], 'full');

                        $queued = $queueForgotPasswordEmail($modx, $email, $resetUrl);
                        if ($queued) {
                            $modx->log(modX::LOG_LEVEL_INFO, '[authHandler] forgot password email queued, userId=' . $userId);
                        } else {
                            $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] forgot password email queue failed, userId=' . $userId);
                        }

                        $success[] = 'Если email зарегистрирован, ссылка для восстановления будет отправлена';
                    }
                } else {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] forgot missing user/profile for userId=' . $userId);
                    $success[] = 'Если email зарегистрирован, ссылка для восстановления будет отправлена';
                }
            } else {
                $success[] = 'Если email зарегистрирован, ссылка для восстановления будет отправлена';
            }
        }
    }
}

// ВОССТАНОВЛЕНИЕ ПАРОЛЯ - установка нового пароля
if ($mode === 'reset') {
    $token = (string)($_GET['token'] ?? '');

    if ($_POST && isset($_POST['new_password'])) {
        if (!CsrfProtection::validateRequest($_POST)) {
            $errors[] = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
        } else {
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmPassword = (string)($_POST['confirm_password'] ?? '');

            if ($newPassword === '') {
                $errors[] = 'Введите новый пароль';
            } elseif (strlen($newPassword) < 6) {
                $errors[] = 'Пароль должен быть минимум 6 символов';
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = 'Пароли не совпадают';
            } else {
                $prefix = $modx->getOption('table_prefix');
                $stmt = $modx->prepare("SELECT internalKey FROM {$prefix}user_attributes WHERE JSON_UNQUOTE(JSON_EXTRACT(extended, '$.reset_token')) = :token LIMIT 1");
                $foundUser = null;

                if ($stmt === false) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Reset token prepare failed');
                    $errors[] = 'Временная ошибка базы данных. Попробуйте позже.';
                } else {
                    $stmt->bindValue(':token', $token, PDO::PARAM_STR);
                    if (!$stmt->execute()) {
                        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Reset token execute failed: ' . implode(' | ', $stmt->errorInfo()));
                        $errors[] = 'Временная ошибка базы данных. Попробуйте позже.';
                    } else {
                        $userId = (int)$stmt->fetchColumn();
                        if ($userId > 0) {
                            $profile = $modx->getObject('modUserProfile', ['internalKey' => $userId]);
                            $extended = $profile ? ($profile->get('extended') ?: []) : [];
                            $isValidExpiry = isset($extended['reset_expiry']) && strtotime((string)$extended['reset_expiry']) > time();

                            if ($profile && isset($extended['reset_token']) && hash_equals((string)$extended['reset_token'], $token) && $isValidExpiry) {
                                $foundUser = $modx->getObject('modUser', $userId);
                                unset($extended['reset_token'], $extended['reset_expiry']);
                                $profile->set('extended', $extended);
                                if (!$profile->save()) {
                                    $errors[] = 'Ошибка обновления токена. Попробуйте снова.';
                                    $foundUser = null;
                                }
                            }
                        }
                    }
                }

                if ($foundUser) {
                    $foundUser->set('password', $newPassword);
                    if ($foundUser->save()) {
                        $success[] = 'Пароль успешно изменен! Теперь можете войти.';
                        $mode = 'login';
                    } else {
                        $errors[] = 'Ошибка сохранения нового пароля';
                    }
                } elseif (empty($errors)) {
                    $errors[] = 'Недействительная или просроченная ссылка сброса';
                }
            }
        }
    }

    if ($mode === 'reset' && empty($success)) {
        $output = '<div class="auth-wrapper" style="max-width: 500px; margin: 50px auto; padding: 30px; background: #fff; border-radius: 10px; box-shadow: 0 2px 20px rgba(0,0,0,0.1);">';

        foreach ($errors as $error) {
            $output .= '<div class="alert alert-danger">' . htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        foreach ($success as $msg) {
            $output .= '<div class="alert alert-success">' . htmlspecialchars((string)$msg, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        $output .= '<h4 class="mb-4">Установка нового пароля</h4>';
        $output .= '<form method="POST">';
        $output .= CsrfProtection::getTokenField();
        $output .= '<input type="hidden" name="mode" value="reset">';
        $output .= '<div class="mb-3"><label class="form-label">Новый пароль</label><input type="password" name="new_password" class="form-control" required></div>';
        $output .= '<div class="mb-3"><label class="form-label">Подтверждение пароля</label><input type="password" name="confirm_password" class="form-control" required></div>';
        $output .= '<button type="submit" class="btn btn-primary">Сохранить пароль</button>';
        $output .= '</form></div>';

        return $output;
    }
}

$output = '<div class="auth-wrapper" style="max-width: 500px; margin: 50px auto; padding: 30px; background: #fff; border-radius: 10px; box-shadow: 0 2px 20px rgba(0,0,0,0.1);">';

foreach ($errors as $error) {
    $output .= '<div class="alert alert-danger">' . htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') . '</div>';
}
foreach ($success as $msg) {
    $output .= '<div class="alert alert-success">' . htmlspecialchars((string)$msg, ENT_QUOTES, 'UTF-8') . '</div>';
}

if ($mode === 'resend_activation' && empty($success)) {
    $loginUrl = $modx->makeUrl((int)$modx->resource->id);
    $output .= '<h4 class="mb-4">Повторная отправка активации</h4>';
    $output .= '<p class="text-muted">Введите email, указанный при регистрации</p>';
    $output .= '<form method="POST">';
    $output .= CsrfProtection::getTokenField();
    $output .= '<input type="hidden" name="mode" value="resend_activation">';
    $output .= '<div class="mb-3"><label class="form-label">Email</label>';
    $resendEmailValue = $_POST['email'] ?? $prefillResendEmail;
    $output .= '<input type="email" name="email" class="form-control" value="' . htmlspecialchars((string)$resendEmailValue, ENT_QUOTES, 'UTF-8') . '" required></div>';
    $output .= '<button type="submit" class="btn btn-primary">Отправить ссылку активации</button>';
    $output .= ' <a href="' . $loginUrl . '" class="btn btn-link">Вернуться ко входу</a>';
    $output .= '</form></div>';
    return $output;
}

if ($mode === 'forgot' && empty($success)) {
    $loginUrl = $modx->makeUrl((int)$modx->resource->id);
    $output .= '<h4 class="mb-4">Восстановление пароля</h4>';
    $output .= '<p class="text-muted">Введите email, указанный при регистрации</p>';
    $output .= '<form method="POST">';
    $output .= CsrfProtection::getTokenField();
    $output .= '<input type="hidden" name="mode" value="forgot">';
    $output .= '<div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>';
    $output .= '<button type="submit" class="btn btn-primary">Отправить ссылку</button>';
    $output .= ' <a href="' . $loginUrl . '" class="btn btn-link">Вернуться к входу</a>';
    $output .= '</form></div>';
    return $output;
}

$activeTab = $mode === 'register' ? 'register' : 'login';

$output .= '<ul class="nav nav-tabs mb-4">';
$output .= '<li class="nav-item"><button class="nav-link ' . ($activeTab === 'login' ? 'active' : '') . '" data-bs-toggle="tab" data-bs-target="#login-tab">Вход</button></li>';
$output .= '<li class="nav-item"><button class="nav-link ' . ($activeTab === 'register' ? 'active' : '') . '" data-bs-toggle="tab" data-bs-target="#register-tab">Регистрация</button></li>';
$output .= '</ul>';

$output .= '<div class="tab-content">';

$output .= '<div class="tab-pane fade ' . ($activeTab === 'login' ? 'show active' : '') . '" id="login-tab">';
$output .= '<form method="POST">';
$output .= CsrfProtection::getTokenField();
$output .= '<input type="hidden" name="mode" value="login">';
$output .= '<div class="mb-3"><label class="form-label">Логин</label><input type="text" name="username" class="form-control" value="' . htmlspecialchars((string)($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8') . '" required></div>';
$output .= '<div class="mb-3"><label class="form-label">Пароль</label><input type="password" name="password" class="form-control" required></div>';
$output .= '<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="rememberme" id="rememberme"><label class="form-check-label" for="rememberme">Запомнить меня</label></div>';
$output .= '<div class="d-flex justify-content-between align-items-center">';
$output .= '<button type="submit" class="btn btn-primary">Войти</button>';
$forgotUrl = $modx->makeUrl((int)$modx->resource->id, '', ['mode' => 'forgot']);
$resendUrl = $modx->makeUrl((int)$modx->resource->id, '', ['mode' => 'resend_activation']);
$output .= '<a href="' . $forgotUrl . '" class="text-muted small">Забыли пароль?</a>';
$output .= '<a href="' . $resendUrl . '" class="text-muted small ms-3">Не пришло письмо активации?</a>';
$output .= '</div></form></div>';

$output .= '<div class="tab-pane fade ' . ($activeTab === 'register' ? 'show active' : '') . '" id="register-tab">';
$output .= '<form method="POST">';
$output .= CsrfProtection::getTokenField();
$output .= '<input type="hidden" name="mode" value="register">';
$output .= '<div class="mb-3"><label class="form-label">Логин *</label><input type="text" name="username" class="form-control" required></div>';
$output .= '<div class="mb-3"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>';
$output .= '<div class="mb-3"><label class="form-label">Пароль * (минимум 6 символов)</label><input type="password" name="password" class="form-control" required></div>';
$output .= '<div class="mb-3"><label class="form-label">Подтверждение пароля *</label><input type="password" name="password_confirm" class="form-control" required></div>';
$output .= '<button type="submit" class="btn btn-success">Зарегистрироваться</button>';
$output .= '<p class="text-muted small mt-3 mb-0">После регистрации нужно подтвердить email по ссылке из письма.</p>';
$output .= '</form></div>';

$output .= '</div></div>';

return $output;
