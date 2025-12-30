<?php
/**
 * Диагностический скрипт для проверки результатов тестов пользователя
 *
 * Для консоли MODX (ID 1):
 * Скопируйте и вставьте в консоль MODX (требуется авторизация с ID 1)
 *
 * Проверяет:
 * - Записи в test_sessions для пользователя ID 2
 * - Поля status, score, passed, finished_at
 * - Связь с траекториями обучения
 * - Достижения и сертификаты
 */

// ID пользователя для диагностики
$targetUserId = 2;

$prefix = $modx->getOption('table_prefix');
$output = [];
$output[] = "=== ДИАГНОСТИКА РЕЗУЛЬТАТОВ ТЕСТОВ ДЛЯ ПОЛЬЗОВАТЕЛЯ ID: {$targetUserId} ===\n";

// 1. Проверяем записи в test_sessions
$output[] = "\n1. ЗАПИСИ В ТАБЛИЦЕ test_sessions:";
$output[] = str_repeat("-", 80);

$sql = "SELECT
    ts.id as session_id,
    ts.test_id,
    ts.status,
    ts.score,
    ts.passed,
    ts.started_at,
    ts.finished_at,
    tt.title as test_title,
    TIMESTAMPDIFF(SECOND, ts.started_at, ts.finished_at) as time_spent
FROM `{$prefix}test_sessions` ts
LEFT JOIN `{$prefix}test_tests` tt ON tt.id = ts.test_id
WHERE ts.user_id = ?
ORDER BY ts.started_at DESC
LIMIT 10";

$stmt = $modx->prepare($sql);
$stmt->execute([$targetUserId]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($sessions)) {
    $output[] = "⚠️  НЕТ ЗАПИСЕЙ В test_sessions для пользователя ID {$targetUserId}";
} else {
    $output[] = "Найдено записей: " . count($sessions);
    foreach ($sessions as $session) {
        $output[] = "\nСессия ID: {$session['session_id']}";
        $output[] = "  Тест: {$session['test_title']} (ID: {$session['test_id']})";
        $output[] = "  Статус: {$session['status']}";
        $output[] = "  Балл: " . ($session['score'] ?? 'NULL') . "%";
        $output[] = "  Пройден (passed): " . ($session['passed'] ? 'ДА (1)' : 'НЕТ (0)');
        $output[] = "  Начат: " . ($session['started_at'] ?? 'NULL');
        $output[] = "  Завершён: " . ($session['finished_at'] ?? 'NULL');
        $output[] = "  Время: " . ($session['time_spent'] ? gmdate('i:s', $session['time_spent']) : 'NULL');

        // Проверяем условия для отображения в testHistory
        $showInHistory = ($session['status'] === 'completed' ||
                         $session['finished_at'] !== null ||
                         $session['score'] !== null);
        $output[] = "  ✓ Отобразится в testHistory: " . ($showInHistory ? 'ДА' : 'НЕТ');

        // Проверяем условия для подсчёта как пройденный в userProfile
        $countAsPassed = ($session['status'] === 'completed' && (int)$session['score'] >= 70);
        $output[] = "  ✓ Считается пройденным в userProfile: " . ($countAsPassed ? 'ДА' : 'НЕТ');

        // Проблемы
        if ($session['score'] == 100 && !$session['passed']) {
            $output[] = "  🔴 ПРОБЛЕМА: Балл 100%, но поле 'passed' = 0!";
        }
        if ($session['status'] !== 'completed' && $session['finished_at']) {
            $output[] = "  🔴 ПРОБЛЕМА: Тест завершён (finished_at есть), но status != 'completed'!";
        }
    }
}

// 2. Проверяем записи в траекториях обучения
$output[] = "\n\n2. ЗАПИСИ В ТРАЕКТОРИЯХ ОБУЧЕНИЯ:";
$output[] = str_repeat("-", 80);

$sql = "SELECT
    e.id as enrollment_id,
    e.path_id,
    e.enrolled_at,
    e.is_active,
    lp.name as path_name,
    p.id as progress_id,
    p.status as progress_status,
    p.completion_pct,
    p.certificate_issued,
    p.certificate_issued_at
FROM `{$prefix}test_learning_path_enrollments` e
JOIN `{$prefix}test_learning_paths` lp ON lp.id = e.path_id
LEFT JOIN `{$prefix}test_learning_path_progress` p ON p.enrollment_id = e.id
WHERE e.user_id = ?
ORDER BY e.enrolled_at DESC";

$stmt = $modx->prepare($sql);
$stmt->execute([$targetUserId]);
$enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($enrollments)) {
    $output[] = "⚠️  НЕТ ЗАПИСЕЙ на траектории для пользователя ID {$targetUserId}";
} else {
    $output[] = "Найдено записей: " . count($enrollments);
    foreach ($enrollments as $enroll) {
        $output[] = "\nТраектория: {$enroll['path_name']} (ID: {$enroll['path_id']})";
        $output[] = "  Enrollment ID: {$enroll['enrollment_id']}";
        $output[] = "  Активна: " . ($enroll['is_active'] ? 'ДА' : 'НЕТ');
        $output[] = "  Записан: {$enroll['enrolled_at']}";

        if ($enroll['progress_id']) {
            $output[] = "  Progress ID: {$enroll['progress_id']}";
            $output[] = "  Статус прогресса: {$enroll['progress_status']}";
            $output[] = "  Завершение: {$enroll['completion_pct']}%";
            $output[] = "  Сертификат выдан: " . ($enroll['certificate_issued'] ? 'ДА' : 'НЕТ');
            if ($enroll['certificate_issued']) {
                $output[] = "  Дата выдачи: " . ($enroll['certificate_issued_at'] ?? 'NULL');
            }

            // Проверяем шаги
            $stepsSql = "SELECT
                step_id,
                status,
                score,
                completed_at,
                session_id
            FROM `{$prefix}test_learning_path_step_completion`
            WHERE progress_id = ?
            ORDER BY step_id";

            $stepsStmt = $modx->prepare($stepsSql);
            $stepsStmt->execute([$enroll['progress_id']]);
            $steps = $stepsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($steps)) {
                $output[] = "  Шагов: " . count($steps);
                $completed = count(array_filter($steps, fn($s) => $s['status'] === 'completed'));
                $output[] = "  Завершено шагов: {$completed}";

                foreach ($steps as $step) {
                    $output[] = "    - Шаг ID: {$step['step_id']}, Статус: {$step['status']}, " .
                               "Балл: " . ($step['score'] ?? 'NULL') . ", " .
                               "Session ID: " . ($step['session_id'] ?? 'NULL');
                }
            }
        } else {
            $output[] = "  ⚠️  НЕТ ЗАПИСИ В test_learning_path_progress!";
        }
    }
}

