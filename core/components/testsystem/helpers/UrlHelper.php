<?php
/**
 * URL Helper Class
 *
 * Централизация логики построения URL для тестов
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-13
 */

class UrlHelper
{
    /**
     * Построение URL для теста по resource_id
     *
     * @param object $modx MODX объект
     * @param int $resourceId ID ресурса (страницы) теста
     * @param int|null $testsParentId ID родительской папки (опционально)
     * @return string URL теста или '#' если не удалось построить
     */
    public static function buildTestUrl($modx, $resourceId, $testsParentId = null)
    {
        if ($resourceId <= 0) {
            return '#';
        }

        $resource = $modx->getObject('modResource', (int)$resourceId);

        if (!$resource) {
            return '#';
        }

        $siteUrl = rtrim($modx->getOption('site_url'), '/');
        $alias = $resource->get('alias');

        // Если не передан родительский ID, получаем из ресурса
        if ($testsParentId === null) {
            $testsParentId = (int)$resource->get('parent');
        }

        // Получаем URI родительской папки
        $parentUri = '';
        if ($testsParentId > 0) {
            $parent = $modx->getObject('modResource', $testsParentId);
            if ($parent) {
                $parentUri = trim($parent->get('uri'), '/');
            }
        }

        // Строим URL
        if (!empty($parentUri)) {
            return $siteUrl . '/' . $parentUri . '/' . $alias;
        } else {
            return $siteUrl . '/' . $alias;
        }
    }

    /**
     * Добавление URL к массиву тестов
     *
     * @param object $modx MODX объект
     * @param array $tests Массив тестов (передается по ссылке)
     * @param int|null $testsParentId ID родительской папки (если известен)
     * @return void
     */
    public static function addUrlsToTests($modx, &$tests, $testsParentId = null)
    {
        if (empty($tests)) {
            return;
        }

        $siteUrl = rtrim($modx->getOption('site_url'), '/');

        // Если не передан, получаем из настроек
        if ($testsParentId === null) {
            $testsParentId = (int)$modx->getOption('lms.user_tests_folder', null, 0);
        }

        // Получаем URI родительской папки один раз
        $parentUri = '';
        if ($testsParentId > 0) {
            $parent = $modx->getObject('modResource', $testsParentId);
            if ($parent) {
                $parentUri = trim($parent->get('uri'), '/');
            }
        }

        // Добавляем URL к каждому тесту
        foreach ($tests as &$test) {
            $resourceId = (int)($test['resource_id'] ?? 0);

            if ($resourceId > 0) {
                $resource = $modx->getObject('modResource', $resourceId);

                if ($resource) {
                    $alias = $resource->get('alias');

                    if (!empty($parentUri)) {
                        $test['test_url'] = $siteUrl . '/' . $parentUri . '/' . $alias;
                    } else {
                        $test['test_url'] = $siteUrl . '/' . $alias;
                    }
                } else {
                    $test['test_url'] = '#';
                }
            } else {
                $test['test_url'] = '#';
            }
        }
    }

    /**
     * Построение публичного URL по slug
     *
     * @param object $modx MODX объект
     * @param string $slug URL slug теста
     * @return string|null URL или null
     */
    public static function buildPublicTestUrl($modx, $slug)
    {
        if (empty($slug)) {
            return null;
        }

        $publicTestPageId = (int)$modx->getOption('lms.public_test_page', null, 0);

        if ($publicTestPageId > 0) {
            return $modx->makeUrl($publicTestPageId, 'web', ['slug' => $slug], 'full');
        }

        return null;
    }

    /**
     * Генерация уникального alias для теста
     *
     * @param object $modx MODX объект
     * @param string $title Заголовок теста
     * @param int $testId ID теста (для уникальности)
     * @param int $parentId ID родительской папки
     * @return string Уникальный alias
     * @throws Exception Если не удалось сгенерировать уникальный alias
     */
    public static function generateUniqueAlias($modx, $title, $testId, $parentId)
    {
        // Транслитерация и очистка
        $baseAlias = $modx->filterPathSegment($title);
        $baseAlias = preg_replace('/[^a-z0-9-]/', '', strtolower(self::transliterate($baseAlias)));

        if (empty($baseAlias)) {
            $baseAlias = 'test-' . $testId;
        }

        $alias = $baseAlias;
        $counter = 1;
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Проверяем уникальность
        while (true) {
            $stmt = $modx->prepare("
                SELECT COUNT(*)
                FROM {$prefix}site_content
                WHERE alias = ? AND parent = ?
            ");
            $stmt->execute([$alias, (int)$parentId]);

            if ((int)$stmt->fetchColumn() === 0) {
                break;
            }

            $alias = $baseAlias . '-' . $counter;
            $counter++;

            if ($counter > 100) {
                throw new Exception('Failed to generate unique alias');
            }
        }

        return $alias;
    }

    /**
     * Транслитерация русских символов
     *
     * @param string $str Строка для транслитерации
     * @return string Транслитерированная строка
     */
    private static function transliterate($str)
    {
        $ru = [
            'а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я',
            'А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я'
        ];
        $en = [
            'a','b','v','g','d','e','e','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','c','ch','sh','sch','','y','','e','yu','ya',
            'A','B','V','G','D','E','E','Zh','Z','I','Y','K','L','M','N','O','P','R','S','T','U','F','H','C','Ch','Sh','Sch','','Y','','E','Yu','Ya'
        ];

        $str = str_replace($ru, $en, $str);
        $str = preg_replace('/[^a-zA-Z0-9-]/', '', $str);
        $str = mb_strtolower($str);

        return $str;
    }
}
