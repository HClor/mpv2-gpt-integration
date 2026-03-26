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
 *   &debug=`1`                     // включает DIAG-логирование и debug-блок
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

$urlParams = [];
if (!empty($_SERVER['QUERY_STRING'])) {
    parse_str((string)$_SERVER['QUERY_STRING'], $urlParams);
}

$valueFromSources = static function (string $key, $default = '') use ($modx, $scriptProperties, $urlParams) {
    $snippetValue = $modx->getOption($key, $scriptProperties, null);
    if ($snippetValue !== null && $snippetValue !== '') {
        return $snippetValue;
    }
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        return $_GET[$key];
    }
    if (isset($urlParams[$key]) && $urlParams[$key] !== '') {
        return $urlParams[$key];
    }
    if (isset($_REQUEST[$key]) && $_REQUEST[$key] !== '') {
        return $_REQUEST[$key];
    }

    return $default;
};

$debugValue = $valueFromSources('debug', null);
if ($debugValue === null || $debugValue === '') {
    $debugValue = $valueFromSources('lms_bc_diag', 0);
}
if ((int)$debugValue !== 1 && isset($_SERVER['REQUEST_URI']) && strpos((string)$_SERVER['REQUEST_URI'], 'lms_bc_diag=1') !== false) {
    $debugValue = 1;
}

$context = [
    'section' => (string)$valueFromSources('section', ''),
    'mode' => (string)$valueFromSources('mode', ''),
    'action' => (string)$valueFromSources('action', ''),
    'category_id' => (int)$valueFromSources('category_id', $valueFromSources('category', 0)),
    'test_id' => (int)$valueFromSources('test_id', $valueFromSources('testId', 0)),
    'path_id' => (int)$valueFromSources('path_id', $valueFromSources('id', 0)),
    'step_id' => (int)$valueFromSources('step_id', $valueFromSources('stepId', 0)),
    'handbook_section_id' => (int)$valueFromSources('handbook_section_id', $valueFromSources('section_id', 0)),
    'session_id' => (int)$valueFromSources('session_id', $valueFromSources('sessionId', 0)),
    'knowledge_area_id' => (int)$valueFromSources('knowledge_area_id', $valueFromSources('knowledge_area', 0)),
    'debug' => (int)$debugValue,
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


if (!empty($context['debug'])) {
    $diagnostics = $builder->getDiagnostics();
    $output .= '<details class="ts-alert ts-alert-info mt-2"><summary>LMS Breadcrumbs DIAG</summary>';
    $output .= '<div class="small text-muted mb-2">Добавьте <code>?lms_bc_diag=1</code> в URL для диагностики.</div>';
    $output .= '<div class="mb-2"><strong>Context:</strong><pre class="mb-0"><code>' . htmlspecialchars(json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '</code></pre></div>';
    $output .= '<div class="mb-2"><strong>GET:</strong><pre class="mb-0"><code>' . htmlspecialchars(json_encode($_GET ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '</code></pre></div>';
    $output .= '<div class="mb-2"><strong>Items:</strong><pre class="mb-0"><code>' . htmlspecialchars(json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') . '</code></pre></div>';
    if (!empty($diagnostics)) {
        $output .= '<ul class="mb-0 mt-2">';
        foreach ($diagnostics as $diagLine) {
            $output .= '<li><code>' . htmlspecialchars($diagLine, ENT_QUOTES, 'UTF-8') . '</code></li>';
        }
        $output .= '</ul>';
    }
    $output .= '</details>';
}

return $output;
