<?php
/**
 * Access Service
 *
 * Сервис для проверки прав доступа пользователей
 *
 * @package MPV2\TestSystem\Services
 * @version 1.0.0
 */

namespace MPV2\TestSystem\Services;

use PDO;

class AccessService
{
    /**
     * @var \modX Экземпляр MODX
     */
    private $modx;

    /**
     * @var string Префикс таблиц БД
     */
    private $prefix;

    /**
     * @var array Кэш прав пользователей
     */
    private $rightsCache = [];

    /**
     * Конструктор
     *
     * @param \modX $modx Экземпляр MODX
     */
    public function __construct($modx)
    {
        $this->modx = $modx;
        $this->prefix = $modx->getOption('table_prefix', null, 'modx_');
    }

    /**
     * Проверяет, является ли пользователь администратором
     *
     * @param int $userId ID пользователя
     * @return bool True если пользователь админ
     */
    public function isAdmin(int $userId): bool
    {
        // Супер-админ (ID = 1) всегда имеет права
        if ($userId === 1) {
            return true;
        }

        $rights = $this->getUserRights($userId);
        return $rights['isAdmin'];
    }

    /**
     * Проверяет, является ли пользователь экспертом
     *
     * @param int $userId ID пользователя
     * @return bool True если пользователь эксперт
     */
    public function isExpert(int $userId): bool
    {
        $rights = $this->getUserRights($userId);
        return $rights['isExpert'];
    }

    /**
     * Проверяет, может ли пользователь редактировать контент
     *
     * @param int $userId ID пользователя
     * @return bool True если может редактировать
     */
    public function canEdit(int $userId): bool
    {
        return $this->isAdmin($userId) || $this->isExpert($userId);
    }

    /**
     * Проверяет, может ли пользователь редактировать конкретный тест
     *
     * @param int $testId ID теста
     * @param int $userId ID пользователя
     * @return bool True если может редактировать
     */
    public function canEditTest(int $testId, int $userId): bool
    {
        // Админы могут редактировать всё
        if ($this->isAdmin($userId)) {
            return true;
        }

        // Проверяем, является ли пользователь создателем теста
        $stmt = $this->modx->prepare("
            SELECT created_by
            FROM {$this->prefix}test_tests
            WHERE id = ?
        ");
        $stmt->execute([$testId]);
        $createdBy = $stmt->fetchColumn();

        if ($createdBy == $userId) {
            return true;
        }

        // Эксперты могут редактировать опубликованные тесты
        if ($this->isExpert($userId)) {
            $stmt = $this->modx->prepare("
                SELECT publication_status
                FROM {$this->prefix}test_tests
                WHERE id = ?
            ");
            $stmt->execute([$testId]);
            $status = $stmt->fetchColumn();

            return $status === 'published' || $status === 'review';
        }

        return false;
    }

    /**
     * Проверяет, может ли пользователь получить доступ к тесту
     *
     * @param int $testId ID теста
     * @param int $userId ID пользователя
     * @return bool True если есть доступ
     */
    public function canAccessTest(int $testId, int $userId): bool
    {
        // Админы и эксперты имеют доступ ко всем тестам
        if ($this->canEdit($userId)) {
            return true;
        }

        // Проверяем статус публикации теста
        $stmt = $this->modx->prepare("
            SELECT publication_status, is_active
            FROM {$this->prefix}test_tests
            WHERE id = ?
        ");
        $stmt->execute([$testId]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$test || !$test['is_active']) {
            return false;
        }

        // Обычные пользователи могут получить доступ только к опубликованным тестам
        return $test['publication_status'] === 'published';
    }

    /**
     * Проверяет, может ли пользователь удалить тест
     *
     * @param int $testId ID теста
     * @param int $userId ID пользователя
     * @return bool True если может удалить
     */
    public function canDeleteTest(int $testId, int $userId): bool
    {
        // Только админы и создатели могут удалять тесты
        if ($this->isAdmin($userId)) {
            return true;
        }

        $stmt = $this->modx->prepare("
            SELECT created_by
            FROM {$this->prefix}test_tests
            WHERE id = ?
        ");
        $stmt->execute([$testId]);
        $createdBy = $stmt->fetchColumn();

        return $createdBy == $userId;
    }

    /**
     * Проверяет, может ли пользователь управлять пользователями
     *
     * @param int $userId ID пользователя
     * @return bool True если может управлять
     */
    public function canManageUsers(int $userId): bool
    {
        return $this->isAdmin($userId);
    }

    /**
     * Проверяет, может ли пользователь управлять категориями
     *
     * @param int $userId ID пользователя
     * @return bool True если может управлять
     */
    public function canManageCategories(int $userId): bool
    {
        return $this->canEdit($userId);
    }

    /**
     * Проверяет, может ли пользователь импортировать вопросы
     *
     * @param int $userId ID пользователя
     * @return bool True если может импортировать
     */
    public function canImportQuestions(int $userId): bool
    {
        return $this->canEdit($userId);
    }

    /**
     * Получает права пользователя (с кешированием)
     *
     * @param int $userId ID пользователя
     * @return array Массив с правами ['isAdmin' => bool, 'isExpert' => bool, 'canEdit' => bool]
     */
    public function getUserRights(int $userId): array
    {
        // Проверяем кэш
        if (isset($this->rightsCache[$userId])) {
            return $this->rightsCache[$userId];
        }

        // Получаем группы пользователя через MODX API
        $user = $this->modx->getObject('modUser', $userId);
        if (!$user) {
            return [
                'isAdmin' => false,
                'isExpert' => false,
                'canEdit' => false
            ];
        }

        $userGroups = $user->getUserGroupNames();
        $isAdmin = in_array('LMS Admins', $userGroups, true) || $userId === 1;
        $isExpert = in_array('LMS Experts', $userGroups, true);

        $rights = [
            'isAdmin' => $isAdmin,
            'isExpert' => $isExpert,
            'canEdit' => $isAdmin || $isExpert
        ];

        // Сохраняем в кэш
        $this->rightsCache[$userId] = $rights;

        return $rights;
    }

    /**
     * Очищает кэш прав для пользователя
     *
     * @param int $userId ID пользователя
     */
    public function clearRightsCache(int $userId): void
    {
        unset($this->rightsCache[$userId]);
    }

    /**
     * Очищает весь кэш прав
     */
    public function clearAllRightsCache(): void
    {
        $this->rightsCache = [];
    }
}
