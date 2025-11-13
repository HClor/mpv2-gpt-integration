<?php
/**
 * getLearningResourceIds v2.0 - С поддержкой избранного
 * &parentId - ID контейнера с тестами (по умолчанию 35)
 */

$parentId = (int)$modx->getOption('parentId', $scriptProperties, 35);
$categoryId = (int)($_GET['category'] ?? 0);
$showFavorites = isset($_GET['favorites']) && $_GET['favorites'] == '1';
$prefix = $modx->getOption('table_prefix');
$tableTests = $prefix . 'test_tests';
$tableQuestions = $prefix . 'test_questions';
$tableFavorites = $prefix . 'test_favorites';

// Получаем все ID ресурсов с обучающими вопросами + количество вопросов
$sql = "SELECT DISTINCT t.resource_id, COUNT(q.id) as questions_count
        FROM {$tableTests} t
        INNER JOIN {$tableQuestions} q ON q.test_id = t.id
        WHERE t.is_active = 1 
          AND t.resource_id IS NOT NULL
          AND q.is_learning = 1
          AND q.published = 1
        GROUP BY t.resource_id";

$stmt = $modx->prepare($sql);
$stmt->execute();
$allLearningResources = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получаем избранные вопросы текущего пользователя
$favoritesCount = 0;
$favoriteQuestions = [];
if ($modx->user->hasSessionContext('web')) {
    $userId = $modx->user->id;
    
    $sqlFav = "SELECT 
                f.question_id,
                q.question_text,
                q.explanation,
                q.question_image,
                q.explanation_image,
                q.test_id,
                t.title as test_title,
                t.resource_id
            FROM {$tableFavorites} f
            INNER JOIN {$tableQuestions} q ON q.id = f.question_id
            INNER JOIN {$tableTests} t ON t.id = q.test_id
            WHERE f.user_id = ?
            ORDER BY f.added_at DESC";
    
    $stmtFav = $modx->prepare($sqlFav);
    $stmtFav->execute([$userId]);
    $favoriteQuestions = $stmtFav->fetchAll(PDO::FETCH_ASSOC);
    $favoritesCount = count($favoriteQuestions);
}

if (empty($allLearningResources) && empty($favoriteQuestions)) {
    $modx->setPlaceholder('allLearningResourceIds', '');
    $modx->setPlaceholder('filteredLearningResourceIds', '');
    $modx->setPlaceholder('learningCategoryIds', '');
    $modx->setPlaceholder('totalLearningCount', 0);
    $modx->setPlaceholder('learningCategories', '[]');
    $modx->setPlaceholder('learningGroupedResources', '[]');
    $modx->setPlaceholder('learningQuestionsCount', '{}');
    $modx->setPlaceholder('currentLearningCategoryId', 0);
    $modx->setPlaceholder('favoritesCount', 0);
    $modx->setPlaceholder('favoriteQuestions', '[]');
    $modx->setPlaceholder('showFavorites', 0);
    return '';
}

$allLearningResourceIds = array_column($allLearningResources, 'resource_id');

// Создаем map с количеством вопросов для каждого ресурса
$questionsCountMap = [];
foreach ($allLearningResources as $item) {
    $questionsCountMap[$item['resource_id']] = $item['questions_count'];
}

// Получаем категории и считаем материалы в каждой
$categoryCounts = [];
$categoryData = [];
foreach ($allLearningResourceIds as $resId) {
    $resource = $modx->getObject('modResource', $resId);
    if ($resource) {
        $pid = $resource->get('parent');
        
        if (!isset($categoryCounts[$pid])) {
            $categoryCounts[$pid] = 0;
            $parent = $modx->getObject('modResource', $pid);
            if ($parent) {
                $categoryData[$pid] = [
                    'id' => $pid,
                    'pagetitle' => $parent->get('pagetitle'),
                    'menuindex' => $parent->get('menuindex'),
                    'count' => 0
                ];
            }
        }
        $categoryCounts[$pid]++;
        if (isset($categoryData[$pid])) {
            $categoryData[$pid]['count'] = $categoryCounts[$pid];
        }
    }
}

// Сортируем категории по menuindex
uasort($categoryData, function($a, $b) {
    return $a['menuindex'] <=> $b['menuindex'];
});

$categoryIds = array_keys($categoryCounts);

// Фильтрация: Избранное ИЛИ по категории
$filteredResourceIds = $allLearningResourceIds;
$groupedResources = [];

