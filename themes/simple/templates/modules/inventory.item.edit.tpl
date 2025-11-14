{if count($infoAlerts) > 0}
    {foreach $infoAlerts as $infoAlert}
        <div class="alert alert-{$infoAlert['type']}" role="alert"><i class="bi bi-info-circle-fill"></i>{$infoAlert['value']}</div>
    {/foreach}
{/if}

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_DESIGNATION')}</div>
        <div class="card-body">
            {if $multiEdit}
                {include 'sys-template-parts/form.multiline.tpl' data=$elements['INF-ITEMNAME']}  {* Names *}
            {else}
                {include 'sys-template-parts/form.input.tpl' data=$elements['INF-ITEMNAME']}  {* Name *}
            {/if}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_PROPERTIES')}</div>
        <div class="card-body">
            {if isset($urlItemPicture)}
                <div class="row">
                    <div class="col-sm-8">
            {/if}
                        {foreach $elements as $key => $itemField}
                            {if {string_contains haystack=$key needle="INF-"} && $key != "INF-ITEMNAME"}
                                {if $itemField.type == 'checkbox'}
                                    {include 'sys-template-parts/form.checkbox.tpl' data=$itemField}
                                {elseif $itemField.type == 'multiline'}
                                    {include 'sys-template-parts/form.multiline.tpl' data=$itemField}
                                {elseif $itemField.type == 'radio'}
                                    {include 'sys-template-parts/form.radio.tpl' data=$itemField}
                                {elseif $itemField.type == 'select'}
                                    {include 'sys-template-parts/form.select.tpl' data=$itemField}
                                {else}
                                    {if !{string_contains haystack=$key needle="_time"}}
                                        {include 'sys-template-parts/form.input.tpl' data=$itemField}
                                    {/if}
                                {/if}
                            {/if}
                        {/foreach}
            {if isset($urlItemPicture)}
                    </div>
                    <div class="col-sm-4 text-end">
                        <img id="adm_inventory_item_picture" class="rounded" src="{$urlItemPicture}" alt="{$l10n->get('SYS_INVENTORY_ITEM_PICTURE_CURRENT')}" />
                        {if isset($urlItemPictureUpload)}
                            <ul class="list-unstyled">
                                <li><a class="icon-link" href="{$urlItemPictureUpload}">
                                    <i class="bi bi-upload"></i>{$l10n->get('SYS_INVENTORY_ITEM_PICTURE_UPLOAD')}</a></li>
                                {if isset($urlItemPictureDelete)}
                                    <li><a id="adm_button_delete_picture" class="icon-link admidio-messagebox" href="javascript:void(0);"
                                        data-buttons="yes-no" data-message="{$l10n->get('SYS_INVENTORY_ITEM_PICTURE_WANT_DELETE')}"
                                        data-href="{$urlItemPictureDelete}"><i class="bi bi-trash"></i>{$l10n->get('SYS_INVENTORY_ITEM_PICTURE_DELETE')}</a></li>
                                {/if}
                            </ul>
                        {/if}
                    </div>
                </div>
            {/if}
        </div>
    </div>
    {if {array_key_exists array=$elements key='item_copy_number'}}
        <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_INVENTORY_COPY_PREFERENCES')}</div>
            <div class="card-body">
                {include 'sys-template-parts/form.input.tpl' data=$elements['item_copy_number']}
                {include 'sys-template-parts/form.select.tpl' data=$elements['item_copy_field']}
            </div>
        </div>
    {/if}

    <div class="form-alert" style="display: none;">&nbsp;</div>
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save']}
    {include file="sys-template-parts/system.info-create-edit.tpl"}
</form>
