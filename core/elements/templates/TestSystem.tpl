{* Обработка выхода из системы (некэшируемый вызов для обработки POST) *}
[[!logoutHandler]]
<!DOCTYPE html>
<html lang="ru">
{include 'tsHead'}

<body>
    {include 'tsHeader'}

    <main class="py-4">
        <div class="container">
            <!-- Хлебные крошки -->
            {$_modx->runSnippet('pdoCrumbs', [
                'showHome' => 1,
                'showCurrent' => 1,
                'tplWrapper' => '@INLINE <nav aria-label="breadcrumb"><ol class="breadcrumb">[[+output]]</ol></nav>'
            ])}

            <!-- Основной контент -->
            {$_modx->resource.content | raw}
        </div>
    </main>

    {include 'tsFooter'}
    {include 'tsScripts'}
</body>
</html>
