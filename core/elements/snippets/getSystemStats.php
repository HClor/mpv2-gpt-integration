<?php
/**
 * getSystemStats - выводит общую статистику системы
 */

if (!$modx instanceof modX) {
    return '';
}

$prefix = $modx->getOption('table_prefix');
$Ttests = $prefix . 'test_tests';
$Tquestions = $prefix . 'test_questions';
$Tcats = $prefix . 'test_categories';
$Tsessions = $prefix . 'test_sessions';
$S = $prefix . 'site_content';

// Получаем статистику
$stats = [];

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
$stats['tests'] = (int)$stmt->fetchColumn();

// Количество вопросов
$stmt = $modx->prepare("SELECT COUNT(*) FROM `{$Tquestions}`");
$stmt->execute();
$stats['questions'] = (int)$stmt->fetchColumn();

// Количество категорий
$stmt = $modx->prepare("SELECT COUNT(*) FROM `{$Tcats}`");
$stmt->execute();
$stats['categories'] = (int)$stmt->fetchColumn();

// Количество завершенных попыток
$stmt = $modx->prepare("SELECT COUNT(*) FROM `{$Tsessions}` WHERE status = 'completed'");
$stmt->execute();
$stats['sessions'] = (int)$stmt->fetchColumn();

$html = [];
$html[] = '<div class="stats-widget">';
$html[] = '<div class="row g-3 text-center">';

$html[] = '<div class="col-6">';
$html[] = '<div class="stat-item p-3 bg-white bg-opacity-10 rounded">';
$html[] = '<div class="stat-number display-4 fw-bold">' . $stats['tests'] . '</div>';
$html[] = '<div class="stat-label">Тестов</div>';
$html[] = '</div>';
$html[] = '</div>';

$html[] = '<div class="col-6">';
$html[] = '<div class="stat-item p-3 bg-white bg-opacity-10 rounded">';
$html[] = '<div class="stat-number display-4 fw-bold">' . $stats['questions'] . '</div>';
$html[] = '<div class="stat-label">Вопросов</div>';
$html[] = '</div>';
$html[] = '</div>';

$html[] = '<div class="col-6">';
$html[] = '<div class="stat-item p-3 bg-white bg-opacity-10 rounded">';
$html[] = '<div class="stat-number display-4 fw-bold">' . $stats['categories'] . '</div>';
$html[] = '<div class="stat-label">Категорий</div>';
$html[] = '</div>';
$html[] = '</div>';

$html[] = '<div class="col-6">';
$html[] = '<div class="stat-item p-3 bg-white bg-opacity-10 rounded">';
$html[] = '<div class="stat-number display-4 fw-bold">' . number_format($stats['sessions'], 0, ',', ' ') . '</div>';
$html[] = '<div class="stat-label">Попыток</div>';
$html[] = '</div>';
$html[] = '</div>';

$html[] = '</div>';
$html[] = '</div>';

return implode('', $html);