<?php
/**
 * Сниппет: getUserRights - Проверка прав пользователя
 * Вызывается из: tsHeader.tpl, learningPaths.php, learningMaterialsTemplate.php
 * Назначение: Возвращает массив с флагами ролей пользователя (isAdmin, isExpert, isStudent)
 *
 * @package TestSystem
 * @version 1.0
 */

$userId = $modx->user->id;

if (!$userId || !$modx->user->hasSessionContext("web")) {
    return [
        "isStudent" => false,
        "isExpert" => false,
        "isAdmin" => false,
        "canEdit" => false,
        "canCreate" => false,
        "canManage" => false
    ];
}

// Проверяем группы
$isStudent = $modx->user->isMember("LMS Students");
$isExpert = $modx->user->isMember("LMS Experts");
$isAdmin = $modx->user->isMember("LMS Admins") || $userId == 1;

return [
    "isStudent" => $isStudent,
    "isExpert" => $isExpert,
    "isAdmin" => $isAdmin,
    "canEdit" => $isExpert || $isAdmin,
    "canCreate" => $isExpert || $isAdmin,
    "canManage" => $isAdmin
];