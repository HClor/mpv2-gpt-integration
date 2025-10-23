<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <base href="{$modx->config.site_url}">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$_modx->resource.pagetitle} - {$_modx->config.site_name}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        main {
            flex: 1;
        }
        .navbar-brand {
            font-weight: bold;
        }
        footer {
            background: #f8f9fa;
            padding: 2rem 0;
            margin-top: 3rem;
        }
    </style>
    {$_modx->resource.cssTV}
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom">
        <div class="container">
            <a class="navbar-brand" href="{$_modx->config.site_start | url}">{$_modx->config.site_name}</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    {'pdoMenu' | snippet : [
                        'startId' => 0,
                        'level' => 1,
                        'includeDocs' => $_modx->config.site_start ~ ',35,34',
                        'tplOuter' => '@INLINE {$wrapper}',
                        'tpl' => '@INLINE <li class="nav-item"><a class="nav-link" href="{$link}">{$menutitle}</a></li>',
                        'tplHere' => '@INLINE <li class="nav-item"><a class="nav-link active" href="{$link}">{$menutitle}</a></li>'
                    ]}
                </ul>
                <ul class="navbar-nav">
                    {'!userMenu' | snippet}
                </ul>
            </div>
        </div>
    </nav>
    <main class="py-4">
        <div class="container">
            {$_modx->resource.content}
        </div>
    </main>
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-0">&copy; {$_modx->config.site_name} {'!Year' | snippet}</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="{35 | url}">Тесты</a> | 
                    <a href="{34 | url}">Рейтинг</a>
                    {if $_modx->user.id == 0}
                        | <a href="{24 | url}">Вход</a>
                    {/if}
                </div>
            </div>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    {$_modx->resource.jsTV}
</body>
</html>