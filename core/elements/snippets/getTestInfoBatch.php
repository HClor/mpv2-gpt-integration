<?php
/**
 * getTestInfoBatch - получает информацию о тестах одним запросом
 * Поддерживает pdoPage для пагинации
 */
if (!$modx instanceof modX) {
    return '';
}

$parents = $scriptProperties['parents'] ?? $modx->resource->id;
$depth = (int)($scriptProperties['depth'] ?? 3);
$limit = (int)($scriptProperties['limit'] ?? 10);
$offset = (int)($scriptProperties['offset'] ?? 0);
$tpl = $scriptProperties['tpl'] ?? 'testCard';
$sortby = $scriptProperties['sortby'] ?? 'createdon';
$sortdir = strtoupper($scriptProperties['sortdir'] ?? 'DESC');
$totalVar = $scriptProperties['totalVar'] ?? 'total';

// Валидация sortdir
if (!in_array($sortdir, ['ASC', 'DESC'])) {
    $sortdir = 'DESC';
}

// Валидация sortby
$allowedSortFields = ['createdon', 'publishedon', 'pagetitle', 'menuindex', 'id'];
if (!in_array($sortby, $allowedSortFields)) {
    $sortby = 'createdon';
}

$prefix = $modx->getOption('table_prefix');
$Ttests = $prefix . 'test_tests';
$Tquestions = $prefix . 'test_questions';
$S = $prefix . 'site_content';

// Получаем все дочерние ID с учетом depth
$parentIds = [$parents];
if ($depth > 0) {
    $childIds = $modx->getChildIds($parents, $depth);
    if (!empty($childIds)) {
        $parentIds = array_merge($parentIds, $childIds);
    }
}

$parentIdsStr = implode(',', $parentIds);

// ИСПРАВЛЕНО: Добавлен фильтр по publication_status
$sql_where_publication = "AND t.publication_status = 'public'";

// Получаем общее количество для пагинации
$sqlCount = "
    SELECT COUNT(DISTINCT sc.id) as total
    FROM `{$S}` sc
    INNER JOIN `{$Ttests}` t ON t.resource_id = sc.id
    WHERE sc.id IN ({$parentIdsStr})
        AND sc.published = 1
        AND sc.deleted = 0
        AND t.is_active = 1
        {$sql_where_publication}
";

$stmtCount = $modx->prepare($sqlCount);
$stmtCount->execute();
$totalCount = (int)$stmtCount->fetchColumn();

// Устанавливаем переменную для pdoPage
$modx->setPlaceholder($totalVar, $totalCount);

// Основной запрос с данными
$sql = "
    SELECT 
        sc.id,
        sc.pagetitle,
        sc.longtitle,
        sc.introtext,
        sc.content,
        sc.uri,
        sc.createdon,
        sc.publishedon,
        t.id as test_id,
        t.questions_per_session,
        t.pass_score,
        t.mode,
        t.publication_status,
        COUNT(q.id) as question_count
    FROM `{$S}` sc
    INNER JOIN `{$Ttests}` t ON t.resource_id = sc.id
    LEFT JOIN `{$Tquestions}` q ON q.test_id = t.id
    WHERE sc.id IN ({$parentIdsStr})
        AND sc.published = 1
        AND sc.deleted = 0
        AND t.is_active = 1
        {$sql_where_publication}
    GROUP BY sc.id, t.id
    ORDER BY sc.{$sortby} {$sortdir}
    LIMIT {$limit} OFFSET {$offset}
";

$stmt = $modx->prepare($sql);
$stmt->execute();
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tests)) {
    return '<div class="alert alert-info">Тесты не найдены</div>';
}

$output = [];
$idx = $offset + 1;

foreach ($tests as $test) {
    // Формируем URL
    $testUrl = $modx->makeUrl((int)$test['id']);
    
    // Форматируем дату
    $createdDate = $test['createdon'] ? date('d.m.Y', $test['createdon']) : '';
    $publishedDate = $test['publishedon'] ? date('d.m.Y', $test['publishedon']) : '';
    
    // Подготавливаем данные для чанка
    $placeholders = [
        'id' => $test['id'],
        'test_id' => $test['test_id'],
        'pagetitle' => $test['pagetitle'],
        'longtitle' => $test['longtitle'],
        'introtext' => $test['introtext'],
        'content' => $test['content'],
        'uri' => $test['uri'],
        'url' => $testUrl,
        'createdon' => $createdDate,
        'publishedon' => $publishedDate,
        'testQuestions' => (int)$test['question_count'],
        'testQuestionsPerSession' => (int)$test['questions_per_session'],
        'testPassScore' => (int)$test['pass_score'],
        'testMode' => $test['mode'],
        'publication_status' => $test['publication_status'],
        'idx' => $idx++,
        'total' => $totalCount
    ];
    
    $output[] = $modx->getChunk($tpl, $placeholders);
}

return implode('', $output);