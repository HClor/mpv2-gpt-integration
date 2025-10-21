<?php
/* TS TESTS LIST v1.0 */

$categoryId = (int)($_GET["category"] ?? 0);

if (!$categoryId) {
    return "<div class=\"alert alert-warning\">Выберите категорию</div>";
}

// Получаем категорию
$stmt = $modx->prepare("SELECT name, description FROM modx_test_categories WHERE id = ?");
$stmt->execute([$categoryId]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    return "<div class=\"alert alert-danger\">Категория не найдена</div>";
}

// Получаем тесты
$stmt = $modx->prepare("
    SELECT 
        id,
        title,
        description,
        mode,
        questions_per_session,
        pass_score
    FROM modx_test_tests
    WHERE category_id = ? AND is_active = 1
    ORDER BY created_at DESC
");
$stmt->execute([$categoryId]);
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html = [];
$html[] = "<div class=\"container mt-4\">";
$html[] = "<h2>" . htmlspecialchars($category["name"]) . "</h2>";
$html[] = "<p class=\"lead\">" . htmlspecialchars($category["description"]) . "</p>";

$html[] = "<a href=\"" . $modx->makeUrl($modx->resource->id) . "\" class=\"btn btn-secondary mb-3\">Назад к категориям</a>";

if (empty($tests)) {
    $html[] = "<div class=\"alert alert-info\">В этой категории пока нет тестов</div>";
} else {
    $html[] = "<div class=\"row\">";
    
    foreach ($tests as $test) {
        // Считаем вопросы
        $stmt = $modx->prepare("SELECT COUNT(*) FROM modx_test_questions WHERE test_id = ?");
        $stmt->execute([$test["id"]]);
        $questionCount = $stmt->fetchColumn();
        
        // Создаем страницу теста если её нет
        $testPage = $modx->getObject("modResource", ["alias" => "test-" . $test["id"]]);
        if (!$testPage) {
            $testPage = $modx->newObject("modResource");
            $testPage->set("pagetitle", "Тест: " . $test["title"]);
            $testPage->set("alias", "test-" . $test["id"]);
            $testPage->set("template", 2);
            $testPage->set("published", 1);
            $testPage->set("hidemenu", 1);
            $testPage->set("content", "<h1>" . $test["title"] . "</h1>[[!testRunner? &testId=`" . $test["id"] . "`]]");
            $testPage->save();
        }
        
        $testUrl = $modx->makeUrl($testPage->id);
        
        $html[] = "<div class=\"col-md-6 mb-4\">";
        $html[] = "<div class=\"card h-100\">";
        $html[] = "<div class=\"card-body\">";
        $html[] = "<h5 class=\"card-title\">" . htmlspecialchars($test["title"]) . "</h5>";
        $html[] = "<p class=\"card-text\">" . htmlspecialchars($test["description"]) . "</p>";
        $html[] = "<ul class=\"list-unstyled small\">";
        $html[] = "<li>Вопросов: " . $questionCount . "</li>";
        $html[] = "<li>За попытку: " . $test["questions_per_session"] . "</li>";
        $html[] = "<li>Проходной балл: " . $test["pass_score"] . "%</li>";
        $html[] = "</ul>";
        $html[] = "</div>";
        $html[] = "<div class=\"card-footer\">";
        $html[] = "<a href=\"" . $testUrl . "\" class=\"btn btn-primary\">Начать тест</a>";
        $html[] = "</div>";
        $html[] = "</div>";
        $html[] = "</div>";
    }
    
    $html[] = "</div>";
}

$html[] = "</div>";

return implode("", $html);