<?php
/**
 * Test Service
 *
 * Сервис для работы с тестами
 *
 * @package MPV2\TestSystem\Services
 * @version 1.0.0
 */

namespace MPV2\TestSystem\Services;

use MPV2\TestSystem\Repositories\BaseRepository;
use PDO;

class TestService
{
    /**
     * @var \modX Экземпляр MODX
     */
    private $modx;

    /**
     * @var BaseRepository Репозиторий
     */
    private $repository;

    /**
     * @var string Префикс таблиц БД
     */
    private $prefix;

    /**
     * Конструктор
     *
     * @param \modX $modx Экземпляр MODX
     */
    public function __construct($modx)
    {
        $this->modx = $modx;
        $this->repository = new BaseRepository($modx);
        $this->prefix = $modx->getOption('table_prefix', null, 'modx_');
    }

    /**
     * Получает тест по ID
     *
     * @param int $testId ID теста
     * @return array|null Данные теста или null
     */
    public function getTestById(int $testId): ?array
    {
        return $this->repository->findById($testId, 'test_tests');
    }

    /**
     * Получает тест по ID ресурса MODX
     *
     * @param int $resourceId ID ресурса
     * @return array|null Данные теста или null
     */
    public function getTestByResource(int $resourceId): ?array
    {
        return $this->repository->findOne(['resource_id' => $resourceId], 'test_tests');
    }

    /**
     * Получает информацию о тесте для отображения
     *
     * @param int $testId ID теста
     * @param int $userId ID пользователя
     * @return array Информация о тесте
     */
    public function getTestInfo(int $testId, int $userId): array
    {
        $test = $this->getTestById($testId);

        if (!$test) {
            throw new \Exception('Тест не найден');
        }

        // Получаем количество вопросов
        $questionsCount = $this->getQuestionsCount($testId);

        // Получаем статистику пользователя по этому тесту
        $userStats = $this->getUserTestStats($testId, $userId);

        // Проверяем, добавлен ли тест в избранное
        $isFavorite = $this->isTestFavorite($testId, $userId);

        return array_merge($test, [
            'questions_count' => $questionsCount,
            'user_attempts' => $userStats['attempts_count'],
            'user_best_score' => $userStats['best_score'],
            'user_avg_score' => $userStats['avg_score'],
            'is_favorite' => $isFavorite
        ]);
    }

    /**
     * Получает количество вопросов в тесте
     *
     * @param int $testId ID теста
     * @return int Количество вопросов
     */
    public function getQuestionsCount(int $testId): int
    {
        return $this->repository->count('test_questions', [
            'test_id' => $testId,
            'is_active' => 1
        ]);
    }

