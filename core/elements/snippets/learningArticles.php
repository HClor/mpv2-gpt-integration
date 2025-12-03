<?php
/**
 * learningArticles - Вывод учебных статей с группировкой по категориям
 * Использует test_categories для организации материалов
 */

// Обработка ошибок
try {
    $rootPageId = (int)$modx->getOption('rootPageId', $scriptProperties, 149);

    // ОТЛАДКА: Логируем начало работы
    $debugMode = true; // Включаем отладку
    $debugOutput = '';

    if ($debugMode) {
        $debugOutput .= '<div class="alert alert-info">';
        $debugOutput .= '<strong>ОТЛАДКА learningArticles:</strong><br>';
        $debugOutput .= 'Root Page ID: ' . $rootPageId . '<br>';
    }

    // Получаем все категории из test_categories
    $prefix = $modx->getOption('table_prefix');

    if ($debugMode) {
        $debugOutput .= 'Table prefix: ' . $prefix . '<br>';
    }

    $stmt = $modx->query("
        SELECT id, name, description, sort_order
        FROM {$prefix}test_categories
        ORDER BY sort_order, name
    ");

    if (!$stmt) {
        throw new Exception('Ошибка запроса категорий');
    }

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($debugMode) {
        $debugOutput .= 'Найдено категорий: ' . count($categories) . '<br>';
    }

    // Получаем все дочерние ресурсы (учебные статьи)
    $c = $modx->newQuery('modResource');
    $c->where([
        'parent' => $rootPageId,
        'published' => 1,
        'deleted' => 0,
        'hidemenu' => 0
    ]);
    $c->sortby('publishedon', 'DESC');
    $articles = $modx->getCollection('modResource', $c);

    if ($debugMode) {
        $debugOutput .= 'Найдено дочерних ресурсов: ' . count($articles) . '<br>';
        if (count($articles) > 0) {
            $debugOutput .= '<ul>';
            foreach ($articles as $art) {
                $debugOutput .= '<li>ID: ' . $art->get('id') . ', Название: ' . htmlspecialchars($art->get('pagetitle')) . '</li>';
            }
            $debugOutput .= '</ul>';
        }
        $debugOutput .= '</div>';
    }

    // Группируем статьи по категориям
    // Используем TV поле category_id если есть, иначе показываем в "Без категории"
    $articlesByCategory = [];
    $articlesWithoutCategory = [];

    foreach ($articles as $article) {
        // Получаем TV поле category_id (с обработкой ошибок)
        $categoryIdTV = '';
        try {
            $categoryIdTV = $article->getTVValue('category_id');
        } catch (Exception $e) {
            // TV поле не существует или ошибка доступа - игнорируем
            if ($debugMode) {
                $debugOutput = str_replace('</div>', 'WARNING: TV category_id не найдено<br></div>', $debugOutput);
            }
        }

        $categoryId = $categoryIdTV ? (int)$categoryIdTV : 0;

    $articleData = [
        'id' => $article->get('id'),
        'pagetitle' => $article->get('pagetitle'),
        'introtext' => $article->get('introtext'),
        'publishedon' => $article->get('publishedon'),
        'url' => $modx->makeUrl($article->get('id'))
    ];

    if ($categoryId > 0) {
        if (!isset($articlesByCategory[$categoryId])) {
            $articlesByCategory[$categoryId] = [];
        }
        $articlesByCategory[$categoryId][] = $articleData;
    } else {
        $articlesWithoutCategory[] = $articleData;
    }
}

// Функция для вывода карточки статьи
function renderArticleCard($article, $modx) {
    $publishDate = date('d.m.Y', $article['publishedon']);
    $intro = $article['introtext'] ?: '';
    $introShort = mb_strlen($intro) > 150 ? mb_substr($intro, 0, 150) . '...' : $intro;

    $html = '<div class="col-lg-4 col-md-6 mb-4">';
    $html .= '<div class="card h-100 shadow-sm">';
    $html .= '<div class="card-body">';
    $html .= '<h5 class="card-title">' . htmlspecialchars($article['pagetitle']) . '</h5>';
    if ($introShort) {
        $html .= '<p class="card-text text-muted small">' . htmlspecialchars($introShort) . '</p>';
    }
    $html .= '<div class="mb-2">';
    $html .= '<span class="badge bg-success">';
    $html .= '<i class="bi bi-journal-text me-1"></i>Статья';
    $html .= '</span>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '<div class="card-footer bg-light d-flex justify-content-between align-items-center">';
    $html .= '<a href="' . $article['url'] . '" class="btn btn-sm btn-outline-primary">';
    $html .= '<i class="bi bi-book me-1"></i> Читать';
    $html .= '</a>';
    $html .= '<small class="text-muted">' . $publishDate . '</small>';
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

// Выводим результаты
$output = '';

// Добавляем отладочную информацию
if ($debugMode && !empty($debugOutput)) {
    $output .= $debugOutput;
}

// Если есть статьи с категориями
if (!empty($articlesByCategory)) {
    foreach ($categories as $category) {
        if (isset($articlesByCategory[$category['id']])) {
            $articles = $articlesByCategory[$category['id']];
            $count = count($articles);

            $output .= '<div class="category-section mb-5">';
            $output .= '<h3 class="h4 mb-3 pb-2 border-bottom">';
            $output .= '<i class="bi bi-folder me-2 text-success"></i>';
            $output .= htmlspecialchars($category['name']);
            $output .= ' <span class="badge bg-secondary">' . $count . '</span>';
            $output .= '</h3>';

            if (!empty($category['description'])) {
                $output .= '<p class="text-muted mb-3">' . htmlspecialchars($category['description']) . '</p>';
            }

            $output .= '<div class="row">';
            foreach ($articles as $article) {
                $output .= renderArticleCard($article, $modx);
            }
            $output .= '</div>';
            $output .= '</div>';
        }
    }
}

// Статьи без категории
if (!empty($articlesWithoutCategory)) {
    $output .= '<div class="category-section mb-5">';
    $output .= '<h3 class="h4 mb-3 pb-2 border-bottom">';
    $output .= '<i class="bi bi-folder-x me-2 text-muted"></i>';
    $output .= 'Без категории';
    $output .= ' <span class="badge bg-secondary">' . count($articlesWithoutCategory) . '</span>';
    $output .= '</h3>';
    $output .= '<div class="row">';
    foreach ($articlesWithoutCategory as $article) {
        $output .= renderArticleCard($article, $modx);
    }
    $output .= '</div>';
    $output .= '</div>';
}

    // Если вообще нет статей
    if (empty($articlesByCategory) && empty($articlesWithoutCategory)) {
        $output .= '<div class="alert alert-info">';
        $output .= '<i class="bi bi-info-circle me-2"></i>';
        $output .= 'Учебных статей пока нет.';
        $output .= '</div>';
    }

    return $output;

} catch (Exception $e) {
    // Ловим все ошибки и выводим красиво
    $errorOutput = '<div class="alert alert-danger">';
    $errorOutput .= '<h5><i class="bi bi-exclamation-triangle me-2"></i>Ошибка в learningArticles</h5>';
    $errorOutput .= '<p><strong>Сообщение:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    $errorOutput .= '<p><strong>Файл:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
    $errorOutput .= '<p><strong>Строка:</strong> ' . $e->getLine() . '</p>';
    $errorOutput .= '<details><summary>Stack trace</summary><pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre></details>';
    $errorOutput .= '</div>';

    return $errorOutput;
}
