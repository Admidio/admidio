<div class="table-responsive">
    <table id="adm_oidc_clients_table" class="table table-condensed table-hover" style="max-width: 100%;">
        <thead>
            <tr>
                {foreach $headers as $header}
                    <th>{$header}</th>
                {/foreach}
            </tr>
        </thead>
        <tbody>
            {foreach $rows as $row}
                <tr id="{$row.id}">
                    {foreach $row.data as $key => $cell}
                        <td {if isset($columnAlign)}style="text-align:{$columnAlign[$key]};{/if}">{$cell}</td>
                    {/foreach}
                </tr>
            {/foreach}
        </tbody>
    </table>
</div>
