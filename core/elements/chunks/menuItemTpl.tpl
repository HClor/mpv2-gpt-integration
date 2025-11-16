{* Menu Item Template - Пункт меню *}
<li class="nav-item">
    <a class="nav-link{if $id == $currentId} active{/if}" href="{$id | url}">
        {$menutitle ?: $pagetitle}
    </a>
</li>
