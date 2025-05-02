<div class="row">
    <div class="col-sm-8">
        {$showName = true}
        {$showUsername = true}
        {$showAddress = true}
        {foreach $masterData as $profileField}
            {if {$profileField.id} == 'LAST_NAME' || {$profileField.id} == 'FIRST_NAME' || {$profileField.id} == 'GENDER'}
                {if $showName}
                    {$showName = false}
                    <div class="admidio-form-group row mb-3">
                        <div class="col-sm-3">
                            {$l10n->get('SYS_NAME')}
                        </div>
                        <div class="col-sm-9">
                            <strong>
                            {$masterData.FIRST_NAME.value} {$masterData.LAST_NAME.value}
                            {if isset($masterData.GENDER)}
                                {$masterData.GENDER.value}
                            {/if}
                            </strong>
                        </div>
                    </div>
                {/if}
            {elseif {$profileField.id} == 'usr_login_name' || {$profileField.id} == 'usr_actual_login'}
                {if $showUsername}
                    {$showUsername = false}
                    <div class="admidio-form-group row mb-3">
                        <div class="col-sm-3">
                            {$profileField.label}
                        </div>
                        <div class="col-sm-9">
                            <strong>{$profileField.value}</strong>
                            {if isset($masterData.usr_actual_login)}
                                <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
                                data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
                                data-bs-content="{$lastLoginInfo}"></i>
                            {/if}
                        </div>
                    </div>
                {/if}
            {elseif {$profileField.id} == 'STREET' || {$profileField.id} == 'POSTCODE' || {$profileField.id} == 'CITY' || {$profileField.id} == 'COUNTRY'}
                    {if $showAddress}
                        {$showAddress = false}
                        <div class="admidio-form-group row mb-3">
                            <div class="col-sm-3">
                                {$l10n->get('SYS_ADDRESS')}
                            </div>
                            <div class="col-sm-9"><strong>
                                {$masterData.STREET.value}<br />
                                {$masterData.POSTCODE.value}  {$masterData.CITY.value}<br />
                                {$masterData.COUNTRY.value}</strong>
                                {if isset($urlMapAddress)}
                                    <br />
                                    <a class="icon-link" href="{$urlMapAddress}" target="_blank" title="{$l10n->get('SYS_MAP_LINK_HOME_DESC')}">
                                        <i class="bi bi-pin-map-fill"></i>{$l10n->get('SYS_MAP')}</a>
                                    {if isset($urlMapRoute)}
                                        &nbsp;-&nbsp;
                                        <a class="icon-link" href="{$urlMapRoute}" target="_blank" title="{$l10n->get('SYS_MAP_LINK_ROUTE_DESC')}">
                                            <i class="bi bi-sign-turn-right-fill"></i>{$l10n->get('SYS_SHOW_ROUTE')}</a>
                                    {/if}
                                {/if}
                            </div>
                        </div>
                    {/if}
            {else}
                <div class="admidio-form-group row mb-3">
                    <div class="col-sm-3">
                        {if strlen($profileField.icon) > 0}
                            {$profileField.icon}
                        {/if}
                        {$profileField.label}
                    </div>
                    <div class="col-sm-9">
                        <strong>{$profileField.value}</strong>
                    </div>
                </div>
            {/if}
        {/foreach}
    </div>
    <div class="col-sm-4 text-end">
        <img id="adm_profile_photo" class="rounded" src="{$urlProfilePhoto}" alt="{$l10n->get('SYS_CURRENT_PROFILE_PICTURE')}" />
        {if isset($urlProfilePhotoUpload)}
            <ul class="list-unstyled">
                <li><a class="icon-link" href="{$urlProfilePhotoUpload}">
                    <i class="bi bi-upload"></i>{$l10n->get('SYS_UPLOAD_PROFILE_PICTURE')}</a></li>
                {if isset($urlProfilePhotoDelete)}
                    <li><a id="adm_button_delete_photo" class="icon-link admidio-messagebox" href="javascript:void(0);"
                        data-buttons="yes-no" data-message="{$l10n->get('SYS_WANT_DELETE_PHOTO')}"
                        data-href="{$urlProfilePhotoDelete}"><i class="bi bi-trash"></i>{$l10n->get('SYS_DELETE_PROFILE_PICTURE')}</a></li>
                {/if}
            </ul>
        {/if}
    </div>
</div>