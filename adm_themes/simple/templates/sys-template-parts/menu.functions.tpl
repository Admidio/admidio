{* Create the functions menu out of the menu array *}
<ul class="nav admidio-menu-function-node">
    {foreach $menuFunctions as $menuItem}
        {if array_key_exists('items', $menuItem)}
            <li class="nav-item dropdown">
                <a id="{$menuItem.id}" class="nav-link btn btn-secondary dropdown-toggle" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
                    <i class="{$menuItem.icon} fa-fw"></i>{$menuItem.name}
                </a>
                <div class="dropdown-menu dropdown-menu-left">
                    {foreach $menuItem.items as $subItem}
                        <a id="{$subItem.id}" class="dropdown-item" href="{$subItem.url}">
                            <i class="{$subItem.icon} fa-fw"></i>{$subItem.name}
                        </a>
                    {/foreach}
                </div>
            </li>
        {else}
            <li class="nav-item">
                <a id="{$menuItem.id}" class="nav-link btn btn-secondary" href="{$menuItem.url}">
                    <i class="{$menuItem.icon} fa-fw"></i>{$menuItem.name}
                    {if $menuItem.badgeCount > 0}
                        <span class="badge bg-light text-dark">{$menuItem.badgeCount}</span>
                    {/if}
                </a>
            </li>
        {/if}
    {/foreach}
</ul>
