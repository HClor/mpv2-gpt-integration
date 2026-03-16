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

/**
 * Отправка письма с ссылкой активации
 */
$sendActivationEmail = static function ($modx, string $email, string $username, string $activationToken) {
    $activationUrl = $modx->makeUrl($modx->resource->id, '', [
        'mode' => 'activate',
        'token' => $activationToken
    ], 'full');

    $modx->getService('mail', 'mail.modPHPMailer');
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

    $sent = $modx->mail->send();
    if (!$sent) {
        $modx->log(modX::LOG_LEVEL_ERROR, '[authHandler] Failed to send activation email: ' . $modx->mail->mailer->ErrorInfo);
    }
    $modx->mail->reset();

    return $sent;
};


// АКТИВАЦИЯ АККАУНТА
if ($mode === 'activate') {
    $activationToken = trim($_GET['token'] ?? '');

    if ($activationToken === '') {
        $errors[] = 'Неверная ссылка активации.';
        $mode = 'login';
    } else {
        $profiles = $modx->getCollection('modUserProfile');
        $targetProfile = null;

        foreach ($profiles as $profile) {
            $extended = $profile->get('extended') ?: [];
            if (isset($extended['activation_token']) && hash_equals((string)$extended['activation_token'], $activationToken)) {
                $targetProfile = $profile;
                break;
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
        $password = trim($_POST["password"] ?? "");
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

                if ($response->isError()) {
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
        $password = trim($_POST["password"] ?? "");
        $passwordConfirm = trim($_POST["password_confirm"] ?? "");

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
                $stmt = $modx->prepare("SELECT COUNT(*) FROM {$prefix}user_attributes WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
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

                    if ($user->save()) {
                        // ДОБАВЛЕНИЕ В ГРУППУ LMS Students
                        $studentGroup = $modx->getObject("modUserGroup", ["name" => "LMS Students"]);

                        if ($studentGroup) {
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
                                    $modx->log(modX::LOG_LEVEL_ERROR, "[authHandler] Failed to add user {$user->id} to LMS Students group");
                                }
                            }
                        } else {
                            $modx->log(modX::LOG_LEVEL_ERROR, "[authHandler] LMS Students group not found!");
                        }

                        $mailSent = $sendActivationEmail($modx, $email, $username, $activationToken);
                        if ($mailSent) {
                            $success[] = "✅ Регистрация почти завершена. Мы отправили ссылку активации на ваш email.";
                            $success[] = "Подтвердите email, чтобы активировать аккаунт и войти.";
                        } else {
                            $errors[] = "Аккаунт создан, но письмо активации не отправлено. Запросите ссылку повторно ниже.";
                            $mode = 'resend_activation';
                        }
                    } else {
                        $errors[] = "Ошибка создания пользователя";
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
            $stmt = $modx->prepare("SELECT internalKey FROM {$prefix}user_attributes WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
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
            $stmt = $modx->prepare("SELECT internalKey FROM {$prefix}user_attributes WHERE email = ?");
            $stmt->execute([$email]);
            $userId = $stmt->fetchColumn();

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
                        $modx->getService('mail', 'mail.modPHPMailer');
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

                        if ($modx->mail->send()) {
                            $success[] = "Ссылка для восстановления пароля отправлена на ваш email";
                        } else {
                            $errors[] = "Ошибка отправки email. Обратитесь к администратору.";
                            $modx->log(modX::LOG_LEVEL_ERROR, "[authHandler] Failed to send reset email: " . $modx->mail->mailer->ErrorInfo);
                        }
                        $modx->mail->reset();
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
            $newPassword = trim($_POST["new_password"] ?? "");
            $confirmPassword = trim($_POST["confirm_password"] ?? "");

            if (empty($newPassword)) {
                $errors[] = "Введите новый пароль";
            } elseif (strlen($newPassword) < 6) {
                $errors[] = "Пароль должен быть минимум 6 символов";
            } elseif ($newPassword !== $confirmPassword) {
                $errors[] = "Пароли не совпадают";
            } else {
                // Ищем пользователя по токену
                $prefix = $modx->getOption('table_prefix');
                $profiles = $modx->getCollection('modUserProfile');
                $foundUser = null;

                foreach ($profiles as $profile) {
                    $extended = $profile->get('extended') ?: [];
                    if (isset($extended['reset_token']) && $extended['reset_token'] === $token) {
                        if (isset($extended['reset_expiry']) && strtotime($extended['reset_expiry']) > time()) {
                            $foundUser = $modx->getObject('modUser', $profile->get('internalKey'));
                            // Очищаем токен
                            unset($extended['reset_token']);
                            unset($extended['reset_expiry']);
                            $profile->set('extended', $extended);
                            $profile->save();
                            break;
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
    $output .= '<input type="email" name="email" class="form-control" required>';
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
