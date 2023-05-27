{* Create the sidebar menu out of the navigation menu array *}
<div class="admidio-headline-mobile-menu d-md-none p-2">
    <span class="text-uppercase">{$l10n->get('SYS_MENU')}</span>
    <button class="btn btn-link d-md-none collapsed float-right" type="button" data-toggle="collapse"
            data-target="#admidio-main-menu" aria-controls="admidio-main-menu" aria-expanded="false">
        <i class="fas fa-bars fa-fw"></i>
    </button>
</div>
<nav class="admidio-menu-list collapse" id="admidio-main-menu">
    {foreach $menuNavigation as $menuGroup}
        <div class="admidio-menu-header">{$menuGroup.name}</div>
        <ul class="nav admidio-menu-node flex-column mb-0">
            {foreach $menuGroup.items as $menuItem}
                <li class="nav-item">
                    <a id="{$menuItem.id}" class="nav-link" href="{$menuItem.url}">
                        <i class="{$menuItem.icon} fa-fw"></i>{$menuItem.name}
                        {if $menuItem.badgeCount > 0}
                            <span class="badge badge-light">{$menuItem.badgeCount}</span>
                        {/if}
                    </a>
                </li>
            {/foreach}
        </ul>
    {/foreach}
</nav>
