<?php
/**
 * Certificate Controller
 *
 * Контроллер для работы с сертификатами
 *
 * @package TestSystem
 * @version 1.0
 * @created 2025-11-15
 */

require_once __DIR__ . '/BaseController.php';
require_once MODX_CORE_PATH . 'components/testsystem/services/CertificateService.php';

class CertificateController extends BaseController
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
                case 'getMyCertificates':
                    return $this->getMyCertificates($data);

                case 'getCertificate':
                    return $this->getCertificate($data);

                case 'verifyCertificate':
                    return $this->verifyCertificate($data);

                case 'issueCertificate':
                    return $this->issueCertificate($data);

                case 'revokeCertificate':
                    return $this->revokeCertificate($data);

                case 'checkEligibility':
                    return $this->checkEligibility($data);

                case 'downloadCertificate':
                    return $this->downloadCertificate($data);

                case 'getCertificateStatistics':
                    return $this->getCertificateStatistics($data);

                case 'cleanupExpired':
                    return $this->cleanupExpired($data);

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
            $this->modx->log(modX::LOG_LEVEL_ERROR, 'CertificateController error: ' . $e->getMessage());
            return ResponseHelper::error('An error occurred while processing your request', 500);
        }
    }

    /**
     * Получить сертификаты текущего пользователя
     *
     * @param array $data
     * @return array
     */
    private function getMyCertificates($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $filters = [
            'entity_type' => ValidationHelper::optionalString($data, 'entity_type', null),
            'is_revoked' => isset($data['is_revoked']) ? (int)$data['is_revoked'] : null,
            'include_expired' => ValidationHelper::optionalBool($data, 'include_expired', true)
        ];

        // Удаляем null значения
        $filters = array_filter($filters, function($value) {
            return $value !== null;
        });

        $certificates = CertificateService::getUserCertificates($this->modx, $userId, $filters);

        return ResponseHelper::success('Certificates loaded successfully', [
            'certificates' => $certificates,
            'count' => count($certificates)
        ]);
    }

    /**
     * Получить конкретный сертификат
     *
     * @param array $data
     * @return array
     */
    private function getCertificate($data)
    {
        $certificateId = ValidationHelper::requireInt($data, 'certificate_id', 'Certificate ID is required');

        $certificate = CertificateService::getCertificate($this->modx, $certificateId);

        if (!$certificate) {
            return ResponseHelper::error('Certificate not found', 404);
        }

        // Проверяем права доступа
        if ($this->isAuthenticated()) {
            $userId = $this->getCurrentUserId();
            $isOwner = ($certificate['user_id'] == $userId);
            $isAdmin = $this->isAdmin() || $this->isExpert();

            if (!$isOwner && !$isAdmin) {
                return ResponseHelper::error('Access denied', 403);
            }
        } else {
            // Неавторизованные пользователи могут видеть только базовую информацию
            $certificate = [
                'certificate_number' => $certificate['certificate_number'],
                'template_name' => $certificate['template_name'],
                'issued_at' => $certificate['issued_at'],
                'status' => $certificate['status']
            ];
        }

        return ResponseHelper::success('Certificate loaded successfully', $certificate);
    }

    /**
     * Верифицировать сертификат (публичный метод)
     *
     * @param array $data
     * @return array
     */
    private function verifyCertificate($data)
    {
        $verificationCode = ValidationHelper::requireString($data, 'verification_code', 'Verification code is required');

        $result = CertificateService::verifyCertificate($this->modx, $verificationCode);

        if (!$result) {
            return ResponseHelper::error('Verification failed', 500);
        }

        $status = $result['verification_status'];

        if ($status === 'valid') {
            return ResponseHelper::success('Certificate is valid', [
                'status' => 'valid',
                'certificate' => [
                    'certificate_number' => $result['certificate_number'],
                    'template_name' => $result['template_name'],
                    'username' => $result['username'],
                    'issued_at' => $result['issued_at'],
                    'expires_at' => $result['expires_at']
                ]
            ]);
        } else {
            return ResponseHelper::success('Certificate verification result', [
                'status' => $status,
                'message' => self::getVerificationMessage($status)
            ]);
        }
    }

    /**
     * Получить сообщение по статусу верификации
     *
     * @param string $status
     * @return string
     */
    private static function getVerificationMessage($status)
    {
        switch ($status) {
            case 'not_found':
                return 'Certificate not found';
            case 'revoked':
                return 'Certificate has been revoked';
            case 'expired':
                return 'Certificate has expired';
            case 'invalid':
                return 'Invalid verification code';
            default:
                return 'Unknown status';
        }
    }

    /**
     * Выдать сертификат (только для админов/экспертов)
     *
     * @param array $data
     * @return array
     */
    private function issueCertificate($data)
    {
        $this->requireAuth();
        $this->requireEditRights('Only admins and experts can issue certificates');

        $templateId = ValidationHelper::requireInt($data, 'template_id', 'Template ID is required');
        $userId = ValidationHelper::requireInt($data, 'user_id', 'User ID is required');
        $entityType = ValidationHelper::optionalString($data, 'entity_type', null);
        $entityId = ValidationHelper::optionalInt($data, 'entity_id', null);
        $certificateData = $data['certificate_data'] ?? [];

        // Проверяем, что пользователь существует
        $user = $this->modx->getObject('modUser', $userId);
        if (!$user) {
            return ResponseHelper::error('User not found', 404);
        }

        $issuedBy = $this->getCurrentUserId();

        $certificateId = CertificateService::issueCertificate(
            $this->modx,
            $templateId,
            $userId,
            $entityType,
            $entityId,
            $certificateData,
            $issuedBy
        );

        if (!$certificateId) {
            return ResponseHelper::error('Failed to issue certificate. It may already exist.', 400);
        }

        $certificate = CertificateService::getCertificate($this->modx, $certificateId);

        return ResponseHelper::success('Certificate issued successfully', [
            'certificate_id' => $certificateId,
            'certificate' => $certificate
        ]);
    }

    /**
     * Отозвать сертификат (только для админов)
     *
     * @param array $data
     * @return array
     */
    private function revokeCertificate($data)
    {
        $this->requireAuth();
        $this->requireAdmin('Only admins can revoke certificates');

        $certificateId = ValidationHelper::requireInt($data, 'certificate_id', 'Certificate ID is required');
        $reason = ValidationHelper::requireString($data, 'reason', 'Reason is required');

        $revokedBy = $this->getCurrentUserId();

        $success = CertificateService::revokeCertificate($this->modx, $certificateId, $revokedBy, $reason);

        if (!$success) {
            return ResponseHelper::error('Failed to revoke certificate', 500);
        }

        return ResponseHelper::success('Certificate revoked successfully', [
            'certificate_id' => $certificateId
        ]);
    }

    /**
     * Проверить возможность получения сертификата
     *
     * @param array $data
     * @return array
     */
    private function checkEligibility($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $templateId = ValidationHelper::requireInt($data, 'template_id', 'Template ID is required');
        $entityType = ValidationHelper::requireString($data, 'entity_type', 'Entity type is required');
        $entityId = ValidationHelper::requireInt($data, 'entity_id', 'Entity ID is required');

        $result = CertificateService::checkEligibility($this->modx, $templateId, $userId, $entityType, $entityId);

        if ($result['eligible']) {
            return ResponseHelper::success('User is eligible for certificate', [
                'eligible' => true
            ]);
        } else {
            return ResponseHelper::success('User is not eligible for certificate', [
                'eligible' => false,
                'reason' => $result['reason']
            ]);
        }
    }

    /**
     * Скачать сертификат
     *
     * @param array $data
     * @return array
     */
    private function downloadCertificate($data)
    {
        $this->requireAuth();
        $userId = $this->getCurrentUserId();

        $certificateId = ValidationHelper::requireInt($data, 'certificate_id', 'Certificate ID is required');

        $certificate = CertificateService::getCertificate($this->modx, $certificateId);

        if (!$certificate) {
            return ResponseHelper::error('Certificate not found', 404);
        }

        // Проверяем права доступа
        $isOwner = ($certificate['user_id'] == $userId);
        $isAdmin = $this->isAdmin() || $this->isExpert();

        if (!$isOwner && !$isAdmin) {
            return ResponseHelper::error('Access denied', 403);
        }

        $downloadUrl = CertificateService::getDownloadUrl($this->modx, $certificateId);

        if (!$downloadUrl) {
            return ResponseHelper::error('Certificate file not found', 404);
        }

        return ResponseHelper::success('Download URL generated', [
            'download_url' => $downloadUrl,
            'certificate_number' => $certificate['certificate_number']
        ]);
    }

    /**
     * Получить статистику по сертификатам (только для админов)
     *
     * @param array $data
     * @return array
     */
    private function getCertificateStatistics($data)
    {
        $this->requireAuth();
        $this->requireAdmin('Only admins can view certificate statistics');

        $stats = CertificateService::getStatistics($this->modx);

        return ResponseHelper::success('Statistics loaded successfully', $stats);
    }

    /**
     * Очистить истекшие сертификаты (только для админов)
     *
     * @param array $data
     * @return array
     */
    private function cleanupExpired($data)
    {
        $this->requireAuth();
        $this->requireAdmin('Only admins can cleanup expired certificates');

        $deletedCount = CertificateService::cleanupExpired($this->modx);

        return ResponseHelper::success('Cleanup completed successfully', [
            'deleted_files' => $deletedCount
        ]);
    }
}
