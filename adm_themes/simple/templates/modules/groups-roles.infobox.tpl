<div class="card admidio-blog" id="adm_lists_infobox">
    <div class="card-header">{$l10n->get('SYS_INFOBOX')}: {$role}</div>
    <div class="card-body">
        {foreach $roleProperties as $roleProperty}
            <div class="admidio-form-group row mb-4">
                <div class="col-sm-3">
                    {$roleProperty.label}
                </div>
                <div class="col-sm-9">
                    <strong>{$roleProperty.value}</strong>
                </div>
            </div>
        {/foreach}
    </div>
</div>
