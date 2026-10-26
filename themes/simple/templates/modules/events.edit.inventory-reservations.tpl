<p class="form-text">{$l10n->get('SYS_INVENTORY_EVENT_RESERVATIONS_DESC')}</p>
{* Preserve an explicitly empty selection when every item row has been removed. *}
<input type="hidden" name="event_inventory_items[]" value="">
<table class="table table-hover w-100">
    <thead>
        <tr>
            <th>{$l10n->get('SYS_INVENTORY_ITEMNAME')}</th>
            <th></th>
        </tr>
    </thead>
    <tbody id="event_inventory_reservations_rows">
        {foreach $eventInventoryReservationRows as $row}
            <tr>
                <td>
                    <select class="form-select" name="event_inventory_items[]" aria-label="{$l10n->get('SYS_INVENTORY_ITEMNAME')}">
                        <option value="">- {$l10n->get('SYS_PLEASE_CHOOSE')} -</option>
                        {foreach $eventInventoryReservationItems as $item}
                            <option value="{$item.id}"{if $item.id === $row.itemId} selected{/if}>{$item.name|escape:'htmlall':'UTF-8'}</option>
                        {/foreach}
                    </select>
                </td>
                <td class="text-end">
                    <button type="button" class="admidio-icon-link border-0 bg-transparent text-danger event-inventory-reservation-remove"
                            aria-label="{$l10n->get('SYS_DELETE')}" title="{$l10n->get('SYS_DELETE')}">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        {/foreach}
    </tbody>
</table>
{include 'sys-template-parts/form.button.tpl' data=$elements['event_inventory_reservation_add']}
