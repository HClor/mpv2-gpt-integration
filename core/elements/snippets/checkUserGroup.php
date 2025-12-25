<?php
/**
 * checkUserGroup - проверка членства пользователя в группе
 *
 * @param string $group Имя группы для проверки
 * @return int 1 если пользователь в группе, 0 если нет
 */

$group = $modx->getOption('group', $scriptProperties, '');

if (empty($group)) {
    return 0;
}

$user = $modx->user;

if (!$user || !$user->get('id')) {
    return 0;
}

return $user->isMember($group) ? 1 : 0;
