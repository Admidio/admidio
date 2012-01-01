<?php
/******************************************************************************
 * Kategorien anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * cat_id: ID der Rollen-Kategorien, die bearbeitet werden soll
 * type  : Typ der Kategorie, die angelegt werden sollen
 *         ROL = Rollenkategorien
 *         LNK = Linkkategorien
 *         USF = Profilfelder
 *         DAT = Termine
 * title : Übergabe des Synonyms für Kategorie.
 *
 ****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_category.php');

// Initialize and check the parameters
$getCatId = admFuncVariableIsValid($_GET, 'cat_id', 'numeric', 0);
$getType  = admFuncVariableIsValid($_GET, 'type', 'string', null, true, array('ROL', 'LNK', 'USF', 'DAT'));
$getTitle = admFuncVariableIsValid($_GET, 'title', 'string', $gL10n->get('SYS_CATEGORY'));

// Modus und Rechte pruefen
if($getType == 'ROL' && $gCurrentUser->assignRoles() == false)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}
elseif($getType == 'LNK' && $gCurrentUser->editWeblinksRight() == false)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}
elseif($getType == 'USF' && $gCurrentUser->editUsers() == false)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}
elseif($getType == 'DAT' && $gCurrentUser->editDates() == false)
{
	$gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// UserField-objekt anlegen
$category = new TableCategory($gDb);

if($getCatId > 0)
{
    $category->readData($getCatId);

    // Pruefung, ob die Kategorie zur aktuellen Organisation gehoert bzw. allen verfuegbar ist
    if($category->getValue('cat_org_id') >  0
    && $category->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id'))
    {
        $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
    }
}

if(isset($_SESSION['categories_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
	$category->setArray($_SESSION['categories_request']);
	if(isset($_SESSION['categories_request']['show_in_several_organizations']) == false)
	{
	   $category->setValue('cat_org_id', $gCurrentOrganization->getValue('org_id'));
	}
    unset($_SESSION['categories_request']);
}

// Systemkategorien duerfen nicht umbenannt werden
$html_disabled = '';
$field_focus   = 'cat_name';
if($category->getValue('cat_system') == 1)
{
    $html_disabled = ' disabled="disabled" ';
    $field_focus   = 'btn_save';
}

// Html-Kopf ausgeben
if($getCatId > 0)
{
    $gLayout['title']  = $gL10n->get('SYS_EDIT_VAR', $getTitle);
}
else
{
    $gLayout['title']  = $gL10n->get('SYS_CREATE_VAR', $getTitle);
}
$gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() 
        {
            $("#'.$field_focus.'").focus();
        }); 
    //--></script>';
require(SERVER_PATH. '/adm_program/system/overall_header.php');

// Html des Modules ausgeben
echo '
<form action="'.$g_root_path.'/adm_program/administration/categories/categories_function.php?cat_id='.$getCatId.'&amp;type='. $getType. '&amp;mode=1" method="post">
<div class="formLayout" id="edit_categories_form">
    <div class="formHead">'. $gLayout['title']. '</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="cat_name">'.$gL10n->get('SYS_NAME').':</label></dt>
                    <dd>
                        <input type="text" id="cat_name" name="cat_name" '.$html_disabled.' style="width: 90%;" maxlength="100" value="'. $category->getValue('cat_name', 'plain'). '" />
                        <span class="mandatoryFieldMarker" title="'.$gL10n->get('SYS_MANDATORY_FIELD').'">*</span>
                    </dd>
                </dl>
            </li>';

            if($getType == 'USF')
            {
                // besitzt die Organisation eine Elternorga oder hat selber Kinder, so kann die Kategorie fuer alle Organisationen sichtbar gemacht werden
                if($category->getValue('cat_system') == 0
                && $gCurrentOrganization->countAllRecords() > 1)
                {
                    echo '
                    <li>
                        <dl>
                            <dt>&nbsp;</dt>
                            <dd>
                                <input type="checkbox" id="show_in_several_organizations" name="show_in_several_organizations" ';
                                if($category->getValue('cat_org_id') == 0)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                                <label for="show_in_several_organizations">'.$gL10n->get('SYS_ENTRY_MULTI_ORGA').'</label>
                                <a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=CAT_CATEGORY_GLOBAL&amp;inline=true"><img 
                                    onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=CAT_CATEGORY_GLOBAL\',this)" onmouseout="ajax_hideTooltip()"
                                    class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="help" title="" /></a>
                            </dd>
                        </dl>
                    </li>';
                }
            }
            else
            {
                echo '
                <li>
                    <dl>
                        <dt>
                            <label for="cat_hidden"><img src="'. THEME_PATH. '/icons/user_key.png" alt="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" /></label>
                        </dt>
                        <dd>
                            <input type="checkbox" id="cat_hidden" name="cat_hidden" ';
                                if($category->getValue('cat_hidden') == 1)
                                {
                                    echo ' checked="checked" ';
                                }
                                echo ' value="1" />
                            <label for="cat_hidden">'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'</label>
                        </dd>
                    </dl>
                </li>';
            }
			echo '
			<li>
				<dl>
					<dt>
						<label for="cat_default"><img src="'. THEME_PATH. '/icons/star.png" alt="'.$gL10n->get('CAT_DEFAULT_VAR', $getTitle).'" /></label>
					</dt>
					<dd>
						<input type="checkbox" id="cat_default" name="cat_default" ';
							if($category->getValue('cat_default') == 1)
							{
								echo ' checked="checked" ';
							}
							echo ' value="1" />
						<label for="cat_default">'.$gL10n->get('CAT_DEFAULT_VAR', $getTitle).'</label>
					</dd>
				</dl>
			</li>
		</ul>
        <hr />';

        if($category->getValue('cat_usr_id_create') > 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($gDb, $gProfileFields, $category->getValue('cat_usr_id_create'));
                echo $gL10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $category->getValue('cat_timestamp_create'));

                if($category->getValue('cat_usr_id_change') > 0)
                {
                    $user_change = new User($gDb, $gProfileFields, $category->getValue('cat_usr_id_change'));
                    echo '<br />'.$gL10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $category->getValue('cat_timestamp_change'));
                }
            echo '</div>';
        }

        echo '<div class="formSubmit">
            <button id="btnSave" type="submit"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$gL10n->get('SYS_SAVE').'" />&nbsp;'.$gL10n->get('SYS_SAVE').'</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>