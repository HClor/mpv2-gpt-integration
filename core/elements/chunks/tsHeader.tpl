<!-- Screenshot reference: /mnt/data/dd4c610d-0c94-4afe-87f2-1ae6c0587a1a.png -->
<!-- Fixed Bootstrap 5 navbar for MODX Revo (variant 2). Improvements:
     - Valid UL/LI structure
     - Grouped user controls into proper nav items
     - Kept getUserStats snippet inline-friendly
     - Removed inline positioning from dropdown-menu
     - Added responsive helpers and aria attributes
-->
<!--<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm py-2">-->
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
      <!-- Основное меню -->
      {$_modx->runSnippet('pdoMenu', [
        'parents' => $_modx->config.site_start,
        'level' => 1,
        'showHidden' => 0,
        'sortby' => 'menuindex',
        'sortdir' => 'ASC',
        'tplOuter' => '@INLINE <ul class="navbar-nav me-auto mb-2 mb-lg-0">[[+wrapper]]</ul>',
        'tpl' => '@INLINE <li class="nav-item [[+classnames]]"><a class="nav-link [[+classnames]]" href="[[+link]]">[[+menutitle]]</a>[[+wrapper]]</li>',
        'tplHere' => '@INLINE <li class="nav-item [[+classnames]]"><a class="nav-link active [[+classnames]]" href="[[+link]]">[[+menutitle]]</a>[[+wrapper]]</li>'
      ])}

      <!-- Меню пользователя -->
      <ul class="navbar-nav align-items-center">
        {if $_modx->user.id}

          {set $isAdmin = $_modx->runSnippet('checkUserGroup', ['group' => 'LMS Admins'])}
          {set $isExpert = $_modx->runSnippet('checkUserGroup', ['group' => 'LMS Experts'])}
          {set $isAdminOrExpert = $isAdmin || $isExpert}

          <!-- Служебное меню (только для админов и экспертов) -->
          {if $isAdminOrExpert}
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="adminMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-tools me-2"></i> Управление
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminMenu">
              {$_modx->runSnippet('pdoMenu', [
                'parents' => 191,
                'level' => 1,
                'showHidden' => 1,
                'sortby' => 'menuindex',
                'sortdir' => 'ASC',
                'tplOuter' => '@INLINE [[+wrapper]]',
                'tpl' => '@INLINE <li><a class="dropdown-item" href="[[+link]]"><i class="fas fa-cog me-2"></i>[[+menutitle]]</a></li>',
                'tplHere' => '@INLINE <li><a class="dropdown-item active" href="[[+link]]"><i class="fas fa-cog me-2"></i>[[+menutitle]]</a></li>'
              ])}
            </ul>
          </li>
          {/if}

          <!-- Профиль -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-user-circle me-2"></i> {$_modx->user.username}
            </a>

            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
              <li><a class="dropdown-item" href="{$_modx->makeUrl(28)}"><i class="fas fa-user me-2"></i> Мой профиль</a></li>
              <li><a class="dropdown-item" href="{$_modx->makeUrl(156)}"><i class="fas fa-chart-line me-2"></i> Мои результаты</a></li>
              <li><a class="dropdown-item" href="{$_modx->makeUrl(180)}"><i class="fas fa-certificate me-2"></i> Сертификаты</a></li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form method="post" action="">
                  {$_modx->runSnippet('csrfToken')}
                  <input type="hidden" name="login_logout" value="1">
                  <button type="submit" class="dropdown-item">
                    <i class="fas fa-sign-out-alt me-2"></i> Выход
                  </button>
                </form>
              </li>
            </ul>
          </li>

        {else}
          <!-- Гость: единая кнопка вход/регистрация (видна как кнопка на всех размерах) -->
          <li class="nav-item">
            <a class="btn btn-primary ms-2" href="{$_modx->makeUrl(24)}">
              <i class="fas fa-sign-in-alt me-1"></i> Вход / Регистрация
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