if ($showFavorites) {
    // Показываем избранные вопросы
    if (!empty($favoriteQuestions)) {
        // Группируем избранное по тестам
        $favoritesByTest = [];
        foreach ($favoriteQuestions as $fq) {
            $testTitle = $fq['test_title'] ?: 'Без названия';
            
            if (!isset($favoritesByTest[$testTitle])) {
                $favoritesByTest[$testTitle] = [
                    'id' => 0,
                    'title' => $testTitle,
                    'menuindex' => 0,
                    'resources' => []
                ];
            }
            
            $favoritesByTest[$testTitle]['resources'][] = [
                'id' => $fq['question_id'],
                'pagetitle' => mb_substr(strip_tags($fq['question_text']), 0, 100) . '...',
                'introtext' => '',
                'menuindex' => 0,
                'questions_count' => 1,
                'question_data' => $fq // Полные данные вопроса
            ];
        }
        
        $groupedResources = array_values($favoritesByTest);
    }
} else if ($categoryId > 0) {
    // Фильтрация по категории
    $filteredResourceIds = [];
    foreach ($allLearningResourceIds as $resId) {
        $res = $modx->getObject('modResource', $resId);
        if ($res && $res->get('parent') == $categoryId) {
            $filteredResourceIds[] = $resId;
        }
    }
    
    // Группируем ресурсы по категориям
    foreach ($filteredResourceIds as $resId) {
        $res = $modx->getObject('modResource', $resId);
        if (!$res) continue;
        
        $pid = $res->get('parent');
        $parent = $modx->getObject('modResource', $pid);
        $parentTitle = $parent ? $parent->get('pagetitle') : 'Без категории';
        
        if (!isset($groupedResources[$pid])) {
            $groupedResources[$pid] = [
                'id' => $pid,
                'title' => $parentTitle,
                'menuindex' => $parent ? $parent->get('menuindex') : 999,
                'resources' => []
            ];
        }
        
        $groupedResources[$pid]['resources'][] = [
            'id' => $resId,
            'pagetitle' => $res->get('pagetitle'),
            'introtext' => $res->get('introtext'),
            'menuindex' => $res->get('menuindex'),
            'questions_count' => $questionsCountMap[$resId] ?? 0
        ];
    }
    
    // Сортируем группы и ресурсы
    uasort($groupedResources, function($a, $b) {
        return $a['menuindex'] <=> $b['menuindex'];
    });
    
    foreach ($groupedResources as &$group) {
        usort($group['resources'], function($a, $b) {
            return $a['menuindex'] <=> $b['menuindex'];
        });
    }
    
    $groupedResources = array_values($groupedResources);
} else {
    // Все материалы
    foreach ($filteredResourceIds as $resId) {
        $res = $modx->getObject('modResource', $resId);
        if (!$res) continue;
        
        $pid = $res->get('parent');
        $parent = $modx->getObject('modResource', $pid);
        $parentTitle = $parent ? $parent->get('pagetitle') : 'Без категории';
        
        if (!isset($groupedResources[$pid])) {
            $groupedResources[$pid] = [
                'id' => $pid,
                'title' => $parentTitle,
                'menuindex' => $parent ? $parent->get('menuindex') : 999,
                'resources' => []
            ];
        }
        
        $groupedResources[$pid]['resources'][] = [
            'id' => $resId,
            'pagetitle' => $res->get('pagetitle'),
            'introtext' => $res->get('introtext'),
            'menuindex' => $res->get('menuindex'),
            'questions_count' => $questionsCountMap[$resId] ?? 0
        ];
    }
    
    uasort($groupedResources, function($a, $b) {
        return $a['menuindex'] <=> $b['menuindex'];
    });
    
    foreach ($groupedResources as &$group) {
        usort($group['resources'], function($a, $b) {
            return $a['menuindex'] <=> $b['menuindex'];
        });
    }
    
    $groupedResources = array_values($groupedResources);
}

// Установка плейсхолдеров
$modx->setPlaceholder('allLearningResourceIds', implode(',', $allLearningResourceIds));
$modx->setPlaceholder('filteredLearningResourceIds', implode(',', $filteredResourceIds));
$modx->setPlaceholder('learningCategoryIds', implode(',', $categoryIds));
$modx->setPlaceholder('currentLearningCategoryId', $categoryId);
$modx->setPlaceholder('totalLearningCount', count($allLearningResourceIds));
$modx->setPlaceholder('favoritesCount', $favoritesCount);
$modx->setPlaceholder('showFavorites', $showFavorites ? 1 : 0);

// Для Fenom передаем массивы
$modx->setPlaceholder('learningCategories', json_encode(array_values($categoryData)));
$modx->setPlaceholder('learningGroupedResources', json_encode($groupedResources));
$modx->setPlaceholder('learningQuestionsCount', json_encode($questionsCountMap));
$modx->setPlaceholder('favoriteQuestions', json_encode($favoriteQuestions));

return '';