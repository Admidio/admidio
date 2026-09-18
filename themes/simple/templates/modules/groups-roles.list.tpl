{if isset($exportMode)}
    <h2 style="font-size:12pt;font-weight:bold;text-align:center;margin-top:0;margin-bottom:15px;">{$subHeadline}</h2>
{/if}
<table id="adm_lists_table" class="{$classTable}" {foreach $attributes as $attribute} {$attribute@key}="{$attribute}" {/foreach} {if !isset($exportMode)}style="max-width: 100%;"{/if}>
    <thead>
        <tr {if isset($exportMode)}style="{$headersStyle}"{/if}>
            {foreach $headers as $key => $header}
                <th style="{if isset($exportMode)}padding-left:3px;padding-right:3px;{/if}text-align:{$columnAlign[$key]};">{$header}</th>
            {/foreach}
        </tr>
    </thead>
    <tbody>
    {if count($rows) eq 0}
        <tr>
            <td colspan="{count($headers)}" style="{if isset($exportMode)}padding-left:3px;padding-right:3px;{/if}text-align: center;">{$l10n->get('SYS_NO_MATCHING_ENTRIES')}</td>
        </tr>
    {else}
    {foreach $rows as $row}
        <tr id="{$row.id}" {if isset($row.class)}class="{$row.class}"{/if} {if isset($exportMode)}style="{$rowsStyle}"{/if}>
        {foreach $row.data as $key => $cell}
            {if $cell|is_array && $cell['order']|isset}
                <td {if isset($row.colspan)}colspan="{$row.colspan}"{/if} data-order="{$cell['order']}" style="{if isset($exportMode)}padding-left:3px;padding-right:3px;{/if}text-align:{$columnAlign[$key]};">{$cell['value']}</td>
            {else}
                <td {if isset($row.colspan)}colspan="{$row.colspan}"{/if} style="{if isset($exportMode)}padding-left:3px;padding-right:3px;{/if}text-align:{$columnAlign[$key]};">{$cell}</td>
            {/if}
        {/foreach}
        </tr>
    {/foreach}
    {/if}
    </tbody>
</table>
