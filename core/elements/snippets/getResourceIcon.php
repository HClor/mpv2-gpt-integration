<?php
/**
 * getResourceIcon - возвращает иконку FontAwesome для ресурса
 *
 * Параметры:
 * - id: ID ресурса
 * - title: название ресурса (опционально)
 * - default: иконка по умолчанию (default: 'cog')
 *
 * Использование:
 * [[getResourceIcon? &id=`[[+id]]` &title=`[[+pagetitle]]`]]
 */

$resourceId = (int)($scriptProperties['id'] ?? 0);
$resourceTitle = strtolower($scriptProperties['title'] ?? '');
$defaultIcon = $scriptProperties['default'] ?? 'cog';

// Карта иконок по ID ресурса
$iconMapById = [
    168 => 'folder-open',       // Управление категориями
    43  => 'users',              // Пользователи
    38  => 'folder-open',        // Управление категориями (альтернативный ID)
    36  => 'plus-circle',        // Создать тест
    156 => 'chart-line',         // Мои результаты
    180 => 'certificate',        // Сертификаты
    28  => 'user',               // Профиль
    34  => 'trophy',             // Рейтинг/Лидерборд
    24  => 'sign-in-alt',        // Авторизация
    35  => 'list-ul',            // Тесты
];

// Карта иконок по ключевым словам в названии
$iconMapByKeyword = [
    'категор'      => 'folder-open',
    'пользовател'  => 'users',
    'тест'         => 'clipboard-list',
    'результат'    => 'chart-bar',
    'сертификат'   => 'certificate',
    'профил'       => 'user',
    'рейтинг'      => 'trophy',
    'лидер'        => 'trophy',
    'настройк'     => 'cog',
    'статистик'    => 'chart-pie',
    'отчет'        => 'file-alt',
    'импорт'       => 'file-import',
    'экспорт'      => 'file-export',
    'материал'     => 'book',
    'уведомлен'    => 'bell',
    'админ'        => 'user-shield',
    'управлен'     => 'tools',
];

// Сначала проверяем по ID
if (isset($iconMapById[$resourceId])) {
    return $iconMapById[$resourceId];
}

// Затем проверяем по ключевым словам в названии
if (!empty($resourceTitle)) {
    foreach ($iconMapByKeyword as $keyword => $icon) {
        if (strpos($resourceTitle, $keyword) !== false) {
            return $icon;
        }
    }
}

// Возвращаем иконку по умолчанию
return $defaultIcon;
