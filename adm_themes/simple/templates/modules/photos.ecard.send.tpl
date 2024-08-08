<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['admidio-csrf-token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['submit_action']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['photo_uuid']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['photo_nr']}
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_LAYOUT')}</div>
        <div class="card-body">
            <div class="admidio-form-group admidio-form-custom-content row mb-4">
                <label for="ecardPhoto" class="col-sm-3 col-form-label">
                    {$l10n->get('SYS_PHOTO')}
                </label>
                <div class="col-sm-9 admidio-photos-thumbnail">
                    <a data-lightbox="image" data-title="{$l10n->get('SYS_PREVIEW')}" href="{$photoPreviewUrl}">
                        <img class="rounded" src="{$photoPreviewUrl}" class="imageFrame"
                             alt="{$l10n->get('SYS_VIEW_PICTURE_FULL_SIZED')}"  title="{$l10n->get('SYS_VIEW_PICTURE_FULL_SIZED')}" />
                    </a>
                </div>
            </div>
            {include 'sys-template-parts/form.select.tpl' data=$elements['ecard_template']}
        </div>
    </div>
    <div class="card admidio-field-group">
        <div class="card-header">{$l10n->get('SYS_CONTACT_DETAILS')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.select.tpl' data=$elements['ecard_recipients']}
            <hr />
            {include 'sys-template-parts/form.input.tpl' data=$elements['name_from']}
            {include 'sys-template-parts/form.input.tpl' data=$elements['mail_from']}
        </div>
    </div>
    <div class="card admidio-field-group admidio-panel-editor">
        <div class="card-header">{$l10n->get('SYS_MESSAGE')}</div>
        <div class="card-body">
            {include 'sys-template-parts/form.editor.tpl' data=$elements['ecard_message']}
        </div>
    </div>
    <div class="form-alert" style="display: none;">&nbsp;</div>
    <div class="btn-group" role="group">
        {include 'sys-template-parts/form.button.tpl' data=$elements['btn_ecard_preview']}
        {include 'sys-template-parts/form.button.tpl' data=$elements['btn_ecard_submit']}
    </div>
</form>
