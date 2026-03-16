<?php
/**
 * Сниппет: activateAccount - Активация аккаунта
 * Вызывается из: MODX ресурсов (страница активации)
 * Назначение: Активирует аккаунт пользователя по токену из email
 *
 * @package TestSystem
 * @version 1.1
 */

$token = trim((string)($_GET['token'] ?? ''));

if ($token === '') {
    return '<div class="ts-alert ts-alert-danger">Неверная ссылка активации.</div>';
}

$pdo = $modx->getConnection(modX::MODE_READONLY);
$stmt = $pdo->prepare('
    SELECT internalKey
    FROM ' . $modx->getTableName('modUserProfile') . '
    WHERE JSON_UNQUOTE(JSON_EXTRACT(extended, "$.activation_token")) = :token
    LIMIT 1
');

if ($stmt === false) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[activateAccount] Failed to prepare activation query');
    return '<div class="ts-alert ts-alert-danger">Временная ошибка активации. Попробуйте позже.</div>';
}

$stmt->bindValue(':token', $token, PDO::PARAM_STR);
if (!$stmt->execute()) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[activateAccount] Failed to execute activation query: ' . implode(' | ', $stmt->errorInfo()));
    return '<div class="ts-alert ts-alert-danger">Временная ошибка активации. Попробуйте позже.</div>';
}

$userId = (int)$stmt->fetchColumn();
if ($userId <= 0) {
    return '<div class="ts-alert ts-alert-danger">Токен активации недействителен, уже использован или повреждён.</div>';
}

$profile = $modx->getObject('modUserProfile', ['internalKey' => $userId]);
$user = $modx->getObject('modUser', $userId);

if (!$profile || !$user) {
    return '<div class="ts-alert ts-alert-danger">Пользователь для активации не найден.</div>';
}

$extended = $profile->get('extended') ?: [];
$storedToken = (string)($extended['activation_token'] ?? '');
$expiresAt = (string)($extended['activation_expires_at'] ?? '');

if ($storedToken === '' || !hash_equals($storedToken, $token)) {
    return '<div class="ts-alert ts-alert-danger">Токен активации недействителен, уже использован или повреждён.</div>';
}

if ($expiresAt === '' || strtotime($expiresAt) < time()) {
    return '<div class="ts-alert ts-alert-warning">Срок действия ссылки активации истёк. Запросите новую ссылку.</div>';
}

if ((int)$user->get('active') === 1) {
    $authId = (int)$modx->getOption('lms.auth_page', null, 0);
    $authUrl = $modx->makeUrl($authId ?: (int)$modx->getOption('site_start'));

    return '<div class="ts-alert ts-alert-info">Аккаунт уже активирован. <a href="' . $authUrl . '">Войти</a></div>';
}

unset($extended['activation_token'], $extended['activation_sent_at'], $extended['activation_expires_at']);
$profile->set('extended', $extended);
$user->set('active', 1);

if (!$profile->save() || !$user->save()) {
    $modx->log(modX::LOG_LEVEL_ERROR, '[activateAccount] Failed to save activated user #' . $userId);
    return '<div class="ts-alert ts-alert-danger">Ошибка активации аккаунта. Попробуйте позже.</div>';
}

$authId = (int)$modx->getOption('lms.auth_page', null, 0);
$authUrl = $modx->makeUrl($authId ?: (int)$modx->getOption('site_start'));

return '<div class="ts-alert ts-alert-success">
    <h4>✅ Аккаунт успешно активирован!</h4>
    <p><a href="' . $authUrl . '" class="ts-btn ts-btn-primary">Перейти ко входу</a></p>
</div>';
