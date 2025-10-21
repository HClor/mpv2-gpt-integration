<?php
/* TS CSV IMPORT FORM v3.0 FINAL CLEAN */

$testId = (int)($_GET["test_id"] ?? ($scriptProperties["testId"] ?? 0));

if (!$testId) {
    return "<div class=\"alert alert-danger\">Test ID not specified</div>";
}

if (!$modx->user->hasSessionContext("web")) {
    return "<div class=\"alert alert-warning\">Please login</div>";
}

$stmt = $modx->prepare("SELECT title, category_id FROM modx_test_tests WHERE id = ?");
$stmt->execute([$testId]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    return "<div class=\"alert alert-danger\">Test not found</div>";
}

$categoryId = $test["category_id"];
$message = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["import_csv"])) {
    
    if (!isset($_FILES["csv_file"]) || empty($_FILES["csv_file"]["tmp_name"])) {
        $errors[] = "Файл не загружен";
    } else {
        $file = $_FILES["csv_file"];
        
        if ($file["error"] !== UPLOAD_ERR_OK) {
            $errors[] = "Ошибка загрузки (код: " . $file["error"] . ")";
        } else {
            $content = file_get_contents($file["tmp_name"]);
            
            if ($content === false) {
                $errors[] = "Не удалось прочитать файл";
            } else {
                $bom = pack("H*", "EFBBBF");
                $content = preg_replace("/^" . $bom . "/", "", $content);
                
                $lines = preg_split("/\r\n|\n|\r/", $content);
                
                if (count($lines) < 2) {
                    $errors[] = "Файл пустой или содержит только заголовок";
                } else {
                    array_shift($lines);
                    
                    $imported = 0;
                    
                    foreach ($lines as $index => $line) {
                        $line = trim($line);
                        if (empty($line)) continue;
                        
                        $row = str_getcsv($line);
                        
                        if (count($row) < 8 || empty(trim($row[0]))) {
                            continue;
                        }
                        
                        $questionText = trim($row[0]);
                        $answerType = trim($row[1]);
                        $answer1 = trim($row[2]);
                        $answer2 = trim($row[3]);
                        $answer3 = trim($row[4]);
                        $answer4 = trim($row[5]);
                        $correctAnswer = trim($row[6]);
                        $explanation = isset($row[7]) ? trim($row[7]) : "";
                        
                        try {
                            $stmt = $modx->prepare("
                                INSERT INTO modx_test_questions 
                                (test_id, question_text, question_type, explanation, sort_order)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            
                            $stmt->execute([$testId, $questionText, $answerType, $explanation, $imported]);
                            $questionId = $modx->lastInsertId();
                            
                            $answers = [$answer1, $answer2, $answer3, $answer4];
                            $correctAnswers = array_map("trim", explode(",", $correctAnswer));
                            
                            foreach ($answers as $i => $answerText) {
                                if (empty($answerText)) continue;
                                
                                $isCorrect = in_array((string)($i + 1), $correctAnswers) ? 1 : 0;
                                
                                $stmt = $modx->prepare("
                                    INSERT INTO modx_test_answers 
                                    (question_id, answer_text, is_correct, sort_order)
                                    VALUES (?, ?, ?, ?)
                                ");
                                
                                $stmt->execute([$questionId, $answerText, $isCorrect, $i]);
                            }
                            
                            $imported++;
                            
                        } catch (Exception $e) {
                            $errors[] = "Ошибка строка " . ($index + 2) . ": " . $e->getMessage();
                        }
                    }
                    
                    if ($imported > 0) {
                        $message = "Успешно импортировано вопросов: " . $imported;
                        $modx->cacheManager->refresh();
                    } else {
                        $errors[] = "Не удалось импортировать вопросы";
                    }
                }
            }
        }
    }
}

$output = "";

if ($message) {
    $testsUrl = $modx->makeUrl($modx->getOption("lms.tests_page", null, 35));
    
    $output .= "<div class=\"alert alert-success\">";
    $output .= "<h4 class=\"mb-3\">" . htmlspecialchars($message) . "</h4>";
    $output .= "<a href=\"" . $testsUrl . "?category=" . (int)$categoryId . "\" class=\"btn btn-success btn-lg\">Готово! Посмотреть тесты</a>";
    $output .= "</div>";
    
    return $output;
}

if (!empty($errors)) {
    $output .= "<div class=\"alert alert-danger\"><ul class=\"mb-0\">";
    foreach ($errors as $error) {
        $output .= "<li>" . htmlspecialchars($error) . "</li>";
    }
    $output .= "</ul></div>";
}

$siteUrl = $modx->getOption("site_url");
$templateUrl = $siteUrl . "assets/components/testsystem/templates/questions_template.csv";

$output .= "<div class=\"card mb-4\">";
$output .= "<div class=\"card-body\">";
$output .= "<div class=\"alert alert-info\">";
$output .= "<h5>Как импортировать вопросы:</h5>";
$output .= "<ol class=\"mb-2\">";
$output .= "<li>Скачайте шаблон CSV</li>";
$output .= "<li>Откройте в Excel или текстовом редакторе</li>";
$output .= "<li>Замените примеры своими вопросами</li>";
$output .= "<li>Сохраните в формате CSV (UTF-8)</li>";
$output .= "<li>Загрузите через форму ниже</li>";
$output .= "</ol>";
$output .= "<a href=\"" . $templateUrl . "\" class=\"btn btn-primary\" download>Скачать шаблон CSV</a>";
$output .= "</div>";

$output .= "<div class=\"card bg-light mb-4\">";
$output .= "<div class=\"card-header\"><strong>Формат CSV:</strong></div>";
$output .= "<div class=\"card-body\">";
$output .= "<p><code>question_text, answer_type, answer_1, answer_2, answer_3, answer_4, correct_answer, explanation</code></p>";
$output .= "<hr>";
$output .= "<p class=\"mb-1\"><strong>answer_type:</strong></p>";
$output .= "<ul class=\"mb-2\">";
$output .= "<li><code>single</code> - один ответ (correct_answer: <code>2</code>)</li>";
$output .= "<li><code>multiple</code> - несколько ответов (correct_answer: <code>\"1,2,4\"</code> в кавычках!)</li>";
$output .= "<li><code>text</code> - текстовый</li>";
$output .= "</ul>";
$output .= "<p class=\"text-danger mb-0\"><small><strong>Важно:</strong> Для multiple кавычки обязательны!</small></p>";
$output .= "</div>";
$output .= "</div>";

$output .= "</div>";
$output .= "</div>";

$output .= "<div class=\"card\">";
$output .= "<div class=\"card-header bg-primary text-white\">";
$output .= "<h5 class=\"mb-0\">Загрузить CSV</h5>";
$output .= "</div>";
$output .= "<div class=\"card-body\">";

$output .= "<form method=\"POST\" enctype=\"multipart/form-data\">";
$output .= "<input type=\"hidden\" name=\"import_csv\" value=\"1\">";

$output .= "<div class=\"mb-3\">";
$output .= "<label class=\"form-label\">CSV файл</label>";
$output .= "<input type=\"file\" name=\"csv_file\" class=\"form-control\" accept=\".csv\" required>";
$output .= "</div>";

$output .= "<button type=\"submit\" class=\"btn btn-success btn-lg\">Импортировать</button>";

$output .= "</form>";

$output .= "</div>";
$output .= "</div>";

return $output;