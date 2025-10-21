{block 'wrapper'}
  <h1 class="mb-4">{$_modx->resource.longtitle ?: $_modx->resource.pagetitle}</h1>
  {block 'before_content'}{/block}
  {block 'content'}
    {var $show_on_page = $_modx->resource.show_on_page}
    {if ($_modx->runSnippet('checkShowOnPage', ['value' => $show_on_page, 'check' => 'content']))}
      {$_modx->resource.content | raw}
    {/if}
    {if ($_modx->runSnippet('checkShowOnPage', ['value' => $show_on_page, 'check' => 'raw_content']))}
      {$_modx->resource.raw_content}
    {/if}
    {if ($_modx->runSnippet('checkShowOnPage', ['value' => $show_on_page, 'check' => 'gallery']))}
      {include 'gallery'}
    {/if}
    {if ($_modx->runSnippet('checkShowOnPage', ['value' => $show_on_page, 'check' => 'children']))}
      {include 'child_list'}
    {/if}
  {/block}
  {block 'after_content'}{/block}
{/block}