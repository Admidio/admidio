<ul class="list-group admidio-list-roles-assign">
{foreach $userRelations as $userRelation}
    <li id="row_ure_{$userRelation.uuid}" class="list-group-item">
        <div>
            <span>{$userRelation.relationName} - <a href="{$userRelation.urlUserProfile}">{$userRelation.userFirstName} {$userRelation.userLastName}</a>
                {if isset($userRelation.urlUserEdit)}
                    <a class="admidio-icon-link" href="{$userRelation.urlUserEdit}"><i
                        class="bi bi-pencil-square" data-bs-toggle="tooltip" title="{$l10n->get('SYS_EDIT_USER_IN_RELATION')}"></i></a>
                {/if}
            </span>
            <span class="float-end text-end">
                {if $isAdministratorUsers}
                    <a class="admidio-icon-link admidio-messagebox" href="javascript:void(0);" data-buttons="yes-no"
                    data-message="{$l10n->get('SYS_WANT_DELETE_ENTRY', array({$userRelation.relationName}))}" data-href="{$userRelation.urlRelationDelete}"><i
                        class="bi bi-trash" data-bs-toggle="tooltip" title="{$l10n->get('SYS_CANCEL_RELATIONSHIP')}"></i></a>
                {/if}
                {if $showRelationsCreateEdit}
                    <a class="admidio-icon-link admidio-create-edit-info" id="relation_info_{$userRelation.uuid}" href="javascript:void(0)"><i
                        class="bi bi-info-circle" data-bs-toggle="tooltip" title="{$l10n->get('SYS_INFORMATIONS')}"></i></a>
                {/if}
            </span>
        </div>
        {if $showRelationsCreateEdit}
            <div id="relation_info_{$userRelation.uuid}_content" style="display: none;">
                {include file="sys-template-parts/system.info-create-edit.tpl" userCreatedName=$userRelation.userCreatedName userCreatedTimestamp=$userRelation.userCreatedTimestamp lastUserEditedName=$userRelation.lastUserEditedName lastUserEditedTimestamp=$userRelation.lastUserEditedTimestamp}
            </div>
        {/if}
    </li>
{/foreach}
</ul>
