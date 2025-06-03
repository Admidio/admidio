{* function to render tables *}
{function name="render_table" headers="" rows="" align="" tableId=""}
    <div class="table-responsive">
        <table id="{$tableId}" class="table table-condensed table-hover">
            <thead>
                <tr>
                    {foreach from=$headers key=colIndex item=header}
                        <th class="text-{$align[$colIndex]}">{$header}</th>
                    {/foreach}
                </tr>
            </thead>
            <tbody>
                {foreach from=$rows item=row}
                    <tr id="adm_inventory_item_{$row.item_uuid}">
                        {foreach from=$row.data item=cell name=table}
                            <td class="text-{$align[$smarty.foreach.table.index]}">{$cell|raw}</td>
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
{/function}

<!-- determinate view class -->
{if $viewType eq "tab"}
    {$viewClass = "admidio-tabbed-field-group"}
{elseif $viewType eq "accordion"}
    {$viewClass = "admidio-accordion-field-group"}
{/if}

<!-- Inventory Keeper Cards -->
{if isset($keeperList)}
    <div class="card {$viewClass}">
        <div class="card-header">
            {$keeperListHeader}
            {if isset($urlInventoryKeeper)}
                <a class="admidio-icon-link float-end" href="{$urlInventoryKeeper}">
                    <i class="bi bi-box-seam-fill" title="{$keeperListHeader}"></i>
                </a>
            {/if}
        </div>
        <div class="card-body">
            <p>{$l10n->get('SYS_INVENTORY_PROFILE_VIEW_KEEPER_DESC')}</p>
            {render_table headers=$keeperList.headers rows=$keeperList.rows align=$keeperList.column_align tableId="adm_inventory_table_keeper_{$viewType}"}
        </div>
    </div>
{/if}
<!-- Inventory Receiver Cards -->
{if isset($receiverList)}
    <div class="card {$viewClass}">
        <div class="card-header">
            {$receiverListHeader}
            {if isset($urlInventoryReceiver)}
                <a class="admidio-icon-link float-end" href="{$urlInventoryReceiver}">
                    <i class="bi bi-box-seam-fill" title="{$receiverListHeader}"></i>
                </a>
            {/if}
        </div>
        <div class="card-body">
            <p>{$l10n->get('SYS_INVENTORY_PROFILE_VIEW_LAST_RECEIVER_DESC')}</p>
            {render_table headers=$receiverList.headers rows=$receiverList.rows align=$receiverList.column_align tableId="adm_inventory_table_receiver_{$viewType}"}
        </div>
    </div>
{/if}