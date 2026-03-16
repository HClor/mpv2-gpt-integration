<?php
/**
 * Сниппет: authHandler - Обработчик авторизации
 * Вызывается из: MODX ресурсов (страница входа/регистрации)
 * Назначение: Обрабатывает вход, регистрацию, выход, активацию и восстановление пароля
 *
 * @package TestSystem
 * @version 2.5
 * @note Упрощённая production-версия: прямые письма + PRG + flash + TTL + rate-limit
 *       Без очереди pending_emails и cron'а. Регистрация мгновенная.
 */

// Подключаем bootstrap для CSRF защиты
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];
$success = [];
$mode = $_GET['mode'] ?? ($_POST['mode'] ?? 'login');
$prefillResendEmail = '';

$authLog = static function (modX $modx, string $message, int $level = modX::LOG_LEVEL_ERROR): void {
    // Пишем всё как ERROR, чтобы шаги были видны даже при log_level=ERROR в MODX.
    $levelName = match ($level) {
        modX::LOG_LEVEL_INFO => 'INFO',
        modX::LOG_LEVEL_WARN => 'WARN',
        modX::LOG_LEVEL_ERROR => 'ERROR',
        default => 'LOG'
    };
    $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler][diag][' . $levelName . '] ' . $message);
};

$checkSmtpReachable = static function (modX $modx) use ($authLog): bool {
    $useSmtp = (bool)$modx->getOption('mail_use_smtp', null, false);
    if (!$useSmtp) {
        $authLog($modx, 'MAIL PREFLIGHT: mail_use_smtp=0, skip SMTP socket check', modX::LOG_LEVEL_INFO);
        return true;
    }

    $hostsRaw = trim((string)$modx->getOption('mail_smtp_hosts', null, ''));
    if ($hostsRaw === '') {
        $hostsRaw = trim((string)$modx->getOption('mail_smtp_host', null, ''));
    }
    $port = (int)$modx->getOption('mail_smtp_port', null, 25);
    $timeout = 3.0;

    if ($hostsRaw === '') {
        $authLog($modx, 'MAIL PREFLIGHT WARNING: SMTP enabled but host is empty', modX::LOG_LEVEL_WARN);
        return false;
    }

    $hosts = preg_split('/[;,]+/', $hostsRaw) ?: [];
    foreach ($hosts as $host) {
        $host = trim($host);
        if ($host === '') {
            continue;
        }

        $authLog($modx, 'MAIL PREFLIGHT: socket check ' . $host . ':' . $port, modX::LOG_LEVEL_INFO);
        $errno = 0;
        $errstr = '';
        $conn = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, $timeout);
        if (is_resource($conn)) {
            fclose($conn);
            $authLog($modx, 'MAIL PREFLIGHT OK: SMTP host reachable ' . $host . ':' . $port, modX::LOG_LEVEL_INFO);
            return true;
        }

        $authLog($modx, 'MAIL PREFLIGHT FAIL: ' . $host . ':' . $port . ' errno=' . $errno . ' err=' . $errstr, modX::LOG_LEVEL_WARN);
    }

    return false;
};

$authLog = static function (modX $modx, string $message, int $level = modX::LOG_LEVEL_ERROR): void {
    // Пишем всё как ERROR, чтобы шаги были видны даже при log_level=ERROR в MODX.
    $levelName = match ($level) {
        modX::LOG_LEVEL_INFO => 'INFO',
        modX::LOG_LEVEL_WARN => 'WARN',
        modX::LOG_LEVEL_ERROR => 'ERROR',
        default => 'LOG'
    };
    $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler][diag][' . $levelName . '] ' . $message);
};

