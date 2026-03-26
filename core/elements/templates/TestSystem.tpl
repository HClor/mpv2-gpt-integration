{* Обработка выхода из системы (некэшируемый вызов для обработки POST) *}
{$_modx->runSnippet('logoutHandler')}
<!DOCTYPE html>
<html lang="ru">
{include 'tsHead'}

<body>
    {include 'tsHeader'}

    <main class="py-4">
        <div class="container">
            <!-- LMS breadcrumbs: отдельный контекстный builder -->
            {$_modx->runSnippet('lmsBreadcrumbs')}

            <!-- Основной контент -->
            {$_modx->resource.content}
        </div>
    </main>

    {include 'tsFooter'}
    {include 'tsScripts'}
</body>
</html>
