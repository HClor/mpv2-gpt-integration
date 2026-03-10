{* Чанк: content_spec - Контент страницы специалиста для siteExtra
   Вызывается из: content.tpl (для parent = 4)
   Расширяет: content_default
   Содержит: фото, должность специалиста
*}
{extends 'content_default'}
{block 'before_content'}
  {if $_modx->resource.img}
    <img src="{$_modx->resource.img | phpthumbon : "w=262&h=300&zc=1"}" alt="{$_modx->resource.pagetitle | escape}" class="float-start me-3 mb-4">
  {/if}
  {if $_modx->resource.subtitle}
    <h2>{$_modx->resource.subtitle}</h2>
  {/if}
{/block}