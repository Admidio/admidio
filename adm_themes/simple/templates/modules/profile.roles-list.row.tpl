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
                    <span class="float-end text-right">
                        <span class="me-2">{$membership.period}</span>
                        {if isset($membership.linkMembershipEdit)}
                            {$membership.linkMembershipEdit}
                        {/if}
                        {if isset($membership.linkMembershipDelete)}
                            {$membership.linkMembershipDelete}
                        {/if}
                        {if $membership.showRelationsCreateEdit}
                            <a class="admidio-icon-link admidio-create-edit-info" id="member_info_{$membership.memberUUID}" href="javascript:void(0)"><i
                                        class="bi bi-info-circle" data-bs-toggle="tooltip" title="{$l10n->get('SYS_INFORMATIONS')}"></i></a>
                        {/if}
                    </span>
                </li>
                <li class="list-group-item" id="membership_period_{$membership.memberUUID}" style="visibility: hidden; display: none;">
                    <form {foreach $membership.form.attributes as $attribute}
                            {$attribute@key}="{$attribute}"
                        {/foreach}>
                        {include 'sys-template-parts/form.input.tpl' data=$membership.form.elements['admidio-csrf-token']}
                        {include 'sys-template-parts/form.input.tpl' data=$membership.form.elements['membership_start_date']}
                        {include 'sys-template-parts/form.input.tpl' data=$membership.form.elements['membership_end_date']}
                        {include 'sys-template-parts/form.button.tpl' data=$membership.form.elements['btn_send']}
                        <div class="form-alert" style="display: none;">&nbsp;</div>
                    </form>
                </li>
                {if $membership.showRelationsCreateEdit}
                    <li class="list-group-item" id="member_info_{$membership.memberUUID}_Content" style="display: none;">
                        <div class="admidio-info-created-edited">
                            <span class="admidio-info-created">{$l10n->get('SYS_CREATED_BY_AND_AT', array($membership.nameUserCreated, $membership.timestampUserCreated))}</span>

                            {if isset($membership.nameLastUserEdited) && $membership.nameLastUserEdited|count_characters > 0}
                                <span class="admidio-info-created">{$l10n->get('SYS_LAST_EDITED_BY', array($membership.nameLastUserEdited, $membership.timestampLastUserEdited))}</span>
                            {/if}
                        </div>
                    </li>
                {/if}
            </ul>
        </li>
    {/foreach}
</ul>
