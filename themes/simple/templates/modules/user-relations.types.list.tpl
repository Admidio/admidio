<div class="table-responsive">
    <table id="adm_relationtypes_table" class="table table-hover" style="max-width: 100%;">
        <thead>
            <tr>
                {foreach $headers as $key => $header}
                    <th style="text-align:{$columnAlign[$key]};">{$header}</th>
                {/foreach}
            </tr>
        </thead>
        <tbody>
        {foreach $rows as $row}
            <tr id="{$row.id}">
                {foreach $row.data as $key => $cell}
                    <td style="text-align:{$columnAlign[$key]};">{$cell}</td>
                {/foreach}
            </tr>
        {/foreach}
        </tbody>
    </table>
</div>
