<table id="adm_inventory_reservations_table" class="table table-condensed table-hover w-100">
    <thead>
        <tr>
            {foreach $reservationList.headers as $headerIndex => $header}
                <th{if $headerIndex === 6} class="d-none"{/if}>{$header}</th>
            {/foreach}
        </tr>
    </thead>
    <tbody>
        {foreach $reservationList.rows as $row}
            <tr id="{$row.id}" data-reservation-status="{$row.status}" data-begin-iso="{$row.beginIso}" data-end-iso="{$row.endIso}">
                <td>{$row.itemName|escape:'htmlall':'UTF-8'}</td>
                <td>
                    {$row.requester|escape:'htmlall':'UTF-8'}
                    {if $row.email}
                        <br><small>{$row.email|escape:'htmlall':'UTF-8'}</small>
                    {/if}

                </td>
                <td data-order="{$row.timestampIso}">{$row.timestamp|escape:'htmlall':'UTF-8'}</td>
                <td data-order="{$row.beginDateTimeIso}">{$row.begin|escape:'htmlall':'UTF-8'}</td>
                <td data-order="{$row.endDateTimeIso}">{$row.end|escape:'htmlall':'UTF-8'}</td>
                <td>
                    {if $row.originUrl}
                        <a href="{$row.originUrl|escape:'htmlall':'UTF-8'}">{$row.origin|escape:'htmlall':'UTF-8'}</a>
                    {else}
                        {$row.origin|escape:'htmlall':'UTF-8'}
                    {/if}
                </td>
                <td class="d-none"></td>
                <td class="text-end">
                    <span class="me-2"><i class="bi {$row.statusIcon}"></i>{$row.statusLabel}</span>
                    {assign var=reservationActions value=[]}
                    {foreach $row.actions as $action}
                        {$reservationActions[] = ['icon' => "bi {$action.icon}", 'tooltip' => $action.label, 'class' => 'admidio-inventory-reservation-update', 'attributes' => ['data-url' => $action.url, 'data-row-id' => $row.id]]}
                    {/foreach}
                    {if $row.mailUrl}
                        {$reservationActions[] = ['url' => $row.mailUrl, 'icon' => 'bi bi-envelope', 'tooltip' => $l10n->get('SYS_SEND_EMAIL')]}
                    {/if}
                    {if $row.comment}
                        {$reservationActions[] = ['class' => 'admidio-inventory-reservation-comment', 'attributes' => ['data-comment' => $row.comment], 'icon' => 'bi bi-chat-left-text-fill', 'tooltip' => $l10n->get('SYS_COMMENT')]}
                    {/if}
                    {include 'sys-template-parts/list.functions.tpl' data=['uuid' => $row.id, 'actions' => $reservationActions]}
                </td>
            </tr>
        {/foreach}
    </tbody>
</table>
<div id="adm_inventory_reservations_alert" class="alert alert-danger form-alert mt-3" style="display: none;"></div>
