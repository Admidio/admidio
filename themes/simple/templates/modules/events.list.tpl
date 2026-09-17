<div class="table-responsive">
    <table id="adm_events_table" class="{$classTable}" style="max-width: 100%;">
        <thead>
            <tr>
                {foreach $headers as $key => $header}
                    <th{if isset($columnClass[$key]) && $columnClass[$key] !== ''} class="{$columnClass[$key]}"{/if} style="text-align:{$columnAlign[$key]};{if $columnWidth[$key] !== ''} width:{$columnWidth[$key]};{/if}">{$header}</th>
                {/foreach}
            </tr>
        </thead>
        <tbody>
        {foreach $rows as $row}
            <tr id="{$row.id}" class="{$row.class}">
            {foreach $row.data as $key => $cell}
                <td{if isset($columnClass[$key]) && $columnClass[$key] !== ''} class="{$columnClass[$key]}"{/if} style="text-align:{$columnAlign[$key]};{if $columnWidth[$key] !== ''} width:{$columnWidth[$key]};{/if}">{$cell}</td>
            {/foreach}
            </tr>
        {/foreach}
        </tbody>
    </table>
</div>