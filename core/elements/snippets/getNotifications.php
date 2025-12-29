<?php
/**
 * Сниппет: getNotifications - Уведомления пользователя
 * Вызывается из: tsHeader.tpl
 * Назначение: Получает и отображает уведомления пользователя (алерты)
 *
 * @var modX $modx
 * @var array $scriptProperties
 *
 * @param int $userId - ID пользователя (по умолчанию текущий)
 * @param int $limit - Лимит уведомлений (по умолчанию 5)
 * @param bool $unreadOnly - Только непрочитанные (по умолчанию 1)
 * @param string $type - Тип уведомлений (test_completed, achievement_earned и т.д.)
 * @param string $priority - Приоритет (low, normal, high, urgent)
 * @param string $tpl - Шаблон для вывода (по умолчанию tplNotification chunk)
 */

// Параметры
$userId = (int)$modx->getOption('userId', $scriptProperties, $modx->user->get('id'));
$limit = (int)$modx->getOption('limit', $scriptProperties, 5);
$unreadOnly = (bool)$modx->getOption('unreadOnly', $scriptProperties, true);
$type = $modx->getOption('type', $scriptProperties, '');
$priority = $modx->getOption('priority', $scriptProperties, '');
$tpl = $modx->getOption('tpl', $scriptProperties, 'tplNotification');

// Если пользователь не авторизован
if (!$userId) {
    return '';
}

// Формируем SQL запрос
$prefix = $modx->getOption('table_prefix');
$tableNotifications = $prefix . 'test_notifications';

$where = ["user_id = ?"];
$params = [$userId];

// Фильтр по прочитанности
if ($unreadOnly) {
    $where[] = "is_read = 0";
}

// Фильтр по типу
if ($type) {
    $where[] = "notification_type = ?";
    $params[] = $type;
}

// Фильтр по приоритету
if ($priority) {
    $where[] = "priority = ?";
    $params[] = $priority;
}

// Фильтр по истечению срока
$where[] = "(expires_at IS NULL OR expires_at > NOW())";

$whereStr = implode(' AND ', $where);

$sql = "
    SELECT
        id,
        notification_type,
        title,
        message,
        action_url,
        icon,
        priority,
        is_read,
        read_at,
        related_type,
        related_id,
        metadata,
        created_at
    FROM `{$tableNotifications}`
    WHERE {$whereStr}
    ORDER BY created_at DESC
    LIMIT {$limit}
";

$stmt = $modx->prepare($sql);
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Если нет уведомлений
if (empty($notifications)) {
    return '';
}

// Формируем вывод
$output = [];

foreach ($notifications as $notification) {
    // Подготавливаем данные для плейсхолдеров
    $placeholders = [
        'id' => $notification['id'],
        'type' => $notification['notification_type'],
        'title' => htmlspecialchars($notification['title'], ENT_QUOTES, 'UTF-8'),
        'message' => htmlspecialchars($notification['message'], ENT_QUOTES, 'UTF-8'),
        'action_url' => htmlspecialchars($notification['action_url'] ?? '', ENT_QUOTES, 'UTF-8'),
        'icon' => $notification['icon'] ?? 'fa-bell',
        'priority' => $notification['priority'],
        'is_read' => $notification['is_read'],
        'read_at' => $notification['read_at'] ?? '',
        'related_type' => $notification['related_type'] ?? '',
        'related_id' => $notification['related_id'] ?? '',
        'created_at' => $notification['created_at'],
        'created_at_ago' => '',
    ];

    // Форматируем "время назад"
    if ($notification['created_at']) {
        $time = strtotime($notification['created_at']);
        $diff = time() - $time;

        if ($diff < 60) {
            $placeholders['created_at_ago'] = 'только что';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            $placeholders['created_at_ago'] = $minutes . ' ' . plural($minutes, 'минуту', 'минуты', 'минут') . ' назад';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            $placeholders['created_at_ago'] = $hours . ' ' . plural($hours, 'час', 'часа', 'часов') . ' назад';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            $placeholders['created_at_ago'] = $days . ' ' . plural($days, 'день', 'дня', 'дней') . ' назад';
        } else {
            $placeholders['created_at_ago'] = date('d.m.Y H:i', $time);
        }
    }

    // Разбираем metadata если есть
    if ($notification['metadata']) {
        $metadata = json_decode($notification['metadata'], true);
        if (is_array($metadata)) {
            foreach ($metadata as $key => $value) {
                $placeholders['meta_' . $key] = $value;
            }
        }
    }

    // Применяем шаблон
    if (strpos($tpl, '@INLINE') === 0) {
        // Inline шаблон
        $template = str_replace('@INLINE', '', $tpl);
        $rendered = $modx->getChunk('', $placeholders, $template);
    } else {
        // Chunk
        $rendered = $modx->getChunk($tpl, $placeholders);
    }

    $output[] = $rendered;
}

/**
 * Функция склонения слов
 */
function plural($number, $one, $two, $five) {
    $number = abs($number) % 100;
    $n1 = $number % 10;

    if ($number > 10 && $number < 20) {
        return $five;
    }
    if ($n1 > 1 && $n1 < 5) {
        return $two;
    }
    if ($n1 == 1) {
        return $one;
    }
    return $five;
}

return implode('', $output);