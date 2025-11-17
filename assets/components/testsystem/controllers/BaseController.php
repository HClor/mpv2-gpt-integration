<?php
/**
 * Base Controller
 *
 * Базовый класс для всех контроллеров AJAX обработчика
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

abstract class BaseController
{
    /**
     * @var modX MODX объект
     */
    protected $modx;

    /**
     * @var string Префикс таблиц БД
     */
    protected $prefix;

    /**
     * Конструктор
     *
     * @param modX $modx MODX объект
     */
    public function __construct($modx)
    {
        $this->modx = $modx;
        $this->prefix = $modx->getOption('table_prefix', null, 'modx_');
    }

    /**
     * Обработка действия
     *
     * @param string $action Название действия
     * @param array $data Данные запроса
     * @return array Ответ в формате ['success' => bool, 'data' => mixed, 'message' => string]
     */
    abstract public function handle($action, $data);

    /**
     * Успешный ответ
     *
     * @param mixed $data Данные
     * @param string $message Сообщение
     * @return array
     */
    protected function success($data = null, $message = '')
    {
        return ResponseHelper::success($data, $message);
    }

    /**
     * Ответ с ошибкой
     *
     * @param string $message Сообщение об ошибке
     * @param int $code Код ошибки
     * @return array
     */
    protected function error($message, $code = 400)
    {
        return ResponseHelper::error($message, $code);
    }

    /**
     * Получение ID текущего пользователя
     *
     * @return int
     */
    protected function getCurrentUserId()
    {
        return PermissionHelper::getCurrentUserId($this->modx);
    }

    /**
     * Проверка авторизации
     *
     * @throws AuthenticationException
     */
    protected function requireAuth()
    {
        PermissionHelper::requireAuthentication($this->modx);
    }

    /**
     * Проверка прав на редактирование
     *
     * @param string|null $message Сообщение об ошибке
     * @throws PermissionException
     */
    protected function requireEditRights($message = null)
    {
        PermissionHelper::requireEditRights($this->modx, $message);
    }
}
