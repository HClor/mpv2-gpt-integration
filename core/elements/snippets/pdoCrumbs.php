<?php
/**
 * pdoCrumbs - простая альтернатива для хлебных крошек
 * Отображает путь от главной до текущей страницы
 *
 * @var modX $modx
 * @var string $separator - разделитель (по умолчанию »)
 * @var string $tpl - шаблон для элемента (по умолчанию встроенный)
 * @var int $showHome - показывать главную страницу (0/1)
 */

if (!$modx instanceof modX) {
    return '';
}

$separator = isset($separator) ? $separator : ' » ';
$showHome = isset($showHome) ? (int)$showHome : 1;
$currentId = $modx->resource->get('id');
$homeId = $modx->getOption('site_start', null, 1);

// Собираем путь от текущей страницы к корню
$crumbs = [];
$id = $currentId;
$depth = 0;
$maxDepth = 20; // Защита от бесконечного цикла

while ($id > 0 && $depth < $maxDepth) {
    $resource = $modx->getObject('modResource', $id);

    if (!$resource) {
        break;
    }

    $crumbs[] = [
        'id' => $id,
        'pagetitle' => $resource->get('pagetitle'),
        'menutitle' => $resource->get('menutitle'),
        'url' => $modx->makeUrl($id)
    ];

    $id = (int)$resource->get('parent');
    $depth++;
}

// Переворачиваем массив (от корня к текущей)
$crumbs = array_reverse($crumbs);

// Убираем главную если нужно
if (!$showHome && count($crumbs) > 0 && $crumbs[0]['id'] == $homeId) {
    array_shift($crumbs);
}

// Формируем вывод
if (empty($crumbs)) {
    return '';
}

$output = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';

foreach ($crumbs as $idx => $crumb) {
    $isLast = ($idx === count($crumbs) - 1);
    $title = !empty($crumb['menutitle']) ? $crumb['menutitle'] : $crumb['pagetitle'];

    if ($isLast) {
        $output .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</li>';
    } else {
        $output .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($crumb['url'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</a></li>';
    }
}

$output .= '</ol></nav>';

return $output;
