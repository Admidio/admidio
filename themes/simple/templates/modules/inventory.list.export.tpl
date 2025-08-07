<table class="table" {foreach $attributes as $attribute} {$attribute@key}="{$attribute}" {/foreach}>
    <thead>
        <tr style="{$headersStyle}">
            {foreach $headers as $key => $header}
                <th style="text-align:{if $column_align[$key] eq 'start'}left{elseif $column_align[$key] eq 'end'}right{else}center{/if};">{$header}</th>
            {/foreach}
        </tr>
    </thead>
    <tbody>
        {foreach $rows as $row}
            <tr style="{$rowsStyle}">
                {foreach $row.data as $key => $cell}
                    <td style="text-align:{if $column_align[$key] eq 'start'}left{elseif $column_align[$key] eq 'end'}right{else}center{/if};">{$cell}</td>
                {/foreach}
            </tr>
        {/foreach}
    </tbody>
</table>