$checkSmtpReachable = static function (modX $modx) use ($authLog): bool {
    $useSmtp = (bool)$modx->getOption('mail_use_smtp', null, false);
    if (!$useSmtp) {
        $authLog($modx, 'MAIL PREFLIGHT: mail_use_smtp=0, skip SMTP socket check', modX::LOG_LEVEL_INFO);
        return true;
    }

    $hostsRaw = trim((string)$modx->getOption('mail_smtp_hosts', null, ''));
    if ($hostsRaw === '') {
        $hostsRaw = trim((string)$modx->getOption('mail_smtp_host', null, ''));
    }
    $port = (int)$modx->getOption('mail_smtp_port', null, 25);
    $timeout = 3.0;

    if ($hostsRaw === '') {
        $authLog($modx, 'MAIL PREFLIGHT WARNING: SMTP enabled but host is empty', modX::LOG_LEVEL_WARN);
        return false;
    }

    $hosts = preg_split('/[;,]+/', $hostsRaw) ?: [];
    foreach ($hosts as $host) {
        $host = trim($host);
        if ($host === '') {
            continue;
        }

        $authLog($modx, 'MAIL PREFLIGHT: socket check ' . $host . ':' . $port, modX::LOG_LEVEL_INFO);
        $errno = 0;
        $errstr = '';
        $conn = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, $timeout);
        if (is_resource($conn)) {
            fclose($conn);
            $authLog($modx, 'MAIL PREFLIGHT OK: SMTP host reachable ' . $host . ':' . $port, modX::LOG_LEVEL_INFO);
            return true;
        }

        $authLog($modx, 'MAIL PREFLIGHT FAIL: ' . $host . ':' . $port . ' errno=' . $errno . ' err=' . $errstr, modX::LOG_LEVEL_WARN);
    }

    return false;
};

$authLog = static function (modX $modx, string $message, int $level = modX::LOG_LEVEL_ERROR): void {
    $modx->log($level, '[authHandler][diag] ' . $message);
};

// FLASH-СООБЩЕНИЯ (PRG-паттерн)
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

// ====================== ВЫХОД ======================
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

// ====================== УЖЕ АВТОРИЗОВАН ======================
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

// ====================== ПОДГОТОВКА И ОТПРАВКА ПИСЕМ ======================
$prepareMailTransport = static function (modX $modx) use ($authLog) {
    $authLog($modx, 'MAIL STEP 1: init mail service', modX::LOG_LEVEL_INFO);
    $mailService = $modx->getService('mail', 'mail.modPHPMailer');
    if (!$mailService || !isset($modx->mail)) {
        $authLog($modx, 'MAIL STEP 1 FAILED: mail service unavailable');
        return false;
    }

    if ($modx->mail->mailer) {
        $authLog($modx, 'MAIL STEP 2: configure transport (Timeout=10, KeepAlive=off, AutoTLS=on)', modX::LOG_LEVEL_INFO);
        $modx->mail->mailer->Timeout = 10;
        $modx->mail->mailer->SMTPKeepAlive = false;
        $modx->mail->mailer->SMTPAutoTLS = true;
    } else {
        $authLog($modx, 'MAIL STEP 2 WARNING: mailer object is empty', modX::LOG_LEVEL_WARN);
    }

    $authLog($modx, 'MAIL STEP 3: transport ready', modX::LOG_LEVEL_INFO);
    return true;
};

