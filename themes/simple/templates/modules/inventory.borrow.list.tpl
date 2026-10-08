{if $canManageReservations}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_INVENTORY_BORROWINGS_READY')}</div>
        <div class="card-body">
            <table id="adm_inventory_borrow_ready_table" class="table table-condensed table-hover" style="width: 100%;">
                <thead><tr><th>{$l10n->get('SYS_INVENTORY_ITEMNAME')}</th><th>{$l10n->get('SYS_PERIOD')}</th><th>{$l10n->get('SYS_EVENT')}</th><th class="text-end"></th></tr></thead>
                <tbody>{foreach $readyReservations as $reservation}<tr><td>{$reservation.item_name|escape:'htmlall':'UTF-8'}</td><td>{$reservation.ivr_begin|escape:'htmlall':'UTF-8'} - {$reservation.ivr_end|escape:'htmlall':'UTF-8'}</td><td>{$reservation.dat_headline|default:''|escape:'htmlall':'UTF-8'}</td><td class="text-end text-nowrap"><a class="btn btn-primary" href="{$reservation.borrowUrl|escape:'htmlall':'UTF-8'}"><i class="bi bi-box-arrow-up-right"></i> {$l10n->get('SYS_INVENTORY_START_BORROWING')}</a></td></tr>{/foreach}</tbody>
            </table>
        </div>
    </div>
{/if}
<div class="card admidio-field-group">
    <div class="card-header">{$l10n->get('SYS_INVENTORY_BORROWINGS_ACTIVE')}</div>
    <div class="card-body">
        <table id="adm_inventory_borrow_active_table" class="table table-condensed table-hover" style="width: 100%;">
            <thead><tr><th>{$l10n->get('SYS_INVENTORY_ITEMNAME')}</th><th>{$l10n->get('SYS_INVENTORY_LAST_RECEIVER')}</th><th>{$l10n->get('SYS_INVENTORY_BORROW_DATE')}</th><th class="text-end"></th></tr></thead>
            <tbody>{foreach $activeBorrowings as $borrowing}<tr><td>{$borrowing.item_name|escape:'htmlall':'UTF-8'}</td><td>{$borrowing.receiver|escape:'htmlall':'UTF-8'}</td><td>{$borrowing.inb_borrow_date|escape:'htmlall':'UTF-8'}</td><td class="text-end text-nowrap"><a class="btn btn-primary" href="{$borrowing.returnUrl|escape:'htmlall':'UTF-8'}"><i class="bi bi-box-arrow-in-down-left"></i> {$l10n->get('SYS_INVENTORY_RECORD_RETURN')}</a></td></tr>{/foreach}</tbody>
        </table>
    </div>
</div>
