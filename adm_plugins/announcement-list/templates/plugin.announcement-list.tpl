<div id="plugin-{$name}" class="admidio-plugin-content">
    <h3>{$l10n->get('PLG_ANNOUNCEMENT_LIST_HEADLINE')}</h3>

    {if count($announcements) > 0}
        <ul class="list-group list-group-flush">
            {foreach $announcements as $announcement}
                <li class="list-group-item">
                    <h5><a href="{$urlAdmidio}/modules/announcements.php?announcement_uuid={$announcement.uuid}">{$announcement.headline}</a></h5>
                    <div>{$announcement.description}</div>
                    <div><em>({$announcement.creationDate})</em></div>
                </li>
            {/foreach}
            <li class="list-group-item">
                <a href="{$urlAdmidio}/modules/announcements.php">{$l10n->get('PLG_ANNOUNCEMENT_LIST_ALL_ENTRIES')}</a>
            </li>
        </ul>
    {else}
        {$message}
    {/if}
</div>
