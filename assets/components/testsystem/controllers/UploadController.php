<?php
/**
 * Upload Controller
 *
 * Контроллер для загрузки файлов (изображения и документы)
 *
 * @package TestSystem
 * @version 1.0
 * @created 2026-02-23
 */

class UploadController extends BaseController
{
    /**
     * Список доступных действий
     */
    private $actions = array(
        'uploadImage',
        'uploadDocument'
    );

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
                case 'uploadImage':
                    return $this->uploadImage($data);

                case 'uploadDocument':
                    return $this->uploadDocument($data);

                default:
                    return $this->error('Action not implemented', 501);
            }
        } catch (AuthenticationException $e) {
            return $this->error($e->getMessage(), 401);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 400);
        } catch (PermissionException $e) {
            return $this->error($e->getMessage(), 403);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), 500);
        }
    }

    /**
     * Проверка прав на загрузку (админ или эксперт)
     *
     * @throws AuthenticationException
     * @throws PermissionException
     */
    private function requireUploadRights()
    {
        $this->requireAuth();

        $isAdmin = PermissionHelper::isAdmin($this->modx);
        $isExpert = PermissionHelper::isExpert($this->modx);

        if (!$isAdmin && !$isExpert) {
            throw new PermissionException('Нет прав для загрузки файлов');
        }
    }

    /**
     * Загрузка изображения для учебных материалов
     */
    private function uploadImage($data)
    {
        $this->requireUploadRights();

        $imageData = ValidationHelper::requireString($data, 'image', 'Изображение не предоставлено');
        $resourceId = ValidationHelper::optionalInt($data, 'resource_id', 0);

        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $imageData, $matches)) {
            throw new ValidationException('Неверный формат изображения');
        }

        $imageType = $matches[1];
        $imageBase64 = $matches[2];

        $allowedTypes = array('jpeg', 'jpg', 'png', 'gif', 'webp');
        if (!in_array(strtolower($imageType), $allowedTypes)) {
            throw new ValidationException('Недопустимый тип изображения. Разрешены: ' . implode(', ', $allowedTypes));
        }

        $imageContent = base64_decode($imageBase64);
        if ($imageContent === false) {
            throw new ValidationException('Ошибка декодирования изображения');
        }

        $uploadDir = MODX_BASE_PATH . 'assets/uploads/images/';
        if ($resourceId > 0) {
            $uploadDir .= intval($resourceId) . '/';
        }

        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Не удалось создать папку для загрузки');
            }
        }

        $fileName = uniqid('img_', true) . '.' . $imageType;
        $filePath = $uploadDir . $fileName;

        if (file_put_contents($filePath, $imageContent) === false) {
            throw new Exception('Ошибка сохранения файла');
        }

        $imageUrl = $this->modx->getOption('site_url') . 'assets/uploads/images/';
        if ($resourceId > 0) {
            $imageUrl .= intval($resourceId) . '/';
        }
        $imageUrl .= $fileName;

        return $this->success(array(
            'url' => $imageUrl,
            'filename' => $fileName
        ), 'Изображение загружено');
    }

    /**
     * Загрузка документа (PDF, DOC, DOCX и т.д.) для учебных материалов
     */
    private function uploadDocument($data)
    {
        $this->requireUploadRights();

        $documentData = ValidationHelper::requireString($data, 'document', 'Документ не предоставлен');
        $originalName = ValidationHelper::optionalString($data, 'filename', 'document');
        $resourceId = ValidationHelper::optionalInt($data, 'resource_id', 0);

        if (!preg_match('/^data:([^;]+);base64,(.+)$/', $documentData, $matches)) {
            throw new ValidationException('Неверный формат документа');
        }

        $mimeType = $matches[1];
        $documentBase64 = $matches[2];

        $mimeToExt = array(
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
        );

        $extension = isset($mimeToExt[$mimeType]) ? $mimeToExt[$mimeType] : null;

        if (!$extension && preg_match('/\.([a-z0-9]+)$/i', $originalName, $extMatches)) {
            $extension = strtolower($extMatches[1]);
        }

        if (!$extension) {
            throw new ValidationException('Неподдерживаемый тип документа');
        }

        $allowedExtensions = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar');
        if (!in_array(strtolower($extension), $allowedExtensions)) {
            throw new ValidationException('Недопустимый тип документа. Разрешены: ' . implode(', ', $allowedExtensions));
        }

        $documentContent = base64_decode($documentBase64);
        if ($documentContent === false) {
            throw new ValidationException('Ошибка декодирования документа');
        }

        if (strlen($documentContent) > 20 * 1024 * 1024) {
            throw new ValidationException('Размер документа не должен превышать 20MB');
        }

        $uploadDir = MODX_BASE_PATH . 'assets/uploads/documents/';
        if ($resourceId > 0) {
            $uploadDir .= intval($resourceId) . '/';
        }

        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Не удалось создать папку для загрузки');
            }
        }

        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $baseName = str_replace(' ', '_', $baseName);
        $safeName = $this->transliterate($baseName);
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $safeName);
        $safeName = preg_replace('/_+/', '_', $safeName);
        $safeName = mb_substr($safeName, 0, 100);

        $fileName = $safeName . '_' . uniqid() . '.' . $extension;
        $filePath = $uploadDir . $fileName;

        if (file_put_contents($filePath, $documentContent) === false) {
            throw new Exception('Ошибка сохранения файла');
        }

        $documentUrl = $this->modx->getOption('site_url') . 'assets/uploads/documents/';
        if ($resourceId > 0) {
            $documentUrl .= intval($resourceId) . '/';
        }
        $documentUrl .= $fileName;

        return $this->success(array(
            'url' => $documentUrl,
            'filename' => $fileName,
            'original_name' => $originalName,
            'extension' => $extension
        ), 'Документ загружен');
    }

    /**
     * Транслитерация кириллицы в латиницу
     *
     * @param string $str Исходная строка
     * @return string
     */
    private function transliterate($str)
    {
        $ru = array('а','б','в','г','д','е','ё','ж','з','и','й','к','л','м','н','о','п','р','с','т','у','ф','х','ц','ч','ш','щ','ъ','ы','ь','э','ю','я',
                    'А','Б','В','Г','Д','Е','Ё','Ж','З','И','Й','К','Л','М','Н','О','П','Р','С','Т','У','Ф','Х','Ц','Ч','Ш','Щ','Ъ','Ы','Ь','Э','Ю','Я');
        $en = array('a','b','v','g','d','e','e','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','c','ch','sh','sch','','y','','e','yu','ya',
                    'A','B','V','G','D','E','E','Zh','Z','I','Y','K','L','M','N','O','P','R','S','T','U','F','H','C','Ch','Sh','Sch','','Y','','E','Yu','Ya');

        $str = str_replace($ru, $en, $str);
        $str = preg_replace('/[^a-zA-Z0-9-]/', '', $str);
        $str = mb_strtolower($str);

        return $str;
    }
}
