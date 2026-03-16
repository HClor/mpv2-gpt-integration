<?php
/**
 * Сниппет: authHandler - Обработчик авторизации
 * Вызывается из: MODX ресурсов (страница входа/регистрации)
 * Назначение: Обрабатывает вход, регистрацию, выход пользователя
 *
 * @package TestSystem
 * @version 2.3
 */

// Подключаем bootstrap для CSRF защиты
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ВЫХОД
if (isset($_POST["login_logout"])) {
    // CSRF Protection для выхода
    if (!CsrfProtection::validateRequest($_POST)) {
        // Для logout не блокируем выход полностью: токен может быть устаревшим из кешированной страницы
        $modx->log(modX::LOG_LEVEL_WARN, '[authHandler] Logout with invalid CSRF token, proceeding with web context logout');
    }
    // Завершаем только сессию в контексте web, не затрагивая mgr (админку)
    if ($modx->user->hasSessionContext('web')) {
        $modx->user->removeSessionContext('web');
    }
    $modx->sendRedirect($modx->makeUrl($modx->getOption("site_start")));
    return;
}

// Если уже авторизован
if ($modx->user->hasSessionContext("web") && $modx->user->id > 0) {
    $testsUrl = $modx->makeUrl(35);
    
    $output = "<div class=\"alert alert-success\">";
    $output .= "<h4>Вы авторизованы: " . htmlspecialchars($modx->user->username) . "</h4>";
    $output .= "<p><a href=\"" . $testsUrl . "\" class=\"btn btn-primary\">Перейти к тестам</a></p>";
    $output .= "<form method=\"post\">";
    $output .= CsrfProtection::getTokenField(); // CSRF Protection
    $output .= "<input type=\"hidden\" name=\"login_logout\" value=\"1\">";
    $output .= "<button type=\"submit\" class=\"btn btn-danger\">Выйти</button>";
    $output .= "</form>";
    $output .= "</div>";
    
    return $output;
}

$errors = [];
$success = [];
$mode = $_GET["mode"] ?? ($_POST["mode"] ?? "login");
$prefillResendEmail = "";

/**
 * Настройка и безопасная отправка email
 */
$restoreDbConnection = static function ($modx, string $context): void {
    try {
        $pingStmt = $modx->query('SELECT 1');
        if ($pingStmt !== false) {
            return;
        }
    } catch (Throwable $e) {
        $modx->log(modX::LOG_LEVEL_WARN, '[authHandler] DB ping failed after ' . $context . ': ' . $e->getMessage());
    }

    if (isset($modx->connection) && is_object($modx->connection) && property_exists($modx->connection, 'pdo')) {
        $modx->connection->pdo = null;
    }
    if (property_exists($modx, 'pdo')) {
        $modx->pdo = null;
    }

    if (!$modx->connect()) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Failed to reconnect DB after ' . $context);
    }
};

$prepareMailTransport = static function ($modx, string $context) {
    $mailService = $modx->getService('mail', 'mail.modPHPMailer');
    if (!$mailService || !isset($modx->mail)) {
        $modx->log(modX::LOG_LEVEL_ERROR, "[authHandler] Mail service unavailable ({$context})");
        return false;
    }

    $mailer = $modx->mail->mailer ?? null;
    if ($mailer) {
        // Не даем SMTP-запросам зависать до 504 на фронте.
        if (property_exists($mailer, 'Timeout')) {
            $mailer->Timeout = 8;
        }
        if (property_exists($mailer, 'SMTPTimeout')) {
            $mailer->SMTPTimeout = 8;
        }
        if (property_exists($mailer, 'Timelimit')) {
            $mailer->Timelimit = 10;
        }
        if (property_exists($mailer, 'SMTPKeepAlive')) {
            $mailer->SMTPKeepAlive = false;
        }
        if (property_exists($mailer, 'SMTPAutoTLS')) {
            $mailer->SMTPAutoTLS = true;
        }
    }

    return true;
};

$sendMailSafely = static function ($modx, string $context) use ($restoreDbConnection): bool {
    $sent = false;
    try {
        $sent = $modx->mail->send();
    } catch (Throwable $e) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Mail send exception (' . $context . '): ' . $e->getMessage());
    }

    if (!$sent) {
        $errorInfo = isset($modx->mail->mailer->ErrorInfo) ? (string)$modx->mail->mailer->ErrorInfo : 'unknown error';
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Failed to send email (' . $context . '): ' . $errorInfo);
    }

    $modx->mail->reset();
    $restoreDbConnection($modx, $context);

    return $sent;
};

