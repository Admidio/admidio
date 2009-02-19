<?php
/******************************************************************************
 * Uebersicht und Pflege aller organisationsspezifischen Profilfelder
 *
 * Copyright    : (c) 2004 - 2008 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 ****************************************************************************/
 
require('../../system/common.php');
require('../../system/login_valid.php');

// nur berechtigte User duerfen die Profilfelder bearbeiten
if (!$g_current_user->isWebmaster())
{
    $g_message->show('norights');
}

$_SESSION['navigation']->addUrl(CURRENT_URL);
unset($_SESSION['fields_request']);

// zusaetzliche Daten fuer den Html-Kopf setzen
$g_layout['title']  = 'Profilfelder';
$g_layout['header'] = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/ajax.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/delete.js"></script>
    
    <script type="text/javascript"><!--
        function moveCategory(direction, usfID)
        {
            var actRow = document.getElementById("row_usf_" + usfID);
            var childs = actRow.parentNode.childNodes;
            var prevNode    = null;
            var nextNode    = null;
            var actRowCount = 0;
            var actSequence = 0;
            var secondUsfId = 0;
            var secondSequence = 0;
            var this_orga   = 0;
            
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
                    
                    if(childs[i].id == "row_usf_" + usfID)
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
                    secondUsfId = prevNode.getAttribute("id").substr(4);
                    secondSequence = actSequence - 1;
                }
            }
            else
            {
                if(nextNode != null)
                {
                    actRow.parentNode.insertBefore(nextNode, actRow);
                    secondUsfId = nextNode.getAttribute("id").substr(4);
                    secondSequence = actSequence + 1;
                }
            }

            if(secondSequence > 0)
            {
                // Nun erst mal die neue Position von dem gewaehlten Feld aktualisieren
                resObject.open("GET", gRootPath + "/adm_program/administration/members/fields_function.php?usf_id=" + usfID + "&mode=4&sequence=" + secondSequence, true);
                resObject.send(null);
                
                // jetzt die neue Position von jeweils verschobenen Feld aktualisieren
                resObject.open("GET", gRootPath + "/adm_program/administration/members/fields_function.php?usf_id=" + secondUsfId + "&mode=4&sequence=" + actSequence, true);
                resObject.send(null);
            }
        }
    --></script>';
    
// Html-Kopf ausgeben
require(THEME_SERVER_PATH. '/overall_header.php');

echo '
<h1 class="moduleHeadline">Profilfelder</h1>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/members/fields_new.php"><img 
            src="'. THEME_PATH. '/icons/add.png" alt="Profilfeld anlegen" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/members/fields_new.php">Profilfeld anlegen</a>
        </span>
    </li>
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/administration/roles/categories.php?type=USF"><img
            src="'. THEME_PATH. '/icons/application_double.png" alt="Kategorien pflegen" /></a>
            <a href="'.$g_root_path.'/adm_program/administration/roles/categories.php?type=USF">Kategorien pflegen</a>
        </span>
    </li>
</ul>';

$sql = 'SELECT * FROM '. TBL_CATEGORIES. ', '. TBL_USER_FIELDS. '
         WHERE cat_type   = "USF"
           AND usf_cat_id = cat_id
           AND (  cat_org_id = '. $g_current_organization->getValue('org_id'). '
               OR cat_org_id IS NULL )
         ORDER BY cat_sequence ASC, usf_sequence ASC ';
$result = $g_db->query($sql);

$js_drag_drop = '';

