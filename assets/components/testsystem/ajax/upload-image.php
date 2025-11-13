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

$modx = new modX();
$modx->initialize('web');

header('Content-Type: application/json; charset=utf-8');

// Проверка авторизации
if (!$modx->user->hasSessionContext('web')) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
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
$allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

if (!in_array($file['type'], $allowed)) {
    die(json_encode(['success' => false, 'message' => 'Invalid file type']));
}

// Максимум 5MB
if ($file['size'] > 5 * 1024 * 1024) {
    die(json_encode(['success' => false, 'message' => 'File too large (max 5MB)']));
}

$assetsPath = MODX_ASSETS_PATH . 'components/testsystem/images/';
$assetsUrl = MODX_ASSETS_URL . 'components/testsystem/images/';

if (!is_dir($assetsPath)) {
    mkdir($assetsPath, 0755, true);
}

// Уникальное имя файла
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('q_') . '_' . time() . '.' . $ext;
$filepath = $assetsPath . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    die(json_encode(['success' => false, 'message' => 'Upload failed']));
}

// Ресайз через GD
try {
    $maxSize = 600; // Изменено с 800 на 600
    list($width, $height) = getimagesize($filepath);
    
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
        switch ($file['type']) {
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
            
            if ($file['type'] === 'image/png' || $file['type'] === 'image/gif') {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
            }
            
            imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            
            switch ($file['type']) {
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