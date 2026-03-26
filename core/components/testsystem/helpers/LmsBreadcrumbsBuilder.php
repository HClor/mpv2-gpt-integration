<?php
/**
 * LMS Breadcrumbs Builder
 *
 * Строит breadcrumb-цепочку по LMS-контексту (тесты / траектории / учебник),
 * без опоры на дерево MODX-ресурсов.
 *
 * @package TestSystem
 */

class LmsBreadcrumbsBuilder
{
    /** @var modX */
    private $modx;

    /** @var array */
    private $context = [];

    /** @var array */
    private $entityCache = [];

    /** @var array */
    private $schemaCache = [];

    /** @var array<string, callable> */
    private $resolvers = [];

    /** @var bool */
    private $debug = false;

    /** @var array<int, string> */
    private $diagnostics = [];

    public function __construct(modX $modx, array $context = [], array $preloadedEntities = [])
    {
        $this->modx = $modx;
        $this->context = $this->normalizeContext($context);
        $this->debug = !empty($this->context['debug']);
        $this->entityCache = $preloadedEntities;

        $this->resolvers = [
            'tests' => [$this, 'resolveTestsBreadcrumbs'],
            'knowledge_areas' => [$this, 'resolveKnowledgeAreasBreadcrumbs'],
            'learning_paths' => [$this, 'resolveLearningPathsBreadcrumbs'],
            'handbook' => [$this, 'resolveHandbookBreadcrumbs'],
            'lms' => [$this, 'resolveGenericLmsBreadcrumbs'],
        ];
    }

    public function build(): array
    {
        $section = $this->context['section'] ?: $this->detectSection();
        $this->context['resolved_section'] = $section;
        $this->diag('DIAG-1', 'Resolved section=' . $section . '; mode=' . $this->context['mode']);
        $resolver = $this->resolvers[$section] ?? $this->resolvers['lms'];
        $items = call_user_func($resolver);

        $result = $this->sanitizeItems($items);
        $this->diag('DIAG-2', 'Breadcrumb items count=' . count($result));

        return $result;
    }

    private function resolveTestsBreadcrumbs(): array
    {
        $items = $this->baseItems();
        $testsUrl = $this->getTestsRootUrl();
        $items[] = $this->item('Тесты', $testsUrl);

        $mode = $this->context['mode'];
        $categoryId = $this->context['category_id'];
        $testId = $this->context['test_id'];

        if ($testId <= 0 && $this->context['session_id'] > 0) {
            $session = $this->getSession($this->context['session_id']);
            if (!empty($session['test_id'])) {
                $testId = (int)$session['test_id'];
            }
        }

        if ($testId > 0) {
            $test = $this->getTest($testId);
            if (!empty($test)) {
                $categoryId = $categoryId > 0 ? $categoryId : (int)($test['category_id'] ?? 0);

                if ($categoryId > 0) {
                    $category = $this->getCategory($categoryId);
                    if (!empty($category['name'])) {
                        $categoryTitle = trim((string)$category['name']);
                        $items[] = $this->item($categoryTitle, $this->getTestsRootUrl(['category' => $categoryId]));
                    }
                }

                // По требованиям: во время прохождения теста не добавляем название теста.
                if (!in_array($mode, ['run', 'result', 'study'], true)) {
                    $items[] = $this->item((string)$test['title'], null, true);
                }
            }
        } elseif ($categoryId > 0) {
            $category = $this->getCategory($categoryId);
            if (!empty($category['name'])) {
                $categoryTitle = trim((string)$category['name']);
                $items[] = $this->item($categoryTitle, null, true);
            }
        }

        return $items;
    }

