<?php
/* TS AUTH HANDLER v2.2 FINAL */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ВЫХОД
if (isset($_POST["login_logout"])) {
    $modx->user->endSession();
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
    $output .= "<input type=\"hidden\" name=\"login_logout\" value=\"1\">";
    $output .= "<button type=\"submit\" class=\"btn btn-danger\">Выйти</button>";
    $output .= "</form>";
    $output .= "</div>";
    
    return $output;
}

$errors = [];
$mode = $_POST["mode"] ?? "login";

// ВХОД
if ($_POST && $mode === "login") {
    $username = trim($_POST["username"] ?? "");
    $password = trim($_POST["password"] ?? "");
    $rememberme = !empty($_POST["rememberme"]);
    
    if (empty($username)) $errors[] = "Введите логин";
    if (empty($password)) $errors[] = "Введите пароль";
    
    if (empty($errors)) {
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

// РЕГИСТРАЦИЯ
if ($_POST && $mode === "register") {
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
            $stmt = $modx->prepare("SELECT COUNT(*) FROM modx_user_attributes WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Email уже используется";
            } else {
                $user = $modx->newObject("modUser");
                $user->set("username", $username);
                $user->set("password", $password);
                $user->set("active", 1);
                
                $profile = $modx->newObject("modUserProfile");
                $profile->set("email", $email);
                $profile->set("fullname", $username);
                $profile->set("blocked", 0);
                
                $user->addOne($profile);
                

                if ($user->save()) {
                    // ДОБАВЛЕНИЕ В ГРУППУ LMS Students
                    $studentGroup = $modx->getObject("modUserGroup", ["name" => "LMS Students"]);
                    
                    if ($studentGroup) {
                        // Проверяем, не состоит ли уже в группе (на всякий случай)
                        $existingMembership = $modx->getObject("modUserGroupMember", [
                            "user_group" => $studentGroup->id,
                            "member" => $user->id
                        ]);
                        
                        if (!$existingMembership) {
                            $membership = $modx->newObject("modUserGroupMember");
                            $membership->set("user_group", $studentGroup->id);
                            $membership->set("member", $user->id);
                            $membership->set("role", 1); // 1 = Member
                            $membership->set("rank", 0);
                            
                            if ($membership->save()) {
                                $modx->log(modX::LOG_LEVEL_INFO, "[authHandler] User {$user->id} added to LMS Students group");
                            } else {
                                $modx->log(modX::LOG_LEVEL_ERROR, "[authHandler] Failed to add user {$user->id} to LMS Students group");
                            }
                        }
                    } else {
                        $modx->log(modX::LOG_LEVEL_ERROR, "[authHandler] LMS Students group not found!");
                    }
                    
                    // ВАЖНО: Автоматический вход после регистрации (опционально)
                    // Если хотите чтобы пользователь сразу входил после регистрации:
                    /*
                    $response = $modx->runProcessor("security/login", [
                        "username" => $username,
                        "password" => $password,
                        "rememberme" => false,
                        "login_context" => "web"
                    ]);
                    
                    if (!$response->isError()) {
                        $testsUrl = $modx->makeUrl(35);
                        $modx->sendRedirect($testsUrl);
                        exit;
                    }
                    */
                    
                    $errors[] = "✅ Регистрация успешна! Теперь можете войти.";
                    $mode = "login";
                } else {
                    $errors[] = "Ошибка создания пользователя";
                }


            }
        }
    }
}

$output = "";

if (!empty($errors)) {
    $output .= "<div class=\"alert alert-danger\"><ul class=\"mb-0\">";
    foreach ($errors as $error) {
        $output .= "<li>" . htmlspecialchars($error) . "</li>";
    }
    $output .= "</ul></div>";
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

$output .= "<button type=\"submit\" class=\"btn btn-primary\">Войти</button>";
$output .= "</form>";
$output .= "</div>";

$output .= "<div class=\"tab-pane fade " . ($activeTab === "register" ? "show active" : "") . "\" id=\"register-tab\">";
$output .= "<form method=\"POST\">";
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
$output .= "</form>";
$output .= "</div>";

$output .= "</div>";

return $output;