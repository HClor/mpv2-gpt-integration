<?php
/**
 * Certificate Service
 *
 * Сервис для управления сертификатами и верификацией
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

class CertificateService
{
    /**
     * Типы сертификатов
     */
    const TYPE_TEST = 'test';
    const TYPE_PATH = 'path';
    const TYPE_ACHIEVEMENT = 'achievement';
    const TYPE_CUSTOM = 'custom';

    /**
     * Выдать сертификат
     *
     * @param modX $modx
     * @param int $templateId ID шаблона
     * @param int $userId Кому выдается
     * @param string|null $entityType Тип сущности
     * @param int|null $entityId ID сущности
     * @param array $certificateData Данные для подстановки
     * @param int|null $issuedBy Кто выдал
     * @return int|false ID сертификата
     */
    public static function issueCertificate($modx, $templateId, $userId, $entityType = null, $entityId = null, $certificateData = [], $issuedBy = null)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        try {
            // Проверяем, не выдан ли уже сертификат
            if ($entityType && $entityId) {
                $stmt = $pdo->prepare("
                    SELECT id FROM {$prefix}test_certificates
                    WHERE user_id = ? AND entity_type = ? AND entity_id = ?
                    AND is_revoked = 0
                ");
                $stmt->execute([$userId, $entityType, $entityId]);
                if ($stmt->fetch()) {
                    // Сертификат уже выдан
                    return false;
                }
            }

            // Получаем данные шаблона
            $stmt = $pdo->prepare("
                SELECT * FROM {$prefix}test_certificate_templates
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$template) {
                return false;
            }

            // Добавляем базовые данные
            $certificateData['certificate_number'] = ''; // Будет сгенерирован триггером
            $certificateData['issue_date'] = date('d.m.Y');
            $certificateData['verification_url'] = $modx->getOption('site_url') . 'verify-certificate/';

            // Получаем имя пользователя
            $user = $modx->getObject('modUser', $userId);
            if ($user) {
                $profile = $user->getOne('Profile');
                $certificateData['user_name'] = $profile ? $profile->get('fullname') : $user->get('username');
            }

            // Создаем запись сертификата
            $stmt = $pdo->prepare("
                INSERT INTO {$prefix}test_certificates
                (template_id, user_id, entity_type, entity_id, certificate_data, score, issued_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $score = $certificateData['score'] ?? null;
            $stmt->execute([
                $templateId,
                $userId,
                $entityType,
                $entityId,
                json_encode($certificateData),
                $score,
                $issuedBy
            ]);

            $certificateId = $pdo->lastInsertId();

            // Генерируем HTML/PDF сертификат
            self::generateCertificateFile($modx, $certificateId);

            return $certificateId;
        } catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, 'Error issuing certificate: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Сгенерировать файл сертификата
     *
     * @param modX $modx
     * @param int $certificateId
     * @return bool
     */
    private static function generateCertificateFile($modx, $certificateId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        // Получаем данные сертификата
        $stmt = $pdo->prepare("
            SELECT c.*, t.template_html, t.orientation, t.paper_size
            FROM {$prefix}test_certificates c
            JOIN {$prefix}test_certificate_templates t ON t.id = c.template_id
            WHERE c.id = ?
        ");
        $stmt->execute([$certificateId]);
        $cert = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cert) {
            return false;
        }

        // Декодируем данные
        $data = json_decode($cert['certificate_data'], true);
        $data['certificate_number'] = $cert['certificate_number'];
        $data['verification_code'] = $cert['verification_code'];

        // Заменяем плейсхолдеры в HTML
        $html = self::replacePlaceholders($cert['template_html'], $data);

        // Сохраняем HTML версию
        $fileName = 'certificate_' . $cert['certificate_number'] . '.html';
        $filePath = MODX_ASSETS_PATH . 'components/testsystem/certificates/' . $fileName;

        // Создаем директорию если не существует
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filePath, $html);

        // Вычисляем хеш файла
        $fileHash = hash_file('sha256', $filePath);

        // Обновляем запись
        $stmt = $pdo->prepare("
            UPDATE {$prefix}test_certificates
            SET file_path = ?, file_hash = ?
            WHERE id = ?
        ");
        $stmt->execute([$filePath, $fileHash, $certificateId]);

        return true;
    }

    /**
     * Заменить плейсхолдеры в HTML
     *
     * @param string $html
     * @param array $data
     * @return string
     */
    private static function replacePlaceholders($html, $data)
    {
        foreach ($data as $key => $value) {
            $html = str_replace("[[+$key]]", htmlspecialchars($value), $html);
        }
        return $html;
    }

    /**
     * Верифицировать сертификат
     *
     * @param modX $modx
     * @param string $verificationCode
     * @return array|false
     */
    public static function verifyCertificate($modx, $verificationCode)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Вызываем stored procedure
        $stmt = $pdo->prepare("CALL verify_certificate(?, ?, ?)");
        $stmt->execute([$verificationCode, $ipAddress, $userAgent]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && !isset($result['result'])) {
            // Сертификат валидный - декодируем данные
            if (!empty($result['certificate_data'])) {
                $result['certificate_data'] = json_decode($result['certificate_data'], true);
            }
            if (!empty($result['metadata'])) {
                $result['metadata'] = json_decode($result['metadata'], true);
            }
            $result['verification_status'] = 'valid';
        } else {
            $result['verification_status'] = $result['result'] ?? 'invalid';
        }

        return $result;
    }

    /**
     * Отозвать сертификат
     *
     * @param modX $modx
     * @param int $certificateId
     * @param int $revokedBy
     * @param string $reason
     * @return bool
     */
    public static function revokeCertificate($modx, $certificateId, $revokedBy, $reason)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        try {
            $stmt = $pdo->prepare("
                UPDATE {$prefix}test_certificates
                SET is_revoked = 1,
                    revoked_at = NOW(),
                    revoked_by = ?,
                    revoke_reason = ?
                WHERE id = ? AND is_revoked = 0
            ");

            return $stmt->execute([$revokedBy, $reason, $certificateId]);
        } catch (Exception $e) {
            $modx->log(modX::LOG_LEVEL_ERROR, 'Error revoking certificate: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить сертификаты пользователя
     *
     * @param modX $modx
     * @param int $userId
     * @param array $filters
     * @return array
     */
    public static function getUserCertificates($modx, $userId, $filters = [])
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $conditions = ["c.user_id = ?"];
        $params = [$userId];

        if (isset($filters['entity_type'])) {
            $conditions[] = "c.entity_type = ?";
            $params[] = $filters['entity_type'];
        }

        if (isset($filters['is_revoked'])) {
            $conditions[] = "c.is_revoked = ?";
            $params[] = (int)$filters['is_revoked'];
        }

        if (isset($filters['include_expired']) && !$filters['include_expired']) {
            $conditions[] = "(c.expires_at IS NULL OR c.expires_at >= NOW())";
        }

        $whereClause = implode(' AND ', $conditions);

        $stmt = $pdo->prepare("
            SELECT c.*, t.name as template_name, t.certificate_type
            FROM {$prefix}test_certificates c
            JOIN {$prefix}test_certificate_templates t ON t.id = c.template_id
            WHERE $whereClause
            ORDER BY c.issued_at DESC
        ");
        $stmt->execute($params);

        $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Декодируем JSON поля
        foreach ($certificates as &$cert) {
            if (!empty($cert['certificate_data'])) {
                $cert['certificate_data'] = json_decode($cert['certificate_data'], true);
            }
            if (!empty($cert['metadata'])) {
                $cert['metadata'] = json_decode($cert['metadata'], true);
            }

            // Определяем статус
            $cert['status'] = self::getCertificateStatus($cert);
        }

        return $certificates;
    }

    /**
     * Получить статус сертификата
     *
     * @param array $certificate
     * @return string
     */
    private static function getCertificateStatus($certificate)
    {
        if ($certificate['is_revoked']) {
            return 'revoked';
        }

        if ($certificate['expires_at'] && strtotime($certificate['expires_at']) < time()) {
            return 'expired';
        }

        return 'valid';
    }

    /**
     * Получить сертификат по ID
     *
     * @param modX $modx
     * @param int $certificateId
     * @return array|null
     */
    public static function getCertificate($modx, $certificateId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            SELECT c.*, t.name as template_name, t.certificate_type,
                   u.username
            FROM {$prefix}test_certificates c
            JOIN {$prefix}test_certificate_templates t ON t.id = c.template_id
            JOIN {$prefix}users u ON u.id = c.user_id
            WHERE c.id = ?
        ");
        $stmt->execute([$certificateId]);

        $cert = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cert) {
            if (!empty($cert['certificate_data'])) {
                $cert['certificate_data'] = json_decode($cert['certificate_data'], true);
            }
            if (!empty($cert['metadata'])) {
                $cert['metadata'] = json_decode($cert['metadata'], true);
            }
            $cert['status'] = self::getCertificateStatus($cert);
        }

        return $cert;
    }

    /**
     * Проверить, может ли пользователь получить сертификат
     *
     * @param modX $modx
     * @param int $templateId
     * @param int $userId
     * @param string $entityType
     * @param int $entityId
     * @return array ['eligible' => bool, 'reason' => string]
     */
    public static function checkEligibility($modx, $templateId, $userId, $entityType, $entityId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        // Проверяем, не выдан ли уже сертификат
        $stmt = $pdo->prepare("
            SELECT id FROM {$prefix}test_certificates
            WHERE user_id = ? AND entity_type = ? AND entity_id = ?
            AND is_revoked = 0
        ");
        $stmt->execute([$userId, $entityType, $entityId]);
        if ($stmt->fetch()) {
            return ['eligible' => false, 'reason' => 'Certificate already issued'];
        }

        // Получаем требования
        $stmt = $pdo->prepare("
            SELECT * FROM {$prefix}test_certificate_requirements
            WHERE template_id = ?
            ORDER BY sort_order
        ");
        $stmt->execute([$templateId]);
        $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Если нет требований - можно выдавать
        if (empty($requirements)) {
            return ['eligible' => true, 'reason' => null];
        }

        // Проверяем каждое требование
        foreach ($requirements as $req) {
            $reqData = json_decode($req['requirement_data'], true);
            $check = self::checkRequirement($modx, $req['requirement_type'], $reqData, $userId, $entityType, $entityId);

            if (!$check['met']) {
                return ['eligible' => false, 'reason' => $check['reason']];
            }
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * Проверить конкретное требование
     *
     * @param modX $modx
     * @param string $requirementType
     * @param array $requirementData
     * @param int $userId
     * @param string $entityType
     * @param int $entityId
     * @return array
     */
    private static function checkRequirement($modx, $requirementType, $requirementData, $userId, $entityType, $entityId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        switch ($requirementType) {
            case 'min_score':
                $minScore = $requirementData['min_score'] ?? 70;

                if ($entityType === 'test') {
                    $stmt = $pdo->prepare("
                        SELECT MAX(score) as max_score
                        FROM {$prefix}test_sessions
                        WHERE user_id = ? AND test_id = ? AND status = 'completed'
                    ");
                    $stmt->execute([$userId, $entityId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$result || $result['max_score'] < $minScore) {
                        return ['met' => false, 'reason' => "Minimum score of $minScore% required"];
                    }
                }
                return ['met' => true, 'reason' => null];

            case 'all_tests_passed':
                // Все тесты в категории должны быть пройдены
                $categoryId = $requirementData['category_id'] ?? null;
                if ($categoryId) {
                    $stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT t.id) as total_tests
                        FROM {$prefix}test_tests t
                        WHERE t.category_id = ? AND t.published = 1
                    ");
                    $stmt->execute([$categoryId]);
                    $totalTests = $stmt->fetch(PDO::FETCH_ASSOC)['total_tests'];

                    $stmt = $pdo->prepare("
                        SELECT COUNT(DISTINCT s.test_id) as passed_tests
                        FROM {$prefix}test_sessions s
                        JOIN {$prefix}test_tests t ON t.id = s.test_id
                        WHERE s.user_id = ? AND t.category_id = ?
                        AND s.status = 'completed' AND s.score >= 70
                    ");
                    $stmt->execute([$userId, $categoryId]);
                    $passedTests = $stmt->fetch(PDO::FETCH_ASSOC)['passed_tests'];

                    if ($passedTests < $totalTests) {
                        return ['met' => false, 'reason' => 'Not all tests in category passed'];
                    }
                }
                return ['met' => true, 'reason' => null];

            case 'path_completed':
                if ($entityType === 'path') {
                    $stmt = $pdo->prepare("
                        SELECT status
                        FROM {$prefix}test_learning_path_progress lpp
                        JOIN {$prefix}test_learning_path_enrollments lpe ON lpe.id = lpp.enrollment_id
                        WHERE lpe.user_id = ? AND lpe.path_id = ?
                    ");
                    $stmt->execute([$userId, $entityId]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$result || $result['status'] !== 'completed') {
                        return ['met' => false, 'reason' => 'Learning path not completed'];
                    }
                }
                return ['met' => true, 'reason' => null];

            case 'achievement_earned':
                $achievementId = $requirementData['achievement_id'] ?? null;
                if ($achievementId) {
                    $stmt = $pdo->prepare("
                        SELECT id FROM {$prefix}test_user_achievements
                        WHERE user_id = ? AND achievement_id = ?
                    ");
                    $stmt->execute([$userId, $achievementId]);

                    if (!$stmt->fetch()) {
                        return ['met' => false, 'reason' => 'Required achievement not earned'];
                    }
                }
                return ['met' => true, 'reason' => null];

            default:
                return ['met' => true, 'reason' => null];
        }
    }

    /**
     * Автоматически выдать сертификат при выполнении условий
     *
     * @param modX $modx
     * @param int $userId
     * @param string $entityType
     * @param int $entityId
     * @param array $certificateData
     * @return int|false
     */
    public static function autoIssueCertificate($modx, $userId, $entityType, $entityId, $certificateData = [])
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        // Находим подходящий шаблон
        $stmt = $pdo->prepare("
            SELECT id FROM {$prefix}test_certificate_templates
            WHERE certificate_type = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$entityType]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            return false;
        }

        $templateId = $template['id'];

        // Проверяем возможность выдачи
        $eligibility = self::checkEligibility($modx, $templateId, $userId, $entityType, $entityId);

        if (!$eligibility['eligible']) {
            return false;
        }

        // Выдаем сертификат
        return self::issueCertificate($modx, $templateId, $userId, $entityType, $entityId, $certificateData);
    }

    /**
     * Получить статистику по сертификатам
     *
     * @param modX $modx
     * @return array
     */
    public static function getStatistics($modx)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->query("CALL get_certificate_statistics()");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Очистить истекшие сертификаты
     *
     * @param modX $modx
     * @return int
     */
    public static function cleanupExpired($modx)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->query("CALL cleanup_expired_certificates()");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['expired_files_cleared'] : 0;
    }

    /**
     * Получить URL для скачивания сертификата
     *
     * @param modX $modx
     * @param int $certificateId
     * @return string|null
     */
    public static function getDownloadUrl($modx, $certificateId)
    {
        $prefix = $modx->getOption('table_prefix');
        $pdo = $modx->getPDO();

        $stmt = $pdo->prepare("
            SELECT certificate_number, file_path
            FROM {$prefix}test_certificates
            WHERE id = ? AND is_revoked = 0
        ");
        $stmt->execute([$certificateId]);
        $cert = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cert || !$cert['file_path'] || !file_exists($cert['file_path'])) {
            return null;
        }

        $fileName = 'certificate_' . $cert['certificate_number'] . '.html';
        return $modx->getOption('assets_url') . 'components/testsystem/certificates/' . $fileName;
    }
}