$sendActivationEmail = static function ($modx, string $email, string $username, string $activationToken) use ($prepareMailTransport, $sendMailSafely) {
    $activationUrl = $modx->makeUrl($modx->resource->id, '', [
        'mode' => 'activate',
        'token' => $activationToken
    ], 'full');

    if (!$prepareMailTransport($modx, 'activation')) {
        return false;
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(30);
    }

    $modx->mail->set(modMail::MAIL_BODY, "
        <h2>Подтверждение регистрации</h2>
        <p>Здравствуйте, " . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . "!</p>
        <p>Для завершения регистрации подтвердите email по ссылке:</p>
        <p><a href='" . htmlspecialchars($activationUrl, ENT_QUOTES, 'UTF-8') . "'>Активировать аккаунт</a></p>
        <p>Если вы не регистрировались, просто проигнорируйте это письмо.</p>
    ");
    $modx->mail->set(modMail::MAIL_FROM, $modx->getOption('emailsender'));
    $modx->mail->set(modMail::MAIL_FROM_NAME, $modx->getOption('site_name'));
    $modx->mail->set(modMail::MAIL_SUBJECT, 'Активация аккаунта - ' . $modx->getOption('site_name'));
    $modx->mail->address('to', $email);
    $modx->mail->setHTML(true);

    return $sendMailSafely($modx, 'activation');
};


// АКТИВАЦИЯ АККАУНТА
if ($mode === 'activate') {
    $activationToken = trim($_GET['token'] ?? '');

    if ($activationToken === '') {
        $errors[] = 'Неверная ссылка активации.';
        $mode = 'login';
    } else {
        $prefix = $modx->getOption('table_prefix');
        $stmt = $modx->prepare("SELECT internalKey FROM {$prefix}user_attributes WHERE JSON_UNQUOTE(JSON_EXTRACT(extended, '$.activation_token')) = :token LIMIT 1");
        $targetProfile = null;

        if ($stmt === false) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Activation token prepare failed');
            $errors[] = 'Временная ошибка базы данных. Попробуйте позже.';
        } else {
            $stmt->bindValue(':token', $activationToken, PDO::PARAM_STR);
            if (!$stmt->execute()) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Activation token execute failed: ' . implode(' | ', $stmt->errorInfo()));
                $errors[] = 'Временная ошибка базы данных. Попробуйте позже.';
            } else {
                $userId = (int)$stmt->fetchColumn();
                if ($userId > 0) {
                    $targetProfile = $modx->getObject('modUserProfile', ['internalKey' => $userId]);
                }
            }
        }

        if (!$targetProfile) {
            $errors[] = 'Ссылка активации недействительна или уже использована.';
        } else {
            $user = $modx->getObject('modUser', $targetProfile->get('internalKey'));
            if (!$user) {
                $errors[] = 'Пользователь для активации не найден.';
            } elseif ((int)$user->get('active') === 1) {
                $success[] = 'Аккаунт уже активирован. Можете войти.';
            } else {
                $extended = $targetProfile->get('extended') ?: [];
                unset($extended['activation_token']);
                unset($extended['activation_sent_at']);
                $targetProfile->set('extended', $extended);
                $user->set('active', 1);

                if ($targetProfile->save() && $user->save()) {
                    $success[] = '✅ Email подтверждён. Аккаунт активирован, теперь можете войти.';
                } else {
                    $errors[] = 'Ошибка активации аккаунта. Попробуйте запросить письмо повторно.';
                }
            }
        }

        $mode = 'login';
    }
}

// ВХОД
if ($_POST && $mode === "login") {
    // CSRF Protection
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = "Ошибка безопасности. Обновите страницу и попробуйте снова.";
    } else {
        $username = trim($_POST["username"] ?? "");
        $password = $_POST["password"] ?? "";
        $rememberme = !empty($_POST["rememberme"]);

        if (empty($username)) $errors[] = "Введите логин";
        if (empty($password)) $errors[] = "Введите пароль";

        if (empty($errors)) {
            $user = $modx->getObject('modUser', ['username' => $username]);
            if ($user && (int)$user->get('active') !== 1) {
                $errors[] = 'Аккаунт не активирован. Проверьте email и перейдите по ссылке активации или запросите письмо повторно по ссылке под формой входа.';
            } else {
                $response = $modx->runProcessor("security/login", [
                    "username" => $username,
                    "password" => $password,
                    "rememberme" => $rememberme,
                    "login_context" => "web"
                ]);

                if (!$response || $response->isError()) {
                    $errors[] = "Неверный логин или пароль";
                } else {
                    $testsUrl = $modx->makeUrl(35);
                    $modx->sendRedirect($testsUrl);
                    exit;
                }
            }
        }
    }
}

