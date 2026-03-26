<?php
/**
 * lmsBreadcrumbs
 *
 * Единый builder внутренних breadcrumbs для LMS (без зависимости от дерева MODX).
 *
 * Базовое использование:
 * [[!lmsBreadcrumbs]]
 *
 * Явный контекст (опционально):
 * [[!lmsBreadcrumbs?
 *   &section=`tests|learning_paths|handbook`
 *   &mode=`list|view|run|result|study`
 *   &category_id=`12`
 *   &test_id=`77`
 *   &path_id=`4`
 *   &step_id=`15`
 *   &handbook_section_id=`81`
 * ]]
 *
 * Поддержка preloaded сущностей (чтобы не делать повторные SQL):
 *   &entities=`{"test:77":{"id":77,"title":"...","category_id":12}}`
 *
 * Как расширять:
 * 1) Добавить новый resolver в LmsBreadcrumbsBuilder::$resolvers
 * 2) Добавить detectSection() правило
 * 3) При необходимости добавить getter новой сущности
 */

require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

if (!$modx instanceof modX) {
    return '';
}

$context = [
    'section' => $modx->getOption('section', $scriptProperties, ''),
    'mode' => $modx->getOption('mode', $scriptProperties, ''),
    'action' => $modx->getOption('action', $scriptProperties, ''),
    'category_id' => (int)$modx->getOption('category_id', $scriptProperties, 0),
    'test_id' => (int)$modx->getOption('test_id', $scriptProperties, 0),
    'path_id' => (int)$modx->getOption('path_id', $scriptProperties, 0),
    'step_id' => (int)$modx->getOption('step_id', $scriptProperties, 0),
    'handbook_section_id' => (int)$modx->getOption('handbook_section_id', $scriptProperties, 0),
    'session_id' => (int)$modx->getOption('session_id', $scriptProperties, 0),
];

$preloaded = [];
$entitiesJson = (string)$modx->getOption('entities', $scriptProperties, '');
if ($entitiesJson !== '') {
    $decoded = json_decode($entitiesJson, true);
    if (is_array($decoded)) {
        $preloaded = $decoded;
    }
}

$builder = new LmsBreadcrumbsBuilder($modx, $context, $preloaded);
$items = $builder->build();

if (empty($items)) {
    return '';
}

$output = '<nav aria-label="LMS breadcrumb" class="ts-lms-breadcrumb mb-3">';
$output .= '<ol class="breadcrumb small mb-0">';

foreach ($items as $item) {
    $title = htmlspecialchars((string)$item['title'], ENT_QUOTES, 'UTF-8');
    $url = isset($item['url']) ? (string)$item['url'] : '';
    $isCurrent = !empty($item['current']);

    if (!$isCurrent && $url !== '') {
        $output .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $title . '</a></li>';
    } elseif ($isCurrent) {
        if ($url !== '') {
            $output .= '<li class="breadcrumb-item active" aria-current="page"><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $title . '</a></li>';
        } else {
            $output .= '<li class="breadcrumb-item active" aria-current="page">' . $title . '</li>';
        }
    } else {
        $output .= '<li class="breadcrumb-item">' . $title . '</li>';
    }
}

$output .= '</ol>';
$output .= '</nav>';

return $output;
