<table id="adm_inventory_reservations_table" class="table table-condensed table-hover w-100">
    <thead>
        <tr>
            {foreach $reservationList.headers as $header}
                <th>{$header}</th>
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
                    {if $row.mailUrl}
                        <a class="admidio-icon-link" href="{$row.mailUrl|escape:'htmlall':'UTF-8'}"
                           aria-label="{$l10n->get('SYS_SEND_EMAIL')}">
                            <i class="bi bi-envelope"></i>
                        </a>
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
                <td>
                    {if $row.comment}
                        <button type="button" class="admidio-icon-link border-0 bg-transparent admidio-inventory-reservation-comment"
                                aria-label="{$l10n->get('SYS_COMMENT')}" data-comment="{$row.comment|escape:'htmlall':'UTF-8'}">
                            <i class="bi bi-chat-left-text-fill"></i>
                        </button>
                    {/if}
                </td>
                <td class="text-end">
                    {if $row.actions}
                        <div class="btn-group admidio-inventory-reservation-action">
                            <button type="button" class="btn btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi {$row.statusIcon}"></i>{$row.statusLabel}
                            </button>
                            <ul class="dropdown-menu">
                                {foreach $row.actions as $action}
                                    <li>
                                        <button type="button" class="icon-link dropdown-item" data-url="{$action.url|escape:'htmlall':'UTF-8'}" data-row-id="{$row.id}">
                                            <i class="bi {$action.icon}"></i>{$action.label}
                                        </button>
                                    </li>
                                {/foreach}
                            </ul>
                        </div>
                    {else}
                        <span class="admidio-inventory-reservation-action">
                            <i class="bi {$row.statusIcon}"></i>{$row.statusLabel}
                        </span>
                    {/if}
                </td>
            </tr>
        {/foreach}
    </tbody>
</table>
<div id="adm_inventory_reservations_alert" class="alert alert-danger form-alert mt-3" style="display: none;"></div>
