<?php
/**
 * Auth pending emails queue processor.
 *
 * Example crontab:
 * * * * * /usr/bin/php /path/to/core/components/testsystem/cron/process-pending-emails.php --limit=20
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line\n");
}

$configPath = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.core.php';
if (!file_exists($configPath)) {
    die("Error: config.core.php not found at: {$configPath}\n");
}

require_once $configPath;
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';

$modx = new modX();
$modx->initialize('web');
$modx->getService('error', 'error.modError');

$prefix = $modx->getOption('table_prefix');
$options = getopt('', ['limit::']);
$limit = isset($options['limit']) ? (int)$options['limit'] : 20;
$limit = max(1, min(20, $limit));

$logPrefix = '[pending-emails]';
$modx->log(modX::LOG_LEVEL_INFO, $logPrefix . ' queue processing started, limit=' . $limit);
echo '[' . date('Y-m-d H:i:s') . '] queue processing started, limit=' . $limit . "\n";

$ensureSql = "CREATE TABLE IF NOT EXISTS {$prefix}pending_emails (\n"
    . "  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
    . "  email_type VARCHAR(64) NOT NULL,\n"
    . "  recipient_email VARCHAR(191) NOT NULL,\n"
    . "  subject VARCHAR(255) NOT NULL,\n"
    . "  body_html MEDIUMTEXT NOT NULL,\n"
    . "  status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',\n"
    . "  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,\n"
    . "  max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 5,\n"
    . "  available_at DATETIME NOT NULL,\n"
    . "  sent_at DATETIME NULL,\n"
    . "  last_error TEXT NULL,\n"
    . "  created_at DATETIME NOT NULL,\n"
    . "  updated_at DATETIME NOT NULL,\n"
    . "  PRIMARY KEY (id),\n"
    . "  KEY idx_pending_status_available (status, available_at),\n"
    . "  KEY idx_pending_recipient (recipient_email),\n"
    . "  KEY idx_pending_type (email_type)\n"
    . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($modx->exec($ensureSql) === false) {
    $modx->log(modX::LOG_LEVEL_ERROR, $logPrefix . ' failed to ensure pending_emails table');
    exit(1);
}

$stmt = $modx->prepare(
    "SELECT id, email_type, recipient_email, subject, body_html, attempts, max_attempts\n"
    . "FROM {$prefix}pending_emails\n"
    . "WHERE status = 'pending' AND available_at <= NOW()\n"
    . "ORDER BY id ASC\n"
    . "LIMIT :limit"
);

if ($stmt === false) {
    $modx->log(modX::LOG_LEVEL_ERROR, $logPrefix . ' failed to prepare pending query');
    exit(1);
}

$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
if (!$stmt->execute()) {
    $modx->log(modX::LOG_LEVEL_ERROR, $logPrefix . ' failed to execute pending query: ' . implode(' | ', $stmt->errorInfo()));
    exit(1);
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
if (empty($rows)) {
    $modx->log(modX::LOG_LEVEL_INFO, $logPrefix . ' no pending emails to process');
    echo '[' . date('Y-m-d H:i:s') . "] no pending emails\n";
    exit(0);
}

$mailService = $modx->getService('mail', 'mail.modPHPMailer');
if (!$mailService || !isset($modx->mail)) {
    $modx->log(modX::LOG_LEVEL_ERROR, $logPrefix . ' Mail service unavailable');
    exit(1);
}

$timeout = (int)$modx->getOption('auth_mail_timeout', null, 8);
$smtpTimeout = (int)$modx->getOption('auth_smtp_timeout', null, 8);
$mailer = $modx->mail->mailer ?? null;
if ($mailer) {
    if (property_exists($mailer, 'Timeout')) {
        $mailer->Timeout = max(1, $timeout);
    }
    if (property_exists($mailer, 'SMTPTimeout')) {
        $mailer->SMTPTimeout = max(1, $smtpTimeout);
    }
    if (property_exists($mailer, 'Timelimit')) {
        $mailer->Timelimit = max(2, $timeout + 2);
    }
    if (property_exists($mailer, 'SMTPKeepAlive')) {
        $mailer->SMTPKeepAlive = false;
    }
}

$sentCount = 0;
$failedCount = 0;

foreach ($rows as $row) {
    $id = (int)$row['id'];

    $lockStmt = $modx->prepare(
        "UPDATE {$prefix}pending_emails\n"
        . "SET status = 'processing', updated_at = NOW()\n"
        . "WHERE id = :id AND status = 'pending'"
    );
    if ($lockStmt === false) {
        $modx->log(modX::LOG_LEVEL_ERROR, $logPrefix . ' lock prepare failed for #' . $id);
        $failedCount++;
        continue;
    }

    $lockStmt->bindValue(':id', $id, PDO::PARAM_INT);
    if (!$lockStmt->execute() || $lockStmt->rowCount() !== 1) {
        continue;
    }

    $modx->mail->set(modMail::MAIL_FROM, (string)$modx->getOption('emailsender'));
    $modx->mail->set(modMail::MAIL_FROM_NAME, (string)$modx->getOption('site_name'));
    $modx->mail->set(modMail::MAIL_SUBJECT, (string)$row['subject']);
    $modx->mail->set(modMail::MAIL_BODY, (string)$row['body_html']);
    $modx->mail->address('to', (string)$row['recipient_email']);
    $modx->mail->setHTML(true);

    $isSent = false;
    $errorText = '';

    try {
        $isSent = (bool)$modx->mail->send();
    } catch (Throwable $e) {
        $errorText = $e->getMessage();
    }

    if (!$isSent && $errorText === '') {
        $errorText = isset($modx->mail->mailer->ErrorInfo) ? (string)$modx->mail->mailer->ErrorInfo : 'unknown error';
    }
    $modx->mail->reset();

    if ($isSent) {
        $doneStmt = $modx->prepare(
            "UPDATE {$prefix}pending_emails\n"
            . "SET status = 'sent', sent_at = NOW(), updated_at = NOW(), last_error = NULL\n"
            . "WHERE id = :id"
        );
        if ($doneStmt) {
            $doneStmt->bindValue(':id', $id, PDO::PARAM_INT);
            $doneStmt->execute();
        }
        $modx->log(modX::LOG_LEVEL_INFO, $logPrefix . ' email sent, queueId=' . $id . ', type=' . $row['email_type']);
        $sentCount++;
        continue;
    }

    $attempts = (int)$row['attempts'] + 1;
    $maxAttempts = max(1, (int)$row['max_attempts']);
    $isFinalFail = $attempts >= $maxAttempts;

    if ($isFinalFail) {
        $failStmt = $modx->prepare(
            "UPDATE {$prefix}pending_emails\n"
            . "SET status = 'failed', attempts = :attempts, last_error = :last_error, updated_at = NOW()\n"
            . "WHERE id = :id"
        );
    } else {
        $retryDelayMinutes = min(30, max(1, $attempts * 2));
        $failStmt = $modx->prepare(
            "UPDATE {$prefix}pending_emails\n"
            . "SET status = 'pending', attempts = :attempts, last_error = :last_error,\n"
            . "    available_at = DATE_ADD(NOW(), INTERVAL {$retryDelayMinutes} MINUTE), updated_at = NOW()\n"
            . "WHERE id = :id"
        );
    }

    if ($failStmt) {
        $failStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $failStmt->bindValue(':attempts', $attempts, PDO::PARAM_INT);
        $failStmt->bindValue(':last_error', mb_substr($errorText, 0, 65535), PDO::PARAM_STR);
        $failStmt->execute();
    }

    $modx->log(modX::LOG_LEVEL_ERROR, $logPrefix . ' email send failed, queueId=' . $id . ', attempts=' . $attempts . '/' . $maxAttempts . ', last_error=' . $errorText);
    if ($isFinalFail) {
        $modx->log(modX::LOG_LEVEL_ERROR, $logPrefix . ' moved to failed, queueId=' . $id);
    }

    $failedCount++;
}

$modx->log(modX::LOG_LEVEL_INFO, $logPrefix . ' queue processing finished: processed=' . count($rows) . ', sent=' . $sentCount . ', failed=' . $failedCount);
echo '[' . date('Y-m-d H:i:s') . '] processed=' . count($rows) . ', sent=' . $sentCount . ', failed=' . $failedCount . "\n";

exit(0);
