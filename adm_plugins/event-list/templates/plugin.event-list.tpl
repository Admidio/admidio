<div id="plugin-{$name}" class="admidio-plugin-content">
    <h3>{$l10n->get('PLG_EVENT_LIST_HEADLINE')}</h3>

    {if count($events) > 0}
        <ul class="list-group list-group-flush">
            {foreach $events as $event}
                <li class="list-group-item">
                    <h5>
                        {$event.dateTimePeriod}<br />
                        <a href="{$urlAdmidio}/adm_program/modules/events/events.php?view_mode=html&view=detail&dat_uuid={$event.uuid}">{$event.headline}</a>
                    </h5>
                    <div>{$event.description}</div>
                </li>
            {/foreach}
            <li class="list-group-item">
                <a href="{$urlAdmidio}/adm_program/modules/events/events.php">{$l10n->get('PLG_EVENT_LIST_ALL_EVENTS')}</a>
            </li>
        </ul>
    {else}
        {$message}
    {/if}
</div>
