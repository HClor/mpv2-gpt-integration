<?php
/**
 * Database Logic Service
 *
 * Единый PHP-слой для логики, ранее вынесенной в MySQL views/triggers/procedures.
 *
 * @package TestSystem
 * @version 1.0
 */

class DatabaseLogicService
{
    /**
     * Обновить streak пользователя.
     *
     * @param modX $modx
     * @param int $userId
     * @return array|null
     */
    public static function updateUserStreak($modx, $userId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');
        $today = date('Y-m-d');

        $stmt = $modx->prepare("
            SELECT user_id, last_activity_date, current_streak, longest_streak
            FROM {$prefix}test_user_streaks
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $streak = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$streak) {
            $stmt = $modx->prepare("
                INSERT INTO {$prefix}test_user_streaks
                (user_id, current_streak, longest_streak, last_activity_date)
                VALUES (?, 1, 1, ?)
            ");
            $stmt->execute([$userId, $today]);

            return [
                'current_streak' => 1,
                'longest_streak' => 1,
                'last_activity_date' => $today
            ];
        }

        $currentStreak = (int)$streak['current_streak'];
        $longestStreak = (int)$streak['longest_streak'];
        $lastDate = $streak['last_activity_date'];

        if ($lastDate !== $today) {
            $daysDiff = (strtotime($today) - strtotime($lastDate)) / 86400;

            if ($daysDiff == 1) {
                $currentStreak++;
                $longestStreak = max($longestStreak, $currentStreak);
            } else {
                $currentStreak = 1;
            }

            $stmt = $modx->prepare("
                UPDATE {$prefix}test_user_streaks
                SET current_streak = ?,
                    longest_streak = ?,
                    last_activity_date = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$currentStreak, $longestStreak, $today, $userId]);
        }

        return [
            'current_streak' => $currentStreak,
            'longest_streak' => $longestStreak,
            'last_activity_date' => $today
        ];
    }

    /**
     * Синхронизировать уровень пользователя после изменения XP.
     *
     * @param modX $modx
     * @param int $userId
     * @return array|null
     */
    public static function syncUserLevel($modx, $userId)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $stmt = $modx->prepare("
            SELECT id, total_xp, current_level
            FROM {$prefix}test_user_experience
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $experience = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$experience) {
            return null;
        }

        $oldLevel = (int)($experience['current_level'] ?: 1);

        $stmt = $modx->prepare("
            SELECT level, title, xp_required
            FROM {$prefix}test_level_config
            WHERE xp_required <= ?
            ORDER BY level DESC
            LIMIT 1
        ");
        $stmt->execute([(int)$experience['total_xp']]);
        $levelConfig = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$levelConfig) {
            return null;
        }

        $newLevel = (int)$levelConfig['level'];

