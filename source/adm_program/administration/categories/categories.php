<?php
/******************************************************************************
 * Uebersicht und Pflege aller Kategorien
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * type : Typ der Kategorien, die gepflegt werden sollen
 *        ROL = Rollenkategorien
 *        LNK = Linkkategorien
 *        USF = Profilfelder
 *        DAT = Termine
 * title : Übergabe des Synonyms für Kategorie.
 *
 ****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_category.php');

// Initialize and check the parameters
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
unset($_SESSION['categories_request']);

// Html-Kopf ausgeben
$gLayout['title']  = $gL10n->get('SYS_ADMINISTRATION_VAR', $getTitle);
$gLayout['header'] = '
<script type="text/javascript"><!--
	function moveCategory(direction, catID)
	{
		var actRow = document.getElementById("row_" + catID);
		var childs = actRow.parentNode.childNodes;
		var prevNode    = null;
		var nextNode    = null;
		var actRowCount = 0;
		var actSequence = 0;
		var secondSequence = 0;

		// erst einmal aktuelle Sequenz und vorherigen/naechsten Knoten ermitteln
		for(i=0;i < childs.length; i++)
		{
			if(childs[i].tagName == "TR")
			{
				actRowCount++;
				if(actSequence > 0 && nextNode == null)
				{
					nextNode = childs[i];
				}

				if(childs[i].id == "row_" + catID)
				{
					actSequence = actRowCount;
				}

				if(actSequence == 0)
				{
					prevNode = childs[i];
				}
			}
		}

		// entsprechende Werte zum Hoch- bzw. Runterverschieben ermitteln
		if(direction == "up")
		{
			if(prevNode != null)
			{
				actRow.parentNode.insertBefore(actRow, prevNode);
				secondSequence = actSequence - 1;
			}
		}
		else
		{
			if(nextNode != null)
			{
				actRow.parentNode.insertBefore(nextNode, actRow);
				secondSequence = actSequence + 1;
			}
		}

		if(secondSequence > 0)
		{
			// Nun erst mal die neue Position von der gewaehlten Kategorie aktualisieren
			$.get(gRootPath + "/adm_program/administration/categories/categories_function.php?cat_id=" + catID + "&type='. $getType. '&mode=4&sequence=" + direction);
		}
	}
	
	$(document).ready(function() 
	{
		$("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', height: \'320px\', onComplete:function(){$("#admButtonNo").focus();}});
	}); 
//--></script>';

require(SERVER_PATH. '/adm_program/system/overall_header.php');

$icon_login_user = '';
if($getType != 'USF')
{
    $icon_login_user = '<img class="iconInformation" src="'.THEME_PATH.'/icons/user_key.png" alt="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" title="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" />';
}

// Html des Modules ausgeben
echo '
<h1 class="moduleHeadline">'.$gLayout['title'].'</h1>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories_new.php?type='.$getType.'&amp;title='.$getTitle.'"><img
            src="'.THEME_PATH.'/icons/add.png" alt="'.$gL10n->get('SYS_CREATE_VAR', $getTitle).'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories_new.php?type='.$getType.'&amp;title='.$getTitle.'">'.$gL10n->get('SYS_CREATE_VAR', $getTitle).'</a>
        </span>
    </li>
</ul>

<table class="tableList" id="tableCategories" style="width: 400px;" cellspacing="0">
    <thead>
        <tr>
            <th>'.$gL10n->get('SYS_TITLE').'</th>
            <th>&nbsp;</th>
            <th>'.$icon_login_user.'</th>
			<th><img class="iconInformation" src="'.THEME_PATH.'/icons/star.png" alt="'.$gL10n->get('CAT_DEFAULT_VAR', $getTitle).'" title="'.$gL10n->get('CAT_DEFAULT_VAR', $getTitle).'" /></th>
            <th>&nbsp;</th>
        </tr>
    </thead>';

    $sql = 'SELECT * FROM '. TBL_CATEGORIES. '
             WHERE (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                   OR cat_org_id IS NULL )
               AND cat_type   = \''.$getType.'\'
             ORDER BY cat_sequence ASC ';
    $cat_result = $gDb->query($sql);
    $write_tbody = false;
    $write_all_orgas = false;
	
	$category = new TableCategory($gDb);

    while($cat_row = $gDb->fetch_array($cat_result))
    {
        $category->clear();
		$category->setArray($cat_row);
			
        if($category->getValue('cat_system') == 1 && $getType == 'USF')
        {
            // da bei USF die Kategorie Stammdaten nicht verschoben werden darf, muss hier ein bischen herumgewurschtelt werden
            echo '<tbody id="cat_'.$category->getValue('cat_id').'">';
        }
        elseif($category->getValue('cat_org_id') == 0 && $getType == 'USF')
        {
            // Kategorien über alle Organisationen kommen immer zuerst
            if($write_all_orgas == false)
            {
                $write_all_orgas = true;
                echo '</tbody>
                <tbody id="cat_all_orgas">';
            }
        }
        else
        {
            if($write_tbody == false)
            {
                $write_tbody = true;
                if($getType == 'USF')
                {
                    echo '</tbody>';
                }
                echo '<tbody id="cat_list">';
            }
        }
        echo '
        <tr id="row_'. $category->getValue('cat_id'). '" class="tableMouseOver">
            <td><a href="'.$g_root_path.'/adm_program/administration/categories/categories_new.php?cat_id='. $category->getValue('cat_id'). '&amp;type='.$getType.'&amp;title='.$getTitle.'">'. $category->getValue('cat_name'). '</a></td>
            <td style="text-align: right; width: 45px;"> ';
                if($category->getValue('cat_system') == 0 || $getType != 'USF')
                {
                    echo '
                    <a class="iconLink" href="javascript:moveCategory(\'up\', '.$category->getValue('cat_id').')"><img
                            src="'. THEME_PATH. '/icons/arrow_up.png" alt="'.$gL10n->get('CAT_MOVE_UP', $getTitle).'" title="'.$gL10n->get('CAT_MOVE_UP', $getTitle).'" /></a>
                    <a class="iconLink" href="javascript:moveCategory(\'down\', '.$category->getValue('cat_id').')"><img
                            src="'. THEME_PATH. '/icons/arrow_down.png" alt="'.$gL10n->get('CAT_MOVE_DOWN', $getTitle).'" title="'.$gL10n->get('CAT_MOVE_DOWN', $getTitle).'" /></a>';
                }
            echo '</td>
            <td>';
                if($category->getValue('cat_hidden') == 1)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/user_key.png" alt="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" title="'.$gL10n->get('SYS_VISIBLE_TO_USERS', $getTitle).'" />';
                }
                else
                {
                    echo '&nbsp;';
                }
            echo '</td>
            <td>';
                if($category->getValue('cat_default') == 1)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/star.png" alt="'.$gL10n->get('CAT_DEFAULT_VAR', $getTitle).'" title="'.$gL10n->get('CAT_DEFAULT_VAR', $getTitle).'" />';
                }
                else
                {
                    echo '&nbsp;';
                }
            echo '</td>
            <td style="text-align: right; width: 90px;">
                <a class="iconLink" href="'.$g_root_path.'/adm_program/administration/categories/categories_new.php?cat_id='. $category->getValue('cat_id'). '&amp;type='.$getType.'&amp;title='.$getTitle.'"><img
                src="'. THEME_PATH. '/icons/edit.png" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'" /></a>';

                if($category->getValue('cat_system') == 1)
                {
                    echo '<img class="iconLink" src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" />';
                }
                else
                {
                    echo '<a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=cat&amp;element_id=row_'.
						$category->getValue('cat_id').'&amp;name='.urlencode($category->getValue('cat_name')).'&amp;database_id='.$category->getValue('cat_id').'&amp;database_id_2='.$getType.'"><img 
						src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>';
                }
            echo '</td>
        </tr>';
    }
    echo '</tbody>
</table>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'.THEME_PATH.'/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$gL10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>