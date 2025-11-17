<?php
/**
 * Test System Data Integrity Check Cronjob
 *
 * Автоматическая проверка целостности данных
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 *
 * УСТАНОВКА В CRONTAB:
 * # Ежедневная проверка в 2:00 ночи
 * 0 2 * * * /usr/bin/php /path/to/core/components/testsystem/cron/integrity-check.php
 *
 * # Еженедельная проверка с автоматической очисткой в воскресенье в 3:00
 * 0 3 * * 0 /usr/bin/php /path/to/core/components/testsystem/cron/integrity-check.php --auto-clean
 *
 * ПАРАМЕТРЫ:
 * --auto-clean      Автоматически очищать найденные проблемы
 * --notify-admin    Отправлять email администратору
 * --clean-old-sessions=90  Очищать сессии старше N дней
 */

// CLI only
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Определяем путь к config.core.php
$configPath = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.core.php';

if (!file_exists($configPath)) {
    die("Error: config.core.php not found at: {$configPath}\n");
}

require_once $configPath;
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

// Инициализируем MODX
$modx = new modX();
$modx->initialize('web');
$modx->getService('error', 'error.modError');

// Подключаем bootstrap
$bootstrapPath = MODX_CORE_PATH . 'components/testsystem/bootstrap.php';
if (!file_exists($bootstrapPath)) {
    die("Error: TestSystem bootstrap not found at: {$bootstrapPath}\n");
}
require_once $bootstrapPath;

// Парсим аргументы командной строки
$options = getopt('', ['auto-clean', 'notify-admin', 'clean-old-sessions::']);

$autoClean = isset($options['auto-clean']);
$notifyAdmin = isset($options['notify-admin']);
$cleanOldSessionsDays = isset($options['clean-old-sessions']) ? (int)$options['clean-old-sessions'] : 0;

// Логируем начало
$logPrefix = '[TestSystem Integrity Check]';
$modx->log(modX::LOG_LEVEL_INFO, "{$logPrefix} Starting integrity check...");
echo "[" . date('Y-m-d H:i:s') . "] Starting TestSystem integrity check\n";

// Проверяем целостность
$report = DataIntegrityService::checkIntegrity($modx);

// Выводим отчет
echo "\n=== INTEGRITY CHECK REPORT ===\n";
echo "Timestamp: {$report['timestamp']}\n";
echo "Total issues found: {$report['total_issues']}\n\n";

if ($report['total_issues'] > 0) {
    if (!empty($report['orphaned_tests'])) {
        echo "Orphaned tests: " . count($report['orphaned_tests']) . "\n";
        foreach ($report['orphaned_tests'] as $test) {
            echo "  - Test #{$test['id']}: {$test['title']} (resource_id: {$test['resource_id']})\n";
        }
    }

    if (!empty($report['orphaned_questions'])) {
        echo "Orphaned questions: " . count($report['orphaned_questions']) . "\n";
    }

    if (!empty($report['orphaned_answers'])) {
        echo "Orphaned answers: " . count($report['orphaned_answers']) . "\n";
    }

    if (!empty($report['orphaned_sessions'])) {
        echo "Orphaned sessions: " . count($report['orphaned_sessions']) . "\n";
    }

    if (!empty($report['orphaned_user_answers'])) {
        echo "Orphaned user answers: " . count($report['orphaned_user_answers']) . "\n";
    }

    if (!empty($report['orphaned_favorites'])) {
        echo "Orphaned favorites: " . count($report['orphaned_favorites']) . "\n";
    }

    if (!empty($report['invalid_category_refs'])) {
        echo "Invalid category references: " . count($report['invalid_category_refs']) . "\n";
    }

    // Логируем проблемы
    $modx->log(modX::LOG_LEVEL_WARN, "{$logPrefix} Found {$report['total_issues']} integrity issues");

    // Автоматическая очистка
    if ($autoClean) {
        echo "\n=== AUTO-CLEAN MODE ===\n";
        $modx->log(modX::LOG_LEVEL_INFO, "{$logPrefix} Starting auto-clean...");

        $cleanResult = DataIntegrityService::cleanAll($modx);

        echo "Total cleaned: {$cleanResult['total_deleted']} records\n";
        echo "Details:\n";
        foreach ($cleanResult['details'] as $type => $result) {
            if ($result['deleted'] > 0) {
                echo "  - {$type}: {$result['deleted']} deleted\n";
            }
        }

        $modx->log(modX::LOG_LEVEL_INFO, "{$logPrefix} Auto-clean completed: {$cleanResult['total_deleted']} records deleted");
    } else {
        echo "\nNote: Run with --auto-clean to automatically fix these issues\n";
    }

    // Отправка уведомления администратору
    if ($notifyAdmin) {
        $adminEmail = $modx->getOption('emailsender');

        if ($adminEmail) {
            $subject = "TestSystem: Data Integrity Issues Found";
            $message = "TestSystem integrity check found {$report['total_issues']} issues.\n\n";
            $message .= "Report:\n";
            $message .= json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($autoClean && isset($cleanResult)) {
                $message .= "\n\nAuto-clean was performed:\n";
                $message .= json_encode($cleanResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }

            mail($adminEmail, $subject, $message);
            echo "\nNotification sent to: {$adminEmail}\n";
            $modx->log(modX::LOG_LEVEL_INFO, "{$logPrefix} Notification sent to {$adminEmail}");
        }
    }
} else {
    echo "No integrity issues found. Database is healthy!\n";
    $modx->log(modX::LOG_LEVEL_INFO, "{$logPrefix} No integrity issues found");
}

// Очистка старых сессий
if ($cleanOldSessionsDays > 0) {
    echo "\n=== CLEANING OLD SESSIONS ===\n";
    echo "Removing sessions older than {$cleanOldSessionsDays} days...\n";

    $sessionResult = DataIntegrityService::cleanOldSessions($modx, $cleanOldSessionsDays);

    echo "Deleted {$sessionResult['deleted']} old sessions\n";
    $modx->log(modX::LOG_LEVEL_INFO, "{$logPrefix} Cleaned {$sessionResult['deleted']} old sessions (>{$cleanOldSessionsDays} days)");
}

echo "\n[" . date('Y-m-d H:i:s') . "] Integrity check completed\n";
$modx->log(modX::LOG_LEVEL_INFO, "{$logPrefix} Integrity check completed");

exit(0);