echo '
<table class="tableList" cellspacing="0">
    <thead>
        <tr>
            <th>Feld<a class="thickbox" href="'. $g_root_path. '/adm_program/system/msg_window.php?err_code=field&amp;window=true&amp;KeepThis=true&amp;TB_iframe=true&amp;height=250&amp;width=580"><img 
                    onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?err_code=field\',this)" onmouseout="ajax_hideTooltip()"
                    class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title="" /></a></th>
            <th>&nbsp;</th>
            <th>Beschreibung</th>
            <th><img class="iconInformation" src="'. THEME_PATH. '/icons/eye.png" alt="Feld für alle Benutzer bzw. nur berechtigte Nutzer sichtbar" title="Feld für alle Benutzer bzw. nur berechtigte Nutzer sichtbar" /></th>
            <th><img class="iconInformation" src="'. THEME_PATH. '/icons/textfield_key.png" alt="Feld nur für berechtigte Benutzer (Recht: Alle Benutzer bearbeiten) editierbar" title="Feld nur für berechtigte Benutzer (Recht: Alle Benutzer bearbeiten) editierbar" /></th>
            <th><img class="iconInformation" src="'. THEME_PATH. '/icons/asterisk_yellow.png" alt="Pflichtfeld, muss vom Benutzer gefüllt werden" title="Pflichtfeld, muss vom Benutzer gefüllt werden" /></th>
            <th>Datentyp</th>
            <th style="width: 40px;">&nbsp;</th>
        </tr>
    </thead>';
    
    $cat_id = 0;

    if($g_db->num_rows($result) > 0)
    {
        while($row = $g_db->fetch_array($result))
        {
            if($cat_id != $row['cat_id'])
            {
                if($cat_id > 0)
                {
                    echo '</tbody>';
                }
                $block_id = 'cat_'.$row['cat_id'];
                echo '<tbody>
                    <tr>
                        <td class="tableSubHeader" colspan="8">
                            <a class="iconShowHide" href="javascript:showHideBlock(\''.$block_id.'\')"><img 
                            id="img_'.$block_id.'" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="ausblenden" /></a>'.$row['cat_name'].'
                        </td>
                    </tr>
                </tbody>
                <tbody id="'.$block_id.'">';
                $cat_id = $row['cat_id'];
            }           
            echo '
            <tr id="row_usf_'.$row['usf_id'].'" class="tableMouseOver">
                <td><a href="'.$g_root_path.'/adm_program/administration/members/fields_new.php?usf_id='.$row['usf_id'].'">'.$row['usf_name'].'</a></td>
                <td style="text-align: right; width: 45px;">
                    <a class="iconLink" href="javascript:moveCategory(\'up\', '.$row['usf_id'].')"><img
                            src="'. THEME_PATH. '/icons/arrow_up.png" alt="Profilfeld nach oben schieben" title="Profilfeld nach oben schieben" /></a>
                    <a class="iconLink" href="javascript:moveCategory(\'down\', '.$row['usf_id'].')"><img
                            src="'. THEME_PATH. '/icons/arrow_down.png" alt="Profilfeld nach unten schieben" title="Profilfeld nach unten schieben" /></a>
                </td>
                <td>'.$row['usf_description'].'</td>
                <td>';
                    if($row['usf_hidden'] == 1)
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/eye_gray.png" alt="Feld nur für berechtigte Benutzer (eigenes Profil &amp; Rollenrecht) sichtbar" title="Feld nur für berechtigte Benutzer (eigenes Profil &amp; Rollenrecht) sichtbar" />';
                    }
                    else
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/eye.png" alt="Feld für alle Benutzer sichtbar" title="Feld für alle Benutzer sichtbar" />';
                    }
                echo '</td>
                <td>';
                    if($row['usf_disabled'] == 1)
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/textfield_key.png" alt="Feld nur durch berechtigte Benutzer bearbeitet werden (Recht: Alle Benutzer bearbeiten)." title="Feld nur für berechtigte Benutzer bearbeitenbar (Recht: Alle Benutzer bearbeiten)" />';
                    }
                    else
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/textfield.png" alt="Feld kann durch Benutzer selbst bearbeitet werden." title="Feld kann durch Benutzer selbst bearbeitet werden." />';
                    }
                echo '</td>
                <td>';
                    if($row['usf_mandatory'] == 1)
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/asterisk_yellow.png" alt="Pflichtfeld, muss vom Benutzer gefüllt werden" title="Pflichtfeld, muss vom Benutzer gefüllt werden" />';
                    }
                    else
                    {
                        echo '<img class="iconInformation" src="'. THEME_PATH. '/icons/asterisk_gray.png" alt="Feld muss nicht zwingend vom Benutzer gefüllt werden" title="Feld muss nicht zwingend vom Benutzer gefüllt werden" />';
                    }
                echo '</td>
                <td>';
                    if($row['usf_type'] == 'DATE')
                    {
                        echo 'Datum';
                    }
                    elseif($row['usf_type'] == 'EMAIL')
                    {
                        echo 'E-Mail';
                    }
                    elseif($row['usf_type'] == 'CHECKBOX')
                    {
                        echo 'Ja / Nein';
                    }
                    elseif($row['usf_type'] == 'TEXT')
                    {
                        echo 'Text (50)';
                    }
                    elseif($row['usf_type'] == 'TEXT_BIG')
                    {
                        echo 'Text (255)';
                    }
                    elseif($row['usf_type'] == 'URL')
                    {
                        echo 'URL';
                    }
                    elseif($row['usf_type'] == 'NUMERIC')
                    {
                        echo 'Zahl';
                    }
                echo '</td>
                <td style="text-align: right; width: 45px;">
                    <a class="iconLink" href="'.$g_root_path.'/adm_program/administration/members/fields_new.php?usf_id='.$row['usf_id'].'"><img 
                        src="'. THEME_PATH. '/icons/edit.png" alt="Bearbeiten" title="Bearbeiten" /></a>';
                    if($row['usf_system'] == 1)
                    {
                        echo '
                        <span class="iconLink">
                            <img src="'. THEME_PATH. '/icons/dummy.png" alt="dummy" />
                        </span>';
                    }
                    else
                    {
                        echo '
                        <a class="iconLink" href="javascript:deleteObject(\'usf\', \'row_usf_'.$row['usf_id'].'\','.$row['usf_id'].',\''.$row['usf_name'].'\')"><img 
                            src="'. THEME_PATH. '/icons/delete.png" alt="Löschen" title="Löschen" /></a>';
                    }
                echo '</td>
            </tr>';
        }
        echo '</tbody>';
    }
    else
    {
        echo '<tr>
            <td colspan="5" style="text-align: center;">
                <p>Es wurden noch keine organisationsspezifischen Profilfelder angelegt !</p>
            </td>
        </tr>';
    }
echo '</table>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img 
            src="'. THEME_PATH. '/icons/back.png" alt="Zurück" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">Zurück</a>
        </span>
    </li>
</ul>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>