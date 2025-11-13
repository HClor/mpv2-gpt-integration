<?php
/**
 * Возвращает роль пользователя LMS (admin/expert/student) по userId.
 */
if (!$modx instanceof modX) {
    return '';
}

$userId = (int)($scriptProperties['userId'] ?? 0);
if ($userId <= 0) {
    return '';
}

$sql = 'SELECT mgn.name FROM modx_member_groups mg
    JOIN modx_membergroup_names mgn ON mgn.id = mg.user_group
    WHERE mg.member = :uid AND mgn.name LIKE "LMS %"';
$stmt = $modx->prepare($sql);
$stmt->execute([':uid' => $userId]);
$groups = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (in_array('LMS Admins', $groups, true)) {
    return 'admin';
}
if (in_array('LMS Experts', $groups, true)) {
    return 'expert';
}
if (in_array('LMS Students', $groups, true)) {
    return 'student';
}

return '';