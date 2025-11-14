<?php
/**
 * Диагностика системы управления доступом
 * Откройте этот файл через браузер: https://yourdomain.com/diagnose_access.php?test_id=123
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Диагностика управления доступом</h1>";
echo "<style>body{font-family:monospace;} .ok{color:green;} .error{color:red;} .info{color:blue;}</style>";

// 1. Подключаем MODX
$configPath = __DIR__ . '/config.core.php';
if (!file_exists($configPath)) {
    die("<p class='error'>❌ config.core.php не найден в " . __DIR__ . "</p>");
}
echo "<p class='ok'>✅ config.core.php найден</p>";

require_once $configPath;
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('web');
echo "<p class='ok'>✅ MODX инициализирован</p>";

// 2. Подключаем bootstrap
$bootstrapPath = MODX_CORE_PATH . 'components/testsystem/bootstrap.php';
if (!file_exists($bootstrapPath)) {
    die("<p class='error'>❌ bootstrap.php не найден: $bootstrapPath</p>");
}
echo "<p class='ok'>✅ bootstrap.php найден</p>";

require_once $bootstrapPath;
echo "<p class='ok'>✅ bootstrap.php подключен</p>";

// 3. Проверяем классы
$classes = [
    'TestPermissionHelper',
    'PermissionHelper',
    'ValidationHelper',
    'ResponseHelper',
    'CsrfProtection'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "<p class='ok'>✅ Класс $class загружен</p>";
    } else {
        echo "<p class='error'>❌ Класс $class НЕ найден</p>";
    }
}

// 4. Проверяем текущего пользователя
if (!$modx->user->hasSessionContext('web')) {
    echo "<p class='error'>❌ Пользователь не авторизован</p>";
    echo "<p class='info'>→ Войдите в систему и обновите страницу</p>";
    die();
}

$userId = (int)$modx->user->get('id');
$username = $modx->user->get('username');
echo "<p class='ok'>✅ Пользователь авторизован: $username (ID: $userId)</p>";

// 5. Проверяем права пользователя
try {
    $isAdmin = TestPermissionHelper::isAdministrator($modx, $userId);
    $isExpert = TestPermissionHelper::isExpert($modx, $userId);
    $isAdminOrExpert = TestPermissionHelper::isAdminOrExpert($modx, $userId);

    echo "<p class='info'>→ isAdministrator: " . ($isAdmin ? 'ДА' : 'НЕТ') . "</p>";
    echo "<p class='info'>→ isExpert: " . ($isExpert ? 'ДА' : 'НЕТ') . "</p>";
    echo "<p class='info'>→ isAdminOrExpert: " . ($isAdminOrExpert ? 'ДА' : 'НЕТ') . "</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Ошибка при проверке прав: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// 6. Проверяем конкретный тест
$testId = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;

if ($testId > 0) {
    echo "<hr><h2>Проверка теста ID: $testId</h2>";

    // Получаем тест
    $test = $modx->getObject('TestTest', $testId);
    if (!$test) {
        echo "<p class='error'>❌ Тест с ID $testId не найден</p>";
    } else {
        echo "<p class='ok'>✅ Тест найден: " . $test->get('title') . "</p>";

        $publicationStatus = $test->get('publication_status');
        $createdBy = $test->get('created_by');

        echo "<p class='info'>→ publication_status: $publicationStatus</p>";
        echo "<p class='info'>→ created_by: $createdBy</p>";

        // Проверяем права на управление доступом
        try {
            $canManage = TestPermissionHelper::canManageAccess($modx, $testId, $userId);
            echo "<p class='" . ($canManage ? 'ok' : 'error') . "'>";
            echo ($canManage ? '✅' : '❌') . " canManageAccess: " . ($canManage ? 'ДА' : 'НЕТ');
            echo "</p>";

            if ($canManage) {
                // Пробуем получить список пользователей
                echo "<h3>Попытка получить список пользователей...</h3>";
                $users = TestPermissionHelper::getTestUsers($modx, $testId);
                echo "<p class='ok'>✅ Список получен, пользователей: " . count($users) . "</p>";

                if (!empty($users)) {
                    echo "<table border='1' cellpadding='5'>";
                    echo "<tr><th>Username</th><th>Role</th><th>Granted At</th></tr>";
                    foreach ($users as $u) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($u['username']) . "</td>";
                        echo "<td>" . htmlspecialchars($u['role']) . "</td>";
                        echo "<td>" . htmlspecialchars($u['granted_at']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
            } else {
                echo "<p class='error'>→ У вас нет прав на управление доступом к этому тесту</p>";
                echo "<p class='info'>→ Владелец: ID $createdBy</p>";
                echo "<p class='info'>→ Ваш ID: $userId</p>";
            }

        } catch (Exception $e) {
            echo "<p class='error'>❌ Ошибка при проверке canManageAccess: " . $e->getMessage() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
    }
} else {
    echo "<hr><p class='info'>ℹ Добавьте ?test_id=123 к URL для проверки конкретного теста</p>";
}

// 7. Проверяем таблицу permissions
echo "<hr><h2>Проверка таблицы modx_test_permissions</h2>";
$prefix = $modx->getOption('table_prefix');
try {
    $stmt = $modx->query("SHOW TABLES LIKE '{$prefix}test_permissions'");
    if ($stmt && $stmt->rowCount() > 0) {
        echo "<p class='ok'>✅ Таблица {$prefix}test_permissions существует</p>";

        $stmt = $modx->query("SELECT COUNT(*) as cnt FROM {$prefix}test_permissions");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p class='info'>→ Записей в таблице: " . $row['cnt'] . "</p>";

        // Показываем структуру
        $stmt = $modx->query("DESCRIBE {$prefix}test_permissions");
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>❌ Таблица {$prefix}test_permissions НЕ существует</p>";
        echo "<p class='info'>→ Выполните миграцию: core/components/testsystem/migrations/003_test_permissions.sql</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Ошибка при проверке таблицы: " . $e->getMessage() . "</p>";
}

echo "<hr><p>Диагностика завершена</p>";
