{* Create the functions menu out of the menu array *}
{if count($menuFunctions) > 0}
    <ul class="nav admidio-menu-function-node">
        {foreach $menuFunctions as $menuItem}
            {if {array_key_exists array=$menuItem key="items"}}
                <li class="nav-item dropdown">
                    <a id="{$menuItem.id}" class="nav-link btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button" aria-expanded="false">
                        <i class="{$menuItem.icon}"></i>{$menuItem.name}
                    </a>
                    <ul class="dropdown-menu">
                        {foreach $menuItem.items as $subItem}
                            <li>
                                <a id="{$subItem.id}" class="dropdown-item icon-link" href="{$subItem.url}">
                                    <i class="{$subItem.icon}"></i>{$subItem.name}
                                </a>
                            </li>
                        {/foreach}
                    </ul>
                </li>
            {else}
                <li class="nav-item">
                    <a id="{$menuItem.id}" class="nav-link btn btn-primary" href="{$menuItem.url}">
                        <i class="{$menuItem.icon}"></i>{$menuItem.name}
                        {if $menuItem.badgeCount > 0}
                            <span class="badge bg-light text-dark">{$menuItem.badgeCount}</span>
                        {/if}
                    </a>
                </li>
            {/if}
        {/foreach}
    </ul>
{/if}
