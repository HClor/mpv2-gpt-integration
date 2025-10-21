<?php
/* TS CATEGORIES AND TESTS v2.2 RESOURCE CHECK */

$categoryId = (int)($_GET["category"] ?? 0);

$html = [];
$html[] = "<div class=\"row\">";

// ЛЕВАЯ КОЛОНКА - Категории
$html[] = "<div class=\"col-md-3\">";
$html[] = "<div class=\"card sticky-top\" style=\"top: 20px;\">";
$html[] = "<div class=\"card-header d-flex justify-content-between align-items-center\">";
$html[] = "<h5 class=\"mb-0\">Категории</h5>";
$html[] = "</div>";
$html[] = "<div class=\"list-group list-group-flush\">";

$stmt = $modx->query("
    SELECT 
        c.id,
        c.name,
        COUNT(t.id) as test_count
    FROM modx_test_categories c
    LEFT JOIN modx_test_tests t ON t.category_id = c.id AND t.is_active = 1
    GROUP BY c.id
    ORDER BY c.sort_order
");

$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($categories as $cat) {
    $isActive = ($cat["id"] == $categoryId) ? "active" : "";
    $categoryUrl = $modx->makeUrl($modx->resource->id) . "?category=" . $cat["id"];
    
    $html[] = "<a href=\"" . $categoryUrl . "\" class=\"list-group-item list-group-item-action " . $isActive . "\">";
    $html[] = "<div class=\"d-flex justify-content-between align-items-center\">";
    $html[] = "<span>" . htmlspecialchars($cat["name"]) . "</span>";
    $html[] = "<span class=\"badge bg-secondary rounded-pill\">" . $cat["test_count"] . "</span>";
    $html[] = "</div>";
    $html[] = "</a>";
}

$html[] = "</div>";

$rights = $modx->runSnippet("getUserRights");
if ($rights["canCreate"]) {
    $addUrl = $modx->makeUrl($modx->getOption("lms.add_test_page", null, 0));
    $html[] = "<div class=\"card-footer\">";
    $html[] = "<a href=\"" . $addUrl . "\" class=\"btn btn-success btn-sm w-100\">+ Добавить тест</a>";
    $html[] = "</div>";
}

$html[] = "</div>";
$html[] = "</div>";

// ПРАВАЯ КОЛОНКА - Тесты
$html[] = "<div class=\"col-md-9\">";

if (!$categoryId) {
    // Показываем приветствие
    $html[] = "<div class=\"card\">";
    $html[] = "<div class=\"card-body text-center py-5\">";
    $html[] = "<h3>Выберите категорию</h3>";
    $html[] = "<p class=\"text-muted\">Выберите категорию слева, чтобы увидеть список тестов</p>";
    $html[] = "</div>";
    $html[] = "</div>";
} else {
    // Получаем категорию
    $stmt = $modx->prepare("SELECT name, description FROM modx_test_categories WHERE id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        $html[] = "<div class=\"alert alert-danger\">Категория не найдена</div>";
    } else {
        // Заголовок категории
        $html[] = "<div class=\"mb-4\">";
        $html[] = "<h2>" . htmlspecialchars($category["name"]) . "</h2>";
        $html[] = "<p class=\"text-muted\">" . htmlspecialchars($category["description"]) . "</p>";
        $html[] = "</div>";
        
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
        
        if (empty($tests)) {
            $html[] = "<div class=\"alert alert-info\">";
            $html[] = "<h5>В этой категории пока нет тестов</h5>";
            $rights = $modx->runSnippet("getUserRights");
if ($rights["canCreate"]) {
                $addUrl = $modx->makeUrl($modx->getOption("lms.add_test_page", null, 0));
                $html[] = "<a href=\"" . $addUrl . "\" class=\"btn btn-primary mt-2\">Создать первый тест</a>";
            }
            $html[] = "</div>";
        } else {
            // Список тестов
            foreach ($tests as $test) {
                // /* TS RESOURCE CHECK v1 */ Проверяем существует ли ресурс
                $testResource = $modx->getObject("modResource", [
                    "alias" => "test-" . $test["id"],
                    "deleted" => 0,
                    "published" => 1
                ]);
                if (!$testResource) continue; // Пропускаем тест без ресурса

                // Проверяем существует ли ресурс /* TS RESOURCE CHECK v1 */
                $testResource = $modx->getObject("modResource", [
                    "alias" => "test-" . $test["id"],
                    "deleted" => 0,
                    "published" => 1
                ]);
                if (!$testResource) {
                    continue; // Пропускаем тест без ресурса
                }
                
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
                    $testPage->set("template", 3);
                    $testPage->set("published", 1);
                    $testPage->set("hidemenu", 1);
                    $testPage->set("content", "<h1>" . $test["title"] . "</h1>[[!testRunner? &testId=`" . $test["id"] . "`]]");
                    $testPage->save();
                }
                
                $testUrl = $modx->makeUrl($testPage->id);
                
                $html[] = "<div class=\"card mb-3\">";
                $html[] = "<div class=\"card-body\">";
                $html[] = "<div class=\"row align-items-center\">";
                
                $html[] = "<div class=\"col-md-8\">";
                $html[] = "<h5 class=\"card-title mb-2\">" . htmlspecialchars($test["title"]) . "</h5>";
                $html[] = "<p class=\"card-text text-muted mb-2\">" . htmlspecialchars($test["description"]) . "</p>";
                $html[] = "<div class=\"small text-muted\">";
                $html[] = "<span class=\"me-3\">Вопросов: " . $questionCount . "</span>";
                $html[] = "<span class=\"me-3\">За попытку: " . $test["questions_per_session"] . "</span>";
                $html[] = "<span>Проходной балл: " . $test["pass_score"] . "%</span>";
                $html[] = "</div>";
                $html[] = "</div>";
                
                $html[] = "<div class=\"col-md-4 text-md-end\">";
                $html[] = "<a href=\"" . $testUrl . "\" class=\"btn btn-primary\">Начать тест</a>";
                $html[] = "</div>";
                
                $html[] = "</div>";
                $html[] = "</div>";
                $html[] = "</div>";
            }
        }
    }
}

$html[] = "</div>";
$html[] = "</div>";

return implode("", $html);