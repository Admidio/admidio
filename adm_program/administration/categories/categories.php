<?php
/******************************************************************************
 * Uebersicht und Pflege aller Kategorien
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * type : (Pflichtuebergabe)
 *        Typ der Kategorien, die gepflegt werden sollen
 *        ROL = Rollenkategorien
 *        LNK = Linkkategorien
 *        USF = Profilfelder
 *        DAT = Datum
 * title:  -Übergabe des Synonyms für Kategorie.
 *
 ****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// lokale Variablen der Uebergabevariablen initialisieren
$req_type = '';

// Uebergabevariablen pruefen
$title = $g_l10n->get('SYS_CATEGORY');
if (isset($_GET['title'])) 
{
   $title = $_GET['title'];
}

// Modus und Rechte pruefen
if(isset($_GET['type']))
{
    if($_GET['type'] != 'ROL' && $_GET['type'] != 'LNK' && $_GET['type'] != 'USF' && $_GET['type'] != 'DAT')
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    if($_GET['type'] == 'ROL' && $g_current_user->assignRoles() == false)
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
    if($_GET['type'] == 'LNK' && $g_current_user->editWeblinksRight() == false)
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
    if($_GET['type'] == 'USF' && $g_current_user->editUsers() == false)
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
    if($_GET['type'] == 'DAT' && $g_current_user->editdates() == false)
    {
        $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
    }
    $req_type = $_GET['type'];
}
else
{
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}

$_SESSION['navigation']->addUrl(CURRENT_URL);
unset($_SESSION['categories_request']);

// Html-Kopf ausgeben
$g_layout['title']  = $g_l10n->get('SYS_PHR_ADMINISTRATION', $title);
$g_layout['header'] = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/ajax.js"></script>

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
                resObject.open("GET", gRootPath + "/adm_program/administration/categories/categories_function.php?cat_id=" + catID + "&type='. $_GET["type"]. '&mode=4&sequence=" + direction, true);
                resObject.send(null);
            }
        }
    //--></script>';

require(THEME_SERVER_PATH. '/overall_header.php');

$icon_login_user = '';
if($_GET['type'] != 'USF')
{
    $icon_login_user = '<img class="iconInformation" src="'.THEME_PATH.'/icons/user_key.png" alt="'.$g_l10n->get('SYS_PHR_VISIBLE_TO_USERS', $title).'" title="'.$g_l10n->get('SYS_PHR_VISIBLE_TO_USERS', $title).'" />';
}

// Html des Modules ausgeben
echo '
<h1 class="moduleHeadline">'.$g_layout['title'].'</h1>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories_new.php?type='.$req_type.'&amp;title='.$title.'"><img
            src="'.THEME_PATH.'/icons/add.png" alt="'.$g_l10n->get('SYS_PHR_CREATE', $title).'" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/categories/categories_new.php?type='.$req_type.'&amp;title='.$title.'">'.$g_l10n->get('SYS_PHR_CREATE', $title).'</a>
        </span>
    </li>
</ul>

<table class="tableList" id="tableCategories" style="width: 400px;" cellspacing="0">
    <thead>
        <tr>
            <th>'.$g_l10n->get('SYS_TITLE').'</th>
            <th>&nbsp;</th>
            <th>'.$icon_login_user.'</th>
            <th>&nbsp;</th>
        </tr>
    </thead>';

    $sql = 'SELECT * FROM '. TBL_CATEGORIES. '
             WHERE (  cat_org_id  = '. $g_current_organization->getValue('org_id'). '
                   OR cat_org_id IS NULL )
               AND cat_type   = "'.$req_type.'"
             ORDER BY cat_sequence ASC ';
    $cat_result = $g_db->query($sql);
    $write_tbody = false;
    $write_all_orgas = false;

    while($cat_row = $g_db->fetch_array($cat_result))
    {
        if($cat_row['cat_system'] == 1 && $_GET['type'] == 'USF')
        {
            // da bei USF die Kategorie Stammdaten nicht verschoben werden darf, muss hier ein bischen herumgewurschtelt werden
            echo '<tbody id="cat_'.$cat_row['cat_id'].'">';
        }
        elseif($cat_row['cat_org_id'] == 0 && $_GET['type'] == 'USF')
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
                if($_GET['type'] == 'USF')
                {
                    echo '</tbody>';
                }
                echo '<tbody id="cat_list">';
            }
        }
        echo '
        <tr id="row_'. $cat_row['cat_id']. '" class="tableMouseOver">
            <td><a href="'.$g_root_path.'/adm_program/administration/categories/categories_new.php?cat_id='. $cat_row['cat_id']. '&amp;type='.$req_type.'&amp;title='.$title.'">'. $cat_row['cat_name']. '</a></td>
            <td style="text-align: right; width: 45px;"> ';
                if($cat_row['cat_system'] == 0 || $_GET['type'] != "USF")
                {
                    echo '
                    <a class="iconLink" href="javascript:moveCategory(\'up\', '.$cat_row['cat_id'].')"><img
                            src="'. THEME_PATH. '/icons/arrow_up.png" alt="'.$g_l10n->get('CAT_PHR_MOVE_UP', $title).'" title="'.$g_l10n->get('CAT_PHR_MOVE_UP', $title).'" /></a>
                    <a class="iconLink" href="javascript:moveCategory(\'down\', '.$cat_row['cat_id'].')"><img
                            src="'. THEME_PATH. '/icons/arrow_down.png" alt="'.$g_l10n->get('CAT_PHR_MOVE_DOWN', $title).'" title="'.$g_l10n->get('CAT_PHR_MOVE_DOWN', $title).'" /></a>';
                }
            echo '</td>
            <td>';
                if($cat_row['cat_hidden'] == 1)
                {
                    echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/user_key.png" alt="'.$g_l10n->get('SYS_PHR_VISIBLE_TO_USERS', $title).'" title="'.$g_l10n->get('SYS_PHR_VISIBLE_TO_USERS', $title).'" />';
                }
                else
                {
                    echo '&nbsp;';
                }
            echo '</td>
            <td style="text-align: right; width: 90px;">
                <a class="iconLink" href="'.$g_root_path.'/adm_program/administration/categories/categories_new.php?cat_id='. $cat_row['cat_id']. '&amp;type='.$req_type.'&amp;title='.$title.'"><img
                src="'. THEME_PATH. '/icons/edit.png" alt="'.$g_l10n->get('SYS_EDIT').'" title="'.$g_l10n->get('SYS_EDIT').'" /></a>';

                if($cat_row['cat_system'] == 1)
                {
                    echo '<img class="iconLink" src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" />';
                }
                else
                {
                    echo '<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/categories/categories_function.php?cat_id='. $cat_row['cat_id']. '&amp;mode=3&amp;type='.$req_type.'"><img
                        src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('SYS_DELETE').'" title="'.$g_l10n->get('SYS_DELETE').'" /></a>';
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
            src="'.THEME_PATH.'/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>