<table class="table" style="width:100%;table-layout:fixed;" {foreach $attributes as $attribute} {$attribute@key}="{$attribute}" {/foreach}>
    <colgroup>
        {foreach $column_widths as $width}
            <col style="width:{$width}%;" />
        {/foreach}
    </colgroup>
    <thead>
        <tr style="{$headersStyle}">
            {foreach $headers as $key => $header}
                <th style="padding-left:3px;padding-right:3px;word-break:break-word;overflow-wrap:break-word;text-align:{if $column_align[$key] eq 'start'}left{elseif $column_align[$key] eq 'end'}right{else}center{/if};">{$header}</th>
            {/foreach}
        </tr>
    </thead>
    <tbody>
        {foreach $rows as $row}
            <tr style="{$rowsStyle}">
                {foreach $row.data as $key => $cell}
                    <td style="padding-left:3px;padding-right:3px;word-break:break-word;overflow-wrap:break-word;text-align:{if $column_align[$key] eq 'start'}left{elseif $column_align[$key] eq 'end'}right{else}center{/if};">{$cell}</td>
                {/foreach}
            </tr>
        {/foreach}
    </tbody>
</table>