// РЕГИСТРАЦИЯ
if ($_POST && $mode === "register") {
    // CSRF Protection
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = "Ошибка безопасности. Обновите страницу и попробуйте снова.";
    } else {
        $username = trim($_POST["username"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        $passwordConfirm = $_POST["password_confirm"] ?? "";

        if (empty($username)) $errors[] = "Введите логин";
        if (empty($email)) $errors[] = "Введите email";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Неверный формат email";
        if (empty($password)) $errors[] = "Введите пароль";
        if (strlen($password) < 6) $errors[] = "Пароль минимум 6 символов";
        if ($password !== $passwordConfirm) $errors[] = "Пароли не совпадают";

        if (empty($errors)) {
            if ($modx->getObject("modUser", ["username" => $username])) {
                $errors[] = "Логин уже занят";
            } else {
                $prefix = $modx->getOption('table_prefix');
                $stmt = $modx->prepare("SELECT COUNT(*) FROM {$prefix}user_attributes WHERE email = :email");
                if ($stmt === false) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Email uniqueness prepare failed');
                    $errors[] = 'Временная ошибка базы данных. Попробуйте позже.';
                } else {
                    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
                    if (!$stmt->execute()) {
                        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Email uniqueness execute failed: ' . implode(' | ', $stmt->errorInfo()));
                        $errors[] = 'Временная ошибка базы данных. Попробуйте позже.';
                    } elseif ((int)$stmt->fetchColumn() > 0) {
                        $errors[] = "Email уже используется";
                    } else {
                        $user = $modx->newObject("modUser");
                        $user->set("username", $username);
                        $user->set("password", $password);
                        $user->set("active", 0);

                        $activationToken = bin2hex(random_bytes(32));

                        $profile = $modx->newObject("modUserProfile");
                        $profile->set("email", $email);
                        $profile->set("fullname", $username);
                        $profile->set("blocked", 0);
                        $profile->set('extended', [
                            'activation_token' => $activationToken,
                            'activation_sent_at' => date('Y-m-d H:i:s')
                        ]);

                        $user->addOne($profile);

                        $created = false;
                        $modx->beginTransaction();
                        try {
                            if (!$user->save()) {
                                throw new RuntimeException('user save failed');
                            }

                            $studentGroup = $modx->getObject("modUserGroup", ["name" => "LMS Students"]);
                            if (!$studentGroup) {
                                throw new RuntimeException('LMS Students group not found');
                            }

                            $existingMembership = $modx->getObject("modUserGroupMember", [
                                "user_group" => $studentGroup->id,
                                "member" => $user->id
                            ]);

                            if (!$existingMembership) {
                                $membership = $modx->newObject("modUserGroupMember");
                                $membership->set("user_group", $studentGroup->id);
                                $membership->set("member", $user->id);
                                $membership->set("role", 1);
                                $membership->set("rank", 0);
                                if (!$membership->save()) {
                                    throw new RuntimeException('membership save failed');
                                }
                            }

                            $modx->commit();
                            $created = true;
                        } catch (Throwable $e) {
                            $modx->rollBack();
                            $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Registration transaction failed: ' . $e->getMessage());
                        }

                        if ($created) {
                            $mailSent = $sendActivationEmail($modx, $email, $username, $activationToken);

                            if ($mailSent) {
                                $success[] = "✅ Аккаунт создан. Ссылка для активации отправлена на ваш email.";
                            } else {
                                $errors[] = "Аккаунт создан, но письмо активации не отправлено. Пожалуйста, свяжитесь с администратором сайта.";
                            }
                        } else {
                            $errors[] = "Ошибка создания пользователя";
                        }
                    }
                }
            }
        }
    }
}