$sendActivationEmail = static function (modX $modx, string $email, string $username, string $activationToken) use ($prepareMailTransport, $checkSmtpReachable, $authLog): bool {
    $authLog($modx, 'ACTIVATION MAIL STEP 1: build activation URL for ' . $email, modX::LOG_LEVEL_INFO);
    $activationUrl = $modx->makeUrl($modx->resource->id, '', [
        'mode' => 'activate',
        'token' => $activationToken
    ], 'full');

    if (!$prepareMailTransport($modx)) {
        $authLog($modx, 'ACTIVATION MAIL STOP: transport is not ready');
        return false;
    }

    if (!$checkSmtpReachable($modx)) {
        $authLog($modx, 'ACTIVATION MAIL STOP: SMTP preflight failed');
        return false;
    }

    $fromEmail = trim((string)$modx->getOption('emailsender'));
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $fallbackDomain = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $fromEmail = 'noreply@' . preg_replace('/:\\d+$/', '', $fallbackDomain);
        $authLog($modx, 'ACTIVATION MAIL STEP 2 WARNING: invalid system emailsender, fallback=' . $fromEmail, modX::LOG_LEVEL_WARN);
    }

    $fromEmail = trim((string)$modx->getOption('emailsender'));
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $fallbackDomain = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $fromEmail = 'noreply@' . preg_replace('/:\\d+$/', '', $fallbackDomain);
        $authLog($modx, 'ACTIVATION MAIL STEP 2 WARNING: invalid system emailsender, fallback=' . $fromEmail, modX::LOG_LEVEL_WARN);
    }

    $fromEmail = trim((string)$modx->getOption('emailsender'));
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $fallbackDomain = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $fromEmail = 'noreply@' . preg_replace('/:\\d+$/', '', $fallbackDomain);
        $authLog($modx, 'ACTIVATION MAIL STEP 2 WARNING: invalid system emailsender, fallback=' . $fromEmail, modX::LOG_LEVEL_WARN);
    }

    $body = '
        <h2>Подтверждение регистрации</h2>
        <p>Здравствуйте, ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . '!</p>
        <p>Для завершения регистрации подтвердите email по ссылке:</p>
        <p><a href="' . htmlspecialchars($activationUrl, ENT_QUOTES, 'UTF-8') . '">Активировать аккаунт</a></p>
        <p>Ссылка действует 24 часа.</p>
        <p>Если вы не регистрировались, просто проигнорируйте это письмо.</p>
    ';

    $modx->mail->set(modMail::MAIL_BODY, $body);
    $authLog($modx, 'ACTIVATION MAIL STEP 3: set envelope/headers', modX::LOG_LEVEL_INFO);
    $modx->mail->set(modMail::MAIL_FROM, $fromEmail);
    $modx->mail->set(modMail::MAIL_FROM_NAME, $modx->getOption('site_name'));
    $modx->mail->set(modMail::MAIL_SUBJECT, 'Активация аккаунта - ' . $modx->getOption('site_name'));
    $modx->mail->address('to', $email);
    $modx->mail->setHTML(true);

    $authLog($modx, 'ACTIVATION MAIL STEP 4: send()', modX::LOG_LEVEL_INFO);
    $sent = $modx->mail->send();
    if (!$sent) {
        $errorInfo = $modx->mail->mailer->ErrorInfo ?? 'unknown error';
        $authLog($modx, 'ACTIVATION MAIL STEP 4 FAILED: ' . $errorInfo);
    } else {
        $authLog($modx, 'ACTIVATION MAIL STEP 4 OK: email sent to ' . $email, modX::LOG_LEVEL_INFO);
    }

    $authLog($modx, 'ACTIVATION MAIL STEP 5: mail reset()', modX::LOG_LEVEL_INFO);
    $modx->mail->reset();

    // После долгой SMTP-операции на shared-хостинге соединение с MySQL может быть разорвано.
    // Делаем reconnect без предварительного ping, чтобы не провоцировать лишний SQL-error в логах.
    $authLog($modx, 'ACTIVATION MAIL STEP 6: force DB reconnect after mail flow', modX::LOG_LEVEL_INFO);
    $reconnectOk = $modx->connect();
    if (!$reconnectOk) {
        $authLog($modx, 'ACTIVATION MAIL STEP 6 FAILED: DB reconnect failed');
    } else {
        $authLog($modx, 'ACTIVATION MAIL STEP 6 OK: DB ready', modX::LOG_LEVEL_INFO);
    }

    return $sent;
};

$sendForgotPasswordEmail = static function (modX $modx, string $email, string $resetUrl) use ($prepareMailTransport): bool {
    if (!$prepareMailTransport($modx)) {
        return false;
    }

    $body = '
        <h3>Восстановление пароля</h3>
        <p>Вы запросили восстановление пароля на сайте ' . htmlspecialchars((string)$modx->getOption('site_name'), ENT_QUOTES, 'UTF-8') . '.</p>
        <p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Нажмите здесь для установки нового пароля</a></p>
        <p>Ссылка действительна 1 час.</p>
        <p>Если вы не запрашивали восстановление пароля, проигнорируйте это письмо.</p>
    ';

    $modx->mail->set(modMail::MAIL_BODY, $body);
    $modx->mail->set(modMail::MAIL_FROM, $modx->getOption('emailsender'));
    $modx->mail->set(modMail::MAIL_FROM_NAME, $modx->getOption('site_name'));
    $modx->mail->set(modMail::MAIL_SUBJECT, 'Восстановление пароля');
    $modx->mail->address('to', $email);
    $modx->mail->setHTML(true);

    $sent = $modx->mail->send();
    if (!$sent) {
        $errorInfo = $modx->mail->mailer->ErrorInfo ?? 'unknown error';
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Failed to send reset email: ' . $errorInfo);
    }
    $modx->mail->reset();

    return $sent;
};

