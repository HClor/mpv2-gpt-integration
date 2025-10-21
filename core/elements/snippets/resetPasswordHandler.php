<?php
/* TS RESET PASSWORD v1.0 */

$token = $_GET['token'] ?? '';
$errors = [];
$success = false;

if (empty($token)) {
    return '<div class="alert alert-danger">Неверная ссылка для восстановления пароля</div>';
}

$c = $modx->newQuery('modUserProfile');
$c->where(['extended:LIKE' => '%"reset_token":"' . $token . '"%']);
$profile = $modx->getObject('modUserProfile', $c);

if (!$profile) {
    $forgotId = $modx->getOption('lms.forgot_page', null, 0);
    return '<div class="alert alert-danger">Неверный или устаревший токен. <a href="' . $modx->makeUrl($forgotId ?: $modx->getOption('site_start')) . '">Запросить новую ссылку</a></div>';
}

$extended = $profile->get('extended') ?: [];
$resetExpiry = $extended['reset_expiry'] ?? 0;

if (time() > $resetExpiry) {
    $forgotId = $modx->getOption('lms.forgot_page', null, 0);
    return '<div class="alert alert-danger">Ссылка устарела. <a href="' . $modx->makeUrl($forgotId ?: $modx->getOption('site_start')) . '">Запросить новую ссылку</a></div>';
}

$user = $profile->getOne('User');
if (!$user) {
    return '<div class="alert alert-danger">Ошибка: пользователь не найден</div>';
}

if ($_POST && isset($_POST['reset_password'])) {
    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');
    
    if (empty($password)) {
        $errors[] = 'Введите новый пароль';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Пароль должен быть не менее 6 символов';
    } elseif ($password !== $password_confirm) {
        $errors[] = 'Пароли не совпадают';
    } else {
        $user->set('password', $password);
        
        unset($extended['reset_token'], $extended['reset_expiry']);
        $profile->set('extended', $extended);
        
        if ($user->save() && $profile->save()) {
            $success = true;
        } else {
            $errors[] = 'Ошибка при сохранении пароля';
        }
    }
}

if ($success) {
    $authId = $modx->getOption('lms.auth_page', null, 0);
    $authUrl = $modx->makeUrl($authId ?: $modx->getOption('site_start'));
    return '<div class="alert alert-success">
        <h4>✅ Пароль успешно изменён!</h4>
        <p>Теперь вы можете <a href="' . $authUrl . '">войти в систему</a> с новым паролем.</p>
        <script>setTimeout(function(){ window.location.href="' . $authUrl . '"; }, 3000);</script>
    </div>';
}

$errorMsg = '';
if (!empty($errors)) {
    $errorMsg = '<div class="alert alert-danger"><ul class="mb-0">';
    foreach ($errors as $error) {
        $errorMsg .= '<li>' . htmlspecialchars($error) . '</li>';
    }
    $errorMsg .= '</ul></div>';
}

return $errorMsg . '<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3>Установка нового пароля</h3>
                </div>
                <div class="card-body">
                    <p>Для пользователя: <strong>' . htmlspecialchars($user->username) . '</strong></p>
                    <form method="post">
                        <input type="hidden" name="reset_password" value="1">
                        <div class="mb-3">
                            <label class="form-label">Новый пароль</label>
                            <input type="password" name="password" class="form-control" required>
                            <small class="form-text text-muted">Минимум 6 символов</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Подтвердите новый пароль</label>
                            <input type="password" name="password_confirm" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Установить новый пароль</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>';