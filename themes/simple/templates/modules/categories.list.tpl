<div class="table-responsive">
    <table id="adm_table_categories" class="table table-hover" width="100%" style="width: 100%;">
        <thead>
            <tr>
                <th>{$l10n->get('SYS_TITLE')}</th>
                <th>&nbsp;</th>
                <th><i class="bi bi-star-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DEFAULT_VAR', [$title])}"></i></th>
                <th>{$l10n->get('SYS_VISIBLE_FOR')}</th>
                <th>{$columnTitleEditable}</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        {foreach $list as $categoryNode}
            <tbody>
                {foreach $categoryNode as $category}
                    <tr id="adm_category_{$category.uuid}">
                        <td><a href="{$category.urlEdit}">{$category.name}</a></td>
                        <td>
                            {if !$category.system}
                                <a class="admidio-icon-link admidio-category-move" href="javascript:void(0)" data-uuid="{$category.uuid}"
                                   data-direction="UP" data-target="adm_category_{$category.uuid}">
                                    <i class="bi bi-arrow-up-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_UP', [$title])}"></i></a>
                                <a class="admidio-icon-link admidio-category-move" href="javascript:void(0)" data-uuid="{$category.uuid}"
                                   data-direction="DOWN" data-target="adm_category_{$category.uuid}">
                                    <i class="bi bi-arrow-down-circle-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MOVE_DOWN', [$title])}"></i></a>
                            {/if}
                        </td>
                        <td>
                            {if $category.default}
                                <i class="bi bi-star-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DEFAULT_VAR', [$title])}"></i>
                            {/if}
                        </td>
                        <td>{$category.visibleForRoles}</td>
                        <td>{$category.editableForRoles}</td>
                        <td class="text-end">
                            {foreach $category.actions as $actionItem}
                                <a {if isset($actionItem.dataHref)} class="admidio-icon-link admidio-messagebox" href="javascript:void(0);"
                                    data-buttons="yes-no" data-message="{$actionItem.dataMessage}" data-href="{$actionItem.dataHref}"
                                        {else} class="admidio-icon-link" href="{$actionItem.url}"{/if}>
                                    <i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i></a>
                            {/foreach}
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        {/foreach}
    </table>
</div>