// ПОВТОРНАЯ ОТПРАВКА АКТИВАЦИИ
if ($_POST && $mode === 'resend_activation') {
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = "Ошибка безопасности. Обновите страницу и попробуйте снова.";
    } else {
        $email = trim($_POST['email'] ?? '');

        if (empty($email)) {
            $errors[] = 'Введите email';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Неверный формат email';
        } else {
            $prefix = $modx->getOption('table_prefix');
            $stmt = $modx->prepare("SELECT internalKey FROM {$prefix}user_attributes WHERE email = :email LIMIT 1");
            if ($stmt === false) {
                $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Resend activation prepare failed');
                $errors[] = 'Временная ошибка базы данных. Попробуйте позже.';
            } else {
                $stmt->bindValue(':email', $email, PDO::PARAM_STR);
                if (!$stmt->execute()) {
                    $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Resend activation execute failed: ' . implode(' | ', $stmt->errorInfo()));
                    $errors[] = 'Временная ошибка базы данных. Попробуйте позже.';
                } else {
                    $userId = (int)$stmt->fetchColumn();

                    if ($userId > 0) {
                $user = $modx->getObject('modUser', $userId);
                $profile = $user ? $user->getOne('Profile') : null;

                if ($user && $profile && (int)$user->get('active') !== 1) {
                    $extended = $profile->get('extended') ?: [];
                    $activationToken = bin2hex(random_bytes(32));
                    $extended['activation_token'] = $activationToken;
                    $extended['activation_sent_at'] = date('Y-m-d H:i:s');
                    $profile->set('extended', $extended);

                    if ($profile->save()) {
                        $mailSent = $sendActivationEmail($modx, $email, $user->get('username'), $activationToken);
                        if ($mailSent) {
                            $success[] = 'Ссылка активации повторно отправлена на ваш email.';
                        } else {
                            $errors[] = 'Не удалось отправить письмо активации. Попробуйте позже.';
                        }
                    } else {
                        $errors[] = 'Не удалось обновить токен активации. Попробуйте позже.';
                    }
                } else {
                    $success[] = 'Если аккаунт существует и не активирован, письмо будет отправлено.';
                }
                    } else {
                        $success[] = 'Если аккаунт существует и не активирован, письмо будет отправлено.';
                    }
                }
            }
        }
    }
}

// ВОССТАНОВЛЕНИЕ ПАРОЛЯ - запрос
if ($_POST && $mode === "forgot") {
    if (!CsrfProtection::validateRequest($_POST)) {
        $errors[] = "Ошибка безопасности. Обновите страницу и попробуйте снова.";
    } else {
        $email = trim($_POST["email"] ?? "");

        if (empty($email)) {
            $errors[] = "Введите email";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Неверный формат email";
        } else {
            // Ищем пользователя по email
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

            if ($userId) {
                $user = $modx->getObject('modUser', $userId);
                if ($user) {
                    // Генерируем токен
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Сохраняем токен в extended поле профиля
                    $profile = $user->getOne('Profile');
                    if ($profile) {
                        $extended = $profile->get('extended') ?: [];
                        $extended['reset_token'] = $token;
                        $extended['reset_expiry'] = $expiry;
                        $profile->set('extended', $extended);
                        $profile->save();

                        // Формируем ссылку для сброса
                        $resetUrl = $modx->makeUrl($modx->resource->id, '', [
                            'mode' => 'reset',
                            'token' => $token
                        ], 'full');

                        // Отправляем email
                        if (!$prepareMailTransport($modx, 'forgot_password')) {
                            $errors[] = 'Ошибка отправки email. Почтовый сервис временно недоступен.';
                        } else {
                            $modx->mail->set(modMail::MAIL_FROM, $modx->getOption('emailsender'));
                            $modx->mail->set(modMail::MAIL_FROM_NAME, $modx->getOption('site_name'));
                            $modx->mail->set(modMail::MAIL_SUBJECT, 'Восстановление пароля');
                            $modx->mail->set(modMail::MAIL_BODY, "
                                <h3>Восстановление пароля</h3>
                                <p>Вы запросили восстановление пароля на сайте {$modx->getOption('site_name')}.</p>
                                <p><a href='{$resetUrl}'>Нажмите здесь для установки нового пароля</a></p>
                                <p>Ссылка действительна 1 час.</p>
                                <p>Если вы не запрашивали восстановление пароля, проигнорируйте это письмо.</p>
                            ");
                            $modx->mail->address('to', $email);
                            $modx->mail->setHTML(true);

                            if ($sendMailSafely($modx, 'forgot_password')) {
                                $success[] = "Ссылка для восстановления пароля отправлена на ваш email";
                            } else {
                                $errors[] = "Ошибка отправки email. Обратитесь к администратору.";
                            }
                        }
                    }
                }
            } else {
                // Не сообщаем что email не найден (безопасность)
                $success[] = "Если email зарегистрирован, ссылка для восстановления будет отправлена";
            }
        }
    }
}

// ВОССТАНОВЛЕНИЕ ПАРОЛЯ - установка нового пароля
if ($mode === "reset") {
    $token = $_GET["token"] ?? "";

    if ($_POST && isset($_POST["new_password"])) {
        if (!CsrfProtection::validateRequest($_POST)) {
            $errors[] = "Ошибка безопасности. Обновите страницу и попробуйте снова.";
        } else {
            $newPassword = $_POST["new_password"] ?? "";
            $confirmPassword = $_POST["confirm_password"] ?? "";

            if (empty($newPassword)) {
                $errors[] = "Введите новый пароль";
            } elseif (strlen($newPassword) < 6) {
                $errors[] = "Пароль должен быть минимум 6 символов";
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = "Пароли не совпадают";
            } else {
                // Ищем пользователя по токену
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
                        $success[] = "Пароль успешно изменён! Теперь можете войти.";
                        $mode = "login";
                    } else {
                        $errors[] = "Ошибка сохранения пароля";
                    }
                } else {
                    $errors[] = "Недействительная или просроченная ссылка";
                }
            }
        }
    }
}