// 3. Проверяем достижения
$output[] = "\n\n3. ДОСТИЖЕНИЯ ПОЛЬЗОВАТЕЛЯ:";
$output[] = str_repeat("-", 80);

$sql = "SELECT
    lpua.id,
    lpua.achievement_id,
    lpua.earned_at,
    lpa.name as achievement_name,
    lpa.condition_type,
    lpa.condition_value,
    lp.name as path_name
FROM `{$prefix}test_learning_path_user_achievements` lpua
JOIN `{$prefix}test_learning_path_achievements` lpa ON lpa.id = lpua.achievement_id
JOIN `{$prefix}test_learning_paths` lp ON lp.id = lpa.path_id
WHERE lpua.user_id = ?
ORDER BY lpua.earned_at DESC";

$stmt = $modx->prepare($sql);
$stmt->execute([$targetUserId]);
$achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($achievements)) {
    $output[] = "⚠️  НЕТ ДОСТИЖЕНИЙ для пользователя ID {$targetUserId}";
} else {
    $output[] = "Найдено достижений: " . count($achievements);
    foreach ($achievements as $ach) {
        $output[] = "\nДостижение: {$ach['achievement_name']}";
        $output[] = "  Траектория: {$ach['path_name']}";
        $output[] = "  Условие: {$ach['condition_type']} = " . ($ach['condition_value'] ?? 'NULL');
        $output[] = "  Получено: {$ach['earned_at']}";
    }
}

// 4. Проверяем сертификаты
$output[] = "\n\n4. СЕРТИФИКАТЫ ПОЛЬЗОВАТЕЛЯ:";
$output[] = str_repeat("-", 80);

$sql = "SELECT
    c.id,
    c.certificate_number,
    c.issued_at,
    c.status,
    c.score,
    lp.name as path_name
FROM `{$prefix}test_certificates` c
LEFT JOIN `{$prefix}test_learning_paths` lp ON lp.id = c.path_id
WHERE c.user_id = ?
ORDER BY c.issued_at DESC";

$stmt = $modx->prepare($sql);
$stmt->execute([$targetUserId]);
$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($certificates)) {
    $output[] = "⚠️  НЕТ СЕРТИФИКАТОВ в таблице test_certificates";
} else {
    $output[] = "Найдено сертификатов: " . count($certificates);
    foreach ($certificates as $cert) {
        $output[] = "\nСертификат: {$cert['certificate_number']}";
        $output[] = "  Траектория: " . ($cert['path_name'] ?? 'NULL');
        $output[] = "  Статус: {$cert['status']}";
        $output[] = "  Балл: " . ($cert['score'] ?? 'NULL') . "%";
        $output[] = "  Выдан: {$cert['issued_at']}";
    }
}

// 5. Резюме и рекомендации
$output[] = "\n\n5. РЕЗЮМЕ И РЕКОМЕНДАЦИИ:";
$output[] = str_repeat("-", 80);

// Проверяем основные проблемы
$hasCompletedTests = false;
$has100PercentTest = false;
$hasMismatchedPassedFlag = false;

foreach ($sessions as $session) {
    if ($session['status'] === 'completed') {
        $hasCompletedTests = true;
    }
    if ((int)$session['score'] >= 100) {
        $has100PercentTest = true;
        if (!$session['passed']) {
            $hasMismatchedPassedFlag = true;
        }
    }
}

if (!$hasCompletedTests) {
    $output[] = "🔴 ПРОБЛЕМА: Нет тестов со статусом 'completed'";
    $output[] = "   РЕШЕНИЕ: Обновить status в test_sessions на 'completed'";
}

if ($hasMismatchedPassedFlag) {
    $output[] = "🔴 ПРОБЛЕМА: Тест(ы) с баллом 100%, но поле 'passed' = 0";
    $output[] = "   РЕШЕНИЕ: Обновить поле 'passed' в test_sessions на 1";
    $output[] = "   SQL: UPDATE `{$prefix}test_sessions` SET `passed` = 1 WHERE user_id = {$targetUserId} AND score >= 70;";
}

if (empty($enrollments)) {
    $output[] = "⚠️  ИНФОРМАЦИЯ: Пользователь не записан ни на одну траекторию обучения";
    $output[] = "   Достижения и сертификаты траекторий недоступны без записи на траекторию";
}

if ($has100PercentTest && empty($achievements)) {
    $output[] = "⚠️  ВОЗМОЖНАЯ ПРОБЛЕМА: Тест пройден на 100%, но нет достижений";
    $output[] = "   Проверьте, связан ли тест с траекторией обучения";
}

$output[] = "\n=== КОНЕЦ ДИАГНОСТИКИ ===";

// Выводим результат
return implode("\n", $output);
