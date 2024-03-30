{* Create the functions menu out of the menu array *}
{if count($menuFunctions) > 0}
    <ul class="nav admidio-menu-function-node">
        {foreach $menuFunctions as $menuItem}
            {if {array_key_exists array=$menuItem key='items'}}
                <li class="nav-item dropdown">
                    <a id="{$menuItem.id}" class="nav-link btn btn-primary dropdown-toggle" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">
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
                    <a id="{$menuItem.id}" class="nav-link btn btn-primary" href="{$menuItem.url}">
                        <i class="{$menuItem.icon} fa-fw"></i>{$menuItem.name}
                    </a>
                </li>
            {/if}
        {/foreach}
    </ul>
{/if}
