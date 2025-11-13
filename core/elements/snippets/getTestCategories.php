<?php
/**
 * getTestCategories - выводит категории-контейнеры с количеством тестов
 */

if (!$modx instanceof modX) {
    return '';
}

$tpl = $scriptProperties['tpl'] ?? 'categoryCardHome';
$limit = (int)($scriptProperties['limit'] ?? 0);
$parents = $scriptProperties['parents'] ?? 35;
$showEmpty = !empty($scriptProperties['showEmpty']);

$prefix = $modx->getOption('table_prefix');
$Ttests = $prefix . 'test_tests';
$S = $prefix . 'site_content';

$sql = "
    SELECT
        cat.id,
        cat.pagetitle as name,
        cat.description,
        cat.introtext,
        cat.uri,
        COUNT(DISTINCT CASE 
            WHEN t.id IS NOT NULL 
            AND test_res.published = 1 
            AND test_res.deleted = 0 
            THEN t.id 
        END) AS test_count
    FROM `{$S}` cat
    LEFT JOIN `{$S}` test_res ON test_res.parent = cat.id 
        AND test_res.isfolder = 0
        AND test_res.published = 1
        AND test_res.deleted = 0
    LEFT JOIN `{$Ttests}` t ON t.resource_id = test_res.id 
        AND t.is_active = 1
    WHERE cat.parent = {$parents}
        AND cat.isfolder = 1
        AND cat.published = 1
        AND cat.deleted = 0
    GROUP BY cat.id
    " . ($showEmpty ? '' : 'HAVING test_count > 0') . "
    ORDER BY cat.pagetitle
";

if ($limit > 0) {
    $sql .= " LIMIT {$limit}";
}

$stmt = $modx->prepare($sql);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($categories)) {
    return '<div class="col-12"><div class="alert alert-info">Категории не найдены</div></div>';
}

$output = [];
$idx = 1;

foreach ($categories as $cat) {
    $categoryUrl = $modx->makeUrl((int)$cat['id']);
    
    // Определяем иконку Bootstrap Icons по названию категории
    $icon = 'bi-folder';
    $catNameLower = mb_strtolower($cat['name']);
    
    if (stripos($catNameLower, 'программ') !== false) {
        $icon = 'bi-code-slash';
    } elseif (stripos($catNameLower, 'психолог') !== false) {
        $icon = 'bi-brain';
    } elseif (stripos($catNameLower, 'литератур') !== false) {
        $icon = 'bi-book';
    } elseif (stripos($catNameLower, 'самообор') !== false) {
        $icon = 'bi-shield-check';
    } elseif (stripos($catNameLower, 'саморазвит') !== false || stripos($catNameLower, 'книги') !== false) {
        $icon = 'bi-lightning';
    }
    
    $placeholders = [
        'id' => $cat['id'],
        'name' => htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'),
        'description' => htmlspecialchars($cat['description'] ?: $cat['introtext'], ENT_QUOTES, 'UTF-8'),
        'icon' => $icon,
        'test_count' => (int)$cat['test_count'],
        'url' => htmlspecialchars($categoryUrl, ENT_QUOTES, 'UTF-8'),
        'idx' => $idx++
    ];
    
    $output[] = $modx->getChunk($tpl, $placeholders);
}

return implode('', $output);