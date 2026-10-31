<ul class="list-group admidio-list-roles-assign">
    {if $userRelations}
        {foreach $userRelations as $userRelation}
            <li id="row_ure_{$userRelation.uuid}" class="list-group-item">
                <div>
                    <span>{$userRelation.relationName} - <a href="{$userRelation.urlUserProfile}">{$userRelation.userFirstName} {$userRelation.userLastName}</a>
                    </span>
                    <span class="float-end text-end">
                        {assign var=relationActions value=[]}
                        {if isset($userRelation.urlUserEdit)}
                            {$relationActions[] = ['url' => $userRelation.urlUserEdit, 'icon' => 'bi bi-pencil-square', 'tooltip' => $l10n->get('SYS_EDIT_USER_IN_RELATION')]}
                        {/if}
                        {if $isAdministratorUsers}
                            {$relationActions[] = ['dataMessage' => $l10n->get('SYS_WANT_DELETE_ENTRY', array($userRelation.relationName)), 'dataHref' => $userRelation.urlRelationDelete, 'icon' => 'bi bi-trash', 'tooltip' => $l10n->get('SYS_CANCEL_RELATIONSHIP')]}
                        {/if}
                        {if $showRelationsCreateEdit}
                            {$relationActions[] = ['class' => 'admidio-create-edit-info', 'attributes' => ['data-info-id' => "relation_info_{$userRelation.uuid}"], 'icon' => 'bi bi-info-circle', 'tooltip' => $l10n->get('SYS_INFORMATIONS')]}
                        {/if}
                        {include 'sys-template-parts/list.functions.tpl' data=['uuid' => $userRelation.uuid, 'actions' => $relationActions]}
                    </span>
                </div>
                {if $showRelationsCreateEdit}
                    <div id="relation_info_{$userRelation.uuid}_content" style="display: none;">
                        {include file="sys-template-parts/system.info-create-edit.tpl" userCreatedName=$userRelation.userCreatedName userCreatedTimestamp=$userRelation.userCreatedTimestamp lastUserEditedName=$userRelation.lastUserEditedName lastUserEditedTimestamp=$userRelation.lastUserEditedTimestamp}
                    </div>
                {/if}
            </li>
        {/foreach}
    {else}
        {$l10n->get('SYS_NO_DATA_FOUND')}
    {/if}
</ul>
