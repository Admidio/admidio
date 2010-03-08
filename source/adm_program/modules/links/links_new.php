<?php
/******************************************************************************
 * Links anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Daniel Dieckelmann
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * lnk_id        - ID der Ankuendigung, die bearbeitet werden soll
 * headline      - Ueberschrift, die ueber den Links steht
 *                 (Default) Links
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/table_weblink.php');

if ($g_preferences['enable_bbcode'] == 1)
{
    require_once('../../system/bbcode.php');
}


// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_weblinks_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
}


// Ist ueberhaupt das Recht vorhanden?
if (!$g_current_user->editWeblinksRight())
{
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// Uebergabevariablen pruefen
if (array_key_exists('lnk_id', $_GET))
{
    if (is_numeric($_GET['lnk_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
}
else
{
    $_GET['lnk_id'] = 0;
}

if (array_key_exists('headline', $_GET))
{
    $_GET['headline'] = strStripTags($_GET['headline']);
}
else
{
    $_GET['headline'] = 'Links';
}

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Weblinkobjekt anlegen
$link = new TableWeblink($g_db, $_GET['lnk_id']);

if(isset($_SESSION['links_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte auslesen
    foreach($_SESSION['links_request'] as $key => $value)
    {
        if(strpos($key, 'lnk_') == 0)
        {
            $link->setValue($key, stripslashes($value));
        }
    }
    unset($_SESSION['links_request']);
}

// Html-Kopf ausgeben
if($_GET['lnk_id'] > 0)
{
    $g_layout['title'] = $_GET['headline']. ' bearbeiten';
}
else
{
    $g_layout['title'] = $_GET['headline']. ' anlegen';
}

//Script für BBCode laden
$javascript = "";
if ($g_preferences['enable_bbcode'] == 1)
{
    $javascript = getBBcodeJS('lnk_description');
}

$g_layout['header'] = $javascript. '
	<script type="text/javascript"><!--
    	$(document).ready(function() 
		{
            $("#lnk_name").focus();
	 	}); 
	//--></script>';

require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
if($_GET['lnk_id'] > 0)
{
    $new_mode = '3';
}
else
{
    $new_mode = '1';
}

echo '
<form action="'.$g_root_path.'/adm_program/modules/links/links_function.php?lnk_id='. $_GET['lnk_id']. '&amp;headline='. $_GET['headline']. '&amp;mode='.$new_mode.'" method="post">
<div class="formLayout" id="edit_links_form">
    <div class="formHead">'. $g_layout['title']. '</div>
    <div class="formBody">
        <ul class="formFieldList">
            <li>
                <dl>
                    <dt><label for="lnk_name">Linkname:</label></dt>
                    <dd>
                        <input type="text" id="lnk_name" name="lnk_name" tabindex="1" style="width: 350px;" maxlength="250" value="'. $link->getValue('lnk_name'). '" />
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="lnk_url">Linkadresse:</label></dt>
                    <dd>
                        <input type="text" id="lnk_url" name="lnk_url" tabindex="2" style="width: 350px;" maxlength="250" value="'. $link->getValue('lnk_url'). '" />
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>
            <li>
                <dl>
                    <dt><label for="lnk_cat_id">Kategorie:</label></dt>
                    <dd>
                        <select id="lnk_cat_id" name="lnk_cat_id" size="1" tabindex="3">
                            <option value=" "';
                                if($link->getValue('lnk_cat_id') == 0)
                                {
                                    echo ' selected="selected"';
                                }
                                echo '>- Bitte wählen -</option>';

                            $sql = 'SELECT * FROM '. TBL_CATEGORIES. '
                                     WHERE cat_org_id = '. $g_current_organization->getValue('org_id'). '
                                       AND cat_type   = "LNK"
                                     ORDER BY cat_sequence ASC ';
                            $result = $g_db->query($sql);

                            while($row = $g_db->fetch_object($result))
                            {
                                echo '<option value="'.$row->cat_id.'"';
                                    if($link->getValue('lnk_cat_id') == $row->cat_id)
                                    {
                                        echo ' selected="selected" ';
                                    }
                                echo '>'.$row->cat_name.'</option>';
                            }
                        echo '</select>
                        <span class="mandatoryFieldMarker" title="Pflichtfeld">*</span>
                    </dd>
                </dl>
            </li>
            ';
         if ($g_preferences['enable_bbcode'] == 1)
         {
            printBBcodeIcons();
         }
         echo '
            <li>
                <dl>
                    <dt><label for="lnk_description">Text:</label>';
                        if($g_preferences['enable_bbcode'] == 1)
                        {
                            printEmoticons();
                        }
                    echo '</dt>
                    <dd>
                        <textarea id="lnk_description" name="lnk_description" tabindex="4" style="width: 350px;" rows="10" cols="40">'. $link->getValue('lnk_description'). '</textarea>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />';

        if($link->getValue('lnk_usr_id_create') > 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($g_db, $link->getValue('lnk_usr_id_create'));
                echo $g_l10n->get('SYS_PHR_CREATED_BY', $user_create->getValue('Vorname'). ' '. $user_create->getValue('Nachname'), $link->getValue('lnk_timestamp_create'));

                if($link->getValue('lnk_usr_id_change') > 0)
                {
                    $user_change = new User($g_db, $link->getValue('lnk_usr_id_change'));
                    echo '<br />'.$g_l10n->get('SYS_PHR_LAST_EDITED_BY', $user_change->getValue('Vorname'). ' '. $user_change->getValue('Nachname'), $link->getValue('lnk_timestamp_change'));
                }
            echo '</div>';
        }

        echo '<div class="formSubmit">
            <button name="speichern" type="submit" value="speichern" tabindex="5"><img src="'. THEME_PATH. '/icons/disk.png" alt="Speichern" />&nbsp;Speichern</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img src="'. THEME_PATH. '/icons/back.png" alt="Zurück" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">Zurück</a>
        </span>
    </li>
</ul>';

require(THEME_SERVER_PATH. '/overall_footer.php');

?>