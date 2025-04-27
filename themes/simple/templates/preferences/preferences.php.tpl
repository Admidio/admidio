<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="phpVersion" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_PHP_VERSION')}
    </label>
    <div class="col-sm-9">
        <div id="phpVersion">
            <span class="{$phpVersionColorClass}"><strong>{$phpVersionText}</strong></span>{$phpVersionInfo}
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="postMaxSize" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_POST_MAX_SIZE')}
    </label>
    <div class="col-sm-9">
        <div id="postMaxSize">
            <span class="{$postMaxSizeColorClass}"><strong>{$postMaxSizeText}</strong></span>
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="memoryLimit" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_MEMORY_LIMIT')}
    </label>
    <div class="col-sm-9">
        <div id="memoryLimit">
            <span class="{$memoryLimitColorClass}"><strong>{$memoryLimitText}</strong></span>
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="fileUploads" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_FILE_UPLOADS')}
    </label>
    <div class="col-sm-9">
        <div id="fileUploads">
            <span class="{$fileUploadsColorClass}"><strong>{$fileUploadsText}</strong></span>
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="uploadMaxFilesize" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_UPLOAD_MAX_FILESIZE')}
    </label>
    <div class="col-sm-9">
        <div id="uploadMaxFilesize">
            <span class="{$uploadMaxFilesizeColorClass}"><strong>{$uploadMaxFilesizeText}</strong></span>
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="prnGenerator" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_PRNG')}
    </label>
    <div class="col-sm-9">
        <div id="prnGenerator">
            <span class="{$prnGeneratorColorClass}"><strong>{$prnGeneratorText}</strong></span>{$prnGeneratorInfo}
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="phpInfo" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_PHP_INFO')}
    </label>
    <div class="col-sm-9">
        <div id="phpInfo">
            <a href="{$admidioUrl}/system/phpinfo.php" target="_blank">phpinfo()</a> <i class="bi bi-box-arrow-up-right"></i>
        </div>
    </div>
</div>
