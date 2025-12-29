<?php
/**
 * @deprecated НЕ ИСПОЛЬЗУЕТСЯ - Можно удалить
 * Сниппет: csrfTokenDebug - Диагностика CSRF токена (временный)
 * Назначение: Отладка CSRF защиты (не вызывается из кода)
 *
 * @package TestSystem
 */

$logFile = MODX_CORE_PATH . 'cache/logs/csrf_debug.log';

function debugLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    debugLog("=== CSRF Token Debug Start ===");
    debugLog("#58 Session status: " . session_status());
    debugLog("#59 SESSION isset: " . (isset($_SESSION) ? 'YES' : 'NO'));

    // Подключаем bootstrap
    debugLog("#60 Loading bootstrap...");
    require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';
    debugLog("#61 Bootstrap loaded");

    // Проверяем старый токен
    if (isset($_SESSION['csrf_token'])) {
        debugLog("#62 Old token type: " . gettype($_SESSION['csrf_token']));
    } else {
        debugLog("#62 No old token");
    }

    // Вызываем getTokenMeta
    debugLog("#63 Calling getTokenMeta...");
    $result = CsrfProtection::getTokenMeta();
    debugLog("#64 Success! Length: " . strlen($result));
    debugLog("#65 Result: " . $result);
    debugLog("=== CSRF Token Debug End ===");

    return $result;

} catch (Exception $e) {
    debugLog("#ERROR Exception: " . $e->getMessage());
    debugLog("#ERROR File: " . $e->getFile() . ":" . $e->getLine());
    debugLog("#ERROR Trace: " . $e->getTraceAsString());
    return "<!-- CSRF Token Error: " . htmlspecialchars($e->getMessage()) . " -->";
}