    /**
     * Получает статистику пользователя по тесту
     *
     * @param int $testId ID теста
     * @param int $userId ID пользователя
     * @return array Статистика
     */
    public function getUserTestStats(int $testId, int $userId): array
    {
        $stmt = $this->modx->prepare("
            SELECT
                COUNT(*) as attempts_count,
                MAX(score) as best_score,
                AVG(score) as avg_score,
                MAX(finished_at) as last_attempt
            FROM {$this->prefix}test_sessions
            WHERE test_id = ? AND user_id = ? AND status = 'completed'
        ");

        $stmt->execute([$testId, $userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'attempts_count' => (int)($result['attempts_count'] ?? 0),
            'best_score' => (float)($result['best_score'] ?? 0),
            'avg_score' => (float)($result['avg_score'] ?? 0),
            'last_attempt' => $result['last_attempt']
        ];
    }

    /**
     * Проверяет, добавлен ли тест в избранное
     *
     * @param int $testId ID теста
     * @param int $userId ID пользователя
     * @return bool True если в избранном
     */
    public function isTestFavorite(int $testId, int $userId): bool
    {
        $result = $this->repository->findOne([
            'test_id' => $testId,
            'user_id' => $userId
        ], 'test_favorites');

        return $result !== null;
    }

    /**
     * Добавляет/удаляет тест из избранного
     *
     * @param int $testId ID теста
     * @param int $userId ID пользователя
     * @return bool True если добавлен, False если удален
     */
    public function toggleFavorite(int $testId, int $userId): bool
    {
        if ($this->isTestFavorite($testId, $userId)) {
            // Удаляем из избранного
            $this->repository->deleteWhere([
                'test_id' => $testId,
                'user_id' => $userId
            ], 'test_favorites');

            return false;
        } else {
            // Добавляем в избранное
            $this->repository->insert([
                'test_id' => $testId,
                'user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s')
            ], 'test_favorites');

            return true;
        }
    }

    /**
     * Создает новую сессию тестирования
     *
     * @param int $testId ID теста
     * @param int $userId ID пользователя
     * @param array $options Дополнительные опции
     * @return int ID созданной сессии
     */
    public function startTestSession(int $testId, int $userId, array $options = []): int
    {
        $test = $this->getTestById($testId);

        if (!$test) {
            throw new \Exception('Тест не найден');
        }

        $sessionData = [
            'test_id' => $testId,
            'user_id' => $userId,
            'started_at' => date('Y-m-d H:i:s'),
            'status' => 'in_progress',
            'mode' => $test['mode'],
            'time_limit' => $test['time_limit'],
            'questions_per_session' => $test['questions_per_session'] ?? 0
        ];

        // Добавляем дополнительные опции
        if (isset($options['knowledge_area_id'])) {
            $sessionData['knowledge_area_id'] = $options['knowledge_area_id'];
        }

        return $this->repository->insert($sessionData, 'test_sessions');
    }

    /**
     * Получает активную сессию пользователя для теста
     *
     * @param int $testId ID теста
     * @param int $userId ID пользователя
     * @return array|null Данные сессии или null
     */
    public function getActiveSession(int $testId, int $userId): ?array
    {
        return $this->repository->findOne([
            'test_id' => $testId,
            'user_id' => $userId,
            'status' => 'in_progress'
        ], 'test_sessions');
    }

    /**
     * Завершает сессию тестирования
     *
     * @param int $sessionId ID сессии
     * @param float $score Итоговый балл
     * @return bool True если успешно завершено
     */
    public function finishTestSession(int $sessionId, float $score): bool
    {
        return $this->repository->update($sessionId, [
            'finished_at' => date('Y-m-d H:i:s'),
            'status' => 'completed',
            'score' => $score
        ], 'test_sessions');
    }

    /**
     * Получает список всех активных тестов
     *
     * @param array $filters Фильтры (category_id, publication_status)
     * @param int|null $limit Лимит
     * @param int $offset Смещение
     * @return array Массив тестов
     */
    public function getActiveTests(array $filters = [], ?int $limit = null, int $offset = 0): array
    {
        $conditions = ['is_active' => 1];

        if (isset($filters['category_id'])) {
            $conditions['category_id'] = $filters['category_id'];
        }

        if (isset($filters['publication_status'])) {
            $conditions['publication_status'] = $filters['publication_status'];
        }

        return $this->repository->findAll(
            'test_tests',
            $conditions,
            'created_at DESC',
            $limit,
            $offset
        );
    }

    /**
     * Создает новый тест
     *
     * @param array $data Данные теста
     * @param int $userId ID создателя
     * @return int ID созданного теста
     */
    public function createTest(array $data, int $userId): int
    {
        $testData = array_merge([
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'is_active' => 1,
            'publication_status' => 'draft'
        ], $data);

        return $this->repository->insert($testData, 'test_tests');
    }

    /**
     * Обновляет тест
     *
     * @param int $testId ID теста
     * @param array $data Данные для обновления
     * @return bool True если обновлено
     */
    public function updateTest(int $testId, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        return $this->repository->update($testId, $data, 'test_tests');
    }

    /**
     * Удаляет тест (мягкое удаление)
     *
     * @param int $testId ID теста
     * @return bool True если удалено
     */
    public function deleteTest(int $testId): bool
    {
        return $this->repository->update($testId, [
            'is_active' => 0,
            'deleted_at' => date('Y-m-d H:i:s')
        ], 'test_tests');
    }

    /**
     * Публикует тест
     *
     * @param int $testId ID теста
     * @return bool True если опубликовано
     */
    public function publishTest(int $testId): bool
    {
        return $this->repository->update($testId, [
            'publication_status' => 'published',
            'published_at' => date('Y-m-d H:i:s')
        ], 'test_tests');
    }

    /**
     * Снимает тест с публикации
     *
     * @param int $testId ID теста
     * @return bool True если снято с публикации
     */
    public function unpublishTest(int $testId): bool
    {
        return $this->repository->update($testId, [
            'publication_status' => 'draft'
        ], 'test_tests');
    }
}
