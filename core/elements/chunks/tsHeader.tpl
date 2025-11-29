<!-- Screenshot reference: /mnt/data/dd4c610d-0c94-4afe-87f2-1ae6c0587a1a.png -->
<!-- Fixed Bootstrap 5 navbar for MODX Revo. Improvements:
     - Valid UL/LI structure
     - Statistics grouped into one li with flex + gap
     - Removed inline position from dropdown-menu
     - Added dropdown-menu-end and responsive helpers
-->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm py-2">
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
      [[!pdoMenu?
        &parents=`0`
        &level=`1`
        &resources=`-[[++site_start]]`
        &showHidden=`0`
        &sortby=`menuindex`
        &sortdir=`ASC`
        &tplOuter=`@INLINE <ul class="navbar-nav me-auto mb-2 mb-lg-0">[[+wrapper]]</ul>`
        &tpl=`@INLINE <li class="nav-item [[+classnames]]"><a class="nav-link [[+classnames]]" href="[[+link]]">[[+menutitle]]</a>[[+wrapper]]</li>`
        &tplHere=`@INLINE <li class="nav-item [[+classnames]]"><a class="nav-link active [[+classnames]]" href="[[+link]]">[[+menutitle]]</a>[[+wrapper]]</li>`
      ]]

      <!-- Меню пользователя -->
      <ul class="navbar-nav align-items-center">
        {if $_modx->user.id > 0}

          <!-- Статистика: один валидный li с flex контейнером -->
          <li class="nav-item d-flex align-items-center me-3">
            <div class="d-flex align-items-center gap-3">
              <!-- карточка "Ваше место" (можно заменить на вызов сниппета) -->
              <!-- Если у вас есть сниппет getUserRank, можно использовать его тут -->
              <div class="p-2 bg-light rounded text-center" style="min-width:72px;">
                <div class="h5 fw-bold text-primary mb-0">#1</div>
                <small class="text-muted">Ваше место в рейтинге</small>
              </div>

              <!-- Блок статистики (вставляйте реальные сниппеты/вызовы данных) -->
              <div class="text-light small">
                <div class="d-flex justify-content-between"><span class="me-2"><i class="bi bi-clipboard-check text-primary"></i> Пройдено тестов:</span><strong>4</strong></div>
                <div class="d-flex justify-content-between"><span class="me-2"><i class="bi bi-check-circle-fill text-success"></i> Успешно:</span><strong>0</strong></div>
                <div class="d-flex justify-content-between"><span class="me-2"><i class="bi bi-graph-up text-info"></i> Средний балл:</span><strong>6.5%</strong></div>
                <div class="d-flex justify-content-between mt-2 pt-2 border-top border-secondary"><span class="fw-bold">Всего баллов:</span><span class="text-warning fw-bold fs-5">26</span></div>
              </div>
            </div>
          </li>

          <!-- Избранное -->
          <li class="nav-item">
            <a class="nav-link" href="{$_modx->makeUrl(1002)}">
              <i class="fas fa-heart"></i> Избранное
            </a>
          </li>

          <!-- Профиль -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-user-circle me-2"></i> {$_modx->user.username}
            </a>
            <!-- Убираем inline position: Bootstrap+Popper сам управляет позиционированием -->
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
              <li>
                <a class="dropdown-item" href="{$_modx->makeUrl(28)}">
                  <i class="fas fa-user me-2"></i> Мой профиль
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="{$_modx->makeUrl(158)}">
                  <i class="fas fa-chart-line me-2"></i> Мои результаты
                </a>
              </li>
              <li>
                <a class="dropdown-item" href="{$_modx->makeUrl(1005)}">
                  <i class="fas fa-certificate me-2"></i> Сертификаты
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item" href="{$_modx->makeUrl($_modx->config.site_start)}?action=logout">
                  <i class="fas fa-sign-out-alt me-2"></i> Выход
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
      <i class="fas fa-bell me-2"></i> <strong>[[+title]]</strong> [[+message]]
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`
  ]]
</div>
{/if}
