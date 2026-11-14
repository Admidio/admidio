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
        <tbody class="admidio-sortable">
            {foreach $categoryNode as $category}
                <tr id="adm_category_{$category.uuid}">
                    <td style="word-break: break-word;"><a href="{$category.urlEdit}">{$category.name}</a></td>
                    <td>
                        {if !$category.system}
                            {include 'sys-template-parts/list.move.tpl' moveActions=[
                                ['direction' => 'UP', 'label' => $l10n->get('SYS_MOVE_UP', [$title]), 'class' => 'admidio-category-move', 'attributes' => ['data-uuid' => "`$category.uuid`", 'data-target' => "adm_category_`$category.uuid`"]],
                                ['direction' => 'DOWN', 'label' => $l10n->get('SYS_MOVE_DOWN', [$title]), 'class' => 'admidio-category-move', 'attributes' => ['data-uuid' => "`$category.uuid`", 'data-target' => "adm_category_`$category.uuid`"]]
                            ]}
                        {/if}
                    </td>
                    <td>
                        {if $category.default}
                            <i class="bi bi-star-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DEFAULT_VAR', [$title])}"></i>
                        {/if}
                    </td>
                    <td style="word-break: break-word;">{$category.visibleForRoles}</td>
                    <td style="word-break: break-word;">{$category.editableForRoles}</td>
                    <td class="text-end">
                        {include 'sys-template-parts/list.functions.tpl' data=$category}
                    </td>
                </tr>
            {/foreach}
        </tbody>
    {/foreach}
</table>
