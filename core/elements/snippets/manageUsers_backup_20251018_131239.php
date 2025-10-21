<?php
/* TS MANAGE USERS v2.1 ROLE PRIORITY FIX */
try {
    if (!$modx->user->hasSessionContext("web")) {
        return '<div class="alert alert-warning">Войдите для доступа</div>';
    }

    $userId = $modx->user->id;
    $isAdmin = $modx->user->isMember("LMS Admins") || $userId == 1;

    if (!$isAdmin) {
        return '<div class="alert alert-danger">Доступ запрещён. Только для администраторов.</div>';
    }

    $errors = [];
    $success = "";

    // ФУНКЦИЯ: определить роль пользователя по приоритету
    function getUserRole($modx, $uid) {
        $q = $modx->prepare("
            SELECT mgn.name 
            FROM {$modx->getTableName('modUserGroupMember')} mg
            JOIN {$modx->getTableName('modUserGroup')} mgn ON mgn.id = mg.user_group
            WHERE mg.member = :uid AND mgn.name LIKE 'LMS %'
        ");
        $q->execute([':uid' => $uid]);
        $groups = $q->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('LMS Admins', $groups)) return 'admin';
        if (in_array('LMS Experts', $groups)) return 'expert';
        if (in_array('LMS Students', $groups)) return 'student';
        return null;
    }

    // ИЗМЕНЕНИЕ РОЛИ
    if ($_POST && isset($_POST["change_role"])) {
        $targetUserId = (int)($_POST["user_id"] ?? 0);
        $newRole = $_POST["new_role"] ?? "";

        if (!$targetUserId || !in_array($newRole, ["student", "expert", "admin"])) {
            $errors[] = "Некорректные данные";
        } else {
            $groupMap = [
                "student" => "LMS Students",
                "expert" => "LMS Experts",
                "admin" => "LMS Admins"
            ];

            $targetUser = $modx->getObject("modUser", $targetUserId);
            if (!$targetUser) {
                $errors[] = "Пользователь не найден";
            } else {
                // Удаляем из всех LMS групп
                foreach ($groupMap as $gname) {
                    $grp = $modx->getObject("modUserGroup", ["name" => $gname]);
                    if ($grp) {
                        $targetUser->leaveGroup($grp->id);
                    }
                }

                // Добавляем в новую группу
                $newGroup = $modx->getObject("modUserGroup", ["name" => $groupMap[$newRole]]);
                if ($newGroup) {
                    $targetUser->joinGroup($newGroup->id);
                    $success = "Роль изменена на: " . $groupMap[$newRole];
                } else {
                    $errors[] = "Группа не найдена";
                }
            }
        }
    }

    // БЛОКИРОВКА
    if ($_POST && isset($_POST["toggle_block"])) {
        $targetUserId = (int)($_POST["user_id"] ?? 0);
        if ($targetUserId && $targetUserId != 1) {
            $targetUser = $modx->getObject("modUser", $targetUserId);
            if ($targetUser) {
                $profile = $targetUser->getOne("Profile");
                $isBlocked = (bool)$profile->get("blocked");
                $profile->set("blocked", !$isBlocked);
                $profile->save();
                $success = $isBlocked ? "Пользователь разблокирован" : "Пользователь заблокирован";
            }
        }
    }

    // СОЗДАНИЕ ПОЛЬЗОВАТЕЛЯ
    if ($_POST && isset($_POST["create_user"])) {
        $username = trim($_POST["username"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = trim($_POST["password"] ?? "");
        $role = $_POST["role"] ?? "student";

        if (!$username || !$email || !$password) {
            $errors[] = "Заполните все поля";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Некорректный email";
        } else {
            $existingUser = $modx->getObject("modUser", ["username" => $username]);
            if ($existingUser) {
                $errors[] = "Логин уже занят";
            } else {
                $newUser = $modx->newObject("modUser");
                $newUser->set("username", $username);
                $newUser->set("active", 1);

                $profile = $modx->newObject("modUserProfile");
                $profile->set("email", $email);
                $profile->set("blocked", 0);
                $profile->set("internalKey", 0);
                $newUser->addOne($profile);

                if ($newUser->save()) {
                    $newUser->set("password", $password);
                    $newUser->save();

                    $groupMap = [
                        "student" => "LMS Students",
                        "expert" => "LMS Experts",
                        "admin" => "LMS Admins"
                    ];
                    $grp = $modx->getObject("modUserGroup", ["name" => $groupMap[$role]]);
                    if ($grp) {
                        $newUser->joinGroup($grp->id);
                    }

                    $success = "Пользователь создан: $username";
                } else {
                    $errors[] = "Ошибка создания";
                }
            }
        }
    }

    // ВЫВОД
    $output = '<div class="container-fluid py-4">';
    $output .= '<h2 class="mb-4">Управление пользователями</h2>';

    if ($errors) {
        foreach ($errors as $err) {
            $output .= '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>';
        }
    }
    if ($success) {
        $output .= '<div class="alert alert-success">' . htmlspecialchars($success) . '</div>';
    }

    // Кнопка создания
    $output .= '<button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#createUserModal">+ Создать пользователя</button>';

    // Таблица пользователей
    $users = $modx->getCollection("modUser", [], true);
    $output .= '<table class="table table-hover mb-0"><thead class="table-light"><tr>';
    $output .= '<th style="width: 50px;">ID</th><th>Логин</th><th>Email</th><th>Роль в LMS</th>';
    $output .= '<th style="width: 100px;">Статус</th><th style="width: 180px;">Действия</th></tr></thead><tbody>';

    foreach ($users as $u) {
        $uid = $u->id;
        $profile = $u->getOne("Profile");
        $email = $profile ? $profile->get("email") : "";
        $blocked = $profile ? (bool)$profile->get("blocked") : false;
        
        $role = getUserRole($modx, $uid);
        $roleBadge = $role ? [
            'student' => '<span class="badge bg-info">Студент</span>',
            'expert' => '<span class="badge bg-primary">Эксперт</span>',
            'admin' => '<span class="badge bg-danger">Админ</span>'
        ][$role] : '<span class="badge bg-secondary">Нет роли</span>';

        $statusBadge = $blocked 
            ? '<span class="badge bg-danger">Заблокирован</span>' 
            : '<span class="badge bg-success">Активен</span>';

        $output .= "<tr><td>$uid</td><td><strong>{$u->username}</strong></td><td>$email</td>";
        $output .= "<td>$roleBadge</td><td>$statusBadge</td><td>";
        
        // Кнопка роли
        $output .= "<button class=\"btn btn-sm btn-primary\" data-bs-toggle=\"modal\" data-bs-target=\"#roleModal$uid\">Роль</button> ";
        
        // Кнопка блокировки (не для admin ID=1)
        if ($uid != 1) {
            $blockText = $blocked ? "Разблок." : "Блок.";
            $output .= "<form method=\"POST\" class=\"d-inline\">";
            $output .= "<input type=\"hidden\" name=\"toggle_block\" value=\"1\">";
            $output .= "<input type=\"hidden\" name=\"user_id\" value=\"$uid\">";
            $output .= "<button type=\"submit\" class=\"btn btn-sm btn-warning\" onclick=\"return confirm('Уверены?')\">$blockText</button>";
            $output .= "</form>";
        }
        
        $output .= "</td></tr>";

        // Модальное окно смены роли
        $currentRole = $role ?? 'student';
        $output .= "<div class=\"modal fade\" id=\"roleModal$uid\" tabindex=\"-1\">";
        $output .= "<div class=\"modal-dialog\"><div class=\"modal-content\">";
        $output .= "<div class=\"modal-header\"><h5 class=\"modal-title\">Изменить роль: {$u->username}</h5>";
        $output .= "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\"></button></div>";
        $output .= "<form method=\"POST\"><div class=\"modal-body\">";
        $output .= "<input type=\"hidden\" name=\"change_role\" value=\"1\">";
        $output .= "<input type=\"hidden\" name=\"user_id\" value=\"$uid\">";
        $output .= "<label class=\"form-label\">Новая роль:</label>";
        $output .= "<select name=\"new_role\" class=\"form-select\">";
        $output .= "<option value=\"student\"" . ($currentRole == 'student' ? ' selected' : '') . ">Студент</option>";
        $output .= "<option value=\"expert\"" . ($currentRole == 'expert' ? ' selected' : '') . ">Эксперт</option>";
        $output .= "<option value=\"admin\"" . ($currentRole == 'admin' ? ' selected' : '') . ">Админ</option>";
        $output .= "</select></div>";
        $output .= "<div class=\"modal-footer\">";
        $output .= "<button type=\"button\" class=\"btn btn-secondary\" data-bs-dismiss=\"modal\">Отмена</button>";
        $output .= "<button type=\"submit\" class=\"btn btn-primary\">Сохранить</button>";
        $output .= "</div></form></div></div></div>";
    }

    $output .= '</tbody></table>';

    // Модальное окно создания пользователя
    $output .= '<div class="modal fade" id="createUserModal" tabindex="-1">';
    $output .= '<div class="modal-dialog"><div class="modal-content">';
    $output .= '<div class="modal-header"><h5 class="modal-title">Создать пользователя</h5>';
    $output .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
    $output .= '<form method="POST"><div class="modal-body">';
    $output .= '<input type="hidden" name="create_user" value="1">';
    $output .= '<div class="mb-3"><label class="form-label">Логин:</label>';
    $output .= '<input type="text" name="username" class="form-control" required></div>';
    $output .= '<div class="mb-3"><label class="form-label">Email:</label>';
    $output .= '<input type="email" name="email" class="form-control" required></div>';
    $output .= '<div class="mb-3"><label class="form-label">Пароль:</label>';
    $output .= '<input type="password" name="password" class="form-control" required></div>';
    $output .= '<div class="mb-3"><label class="form-label">Роль:</label>';
    $output .= '<select name="role" class="form-select">';
    $output .= '<option value="student">Студент</option>';
    $output .= '<option value="expert">Эксперт</option>';
    $output .= '<option value="admin">Админ</option>';
    $output .= '</select></div></div>';
    $output .= '<div class="modal-footer">';
    $output .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>';
    $output .= '<button type="submit" class="btn btn-success">Создать</button>';
    $output .= '</div></form></div></div></div>';

    $output .= '</div>';

    return $output;

} catch (Exception $e) {
    return '<div class="alert alert-danger">Ошибка: ' . htmlspecialchars($e->getMessage()) . '</div>';
}