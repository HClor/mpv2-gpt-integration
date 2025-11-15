<?php
/**
 * Controller Factory
 *
 * Фабрика для создания контроллеров и маршрутизации запросов
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class ControllerFactory
{
    /**
     * @var modX MODX объект
     */
    private $modx;

    /**
     * Маппинг действий на контроллеры
     * @var array
     */
    private $actionMap = [
        // Session Controller
        'startSession' => 'SessionController',
        'cleanupOldSessions' => 'SessionController',
        'getNextQuestion' => 'SessionController',
        'submitAnswer' => 'SessionController',
        'finishTest' => 'SessionController',

        // Favorite Controller
        'toggleFavorite' => 'FavoriteController',
        'getFavoriteStatus' => 'FavoriteController',
        'getFavoriteQuestions' => 'FavoriteController',

        // Question Controller
        'createQuestion' => 'QuestionController',
        'getQuestion' => 'QuestionController',
        'updateQuestion' => 'QuestionController',
        'deleteQuestion' => 'QuestionController',
        'getAllQuestions' => 'QuestionController',
        'getQuestionAnswers' => 'QuestionController',
        'togglePublished' => 'QuestionController',
        'toggleLearning' => 'QuestionController',

        // Test Controller
        'getTestInfo' => 'TestController',
        'getTestSettings' => 'TestController',
        'updateTestSettings' => 'TestController',
        'updateTest' => 'TestController',
        'deleteTest' => 'TestController',

        // Admin Controller
        'checkIntegrity' => 'AdminController',
        'cleanOrphanedData' => 'AdminController',
        'cleanOrphanedTests' => 'AdminController',
        'cleanOrphanedQuestions' => 'AdminController',
        'cleanOrphanedAnswers' => 'AdminController',
        'cleanOrphanedSessions' => 'AdminController',
        'cleanOldSessions' => 'AdminController',
        'getSystemStats' => 'AdminController',

        // Material Controller
        'createMaterial' => 'MaterialController',
        'getMaterial' => 'MaterialController',
        'updateMaterial' => 'MaterialController',
        'deleteMaterial' => 'MaterialController',
        'getMaterialsList' => 'MaterialController',
        'addContentBlock' => 'MaterialController',
        'updateContentBlock' => 'MaterialController',
        'deleteContentBlock' => 'MaterialController',
        'addAttachment' => 'MaterialController',
        'deleteAttachment' => 'MaterialController',
        'updateProgress' => 'MaterialController',
        'getUserProgress' => 'MaterialController',
        'setTags' => 'MaterialController',
        'linkTest' => 'MaterialController',
        'unlinkTest' => 'MaterialController',

        // Category Controller
        'grantCategoryPermission' => 'CategoryController',
        'revokeCategoryPermission' => 'CategoryController',
        'getCategoryUsers' => 'CategoryController',
        'getUserCategories' => 'CategoryController',
        'checkCategoryPermission' => 'CategoryController',
        'getPermissionHistory' => 'CategoryController',
        'bulkGrantPermissions' => 'CategoryController',
        'bulkRevokePermissions' => 'CategoryController',
    ];

    /**
     * Кеш контроллеров
     * @var array
     */
    private $controllers = [];

    /**
     * Конструктор
     *
     * @param modX $modx MODX объект
     */
    public function __construct($modx)
    {
        $this->modx = $modx;
    }

    /**
     * Проверка, может ли фабрика обработать действие
     *
     * @param string $action Название действия
     * @return bool
     */
    public function canHandle($action)
    {
        return isset($this->actionMap[$action]);
    }

    /**
     * Обработка действия через соответствующий контроллер
     *
     * @param string $action Название действия
     * @param array $data Данные запроса
     * @return array Ответ
     */
    public function handle($action, $data)
    {
        if (!$this->canHandle($action)) {
            return ResponseHelper::error('Unknown action: ' . $action, 404);
        }

        $controllerName = $this->actionMap[$action];
        $controller = $this->getController($controllerName);

        return $controller->handle($action, $data);
    }

    /**
     * Получение экземпляра контроллера (с кешированием)
     *
     * @param string $controllerName Название контроллера
     * @return BaseController
     */
    private function getController($controllerName)
    {
        if (!isset($this->controllers[$controllerName])) {
            $this->controllers[$controllerName] = new $controllerName($this->modx);
        }

        return $this->controllers[$controllerName];
    }

    /**
     * Добавление нового маппинга действия на контроллер
     *
     * @param string $action Действие
     * @param string $controllerName Название контроллера
     */
    public function addMapping($action, $controllerName)
    {
        $this->actionMap[$action] = $controllerName;
    }

    /**
     * Получение списка всех обрабатываемых действий
     *
     * @return array
     */
    public function getHandledActions()
    {
        return array_keys($this->actionMap);
    }
}