// ====================== РЕГИСТРАЦИЯ ======================
$registerUser = static function (modX $modx, array $post) use ($sendActivationEmail, $authLog): array {
    $errors = [];
    $success = [];

    $authLog($modx, 'REGISTER STEP 1: start registration flow', modX::LOG_LEVEL_INFO);

    $authLog($modx, 'REGISTER STEP 1: start registration flow', modX::LOG_LEVEL_INFO);

    $authLog($modx, 'REGISTER STEP 1: start registration flow', modX::LOG_LEVEL_INFO);

    $username = trim((string)($post['username'] ?? ''));
    $email = trim((string)($post['email'] ?? ''));
    $password = (string)($post['password'] ?? '');
    $passwordConfirm = (string)($post['password_confirm'] ?? '');

    if ($username === '') $errors[] = 'Введите логин';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Неверный формат email';
    if ($password === '' || strlen($password) < 6) $errors[] = 'Пароль минимум 6 символов';
    if ($password !== $passwordConfirm) $errors[] = 'Пароли не совпадают';

    if (!empty($errors)) {
        $authLog($modx, 'REGISTER STEP 2 FAILED: input validation', modX::LOG_LEVEL_WARN);
        return ['errors' => $errors, 'success' => $success];
    }
    $authLog($modx, 'REGISTER STEP 2 OK: input validation', modX::LOG_LEVEL_INFO);

    if ($modx->getObject('modUser', ['username' => $username])) {
        $authLog($modx, 'REGISTER STEP 3 FAILED: username exists=' . $username, modX::LOG_LEVEL_WARN);
        $errors[] = 'Логин уже занят';
        return ['errors' => $errors, 'success' => $success];
    }
    $authLog($modx, 'REGISTER STEP 3 OK: username available=' . $username, modX::LOG_LEVEL_INFO);

    $prefix = $modx->getOption('table_prefix');
    $stmt = $modx->prepare("SELECT COUNT(*) FROM {$prefix}user_attributes WHERE email = :email");
    if ($stmt === false) {
        $authLog($modx, 'REGISTER STEP 4 FAILED: SQL prepare user_attributes by email');
        $errors[] = 'Ошибка проверки email';
        return ['errors' => $errors, 'success' => $success];
    }
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    if (!$stmt->execute()) {
        $authLog($modx, 'REGISTER STEP 4 FAILED: SQL execute user_attributes by email ' . implode(' ', $stmt->errorInfo()));
        $errors[] = 'Ошибка проверки email';
        return ['errors' => $errors, 'success' => $success];
    }
    if ((int)$stmt->fetchColumn() > 0) {
        $authLog($modx, 'REGISTER STEP 4 FAILED: email already used=' . $email, modX::LOG_LEVEL_WARN);
        $errors[] = 'Email уже используется';
        return ['errors' => $errors, 'success' => $success];
    }
    $authLog($modx, 'REGISTER STEP 4 OK: email available=' . $email, modX::LOG_LEVEL_INFO);

    $activationToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

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
        'activation_sent_at' => date('Y-m-d H:i:s'),
        'activation_expires_at' => $expiresAt
    ]);
    $user->addOne($profile);

    $modx->beginTransaction();
    $authLog($modx, 'REGISTER STEP 5: transaction begin', modX::LOG_LEVEL_INFO);
    try {
        if (!$user->save()) {
            throw new RuntimeException('user save failed');
        }
        $authLog($modx, 'REGISTER STEP 6: user saved id=' . (int)$user->id, modX::LOG_LEVEL_INFO);

        // Compatibility mode: skip auto-membership to avoid modAccess/modUserGroup failures on some legacy installs.
        $authLog($modx, 'REGISTER STEP 7: skip LMS Students auto-membership for compatibility', modX::LOG_LEVEL_WARN);

        $modx->commit();
        $authLog($modx, 'REGISTER STEP 8: transaction commit', modX::LOG_LEVEL_INFO);
    } catch (Throwable $e) {
        $modx->rollBack();
        $authLog($modx, 'REGISTER STEP 8 FAILED: transaction rollback ' . $e->getMessage());
        $errors[] = 'Ошибка создания пользователя';
        return ['errors' => $errors, 'success' => $success];
    }

    $authLog($modx, 'REGISTER STEP 9: send activation email', modX::LOG_LEVEL_INFO);
    $mailSent = $sendActivationEmail($modx, $email, $username, $activationToken);

    if ($mailSent) {
        $success[] = '✅ Аккаунт создан. Письмо с ссылкой активации отправлено на ' . htmlspecialchars($email);
    } else {
        $errors[] = 'Аккаунт создан, но письмо не отправлено. Используйте повторную отправку.';
    }

    return [
        'errors' => $errors,
        'success' => $success,
        'prg_redirect' => $modx->makeUrl($modx->resource->id, '', ['mode' => 'login'])
    ];
};

