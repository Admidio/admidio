<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label class="col-sm-3 col-form-label">
        {$l10n->get('SYS_CURRENT_PROFILE_PICTURE')}
    </label>
    <div class="col-sm-9">
        <div>
            <img class="imageFrame" src="{$urlCurrentProfilePhoto}" alt="{$l10n->get('SYS_CURRENT_PROFILE_PICTURE')}" />
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label class="col-sm-3 col-form-label">
        {$l10n->get('SYS_NEW_PROFILE_PICTURE')}
    </label>
    <div class="col-sm-9">
        <div>
            <img class="imageFrame" src="{$urlNewProfilePhoto}" alt="{$l10n->get('SYS_NEW_PROFILE_PICTURE')}" />
        </div>
    </div>
</div>
<hr />
<a id="buttonDonate" class="btn btn-primary admidio-margin-bottom" href="{$urlNextPage}">
    <i class="bi bi-upload" data-bs-toggle="tooltip" title="{$l10n->get('SYS_APPLY')}"></i>{$l10n->get('SYS_APPLY')}
</a>
