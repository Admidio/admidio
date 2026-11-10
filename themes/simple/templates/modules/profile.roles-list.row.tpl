<ul class="list-group admidio-list-roles-assign" id="{$listID}">
    {foreach $memberships as $membership}
        <li class="list-group-item" id="membership_{$membership.memberUUID}">
            <ul class="list-group admidio-list-roles-assign-pos">
                <li class="list-group-item">
                    <span>{$membership.category}&nbsp;-&nbsp;{$membership.role}
                        {if isset($membership.leader)}
                            &nbsp;-&nbsp;{$membership.leader}
                        {/if}
                    </span>
                    <span class="float-end text-end">
                        <span class="me-2">
                            {$membership.period}
                            {if isset($membership.duration)}
                                <span class="badge bg-info ms-1" data-bs-toggle="tooltip" title="{$l10n->get('SYS_MEMBERSHIP_DURATION')}">{$membership.duration}</span>
                            {/if}
                        </span>
                        {assign var=membershipActions value=$membership.actions}
                        {if $membership.showRelationsCreateEdit}
                            {$membershipActions[] = ['class' => 'admidio-create-edit-info', 'attributes' => ['data-info-id' => "member_info_{$membership.memberUUID}"], 'icon' => 'bi bi-info-circle', 'tooltip' => $l10n->get('SYS_INFORMATIONS')]}
                        {/if}
                        {include 'sys-template-parts/list.functions.tpl' data=['uuid' => $membership.memberUUID, 'actions' => $membershipActions]}
                    </span>
                </li>
                <li class="list-group-item" id="adm_membership_period_{$membership.memberUUID}" style="visibility: hidden; display: none;">
                    <form {foreach $membership.form.attributes as $attribute}
                            {$attribute@key}="{$attribute}"
                        {/foreach}>
                        {include 'sys-template-parts/form.input.tpl' data=$membership.form.elements['adm_csrf_token']}
                        {include 'sys-template-parts/form.input.tpl' data=$membership.form.elements['adm_membership_start_date']}
                        {include 'sys-template-parts/form.input.tpl' data=$membership.form.elements['adm_membership_end_date']}
                        {include 'sys-template-parts/form.button.tpl' data=$membership.form.elements['adm_button_send']}
                        <div class="form-alert" style="display: none;">&nbsp;</div>
                    </form>
                </li>
                {if $membership.showRelationsCreateEdit}
                    <li class="list-group-item" id="member_info_{$membership.memberUUID}_content" style="display: none;">
                        {include file="sys-template-parts/system.info-create-edit.tpl" userCreatedName=$membership.userCreatedName userCreatedTimestamp=$membership.userCreatedTimestamp lastUserEditedName=$membership.lastUserEditedName lastUserEditedTimestamp=$membership.lastUserEditedTimestamp}
                    </li>
                {/if}
            </ul>
        </li>
    {/foreach}
</ul>