    private function resolveLearningPathsBreadcrumbs(): array
    {
        $items = $this->baseItems();
        $pathsUrl = $this->getLearningPathsRootUrl();
        $items[] = $this->item('Траектории обучения', $pathsUrl);

        $pathId = $this->context['path_id'];
        $stepId = $this->context['step_id'];

        if ($pathId <= 0 && $stepId > 0) {
            $step = $this->getPathStep($stepId);
            $pathId = (int)($step['path_id'] ?? 0);
        }

        if ($pathId > 0) {
            $path = $this->getLearningPath($pathId);
            if (!empty($path['name'])) {
                $items[] = $this->item((string)$path['name'], $this->getLearningPathsRootUrl(['mode' => 'view', 'id' => $pathId]));
            }
        }

        if ($stepId > 0) {
            $step = $this->getPathStep($stepId);
            if (!empty($step['name'])) {
                $items[] = $this->item((string)$step['name'], null, true);
            }
        }

        if (count($items) === 2) {
            $items[count($items) - 1]['current'] = true;
            $items[count($items) - 1]['url'] = null;
        }

        return $items;
    }

    private function resolveHandbookBreadcrumbs(): array
    {
        $mode = (string)$this->context['mode'];
        $isLearningTestMode = in_array($mode, ['study', 'learning'], true)
            && ($this->context['test_id'] > 0 || $this->context['session_id'] > 0);

        if (!$isLearningTestMode && $this->context['handbook_section_id'] <= 0 && $this->context['path_id'] <= 0) {
            $resourceItems = $this->buildResourceHierarchyItems();
            if (!empty($resourceItems)) {
                return $resourceItems;
            }
        }

        $items = $this->baseItems();

        $pathId = $this->context['path_id'];
        $sectionId = $this->context['handbook_section_id'];

        if ($pathId > 0) {
            $items[] = $this->item('Траектории обучения', $this->getLearningPathsRootUrl());
            $path = $this->getLearningPath($pathId);
            if (!empty($path['name'])) {
                $items[] = $this->item((string)$path['name'], $this->getLearningPathsRootUrl(['mode' => 'view', 'id' => $pathId]));
            }
        } else {
            $items[] = $this->item('Учебник', $this->getHandbookRootUrl());
        }

        if ($isLearningTestMode) {
            $items[] = $this->item('Вопросы из тестов', $this->getHandbookRootUrl());

            $testId = (int)$this->context['test_id'];
            if ($testId <= 0 && (int)$this->context['session_id'] > 0) {
                $session = $this->getSession((int)$this->context['session_id']);
                $testId = (int)($session['test_id'] ?? 0);
            }

            if ($testId > 0) {
                $test = $this->getTest($testId);
                if (!empty($test)) {
                    $categoryId = (int)($test['category_id'] ?? 0);
                    if ($categoryId > 0) {
                        $category = $this->getCategory($categoryId);
                        if (!empty($category['name'])) {
                            $items[] = $this->item((string)$category['name'], $this->getHandbookRootUrl());
                        }
                    }

                    if (!empty($test['title'])) {
                        $items[] = $this->item((string)$test['title'], null, true);
                    }
                }
            }
        }

        if ($sectionId > 0) {
            $section = $this->getHandbookSection($sectionId);
            if (!empty($section['name'])) {
                $items[] = $this->item((string)$section['name'], null, true);
            }
        }

        if (count($items) === 2) {
            $items[count($items) - 1]['current'] = true;
            $items[count($items) - 1]['url'] = null;
        }

        return $items;
    }

    private function resolveKnowledgeAreasBreadcrumbs(): array
    {
        $items = $this->baseItems();
        $knowledgeAreaUrl = $this->getKnowledgeAreaRootUrl();
        $items[] = $this->item('Области знаний', $knowledgeAreaUrl);

        $areaId = (int)($this->context['knowledge_area_id'] ?? 0);
        if ($areaId > 0) {
            $area = $this->getKnowledgeArea($areaId);
            if (!empty($area['name'])) {
                $items[] = $this->item((string)$area['name'], null, true);
            }
        }

        if (count($items) === 2) {
            $items[count($items) - 1]['current'] = true;
            $items[count($items) - 1]['url'] = null;
        }

        return $items;
    }

    private function resolveGenericLmsBreadcrumbs(): array
    {
        $resourceItems = $this->buildResourceHierarchyItems();
        if (!empty($resourceItems)) {
            return $resourceItems;
        }

        return [$this->item('Главная', $this->getHomeUrl()), $this->item('LMS', null, true)];
    }