        $stmt = $modx->prepare("
            SELECT xp_required
            FROM {$prefix}test_level_config
            WHERE level = ?
            LIMIT 1
        ");
        $stmt->execute([$newLevel + 1]);
        $nextLevelXp = $stmt->fetchColumn();
        $xpToNext = ($nextLevelXp !== false) ? max(0, (int)$nextLevelXp - (int)$experience['total_xp']) : 0;

        $stmt = $modx->prepare("
            UPDATE {$prefix}test_user_experience
            SET current_level = ?,
                level = ?,
                title = ?,
                xp_to_next_level = ?
            WHERE id = ?
        ");
        $stmt->execute([$newLevel, $newLevel, $levelConfig['title'], $xpToNext, $experience['id']]);

        if ($newLevel > $oldLevel) {
            NotificationService::createNotification(
                $modx,
                $userId,
                NotificationService::TYPE_LEVEL_UP,
                'Поздравляем с новым уровнем!',
                'Вы достигли уровня ' . $newLevel . ' - "' . $levelConfig['title'] . '"!',
                [
                    'icon' => 'fa-level-up',
                    'priority' => NotificationService::PRIORITY_HIGH,
                    'related_type' => 'level',
                    'related_id' => $newLevel,
                    'metadata' => [
                        'old_level' => $oldLevel,
                        'new_level' => $newLevel,
                        'level_title' => $levelConfig['title'],
                        'total_xp' => (int)$experience['total_xp']
                    ]
                ]
            );
        }

        return [
            'old_level' => $oldLevel,
            'current_level' => $newLevel,
            'title' => $levelConfig['title'],
            'xp_to_next_level' => $xpToNext
        ];
    }

    /**
     * Записать историю изменения category permissions.
     *
     * @param modX $modx
     * @param int $categoryId
     * @param int $userId
     * @param string $action
     * @param string|null $oldRole
     * @param string|null $newRole
     * @param int|null $performedBy
     * @param string|null $performedAt
     * @return bool
     */
    public static function logCategoryPermissionChange($modx, $categoryId, $userId, $action, $oldRole = null, $newRole = null, $performedBy = null, $performedAt = null)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');
        $performedAt = $performedAt ?: date('Y-m-d H:i:s');

        $stmt = $modx->prepare("
            INSERT INTO {$prefix}test_permission_history
            (category_id, user_id, action, old_role, new_role, performed_by, performed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        return $stmt->execute([$categoryId, $userId, $action, $oldRole, $newRole, $performedBy, $performedAt]);
    }

    /**
     * Подготовить поля сертификата перед INSERT.
     *
     * @param modX $modx
     * @param int $templateId
     * @param int $userId
     * @param array $certificateData
     * @return array
     */
    public static function prepareCertificatePayload($modx, $templateId, $userId, array $certificateData)
    {
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        $stmt = $modx->prepare("
            SELECT expiration_days
            FROM {$prefix}test_certificate_templates
            WHERE id = ?
        ");
        $stmt->execute([$templateId]);
        $expirationDays = $stmt->fetchColumn();

        $year = date('Y');
        $stmt = $modx->prepare("
            SELECT COUNT(*) + 1
            FROM {$prefix}test_certificates
            WHERE YEAR(issued_at) = ?
        ");
        $stmt->execute([$year]);
        $yearCount = (int)$stmt->fetchColumn();

        $certificateNumber = !empty($certificateData['certificate_number'])
            ? $certificateData['certificate_number']
            : sprintf('CERT-%s-%06d', $year, $yearCount);

        $verificationCode = !empty($certificateData['verification_code'])
            ? $certificateData['verification_code']
            : hash('sha256', implode('|', [microtime(true), $userId, $templateId, bin2hex(random_bytes(8))]));

        $expiresAt = null;
        if (!empty($certificateData['expires_at'])) {
            $expiresAt = $certificateData['expires_at'];
        } elseif ($expirationDays !== false && $expirationDays !== null) {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . (int)$expirationDays . ' days'));
        }

        return [
            'certificate_number' => $certificateNumber,
            'verification_code' => $verificationCode,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Создать уведомление о выдаче сертификата.
     *
     * @param modX $modx
     * @param array $certificateRow
     * @return void
     */
    public static function notifyCertificateIssued($modx, array $certificateRow)
    {
        $entityName = null;
        $prefix = $modx->getOption('table_prefix', null, 'modx_');

        if ($certificateRow['entity_type'] === 'test' && !empty($certificateRow['entity_id'])) {
            $stmt = $modx->prepare("SELECT title FROM {$prefix}test_tests WHERE id = ?");
            $stmt->execute([$certificateRow['entity_id']]);
            $entityName = $stmt->fetchColumn();
        } elseif ($certificateRow['entity_type'] === 'path' && !empty($certificateRow['entity_id'])) {
            $stmt = $modx->prepare("SELECT title FROM {$prefix}test_learning_paths WHERE id = ?");
            $stmt->execute([$certificateRow['entity_id']]);
            $entityName = $stmt->fetchColumn();
        } elseif ($certificateRow['entity_type'] === 'achievement' && !empty($certificateRow['entity_id'])) {
            $stmt = $modx->prepare("SELECT name FROM {$prefix}test_achievements WHERE id = ?");
            $stmt->execute([$certificateRow['entity_id']]);
            $entityName = $stmt->fetchColumn();
        }

        NotificationService::createNotification(
            $modx,
            (int)$certificateRow['user_id'],
            NotificationService::TYPE_CUSTOM,
            'Получен новый сертификат!',
            'Вам выдан сертификат "' . $certificateRow['template_name'] . '" за ' . ($entityName ?: 'достижение') . '. Номер: ' . $certificateRow['certificate_number'],
            [
                'icon' => 'fa-certificate',
                'priority' => NotificationService::PRIORITY_HIGH,
                'related_type' => 'certificate',
                'related_id' => $certificateRow['id'],
                'metadata' => [
                    'certificate_number' => $certificateRow['certificate_number'],
                    'verification_code' => $certificateRow['verification_code'],
                    'template_name' => $certificateRow['template_name'],
                    'entity_name' => $entityName,
                ]
            ]
        );
    }
}
