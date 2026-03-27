<p class="lead">{$description}</p>
<div class="card admidio-blog">
    <div class="card-header">{$l10n->get('SYS_SIMILAR_CONTACTS_FOUND')}</div>
    <div class="card-body">
        <ul class="list-group list-group-flush">
            {foreach $similarUsers as $similarUser}
                <li class="list-group-item">
                    <a href="{$similarUser.profileUrl}" title="{$l10n->get('SYS_SHOW_PROFILE')}">
                        <i class="bi bi-person-fill"></i>{$similarUser.data->getValue('FIRST_NAME')} {$similarUser.data->getValue('LAST_NAME')}</a><br />
                    {if $similarUser.data->getValue('STREET') ne ''}
                        {$similarUser.data->getValue('STREET')}<br />
                    {/if}
                    {if $similarUser.data->getValue('POSTCODE') ne '' or $similarUser.data->getValue('CITY') ne ''}
                        {$similarUser.data->getValue('POSTCODE')} {$similarUser.data->getValue('CITY')}<br />
                    {/if}
                    {if $similarUser.data->getValue('EMAIL') ne ''}
                        <a href="{$similarUser.emailUrl}">{$similarUser.data->getValue('EMAIL')}</a><br />
                    {/if}
                    {if {array_key_exists array=$similarUser key='button'}}
                        <br />
                        <p>{$similarUser.button.description}</p>
                        <button class="btn btn-primary" onclick="redirectPost('{$similarUser.button.url}',{ adm_csrf_token: '{$similarUser.button.csrfToken}' });">
                            <i class="bi {$similarUser.button.icon}"></i>{$similarUser.button.label}</button>
                    {/if}
                </li>
            {/foreach}
        </ul>
    </div>
</div>
<div class="card admidio-blog">
    <div class="card-header">{$l10n->get('SYS_CREATE_CONTACT')}</div>
    <div class="card-body">
        <p>{$l10n->get('SYS_CONTACT_NOT_FOUND_CREATE_NEW')}</p>

        <button class="btn btn-primary" onclick="redirectPost('{$createNewUserUrl}', { adm_csrf_token: '{$csrfToken}' });">
            <i class="bi bi-plus-circle-fill"></i>{$l10n->get('SYS_CREATE_CONTACT')}</button>
    </div>
</div>
