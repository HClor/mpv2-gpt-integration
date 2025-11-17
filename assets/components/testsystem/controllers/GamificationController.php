<?php
/**
 * Gamification Controller
 *
 * Контроллер для работы с системой геймификации
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

require_once __DIR__ . '/BaseController.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/GamificationService.php';

class GamificationController extends BaseController
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
                case 'getMyProfile':
                    return $this->getMyProfile($data);

                case 'getMyAchievements':
                    return $this->getMyAchievements($data);

                case 'getLeaderboard':
                    return $this->getLeaderboard($data);

                case 'getMyStreak':
                    return $this->getMyStreak($data);

                case 'awardXP':
                    return $this->awardXP($data);

                case 'checkAchievements':
                    return $this->checkAchievements($data);

                case 'getXPHistory':
                    return $this->getXPHistory($data);

                case 'getLevelStats':
                    return $this->getLevelStats($data);

                case 'getRarestAchievements':
                    return $this->getRarestAchievements($data);

                case 'updateLeaderboard':
                    return $this->updateLeaderboard($data);

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
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'GamificationController error: ' . $e->getMessage());
            return ResponseHelper::error('An error occurred while processing your request', 500);
        }
    }

    /**
     * Получить профиль текущего пользователя (XP, уровень, достижения)
     *
     * @param array $data
     * @return array
     */
    private function getMyProfile($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $profile = GamificationService::getUserProfile($this->modx, $userId);

        if (!$profile) {
            return ResponseHelper::error('Failed to load profile', 500);
        }

        return ResponseHelper::success('Profile loaded successfully', $profile);
    }

    /**
     * Получить список достижений текущего пользователя
     *
     * @param array $data
     * @return array
     */
    private function getMyAchievements($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $includeNotEarned = ValidationHelper::optionalBool($data, 'include_not_earned', true);

        $achievements = GamificationService::getUserAchievements($this->modx, $userId, $includeNotEarned);

        return ResponseHelper::success('Achievements loaded successfully', [
            'achievements' => $achievements,
            'total' => count($achievements),
            'earned' => count(array_filter($achievements, function ($a) {
                return $a['is_earned'];
            }))
        ]);
    }

    /**
     * Получить рейтинг (leaderboard)
     *
     * @param array $data
     * @return array
     */
    private function getLeaderboard($data)
    {
        $period = ValidationHelper::optionalString($data, 'period', GamificationService::PERIOD_ALL_TIME);
        $categoryId = ValidationHelper::optionalInt($data, 'category_id', null);
        $limit = ValidationHelper::optionalInt($data, 'limit', 50);
        $offset = ValidationHelper::optionalInt($data, 'offset', 0);

        // Валидация периода
        $allowedPeriods = [
            GamificationService::PERIOD_ALL_TIME,
            GamificationService::PERIOD_YEARLY,
            GamificationService::PERIOD_MONTHLY,
            GamificationService::PERIOD_WEEKLY
        ];

        if (!in_array($period, $allowedPeriods, true)) {
            throw new ValidationException('Invalid period. Allowed: ' . implode(', ', $allowedPeriods));
        }

        if ($limit > 100) {
            $limit = 100; // Максимум 100 записей
        }

        $leaderboard = GamificationService::getLeaderboard($this->modx, $period, $categoryId, $limit, $offset);

        // Если пользователь авторизован, добавляем его позицию
        $myPosition = null;
        if ($this->isAuthenticated()) {
            $userId = $this->getCurrentUserId();
            $rank = GamificationService::getUserRank($this->modx, $userId, $period, $categoryId);
            if ($rank) {
                $myPosition = [
                    'rank' => $rank,
                    'period' => $period,
                    'category_id' => $categoryId
                ];
            }
        }

        return ResponseHelper::success('Leaderboard loaded successfully', [
            'leaderboard' => $leaderboard,
            'period' => $period,
            'category_id' => $categoryId,
            'my_position' => $myPosition,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * Получить информацию о серии активности
     *
     * @param array $data
     * @return array
     */
    private function getMyStreak($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $streak = GamificationService::updateUserStreak($this->modx, $userId);

        if (!$streak) {
            return ResponseHelper::error('Failed to load streak information', 500);
        }

        return ResponseHelper::success('Streak information loaded successfully', $streak);
    }

    /**
     * Начислить XP пользователю (только для админов)
     *
     * @param array $data
     * @return array
     */
    private function awardXP($data)
    {
        $this->requireAuth();
        $this->requireAdmin('Only admins can manually award XP');

        $userId = ValidationHelper::requireInt($data, 'user_id', 'User ID is required');
        $xpAmount = ValidationHelper::requireInt($data, 'xp_amount', 'XP amount is required', true, 1);
        $reason = ValidationHelper::requireString($data, 'reason', 'Reason is required');
        $relatedId = ValidationHelper::optionalInt($data, 'related_id', null);
        $relatedType = ValidationHelper::optionalString($data, 'related_type', null);

        // Проверяем, что пользователь существует
        $user = $this->modx->getObject('modUser', $userId);
        if (!$user) {
            return ResponseHelper::error('User not found', 404);
        }

        $success = GamificationService::awardXP(
            $this->modx,
            $userId,
            $xpAmount,
            $reason,
            $relatedId,
            $relatedType
        );

        if (!$success) {
            return ResponseHelper::error('Failed to award XP', 500);
        }

        // Проверяем достижения после начисления XP
        GamificationService::checkAndAwardAchievements($this->modx, $userId, 'xp_awarded', [
            'xp_amount' => $xpAmount
        ]);

        // Получаем обновленный профиль
        $profile = GamificationService::getUserProfile($this->modx, $userId);

        return ResponseHelper::success('XP awarded successfully', [
            'user_id' => $userId,
            'xp_awarded' => $xpAmount,
            'new_total_xp' => $profile['total_xp'],
            'current_level' => $profile['current_level']
        ]);
    }

    /**
     * Проверить и наградить достижениями (вызывается после активностей)
     *
     * @param array $data
     * @return array
     */
    private function checkAchievements($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $activityType = ValidationHelper::requireString($data, 'activity_type', 'Activity type is required');
        $activityData = $data['activity_data'] ?? [];

        $newAchievements = GamificationService::checkAndAwardAchievements(
            $this->modx,
            $userId,
            $activityType,
            $activityData
        );

        return ResponseHelper::success('Achievements checked successfully', [
            'new_achievements' => $newAchievements,
            'count' => count($newAchievements)
        ]);
    }

    /**
     * Получить историю получения XP
     *
     * @param array $data
     * @return array
     */
    private function getXPHistory($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $limit = ValidationHelper::optionalInt($data, 'limit', 50);
        $offset = ValidationHelper::optionalInt($data, 'offset', 0);

        if ($limit > 100) {
            $limit = 100;
        }

        $history = GamificationService::getXPHistory($this->modx, $userId, $limit, $offset);

        return ResponseHelper::success('XP history loaded successfully', [
            'history' => $history,
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * Получить статистику по уровням
     *
     * @param array $data
     * @return array
     */
    private function getLevelStats($data)
    {
        $stats = GamificationService::getLevelStatistics($this->modx);

        return ResponseHelper::success('Level statistics loaded successfully', [
            'levels' => $stats,
            'total_levels' => count($stats)
        ]);
    }

    /**
     * Получить самые редкие достижения
     *
     * @param array $data
     * @return array
     */
    private function getRarestAchievements($data)
    {
        $limit = ValidationHelper::optionalInt($data, 'limit', 10);

        if ($limit > 50) {
            $limit = 50;
        }

        $achievements = GamificationService::getRarestAchievements($this->modx, $limit);

        return ResponseHelper::success('Rarest achievements loaded successfully', [
            'achievements' => $achievements,
            'count' => count($achievements)
        ]);
    }

    /**
     * Обновить рейтинг (только для админов, обычно вызывается через cron)
     *
     * @param array $data
     * @return array
     */
    private function updateLeaderboard($data)
    {
        $this->requireAuth();
        $this->requireAdmin('Only admins can update leaderboard');

        $period = ValidationHelper::optionalString($data, 'period', GamificationService::PERIOD_ALL_TIME);

        // Валидация периода
        $allowedPeriods = [
            GamificationService::PERIOD_ALL_TIME,
            GamificationService::PERIOD_YEARLY,
            GamificationService::PERIOD_MONTHLY,
            GamificationService::PERIOD_WEEKLY
        ];

        if (!in_array($period, $allowedPeriods, true)) {
            throw new ValidationException('Invalid period. Allowed: ' . implode(', ', $allowedPeriods));
        }

        $success = GamificationService::updateLeaderboard($this->modx, $period);

        if (!$success) {
            return ResponseHelper::error('Failed to update leaderboard', 500);
        }

        return ResponseHelper::success('Leaderboard updated successfully', [
            'period' => $period
        ]);
    }
}
