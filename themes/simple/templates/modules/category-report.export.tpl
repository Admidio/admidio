<table id="adm_lists_table" class="{$classTable}" {foreach $attributes as $attribute} {$attribute@key}="{$attribute}" {/foreach}>
    <thead>
        <tr style="text-align: center">
            <th colspan="{count($headers)}">{$subHeadline}</th>
        </tr>
        <tr style="{$headersStyle}">
            {foreach $headers as $key => $header}
                <th style="text-align:{$columnAlign[$key]};">{$header}</th>
            {/foreach}
        </tr>
    </thead>
    <tbody>
        {foreach $rows as $row}
            <tr id="{$row.id}" style="{$rowsStyle}">
                {foreach $row.data as $key => $cell}
                    <td style="text-align:{$columnAlign[$key]};">{$cell}</td>
                {/foreach}
            </tr>
        {/foreach}
    </tbody>
</table>