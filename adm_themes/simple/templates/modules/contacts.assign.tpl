<p class="lead admidio-max-with">{$description}</p>
<div class="card admidio-blog">
    <div class="card-header">{$l10n->get('SYS_SIMILAR_CONTACTS_FOUND')}</div>
    <div class="card-body">
        <ul class="list-group list-group-flush">
            {foreach $similarUsers as $similarUser}
                <li class="list-group-item">
                    <a href="{$similarUser.profileUrl}" title="{$l10n->get('SYS_SHOW_PROFILE')}">
                        <i class="fas fa-user"></i>{$similarUser.data->getValue('FIRST_NAME')} {$similarUser.data->getValue('LAST_NAME')}</a><br />
                    {if $similarUser.data->getValue('STREET') ne ''}
                        {$similarUser.data->getValue('STREET')}<br />
                    {/if}
                    {if $similarUser.data->getValue('POSTCODE') ne '' or $similarUser.data->getValue('CITY') ne ''}
                        {$similarUser.data->getValue('POSTCODE')} {$similarUser.data->getValue('CITY')}<br />
                    {/if}
                    {if $similarUser.data->getValue('EMAIL') ne ''}
                        <a href="{$similarUser.emailUrl}">{$similarUser.data->getValue('EMAIL')}</a><br />
                    {/if}
                    {if array_key_exists('button', $similarUser)}
                        <br />
                        <p>{$similarUser.button.description}</p>
                        <button class="btn btn-primary" onclick="window.location.href='{$similarUser.button.url}'">
                            <i class="fas {$similarUser.button.icon}"></i>{$similarUser.button.label}</button>
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

        <button class="btn btn-primary" onclick="window.location.href='{$createNewUserUrl}'">
            <i class="fas fa-plus-circle"></i>{$l10n->get('SYS_CREATE_CONTACT')}</button>
    </div>
</div>
