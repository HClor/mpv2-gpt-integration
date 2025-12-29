{* Чанк: tsHeader - Навигационная панель для системы тестирования
   Вызывается из: TestSystem.tpl, learning-materials-template*.html
   Содержит: логотип, основное меню, меню пользователя (вход/профиль), уведомления
   Использует сниппеты: getUserRights, csrfToken, getNotifications

   Структура меню:
   - Основное: Учебные материалы, Тесты, Траектории, Лидеры, Области знаний
   - Пользователь: Профиль, Мои тесты, Мои траектории, Избранное, История, Сертификаты
   - Админ: Управление (dropdown)
*}
<nav class="navbar navbar-expand-lg shadow-sm py-2">
  <div class="container">
    <!-- Логотип -->
    <a class="navbar-brand" href="{$_modx->config.site_start | url}">
      <i class="fas fa-graduation-cap"></i>
      {$_modx->config.site_name}
    </a>

    <!-- Кнопка меню для мобильных -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarMain">
      <!-- Основное меню (явные пункты с иконками) -->
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link{if $_modx->resource.id == 149} active{/if}" href="{$_modx->makeUrl(149)}">
            <i class="fas fa-book me-1"></i> Учебные материалы
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link{if $_modx->resource.id == 35} active{/if}" href="{$_modx->makeUrl(35)}">
            <i class="fas fa-tasks me-1"></i> Тесты
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link{if $_modx->resource.id == 193 || $_modx->resource.parent == 193} active{/if}" href="{$_modx->makeUrl(193)}">
            <i class="fas fa-route me-1"></i> Траектории
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link{if $_modx->resource.id == 159} active{/if}" href="{$_modx->makeUrl(159)}">
            <i class="fas fa-trophy me-1"></i> Лидеры
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link{if $_modx->resource.id == 185} active{/if}" href="{$_modx->makeUrl(185)}">
            <i class="fas fa-brain me-1"></i> Области знаний
          </a>
        </li>
      </ul>

      <!-- Правая часть меню -->
      <ul class="navbar-nav align-items-center">
        {if $_modx->user.id}
          {set $rights = $_modx->runSnippet('getUserRights')}
          {set $isAdminOrExpert = $rights.isAdmin || $rights.isExpert}

          <!-- Служебное меню (только для админов и экспертов) -->
          {if $isAdminOrExpert}
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="adminMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-cogs me-1"></i> Управление
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminMenu">
              {$_modx->runSnippet('pdoMenu', [
                'parents' => 191,
                'level' => 1,
                'showHidden' => 1,
                'sortby' => 'menuindex',
                'sortdir' => 'ASC',
                'tplOuter' => '@INLINE [[+wrapper]]',
                'tpl' => '@INLINE <li><a class="dropdown-item" href="[[+link]]"><i class="fas fa-wrench me-2"></i>[[+menutitle]]</a></li>',
                'tplHere' => '@INLINE <li><a class="dropdown-item active" href="[[+link]]"><i class="fas fa-wrench me-2"></i>[[+menutitle]]</a></li>'
              ])}
              <li><hr class="dropdown-divider"></li>
              <li class="dropdown-header"><i class="fas fa-chart-bar me-2"></i>Статистика</li>
              <li><a class="dropdown-item{if $_modx->resource.id == 195} active{/if}" href="{$_modx->makeUrl(195)}"><i class="fas fa-route me-2"></i>Статистика траекторий</a></li>
              <li><a class="dropdown-item{if $_modx->resource.id == 196} active{/if}" href="{$_modx->makeUrl(196)}"><i class="fas fa-users me-2"></i>Статистика пользователей</a></li>
            </ul>
          </li>
          {/if}

          <!-- Меню пользователя -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-user-circle me-1"></i> {$_modx->user.username}
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
              <li><a class="dropdown-item" href="{$_modx->makeUrl(28)}"><i class="fas fa-user me-2"></i> Мой профиль</a></li>
              <li><a class="dropdown-item" href="{$_modx->makeUrl(186)}"><i class="fas fa-edit me-2"></i> Мои тесты</a></li>
              <li><a class="dropdown-item" href="{$_modx->makeUrl(194)}"><i class="fas fa-map-signs me-2"></i> Мои траектории</a></li>
              <li><a class="dropdown-item" href="{$_modx->makeUrl(169)}"><i class="fas fa-star me-2"></i> Избранное</a></li>
              <li><a class="dropdown-item" href="{$_modx->makeUrl(157)}"><i class="fas fa-history me-2"></i> История тестов</a></li>
              <li><a class="dropdown-item" href="{$_modx->makeUrl(180)}"><i class="fas fa-certificate me-2"></i> Сертификаты</a></li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form method="post" action="">
                  {$_modx->runSnippet('csrfToken')}
                  <input type="hidden" name="login_logout" value="1">
                  <button type="submit" class="dropdown-item text-danger">
                    <i class="fas fa-sign-out-alt me-2"></i> Выход
                  </button>
                </form>
              </li>
            </ul>
          </li>

        {else}
          <!-- Гость: кнопка входа -->
          <li class="nav-item">
            <a class="btn btn-primary ms-2" href="{$_modx->makeUrl(24)}">
              <i class="fas fa-sign-in-alt me-1"></i> Вход
            </a>
          </li>
        {/if}
      </ul>
    </div>
  </div>
</nav>

<!-- Уведомления (если есть) -->
{if $_modx->user.id}
<div class="container mt-3">
  {$_modx->runSnippet('getNotifications', [
    'limit' => 5,
    'unreadOnly' => 1,
    'tpl' => '@INLINE <div class="alert alert-[[+priority:eq=`high`:then=`warning`:else=`info`]] alert-dismissible fade show"><i class="fas fa-bell me-2"></i> <strong>[[+title]]</strong> [[+message]]<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>'
  ])}
</div>
{/if}
