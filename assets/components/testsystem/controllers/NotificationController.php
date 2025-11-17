<?php
/**
 * Notification Controller
 *
 * Контроллер для работы с системой уведомлений
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

require_once __DIR__ . '/BaseController.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/NotificationService.php';

class NotificationController extends BaseController
{
    /**
     * Обработка действий
     *
     * @param string $action Название действия
     * @param array $data Данные запроса
     * @return array Ответ
     */
    public function handle($action, $data)
    {
        try {
            switch ($action) {
                case 'getMyNotifications':
                    return $this->getMyNotifications($data);

                case 'getUnreadCount':
                    return $this->getUnreadCount($data);

                case 'markAsRead':
                    return $this->markAsRead($data);

                case 'markAllAsRead':
                    return $this->markAllAsRead($data);

                case 'deleteNotification':
                    return $this->deleteNotification($data);

                case 'createNotification':
                    return $this->createNotification($data);

                case 'sendEmail':
                    return $this->sendEmail($data);

                case 'getMyPreferences':
                    return $this->getMyPreferences($data);

                case 'updatePreference':
                    return $this->updatePreference($data);

                case 'processQueue':
                    return $this->processQueue($data);

                case 'cleanupOld':
                    return $this->cleanupOld($data);

                case 'getStatistics':
                    return $this->getStatistics($data);

                default:
                    return ResponseHelper::error('Unknown action: ' . $action, 404);
            }
        } catch (AuthenticationException $e) {
            return ResponseHelper::error($e->getMessage(), 401);
        } catch (PermissionException $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        } catch (ValidationException $e) {
            return ResponseHelper::error($e->getMessage(), 400);
        } catch (Exception $e) {
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'NotificationController error: ' . $e->getMessage());
            return ResponseHelper::error('An error occurred while processing your request', 500);
        }
    }

    /**
     * Получить уведомления текущего пользователя
     *
     * @param array $data
     * @return array
     */
    private function getMyNotifications($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $filters = [
            'is_read' => isset($data['is_read']) ? (int)$data['is_read'] : null,
            'type' => $data['type'] ?? null,
            'priority' => $data['priority'] ?? null,
            'limit' => ValidationHelper::optionalInt($data, 'limit', 50),
            'offset' => ValidationHelper::optionalInt($data, 'offset', 0)
        ];

        // Удаляем null значения
        $filters = array_filter($filters, function($value) {
            return $value !== null;
        });

        $notifications = NotificationService::getUserNotifications($this->modx, $userId, $filters);

        return ResponseHelper::success('Notifications loaded successfully', [
            'notifications' => $notifications,
            'total' => count($notifications)
        ]);
    }

    /**
     * Получить количество непрочитанных уведомлений
     *
     * @param array $data
     * @return array
     */
    private function getUnreadCount($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $count = NotificationService::getUnreadCount($this->modx, $userId);

        return ResponseHelper::success('Unread count loaded successfully', [
            'unread_count' => $count
        ]);
    }

    /**
     * Пометить уведомление как прочитанное
     *
     * @param array $data
     * @return array
     */
    private function markAsRead($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $notificationId = ValidationHelper::requireInt($data, 'notification_id', 'Notification ID is required');

        $success = NotificationService::markAsRead($this->modx, $notificationId, $userId);

        if (!$success) {
            return ResponseHelper::error('Notification not found or already read', 404);
        }

        return ResponseHelper::success('Notification marked as read', [
            'notification_id' => $notificationId
        ]);
    }

    /**
     * Пометить все уведомления как прочитанные
     *
     * @param array $data
     * @return array
     */
    private function markAllAsRead($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $success = NotificationService::markAllAsRead($this->modx, $userId);

        if (!$success) {
            return ResponseHelper::error('Failed to mark notifications as read', 500);
        }

        return ResponseHelper::success('All notifications marked as read');
    }

    /**
     * Удалить уведомление
     *
     * @param array $data
     * @return array
     */
    private function deleteNotification($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $notificationId = ValidationHelper::requireInt($data, 'notification_id', 'Notification ID is required');

        $success = NotificationService::deleteNotification($this->modx, $notificationId, $userId);

        if (!$success) {
            return ResponseHelper::error('Notification not found', 404);
        }

        return ResponseHelper::success('Notification deleted successfully', [
            'notification_id' => $notificationId
        ]);
    }

    /**
     * Создать уведомление (только для админов/экспертов)
     *
     * @param array $data
     * @return array
     */
    private function createNotification($data)
    {
        $this->requireAuth();
        $this->requireEditRights('Only admins and experts can create notifications');

        $userId = ValidationHelper::requireInt($data, 'user_id', 'User ID is required');
        $type = ValidationHelper::requireString($data, 'type', 'Notification type is required');
        $title = ValidationHelper::requireString($data, 'title', 'Title is required');
        $message = ValidationHelper::requireString($data, 'message', 'Message is required');

        $options = [
            'action_url' => ValidationHelper::optionalString($data, 'action_url', null),
            'icon' => ValidationHelper::optionalString($data, 'icon', null),
            'priority' => ValidationHelper::optionalString($data, 'priority', NotificationService::PRIORITY_NORMAL),
            'related_type' => ValidationHelper::optionalString($data, 'related_type', null),
            'related_id' => ValidationHelper::optionalInt($data, 'related_id', null),
            'metadata' => $data['metadata'] ?? null,
            'expires_at' => ValidationHelper::optionalString($data, 'expires_at', null)
        ];

        // Проверяем, что пользователь существует
        $user = $this->modx->getObject('modUser', $userId);
        if (!$user) {
            return ResponseHelper::error('User not found', 404);
        }

        $notificationId = NotificationService::createNotification($this->modx, $userId, $type, $title, $message, $options);

        if (!$notificationId) {
            return ResponseHelper::error('Failed to create notification', 500);
        }

        return ResponseHelper::success('Notification created successfully', [
            'notification_id' => $notificationId
        ]);
    }

    /**
     * Отправить email уведомление (только для админов)
     *
     * @param array $data
     * @return array
     */
    private function sendEmail($data)
    {
        $this->requireAuth();
        $this->requireAdmin('Only admins can send email notifications');

        $userId = ValidationHelper::requireInt($data, 'user_id', 'User ID is required');
        $templateKey = ValidationHelper::requireString($data, 'template_key', 'Template key is required');
        $placeholders = $data['placeholders'] ?? [];
        $recipient = ValidationHelper::optionalString($data, 'recipient', null);

        // Проверяем, что пользователь существует
        $user = $this->modx->getObject('modUser', $userId);
        if (!$user) {
            return ResponseHelper::error('User not found', 404);
        }

        $success = NotificationService::sendEmail($this->modx, $userId, $templateKey, $placeholders, [
            'recipient' => $recipient
        ]);

        if (!$success) {
            return ResponseHelper::error('Failed to send email', 500);
        }

        return ResponseHelper::success('Email sent successfully');
    }

    /**
     * Получить настройки подписок текущего пользователя
     *
     * @param array $data
     * @return array
     */
    private function getMyPreferences($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $preferences = NotificationService::getUserPreferences($this->modx, $userId);

        return ResponseHelper::success('Preferences loaded successfully', [
            'preferences' => $preferences
        ]);
    }

    /**
     * Обновить настройку подписки
     *
     * @param array $data
     * @return array
     */
    private function updatePreference($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $notificationType = ValidationHelper::requireString($data, 'notification_type', 'Notification type is required');
        $channel = ValidationHelper::requireString($data, 'channel', 'Channel is required');
        $isEnabled = ValidationHelper::optionalBool($data, 'is_enabled', true);
        $frequency = ValidationHelper::optionalString($data, 'frequency', 'immediate');

        // Валидация канала
        $allowedChannels = [
            NotificationService::CHANNEL_SYSTEM,
            NotificationService::CHANNEL_EMAIL,
            NotificationService::CHANNEL_PUSH
        ];

        if (!in_array($channel, $allowedChannels, true)) {
            throw new ValidationException('Invalid channel. Allowed: ' . implode(', ', $allowedChannels));
        }

        // Валидация частоты
        $allowedFrequencies = ['immediate', 'daily_digest', 'weekly_digest', 'disabled'];
        if (!in_array($frequency, $allowedFrequencies, true)) {
            throw new ValidationException('Invalid frequency. Allowed: ' . implode(', ', $allowedFrequencies));
        }

        $success = NotificationService::updatePreference(
            $this->modx,
            $userId,
            $notificationType,
            $channel,
            $isEnabled,
            $frequency
        );

        if (!$success) {
            return ResponseHelper::error('Failed to update preference', 500);
        }

        return ResponseHelper::success('Preference updated successfully', [
            'notification_type' => $notificationType,
            'channel' => $channel,
            'is_enabled' => $isEnabled,
            'frequency' => $frequency
        ]);
    }

    /**
     * Обработать очередь уведомлений (только для админов, обычно вызывается через cron)
     *
     * @param array $data
     * @return array
     */
    private function processQueue($data)
    {
        $this->requireAuth();
        $this->requireAdmin('Only admins can process notification queue');

        $batchSize = ValidationHelper::optionalInt($data, 'batch_size', 50);

        if ($batchSize > 200) {
            $batchSize = 200; // Максимум 200 за раз
        }

        $processed = NotificationService::processQueue($this->modx, $batchSize);

        return ResponseHelper::success('Queue processed successfully', [
            'processed_count' => $processed,
            'batch_size' => $batchSize
        ]);
    }

    /**
     * Очистить старые уведомления (только для админов)
     *
     * @param array $data
     * @return array
     */
    private function cleanupOld($data)
    {
        $this->requireAuth();
        $this->requireAdmin('Only admins can cleanup old notifications');

        $daysToKeep = ValidationHelper::optionalInt($data, 'days_to_keep', 30);

        if ($daysToKeep < 7) {
            throw new ValidationException('Days to keep must be at least 7');
        }

        $deleted = NotificationService::cleanupOldNotifications($this->modx, $daysToKeep);

        return ResponseHelper::success('Old notifications cleaned up successfully', [
            'deleted_count' => $deleted,
            'days_kept' => $daysToKeep
        ]);
    }

    /**
     * Получить статистику по уведомлениям
     *
     * @param array $data
     * @return array
     */
    private function getStatistics($data)
    {
        $this->requireAuth();

        // Админы могут получать общую статистику, пользователи - только свою
        $isAdmin = $this->isAdmin();
        $userId = $isAdmin && !isset($data['my_stats']) ? null : $this->getCurrentUserId();

        $stats = NotificationService::getStatistics($this->modx, $userId);

        return ResponseHelper::success('Statistics loaded successfully', $stats);
    }
}
