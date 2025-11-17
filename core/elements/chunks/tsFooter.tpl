<footer>
    <div class="container">
        <div class="row">
            <!-- Информация о сайте -->
            <div class="col-md-4 mb-3">
                <h5>
                    <i class="fas fa-graduation-cap"></i>
                    {$_modx->config.site_name}
                </h5>
                <p class="text-muted">
                    Современная система тестирования и обучения с геймификацией, аналитикой и сертификацией.
                </p>
                <div class="mt-3">
                    <a href="#" class="text-white me-3"><i class="fab fa-vk fa-lg"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-telegram fa-lg"></i></a>
                    <a href="#" class="text-white me-3"><i class="fab fa-youtube fa-lg"></i></a>
                </div>
            </div>

            <!-- Быстрые ссылки -->
            <div class="col-md-4 mb-3">
                <h5>Быстрые ссылки</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <a href="{$_modx->makeUrl(999)}">
                            <i class="fas fa-clipboard-list"></i> Все тесты
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{$_modx->makeUrl(1000)}">
                            <i class="fas fa-book"></i> Учебные материалы
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{$_modx->makeUrl(1001)}">
                            <i class="fas fa-trophy"></i> Таблица лидеров
                        </a>
                    </li>
                    {if $_modx->user.id > 0}
                    <li class="mb-2">
                        <a href="{$_modx->makeUrl(1003)}">
                            <i class="fas fa-user"></i> Мой профиль
                        </a>
                    </li>
                    <li class="mb-2">
                        <a href="{$_modx->makeUrl(1002)}">
                            <i class="fas fa-heart"></i> Избранное
                        </a>
                    </li>
                    {/if}
                </ul>
            </div>

            <!-- Статистика системы -->
            <div class="col-md-4 mb-3">
                <h5>Статистика</h5>
                [[!getSystemStats?
                    &tpl=`@INLINE
                    <div class="mb-2">
                        <i class="fas fa-users text-primary"></i>
                        <strong>[[+total_users]]</strong> пользователей
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-clipboard-list text-success"></i>
                        <strong>[[+total_tests]]</strong> тестов
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-check-circle text-info"></i>
                        <strong>[[+total_sessions]]</strong> прохождений
                    </div>
                    <div class="mb-2">
                        <i class="fas fa-star text-warning"></i>
                        Средний балл: <strong>[[+avg_score]]%</strong>
                    </div>`
                ]]
            </div>
        </div>

        <hr class="border-secondary my-4">

        <div class="row">
            <div class="col-md-6">
                <p class="mb-0">
                    &copy; {$_modx->config.site_name} [[!Year]]
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="{$_modx->makeUrl(1008)}">О системе</a>
                <span class="mx-2">|</span>
                <a href="{$_modx->makeUrl(1009)}">Правила</a>
                <span class="mx-2">|</span>
                <a href="{$_modx->makeUrl(1010)}">Контакты</a>
                <span class="mx-2">|</span>
                <a href="{$_modx->makeUrl(1011)}">Помощь</a>
            </div>
        </div>
    </div>
</footer>
