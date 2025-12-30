<?php
/**
 * Migration: Update myCertificates snippet
 *
 * Fixes certificate display issues:
 * - Use entity_type/entity_id instead of path_id
 * - Use is_revoked instead of status
 * - Properly check expiration dates
 *
 * Run this once after deploying the fix
 */

if (!defined('MODX_CORE_PATH')) {
    define('MODX_API_MODE', true);
    require_once dirname(__DIR__, 4) . '/index.php';
}

$snippet = $modx->getObject('modSnippet', ['name' => 'myCertificates']);
if (!$snippet) {
    die("ERROR: Snippet 'myCertificates' not found\n");
}

echo "Updating snippet 'myCertificates'...\n";

$code = $snippet->get('snippet');

// Fix 1: Update SQL query to use entity_type/entity_id
$oldSQL = <<<'SQL'
    SELECT c.id, c.certificate_number, c.issued_at, c.expires_at, c.status, c.score,
           ct.name as template_name, ct.description as template_description,
           lp.name as path_name, lp.id as path_id
    FROM `{$prefix}test_certificates` c
    LEFT JOIN `{$prefix}test_certificate_templates` ct ON ct.id = c.template_id
    LEFT JOIN `{$prefix}test_learning_paths` lp ON lp.id = c.path_id
    WHERE c.user_id = ?
    ORDER BY c.issued_at DESC
SQL;

$newSQL = <<<'SQL'
    SELECT c.id, c.certificate_number, c.issued_at, c.expires_at,
           c.is_revoked, c.score, c.entity_type, c.entity_id,
           ct.name as template_name, ct.description as template_description,
           CASE
               WHEN c.entity_type = 'test' THEN t.title
               WHEN c.entity_type = 'path' THEN lp.name
               ELSE 'Достижение'
           END as entity_name
    FROM `{$prefix}test_certificates` c
    LEFT JOIN `{$prefix}test_certificate_templates` ct ON ct.id = c.template_id
    LEFT JOIN `{$prefix}test_tests` t ON t.id = c.entity_id AND c.entity_type = 'test'
    LEFT JOIN `{$prefix}test_learning_paths` lp ON lp.id = c.entity_id AND c.entity_type = 'path'
    WHERE c.user_id = ?
    ORDER BY c.issued_at DESC
SQL;

// Replace SQL (handle multiline)
$pattern = '/SELECT c\.id.*?ORDER BY c\.issued_at DESC/s';
$code = preg_replace($pattern, $newSQL, $code);

// Fix 2: Replace status check with is_revoked and expiration check
$oldStatusCode = <<<'PHP'
    $statusClass = $cert['status'] === 'active' ? 'success' : ($cert['status'] === 'revoked' ? 'danger' : 'secondary');
    $statusLabel = $cert['status'] === 'active' ? 'Активен' : ($cert['status'] === 'revoked' ? 'Отозван' : 'Истёк');
PHP;

$newStatusCode = <<<'PHP'
    // Определяем статус сертификата
    if ($cert['is_revoked']) {
        $statusClass = 'danger';
        $statusLabel = 'Отозван';
    } elseif ($cert['expires_at'] && strtotime($cert['expires_at']) < time()) {
        $statusClass = 'secondary';
        $statusLabel = 'Истёк';
    } else {
        $statusClass = 'success';
        $statusLabel = 'Активен';
    }
PHP;

$code = str_replace($oldStatusCode, $newStatusCode, $code);

// Fix 3: Replace path_name with entity_name
$code = str_replace('$cert[\'path_name\']', '$cert[\'entity_name\']', $code);

// Fix 4: Update statistics counters
$oldActiveCount = '$activeCerts = count(array_filter($certificates, fn($c) => $c[\'status\'] === \'active\')) + count($pathCertificates);';
$newActiveCount = '$activeCerts = count(array_filter($certificates, function($c) {
    return !$c[\'is_revoked\'] && (!$c[\'expires_at\'] || strtotime($c[\'expires_at\']) >= time());
})) + count($pathCertificates);';

$code = str_replace($oldActiveCount, $newActiveCount, $code);

$oldExpiredCount = '$expiredCerts = count(array_filter($certificates, fn($c) => $c[\'status\'] === \'expired\'));';
$newExpiredCount = '$expiredCerts = count(array_filter($certificates, function($c) {
    return !$c[\'is_revoked\'] && $c[\'expires_at\'] && strtotime($c[\'expires_at\']) < time();
}));';

$code = str_replace($oldExpiredCount, $newExpiredCount, $code);

// Save updated snippet
$snippet->set('snippet', $code);
if ($snippet->save()) {
    echo "✅ Snippet updated successfully!\n";
    echo "The myCertificates snippet now correctly displays test certificates.\n";
} else {
    echo "❌ Failed to save snippet\n";
}
