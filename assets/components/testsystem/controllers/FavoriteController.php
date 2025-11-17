<?php
/**
 * Favorite Controller
 *
 * Контроллер для управления избранными вопросами
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class FavoriteController extends BaseController
{
    /**
     * Список доступных действий
     */
    private $actions = [
        'toggleFavorite',
        'getFavoriteStatus',
        'getFavoriteQuestions'
    ];

    /**
     * Обработка действия
     *
     * @param string $action Название действия
     * @param array $data Данные запроса
     * @return array
     */
    public function handle($action, $data)
    {
        if (!in_array($action, $this->actions)) {
            return $this->error('Unknown action: ' . $action, 404);
        }

        try {
            switch ($action) {
                case 'toggleFavorite':
                    return $this->toggleFavorite($data);

                case 'getFavoriteStatus':
                    return $this->getFavoriteStatus($data);

                case 'getFavoriteQuestions':
                    return $this->getFavoriteQuestions($data);

                default:
                    return $this->error('Action not implemented', 501);
            }
        } catch (AuthenticationException $e) {
            return $this->error($e->getMessage(), 401);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Переключение статуса избранного
     */
    private function toggleFavorite($data)
    {
        $this->requireAuth();

        $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');
        $userId = $this->getCurrentUserId();

        // Проверяем существование
        $stmt = $this->modx->prepare("
            SELECT COUNT(*) FROM {$this->prefix}test_favorites
            WHERE user_id = ? AND question_id = ?
        ");
        $stmt->execute([$userId, $questionId]);
        $exists = (int)$stmt->fetchColumn() > 0;

        if ($exists) {
            // Удаляем из избранного
            $stmt = $this->modx->prepare("
                DELETE FROM {$this->prefix}test_favorites
                WHERE user_id = ? AND question_id = ?
            ");
            $stmt->execute([$userId, $questionId]);
            $isFavorite = false;
        } else {
            // Добавляем в избранное
            $stmt = $this->modx->prepare("
                INSERT INTO {$this->prefix}test_favorites (user_id, question_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$userId, $questionId]);
            $isFavorite = true;
        }

        return $this->success(['is_favorite' => $isFavorite]);
    }

    /**
     * Получение статуса избранного для вопроса
     */
    private function getFavoriteStatus($data)
    {
        // Если не авторизован, возвращаем false
        if (!$this->modx->user->hasSessionContext('web')) {
            return $this->success(['is_favorite' => false]);
        }

        $questionId = ValidationHelper::requireQuestionId($data['question_id'] ?? 0, 'Question ID required');
        $userId = $this->getCurrentUserId();

        $stmt = $this->modx->prepare("
            SELECT COUNT(*) FROM {$this->prefix}test_favorites
            WHERE user_id = ? AND question_id = ?
        ");
        $stmt->execute([$userId, $questionId]);
        $isFavorite = (int)$stmt->fetchColumn() > 0;

        return $this->success(['is_favorite' => $isFavorite]);
    }

    /**
     * Получение списка избранных вопросов
     */
    private function getFavoriteQuestions($data)
    {
        $this->requireAuth();

        $userId = $this->getCurrentUserId();

        $stmt = $this->modx->prepare("
            SELECT
                q.id,
                q.question_text,
                q.question_type,
                q.question_image,
                q.explanation,
                q.explanation_image,
                t.title as test_title,
                t.resource_id
            FROM {$this->prefix}test_favorites f
            INNER JOIN {$this->prefix}test_questions q ON q.id = f.question_id
            INNER JOIN {$this->prefix}test_tests t ON t.id = q.test_id
            WHERE f.user_id = ?
            ORDER BY f.added_at DESC
        ");

        $stmt->execute([$userId]);
        $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->success($favorites);
    }
}
