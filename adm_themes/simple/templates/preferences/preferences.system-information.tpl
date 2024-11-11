<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="operatingSystem" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_OPERATING_SYSTEM')}
    </label>
    <div class="col-sm-9">
        <div id="operatingSystem">
            <strong>{$operatingSystemName}</strong> ({$operatingSystemUserName})
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="architectureOS" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_64BIT')}
    </label>
    <div class="col-sm-9">
        <div id="architectureOS">
            <span class="{$architectureOSColorClass}"><strong>{$architectureOSText}</strong></span>
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="unix" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_UNIX')}
    </label>
    <div class="col-sm-9">
        <div id="unix"><strong>{$unixText}</strong></div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="fileUploads" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_DIRECTORY_SEPARATOR')}
    </label>
    <div class="col-sm-9">
        <div id="fileUploads"><strong>{$directorySeparator}</strong></div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="uploadMaxFilesize" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_PATH_SEPARATOR')}
    </label>
    <div class="col-sm-9">
        <div id="uploadMaxFilesize"><strong>{$pathSeparator}</strong></div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="prnGenerator" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_MAX_PATH_LENGTH')}
    </label>
    <div class="col-sm-9">
        <div id="prnGenerator">{$maxPathLength}</div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="architectureOS" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_64BIT')}
    </label>
    <div class="col-sm-9">
        <div id="architectureOS">
            <span class="{$architectureOSColorClass}"><strong>{$architectureOSText}</strong></span>
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="databaseVersion" class="col-sm-3 col-form-label">
        {$databaseVersionName}
    </label>
    <div class="col-sm-9">
        <div id="databaseVersion">
            <span class="{$databaseVersionColorClass}"><strong>{$databaseVersionText}</strong></span>{$databaseVersionInfo}
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="directoryProtection" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_DIRECTORY_PROTECTION')}
    </label>
    <div class="col-sm-9">
        <div id="directoryProtection">
            <span class="{$directoryProtectionColorClass}"><strong>{$directoryProtectionText}</strong></span>{$directoryProtectionInfo}
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="maxProcessableImageSize" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_MAX_PROCESSABLE_IMAGE_SIZE')}
    </label>
    <div class="col-sm-9">
        <div id="maxProcessableImageSize">{$maxProcessableImageSize}</div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="debugMode" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_DEBUG_OUTPUT')}
    </label>
    <div class="col-sm-9">
        <div id="debugMode">
            <span class="{$debugModeColorClass}"><strong>{$debugModeText}</strong></span>
        </div>
    </div>
</div><div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="importMode" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_IMPORT_MODE')}
    </label>
    <div class="col-sm-9">
        <div id="importMode">
            <span class="{$importModeColorClass}"><strong>{$importModeText}</strong></span>
        </div>
    </div>
</div>
<div class="admidio-form-group admidio-form-custom-content row mb-3">
    <label for="diskSpace" class="col-sm-3 col-form-label">
        {$l10n->get('SYS_DISK_SPACE')}
    </label>
    <div class="col-sm-9">
        <div id="diskSpace">{$diskSpaceContent}</div>
    </div>
</div>
