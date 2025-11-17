<head>
    <base href="{$_modx->config.site_url}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="{$_modx->resource.description}">
    <meta name="keywords" content="{$_modx->resource.keywords}">

    <title>{$_modx->resource.pagetitle ?: $_modx->resource.longtitle} - {$_modx->config.site_name}</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome для иконок -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Test System CSS -->
    <link rel="stylesheet" href="/assets/components/testsystem/css/tsrunner.css">

    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #f8f9fa;
        }

        main {
            flex: 1;
        }

        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }

        .navbar-brand i {
            color: #0d6efd;
        }

        footer {
            background: #343a40;
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
        }

        footer a {
            color: #adb5bd;
            text-decoration: none;
        }

        footer a:hover {
            color: white;
        }

        .breadcrumb {
            background: white;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }

        /* Стили для карточек */
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }

        /* Стили для уведомлений -->
        .alert {
            border-radius: 0.5rem;
        }

        /* XP и уровень */
        .user-xp {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: bold;
        }
    </style>

    <!-- Дополнительный CSS из TV поля -->
    {$_modx->resource.cssTV}
</head>