    private function normalizeContext(array $context): array
    {
        $query = $_GET ?? [];

        $ctx = [
            'section' => (string)($context['section'] ?? ($query['section'] ?? '')),
            'mode' => (string)($context['mode'] ?? ($query['mode'] ?? ($query['view'] ?? 'list'))),
            'action' => (string)($context['action'] ?? ($query['action'] ?? '')),
            'category_id' => (int)($context['category_id'] ?? ($query['category_id'] ?? ($query['category'] ?? 0))),
            'test_id' => (int)($context['test_id'] ?? ($query['test_id'] ?? ($query['testId'] ?? 0))),
            'path_id' => (int)($context['path_id'] ?? ($query['path_id'] ?? ($query['id'] ?? 0))),
            'step_id' => (int)($context['step_id'] ?? ($query['step_id'] ?? ($query['stepId'] ?? 0))),
            'handbook_section_id' => (int)($context['handbook_section_id'] ?? ($query['handbook_section_id'] ?? ($query['section_id'] ?? 0))),
            'session_id' => (int)($context['session_id'] ?? ($query['sessionId'] ?? 0)),
            'knowledge_area_id' => (int)($context['knowledge_area_id'] ?? ($query['knowledge_area_id'] ?? ($query['knowledge_area'] ?? 0))),
            'debug' => (int)($context['debug'] ?? ($query['lms_bc_diag'] ?? 0)) === 1,
        ];

        return $ctx;
    }

    private function detectSection(): string
    {
        if ($this->context['section'] !== '') {
            if (
                $this->context['section'] === 'tests'
                && in_array($this->context['mode'], ['study', 'learning'], true)
                && ($this->context['test_id'] > 0 || $this->context['session_id'] > 0)
            ) {
                $this->diag('DIAG-20', 'detectSection override tests=>handbook for learning test mode');
                return 'handbook';
            }
            return $this->context['section'];
        }

        if ($this->context['path_id'] > 0 || $this->context['step_id'] > 0 || in_array($this->context['mode'], ['view', 'edit', 'my'], true)) {
            $this->diag('DIAG-4', 'detectSection => learning_paths');
            return 'learning_paths';
        }

        if ($this->context['handbook_section_id'] > 0 || in_array($this->context['mode'], ['study', 'learning'], true)) {
            $this->diag('DIAG-5', 'detectSection => handbook');
            return 'handbook';
        }

        if ($this->context['test_id'] > 0 || $this->context['category_id'] > 0 || $this->context['session_id'] > 0) {
            $this->diag('DIAG-6', 'detectSection => tests');
            return 'tests';
        }
        if ($this->context['knowledge_area_id'] > 0) {
            $this->diag('DIAG-17', 'detectSection => knowledge_areas');
            return 'knowledge_areas';
        }

        $resource = $this->modx->resource;
        if ($resource instanceof modResource) {
            $resourceId = (int)$resource->get('id');
            $alias = (string)$resource->get('alias');
            $this->diag('DIAG-12', 'detectSection resource probe: id=' . $resourceId . '; alias=' . $alias);

            $testsRootId = (int)$this->modx->getOption('lms.test_page', null, Config::getPageId('tests_root', 155));
            if ($testsRootId > 0 && $resourceId === $testsRootId) {
                $this->diag('DIAG-13', 'detectSection => tests (resource id match)');
                return 'tests';
            }
            if ($alias === 'tests') {
                $this->diag('DIAG-16', 'detectSection => tests (resource alias match)');
                return 'tests';
            }
            if (in_array($alias, ['oblast-znanij', 'knowledge-area', 'moi-oblasti-znanij'], true)) {
                $this->diag('DIAG-18', 'detectSection => knowledge_areas (resource alias match)');
                return 'knowledge_areas';
            }
            if (in_array($alias, ['learning-articles', 'learning-materials', 'handbook'], true)) {
                $this->diag('DIAG-19', 'detectSection => handbook (resource alias match)');
                return 'handbook';
            }

            $pathsRootId = (int)$this->modx->getOption('lms.learning_paths_page', null, 0);
            if (($pathsRootId > 0 && $resourceId === $pathsRootId) || $alias === 'learning-paths') {
                $this->diag('DIAG-14', 'detectSection => learning_paths (resource)');
                return 'learning_paths';
            }

            $handbookRootId = (int)$this->modx->getOption('lms.handbook_page', null, 0);
            if (($handbookRootId > 0 && $resourceId === $handbookRootId) || in_array($alias, ['learning-materials', 'handbook'], true)) {
                $this->diag('DIAG-15', 'detectSection => handbook (resource)');
                return 'handbook';
            }
        }

        $this->diag('DIAG-7', 'detectSection => lms (fallback)');
        return 'lms';
    }

