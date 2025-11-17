<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container">
        <!-- Логотип -->
        <a class="navbar-brand" href="{$_modx->config.site_start | url}">
            <i class="fas fa-graduation-cap"></i>
            {$_modx->config.site_name}
        </a>

        <!-- Кнопка меню для мобильных -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">
            <!-- Основное меню -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="{$_modx->config.site_start | url}">
                        <i class="fas fa-home"></i> Главная
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{$_modx->makeUrl(999)}">
                        <i class="fas fa-clipboard-list"></i> Тесты
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{$_modx->makeUrl(1000)}">
                        <i class="fas fa-book"></i> Обучение
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="{$_modx->makeUrl(1001)}">
                        <i class="fas fa-trophy"></i> Рейтинг
                    </a>
                </li>
            </ul>

            <!-- Меню пользователя -->
            <ul class="navbar-nav">
                {if $_modx->user.id > 0}
                    <!-- Авторизованный пользователь -->

                    <!-- XP и уровень -->
                    [[!getUserStats?
                        &userId=`{$_modx->user.id}`
                        &tpl=`@INLINE <span class="user-xp me-3">
                            <i class="fas fa-star"></i> Уровень [[+level]] | [[+total_xp]] XP
                        </span>`
                    ]]

                    <!-- Избранное -->
                    <li class="nav-item">
                        <a class="nav-link" href="{$_modx->makeUrl(1002)}">
                            <i class="fas fa-heart"></i> Избранное
                        </a>
                    </li>

                    <!-- Профиль -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userMenu" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> {$_modx->user.username}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="{$_modx->makeUrl(1003)}">
                                    <i class="fas fa-user"></i> Мой профиль
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{$_modx->makeUrl(1004)}">
                                    <i class="fas fa-chart-line"></i> Мои результаты
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{$_modx->makeUrl(1005)}">
                                    <i class="fas fa-certificate"></i> Сертификаты
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="{$_modx->makeUrl($_modx->config.site_start)}?action=logout">
                                    <i class="fas fa-sign-out-alt"></i> Выход
                                </a>
                            </li>
                        </ul>
                    </li>
                {else}
                    <!-- Гость -->
                    <li class="nav-item">
                        <a class="nav-link" href="{$_modx->makeUrl(1006)}">
                            <i class="fas fa-sign-in-alt"></i> Вход
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary ms-2" href="{$_modx->makeUrl(1007)}">
                            <i class="fas fa-user-plus"></i> Регистрация
                        </a>
                    </li>
                {/if}
            </ul>
        </div>
    </div>
</nav>

<!-- Уведомления (если есть) -->
{if $_modx->user.id > 0}
<div class="container mt-3">
    [[!getNotifications?
        &limit=`5`
        &unreadOnly=`1`
        &tpl=`@INLINE
        <div class="alert alert-[[+priority:eq=`high`:then=`warning`:else=`info`]] alert-dismissible fade show">
            <i class="fas fa-bell"></i> <strong>[[+title]]</strong> [[+message]]
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`
    ]]
</div>
{/if}
