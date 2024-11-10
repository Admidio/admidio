{if $settings->getInt('system_show_create_edit') > 0 && $nameUserCreated|count_characters > 0}
    <div class="admidio-info-created-edited">
        <span class="admidio-info-created">{$l10n->get('SYS_CREATED_BY_AND_AT', array($nameUserCreated, $timestampUserCreated))}</span>

        {if isset($nameLastUserEdited) && $nameLastUserEdited|count_characters > 0}
            <span class="admidio-info-created">{$l10n->get('SYS_LAST_EDITED_BY', array($nameLastUserEdited, $timestampLastUserEdited))}</span>
        {/if}
    </div>
{/if}
