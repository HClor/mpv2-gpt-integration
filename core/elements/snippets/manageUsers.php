<?php
/**
 * TS MANAGE USERS v2.7 - FIXED isMember LOGIC
 */
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

if (!$modx instanceof modX) {
    return '<div class="alert alert-danger">MODX context required</div>';
}

try {
    // Проверка авторизации
    PermissionHelper::requireAuthentication($modx);

    // Проверка прав администратора
    if (!PermissionHelper::isAdmin($modx)) {
        throw new PermissionException('Доступ запрещён. Только для администраторов.');
    }

    $errors = [];
    $success = '';

    $groupMap = [
        'student' => Config::getGroup('students'),
        'expert' => Config::getGroup('experts'),
        'admin'  => Config::getGroup('admins'),
    ];

    $getUserRole = static function (modX $modx, int $uid): ?string {
        $adminGroup = Config::getGroup('admins');
        $expertGroup = Config::getGroup('experts');
        $studentGroup = Config::getGroup('students');

        $sql = 'SELECT mgn.name
                FROM modx_member_groups mg
                JOIN modx_membergroup_names mgn ON mgn.id = mg.user_group
                WHERE mg.member = :uid AND mgn.name LIKE "LMS %"
                ORDER BY FIELD(mgn.name, :admin, :expert, :student)
                LIMIT 1';
        $stmt = $modx->prepare($sql);
        $stmt->execute([
            ':uid' => $uid,
            ':admin' => $adminGroup,
            ':expert' => $expertGroup,
            ':student' => $studentGroup
        ]);
        $groupName = $stmt->fetchColumn();

        return match($groupName) {
            $adminGroup => 'admin',
            $expertGroup => 'expert',
            $studentGroup => 'student',
            default => null
        };
    };

    $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

    $sanitizeText = static function (modX $modx, string $value): string {
        return $modx->sanitize($value, $modx->sanitizePatterns['text']);
    };

    if ($isPost && isset($_POST['change_role'])) {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $newRole = $sanitizeText($modx, (string)($_POST['new_role'] ?? ''));

        if (!$targetUserId || !array_key_exists($newRole, $groupMap)) {
            $errors[] = 'Некорректные данные';
        } else {
            $targetUser = $modx->getObject('modUser', $targetUserId);
            if (!$targetUser) {
                $errors[] = 'Пользователь не найден';
            } else {
                foreach ($groupMap as $groupName) {
                    $group = $modx->getObject('modUserGroup', ['name' => $groupName]);
                    if ($group) {
                        $targetUser->leaveGroup((int)$group->get('id'));
                    }
                }

                $newGroup = $modx->getObject('modUserGroup', ['name' => $groupMap[$newRole]]);
                if ($newGroup) {
                    $targetUser->joinGroup((int)$newGroup->get('id'), 1);
                    $success = 'Роль изменена на: ' . $groupMap[$newRole];
                } else {
                    $errors[] = 'Группа не найдена';
                }
            }
        }
    }

    if ($isPost && isset($_POST['toggle_block'])) {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId && $targetUserId !== 1) {
            $targetUser = $modx->getObject('modUser', $targetUserId);
            if ($targetUser) {
                $profile = $targetUser->getOne('Profile');
                if ($profile) {
                    $isBlocked = (bool)$profile->get('blocked');
                    $profile->set('blocked', !$isBlocked);
                    $profile->save();
                    $success = $isBlocked ? 'Пользователь разблокирован' : 'Пользователь заблокирован';
                }
            }
        }
    }

    if ($isPost && isset($_POST['create_user'])) {
        $username = $sanitizeText($modx, trim((string)($_POST['username'] ?? '')));
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = $sanitizeText($modx, (string)($_POST['role'] ?? 'student'));

        if ($username === '' || $email === '' || $password === '') {
            $errors[] = 'Заполните все поля';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email';
        } elseif (!array_key_exists($role, $groupMap)) {
            $errors[] = 'Некорректная роль';
        } else {
            $existingUser = $modx->getObject('modUser', ['username' => $username]);
            if ($existingUser) {
                $errors[] = 'Логин уже занят';
            } else {
                $newUser = $modx->newObject('modUser');
                $newUser->set('username', $username);
                $newUser->set('active', 1);

                $profile = $modx->newObject('modUserProfile');
                $profile->set('email', $email);
                $profile->set('blocked', 0);
                $newUser->addOne($profile);

                if ($newUser->save()) {
                    $newUser->set('password', $password);
                    $newUser->save();

                    $group = $modx->getObject('modUserGroup', ['name' => $groupMap[$role]]);
                    if ($group) {
                        $newUser->joinGroup((int)$group->get('id'), 1);
                    }

                    $success = 'Пользователь создан: ' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
                } else {
                    $errors[] = 'Ошибка создания';
                }
            }
        }
    }

    $output = '<div class="container-fluid py-4">';
    $output .= '<h2 class="mb-4">Управление пользователями</h2>';

    foreach ($errors as $err) {
        $output .= '<div class="alert alert-danger">' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    if ($success !== '') {
        $output .= '<div class="alert alert-success">' . $success . '</div>';
    }

    $output .= '<button class="btn btn-success mb-3" data-bs-toggle="modal" data-bs-target="#createUserModal">+ Создать пользователя</button>';

    $users = $modx->getCollection('modUser');

    $output .= '<table class="table table-hover mb-0"><thead class="table-light"><tr>';
    $output .= '<th style="width: 50px;">ID</th><th>Логин</th><th>Email</th><th>Роль в LMS</th>';
    $output .= '<th style="width: 100px;">Статус</th><th style="width: 180px;">Действия</th></tr></thead><tbody>';

    foreach ($users as $user) {
        $uid = (int)$user->get('id');
        $profile = $user->getOne('Profile');
        $email = $profile ? $profile->get('email') : '';
        $blocked = $profile ? (bool)$profile->get('blocked') : false;

        $role = $getUserRole($modx, $uid);
        $roleBadge = '<span class="badge bg-secondary">Нет роли</span>';
        if ($role) {
            $roleBadge = [
                'student' => '<span class="badge bg-info">Студент</span>',
                'expert'  => '<span class="badge bg-primary">Эксперт</span>',
                'admin'   => '<span class="badge bg-danger">Админ</span>',
            ][$role];
        }

        $statusBadge = $blocked
            ? '<span class="badge bg-danger">Заблокирован</span>'
            : '<span class="badge bg-success">Активен</span>';

        $output .= '<tr>';
        $output .= '<td>' . $uid . '</td>';
        $output .= '<td><strong>' . htmlspecialchars($user->get('username'), ENT_QUOTES, 'UTF-8') . '</strong></td>';
        $output .= '<td>' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</td>';
        $output .= '<td>' . $roleBadge . '</td>';
        $output .= '<td>' . $statusBadge . '</td>';
        $output .= '<td>';

        $output .= '<button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#roleModal' . $uid . '">Роль</button> ';

        if ($uid !== 1) {
            $output .= '<form method="POST" class="d-inline">';
            $output .= '<input type="hidden" name="toggle_block" value="1">';
            $output .= '<input type="hidden" name="user_id" value="' . $uid . '">';
            $output .= '<button type="submit" class="btn btn-sm btn-warning" onclick="return confirm(\'Уверены?\')">' . ($blocked ? 'Разблок.' : 'Блок.') . '</button>';
            $output .= '</form>';
        }

        $output .= '</td></tr>';

        $currentRole = $role ?? 'student';
        $output .= '<div class="modal fade" id="roleModal' . $uid . '" tabindex="-1">';
        $output .= '<div class="modal-dialog"><div class="modal-content">';
        $output .= '<div class="modal-header"><h5 class="modal-title">Изменить роль: ' . htmlspecialchars($user->get('username'), ENT_QUOTES, 'UTF-8') . '</h5>';
        $output .= '<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>';
        $output .= '<form method="POST"><div class="modal-body">';
        $output .= '<input type="hidden" name="change_role" value="1">';
        $output .= '<input type="hidden" name="user_id" value="' . $uid . '">';
        $output .= '<label class="form-label">Новая роль:</label>';
        $output .= '<select name="new_role" class="form-select">';
        foreach ($groupMap as $key => $title) {
            $selected = $currentRole === $key ? ' selected' : '';
            $label = [
                'student' => 'Студент',
                'expert'  => 'Эксперт',
                'admin'   => 'Админ',
            ][$key];
            $output .= '<option value="' . $key . '"' . $selected . '>' . $label . '</option>';
        }
        $output .= '</select></div>';
        $output .= '<div class="modal-footer">';
        $output .= '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>';
        $output .= '<button type="submit" class="btn btn-primary">Сохранить</button>';
        $output .= '</div></form></div></div></div>';
    }

    $output .= '</tbody></table>';

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
} catch (Throwable $e) {
    return '<div class="alert alert-danger">Ошибка: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
}