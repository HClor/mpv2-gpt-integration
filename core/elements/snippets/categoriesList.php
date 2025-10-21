<?php
/* TS CATEGORIES LIST v1.0 */

// Получаем категории
$stmt = $modx->query("
    SELECT 
        c.id,
        c.name,
        c.description,
        COUNT(t.id) as test_count
    FROM modx_test_categories c
    LEFT JOIN modx_test_tests t ON t.category_id = c.id AND t.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html = [];
$html[] = "<div class=\"container mt-4\">";
$html[] = "<div class=\"d-flex justify-content-between align-items-center mb-3\">";
$html[] = "<h2 class=\"mb-0\">Категории тестов</h2>";
if ($modx->user->hasSessionContext("web")) {
    $addUrl = $modx->makeUrl($modx->getOption("lms.add_test_page", null, 0));
    $html[] = "<a href=\"" . $addUrl . "\" class=\"btn btn-success\">+ Добавить тест</a>";
}
$html[] = "</div>";

if (empty($categories)) {
    $html[] = "<div class=\"alert alert-info\">Категории пока не созданы</div>";
} else {
    $html[] = "<div class=\"row\">";
    
    foreach ($categories as $cat) {
        $categoryUrl = $modx->makeUrl($modx->resource->id) . "?category=" . $cat["id"];
        
        $html[] = "<div class=\"col-md-4 mb-4\">";
        $html[] = "<div class=\"card h-100\">";
        $html[] = "<div class=\"card-body\">";
        $html[] = "<h5 class=\"card-title\">" . htmlspecialchars($cat["name"]) . "</h5>";
        $html[] = "<p class=\"card-text\">" . htmlspecialchars($cat["description"]) . "</p>";
        $html[] = "<p class=\"text-muted\">Тестов: " . $cat["test_count"] . "</p>";
        $html[] = "</div>";
        $html[] = "<div class=\"card-footer\">";
        $html[] = "<a href=\"" . $categoryUrl . "\" class=\"btn btn-primary\">Перейти к тестам</a>";
        $html[] = "</div>";
        $html[] = "</div>";
        $html[] = "</div>";
    }
    
    $html[] = "</div>";
}

$html[] = "</div>";

return implode("", $html);