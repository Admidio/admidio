<?php
/******************************************************************************
 * Ankuendigungen auflisten
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * start     - Angabe, ab welchem Datensatz Ankuendigungen angezeigt werden sollen
 * headline  - Ueberschrift, die ueber den Ankuendigungen steht
 *             (Default) Ankuendigungen
 * id        - Nur eine einzige Annkuendigung anzeigen lassen.
 * date      - Alle Ankuendigungen zu einem Datum werden aufgelistet
 *             Uebergabeformat: YYYYMMDD
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/table_announcement.php');

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_announcements_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}
elseif($g_preferences['enable_announcements_module'] == 2)
{
    // nur eingeloggte Benutzer duerfen auf das Modul zugreifen
    require('../../system/login_valid.php');
}

// lokale Variablen der Uebergabevariablen initialisieren
$req_start    = 0;
$req_headline = $g_l10n->get('ANN_ANNOUNCEMENTS');
$req_id       = 0;
$sql_datum    = '';

// Uebergabevariablen pruefen

if(isset($_GET['start']))
{
    if(is_numeric($_GET['start']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_start = $_GET['start'];
}

if(isset($_GET['headline']))
{
    $req_headline = strStripTags($_GET['headline']);
}

if(isset($_GET['id']))
{
    if(is_numeric($_GET['id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $req_id = $_GET['id'];
}

if(array_key_exists('date', $_GET))
{
    if(is_numeric($_GET['date']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    else
    {
        $sql_datum = substr($_GET['date'],0,4). '-'. substr($_GET['date'],4,2). '-'. substr($_GET['date'],6,2);
    }
}

unset($_SESSION['announcements_request']);
// Navigation faengt hier im Modul an
$_SESSION['navigation']->clear();
$_SESSION['navigation']->addUrl(CURRENT_URL);

// Html-Kopf ausgeben
$g_layout['title']  = $req_headline;
$g_layout['header'] = '
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/ajax.js"></script>
    <script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/delete.js"></script>';

if($g_preferences['enable_rss'] == 1)
{
    $g_layout['header'] .= '<link type="application/rss+xml" rel="alternate" title="'. $g_current_organization->getValue("org_longname"). ' - Ankuendigungen"
        href="'.$g_root_path.'/adm_program/modules/announcements/rss_announcements.php" />';
};

require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '<h1 class="moduleHeadline">'.$req_headline.'</h1>';

// alle Gruppierungen finden, in denen die Orga entweder Mutter oder Tochter ist
$organizations = '';
$arr_ref_orgas = $g_current_organization->getReferenceOrganizations(true, true);

foreach($arr_ref_orgas as $key => $value)
{
	$organizations = $organizations. '"'.$value.'",';
}
$organizations = $organizations. '"'. $g_current_organization->getValue("org_shortname"). '"';

// falls eine id fuer ein bestimmtes Datum uebergeben worden ist...
if($req_id > 0)
{
    $conditions = 'AND ann_id ='. $req_id;
}
//...ansonsten alle fuer die Gruppierung passenden Termine aus der DB holen.
else
{
    // Ankuendigungen an einem Tag suchen
    if(strlen($sql_datum) > 0)
    {
        $conditions = ' AND DATE_FORMAT(ann_timestamp_create, "%Y-%m-%d") = "'.$sql_datum.'"';        
    }
    //...ansonsten alle fuer die Gruppierung passenden Ankuendigungen aus der DB holen.
    else
    {
        $conditions = '';
    }
}

if($req_id == 0)
{
    // Gucken wieviele Datensaetze die Abfrage ermittelt kann...
    $sql = 'SELECT COUNT(1) as count 
              FROM '. TBL_ANNOUNCEMENTS. '
             WHERE (  ann_org_shortname = "'. $g_current_organization->getValue('org_shortname'). '"
                OR (   ann_global   = 1
               AND ann_org_shortname IN ('.$organizations.') ))
                   '.$conditions.'';
    $result = $g_db->query($sql);
    $row    = $g_db->fetch_array($result);
    $num_announcements = $row['count'];
}
else
{
    $num_announcements = 1;
}

// Anzahl Ankuendigungen pro Seite
if($g_preferences['announcements_per_page'] > 0)
{
    $announcements_per_page = $g_preferences['announcements_per_page'];
}
else
{
    $announcements_per_page = $num_announcements;
}

// nun die Ankuendigungen auslesen, die angezeigt werden sollen
$sql = 'SELECT ann.*, 
               cre_surname.usd_value as create_surname, cre_firstname.usd_value as create_firstname,
               cha_surname.usd_value as change_surname, cha_firstname.usd_value as change_firstname
          FROM '. TBL_ANNOUNCEMENTS. ' ann
          LEFT JOIN '. TBL_USER_DATA .' cre_surname
            ON cre_surname.usd_usr_id = ann_usr_id_create
           AND cre_surname.usd_usf_id = '.$g_current_user->getProperty('LAST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cre_firstname
            ON cre_firstname.usd_usr_id = ann_usr_id_create
           AND cre_firstname.usd_usf_id = '.$g_current_user->getProperty('FIRST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_surname
            ON cha_surname.usd_usr_id = ann_usr_id_change
           AND cha_surname.usd_usf_id = '.$g_current_user->getProperty('LAST_NAME', 'usf_id').'
          LEFT JOIN '. TBL_USER_DATA .' cha_firstname
            ON cha_firstname.usd_usr_id = ann_usr_id_change
           AND cha_firstname.usd_usf_id = '.$g_current_user->getProperty('FIRST_NAME', 'usf_id').'
         WHERE (  ann_org_shortname = "'. $g_current_organization->getValue('org_shortname'). '"
            OR (   ann_global   = 1
           AND ann_org_shortname IN ('.$organizations.') ))
               '.$conditions.' 
         ORDER BY ann_timestamp_create DESC
         LIMIT '.$req_start.', '.$announcements_per_page.'';
$announcements_result = $g_db->query($sql);

// Neue Ankuendigung anlegen
if($g_current_user->editAnnouncements())
{
    echo '
    <ul class="iconTextLinkList">
        <li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/modules/announcements/announcements_new.php?headline='.$req_headline.'"><img
                src="'. THEME_PATH. '/icons/add.png" alt="'.$g_l10n->get('SYS_CREATE').'" /></a>
                <a href="'.$g_root_path.'/adm_program/modules/announcements/announcements_new.php?headline='.$req_headline.'">'.$g_l10n->get('SYS_CREATE').'</a>
            </span>
        </li>
    </ul>';        
}

if ($g_db->num_rows($announcements_result) == 0)
{
    // Keine Ankuendigungen gefunden
    if($req_id > 0)
    {
        echo '<p>'.$g_l10n->get('SYS_NO_ENTRY').'</p>';
    }
    else
    {
        echo '<p>'.$g_l10n->get('SYS_NO_ENTRIES').'</p>';
    }
}
else
{
    $announcement = new TableAnnouncement($g_db);

    // Ankuendigungen auflisten
    while($row = $g_db->fetch_array($announcements_result))
    {
        $announcement->clear();
        $announcement->setArray($row);
        echo '
        <div class="boxLayout" id="ann_'.$announcement->getValue("ann_id").'">
            <div class="boxHead">
                <div class="boxHeadLeft">
                    <img src="'. THEME_PATH. '/icons/announcements.png" alt="'. $announcement->getValue("ann_headline"). '" />'.
                    $announcement->getValue("ann_headline"). '
                </div>
                <div class="boxHeadRight">'.$announcement->getValue("ann_timestamp_create", $g_preferences['system_date']).'&nbsp;';
                    
                    // aendern & loeschen duerfen nur User mit den gesetzten Rechten
                    if($g_current_user->editAnnouncements())
                    {
                        if($announcement->editRight() == true)
                        {
                            echo '
                            <a class="iconLink" href="'.$g_root_path.'/adm_program/modules/announcements/announcements_new.php?ann_id='. $announcement->getValue('ann_id'). '&amp;headline='.$req_headline.'"><img 
                                src="'. THEME_PATH. '/icons/edit.png" alt="'.$g_l10n->get('SYS_EDIT').'" title="'.$g_l10n->get('SYS_EDIT').'" /></a>';
                        }

                        // Loeschen darf man nur Ankuendigungen der eigenen Gliedgemeinschaft
                        if($announcement->getValue("ann_org_shortname") == $g_organization)
                        {
                            echo '
                            <a class="iconLink" href="javascript:deleteObject(\'ann\', \'ann_'.$announcement->getValue("ann_id").'\','.$announcement->getValue("ann_id").',\''.$announcement->getValue("ann_headline").'\')"><img 
                                src="'. THEME_PATH. '/icons/delete.png" alt="'.$g_l10n->get('SYS_DELETE').'" title="'.$g_l10n->get('SYS_DELETE').'" /></a>';
                        }    
                    }
                    echo '</div>
            </div>

            <div class="boxBody">'.
                $announcement->getDescription('HTML').'
                <div class="editInformation">'.
                    $g_l10n->get('SYS_CREATED_BY', $row['create_firstname']. ' '. $row['create_surname'],  $announcement->getValue('ann_timestamp_create'));

                    if($announcement->getValue('ann_usr_id_change') > 0)
                    {
                        echo '<br />'.$g_l10n->get('SYS_LAST_EDITED_BY', $row['change_firstname']. ' '. $row['change_surname'],  $announcement->getValue('ann_timestamp_change'));
                    }
                echo '</div>
            </div>
        </div>';
    }  // Ende While-Schleife
}

// Navigation mit Vor- und Zurueck-Buttons
$base_url = $g_root_path.'/adm_program/modules/announcements/announcements.php?headline='.$req_headline;
echo generatePagination($base_url, $num_announcements, $announcements_per_page, $req_start, TRUE);
        
require(THEME_SERVER_PATH. '/overall_footer.php');

?>