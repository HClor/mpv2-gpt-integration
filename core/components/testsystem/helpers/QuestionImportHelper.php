<?php
/**
 * Question Import Helper
 * Reusable class for importing questions from CSV/Excel files
 */

namespace MPV2\TestSystem\Helpers;

use Exception;
use PDO;

class QuestionImportHelper
{
    /**
     * Определяет, является ли строка заголовком импорта.
     * Поддерживает варианты с кавычками и без.
     */
    private static function isHeaderRow(array $row): bool
    {
        if (count($row) < 7) {
            return false;
        }

        $normalize = static function ($value): string {
            $value = (string)$value;
            $value = preg_replace('/^\xEF\xBB\xBF/u', '', $value); // UTF-8 BOM
            $value = trim($value);
            $value = trim($value, "\"'`");
            $value = strtolower($value);
            return preg_replace('/\s+/', '', $value);
        };

        $expected = [
            'question_text',
            'answer_type',
            'answer_1',
            'answer_2',
            'answer_3',
            'answer_4',
            'correct_answer',
            'explanation',
        ];

        for ($i = 0; $i < 7; $i++) {
            if ($normalize($row[$i] ?? '') !== $expected[$i]) {
                return false;
            }
        }

        if (isset($row[7]) && trim((string)$row[7]) !== '') {
            return $normalize($row[7]) === $expected[7];
        }

        return true;
    }

