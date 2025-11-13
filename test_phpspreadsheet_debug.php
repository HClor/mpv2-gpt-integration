<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== НАЧАЛО ТЕСТА ===\n";

$vendorPath = __DIR__ . '/vendor/autoload.php';
echo "Путь к autoload: {$vendorPath}\n";

if (file_exists($vendorPath)) {
    echo "✅ Файл autoload.php найден\n";
    
    try {
        require_once $vendorPath;
        echo "✅ Autoload подключен\n";
    } catch (Exception $e) {
        echo "❌ Ошибка при подключении autoload: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
        echo "✅ PhpSpreadsheet установлен успешно!\n";
        echo "Версия PHP: " . PHP_VERSION . "\n";
        
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', 'Test');
            echo "✅ Можно создавать Excel файлы!\n";
        } catch (Exception $e) {
            echo "❌ Ошибка создания Excel: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Класс IOFactory не найден\n";
    }
} else {
    echo "❌ Файл autoload.php не найден: {$vendorPath}\n";
}

echo "=== КОНЕЦ ТЕСТА ===\n";
