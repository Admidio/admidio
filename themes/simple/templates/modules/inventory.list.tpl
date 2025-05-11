{* {if strlen($infoAlert) > 0}
    <div class="alert alert-info" role="alert"><i class="bi bi-info-circle-fill"></i>{$infoAlert}</div>
{/if} *}

<div class="table-responsive">
    <table id="adm_inventory_table" class="table table-condensed{if $print} table-striped{else} table-hover{/if}" style="max-width: 100%;">
        <thead>
            <tr>
                {foreach from=$list.headers key=colIndex item=header}
                    <th class="text-{$list.column_align[$colIndex]}">{$header}</th>
                {/foreach}
            </tr>
        </thead>
        <tbody>
            {foreach from=$list.rows item=row}
                <tr id="adm_inventory_item_{$row.item_uuid}">
                    {foreach from=$row.data item=cell name=table}
                        <td class="text-{$list.column_align[$smarty.foreach.table.index]}">{$cell|raw}</td>
                    {/foreach}
                    {if isset($row.actions)}
                        <td class="text-end">
                            {foreach $row.actions as $actionItem}
                                    <a
                                        {if isset($actionItem.popup)}
                                            class="admidio-icon-link openPopup" href="javascript:void(0);" data-href="{$actionItem.dataHref}"
                                        {elseif isset($actionItem.dataHref)}
                                            class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no" data-message="{$actionItem.dataMessage}" data-href="{$actionItem.dataHref}"
                                        {else}
                                            class="admidio-icon-link" href="{$actionItem.url}"
                                        {/if}
                                        ><i class="{$actionItem.icon}" data-bs-toggle="tooltip" title="{$actionItem.tooltip}"></i>
                                    </a>
                            {/foreach}
                        </td>
                    {/if}
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
