<?php
/**
 * MenuWithACL - SQL-версия с поддержкой шаблонов
 * 
 * @var modX $modx
 * @var int $parents - ID родительского ресурса (по умолчанию 0)
 * @var string $tpl - Название чанка для пункта меню (или @INLINE)
 * @var string $tplOuter - Название чанка для обертки меню (или @INLINE)
 * @var string $sortby - Поле сортировки (по умолчанию menuindex)
 * @var string $sortdir - Направление сортировки (ASC/DESC)
 * @var int $showHidden - Показывать ресурсы с hidemenu=1 (0/1)
 * @var string $activeClass - CSS класс для активного пункта меню
 */

// Параметры
$parents = isset($parents) ? (int)$parents : 0;
$tpl = isset($tpl) ? $tpl : 'menuItemTpl';
$tplOuter = isset($tplOuter) ? $tplOuter : 'menuOuterTpl';
$sortby = isset($sortby) ? $sortby : 'menuindex';
$sortdir = isset($sortdir) ? $sortdir : 'ASC';
$showHidden = isset($showHidden) ? (int)$showHidden : 0;
$activeClass = isset($activeClass) ? $activeClass : 'active';

// Текущий ресурс для подсветки активного пункта
$currentId = $modx->resource->get('id');

// Проверка авторизации в контексте web
$isAuthenticated = $modx->user->isAuthenticated('web');
$userId = $modx->user->get('id');

// Получаем группы пользователя
$userGroupIds = array();
if ($isAuthenticated && $userId > 0) {
    $c = $modx->newQuery('modUserGroupMember');
    $c->where(array('member' => $userId));
    $memberships = $modx->getCollection('modUserGroupMember', $c);
    
    foreach ($memberships as $membership) {
        $groupId = $membership->get('user_group');
        if (!in_array($groupId, $userGroupIds)) {
            $userGroupIds[] = $groupId;
        }
    }
}

// Прямой SQL запрос (обходит ACL-фильтрацию MODX)
$sql = "SELECT id, pagetitle, longtitle, menutitle, description, alias, parent, menuindex 
        FROM " . $modx->getTableName('modResource') . " 
        WHERE parent = :parent 
          AND published = 1 
          AND deleted = 0";

if (!$showHidden) {
    $sql .= " AND hidemenu = 0";
}

$sql .= " ORDER BY " . $sortby . " " . $sortdir;

$stmt = $modx->prepare($sql);
$stmt->bindParam(':parent', $parents, PDO::PARAM_INT);
$stmt->execute();
$resourcesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Формируем меню
$output = '';
$itemCount = 0;

foreach ($resourcesData as $resData) {
    $id = (int)$resData['id'];
    
    // Получаем группы ресурсов
    $c = $modx->newQuery('modResourceGroupResource');
    $c->where(array('document' => $id));
    $resourceGroupLinks = $modx->getCollection('modResourceGroupResource', $c);
    
    $resourceGroupIds = array();
    foreach ($resourceGroupLinks as $link) {
        $resourceGroupIds[] = $link->get('document_group');
    }
    
    // Проверка доступа
    if (empty($resourceGroupIds)) {
        // Публичный ресурс - показываем всем
        $canView = true;
    } else {
        // Защищенный ресурс - проверяем права
        $canView = false;
        
        if ($isAuthenticated && !empty($userGroupIds)) {
            foreach ($resourceGroupIds as $rgId) {
                $c = $modx->newQuery('modAccessResourceGroup');
                $c->where(array(
                    'target' => $rgId,
                    'principal:IN' => $userGroupIds,
                    'context_key' => $modx->context->get('key')
                ));
                $acl = $modx->getObject('modAccessResourceGroup', $c);
                
                if ($acl && (int)$acl->get('authority') === 0) {
                    $canView = true;
                    break;
                }
            }
        }
    }
    
    // Пропускаем ресурс, если нет доступа
    if (!$canView) {
        continue;
    }
    
    // Формируем пункт меню
    $itemCount++;
    
    $class = ($id == $currentId) ? $activeClass : '';
    
    // Данные для шаблона
    $data = array(
        'id' => $id,
        'pagetitle' => $resData['pagetitle'],
        'longtitle' => $resData['longtitle'],
        'menutitle' => !empty($resData['menutitle']) ? $resData['menutitle'] : $resData['pagetitle'],
        'description' => $resData['description'],
        'alias' => $resData['alias'],
        'url' => $modx->makeUrl($id),
        'class' => $class,
        'classAttr' => $class ? ' class="' . $class . '"' : '',
        'parent' => $resData['parent'],
        'menuindex' => $resData['menuindex']
    );
    
    // Парсим шаблон через getChunk (поддерживает и чанки, и @INLINE)
    $output .= $modx->getChunk($tpl, $data);
}

// Если пунктов нет - возвращаем пустоту
if ($itemCount === 0) {
    return '';
}

// Оборачиваем в внешний шаблон
return $modx->getChunk($tplOuter, array('wrapper' => $output));