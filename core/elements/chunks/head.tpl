<title>{$_modx->resource.longtitle ?: $_modx->resource.pagetitle}</title>
<base href="{$_modx->config.site_url}">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

{if $_modx->resource.searchable}
  <meta name="robots" content="index, follow">
{else}
  <meta name="robots" content="noindex, nofollow">
{/if}
{* Meta description *}
{if $_modx->resource.description}
  <meta name="description" content="{$_modx->resource.description | strip_tags | escape}">
{else}
  {var $short_content = $_modx->runSnippet('cleanDescription', ['input' => $_modx->resource.content])}
  {if $short_content && ($short_content | strlen) > 10}
    <meta name="description" content="{$short_content | ellipsis : "200" | escape}">
  {/if}
{/if}
{* Keywords *}
{if $_modx->resource.keywords}
  <meta name="keywords" content="{$_modx->resource.keywords | strip_tags}" />
{/if}
{* Open Graph *}
<meta property="og:title" content="{$_modx->resource.pagetitle}">
<meta property="og:type" content="article">
<meta property="og:url" content="{$_modx->config.site_url}{$_modx->resource.uri}">
{if $_modx->resource.img}
  <meta property="og:image" content="{$_modx->config.site_url | rtrim : '/'}{$_modx->resource.img | phpthumbon : 'w=800&h=420&zc=1'}" />
{/if}
<meta property="og:site_name" content="{$_modx->config.site_name}">

<link rel="icon" href="/assets/components/siteextra/web/img/favicon.ico" type="image/x-icon">

{* Bootstrap 5 + Icons *}
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">




{* MinifyX подключает кастомные стили и скрипты *}
{'!MinifyX' | snippet : [
  'minifyCss' => 1,
  'minifyJs' => 1,
  'cssSources' => '/assets/components/siteextra/web/css/style.css',
  'jsSources'  => '/assets/components/siteextra/web/js/script.js'
]}
{'MinifyX.css' | placeholder}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
