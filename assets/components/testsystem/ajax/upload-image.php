<?php
$configPath = dirname(dirname(dirname(dirname(__FILE__)))) . '/config.core.php';
if (!file_exists($configPath)) {
    $configPath = dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.core.php';
}

if (!file_exists($configPath)) {
    die(json_encode(['success' => false, 'message' => 'Config not found']));
}

require_once $configPath;
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
require_once MODX_CORE_PATH . 'components/testsystem/bootstrap.php';

$modx = new modX();
$modx->initialize('web');

header('Content-Type: application/json; charset=utf-8');

// Проверка авторизации
if (!$modx->user->hasSessionContext('web')) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

// CSRF Protection - проверяем токен в заголовке или POST данных
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
if (!$csrfToken || !CsrfProtection::validateToken($csrfToken)) {
    die(json_encode(['success' => false, 'message' => 'CSRF token validation failed']));
}

// Проверка прав
$userId = (int)$modx->user->get('id');
$userGroups = $modx->user->getUserGroupNames();
$canUpload = in_array('LMS Experts', $userGroups, true) || in_array('LMS Admins', $userGroups, true);

if (!$canUpload) {
    die(json_encode(['success' => false, 'message' => 'No permission']));
}

if (!isset($_FILES['image'])) {
    die(json_encode(['success' => false, 'message' => 'No file uploaded']));
}

$file = $_FILES['image'];

// Максимум 5MB
if ($file['size'] > 5 * 1024 * 1024) {
    die(json_encode(['success' => false, 'message' => 'File too large (max 5MB)']));
}

// УЛУЧШЕННАЯ ВАЛИДАЦИЯ: проверяем реальный MIME тип через getimagesize()
// Это защищает от подделки MIME типа в заголовках
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    die(json_encode(['success' => false, 'message' => 'File is not a valid image']));
}

// Разрешенные MIME типы (реальные, не из заголовков)
$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
];

$actualMimeType = $imageInfo['mime'];
if (!in_array($actualMimeType, $allowedMimeTypes, true)) {
    die(json_encode(['success' => false, 'message' => 'Invalid image type. Allowed: JPEG, PNG, GIF, WebP']));
}

// Проверка расширения файла
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($ext, $allowedExtensions, true)) {
    die(json_encode(['success' => false, 'message' => 'Invalid file extension']));
}

// Дополнительная проверка: расширение должно соответствовать MIME типу
$extensionMimeMap = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
];

if (isset($extensionMimeMap[$ext]) && $extensionMimeMap[$ext] !== $actualMimeType) {
    die(json_encode(['success' => false, 'message' => 'File extension does not match image type']));
}

$assetsPath = MODX_ASSETS_PATH . 'components/testsystem/images/';
$assetsUrl = MODX_ASSETS_URL . 'components/testsystem/images/';

if (!is_dir($assetsPath)) {
    mkdir($assetsPath, 0755, true);
}

// Уникальное имя файла (используем проверенное расширение)
$filename = uniqid('q_') . '_' . time() . '.' . $ext;
$filepath = $assetsPath . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    die(json_encode(['success' => false, 'message' => 'Upload failed']));
}

// Ресайз через GD
try {
    $maxSize = 600; // Изменено с 800 на 600
    $width = $imageInfo[0];
    $height = $imageInfo[1];

    // Определяем длинную сторону
    $isLandscape = $width > $height;

    if (($isLandscape && $width > $maxSize) || (!$isLandscape && $height > $maxSize)) {
        if ($isLandscape) {
            $ratio = $maxSize / $width;
            $newWidth = $maxSize;
            $newHeight = (int)($height * $ratio);
        } else {
            $ratio = $maxSize / $height;
            $newHeight = $maxSize;
            $newWidth = (int)($width * $ratio);
        }

        $image = null;
        switch ($actualMimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = imagecreatefromjpeg($filepath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($filepath);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($filepath);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($filepath);
                break;
        }
        
        if ($image) {
            $newImage = imagecreatetruecolor($newWidth, $newHeight);

            if ($actualMimeType === 'image/png' || $actualMimeType === 'image/gif') {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
            }

            imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            switch ($actualMimeType) {
                case 'image/jpeg':
                case 'image/jpg':
                    imagejpeg($newImage, $filepath, 85);
                    break;
                case 'image/png':
                    imagepng($newImage, $filepath, 8);
                    break;
                case 'image/gif':
                    imagegif($newImage, $filepath);
                    break;
                case 'image/webp':
                    imagewebp($newImage, $filepath, 85);
                    break;
            }
            
            imagedestroy($image);
            imagedestroy($newImage);
        }
    }
} catch (Exception $e) {
    // Если ресайз не удался, оставляем оригинал
}

echo json_encode([
    'success' => true,
    'url' => $assetsUrl . $filename
]);