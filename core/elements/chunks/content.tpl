{var $default = true}
{switch $_modx->resource.id}
    {case 1}
        {var $default = false}
        {include 'content_main'}
    {case 4}
        {var $default = false}
        {include 'content_spec_list'}
{/switch}
{switch $_modx->resource.parent}
    {case 4}
        {var $default = false}
        {include 'content_spec'}
{/switch}
{if $default}
    {include 'content_default'}
{/if}
