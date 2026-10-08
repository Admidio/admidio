<p class="form-text">{$l10n->get('SYS_INVENTORY_EVENT_RESERVATIONS_DESC')}</p>
<table class="table table-hover" style="width: 100%;">
    <thead><tr><th>{$l10n->get('SYS_INVENTORY_ITEMNAME')}</th><th></th></tr></thead>
    <tbody id="event_inventory_reservations_rows">
        {foreach $eventInventoryReservationRows as $row}
            <tr>
                <td><select class="form-select" name="event_inventory_items[]"><option value="">- {$l10n->get('SYS_PLEASE_CHOOSE')} -</option>{foreach $eventInventoryReservationItems as $item}<option value="{$item.id}"{if $item.id === $row.itemId} selected{/if}>{$item.name|escape:'htmlall':'UTF-8'}</option>{/foreach}</select></td>
                <td class="text-end"><button type="button" class="btn btn-link text-danger p-0 event-inventory-reservation-remove" title="{$l10n->get('SYS_DELETE')}"><i class="bi bi-trash"></i></button></td>
            </tr>
        {/foreach}
    </tbody>
</table>
{include 'sys-template-parts/form.button.tpl' data=$elements['event_inventory_reservation_add']}
