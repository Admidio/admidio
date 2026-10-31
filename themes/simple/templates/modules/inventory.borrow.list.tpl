{if $canManageReservations}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_RESERVATION_BORROWINGS_READY')}</div>
        <div class="card-body">
            <table id="adm_inventory_borrow_ready_table" class="table table-condensed table-hover w-100">
                <thead>
                    <tr>
                        <th>{$l10n->get('SYS_INVENTORY_ITEMNAME')}</th>
                        <th>{$l10n->get('SYS_PERIOD')}</th>
                        <th>{$l10n->get('SYS_EVENT')}</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    {foreach $readyReservations as $reservation}
                        <tr>
                            <td>{$reservation.item_name|escape:'htmlall':'UTF-8'}</td>
                            <td data-order="{$reservation.ivr_begin_iso|escape:'htmlall':'UTF-8'}">
                                {$reservation.ivr_begin|escape:'htmlall':'UTF-8'} - {$reservation.ivr_end|escape:'htmlall':'UTF-8'}
                            </td>
                            <td>{$reservation.dat_headline|default:''|escape:'htmlall':'UTF-8'}</td>
                            <td class="text-end text-nowrap">
                                {if $reservation.item_is_borrowed}
                                    {assign var=borrowAction value=['icon' => 'bi bi-box-arrow-up-right', 'tooltip' => $l10n->get('SYS_ITEM_ALREADY_BORROWED'), 'dataMessage' => $l10n->get('SYS_ITEM_ALREADY_BORROWED'), 'attributes' => ['data-title' => $l10n->get('SYS_WARNING'), 'data-type' => 'warning']]}
                                {else}
                                    {assign var=borrowAction value=['url' => $reservation.borrowUrl, 'icon' => 'bi bi-box-arrow-up-right', 'tooltip' => $l10n->get('SYS_INVENTORY_ITEM_BORROW')]}
                                {/if}
                                {include 'sys-template-parts/list.functions.tpl' data=['uuid' => $reservation.ivr_uuid, 'actions' => [$borrowAction]]}
                            </td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        </div>
    </div>
{/if}
<div class="card admidio-field-group">
    <div class="card-header">{$l10n->get('SYS_BORROWINGS_ACTIVE')}</div>
    <div class="card-body">
        <table id="adm_inventory_borrow_active_table" class="table table-condensed table-hover w-100">
            <thead>
                <tr>
                    <th>{$l10n->get('SYS_INVENTORY_ITEMNAME')}</th>
                    <th>{$l10n->get('SYS_INVENTORY_LAST_RECEIVER')}</th>
                    <th>{$l10n->get('SYS_INVENTORY_BORROW_DATE')}</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                {foreach $activeBorrowings as $borrowing}
                    <tr>
                        <td>{$borrowing.item_name|escape:'htmlall':'UTF-8'}</td>
                        <td>{$borrowing.receiver|escape:'htmlall':'UTF-8'}</td>
                        <td data-order="{$borrowing.inb_borrow_date_iso|escape:'htmlall':'UTF-8'}">{$borrowing.inb_borrow_date|escape:'htmlall':'UTF-8'}</td>
                        <td class="text-end text-nowrap">
                            {include 'sys-template-parts/list.functions.tpl' data=['uuid' => $borrowing.ini_uuid, 'actions' => [['url' => $borrowing.returnUrl, 'icon' => 'bi bi-box-arrow-in-down-left', 'tooltip' => $l10n->get('SYS_INVENTORY_ITEM_RETURN')]]]}
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
    </div>
</div>
