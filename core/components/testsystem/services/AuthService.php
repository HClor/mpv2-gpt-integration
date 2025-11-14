<?php
/**
 * Authentication Service
 *
 * Сервис для проверки авторизации пользователей
 *
 * @package MPV2\TestSystem\Services
 * @version 1.0.0
 */

namespace MPV2\TestSystem\Services;

class AuthService
{
    /**
     * @var \modX Экземпляр MODX
     */
    private $modx;

    /**
     * @var int|null ID текущего пользователя (кэш)
     */
    private $currentUserId = null;

    /**
     * Конструктор
     *
     * @param \modX $modx Экземпляр MODX
     */
    public function __construct($modx)
    {
        $this->modx = $modx;
    }

    /**
     * Проверяет, авторизован ли пользователь
     *
     * @param string $context Контекст (по умолчанию 'web')
     * @return bool True если пользователь авторизован
     */
    public function isAuthenticated(string $context = 'web'): bool
    {
        return $this->modx->user->hasSessionContext($context);
    }

    /**
     * Получает ID текущего пользователя
     *
     * @return int ID пользователя (0 если не авторизован)
     */
    public function getUserId(): int
    {
        if ($this->currentUserId !== null) {
            return $this->currentUserId;
        }

        if (!$this->isAuthenticated()) {
            $this->currentUserId = 0;
            return 0;
        }

        $this->currentUserId = (int)$this->modx->user->get('id');
        return $this->currentUserId;
    }

    /**
     * Получает объект текущего пользователя
     *
     * @return \modUser|null Объект пользователя или null
     */
    public function getUser(): ?\modUser
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $userId = $this->getUserId();
        return $this->modx->getObject('modUser', $userId);
    }

    /**
     * Получает данные профиля текущего пользователя
     *
     * @return array Массив с данными профиля
     */
    public function getUserProfile(): array
    {
        if (!$this->isAuthenticated()) {
            return [];
        }

        $user = $this->getUser();
        if (!$user) {
            return [];
        }

        $profile = $user->getOne('Profile');
        if (!$profile) {
            return [];
        }

        return [
            'id' => $user->get('id'),
            'username' => $user->get('username'),
            'fullname' => $profile->get('fullname'),
            'email' => $profile->get('email'),
            'photo' => $profile->get('photo'),
        ];
    }

    /**
     * Требует авторизацию, возвращает HTML с ошибкой если пользователь не авторизован
     *
     * @param string $context Контекст (по умолчанию 'web')
     * @param int|null $loginPageId ID страницы входа (если null, используется системная настройка)
     * @return string|null HTML с сообщением об ошибке или null если авторизован
     */
    public function requireAuth(string $context = 'web', ?int $loginPageId = null): ?string
    {
        if ($this->isAuthenticated($context)) {
            return null;
        }

        // Определяем страницу входа
        if ($loginPageId === null) {
            $loginPageId = (int)$this->modx->getOption('unauthorized_page', null, 1);
        }

        $authUrl = $this->modx->makeUrl($loginPageId, '', '', 'full');

        return sprintf(
            '<div class="alert alert-warning" role="alert">'
            . '<h4 class="alert-heading">Требуется авторизация</h4>'
            . '<p>Для доступа к этому разделу необходимо войти в систему.</p>'
            . '<hr>'
            . '<p class="mb-0"><a href="%s" class="btn btn-primary">Войти</a></p>'
            . '</div>',
            htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Требует авторизацию для AJAX запросов (возвращает JSON)
     *
     * @param string $context Контекст (по умолчанию 'web')
     * @return array|null Массив с ошибкой или null если авторизован
     */
    public function requireAuthAjax(string $context = 'web'): ?array
    {
        if ($this->isAuthenticated($context)) {
            return null;
        }

        return [
            'success' => false,
            'message' => 'Требуется авторизация',
            'error_code' => 'AUTH_REQUIRED',
            'status' => 401
        ];
    }

    /**
     * Проверяет, является ли текущий пользователь владельцем ресурса
     *
     * @param int $resourceId ID ресурса
     * @return bool True если владелец
     */
    public function isOwner(int $resourceId): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $userId = $this->getUserId();
        $resource = $this->modx->getObject('modResource', $resourceId);

        if (!$resource) {
            return false;
        }

        return (int)$resource->get('createdby') === $userId;
    }

    /**
     * Логирует действие пользователя
     *
     * @param string $action Название действия
     * @param array $data Дополнительные данные
     * @param string $level Уровень (INFO, WARNING, ERROR)
     */
    public function logUserAction(string $action, array $data = [], string $level = 'INFO'): void
    {
        $userId = $this->getUserId();
        $username = $this->isAuthenticated() ? $this->modx->user->get('username') : 'guest';

        $logData = array_merge([
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'username' => $username,
            'action' => $action,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ], $data);

        // Логируем в MODX
        $this->modx->log(
            constant('modX::LOG_LEVEL_' . $level),
            sprintf(
                '[TestSystem] User: %s (ID: %d), Action: %s, Data: %s',
                $username,
                $userId,
                $action,
                json_encode($data)
            )
        );
    }

    /**
     * Очищает кэш текущего пользователя
     */
    public function clearUserCache(): void
    {
        $this->currentUserId = null;
    }

    /**
     * Проверяет, имеет ли пользователь определенную роль
     *
     * @param string $roleName Название роли
     * @param int|null $userId ID пользователя (если null, используется текущий)
     * @return bool True если имеет роль
     */
    public function hasRole(string $roleName, ?int $userId = null): bool
    {
        if ($userId === null) {
            $userId = $this->getUserId();
        }

        if ($userId === 0) {
            return false;
        }

        $user = $this->modx->getObject('modUser', $userId);
        if (!$user) {
            return false;
        }

        $userGroups = $user->getUserGroupNames();
        return in_array($roleName, $userGroups, true);
    }

    /**
     * Получает все роли пользователя
     *
     * @param int|null $userId ID пользователя (если null, используется текущий)
     * @return array Массив названий ролей
     */
    public function getUserRoles(?int $userId = null): array
    {
        if ($userId === null) {
            $userId = $this->getUserId();
        }

        if ($userId === 0) {
            return [];
        }

        $user = $this->modx->getObject('modUser', $userId);
        if (!$user) {
            return [];
        }

        return $user->getUserGroupNames();
    }
}
