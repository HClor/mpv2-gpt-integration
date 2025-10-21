<?php
/* TS ADD TEST FORM v3.0 WITH REDIRECT */

if (!$modx->user->hasSessionContext("web")) {
    $authUrl = $modx->makeUrl($modx->getOption("lms.auth_page", null, 0));
    return "<div class=\"alert alert-warning\"><a href=\"" . $authUrl . "\">Войдите</a>, чтобы добавлять тесты</div>";
}

$rights = $modx->runSnippet("getUserRights");
if (!$rights["canCreate"]) {
    return "<div class=\"alert alert-danger\">
        <h4>Доступ запрещён</h4>
        <p>Создание тестов доступно только экспертам и администраторам.</p>
    </div>";
}

$errors = [];

// ОБРАБОТКА СОЗДАНИЯ ТЕСТА
if ($_POST && isset($_POST["add_test"])) {
    $categoryId = (int)($_POST["category_id"] ?? 0);
    $title = trim($_POST["title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $mode = $_POST["mode"] ?? "training";
    $timeLimit = (int)($_POST["time_limit"] ?? 0);
    $passScore = (int)($_POST["pass_score"] ?? 70);
    $questionsPerSession = (int)($_POST["questions_per_session"] ?? 20);
    
    if (!$categoryId) $errors[] = "Выберите категорию";
    if (empty($title)) $errors[] = "Введите название теста";
    if ($passScore < 0 || $passScore > 100) $errors[] = "Проходной балл: 0-100%";
    
    if (empty($errors)) {
        $stmt = $modx->prepare("
            INSERT INTO modx_test_tests 
            (category_id, title, description, mode, time_limit, pass_score, questions_per_session, randomize_questions, randomize_answers, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1, 1)
        ");
        
        if ($stmt->execute([$categoryId, $title, $description, $mode, $timeLimit, $passScore, $questionsPerSession])) {
            $newTestId = $modx->lastInsertId();
            
            // РЕДИРЕКТ НА ЭТУ ЖЕ СТРАНИЦУ С test_id
            $currentUrl = $modx->makeUrl($modx->resource->id);
            $redirectUrl = $currentUrl . "?test_id=" . $newTestId;
            
            $modx->sendRedirect($redirectUrl);
            exit;
        } else {
            $errors[] = "Ошибка при создании теста";
        }
    }
}

// ПРОВЕРЯЕМ ЕСТЬ ЛИ test_id В GET (шаг 2)
$testId = (int)($_GET["test_id"] ?? 0);

if ($testId) {
    // Проверяем существует ли тест
    $stmt = $modx->prepare("SELECT id, title FROM modx_test_tests WHERE id = ?");
    $stmt->execute([$testId]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test) {
        // ПОКАЗЫВАЕМ ШАГ 2
        $output = "";
        
        $output .= "<div class=\"row\">";
        $output .= "<div class=\"col-md-10 offset-md-1\">";
        
        $output .= "<div class=\"card border-success mb-4\">";
        $output .= "<div class=\"card-header bg-success text-white\">";
        $output .= "<h4 class=\"mb-0\">✓ Шаг 1 завершён: Тест создан</h4>";
        $output .= "</div>";
        $output .= "<div class=\"card-body\">";
        $output .= "<p class=\"mb-0\">Тест: <strong>" . htmlspecialchars($test["title"]) . "</strong></p>";
        $output .= "</div>";
        $output .= "</div>";
        
        $output .= "<div class=\"card\">";
        $output .= "<div class=\"card-header bg-primary text-white\">";
        $output .= "<h4 class=\"mb-0\">Шаг 2: Импортируйте вопросы</h4>";
        $output .= "</div>";
        $output .= "<div class=\"card-body\">";
        
        $output .= $modx->runSnippet("csvImportForm", ["testId" => $testId]);
        
        $output .= "</div>";
        $output .= "</div>";
        
        $output .= "</div>";
        $output .= "</div>";
        
        return $output;
    }
}

// ПОКАЗЫВАЕМ ШАГ 1 - ФОРМУ СОЗДАНИЯ ТЕСТА
$stmt = $modx->query("SELECT id, name FROM modx_test_categories ORDER BY sort_order");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$output = "";

$output .= "<div class=\"row\">";
$output .= "<div class=\"col-md-8 offset-md-2\">";

if (!empty($errors)) {
    $output .= "<div class=\"alert alert-danger\"><ul class=\"mb-0\">";
    foreach ($errors as $error) {
        $output .= "<li>" . htmlspecialchars($error) . "</li>";
    }
    $output .= "</ul></div>";
}

$output .= "<div class=\"card\">";
$output .= "<div class=\"card-header bg-primary text-white\">";
$output .= "<h4 class=\"mb-0\">Шаг 1: Создайте тест</h4>";
$output .= "</div>";
$output .= "<div class=\"card-body\">";

$output .= "<form method=\"POST\">";
$output .= "<input type=\"hidden\" name=\"add_test\" value=\"1\">";

$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\">Категория *</label>";
$output .= "<select name=\"category_id\" class=\"form-select\" required>";
$output .= "<option value=\"\">-- Выберите категорию --</option>";
foreach ($categories as $cat) {
    $selected = (isset($_POST["category_id"]) && $_POST["category_id"] == $cat["id"]) ? "selected" : "";
    $output .= "<option value=\"" . $cat["id"] . "\" " . $selected . ">" . htmlspecialchars($cat["name"]) . "</option>";
}
$output .= "</select>";
$output .= "</div>";

$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\">Название теста *</label>";
$output .= "<input type=\"text\" name=\"title\" class=\"form-control\" value=\"" . htmlspecialchars($_POST["title"] ?? "") . "\" required>";
$output .= "<small class=\"form-text text-muted\">Например: По книге Красная таблетка</small>";
$output .= "</div>";

$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\">Описание</label>";
$output .= "<textarea name=\"description\" class=\"form-control\" rows=\"3\">" . htmlspecialchars($_POST["description"] ?? "") . "</textarea>";
$output .= "</div>";

$output .= "<div class=\"row\">";

$output .= "<div class=\"col-md-6 mb-3\">";
$output .= "<label class=\"form-label\">Режим по умолчанию</label>";
$output .= "<select name=\"mode\" class=\"form-select\">";
$output .= "<option value=\"training\" " . (($_POST["mode"] ?? "training") == "training" ? "selected" : "") . ">Training (обучение)</option>";
$output .= "<option value=\"exam\" " . (($_POST["mode"] ?? "") == "exam" ? "selected" : "") . ">Exam (экзамен)</option>";
$output .= "</select>";
$output .= "</div>";

$output .= "<div class=\"col-md-6 mb-3\">";
$output .= "<label class=\"form-label\">Проходной балл (%)</label>";
$output .= "<input type=\"number\" name=\"pass_score\" class=\"form-control\" value=\"" . ($_POST["pass_score"] ?? "70") . "\" min=\"0\" max=\"100\">";
$output .= "</div>";

$output .= "</div>";

$output .= "<div class=\"row\">";

$output .= "<div class=\"col-md-6 mb-3\">";
$output .= "<label class=\"form-label\">Вопросов за попытку</label>";
$output .= "<input type=\"number\" name=\"questions_per_session\" class=\"form-control\" value=\"" . ($_POST["questions_per_session"] ?? "20") . "\" min=\"1\">";
$output .= "<small class=\"form-text text-muted\">Сколько случайных вопросов показывать</small>";
$output .= "</div>";

$output .= "<div class=\"col-md-6 mb-3\">";
$output .= "<label class=\"form-label\">Время на тест (минут)</label>";
$output .= "<input type=\"number\" name=\"time_limit\" class=\"form-control\" value=\"" . ($_POST["time_limit"] ?? "0") . "\" min=\"0\">";
$output .= "<small class=\"form-text text-muted\">0 = без ограничения</small>";
$output .= "</div>";

$output .= "</div>";

$output .= "<div class=\"d-flex justify-content-between mt-4\">";
$testsUrl = $modx->makeUrl($modx->getOption("lms.tests_page", null, 35));
$output .= "<a href=\"" . $testsUrl . "\" class=\"btn btn-secondary\">Отмена</a>";
$output .= "<button type=\"submit\" class=\"btn btn-primary btn-lg\">Далее: Импорт вопросов →</button>";
$output .= "</div>";

$output .= "</form>";

$output .= "</div>";
$output .= "</div>";

$output .= "</div>";
$output .= "</div>";

return $output;