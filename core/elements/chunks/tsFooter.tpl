{* Чанк: tsFooter - Подвал страницы для системы тестирования
   Вызывается из: TestSystem.tpl, learning-materials-template*.html
   Содержит: краткое описание, быстрые и служебные ссылки, копирайт
*}
<footer class="ts-footer-simple py-4">
    <div class="container">
        <div class="row gy-3">
            <div class="col-lg-4">
                <h5 class="mb-2">{$_modx->config.site_name}</h5>
                <p class="mb-0">Платформа для тестирования, обучения и оценки прогресса в едином рабочем пространстве.</p>
            </div>

            <div class="col-sm-6 col-lg-4">
                <h6 class="mb-2">Быстрые ссылки</h6>
                <ul class="list-unstyled mb-0 d-grid gap-1">
                    <li><a href="{$_modx->makeUrl(35)}">Тесты</a></li>
                    <li><a href="{$_modx->makeUrl(149)}">Учебные материалы</a></li>
                    <li><a href="{$_modx->makeUrl(159)}">Рейтинг</a></li>
                </ul>
            </div>

            <div class="col-sm-6 col-lg-4">
                <h6 class="mb-2">Служебная информация</h6>
                <ul class="list-unstyled mb-0 d-grid gap-1">
                    <li><a href="{$_modx->makeUrl(181)}">О системе</a></li>
                    <li><a href="{$_modx->makeUrl(182)}">Правила</a></li>
                    <li><a href="{$_modx->makeUrl(183)}">Контакты</a></li>
                    <li><a href="{$_modx->makeUrl(184)}">Помощь</a></li>
                </ul>
            </div>
        </div>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 pt-3 mt-3 border-top">
            <p class="mb-0">&copy; {$_modx->config.site_name} [[!Year]]</p>
            {if $_modx->user.id > 0}
                <a href="{$_modx->makeUrl(28)}">Личный кабинет</a>
            {/if}
        </div>
    </div>
</footer>
