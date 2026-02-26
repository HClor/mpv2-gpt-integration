<?php
/**
 * Сниппет: myCertificates - Мои сертификаты
 * Вызывается из: MODX ресурсов (страница сертификатов)
 * Назначение: Отображает список сертификатов пользователя
 *
 * @package TestSystem
 */

if (!$modx instanceof modX) {
    return '';
}

$userId = $modx->user->id;

if (!$userId) {
    $authUrl = $modx->makeUrl(24, '', '', 'abs');
    return '<div class="ts-alert ts-alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Войдите, чтобы видеть свои сертификаты.
        <br><a class="ts-btn ts-btn-primary mt-2" href="' . htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8') . '">Войти</a>
    </div>';
}

$prefix = $modx->getOption('table_prefix');
$h = function($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

// Получаем сертификаты пользователя
$sql = "
    SELECT
        c.id,
        c.certificate_number,
        c.issued_at,
        c.expires_at,
        c.status,
        c.score,
        ct.name as template_name,
        ct.description as template_description,
        lp.name as path_name,
        lp.id as path_id
    FROM `{$prefix}test_certificates` c
    LEFT JOIN `{$prefix}test_certificate_templates` ct ON ct.id = c.template_id
    LEFT JOIN `{$prefix}test_learning_paths` lp ON lp.id = c.path_id
    WHERE c.user_id = ?
    ORDER BY c.issued_at DESC
";

$stmt = $modx->prepare($sql);
if ($stmt === false) {
    return '<div class="ts-alert ts-alert-danger">Ошибка при получении сертификатов</div>';
}
$stmt->execute([$userId]);
$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Также получаем сертификаты из прогресса траекторий
$sqlPathCerts = "
    SELECT
        lpp.id as progress_id,
        lpp.certificate_issued_at as issued_at,
        lp.name as path_name,
        lp.id as path_id,
        lpp.completion_pct as score,
        'active' as status
    FROM `{$prefix}test_learning_path_progress` lpp
    JOIN `{$prefix}test_learning_paths` lp ON lp.id = lpp.path_id
    WHERE lpp.user_id = ? AND lpp.certificate_issued = 1
    ORDER BY lpp.certificate_issued_at DESC
";

$stmtPath = $modx->prepare($sqlPathCerts);
if ($stmtPath) {
    $stmtPath->execute([$userId]);
    $pathCertificates = $stmtPath->fetchAll(PDO::FETCH_ASSOC);
} else {
    $pathCertificates = [];
}

$html = [];
$html[] = '<div class="certificates-container">';

$html[] = '<div class="d-flex justify-content-between align-items-center mb-4">';
$html[] = '<h2><i class="bi bi-award me-2"></i>Мои сертификаты</h2>';
$html[] = '</div>';

// Статистика
$totalCerts = count($certificates) + count($pathCertificates);
$html[] = '<div class="row mb-4">';
$html[] = '<div class="col-md-4">';
$html[] = '<div class="card bg-primary text-white">';
$html[] = '<div class="card-body text-center">';
$html[] = '<h3 class="mb-0">' . $totalCerts . '</h3>';
$html[] = '<p class="mb-0">Всего сертификатов</p>';
$html[] = '</div></div></div>';

$activeCerts = count(array_filter($certificates, fn($c) => $c['status'] === 'active')) + count($pathCertificates);
$html[] = '<div class="col-md-4">';
$html[] = '<div class="card bg-success text-white">';
$html[] = '<div class="card-body text-center">';
$html[] = '<h3 class="mb-0">' . $activeCerts . '</h3>';
$html[] = '<p class="mb-0">Активных</p>';
$html[] = '</div></div></div>';

$expiredCerts = count(array_filter($certificates, fn($c) => $c['status'] === 'expired'));
$html[] = '<div class="col-md-4">';
$html[] = '<div class="card bg-secondary text-white">';
$html[] = '<div class="card-body text-center">';
$html[] = '<h3 class="mb-0">' . $expiredCerts . '</h3>';
$html[] = '<p class="mb-0">Истекших</p>';
$html[] = '</div></div></div>';
$html[] = '</div>';

if (empty($certificates) && empty($pathCertificates)) {
    $pathsUrl = $modx->makeUrl(125, '', '', 'abs');
    $html[] = '
        <div class="ts-alert ts-alert-info text-center py-5">
            <i class="bi bi-award fs-1 d-block mb-3"></i>
            <h5>У вас пока нет сертификатов</h5>
            <p class="mb-3">Завершите траекторию обучения, чтобы получить сертификат</p>
            <a href="' . $h($pathsUrl) . '" class="ts-btn ts-btn-primary">
                <i class="bi bi-mortarboard me-1"></i>Перейти к траекториям
            </a>
        </div>
    ';
} else {
    $html[] = '<div class="row g-4">';

    // Сертификаты из таблицы certificates
    foreach ($certificates as $cert) {
        $statusClass = $cert['status'] === 'active' ? 'success' : ($cert['status'] === 'revoked' ? 'danger' : 'secondary');
        $statusLabel = $cert['status'] === 'active' ? 'Активен' : ($cert['status'] === 'revoked' ? 'Отозван' : 'Истёк');

        $html[] = '
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-' . $statusClass . '">
                <div class="card-header bg-' . $statusClass . ' text-white">
                    <i class="bi bi-award me-2"></i>' . $h($cert['template_name'] ?? 'Сертификат') . '
                </div>
                <div class="card-body">
                    <h6 class="card-title">' . $h($cert['path_name'] ?? 'Общий сертификат') . '</h6>
                    <p class="text-muted small mb-2">
                        Номер: <strong>' . $h($cert['certificate_number']) . '</strong>
                    </p>
                    <p class="text-muted small mb-2">
                        <i class="bi bi-calendar-check me-1"></i>
                        Выдан: ' . date('d.m.Y', strtotime($cert['issued_at'])) . '
                    </p>
                    ' . ($cert['expires_at'] ? '<p class="text-muted small mb-2">
                        <i class="bi bi-calendar-x me-1"></i>
                        Действует до: ' . date('d.m.Y', strtotime($cert['expires_at'])) . '
                    </p>' : '') . '
                    ' . ($cert['score'] ? '<p class="text-muted small mb-0">
                        <i class="bi bi-star me-1"></i>
                        Результат: ' . (int)$cert['score'] . '%
                    </p>' : '') . '
                </div>
                <div class="card-footer bg-white">
                    <span class="badge bg-' . $statusClass . '">' . $statusLabel . '</span>
                </div>
            </div>
        </div>';
    }

    // Сертификаты из прогресса траекторий
    $pathsUrl = $modx->makeUrl(125, '', '', 'abs');
    foreach ($pathCertificates as $cert) {
        $html[] = '
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border-success">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-award me-2"></i>Сертификат траектории
                </div>
                <div class="card-body">
                    <h6 class="card-title">' . $h($cert['path_name']) . '</h6>
                    <p class="text-muted small mb-2">
                        <i class="bi bi-calendar-check me-1"></i>
                        Выдан: ' . ($cert['issued_at'] ? date('d.m.Y', strtotime($cert['issued_at'])) : 'Дата не указана') . '
                    </p>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-star me-1"></i>
                        Завершение: ' . (int)$cert['score'] . '%
                    </p>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                    <span class="ts-badge ts-badge-success">Активен</span>
                    <a href="' . $h($pathsUrl) . '?mode=view&id=' . (int)$cert['path_id'] . '" class="ts-btn ts-btn-sm ts-btn-ghost">
                        <i class="bi bi-eye"></i> Посмотреть
                    </a>
                </div>
            </div>
        </div>';
    }

    $html[] = '</div>';
}

$html[] = '</div>';

return implode('', $html);