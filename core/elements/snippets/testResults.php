<?php
/**
 * testResults - Отображение результатов теста
 * Показывает результаты последнего пройденного теста
 */

if (!$modx instanceof modX) {
    return '';
}

$userId = $modx->user->id;

if (!$userId) {
    return '<p class="text-muted">Войдите, чтобы видеть результаты</p>';
}

$prefix = $modx->getOption('table_prefix');

// Получаем ID последней сессии из URL или берем последнюю завершенную
$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

$sql = "
    SELECT
        ts.id,
        ts.test_id,
        ts.score,
        ts.passed,
        ts.started_at,
        ts.completed_at,
        tt.title as test_title,
        tt.passing_score,
        COUNT(DISTINCT tua.id) as answered_questions,
        COUNT(DISTINCT tq.id) as total_questions
    FROM `{$prefix}test_sessions` ts
    LEFT JOIN `{$prefix}test_tests` tt ON tt.id = ts.test_id
    LEFT JOIN `{$prefix}test_questions` tq ON tq.test_id = ts.test_id
    LEFT JOIN `{$prefix}test_user_answers` tua ON tua.session_id = ts.id
    WHERE ts.user_id = " . (int)$userId . " AND ts.status = 'completed'
";

if ($sessionId > 0) {
    $sql .= " AND ts.id = " . (int)$sessionId;
}

$sql .= " GROUP BY ts.id ORDER BY ts.completed_at DESC LIMIT 1";

$stmt = $modx->query($sql);

if ($stmt === false) {
    return '<p class="alert alert-danger">Ошибка при получении результатов</p>';
}

$result = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    return '
        <p class="text-muted mb-3">Нет результатов тестов.</p>
        <a href="' . $modx->makeUrl(35) . '" class="btn btn-primary btn-sm">
            <i class="bi bi-play-circle"></i> Пройти тест
        </a>
    ';
}

// Получаем все ответы пользователя для этой сессии
$sqlAnswers = "
    SELECT
        tq.id,
        tq.question_text,
        tq.question_type,
        ta.text as correct_answer,
        tua.answer_text as user_answer,
        CASE
            WHEN tq.question_type = 'single_choice' AND tua.answer_id = ta.id THEN 1
            WHEN tq.question_type = 'multiple_choice' AND tua.answer_id = ta.id THEN 1
            ELSE 0
        END as is_correct
    FROM `{$prefix}test_questions` tq
    LEFT JOIN `{$prefix}test_answers` ta ON ta.question_id = tq.id AND ta.is_correct = 1
    LEFT JOIN `{$prefix}test_user_answers` tua ON tua.question_id = tq.id AND tua.session_id = " . (int)$result['id'] . "
    WHERE tq.test_id = " . (int)$result['test_id'] . "
    ORDER BY tq.id ASC
";

$stmtAnswers = $modx->query($sqlAnswers);
$answers = [];

if ($stmtAnswers !== false) {
    $answers = $stmtAnswers->fetchAll(PDO::FETCH_ASSOC);
}

$statusClass = $result['passed'] ? 'success' : 'danger';
$statusText = $result['passed'] ? '✓ Пройден' : '✗ Не пройден';

$html = [];
$html[] = '<div class="results-container">';

// Заголовок теста
$html[] = '<div class="card mb-3">';
$html[] = '<div class="card-header">';
$html[] = '<h5 class="mb-0">' . htmlspecialchars($result['test_title']) . '</h5>';
$html[] = '</div>';

// Общие результаты
$html[] = '<div class="card-body">';
$html[] = '<div class="row mb-3">';
$html[] = '<div class="col-md-6">';
$html[] = '<p><strong>Статус:</strong> <span class="badge badge-' . $statusClass . '">' . $statusText . '</span></p>';
$html[] = '<p><strong>Ваш результат:</strong> <span class="text-primary fw-bold">' . (int)$result['score'] . '%</span></p>';
$html[] = '<p><strong>Проходной балл:</strong> ' . (int)$result['passing_score'] . '%</p>';
$html[] = '</div>';
$html[] = '<div class="col-md-6">';
$html[] = '<p><strong>Вопросов ответено:</strong> ' . $result['answered_questions'] . ' из ' . $result['total_questions'] . '</p>';
$html[] = '<p><strong>Дата сдачи:</strong> ' . date('d.m.Y H:i', strtotime($result['completed_at'])) . '</p>';
$html[] = '</div>';
$html[] = '</div>';
$html[] = '</div>';
$html[] = '</div>';

// Список ответов
if (!empty($answers)) {
    $html[] = '<div class="card">';
    $html[] = '<div class="card-header">';
    $html[] = '<h6 class="mb-0">Подробные результаты</h6>';
    $html[] = '</div>';
    $html[] = '<div class="card-body">';

    foreach ($answers as $idx => $answer) {
        $correctClass = $answer['is_correct'] ? 'text-success' : 'text-danger';
        $icon = $answer['is_correct'] ? '✓' : '✗';

        $html[] = '<div class="mb-3 pb-3 border-bottom">';
        $html[] = '<p class="mb-2"><strong>Вопрос ' . ($idx + 1) . ':</strong> ' . htmlspecialchars($answer['question_text']) . '</p>';
        $html[] = '<p class="mb-1 ' . $correctClass . '"><strong>' . $icon . ' Ваш ответ:</strong> ' . htmlspecialchars($answer['user_answer'] ?? '(не ответено)') . '</p>';
        if (!$answer['is_correct']) {
            $html[] = '<p class="mb-0 text-success"><strong>✓ Правильный ответ:</strong> ' . htmlspecialchars($answer['correct_answer'] ?? '') . '</p>';
        }
        $html[] = '</div>';
    }

    $html[] = '</div>';
    $html[] = '</div>';
}

$html[] = '</div>';

return implode('', $html);
