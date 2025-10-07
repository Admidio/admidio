<table id="adm_lists_table" class="{$classTable}" {foreach $attributes as $attribute} {$attribute@key}="{$attribute}" {/foreach} {if !isset($exportMode)}style="max-width: 100%;"{/if}>
    <thead>
    {if isset($exportMode)}
        <tr style="text-align: center">
            <th colspan="{count($headers)}">{$subHeadline}</th>
        </tr>
    {/if}
        <tr {if isset($exportMode)}style="{$headersStyle}"{/if}>
            {foreach $headers as $key => $header}
                <th style="text-align:{$columnAlign[$key]};">{$header}</th>
            {/foreach}
        </tr>
    </thead>
    <tbody>
    {if count($rows) eq 0}
        <tr>
            <td colspan="{count($headers)}" style="text-align: center;">{$l10n->get('SYS_NO_MATCHING_ENTRIES')}</td>
        </tr>
    {else}
    {foreach $rows as $row}
        <tr id="{$row.id}" {if isset($exportMode)}style="{$rowsStyle}"{/if}>
        {foreach $row.data as $key => $cell}
            <td style="text-align:{$columnAlign[$key]};"{if isset($cell.order)} data-order="{$cell.order}"{/if}>{if isset($cell.value)} {$cell.value} {else} {$cell} {/if}</td>
        {/foreach}
        </tr>
    {/foreach}
    {/if}
    </tbody>
</table>