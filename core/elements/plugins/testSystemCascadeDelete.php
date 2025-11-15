<?php
/**
 * Test System Cascade Delete Plugin
 *
 * Автоматическое каскадное удаление тестов при удалении связанных ресурсов MODX
 *
 * @name TestSystemCascadeDelete
 * @events OnBeforeDocFormDelete,OnDocFormDelete,OnEmptyTrash
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 *
 * УСТАНОВКА:
 * 1. Создайте новый плагин в MODX Manager
 * 2. Скопируйте содержимое этого файла в код плагина
 * 3. Подпишите плагин на события: OnBeforeDocFormDelete, OnDocFormDelete, OnEmptyTrash
 * 4. Сохраните и активируйте плагин
 */

// Получаем событие
$eventName = $modx->event->name;

// Подключаем необходимые классы
$bootstrapPath = MODX_CORE_PATH . 'components/testsystem/bootstrap.php';
if (file_exists($bootstrapPath)) {
    require_once $bootstrapPath;
} else {
    $modx->log(modX::LOG_LEVEL_ERROR, 'TestSystem bootstrap not found: ' . $bootstrapPath);
    return;
}

$prefix = $modx->getOption('table_prefix', null, 'modx_');

switch ($eventName) {
    case 'OnBeforeDocFormDelete':
        /**
         * Срабатывает ПЕРЕД удалением документа через Manager
         * Удаляем связанный тест до удаления ресурса
         */
        if (isset($resource) && is_object($resource)) {
            $resourceId = $resource->get('id');

            // Ищем тест, связанный с этим ресурсом
            $stmt = $modx->prepare("SELECT id FROM {$prefix}test_tests WHERE resource_id = ?");
            $stmt->execute([$resourceId]);
            $testId = $stmt->fetchColumn();

            if ($testId) {
                // Логируем удаление
                $modx->log(modX::LOG_LEVEL_INFO, "TestSystemCascadeDelete: Deleting test #{$testId} linked to resource #{$resourceId}");

                // Удаляем тест через TestRepository
                try {
                    $deleted = TestRepository::deleteTest($modx, $testId);

                    if ($deleted) {
                        $modx->log(modX::LOG_LEVEL_INFO, "TestSystemCascadeDelete: Successfully deleted test #{$testId}");
                    } else {
                        $modx->log(modX::LOG_LEVEL_WARN, "TestSystemCascadeDelete: Failed to delete test #{$testId}");
                    }
                } catch (Exception $e) {
                    $modx->log(modX::LOG_LEVEL_ERROR, "TestSystemCascadeDelete Error: " . $e->getMessage());
                }
            }
        }
        break;

    case 'OnDocFormDelete':
        /**
         * Срабатывает ПОСЛЕ удаления документа через Manager
         * Используем для окончательной очистки если что-то осталось
         */
        if (isset($resource) && is_object($resource)) {
            $resourceId = $resource->get('id');

            // Проверяем, остались ли тесты с этим resource_id
            $stmt = $modx->prepare("SELECT COUNT(*) FROM {$prefix}test_tests WHERE resource_id = ?");
            $stmt->execute([$resourceId]);
            $remainingTests = $stmt->fetchColumn();

            if ($remainingTests > 0) {
                $modx->log(modX::LOG_LEVEL_WARN, "TestSystemCascadeDelete: Found {$remainingTests} orphaned test(s) for resource #{$resourceId}");

                // Очищаем через DataIntegrityService
                $orphaned = DataIntegrityService::findOrphanedTests($modx);
                if (!empty($orphaned)) {
                    $result = DataIntegrityService::cleanOrphanedTests($modx);
                    $modx->log(modX::LOG_LEVEL_INFO, "TestSystemCascadeDelete: Cleaned {$result['deleted']} orphaned tests");
                }
            }
        }
        break;

    case 'OnEmptyTrash':
        /**
         * Срабатывает при очистке корзины
         * Массовое удаление тестов для удаленных ресурсов
         */
        if (isset($ids) && is_array($ids) && !empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            // Ищем тесты, связанные с удаляемыми ресурсами
            $stmt = $modx->prepare("SELECT id FROM {$prefix}test_tests WHERE resource_id IN ({$placeholders})");
            $stmt->execute($ids);
            $testIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($testIds)) {
                $modx->log(modX::LOG_LEVEL_INFO, "TestSystemCascadeDelete: Emptying trash - found " . count($testIds) . " tests to delete");

                foreach ($testIds as $testId) {
                    try {
                        TestRepository::deleteTest($modx, $testId);
                    } catch (Exception $e) {
                        $modx->log(modX::LOG_LEVEL_ERROR, "TestSystemCascadeDelete Error deleting test #{$testId}: " . $e->getMessage());
                    }
                }

                $modx->log(modX::LOG_LEVEL_INFO, "TestSystemCascadeDelete: Deleted " . count($testIds) . " tests from trash");
            }
        }

        // Также запускаем проверку целостности
        try {
            $orphaned = DataIntegrityService::findOrphanedTests($modx);
            if (!empty($orphaned)) {
                $result = DataIntegrityService::cleanOrphanedTests($modx);
                $modx->log(modX::LOG_LEVEL_INFO, "TestSystemCascadeDelete: OnEmptyTrash - cleaned {$result['deleted']} orphaned tests");
            }
        } catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, "TestSystemCascadeDelete integrity check error: " . $e->getMessage());
        }
        break;
}

return;
