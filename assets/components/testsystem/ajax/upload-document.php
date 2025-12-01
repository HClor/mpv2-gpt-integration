<?php
/**
 * Document Upload Handler for Learning Materials
 * Supports: PDF, Excel (.xlsx, .xls), Word (.docx, .doc)
 */

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

// Проверка авторизации в контексте web
if (!$modx->user->hasSessionContext('web')) {
    die(json_encode(['success' => false, 'message' => 'Not authorized']));
}

// CSRF Protection
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
if (!$csrfToken || !CsrfProtection::validateToken($csrfToken)) {
    die(json_encode(['success' => false, 'message' => 'CSRF token validation failed']));
}

// Проверка прав
$userId = (int)$modx->user->get('id');
$userGroups = $modx->user->getUserGroupNames();
$canUpload = in_array('LMS Experts', $userGroups, true) || in_array('LMS Admins', $userGroups, true);

if (!$canUpload) {
    die(json_encode(['success' => false, 'message' => 'No permission to upload documents']));
}

if (!isset($_FILES['document'])) {
    die(json_encode(['success' => false, 'message' => 'No file uploaded']));
}

$file = $_FILES['document'];

// Максимум 10MB для документов
if ($file['size'] > 10 * 1024 * 1024) {
    die(json_encode(['success' => false, 'message' => 'File too large (max 10MB)']));
}

// Проверка расширения файла
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExtensions = ['pdf', 'xlsx', 'xls', 'docx', 'doc', 'pptx', 'ppt', 'txt', 'csv'];

if (!in_array($ext, $allowedExtensions, true)) {
    die(json_encode([
        'success' => false,
        'message' => 'Invalid file type. Allowed: PDF, Excel, Word, PowerPoint, TXT, CSV'
    ]));
}

// Проверка MIME типа (реальный, не из заголовков)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$actualMimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowedMimeTypes = [
    'application/pdf',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
    'application/vnd.ms-excel', // .xls
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
    'application/msword', // .doc
    'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
    'application/vnd.ms-powerpoint', // .ppt
    'text/plain', // .txt
    'text/csv', // .csv
    'application/csv',
    'application/vnd.ms-office', // старые форматы Office
];

if (!in_array($actualMimeType, $allowedMimeTypes, true)) {
    die(json_encode([
        'success' => false,
        'message' => 'Invalid MIME type: ' . $actualMimeType
    ]));
}

// Проверка на вредоносный контент (простая проверка заголовка файла)
$handle = fopen($file['tmp_name'], 'rb');
$header = fread($handle, 1024);
fclose($handle);

// Проверяем на PHP код в начале файла
if (preg_match('/<\?php|<\?=|<script/i', $header)) {
    die(json_encode([
        'success' => false,
        'message' => 'File contains suspicious content'
    ]));
}

$assetsPath = MODX_ASSETS_PATH . 'components/testsystem/documents/';
$assetsUrl = MODX_ASSETS_URL . 'components/testsystem/documents/';

if (!is_dir($assetsPath)) {
    mkdir($assetsPath, 0755, true);
}

// Уникальное имя файла (сохраняем оригинальное имя для удобства)
$originalName = pathinfo($file['name'], PATHINFO_FILENAME);
$originalName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $originalName); // Безопасное имя
$filename = uniqid('doc_') . '_' . $originalName . '.' . $ext;
$filepath = $assetsPath . $filename;

if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    die(json_encode(['success' => false, 'message' => 'Upload failed']));
}

// Получаем размер файла в читаемом формате
$fileSize = $file['size'];
$fileSizeFormatted = formatFileSize($fileSize);

// Получаем иконку для типа файла
$fileIcon = getFileIcon($ext);

echo json_encode([
    'success' => true,
    'url' => $assetsUrl . $filename,
    'filename' => $file['name'],
    'size' => $fileSize,
    'sizeFormatted' => $fileSizeFormatted,
    'extension' => $ext,
    'icon' => $fileIcon
]);

// Вспомогательные функции
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

function getFileIcon($ext) {
    $icons = [
        'pdf' => 'bi-file-earmark-pdf',
        'doc' => 'bi-file-earmark-word',
        'docx' => 'bi-file-earmark-word',
        'xls' => 'bi-file-earmark-excel',
        'xlsx' => 'bi-file-earmark-excel',
        'ppt' => 'bi-file-earmark-ppt',
        'pptx' => 'bi-file-earmark-ppt',
        'txt' => 'bi-file-earmark-text',
        'csv' => 'bi-file-earmark-spreadsheet',
    ];

    return $icons[$ext] ?? 'bi-file-earmark';
}
