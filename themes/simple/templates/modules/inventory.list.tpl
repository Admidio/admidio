{* {if strlen($infoAlert) > 0}
    <div class="alert alert-info" role="alert"><i class="bi bi-info-circle-fill"></i>{$infoAlert}</div>
{/if} *}

<div class="table-responsive">
<table id="adm_inventory_table" class="table table-condensed{if $print} table-striped{else} table-hover{/if}">
        <thead>
            <tr>
                {foreach from=$list.headers key=colIndex item=header}
                    <th class="text-{$list.column_align[$colIndex]}">{$header}</th>
                {/foreach}
            </tr>
        </thead>
        <tbody>
            {foreach from=$list.rows item=row}
                <tr>
                    {foreach from=$row key=colIndex item=cell}
                        <td class="text-{$list.column_align[$colIndex]}">{$cell|raw}</td>
                    {/foreach}
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