    /**
     * Import questions from a file
     *
     * @param string $filePath Full path to the file
     * @param string $fileExtension File extension (csv, xlsx, xls)
     * @param int $testId Test ID to import questions into
     * @param object $modx MODX instance
     * @param string $prefix Database table prefix
     * @param bool $hasPhpSpreadsheet Whether PhpSpreadsheet is available
     * @return array ['success' => bool, 'imported' => int, 'errors' => array, 'success_messages' => array]
     */
    public static function importFromFile($filePath, $fileExtension, $testId, $modx, $prefix, $hasPhpSpreadsheet = false)
    {
        $errors = [];
        $successMessages = [];
        $imported = 0;

        // Validate inputs
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'imported' => 0,
                'errors' => ['Файл не найден: ' . basename($filePath)],
                'success_messages' => []
            ];
        }

        if ($testId <= 0) {
            return [
                'success' => false,
                'imported' => 0,
                'errors' => ['Некорректный ID теста'],
                'success_messages' => []
            ];
        }

        try {
            // Start transaction for atomic operations
            $modx->exec("START TRANSACTION");

            $rows = [];
            $hasHeader = false;

            // ============ CSV PROCESSING ============
            if ($fileExtension === 'csv') {
                if (($handle = fopen($filePath, 'r')) !== false) {
                    $firstLine = fgets($handle);
                    rewind($handle);

                    // Detect encoding
                    $encoding = mb_detect_encoding($firstLine, ['UTF-8', 'Windows-1251', 'ISO-8859-1'], true);

                    $lineNumber = 0;
                    while (($data = fgetcsv($handle, 10000, ',')) !== false) {
                        $lineNumber++;

                        if ($lineNumber === 1) {
                            $hasHeader = self::isHeaderRow($data);
                            if ($hasHeader) {
                                continue;
                            }
                        }

                        // Convert encoding if needed
                        if ($encoding !== 'UTF-8') {
                            $data = array_map(function($item) use ($encoding) {
                                return mb_convert_encoding($item, 'UTF-8', $encoding);
                            }, $data);
                        }

                        $rows[] = $data;
                    }
                    fclose($handle);
                } else {
                    $errors[] = "Не удалось открыть CSV файл";
                    $modx->exec("ROLLBACK");
                    return [
                        'success' => false,
                        'imported' => 0,
                        'errors' => $errors,
                        'success_messages' => []
                    ];
                }
            }
            // ============ EXCEL PROCESSING ============
            elseif (in_array($fileExtension, ['xlsx', 'xls'])) {
                if (!$hasPhpSpreadsheet) {
                    $errors[] = "PhpSpreadsheet не установлен. Используйте CSV или установите библиотеку через: composer install";
                    $modx->exec("ROLLBACK");
                    return [
                        'success' => false,
                        'imported' => 0,
                        'errors' => $errors,
                        'success_messages' => []
                    ];
                }

                try {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $highestRow = $worksheet->getHighestRow();

                    for ($row = 1; $row <= $highestRow; $row++) {
                        $rowData = [];
                        for ($col = 'A'; $col <= 'H'; $col++) {
                            $cellValue = $worksheet->getCell($col . $row)->getValue();
                            $rowData[] = $cellValue !== null ? (string)$cellValue : '';
                        }

                        if ($row === 1) {
                            $hasHeader = self::isHeaderRow($rowData);
                            if ($hasHeader) {
                                continue;
                            }
                        }

                        // Only add non-empty rows
                        if (!empty(trim($rowData[0]))) {
                            $rows[] = $rowData;
                        }
                    }
                } catch (Exception $e) {
                    $errors[] = "Ошибка чтения Excel: " . $e->getMessage();
                    $modx->log(\modX::LOG_LEVEL_ERROR, "[QuestionImportHelper] Excel error: " . $e->getMessage());
                    $modx->exec("ROLLBACK");
                    return [
                        'success' => false,
                        'imported' => 0,
                        'errors' => $errors,
                        'success_messages' => []
                    ];
                }
            } else {
                $errors[] = "Неподдерживаемый формат файла: {$fileExtension}";
                $modx->exec("ROLLBACK");
                return [
                    'success' => false,
                    'imported' => 0,
                    'errors' => $errors,
                    'success_messages' => []
                ];
            }

            // ============ VALIDATE DATA ============
            if (empty($rows)) {
                $errors[] = "Файл пуст или не содержит данных";
                $modx->exec("ROLLBACK");
                return [
                    'success' => false,
                    'imported' => 0,
                    'errors' => $errors,
                    'success_messages' => []
                ];
            }

            // ============ PROCESS ROWS ============
            $lineNumberOffset = $hasHeader ? 2 : 1;
            foreach ($rows as $index => $row) {
                $lineNumber = $index + $lineNumberOffset;

                // Validate row structure
                if (count($row) < 7) {
                    $errors[] = "Строка {$lineNumber}: недостаточно столбцов (минимум 7)";
                    continue;
                }

                // Extract data
                $questionText = trim($row[0] ?? '');
                $questionType = strtolower(trim($row[1] ?? 'single'));
                $answer1 = trim($row[2] ?? '');
                $answer2 = trim($row[3] ?? '');
                $answer3 = trim($row[4] ?? '');
                $answer4 = trim($row[5] ?? '');
                $correctAnswers = trim($row[6] ?? '');
                $explanation = trim($row[7] ?? '');

                // Validate question text
                if (empty($questionText)) {
                    $errors[] = "Строка {$lineNumber}: пустой текст вопроса";
                    continue;
                }

                // Validate question type
                if (!in_array($questionType, ['single', 'multiple'])) {
                    $errors[] = "Строка {$lineNumber}: некорректный тип вопроса '{$questionType}' (используйте 'single' или 'multiple')";
                    continue;
                }

                // Collect non-empty answers
                $answers = array_filter([$answer1, $answer2, $answer3, $answer4], function($a) {
                    return !empty(trim($a));
                });

                if (count($answers) < 2) {
                    $errors[] = "Строка {$lineNumber}: минимум 2 варианта ответа";
                    continue;
                }

                // Validate correct answers
                if (empty($correctAnswers)) {
                    $errors[] = "Строка {$lineNumber}: не указаны правильные ответы";
                    continue;
                }

                $correctIndexes = array_map('trim', explode(',', $correctAnswers));
                $correctIndexes = array_filter($correctIndexes, 'is_numeric');

                if (empty($correctIndexes)) {
                    $errors[] = "Строка {$lineNumber}: некорректный формат правильных ответов (используйте цифры через запятую: 1,2,3)";
                    continue;
                }

                // ============ INSERT QUESTION ============
                $stmt = $modx->prepare("
                    INSERT INTO `{$prefix}test_questions`
                    (test_id, question_text, question_type, explanation, published, created_at)
                    VALUES (?, ?, ?, ?, 1, NOW())
                ");

                if (!$stmt->execute([$testId, $questionText, $questionType, $explanation])) {
                    $errors[] = "Строка {$lineNumber}: ошибка вставки вопроса в БД";
                    continue;
                }

                $questionId = (int)$modx->lastInsertId();

                // ============ INSERT ANSWERS ============
                $answersArray = array_values($answers);
                $allAnswersInserted = true;

                foreach ($answersArray as $idx => $answerText) {
                    $answerNumber = $idx + 1;
                    $isCorrect = in_array((string)$answerNumber, $correctIndexes) ? 1 : 0;

                    $stmt = $modx->prepare("
                        INSERT INTO `{$prefix}test_answers`
                        (question_id, answer_text, is_correct, sort_order)
                        VALUES (?, ?, ?, ?)
                    ");

                    if (!$stmt->execute([$questionId, $answerText, $isCorrect, $answerNumber])) {
                        $allAnswersInserted = false;
                        break;
                    }
                }

                // Rollback question if answers failed
                if (!$allAnswersInserted) {
                    $errors[] = "Строка {$lineNumber}: ошибка вставки ответов";
                    $stmt = $modx->prepare("DELETE FROM `{$prefix}test_questions` WHERE id = ?");
                    $stmt->execute([$questionId]);
                    continue;
                }

                $successMessages[] = "Строка {$lineNumber}: вопрос добавлен успешно";
                $imported++;
            }

            // Commit transaction
            if ($imported > 0) {
                $modx->exec("COMMIT");
            } else {
                $modx->exec("ROLLBACK");
            }

        } catch (Exception $e) {
            $modx->exec("ROLLBACK");
            $errors[] = "Критическая ошибка: " . $e->getMessage();
            $modx->log(\modX::LOG_LEVEL_ERROR, "[QuestionImportHelper] Exception: " . $e->getMessage());

            return [
                'success' => false,
                'imported' => 0,
                'errors' => $errors,
                'success_messages' => $successMessages
            ];
        }

        // Return results
        return [
            'success' => ($imported > 0 && empty($errors)),
            'imported' => $imported,
            'errors' => $errors,
            'success_messages' => $successMessages
        ];
    }

    /**
     * Validate file before import
     *
     * @param array $file $_FILES array element
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateUploadedFile($file)
    {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [
                'valid' => false,
                'error' => "Ошибка загрузки файла (код: {$file['error']})"
            ];
        }

        // Check file size (max 10MB)
        $maxFileSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxFileSize) {
            return [
                'valid' => false,
                'error' => "Файл слишком большой. Максимальный размер: 10MB"
            ];
        }

        // Check extension
        $fileName = $file['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['csv', 'xlsx', 'xls'];

        if (!in_array($fileExtension, $allowedExtensions)) {
            return [
                'valid' => false,
                'error' => "Недопустимый формат файла. Разрешены: CSV, XLSX, XLS"
            ];
        }

        // Check MIME type
        $filePath = $file['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        $allowedMimeTypes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        if (!in_array($mimeType, $allowedMimeTypes)) {
            return [
                'valid' => false,
                'error' => "Недопустимый тип файла. Обнаружен: {$mimeType}"
            ];
        }

        return [
            'valid' => true,
            'error' => null
        ];
    }

    /**
     * Check if PhpSpreadsheet is available
     *
     * @return bool
     */
    public static function hasPhpSpreadsheet()
    {
        // First check if class already exists (autoload might be included by MODX)
        if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory', true)) {
            return true;
        }

        // Try to load vendor/autoload.php from various locations
        $vendorPaths = [
            MODX_BASE_PATH . 'vendor/autoload.php',
            MODX_CORE_PATH . '../vendor/autoload.php',
            MODX_CORE_PATH . '../../vendor/autoload.php',
            dirname(dirname(dirname(dirname(__DIR__)))) . '/vendor/autoload.php',
            '/home/l/lmixru/mpv2.lmix.ru/public_html/vendor/autoload.php'
        ];

        foreach ($vendorPaths as $vendorPath) {
            if (file_exists($vendorPath)) {
                require_once $vendorPath;
                // Check again after requiring autoload
                if (class_exists('\PhpOffice\PhpSpreadsheet\IOFactory', false)) {
                    return true;
                }
            }
        }

        return false;
    }
}
