<?php
/**
 * Test Service Class
 *
 * Сервис для работы с тестами (создание, публикация, удаление)
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-13
 */

class TestService
{
    /**
     * Создание теста вместе со страницей
     *
     * @param object $modx MODX объект
     * @param string $title Заголовок теста
     * @param string $description Описание теста
     * @param string $publicationStatus Статус публикации (draft, private, unlisted, public)
     * @param int $userId ID пользователя-создателя
     * @return array ['test_id' => int, 'resource_id' => int, 'test_url' => string]
     * @throws Exception При ошибках создания
     */
    public static function createTestWithPage($modx, $title, $description, $publicationStatus, $userId)
    {
        // Валидация статуса
        $allowedStatuses = ['draft', 'private', 'unlisted', 'public'];
        if (!in_array($publicationStatus, $allowedStatuses, true)) {
            $publicationStatus = 'draft';
        }

        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // ШАГ 1: Создаём тест
        $testId = self::createTestRecord($modx, $prefix, $title, $description, $publicationStatus, $userId);

        try {
            // ШАГ 2: Создаём страницу для теста
            $resourceId = self::createTestPage($modx, $testId, $title, $userId);

            // ШАГ 3: Привязываем тест к странице
            self::linkTestToPage($modx, $prefix, $testId, $resourceId);

            // ШАГ 4: Очищаем кеш и генерируем URL
            $testUrl = self::generateTestUrl($modx, $resourceId, $testId, $title);

            return [
                'test_id' => $testId,
                'resource_id' => $resourceId,
                'test_url' => $testUrl
            ];

        } catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, '[TestService::createTestWithPage] Error: ' . $e->getMessage());
            throw new Exception('Failed to create test with page: ' . $e->getMessage());
        }
    }

    /**
     * Создание записи теста в БД
     *
     * @param object $modx MODX объект
     * @param string $prefix Префикс таблиц
     * @param string $title Заголовок
     * @param string $description Описание
     * @param string $publicationStatus Статус публикации
     * @param int $userId ID пользователя
     * @return int ID созданного теста
     * @throws Exception При ошибке создания
     */
    private static function createTestRecord($modx, $prefix, $title, $description, $publicationStatus, $userId)
    {
        $insertStmt = $modx->prepare("
            INSERT INTO {$prefix}test_tests
            (title, description, created_by, created_at, publication_status, is_active, mode, time_limit, pass_score, questions_per_session, resource_id)
            VALUES (?, ?, ?, NOW(), ?, 1, 'training', 0, 70, 20, 0)
        ");

        if (!$insertStmt) {
            throw new Exception('Failed to prepare insert statement');
        }

        if (!$insertStmt->execute([$title, $description, $userId, $publicationStatus])) {
            throw new Exception('Failed to create test: ' . print_r($insertStmt->errorInfo(), true));
        }

        $testId = (int)$modx->lastInsertId();

        if ($testId <= 0) {
            throw new Exception('Invalid test ID after insert');
        }

        $modx->log(modX::LOG_LEVEL_INFO, "[TestService] Test created: ID={$testId}, Title={$title}");

        return $testId;
    }

    /**
     * Создание страницы (ресурса) для теста
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @param string $title Заголовок
     * @param int $userId ID пользователя
     * @return int ID созданного ресурса
     * @throws Exception При ошибке создания
     */
    private static function createTestPage($modx, $testId, $title, $userId)
    {
        $testsParentId = (int)$modx->getOption('lms.user_tests_folder', null, 0);

        // Генерируем уникальный alias
        $alias = self::generateUniqueAlias($modx, $title, $testId);

        $modx->log(modX::LOG_LEVEL_INFO, "[TestService] Using alias: {$alias}, parent: {$testsParentId}");

        // Получаем ID шаблона
        $templateId = self::getTestTemplateId($modx);

        $modx->log(modX::LOG_LEVEL_INFO, "[TestService] Using template: {$templateId}");

        // Создаём ресурс
        $resource = $modx->newObject('modResource');

        if (!$resource) {
            throw new Exception('Failed to create resource object');
        }

        // Устанавливаем поля ресурса
        $resourceData = [
            'pagetitle' => $title,
            'alias' => $alias,
            'parent' => $testsParentId,
            'template' => $templateId,
            'content' => '[[!testRunner]]',
            'published' => 1,
            'richtext' => 0,
            'searchable' => 1,
            'cacheable' => 1,
            'createdby' => $userId,
            'createdon' => time(),
            'class_key' => 'modDocument',
            'context_key' => 'web'
        ];

        $resource->fromArray($resourceData, '', true, true);

        // Сохраняем БЕЗ событий
        if (!$resource->save()) {
            $errors = $resource->getErrors();
            $errorMsg = 'Resource validation errors: ';
            foreach ($errors as $field => $error) {
                $errorMsg .= "{$field}: {$error}, ";
            }
            $modx->log(modX::LOG_LEVEL_ERROR, "[TestService] {$errorMsg}");
            throw new Exception('Failed to save resource: ' . $errorMsg);
        }

        $resourceId = (int)$resource->get('id');

        if ($resourceId <= 0) {
            throw new Exception('Invalid resource ID after save: got ' . var_export($resourceId, true));
        }

        $modx->log(modX::LOG_LEVEL_INFO, "[TestService] Resource created: ID={$resourceId}");

        return $resourceId;
    }

    /**
     * Генерация уникального alias для страницы теста
     *
     * @param object $modx MODX объект
     * @param string $title Заголовок
     * @param int $testId ID теста
     * @return string Уникальный alias
     */
    private static function generateUniqueAlias($modx, $title, $testId)
    {
        $baseAlias = $modx->filterPathSegment($title);
        $baseAlias = preg_replace('/[^a-z0-9-]/', '', strtolower(self::transliterate($baseAlias)));

        if (empty($baseAlias)) {
            $baseAlias = 'test';
        }

        // Финальный alias = base-{testId} (гарантированно уникальный)
        return $baseAlias . '-' . $testId;
    }

    /**
     * Получение ID шаблона для тестов
     *
     * @param object $modx MODX объект
     * @return int ID шаблона
     */
    private static function getTestTemplateId($modx)
    {
        $templateId = (int)$modx->getOption('lms.test_template', null, 0);

        if ($templateId === 0) {
            $templateId = (int)$modx->getOption('default_template', null, 1);
        }

        return $templateId;
    }

    /**
     * Привязка теста к странице
     *
     * @param object $modx MODX объект
     * @param string $prefix Префикс таблиц
     * @param int $testId ID теста
     * @param int $resourceId ID ресурса
     * @throws Exception При ошибке обновления
     */
    private static function linkTestToPage($modx, $prefix, $testId, $resourceId)
    {
        $updateStmt = $modx->prepare("
            UPDATE {$prefix}test_tests
            SET resource_id = ?
            WHERE id = ?
        ");

        if (!$updateStmt || !$updateStmt->execute([$resourceId, $testId])) {
            $modx->log(modX::LOG_LEVEL_ERROR, "[TestService] Failed to link test {$testId} to resource {$resourceId}");
            throw new Exception('Failed to link test to page');
        }

        $modx->log(modX::LOG_LEVEL_INFO, "[TestService] Test linked to resource");
    }

    /**
     * Генерация URL теста с очисткой кеша
     *
     * @param object $modx MODX объект
     * @param int $resourceId ID ресурса
     * @param int $testId ID теста
     * @param string $title Заголовок (для fallback)
     * @return string URL теста
     */
    private static function generateTestUrl($modx, $resourceId, $testId, $title)
    {
        // Очищаем кеш ПЕРЕД генерацией URL
        try {
            $modx->cacheManager->refresh([
                'db' => [],
                'auto_publish' => ['contexts' => ['web']],
                'context_settings' => ['contexts' => ['web']],
                'resource' => ['contexts' => ['web']],
            ]);
        } catch (Exception $cacheError) {
            $modx->log(modX::LOG_LEVEL_WARN, "[TestService] Cache clear failed: " . $cacheError->getMessage());
        }

        $testUrl = '';

        try {
            // Перезагружаем ресурс из БД чтобы убедиться что данные актуальны
            $resource = $modx->getObject('modResource', $resourceId);

            if (!$resource) {
                throw new Exception('Resource not found after creation');
            }

            $testUrl = $modx->makeUrl($resourceId, 'web', '', 'full');

            // Проверяем валидность
            if (empty($testUrl) || !filter_var($testUrl, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid URL from makeUrl');
            }

            $modx->log(modX::LOG_LEVEL_INFO, "[TestService] URL via makeUrl: {$testUrl}");

        } catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_WARN, "[TestService] makeUrl failed, using fallback: " . $e->getMessage());

            // Fallback: строим URL вручную
            $testUrl = self::buildFallbackUrl($modx, $resourceId, $testId, $title);
        }

        return $testUrl;
    }

    /**
     * Построение URL вручную (fallback)
     *
     * @param object $modx MODX объект
     * @param int $resourceId ID ресурса
     * @param int $testId ID теста
     * @param string $title Заголовок
     * @return string URL
     */
    private static function buildFallbackUrl($modx, $resourceId, $testId, $title)
    {
        $siteUrl = rtrim($modx->getOption('site_url'), '/');
        $useFriendlyUrls = (bool)$modx->getOption('friendly_urls', null, false);
        $testsParentId = (int)$modx->getOption('lms.user_tests_folder', null, 0);

        if ($useFriendlyUrls && $testsParentId > 0) {
            // Получаем URI родительской папки
            $parentResource = $modx->getObject('modResource', $testsParentId);

            if ($parentResource) {
                $parentUri = trim($parentResource->get('uri'), '/');
                $alias = self::generateUniqueAlias($modx, $title, $testId);
                $testUrl = $siteUrl . '/' . $parentUri . '/' . $alias;
            } else {
                $alias = self::generateUniqueAlias($modx, $title, $testId);
                $testUrl = $siteUrl . '/' . $alias;
            }

            $modx->log(modX::LOG_LEVEL_INFO, "[TestService] Fallback URL with parent: {$testUrl}");
        } elseif ($useFriendlyUrls) {
            $alias = self::generateUniqueAlias($modx, $title, $testId);
            $testUrl = $siteUrl . '/' . $alias;
            $modx->log(modX::LOG_LEVEL_INFO, "[TestService] Fallback URL simple: {$testUrl}");
        } else {
            // Без ЧПУ
            $testUrl = $siteUrl . '/?id=' . $resourceId;
            $modx->log(modX::LOG_LEVEL_INFO, "[TestService] Fallback URL no-friendly: {$testUrl}");
        }

        return $testUrl;
    }

    /**
     * Транслитерация строки (cyrillictolatinext)
     *
     * @param string $str Строка для транслитерации
     * @return string Транслитерированная строка
     */
    private static function transliterate($str)
    {
        $converter = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ь' => '', 'ы' => 'y', 'ъ' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',

            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D',
            'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh', 'З' => 'Z', 'И' => 'I',
            'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N',
            'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T',
            'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'Ts', 'Ч' => 'Ch',
            'Ш' => 'Sh', 'Щ' => 'Sch', 'Ь' => '', 'Ы' => 'Y', 'Ъ' => '',
            'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
        ];

        return strtr($str, $converter);
    }

    /**
     * Публикация теста (изменение статуса публикации)
     *
     * @param object $modx MODX объект
     * @param int $testId ID теста
     * @param string $publicationStatus Новый статус (draft, private, unlisted, public)
     * @param int $currentUserId ID текущего пользователя
     * @return array ['status' => string, 'slug' => string|null, 'public_url' => string|null]
     * @throws Exception При ошибках публикации
     */
    public static function publishTest($modx, $testId, $publicationStatus, $currentUserId)
    {
        // Валидация статуса
        if (!in_array($publicationStatus, ['draft', 'private', 'unlisted', 'public'], true)) {
            throw new ValidationException('Invalid publication status');
        }

        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        // Загружаем тест
        $test = self::getTestForPublication($modx, $prefix, $testId);

        // Проверяем права
        self::checkPublicationRights($modx, $test, $currentUserId);

        // Для публикации нужно минимум 5 вопросов
        if (in_array($publicationStatus, ['unlisted', 'public'])) {
            self::validateQuestionCount($modx, $prefix, $testId);
        }

        // Генерируем slug если нужно
        $slug = self::ensurePublicSlug($modx, $prefix, $test, $publicationStatus, $testId);

        // Обновляем статус
        self::updatePublicationStatus($modx, $prefix, $testId, $publicationStatus, $slug);

        // Создаем уведомления при публикации
        if ($publicationStatus === 'public' && $test['publication_status'] !== 'public') {
            self::notifySharedUsers($modx, $prefix, $testId, $test['title'], $currentUserId);
        }

        // Генерируем публичный URL если есть slug
        $publicUrl = null;
        if ($slug) {
            $publicPageId = (int)$modx->getOption('lms.public_test_page', null, 0);
            $publicUrl = $modx->makeUrl($publicPageId, 'web', ['slug' => $slug], 'full');
        }

        return [
            'status' => $publicationStatus,
            'slug' => $slug,
            'public_url' => $publicUrl
        ];
    }

    /**
     * Получение теста для публикации
     */
    private static function getTestForPublication($modx, $prefix, $testId)
    {
        $stmt = $modx->prepare("
            SELECT created_by, title, publication_status, public_url_slug
            FROM {$prefix}test_tests
            WHERE id = ?
        ");
        $stmt->execute([$testId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test) {
            throw new NotFoundException('Test not found');
        }

        return $test;
    }

    /**
     * Проверка прав на изменение статуса публикации
     */
    private static function checkPublicationRights($modx, $test, $currentUserId)
    {
        $rights = PermissionHelper::getUserRights($modx);
        $isOwner = ((int)$test['created_by'] === $currentUserId);

        if (!$isOwner && !$rights['isAdmin']) {
            throw new PermissionException('Only owner or admin can change publication status');
        }
    }

    /**
     * Валидация количества вопросов для публикации
     */
    private static function validateQuestionCount($modx, $prefix, $testId)
    {
        $stmt = $modx->prepare("
            SELECT COUNT(*)
            FROM {$prefix}test_questions
            WHERE test_id = ? AND published = 1
        ");
        $stmt->execute([$testId]);
        $questionsCount = (int)$stmt->fetchColumn();

        if ($questionsCount < 5) {
            throw new ValidationException('Test must have at least 5 published questions to be public');
        }
    }

    /**
     * Генерация или получение slug для публичного доступа
     */
    private static function ensurePublicSlug($modx, $prefix, $test, $publicationStatus, $testId)
    {
        $slug = $test['public_url_slug'];

        // Генерируем slug только если нужно и его нет
        if (in_array($publicationStatus, ['unlisted', 'public']) && !$slug) {
            $slug = self::generateUniqueSlug($modx, $prefix, $test['title'], $testId);
        }

        return $slug;
    }

    /**
     * Генерация уникального slug для публичного URL
     */
    private static function generateUniqueSlug($modx, $prefix, $title, $testId)
    {
        $baseSlug = $modx->filterPathSegment($title);
        $baseSlug = preg_replace('/[^a-z0-9-]/', '', strtolower($baseSlug));
        $baseSlug = substr($baseSlug, 0, 100);

        if (empty($baseSlug)) {
            $baseSlug = 'test-' . $testId;
        }

        $slug = $baseSlug;
        $counter = 1;

        // Проверяем уникальность
        while (true) {
            $stmt = $modx->prepare("
                SELECT COUNT(*)
                FROM {$prefix}test_tests
                WHERE public_url_slug = ? AND id != ?
            ");
            $stmt->execute([$slug, $testId]);

            if ((int)$stmt->fetchColumn() === 0) {
                break;
            }

            $slug = $baseSlug . '-' . $counter;
            $counter++;

            if ($counter > 100) {
                throw new Exception('Failed to generate unique URL slug');
            }
        }

        return $slug;
    }

    /**
     * Обновление статуса публикации в БД
     */
    private static function updatePublicationStatus($modx, $prefix, $testId, $publicationStatus, $slug)
    {
        $publishedAt = in_array($publicationStatus, ['unlisted', 'public']) ? 'NOW()' : 'NULL';

        $stmt = $modx->prepare("
            UPDATE {$prefix}test_tests
            SET publication_status = ?,
                public_url_slug = ?,
                published_at = $publishedAt
            WHERE id = ?
        ");

        $stmt->execute([$publicationStatus, $slug, $testId]);
    }

    /**
     * Уведомление пользователей о публикации теста
     */
    private static function notifySharedUsers($modx, $prefix, $testId, $testTitle, $currentUserId)
    {
        $stmt = $modx->prepare("
            SELECT DISTINCT user_id
            FROM {$prefix}test_permissions
            WHERE test_id = ?
        ");
        $stmt->execute([$testId]);
        $sharedUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($sharedUsers)) {
            $message = "Тест \"{$testTitle}\" стал публичным";

            $stmt = $modx->prepare("
                INSERT INTO {$prefix}test_notifications
                (user_id, type, test_id, initiator_id, message)
                VALUES (?, 'test_published', ?, ?, ?)
            ");

            foreach ($sharedUsers as $userId) {
                $stmt->execute([$userId, $testId, $currentUserId, $message]);
            }
        }
    }
}