$output = "";

if (!empty($success)) {
    $output .= "<div class=\"alert alert-success\"><ul class=\"mb-0\">";
    foreach ($success as $msg) {
        $output .= "<li>" . htmlspecialchars($msg) . "</li>";
    }
    $output .= "</ul></div>";
}

if (!empty($errors)) {
    $output .= "<div class=\"alert alert-danger\"><ul class=\"mb-0\">";
    foreach ($errors as $error) {
        $output .= "<li>" . htmlspecialchars($error) . "</li>";
    }
    $output .= "</ul></div>";
}

// Форма сброса пароля (по токену)
if ($mode === "reset" && empty($success)) {
    $token = htmlspecialchars($_GET["token"] ?? "");
    $output .= "<h4 class=\"mb-4\">Установка нового пароля</h4>";
    $output .= "<form method=\"POST\">";
    $output .= CsrfProtection::getTokenField();
    $output .= "<input type=\"hidden\" name=\"mode\" value=\"reset\">";

    $output .= "<div class=\"mb-3\">";
    $output .= "<label class=\"form-label\">Новый пароль *</label>";
    $output .= "<input type=\"password\" name=\"new_password\" class=\"form-control\" required minlength=\"6\">";
    $output .= "</div>";

    $output .= "<div class=\"mb-3\">";
    $output .= "<label class=\"form-label\">Подтверждение пароля *</label>";
    $output .= "<input type=\"password\" name=\"confirm_password\" class=\"form-control\" required minlength=\"6\">";
    $output .= "</div>";

    $output .= "<button type=\"submit\" class=\"btn btn-primary\">Сохранить пароль</button>";
    $output .= "</form>";
    return $output;
}

// Форма повторной отправки ссылки активации
if ($mode === 'resend_activation' && empty($success)) {
    $loginUrl = $modx->makeUrl($modx->resource->id);
    $output .= '<h4 class="mb-4">Повторная отправка активации</h4>';
    $output .= '<p class="text-muted">Введите email, указанный при регистрации</p>';
    $output .= '<form method="POST">';
    $output .= CsrfProtection::getTokenField();
    $output .= '<input type="hidden" name="mode" value="resend_activation">';

    $output .= '<div class="mb-3">';
    $output .= '<label class="form-label">Email</label>';
    $resendEmailValue = $_POST['email'] ?? $prefillResendEmail;
    $output .= '<input type="email" name="email" class="form-control" value="' . htmlspecialchars((string)$resendEmailValue, ENT_QUOTES, 'UTF-8') . '" required>';
    $output .= '</div>';

    $output .= '<button type="submit" class="btn btn-primary">Отправить ссылку активации</button>';
    $output .= ' <a href="' . $loginUrl . '" class="btn btn-link">Вернуться ко входу</a>';
    $output .= '</form>';
    return $output;
}