// ====================== АКТИВАЦИЯ ======================
$activateUserByToken = static function (modX $modx, string $activationToken): array {
    $errors = [];
    $success = [];

    if ($activationToken === '') {
        $errors[] = 'Неверная ссылка активации.';
        return ['errors' => $errors, 'success' => $success];
    }

    $prefix = $modx->getOption('table_prefix');
    $stmt = $modx->prepare("SELECT internalKey FROM {$prefix}user_attributes WHERE JSON_UNQUOTE(JSON_EXTRACT(extended, '$.activation_token')) = :token LIMIT 1");
    $stmt->bindValue(':token', $activationToken, PDO::PARAM_STR);
    $stmt->execute();
    $userId = (int)$stmt->fetchColumn();

    if ($userId <= 0) {
        $errors[] = 'Ссылка активации недействительна или уже использована.';
        return ['errors' => $errors, 'success' => $success];
    }

    $user = $modx->getObject('modUser', $userId);
    $profile = $user ? $user->getOne('Profile') : null;

    if (!$user || !$profile) {
        $errors[] = 'Пользователь для активации не найден.';
        return ['errors' => $errors, 'success' => $success];
    }

    $extended = $profile->get('extended') ?: [];
    $expiresAt = (string)($extended['activation_expires_at'] ?? '');

    if ($expiresAt === '' || strtotime($expiresAt) < time()) {
        $errors[] = 'Срок действия ссылки активации истёк. Запросите новую ссылку.';
        return ['errors' => $errors, 'success' => $success];
    }

    if ((int)$user->get('active') === 1) {
        $success[] = 'Аккаунт уже активирован. Можете войти.';
        return ['errors' => $errors, 'success' => $success];
    }

    unset($extended['activation_token'], $extended['activation_sent_at'], $extended['activation_expires_at']);
    $profile->set('extended', $extended);
    $user->set('active', 1);

    if ($profile->save() && $user->save()) {
        $success[] = '✅ Email подтверждён. Аккаунт активирован, теперь можете войти.';
    } else {
        $errors[] = 'Ошибка активации аккаунта. Попробуйте запросить письмо повторно.';
    }

    return ['errors' => $errors, 'success' => $success];
};

