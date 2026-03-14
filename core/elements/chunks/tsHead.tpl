{* Чанк: tsHead - Заголовок HTML для системы тестирования
   Вызывается из: TestSystem.tpl, learning-materials-template*.html
   Содержит: meta-теги, CSS (Bootstrap 5, Font Awesome, кастомные стили), CSRF токен
*}
<head>
    <base href="{$_modx->config.site_url}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{$_modx->resource.description | escape}">
    <meta name="keywords" content="{$_modx->resource.keywords | escape}">

    <title>{$_modx->resource.pagetitle ?: $_modx->resource.longtitle} - {$_modx->config.site_name}</title>

    <link rel="icon" type="image/png" href="/assets/components/template/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/assets/components/template/favicon/favicon.svg" />
    <link rel="shortcut icon" href="/assets/components/template/favicon/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/components/template/favicon/apple-touch-icon.png" />
    <link rel="manifest" href="/assets/components/template/favicon/site.webmanifest" />

    <!-- Design Tokens (CSS Custom Properties) — MUST be first -->
    <link rel="stylesheet" href="/assets/components/testsystem/css/ts-variables.css">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons (единственная библиотека иконок) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Global layout: body, navbar, footer, breadcrumb, card, alert, user-xp -->
    <link rel="stylesheet" href="/assets/components/testsystem/css/ts-layout.css">

    <!-- Component library: ts-btn, ts-card, ts-badge, ts-field, ts-alert, ts-meta, ts-dropdown -->
    <link rel="stylesheet" href="/assets/components/testsystem/css/ts-components.css">

    <!-- Test System CSS -->
    <link rel="stylesheet" href="/assets/components/testsystem/css/tsrunner.css">

    <!-- Расширенные стили для спринтов 9-17 -->
    <link href="/assets/components/testsystem/css/testsystem-extended.css" rel="stylesheet">

    <!-- CSRF токен для безопасности -->
    {$_modx->runSnippet('csrfToken')}
</head>