    private function baseItems(): array
    {
        return [
            $this->item('Главная', $this->getHomeUrl()),
        ];
    }

    private function item(string $title, ?string $url = null, bool $current = false): array
    {
        return [
            'title' => $title,
            'url' => $url,
            'current' => $current,
        ];
    }

    private function sanitizeItems(array $items): array
    {
        if (empty($items)) {
            return $this->resolveGenericLmsBreadcrumbs();
        }

        $count = count($items);
        foreach ($items as $index => &$item) {
            $item['title'] = trim((string)($item['title'] ?? ''));
            $item['url'] = isset($item['url']) && $item['url'] !== '' ? (string)$item['url'] : null;
            $item['current'] = !empty($item['current']);

            if ($index === $count - 1 && !$item['current']) {
                $item['current'] = true;
                if (!(($this->context['resolved_section'] ?? $this->detectSection()) === 'tests' && in_array($this->context['mode'], ['run', 'result', 'study'], true))) {
                    $item['url'] = null;
                }
            }
        }
        unset($item);

        return array_values(array_filter($items, static function ($item) {
            return $item['title'] !== '';
        }));
    }

    private function getHomeUrl(): string
    {
        $siteStart = Config::getPageId('site_start', 1);
        return $this->modx->makeUrl($siteStart, 'web', [], 'full');
    }

    private function getTestsRootUrl(array $params = []): ?string
    {
        if ($this->modx->resource instanceof modResource) {
            $resourceAlias = (string)$this->modx->resource->get('alias');
            if ($resourceAlias === 'tests') {
                $resourceId = (int)$this->modx->resource->get('id');
                return $this->modx->makeUrl($resourceId, 'web', $params, 'full');
            }
        }

        $pageId = (int)$this->modx->getOption('lms.tests_catalog_page', null, Config::getPageId('tests_root', 0));
        if ($pageId <= 0) {
            $testsResource = $this->modx->getObject('modResource', ['alias' => 'tests']);
            $pageId = $testsResource ? (int)$testsResource->get('id') : 0;
        }
        if ($pageId <= 0) {
            return null;
        }

        return $this->modx->makeUrl($pageId, 'web', $params, 'full');
    }

    private function getLearningPathsRootUrl(array $params = []): ?string
    {
        $pageId = (int)$this->modx->getOption('lms.learning_paths_page', null, 0);
        if ($pageId <= 0) {
            $resource = $this->modx->getObject('modResource', ['alias' => 'learning-paths']);
            $pageId = $resource ? (int)$resource->get('id') : 0;
        }

        if ($pageId <= 0 && $this->modx->resource) {
            $pageId = (int)$this->modx->resource->get('id');
        }

        return $pageId > 0 ? $this->modx->makeUrl($pageId, 'web', $params, 'full') : null;
    }

    private function getHandbookRootUrl(): ?string
    {
        $pageId = (int)$this->modx->getOption('lms.handbook_page', null, 0);
        if ($pageId <= 0) {
            $resource = $this->modx->getObject('modResource', ['alias' => 'learning-materials']);
            $pageId = $resource ? (int)$resource->get('id') : 0;
        }

        return $pageId > 0 ? $this->modx->makeUrl($pageId, 'web', [], 'full') : null;
    }

    private function getKnowledgeAreaRootUrl(): ?string
    {
        $pageId = Config::getPageId('manage_areas', 0);
        if ($pageId <= 0) {
            $listPage = $this->modx->getObject('modResource', ['alias' => 'moi-oblasti-znanij']);
            $pageId = $listPage ? (int)$listPage->get('id') : 0;
        }
        if ($pageId <= 0) {
            $pageId = Config::getPageId('knowledge_area', 0);
        }
        return $pageId > 0 ? $this->modx->makeUrl($pageId, 'web', [], 'full') : null;
    }

