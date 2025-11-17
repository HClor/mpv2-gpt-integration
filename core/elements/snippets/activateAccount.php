<?php
/* TS ACTIVATE ACCOUNT v1.0 */

$token = $_GET['token'] ?? '';

if (empty($token)) {
    return '<div class="alert alert-danger">Неверная ссылка активации</div>';
}

// Безопасный поиск токена в JSON поле с использованием prepared statement
$pdo = $modx->getConnection(modX::MODE_READONLY);
$stmt = $pdo->prepare("
    SELECT id
    FROM " . $modx->getTableName('modUserProfile') . "
    WHERE JSON_EXTRACT(extended, '$.activation_token') = :token
    LIMIT 1
");
$stmt->bindValue(':token', $token, PDO::PARAM_STR);
$stmt->execute();
$profileId = $stmt->fetchColumn();

$profile = null;
if ($profileId) {
    $profile = $modx->getObject('modUserProfile', $profileId);
}

if (!$profile) {
    return '<div class="alert alert-danger">Неверный токен активации или аккаунт уже активирован</div>';
}

$user = $profile->getOne('User');
if ($user) {
    $wasInactive = $user->get('active') == 0;
    
    if ($wasInactive) {
        $user->set('active', 1);
        
        $extended = $profile->get('extended');
        if (is_array($extended)) {
            unset($extended['activation_token']);
            $profile->set('extended', $extended);
        }
        
        if ($user->save() && $profile->save()) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $modx->user = $user;
            $modx->user->addSessionContext('web');
            
            $profileId = $modx->getOption('lms.profile_page', null, 0);
            $profileUrl = $modx->makeUrl($profileId ?: $modx->getOption('site_start'));
            
            return '<div class="alert alert-success">
                <h4>✅ Аккаунт успешно активирован и вы авторизованы!</h4>
                <p><a href="' . $profileUrl . '" class="btn btn-primary">Перейти в профиль</a></p>
                <script>setTimeout(function(){ window.location.href="' . $profileUrl . '"; }, 2000);</script>
            </div>';
        }
    } else {
        if ($modx->user->id == $user->id) {
            $profileId = $modx->getOption('lms.profile_page', null, 0);
            return '<div class="alert alert-info">Вы уже авторизованы. <a href="' . $modx->makeUrl($profileId ?: $modx->getOption('site_start')) . '">Перейти в профиль</a></div>';
        }
        $authId = $modx->getOption('lms.auth_page', null, 0);
        return '<div class="alert alert-warning">Аккаунт уже активирован. <a href="' . $modx->makeUrl($authId ?: $modx->getOption('site_start')) . '">Войти</a></div>';
    }
}

return '<div class="alert alert-danger">Ошибка активации</div>';