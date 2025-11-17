<!DOCTYPE html>
<html lang="ru">
[[$tsHead]]

<body>
    [[$tsHeader]]

    <main class="py-4">
        <div class="container">
            <!-- Хлебные крошки -->
            [[pdoCrumbs?
                &showHome=`1`
                &showCurrent=`1`
                &outputSeparator=` / `
            ]]

            <!-- Основной контент -->
            {$_modx->resource.content}
        </div>
    </main>

    [[$tsFooter]]
    [[$tsScripts]]
</body>
</html>
