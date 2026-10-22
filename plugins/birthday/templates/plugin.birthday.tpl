<div id="plugin-{$name}" class="admidio-plugin-content">
    <h3>{$l10n->get('PLG_BIRTHDAY_HEADLINE')}</h3>

    {if count($birthdays) > 0}
        <ul class="list-group list-group-flush">
            {foreach $birthdays as $birthday}
                <li class="list-group-item">{$birthday.userText}</li>
            {/foreach}
        </ul>
    {else}
        {$message}
    {/if}
</div>
