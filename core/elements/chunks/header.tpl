{* Чанк: header - Шапка страницы для siteExtra
   Вызывается из: siteExtra.tpl
   Содержит: логотип, адрес, телефон
*}
<header class="page-header row">
  <div class="col-sm-4">
    <h2>
      <a href="{'site_url' | config}" id="logo">
        <nobr><span class="glyphicon glyphicon-send me-2"></span>{'site_name' | config}</nobr>
      </a>
    </h2>
  </div>
  <div class="col-sm-4">
    <p class="mt-4">
      {'address' | config}
    </p>
  </div>
  <div class="col-sm-4">
    <h2 class="text-right">
      <nobr><a href="tel:{'phone' | config | preg_replace : '/[^0-9+]/' : ''}">{'phone' | config}</a></nobr>
    </h2>
  </div>
</header>
