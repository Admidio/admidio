<ul class="list-group admidio-list-roles-assign">
    {foreach $externalRoles as $externalRole}
        <li class="list-group-item">
            <span>{$externalRole.organization} - {$externalRole.category} - {$externalRole.role}
                {if $externalRole.leader}
                    &nbsp;-&nbsp;{$l10n->get('SYS_LEADER')}
                {/if}
            </span>
            <span class="float-end">{$externalRole.timestamp}</span>
        </li>
    {/foreach}
</ul>