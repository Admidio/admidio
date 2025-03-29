{if $settings->getInt('system_show_create_edit') > 0 && $userCreatedName|count_characters > 0}
    <div class="admidio-info-created-edited">
        <span class="admidio-info-created">{$l10n->get('SYS_CREATED_BY_AND_AT', array($userCreatedName, $userCreatedTimestamp))}</span>

        {if isset($lastUserEditedName) && $lastUserEditedName|count_characters > 0}
            <span class="admidio-info-created">{$l10n->get('SYS_LAST_EDITED_BY', array($lastUserEditedName, $lastUserEditedTimestamp))}</span>
        {/if}
    </div>
{/if}
