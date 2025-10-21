<?php
/* TS TEST RUNNER v2.0 WITH MODE SELECTION */

//$testId = $scriptProperties["testId"] ?? $modx->getOption("lms.demo_test_id", null, 0);

$testId = (int)$modx->getOption('test_id', $scriptProperties,
    $modx->getOption('lms.demo_test_id', null, 0)
);

if (!$testId) {
    return "<div class=\"alert alert-danger\">Тест не найден</div>";
}

// Проверяем авторизацию
if (!$modx->user->hasSessionContext("web")) {
    $authId = $modx->getOption("lms.auth_page", null, 0);
    $authUrl = $modx->makeUrl($authId, "web", "", "full");
    return "<div class=\"alert alert-warning\">
        <p>Для прохождения теста необходимо <a href=\"" . $authUrl . "\">войти в систему</a>.</p>
    </div>";
}

// Получаем информацию о тесте
$stmt = $modx->prepare("
    SELECT id, title, description, mode, time_limit, pass_score, questions_per_session
    FROM modx_test_tests 
    WHERE id = ? AND is_active = 1
");
$stmt->execute([$testId]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    return "<div class=\"alert alert-danger\">Тест не найден или неактивен</div>";
}

$stmt = $modx->prepare("SELECT COUNT(*) FROM modx_test_questions WHERE test_id = ?");
$stmt->execute([$testId]);
$totalQuestions = $stmt->fetchColumn();
$questionsPerSession = $test["questions_per_session"] ?? 20;

$output = "<div id=\"test-container\" data-test-id=\"" . $testId . "\">
    <div id=\"test-info\" class=\"card mb-4\">
        <div class=\"card-header\">
            <h2>" . htmlspecialchars($test["title"]) . "</h2>
        </div>
        <div class=\"card-body\">
            <p>" . htmlspecialchars($test["description"]) . "</p>
            <ul class=\"list-unstyled\">
                <li><strong>Вопросов в банке:</strong> " . $totalQuestions . "</li>
                <li><strong>Вопросов за попытку:</strong> " . $questionsPerSession . "</li>
                <li><strong>Проходной балл:</strong> " . $test["pass_score"] . "%</li>
                " . ($test["time_limit"] > 0 ? "<li><strong>Время:</strong> " . $test["time_limit"] . " минут</li>" : "") . "
            </ul>
            
            <hr>
            
            <h5>Выберите режим прохождения:</h5>
            <div class=\"row mt-3\">
                <div class=\"col-md-6 mb-3\">
                    <div class=\"card h-100 border-primary\">
                        <div class=\"card-body\">
                            <h5 class=\"card-title\">Режим обучения (Training)</h5>
                            <p class=\"card-text\">
                                <strong>Особенности:</strong><br>
                                - Показываются правильные ответы<br>
                                - Есть объяснения после каждого вопроса<br>
                                - Можно учиться на ошибках<br>
                                - Результат не влияет на рейтинг
                            </p>
                            <button class=\"btn btn-primary w-100 start-test-btn\" data-mode=\"training\">
                                Начать в режиме Training
                            </button>
                        </div>
                    </div>
                </div>
                <div class=\"col-md-6 mb-3\">
                    <div class=\"card h-100 border-danger\">
                        <div class=\"card-body\">
                            <h5 class=\"card-title\">Режим экзамена (Exam)</h5>
                            <p class=\"card-text\">
                                <strong>Особенности:</strong><br>
                                - Правильные ответы не показываются<br>
                                - Результат только в конце теста<br>
                                - Более строгая проверка знаний<br>
                                - Засчитывается в рейтинг
                            </p>
                            <button class=\"btn btn-danger w-100 start-test-btn\" data-mode=\"exam\">
                                Начать в режиме Exam
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div id=\"question-container\" style=\"display:none;\">
        <div class=\"card mb-3\">
            <div class=\"card-header d-flex justify-content-between align-items-center\">
                <span id=\"question-progress\">Вопрос <span id=\"current-q\">1</span> из <span id=\"total-q\">" . $questionsPerSession . "</span></span>
                <span id=\"mode-badge\" class=\"badge\"></span>
            </div>
            <div class=\"card-body\">
                <h4 id=\"question-text\"></h4>
                <div id=\"answer-options\" class=\"mt-3\"></div>
                <div id=\"explanation-block\" class=\"alert alert-info mt-3\" style=\"display:none;\"></div>
            </div>
            <div class=\"card-footer\">
                <button id=\"submit-answer-btn\" class=\"btn btn-primary\" disabled>Ответить</button>
                <button id=\"next-question-btn\" class=\"btn btn-success\" style=\"display:none;\">Следующий вопрос</button>
            </div>
        </div>
    </div>
    
    <div id=\"results-container\" style=\"display:none;\">
        <div class=\"card\">
            <div class=\"card-header\">
                <h2>Результаты теста</h2>
            </div>
            <div class=\"card-body text-center\">
                <h3 id=\"final-score\" class=\"mb-3\"></h3>
                <p id=\"result-message\" class=\"lead\"></p>
                <div id=\"result-details\" class=\"mt-3\"></div>
                <a href=\"" . $modx->makeUrl($modx->resource->id) . "\" class=\"btn btn-primary mt-3\">Пройти еще раз</a>
                <a href=\"" . $modx->makeUrl($modx->getOption("lms.test_page", null, 0)) . "\" class=\"btn btn-secondary mt-3\">К списку тестов</a>
            </div>
        </div>
    </div>
</div>

<link rel=\"stylesheet\" href=\"/assets/components/testsystem/css/tsrunner.css\">
<script src=\"/assets/components/testsystem/js/tsrunner.js\"></script>";

return $output;