// ====================== ПОВТОРНАЯ ОТПРАВКА АКТИВАЦИИ ======================
$resendActivationEmail = static function (modX $modx, string $email) use ($sendActivationEmail): array {
    $genericSuccess = 'Если аккаунт существует и не активирован, письмо будет отправлено.';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['errors' => ['Введите корректный email.'], 'success' => []];
    }

    $prefix = $modx->getOption('table_prefix');
    $stmt = $modx->prepare("SELECT internalKey FROM {$prefix}user_attributes WHERE email = :email LIMIT 1");
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $userId = (int)$stmt->fetchColumn();

    if ($userId <= 0) {
        return ['errors' => [], 'success' => [$genericSuccess]];
    }

    $user = $modx->getObject('modUser', $userId);
    $profile = $user ? $user->getOne('Profile') : null;

    if (!$user || !$profile || (int)$user->get('active') === 1) {
        return ['errors' => [], 'success' => [$genericSuccess]];
    }

    $extended = $profile->get('extended') ?: [];
    $lastSentAt = isset($extended['activation_sent_at']) ? strtotime((string)$extended['activation_sent_at']) : 0;
    if ((time() - $lastSentAt) < 300) {
        return ['errors' => [], 'success' => ['Письмо уже отправлено недавно. Подождите 5 минут.']];
    }

    $activationToken = bin2hex(random_bytes(32));
    $extended['activation_token'] = $activationToken;
    $extended['activation_sent_at'] = date('Y-m-d H:i:s');
    $extended['activation_expires_at'] = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $profile->set('extended', $extended);

    if ($profile->save()) {
        $sent = $sendActivationEmail($modx, $email, (string)$user->get('username'), $activationToken);
        return ['errors' => [], 'success' => [$sent ? 'Ссылка активации повторно отправлена на ваш email.' : 'Не удалось отправить письмо. Попробуйте позже.']];
    }

    return ['errors' => [], 'success' => [$genericSuccess]];
};

// ====================== ВХОД ======================
$loginUser = static function (modX $modx, array $post): array {
    $errors = [];
    $username = trim((string)($post['username'] ?? ''));
    $password = (string)($post['password'] ?? '');
    $rememberme = !empty($post['rememberme']);

    if ($username === '') $errors[] = 'Введите логин';
    if ($password === '') $errors[] = 'Введите пароль';

    if (!empty($errors)) {
        return ['errors' => $errors];
    }

    $user = $modx->getObject('modUser', ['username' => $username]);
    if ($user && (int)$user->get('active') !== 1) {
        $errors[] = 'Аккаунт не активирован. Проверьте email и перейдите по ссылке активации или запросите письмо повторно.';
        return ['errors' => $errors];
    }

    $response = $modx->runProcessor('security/login', [
        'username' => $username,
        'password' => $password,
        'rememberme' => $rememberme,
        'login_context' => 'web'
    ]);

    if (!$response || $response->isError()) {
        $errors[] = 'Неверный логин или пароль';
        return ['errors' => $errors];
    }

    return ['redirect' => $modx->makeUrl(35)];
};

// ====================== ОБРАБОТКА ДЕЙСТВИЙ ======================

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
        $errors = array_merge($errors, $result['errors'] ?? []);
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
                'success' => $result['success'] ?? []
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

// ====================== ВОССТАНОВЛЕНИЕ ПАРОЛЯ — ЗАПРОС ======================
if ($_POST && $mode === 'forgot') {
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        $email = trim((string)($_POST['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Введите корректный email';
        } else {
            $prefix = $modx->getOption('table_prefix');
            $stmt = $modx->prepare("SELECT internalKey FROM {$prefix}user_attributes WHERE email = :email LIMIT 1");
            $stmt->bindValue(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            $userId = (int)$stmt->fetchColumn();

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
                    $profile->save();

                    $resetUrl = $modx->makeUrl($modx->resource->id, '', [
                        'mode' => 'reset',
                        'token' => $token
                    ], 'full');

                    $sent = $sendForgotPasswordEmail($modx, $email, $resetUrl);
                    if ($sent) {
                        $success[] = 'Ссылка для восстановления пароля отправлена на ваш email';
                    } else {
                        $errors[] = 'Не удалось отправить письмо. Попробуйте позже.';
                    }
                }
            }
            // Для безопасности всегда показываем успех, даже если email не найден
            if (empty($success) && empty($errors)) {
                $success[] = 'Если email зарегистрирован, ссылка для восстановления будет отправлена';
            }
        }
    }
}

