<?php
/**
 * Notification Service
 *
 * Сервис для управления системой уведомлений и email-рассылок
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class NotificationService
{
    /**
     * Типы уведомлений
     */
    const TYPE_TEST_COMPLETED = 'test_completed';
    const TYPE_TEST_ASSIGNED = 'test_assigned';
    const TYPE_ACHIEVEMENT_EARNED = 'achievement_earned';
    const TYPE_LEVEL_UP = 'level_up';
    const TYPE_PATH_STEP_UNLOCKED = 'path_step_unlocked';
    const TYPE_ESSAY_REVIEWED = 'essay_reviewed';
    const TYPE_DEADLINE_REMINDER = 'deadline_reminder';
    const TYPE_MATERIAL_AVAILABLE = 'material_available';
    const TYPE_PERMISSION_GRANTED = 'permission_granted';
    const TYPE_CUSTOM = 'custom';

    /**
     * Каналы доставки
     */
    const CHANNEL_SYSTEM = 'system';
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_PUSH = 'push';

    /**
     * Приоритеты
     */
    const PRIORITY_LOW = 'low';
    const PRIORITY_NORMAL = 'normal';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    /**
     * Создать уведомление
     *
     * @param modX $modx
     * @param int $userId ID пользователя-получателя
     * @param string $type Тип уведомления
     * @param string $title Заголовок
     * @param string $message Текст сообщения
     * @param array $options Дополнительные опции
     * @return int|false ID созданного уведомления или false
     */
    public static function createNotification($modx, $userId, $type, $title, $message, $options = [])
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $actionUrl = $options['action_url'] ?? null;
        $icon = $options['icon'] ?? null;
        $priority = $options['priority'] ?? self::PRIORITY_NORMAL;
        $relatedType = $options['related_type'] ?? null;
        $relatedId = $options['related_id'] ?? null;
        $metadata = isset($options['metadata']) ? json_encode($options['metadata']) : null;
        $expiresAt = $options['expires_at'] ?? null;

        try {
            $stmt = $pdo->prepare("
                INSERT INTO {$prefix}test_notifications
                (user_id, notification_type, title, message, action_url, icon, priority,
                 related_type, related_id, metadata, expires_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $type,
                $title,
                $message,
                $actionUrl,
                $icon,
                $priority,
                $relatedType,
                $relatedId,
                $metadata,
                $expiresAt
            ]);

            return $pdo->lastInsertId();
        } catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, 'Error creating notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить уведомления пользователя
     *
     * @param modX $modx
     * @param int $userId
     * @param array $filters Фильтры (is_read, type, limit, offset)
     * @return array
     */
    public static function getUserNotifications($modx, $userId, $filters = [])
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $conditions = ["user_id = ?"];
        $params = [$userId];

        // Фильтр по прочтению
        if (isset($filters['is_read'])) {
            $conditions[] = "is_read = ?";
            $params[] = (int)$filters['is_read'];
        }

        // Фильтр по типу
        if (!empty($filters['type'])) {
            $conditions[] = "notification_type = ?";
            $params[] = $filters['type'];
        }

        // Фильтр по приоритету
        if (!empty($filters['priority'])) {
            $conditions[] = "priority = ?";
            $params[] = $filters['priority'];
        }

        $whereClause = implode(' AND ', $conditions);
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;

        $stmt = $pdo->prepare("
            SELECT *
            FROM {$prefix}test_notifications
            WHERE $whereClause
            ORDER BY
                CASE priority
                    WHEN 'urgent' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'normal' THEN 3
                    WHEN 'low' THEN 4
                END,
                created_at DESC
            LIMIT ? OFFSET ?
        ");

        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);

        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Декодируем metadata
        foreach ($notifications as &$notification) {
            if (!empty($notification['metadata'])) {
                $notification['metadata'] = json_decode($notification['metadata'], true);
            }
        }

        return $notifications;
    }

    /**
     * Получить количество непрочитанных уведомлений
     *
     * @param modX $modx
     * @param int $userId
     * @return int
     */
    public static function getUnreadCount($modx, $userId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM {$prefix}test_notifications
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$result['count'];
    }

    /**
     * Пометить уведомление как прочитанное
     *
     * @param modX $modx
     * @param int $notificationId
     * @param int $userId Для проверки прав
     * @return bool
     */
    public static function markAsRead($modx, $notificationId, $userId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            UPDATE {$prefix}test_notifications
            SET is_read = 1, read_at = NOW()
            WHERE id = ? AND user_id = ? AND is_read = 0
        ");

        return $stmt->execute([$notificationId, $userId]);
    }

    /**
     * Пометить все уведомления как прочитанные
     *
     * @param modX $modx
     * @param int $userId
     * @return bool
     */
    public static function markAllAsRead($modx, $userId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            UPDATE {$prefix}test_notifications
            SET is_read = 1, read_at = NOW()
            WHERE user_id = ? AND is_read = 0
        ");

        return $stmt->execute([$userId]);
    }

    /**
     * Удалить уведомление
     *
     * @param modX $modx
     * @param int $notificationId
     * @param int $userId Для проверки прав
     * @return bool
     */
    public static function deleteNotification($modx, $notificationId, $userId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            DELETE FROM {$prefix}test_notifications
            WHERE id = ? AND user_id = ?
        ");

        return $stmt->execute([$notificationId, $userId]);
    }

    /**
     * Добавить уведомление в очередь для отправки
     *
     * @param modX $modx
     * @param int $userId
     * @param string $templateKey Ключ шаблона
     * @param string $channel Канал доставки
     * @param array $placeholders Данные для подстановки
     * @param array $options Дополнительные опции
     * @return int|false ID задачи в очереди
     */
    public static function queueNotification($modx, $userId, $templateKey, $channel, $placeholders = [], $options = [])
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        // Проверяем настройки пользователя
        if (!self::isNotificationEnabled($modx, $userId, $templateKey, $channel)) {
            return false; // Пользователь отключил этот тип уведомлений
        }

        $recipient = $options['recipient'] ?? null;
        $priority = $options['priority'] ?? self::PRIORITY_NORMAL;
        $scheduledAt = $options['scheduled_at'] ?? null;

        // Если recipient не указан и канал = email, получаем email пользователя
        if (!$recipient && $channel === self::CHANNEL_EMAIL) {
            $user = $modx->getObject('modUser', $userId);
            if ($user) {
                $profile = $user->getOne('Profile');
                if ($profile) {
                    $recipient = $profile->get('email');
                }
            }
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO {$prefix}test_notification_queue
                (user_id, template_key, channel, recipient, placeholders, priority, scheduled_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $templateKey,
                $channel,
                $recipient,
                json_encode($placeholders),
                $priority,
                $scheduledAt
            ]);

            return $pdo->lastInsertId();
        } catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, 'Error queueing notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Проверить, включено ли уведомление для пользователя
     *
     * @param modX $modx
     * @param int $userId
     * @param string $notificationType
     * @param string $channel
     * @return bool
     */
    private static function isNotificationEnabled($modx, $userId, $notificationType, $channel)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            SELECT is_enabled, frequency
            FROM {$prefix}test_notification_preferences
            WHERE user_id = ? AND notification_type = ? AND channel = ?
        ");
        $stmt->execute([$userId, $notificationType, $channel]);
        $pref = $stmt->fetch(PDO::FETCH_ASSOC);

        // Если настройки нет, по умолчанию включено
        if (!$pref) {
            return true;
        }

        return $pref['is_enabled'] == 1 && $pref['frequency'] !== 'disabled';
    }

    /**
     * Обновить настройки подписки пользователя
     *
     * @param modX $modx
     * @param int $userId
     * @param string $notificationType
     * @param string $channel
     * @param bool $isEnabled
     * @param string $frequency
     * @return bool
     */
    public static function updatePreference($modx, $userId, $notificationType, $channel, $isEnabled, $frequency = 'immediate')
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO {$prefix}test_notification_preferences
                (user_id, notification_type, channel, is_enabled, frequency)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    is_enabled = VALUES(is_enabled),
                    frequency = VALUES(frequency)
            ");

            return $stmt->execute([
                $userId,
                $notificationType,
                $channel,
                $isEnabled ? 1 : 0,
                $frequency
            ]);
        } catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, 'Error updating notification preference: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить настройки подписок пользователя
     *
     * @param modX $modx
     * @param int $userId
     * @return array
     */
    public static function getUserPreferences($modx, $userId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            SELECT *
            FROM {$prefix}test_notification_preferences
            WHERE user_id = ?
            ORDER BY notification_type, channel
        ");
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Отправить email уведомление
     *
     * @param modX $modx
     * @param int $userId
     * @param string $templateKey
     * @param array $placeholders
     * @param array $options
     * @return bool
     */
    public static function sendEmail($modx, $userId, $templateKey, $placeholders = [], $options = [])
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        try {
            // Получаем шаблон
            $stmt = $pdo->prepare("
                SELECT *
                FROM {$prefix}test_notification_templates
                WHERE template_key = ? AND channel = ? AND is_active = 1
            ");
            $stmt->execute([$templateKey, self::CHANNEL_EMAIL]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                $modx->log(modX::LOG_LEVEL_ERROR, "Email template not found: $templateKey");
                return false;
            }

            // Получаем email пользователя
            $user = $modx->getObject('modUser', $userId);
            if (!$user) {
                return false;
            }

            $profile = $user->getOne('Profile');
            $recipientEmail = $options['recipient'] ?? ($profile ? $profile->get('email') : null);

            if (!$recipientEmail || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                $modx->log(modX::LOG_LEVEL_ERROR, "Invalid email for user $userId");
                return false;
            }

            // Заменяем плейсхолдеры в теме и теле
            $subject = self::replacePlaceholders($template['subject_template'], $placeholders);
            $bodyHtml = self::replacePlaceholders($template['html_template'] ?? $template['body_template'], $placeholders);
            $bodyText = self::replacePlaceholders($template['body_template'], $placeholders);

            // Отправляем через MODX mail service
            $modx->getService('mail', 'mail.modPHPMailer');
            $modx->mail->set(modMail::MAIL_BODY, $bodyHtml);
            $modx->mail->set(modMail::MAIL_BODY_TEXT, $bodyText);
            $modx->mail->set(modMail::MAIL_FROM, $modx->getOption('emailsender'));
            $modx->mail->set(modMail::MAIL_FROM_NAME, $modx->getOption('site_name'));
            $modx->mail->set(modMail::MAIL_SUBJECT, $subject);
            $modx->mail->address('to', $recipientEmail);
            $modx->mail->setHTML(true);

            $sent = $modx->mail->send();
            $modx->mail->reset();

            // Логируем доставку
            self::logDelivery($modx, null, $userId, self::CHANNEL_EMAIL, $template['notification_type'],
                $recipientEmail, $subject, $bodyText, $sent ? 'sent' : 'failed');

            return $sent;
        } catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, 'Error sending email: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Заменить плейсхолдеры в тексте
     *
     * @param string $text
     * @param array $placeholders
     * @return string
     */
    private static function replacePlaceholders($text, $placeholders)
    {
        foreach ($placeholders as $key => $value) {
            // Поддержка простых плейсхолдеров [[+key]]
            $text = str_replace("[[+$key]]", $value, $text);

            // Поддержка условных плейсхолдеров [[+key:notempty=`text`]]
            $pattern = "/\[\[\+$key:notempty=`([^`]*)`\]\]/";
            $replacement = !empty($value) ? '$1' : '';
            $text = preg_replace($pattern, $replacement, $text);

            // Заменяем значение в условном блоке
            $text = str_replace("[[+$key]]", $value, $text);
        }

        return $text;
    }

    /**
     * Залогировать доставку уведомления
     *
     * @param modX $modx
     * @param int|null $notificationId
     * @param int $userId
     * @param string $channel
     * @param string $notificationType
     * @param string|null $recipient
     * @param string|null $subject
     * @param string|null $body
     * @param string $status
     * @param string|null $errorMessage
     * @return bool
     */
    private static function logDelivery($modx, $notificationId, $userId, $channel, $notificationType,
                                       $recipient, $subject, $body, $status, $errorMessage = null)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        try {
            $stmt = $pdo->prepare("
                INSERT INTO {$prefix}test_notification_delivery
                (notification_id, user_id, channel, notification_type, recipient, subject, body, status, error_message, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            return $stmt->execute([
                $notificationId,
                $userId,
                $channel,
                $notificationType,
                $recipient,
                $subject,
                $body,
                $status,
                $errorMessage,
                $status === 'sent' ? date('Y-m-d H:i:s') : null
            ]);
        } catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, 'Error logging delivery: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Обработать очередь уведомлений
     *
     * @param modX $modx
     * @param int $batchSize Количество задач для обработки
     * @return int Количество обработанных задач
     */
    public static function processQueue($modx, $batchSize = 50)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        // Получаем задачи из очереди
        $stmt = $pdo->prepare("
            SELECT *
            FROM {$prefix}test_notification_queue
            WHERE status = 'pending'
            AND scheduled_at <= NOW()
            AND attempts < max_attempts
            ORDER BY priority DESC, scheduled_at ASC
            LIMIT ?
        ");
        $stmt->execute([$batchSize]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $processed = 0;

        foreach ($tasks as $task) {
            // Помечаем как "в обработке"
            $updateStmt = $pdo->prepare("
                UPDATE {$prefix}test_notification_queue
                SET status = 'processing', attempts = attempts + 1
                WHERE id = ?
            ");
            $updateStmt->execute([$task['id']]);

            // Декодируем плейсхолдеры
            $placeholders = json_decode($task['placeholders'], true) ?? [];

            // Отправляем уведомление
            $success = false;
            if ($task['channel'] === self::CHANNEL_EMAIL) {
                $success = self::sendEmail($modx, $task['user_id'], $task['template_key'], $placeholders, [
                    'recipient' => $task['recipient']
                ]);
            }
            // Здесь можно добавить обработку других каналов (push и т.д.)

            // Обновляем статус задачи
            if ($success) {
                $updateStmt = $pdo->prepare("
                    UPDATE {$prefix}test_notification_queue
                    SET status = 'completed', processed_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$task['id']]);
                $processed++;
            } else {
                // Если достигнуто максимальное количество попыток, помечаем как failed
                if ($task['attempts'] + 1 >= $task['max_attempts']) {
                    $updateStmt = $pdo->prepare("
                        UPDATE {$prefix}test_notification_queue
                        SET status = 'failed', processed_at = NOW(), error_message = 'Max attempts reached'
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$task['id']]);
                } else {
                    // Возвращаем в pending для повторной попытки
                    $updateStmt = $pdo->prepare("
                        UPDATE {$prefix}test_notification_queue
                        SET status = 'pending'
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$task['id']]);
                }
            }
        }

        return $processed;
    }

    /**
     * Очистить старые уведомления
     *
     * @param modX $modx
     * @param int $daysToKeep Количество дней для хранения прочитанных уведомлений
     * @return int Количество удаленных записей
     */
    public static function cleanupOldNotifications($modx, $daysToKeep = 30)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("CALL cleanup_old_notifications(?)");
        $stmt->execute([$daysToKeep]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['deleted_notifications'] : 0;
    }

    /**
     * Получить статистику по уведомлениям
     *
     * @param modX $modx
     * @param int|null $userId Если null - общая статистика
     * @return array
     */
    public static function getStatistics($modx, $userId = null)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stats = [];

        if ($userId) {
            // Статистика для конкретного пользователя
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(*) as total,
                    SUM(is_read = 0) as unread,
                    SUM(is_read = 1) as read
                FROM {$prefix}test_notifications
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $stats['notifications'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Статистика по типам
            $stmt = $pdo->prepare("
                SELECT notification_type, COUNT(*) as count
                FROM {$prefix}test_notifications
                WHERE user_id = ?
                GROUP BY notification_type
                ORDER BY count DESC
            ");
            $stmt->execute([$userId]);
            $stats['by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Общая статистика системы
            $stmt = $pdo->query("
                SELECT
                    COUNT(*) as total_notifications,
                    SUM(is_read = 0) as total_unread,
                    COUNT(DISTINCT user_id) as users_with_notifications
                FROM {$prefix}test_notifications
            ");
            $stats['overview'] = $stmt->fetch(PDO::FETCH_ASSOC);

            // Статистика очереди
            $stmt = $pdo->query("
                SELECT status, COUNT(*) as count
                FROM {$prefix}test_notification_queue
                GROUP BY status
            ");
            $stats['queue'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Статистика доставки за последние 7 дней
            $stmt = $pdo->query("
                SELECT channel, status, COUNT(*) as count
                FROM {$prefix}test_notification_delivery
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY channel, status
                ORDER BY channel, status
            ");
            $stats['delivery_7days'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $stats;
    }
}
