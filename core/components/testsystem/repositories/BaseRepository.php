<?php
/**
 * Base Repository
 *
 * Базовый класс для работы с базой данных
 *
 * @package MPV2\TestSystem\Repositories
 * @version 1.0.0
 */

namespace MPV2\TestSystem\Repositories;

use PDO;
use PDOStatement;

class BaseRepository
{
    /**
     * @var \modX Экземпляр MODX
     */
    protected $modx;

    /**
     * @var string Префикс таблиц БД
     */
    protected $prefix;

    /**
     * @var string Название таблицы (без префикса)
     */
    protected $tableName;

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
     * Получает полное имя таблицы с префиксом
     *
     * @param string|null $tableName Название таблицы (если null, используется $this->tableName)
     * @return string Полное имя таблицы
     */
    protected function getTableName(?string $tableName = null): string
    {
        $table = $tableName ?? $this->tableName;
        return $this->prefix . $table;
    }

    /**
     * Выполняет подготовленный запрос
     *
     * @param string $sql SQL запрос
     * @param array $params Параметры запроса
     * @return PDOStatement Результат выполнения
     */
    protected function execute(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->modx->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Получает одну запись по ID
     *
     * @param int $id ID записи
     * @param string|null $tableName Название таблицы
     * @return array|null Запись или null
     */
    public function findById(int $id, ?string $tableName = null): ?array
    {
        $table = $this->getTableName($tableName);
        $sql = "SELECT * FROM {$table} WHERE id = ?";

        $stmt = $this->execute($sql, [$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Получает все записи из таблицы
     *
     * @param string|null $tableName Название таблицы
     * @param array $conditions Условия WHERE (ключ => значение)
     * @param string $orderBy Сортировка
     * @param int|null $limit Лимит
     * @param int $offset Смещение
     * @return array Массив записей
     */
    public function findAll(
        ?string $tableName = null,
        array $conditions = [],
        string $orderBy = 'id ASC',
        ?int $limit = null,
        int $offset = 0
    ): array {
        $table = $this->getTableName($tableName);
        $sql = "SELECT * FROM {$table}";

        $params = [];

        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $key => $value) {
                $whereClauses[] = "{$key} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }

        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Получает одну запись по условиям
     *
     * @param array $conditions Условия WHERE (ключ => значение)
     * @param string|null $tableName Название таблицы
     * @return array|null Запись или null
     */
    public function findOne(array $conditions, ?string $tableName = null): ?array
    {
        $table = $this->getTableName($tableName);
        $whereClauses = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            $whereClauses[] = "{$key} = ?";
            $params[] = $value;
        }

        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $whereClauses) . " LIMIT 1";

        $stmt = $this->execute($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Подсчитывает количество записей
     *
     * @param string|null $tableName Название таблицы
     * @param array $conditions Условия WHERE (ключ => значение)
     * @return int Количество записей
     */
    public function count(?string $tableName = null, array $conditions = []): int
    {
        $table = $this->getTableName($tableName);
        $sql = "SELECT COUNT(*) FROM {$table}";

        $params = [];

        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $key => $value) {
                $whereClauses[] = "{$key} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        $stmt = $this->execute($sql, $params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Вставляет новую запись
     *
     * @param array $data Данные для вставки (ключ => значение)
     * @param string|null $tableName Название таблицы
     * @return int ID вставленной записи
     */
    public function insert(array $data, ?string $tableName = null): int
    {
        $table = $this->getTableName($tableName);

        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->execute($sql, $values);

        return (int)$this->modx->lastInsertId();
    }

    /**
     * Обновляет запись
     *
     * @param int $id ID записи
     * @param array $data Данные для обновления (ключ => значение)
     * @param string|null $tableName Название таблицы
     * @return bool True если обновлено
     */
    public function update(int $id, array $data, ?string $tableName = null): bool
    {
        $table = $this->getTableName($tableName);

        $setClauses = [];
        $params = [];

        foreach ($data as $key => $value) {
            $setClauses[] = "{$key} = ?";
            $params[] = $value;
        }

        $params[] = $id;

        $sql = sprintf(
            "UPDATE %s SET %s WHERE id = ?",
            $table,
            implode(', ', $setClauses)
        );

        $stmt = $this->execute($sql, $params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Обновляет записи по условиям
     *
     * @param array $data Данные для обновления (ключ => значение)
     * @param array $conditions Условия WHERE (ключ => значение)
     * @param string|null $tableName Название таблицы
     * @return int Количество обновленных записей
     */
    public function updateWhere(array $data, array $conditions, ?string $tableName = null): int
    {
        $table = $this->getTableName($tableName);

        $setClauses = [];
        $params = [];

        foreach ($data as $key => $value) {
            $setClauses[] = "{$key} = ?";
            $params[] = $value;
        }

        $whereClauses = [];
        foreach ($conditions as $key => $value) {
            $whereClauses[] = "{$key} = ?";
            $params[] = $value;
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $setClauses),
            implode(' AND ', $whereClauses)
        );

        $stmt = $this->execute($sql, $params);

        return $stmt->rowCount();
    }

    /**
     * Удаляет запись по ID
     *
     * @param int $id ID записи
     * @param string|null $tableName Название таблицы
     * @return bool True если удалено
     */
    public function delete(int $id, ?string $tableName = null): bool
    {
        $table = $this->getTableName($tableName);
        $sql = "DELETE FROM {$table} WHERE id = ?";

        $stmt = $this->execute($sql, [$id]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Удаляет записи по условиям
     *
     * @param array $conditions Условия WHERE (ключ => значение)
     * @param string|null $tableName Название таблицы
     * @return int Количество удаленных записей
     */
    public function deleteWhere(array $conditions, ?string $tableName = null): int
    {
        $table = $this->getTableName($tableName);

        $whereClauses = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            $whereClauses[] = "{$key} = ?";
            $params[] = $value;
        }

        $sql = sprintf(
            "DELETE FROM %s WHERE %s",
            $table,
            implode(' AND ', $whereClauses)
        );

        $stmt = $this->execute($sql, $params);

        return $stmt->rowCount();
    }

    /**
     * Проверяет существование записи
     *
     * @param int $id ID записи
     * @param string|null $tableName Название таблицы
     * @return bool True если запись существует
     */
    public function exists(int $id, ?string $tableName = null): bool
    {
        $table = $this->getTableName($tableName);
        $sql = "SELECT 1 FROM {$table} WHERE id = ? LIMIT 1";

        $stmt = $this->execute($sql, [$id]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Начинает транзакцию
     *
     * @return bool True если транзакция начата
     */
    public function beginTransaction(): bool
    {
        return $this->modx->prepare("START TRANSACTION")->execute();
    }

    /**
     * Фиксирует транзакцию
     *
     * @return bool True если транзакция зафиксирована
     */
    public function commit(): bool
    {
        return $this->modx->prepare("COMMIT")->execute();
    }

    /**
     * Откатывает транзакцию
     *
     * @return bool True если транзакция откачена
     */
    public function rollback(): bool
    {
        return $this->modx->prepare("ROLLBACK")->execute();
    }
}