// ====================== ВОССТАНОВЛЕНИЕ ПАРОЛЯ — УСТАНОВКА ======================
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
                $stmt->bindValue(':token', $token, PDO::PARAM_STR);
                $stmt->execute();
                $userId = (int)$stmt->fetchColumn();

                $foundUser = null;
                if ($userId > 0) {
                    $profile = $modx->getObject('modUserProfile', ['internalKey' => $userId]);
                    $extended = $profile ? ($profile->get('extended') ?: []) : [];
                    $isValidExpiry = isset($extended['reset_expiry']) && strtotime((string)$extended['reset_expiry']) > time();

                    if ($profile && isset($extended['reset_token']) && hash_equals((string)$extended['reset_token'], $token) && $isValidExpiry) {
                        $foundUser = $modx->getObject('modUser', $userId);
                        unset($extended['reset_token'], $extended['reset_expiry']);
                        $profile->set('extended', $extended);
                        $profile->save();
                    }
                }

                if ($foundUser) {
                    $foundUser->set('password', $newPassword);
                    if ($foundUser->save()) {
                        $success[] = 'Пароль успешно изменён! Теперь можете войти.';
                        $mode = 'login';
                    } else {
                        $errors[] = 'Ошибка сохранения пароля';
                    }
                } else {
                    $errors[] = 'Недействительная или просроченная ссылка';
                }
            }
        }
    }
}

// ====================== ФОРМИРОВАНИЕ ВЫВОДА ======================
$output = '<div class="auth-wrapper" style="max-width: 500px; margin: 50px auto; padding: 30px; background: #fff; border-radius: 10px; box-shadow: 0 2px 20px rgba(0,0,0,0.1);">';

foreach ($errors as $error) {
    $output .= '<div class="alert alert-danger">' . htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') . '</div>';
}
foreach ($success as $msg) {
    $output .= '<div class="alert alert-success">' . htmlspecialchars((string)$msg, ENT_QUOTES, 'UTF-8') . '</div>';
}

// Форма установки нового пароля
if ($mode === 'reset' && empty($success)) {
    $output .= '<h4 class="mb-4">Установка нового пароля</h4>';
    $output .= '<form method="POST">';
    $output .= CsrfProtection::getTokenField();
    $output .= '<input type="hidden" name="mode" value="reset">';
    $output .= '<div class="mb-3"><label class="form-label">Новый пароль *</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>';
    $output .= '<div class="mb-3"><label class="form-label">Подтверждение пароля *</label><input type="password" name="confirm_password" class="form-control" required minlength="6"></div>';
    $output .= '<button type="submit" class="btn btn-primary">Сохранить пароль</button>';
    $output .= '</form>';
    $output .= '</div>';
    return $output;
}

// Форма повторной отправки активации
if ($mode === 'resend_activation' && empty($success)) {
    $loginUrl = $modx->makeUrl((int)$modx->resource->id);
    $output .= '<h4 class="mb-4">Повторная отправка активации</h4>';
    $output .= '<p class="text-muted">Введите email, указанный при регистрации</p>';
    $output .= '<form method="POST">';
    $output .= CsrfProtection::getTokenField();
    $output .= '<input type="hidden" name="mode" value="resend_activation">';
    $output .= '<div class="mb-3"><label class="form-label">Email</label>';
    $output .= '<input type="email" name="email" class="form-control" value="' . htmlspecialchars((string)$prefillResendEmail, ENT_QUOTES, 'UTF-8') . '" required></div>';
    $output .= '<button type="submit" class="btn btn-primary">Отправить ссылку активации</button>';
    $output .= ' <a href="' . $loginUrl . '" class="btn btn-link">Вернуться ко входу</a>';
    $output .= '</form>';
    $output .= '</div>';
    return $output;
}

// Форма запроса восстановления пароля
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
    $output .= '</form>';
    $output .= '</div>';
    return $output;
}

// Основные табы: Вход / Регистрация
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
$output .= '</div>';
$output .= '</form>';
$output .= '</div>';

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
$output .= '</form>';
$output .= '</div>';

$output .= '</div></div>';

return $output;
