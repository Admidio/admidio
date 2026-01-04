<table id="adm_role_assignment_table" class="table table-hover" style="max-width: 100%;">
    <thead>
        <tr>
            {foreach $headers as $key => $header}
                <th style="text-align:{$columnAlign[$key]};{if $columnWidth[$key] !== ''} width:{$columnWidth[$key]};{/if}">{$header}</th>
            {/foreach}
        </tr>
    </thead>
    {foreach $groups as $key => $group}
        <tbody>
            <tr id="{$group.id}" class="{$group.class}">
                <td colspan="{$group.colspan}">{$group.data}</td>
            </tr>
        </tbody>
        <tbody id="{$key}">
        {foreach $group.rows as $row}
            <tr id="{$row.id}">
            {foreach $row.data as $key => $cell}
                <td style="text-align:{$columnAlign[$key]};{if $columnWidth[$key] !== ''} width:{$columnWidth[$key]};{/if}">{$cell}</td>
            {/foreach}
            </tr>
        {/foreach}
        </tbody>
    {/foreach}
</table>