<?php
/**
 * Сниппет: getSystemStats - Статистика системы
 * Вызывается из: tsFooter.tpl
 * Назначение: Выводит общую статистику системы (пользователи, тесты, прохождения, средний балл)
 *
 * Параметры:
 * - &tpl - шаблон для вывода (опционально). Если указан, используются плейсхолдеры:
 *   [[+total_users]], [[+total_tests]], [[+total_sessions]], [[+avg_score]],
 *   [[+total_questions]], [[+total_categories]]
 *
 * @package TestSystem
 */

if (!$modx instanceof modX) {
    return '';
}

$tpl = $modx->getOption('tpl', $scriptProperties, '');
$prefix = $modx->getOption('table_prefix');

$Ttests = $prefix . 'test_tests';
$Tquestions = $prefix . 'test_questions';
$Tcats = $prefix . 'test_categories';
$Tsessions = $prefix . 'test_sessions';
$Tusers = $prefix . 'users';
$S = $prefix . 'site_content';

// Получаем статистику
$stats = [];

// Количество активных пользователей (у которых есть хотя бы 1 сессия)
$stmt = $modx->prepare("
    SELECT COUNT(DISTINCT user_id)
    FROM `{$Tsessions}`
    WHERE user_id > 0
");
$stmt->execute();
$stats['total_users'] = (int)$stmt->fetchColumn();

// Количество активных тестов
$stmt = $modx->prepare("
    SELECT COUNT(DISTINCT t.id)
    FROM `{$Ttests}` t
    INNER JOIN `{$S}` sc ON sc.id = t.resource_id
    WHERE t.is_active = 1
        AND sc.published = 1
        AND sc.deleted = 0
");
$stmt->execute();
$stats['total_tests'] = (int)$stmt->fetchColumn();

// Количество вопросов
$stmt = $modx->prepare("SELECT COUNT(*) FROM `{$Tquestions}`");
$stmt->execute();
$stats['total_questions'] = (int)$stmt->fetchColumn();

// Количество категорий
$stmt = $modx->prepare("SELECT COUNT(*) FROM `{$Tcats}`");
$stmt->execute();
$stats['total_categories'] = (int)$stmt->fetchColumn();

// Количество завершенных сессий
$stmt = $modx->prepare("SELECT COUNT(*) FROM `{$Tsessions}` WHERE status = 'completed'");
$stmt->execute();
$stats['total_sessions'] = (int)$stmt->fetchColumn();

// Средний балл по всем завершённым сессиям
$stmt = $modx->prepare("
    SELECT ROUND(AVG(score), 1)
    FROM `{$Tsessions}`
    WHERE status = 'completed' AND score IS NOT NULL
");
$stmt->execute();
$avgScore = $stmt->fetchColumn();
$stats['avg_score'] = $avgScore !== null ? (float)$avgScore : 0;

// Если указан шаблон - используем его
if (!empty($tpl)) {
    // Парсим inline шаблон или чанк
    $chunk = $modx->newObject('modChunk');
    $chunk->setCacheable(false);

    // Убираем @INLINE если есть
    $tplContent = $tpl;
    if (strpos($tpl, '@INLINE') === 0) {
        $tplContent = trim(substr($tpl, 7));
    }

    $chunk->setContent($tplContent);
    return $chunk->process($stats);
}

// Если шаблон не указан - возвращаем дефолтный HTML
$html = [];
$html[] = '<div class="stats-widget">';
$html[] = '<div class="row g-3 text-center">';

$html[] = '<div class="col-6">';
$html[] = '<div class="stat-item p-3 bg-white bg-opacity-10 rounded">';
$html[] = '<div class="stat-number display-4 fw-bold">' . $stats['total_tests'] . '</div>';
$html[] = '<div class="stat-label">Тестов</div>';
$html[] = '</div>';
$html[] = '</div>';

$html[] = '<div class="col-6">';
$html[] = '<div class="stat-item p-3 bg-white bg-opacity-10 rounded">';
$html[] = '<div class="stat-number display-4 fw-bold">' . $stats['total_questions'] . '</div>';
$html[] = '<div class="stat-label">Вопросов</div>';
$html[] = '</div>';
$html[] = '</div>';

$html[] = '<div class="col-6">';
$html[] = '<div class="stat-item p-3 bg-white bg-opacity-10 rounded">';
$html[] = '<div class="stat-number display-4 fw-bold">' . $stats['total_users'] . '</div>';
$html[] = '<div class="stat-label">Участников</div>';
$html[] = '</div>';
$html[] = '</div>';

$html[] = '<div class="col-6">';
$html[] = '<div class="stat-item p-3 bg-white bg-opacity-10 rounded">';
$html[] = '<div class="stat-number display-4 fw-bold">' . number_format($stats['total_sessions'], 0, ',', ' ') . '</div>';
$html[] = '<div class="stat-label">Попыток</div>';
$html[] = '</div>';
$html[] = '</div>';

$html[] = '</div>';
$html[] = '</div>';

return implode('', $html);
