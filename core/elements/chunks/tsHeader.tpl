{* Чанк: tsHeader - Навигационная панель для системы тестирования
   Вызывается из: TestSystem.tpl
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
      <i class="bi bi-mortarboard-fill"></i>
      {$_modx->config.site_name}
    </a>

    <!-- Кнопка меню для мобильных -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarMain">
      <!-- Основное меню: названия из menutitle ресурса, иконки из поля description -->
      <!-- Порядок задаётся через menuindex ресурсов в MODX Admin -->
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        {$_modx->runSnippet('pdoMenu', [
          'resources' => '149,35,193,159,185',
          'parents' => '-1',
          'level' => 1,
          'showHidden' => 1,
          'sortby' => 'menuindex',
          'sortdir' => 'ASC',
          'tplOuter' => '@INLINE [[+wrapper]]',
          'tpl' => '@INLINE <li class="nav-item"><a class="nav-link" href="[[+link]]"><i class="bi [[+description]] me-1"></i> [[+menutitle]]</a></li>',
          'tplHere' => '@INLINE <li class="nav-item"><a class="nav-link active" href="[[+link]]"><i class="bi [[+description]] me-1"></i> [[+menutitle]]</a></li>',
          'tplParentRow' => '@INLINE <li class="nav-item"><a class="nav-link" href="[[+link]]"><i class="bi [[+description]] me-1"></i> [[+menutitle]]</a></li>',
          'tplParentRowHere' => '@INLINE <li class="nav-item"><a class="nav-link active" href="[[+link]]"><i class="bi [[+description]] me-1"></i> [[+menutitle]]</a></li>'
        ])}
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
              <i class="bi bi-gear me-1"></i> Управление
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="adminMenu">
              {$_modx->runSnippet('pdoMenu', [
                'parents' => 191,
                'level' => 1,
                'showHidden' => 1,
                'sortby' => 'menuindex',
                'sortdir' => 'ASC',
                'tplOuter' => '@INLINE [[+wrapper]]',
                'tpl' => '@INLINE <li><a class="dropdown-item" href="[[+link]]"><i class="bi bi-gear me-2"></i>[[+menutitle]]</a></li>',
                'tplHere' => '@INLINE <li><a class="dropdown-item active" href="[[+link]]"><i class="bi bi-gear me-2"></i>[[+menutitle]]</a></li>'
              ])}
              <li><hr class="dropdown-divider"></li>
              <li class="dropdown-header"><i class="bi bi-graph-up me-2"></i>Статистика</li>
              <li><a class="dropdown-item{if $_modx->resource.id == 201} active{/if}" href="{$_modx->makeUrl(201)}"><i class="bi bi-people me-2"></i>Статистика пользователей</a></li>
              <li><a class="dropdown-item{if $_modx->resource.id == 200} active{/if}" href="{$_modx->makeUrl(200)}"><i class="bi bi-signpost-split me-2"></i>Статистика траекторий обучения</a></li>
            </ul>
          </li>
          {/if}

          <!-- Меню пользователя -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle me-1"></i> {$_modx->user.username | escape}
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
              <li><a class="dropdown-item" href="{$_modx->makeUrl(28)}"><i class="bi bi-person-circle me-2"></i> Мой профиль</a></li>
              <li><a class="dropdown-item" href="{$_modx->makeUrl(186)}"><i class="bi bi-journal-text me-2"></i> Мои тесты</a></li>
              <li><a class="dropdown-item" href="{$_modx->makeUrl(194)}"><i class="bi bi-map me-2"></i> Мои траектории</a></li>
              <li><a class="dropdown-item" href="{$_modx->makeUrl(202)}"><i class="bi bi-award me-2"></i> Мои достижения</a></li>
              <li><a class="dropdown-item" href="{$_modx->makeUrl(169)}"><i class="bi bi-bookmark me-2"></i> Избранное</a></li>
              <li><a class="dropdown-item" href="{$_modx->makeUrl(157)}"><i class="bi bi-clock-history me-2"></i> История тестов</a></li>
              <li><a class="dropdown-item" href="{$_modx->makeUrl(180)}"><i class="bi bi-patch-check me-2"></i> Сертификаты</a></li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form method="post" action="" id="logout-form">
                  {$_modx->runSnippet('csrfTokenField')}
                  <input type="hidden" name="login_logout" value="1">
                  <button type="submit" class="dropdown-item text-danger" id="logout-btn">
                    <i class="bi bi-box-arrow-right me-2"></i> Выход
                  </button>
                </form>
              </li>
            </ul>
          </li>

          <script>
          // Обработчик logout - предотвращаем закрытие dropdown до отправки
          document.getElementById('logout-btn')?.addEventListener('click', function(e) {
              e.preventDefault();
              e.stopPropagation();
              document.getElementById('logout-form').submit();
          });
          </script>

        {else}
          <!-- Гость: кнопка входа -->
          <li class="nav-item">
            <a class="ts-btn ts-btn-primary ms-2" href="{$_modx->makeUrl(24)}">
              <i class="bi bi-box-arrow-in-right me-1"></i> Вход
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
    'tpl' => '@INLINE <div class="alert alert-[[+priority:eq=`high`:then=`warning`:else=`info`]] alert-dismissible fade show"><i class="bi bi-bell me-2"></i> <strong>[[+title]]</strong> [[+message]]<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>'
  ])}
</div>
{/if}