// Форма запроса восстановления пароля
if ($mode === "forgot" && empty($success)) {
    $loginUrl = $modx->makeUrl($modx->resource->id);
    $output .= "<h4 class=\"mb-4\">Восстановление пароля</h4>";
    $output .= "<p class=\"text-muted\">Введите email, указанный при регистрации</p>";
    $output .= "<form method=\"POST\">";
    $output .= CsrfProtection::getTokenField();
    $output .= "<input type=\"hidden\" name=\"mode\" value=\"forgot\">";

    $output .= "<div class=\"mb-3\">";
    $output .= "<label class=\"form-label\">Email</label>";
    $output .= "<input type=\"email\" name=\"email\" class=\"form-control\" required>";
    $output .= "</div>";

    $output .= "<button type=\"submit\" class=\"btn btn-primary\">Отправить ссылку</button>";
    $output .= " <a href=\"" . $loginUrl . "\" class=\"btn btn-link\">Вернуться к входу</a>";
    $output .= "</form>";
    return $output;
}

$activeTab = $mode === "register" ? "register" : "login";

$output .= "<ul class=\"nav nav-tabs mb-4\">";
$output .= "<li class=\"nav-item\">";
$output .= "<button class=\"nav-link " . ($activeTab === "login" ? "active" : "") . "\" data-bs-toggle=\"tab\" data-bs-target=\"#login-tab\">Вход</button>";
$output .= "</li>";
$output .= "<li class=\"nav-item\">";
$output .= "<button class=\"nav-link " . ($activeTab === "register" ? "active" : "") . "\" data-bs-toggle=\"tab\" data-bs-target=\"#register-tab\">Регистрация</button>";
$output .= "</li>";
$output .= "</ul>";

$output .= "<div class=\"tab-content\">";

$output .= "<div class=\"tab-pane fade " . ($activeTab === "login" ? "show active" : "") . "\" id=\"login-tab\">";
$output .= "<form method=\"POST\">";
$output .= CsrfProtection::getTokenField(); // CSRF Protection
$output .= "<input type=\"hidden\" name=\"mode\" value=\"login\">";

$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\">Логин</label>";
$output .= "<input type=\"text\" name=\"username\" class=\"form-control\" value=\"" . htmlspecialchars($_POST["username"] ?? "") . "\" required>";
$output .= "</div>";

$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\">Пароль</label>";
$output .= "<input type=\"password\" name=\"password\" class=\"form-control\" required>";
$output .= "</div>";

$output .= "<div class=\"form-check mb-3\">";
$output .= "<input class=\"form-check-input\" type=\"checkbox\" name=\"rememberme\" id=\"rememberme\">";
$output .= "<label class=\"form-check-label\" for=\"rememberme\">Запомнить меня</label>";
$output .= "</div>";

$output .= "<div class=\"d-flex justify-content-between align-items-center\">";
$output .= "<button type=\"submit\" class=\"btn btn-primary\">Войти</button>";
$forgotUrl = $modx->makeUrl($modx->resource->id, '', ['mode' => 'forgot']);
$output .= "<a href=\"" . $forgotUrl . "\" class=\"text-muted small\">Забыли пароль?</a>";
$resendUrl = $modx->makeUrl($modx->resource->id, '', ['mode' => 'resend_activation']);
$output .= "<a href=\"" . $resendUrl . "\" class=\"text-muted small ms-3\">Не пришло письмо активации?</a>";
$output .= "</div>";
$output .= "</form>";
$output .= "</div>";

$output .= "<div class=\"tab-pane fade " . ($activeTab === "register" ? "show active" : "") . "\" id=\"register-tab\">";
$output .= "<form method=\"POST\">";
$output .= CsrfProtection::getTokenField(); // CSRF Protection
$output .= "<input type=\"hidden\" name=\"mode\" value=\"register\">";

$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\">Логин *</label>";
$output .= "<input type=\"text\" name=\"username\" class=\"form-control\" required>";
$output .= "</div>";

$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\">Email *</label>";
$output .= "<input type=\"email\" name=\"email\" class=\"form-control\" required>";
$output .= "</div>";

$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\">Пароль * (минимум 6 символов)</label>";
$output .= "<input type=\"password\" name=\"password\" class=\"form-control\" required>";
$output .= "</div>";

$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\">Подтверждение пароля *</label>";
$output .= "<input type=\"password\" name=\"password_confirm\" class=\"form-control\" required>";
$output .= "</div>";

$output .= "<button type=\"submit\" class=\"btn btn-success\">Зарегистрироваться</button>";
$output .= "<p class=\"text-muted small mt-3 mb-0\">После регистрации нужно подтвердить email по ссылке из письма.</p>";
$output .= "</form>";
$output .= "</div>";

$output .= "</div>";

return $output;