    private function getCategory(int $categoryId): ?array
    {
        if ($categoryId <= 0) {
            return null;
        }

        $cacheKey = 'category:' . $categoryId;
        if (isset($this->entityCache[$cacheKey])) {
            return $this->entityCache[$cacheKey];
        }

        $prefix = (string)$this->modx->getOption('table_prefix');
        $stmt = $this->modx->prepare("SELECT id, name FROM {$prefix}test_categories WHERE id = :id LIMIT 1");
        if (!$stmt) {
            $this->diag('DIAG-3', 'Failed to prepare SQL in getCategory for category_id=' . $categoryId);
            return null;
        }

        $stmt->bindValue(':id', $categoryId, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $this->entityCache[$cacheKey] = $row;
        $this->diag('DIAG-8', 'getCategory category_id=' . $categoryId . '; found=' . ($row ? '1' : '0'));

        return $row;
    }

    private function getTest(int $testId): ?array
    {
        if ($testId <= 0) {
            return null;
        }

        $cacheKey = 'test:' . $testId;
        if (isset($this->entityCache[$cacheKey])) {
            return $this->entityCache[$cacheKey];
        }

        $prefix = (string)$this->modx->getOption('table_prefix');
        $table = $prefix . 'test_tests';
        $categoryColumn = $this->tableHasColumn($table, 'category_id') ? 'category_id' : 'resource_id';
        $sql = "SELECT id, title, {$categoryColumn} AS category_id FROM {$table} WHERE id = :id LIMIT 1";
        $stmt = $this->modx->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $stmt->bindValue(':id', $testId, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $this->entityCache[$cacheKey] = $row;
        $this->diag('DIAG-9', 'getTest test_id=' . $testId . '; found=' . ($row ? '1' : '0'));

        return $row;
    }

    private function getSession(int $sessionId): ?array
    {
        if ($sessionId <= 0) {
            return null;
        }

        $cacheKey = 'session:' . $sessionId;
        if (isset($this->entityCache[$cacheKey])) {
            return $this->entityCache[$cacheKey];
        }

        $prefix = (string)$this->modx->getOption('table_prefix');
        $stmt = $this->modx->prepare("SELECT id, test_id FROM {$prefix}test_sessions WHERE id = :id LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bindValue(':id', $sessionId, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $this->entityCache[$cacheKey] = $row;
        $this->diag('DIAG-11', 'getSession session_id=' . $sessionId . '; found=' . ($row ? '1' : '0'));

        return $row;
    }

    private function getLearningPath(int $pathId): ?array
    {
        if ($pathId <= 0) {
            return null;
        }

        $cacheKey = 'path:' . $pathId;
        if (isset($this->entityCache[$cacheKey])) {
            return $this->entityCache[$cacheKey];
        }

        $prefix = (string)$this->modx->getOption('table_prefix');
        $stmt = $this->modx->prepare("SELECT id, name FROM {$prefix}test_learning_paths WHERE id = :id LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bindValue(':id', $pathId, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $this->entityCache[$cacheKey] = $row;

        return $row;
    }

    private function getPathStep(int $stepId): ?array
    {
        if ($stepId <= 0) {
            return null;
        }

        $cacheKey = 'step:' . $stepId;
        if (isset($this->entityCache[$cacheKey])) {
            return $this->entityCache[$cacheKey];
        }

        $prefix = (string)$this->modx->getOption('table_prefix');
        $table = $prefix . 'test_learning_path_steps';
        $nameColumn = $this->tableHasColumn($table, 'name') ? 'name' : 'title';
        $sql = "SELECT id, path_id, {$nameColumn} AS name FROM {$table} WHERE id = :id LIMIT 1";
        $stmt = $this->modx->prepare($sql);
        if (!$stmt) {
            return null;
        }

        $stmt->bindValue(':id', $stepId, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $this->entityCache[$cacheKey] = $row;

        return $row;
    }

    private function getHandbookSection(int $sectionId): ?array
    {
        if ($sectionId <= 0) {
            return null;
        }

        $cacheKey = 'handbook_section:' . $sectionId;
        if (isset($this->entityCache[$cacheKey])) {
            return $this->entityCache[$cacheKey];
        }

        $prefix = (string)$this->modx->getOption('table_prefix');

        $queries = [
            "SELECT id, title AS name FROM {$prefix}test_learning_materials WHERE id = :id LIMIT 1",
            "SELECT id, pagetitle AS name FROM {$prefix}site_content WHERE id = :id LIMIT 1",
        ];

        foreach ($queries as $sql) {
            $stmt = $this->modx->prepare($sql);
            if (!$stmt) {
                continue;
            }

            $stmt->bindValue(':id', $sectionId, PDO::PARAM_INT);
            if (!$stmt->execute()) {
                continue;
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['name'])) {
                $this->entityCache[$cacheKey] = $row;
                return $row;
            }
        }

        return null;
    }

    private function getKnowledgeArea(int $areaId): ?array
    {
        if ($areaId <= 0) {
            return null;
        }

        $cacheKey = 'knowledge_area:' . $areaId;
        if (isset($this->entityCache[$cacheKey])) {
            return $this->entityCache[$cacheKey];
        }

        $prefix = (string)$this->modx->getOption('table_prefix');
        $stmt = $this->modx->prepare("SELECT id, name FROM {$prefix}test_knowledge_areas WHERE id = :id LIMIT 1");
        if (!$stmt) {
            return null;
        }

        $stmt->bindValue(':id', $areaId, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $this->entityCache[$cacheKey] = $row;

        return $row;
    }

    private function buildResourceHierarchyItems(): array
    {
        if (!$this->modx->resource instanceof modResource) {
            return [];
        }

        $chain = [];
        $currentId = (int)$this->modx->resource->get('id');
        $guard = 0;

        while ($currentId > 0 && $guard < 30) {
            $guard++;
            $resource = $this->modx->getObject('modResource', $currentId);
            if (!$resource) {
                break;
            }

            $chain[] = [
                'id' => (int)$resource->get('id'),
                'title' => (string)$resource->get('pagetitle'),
                'parent' => (int)$resource->get('parent'),
            ];
            $currentId = (int)$resource->get('parent');
        }

        if (empty($chain)) {
            return [];
        }

        $chain = array_reverse($chain);
        $items = [$this->item('Главная', $this->getHomeUrl())];
        $siteStart = Config::getPageId('site_start', 1);
        foreach ($chain as $index => $node) {
            $isLast = $index === count($chain) - 1;
            if ((int)$node['id'] === $siteStart) {
                continue;
            }
            $items[] = $this->item(
                $node['title'],
                $isLast ? null : $this->modx->makeUrl((int)$node['id'], 'web', [], 'full'),
                $isLast
            );
        }

        return $items;
    }


    public function getDiagnostics(): array
    {
        return $this->diagnostics;
    }

    private function diag(string $code, string $message): void
    {
        if (!$this->debug) {
            return;
        }

        $line = '[' . $code . '] ' . $message;
        $this->diagnostics[] = $line;
        $this->modx->log(modX::LOG_LEVEL_ERROR, '[lmsBreadcrumbs] ' . $line);
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $cacheKey = $table . ':' . $column;
        if (isset($this->schemaCache[$cacheKey])) {
            return $this->schemaCache[$cacheKey];
        }

        $stmt = $this->modx->prepare("SHOW COLUMNS FROM `{$table}` LIKE :column");
        if (!$stmt) {
            $this->schemaCache[$cacheKey] = false;
            return false;
        }

        $stmt->bindValue(':column', $column, PDO::PARAM_STR);
        if (!$stmt->execute()) {
            $this->schemaCache[$cacheKey] = false;
            return false;
        }

        $exists = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        $this->schemaCache[$cacheKey] = $exists;
        $this->diag('DIAG-10', 'tableHasColumn ' . $table . '.' . $column . '=' . ($exists ? '1' : '0'));

        return $exists;
    }
}
