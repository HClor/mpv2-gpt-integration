<?php

$vendorPath = __DIR__ . '/vendor/autoload.php';

if (file_exists($vendorPath)) {
    require_once $vendorPath;
    
    if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
        echo "✅ PhpSpreadsheet установлен успешно!\n";
        echo "Версия PHP: " . PHP_VERSION . "\n";
        
        // Тест создания Excel
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', 'Test');
            echo "✅ Можно создавать Excel файлы!\n";
        } catch (Exception $e) {
            echo "❌ Ошибка: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ Класс IOFactory не найден\n";
    }
} else {
    echo "❌ Файл autoload.php не найден: {$vendorPath}\n";
}

