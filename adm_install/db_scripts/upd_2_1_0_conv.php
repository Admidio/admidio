<?php
/******************************************************************************
 * Datenkonvertierung fuer die Version 2.1.0
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/list_configuration.php');
require_once(SERVER_PATH. '/adm_program/system/classes/user.php');
require_once(SERVER_PATH. '/adm_program/system/classes/user_fields.php');

$systemmails_texts = array(

    'SYSMAIL_REGISTRATION_USER' => '#Betreff# Anmeldung bei %organization_long_name%
#Inhalt# Hallo %user_first_name%,

deine Anmeldung auf %organization_homepage% wurde bestätigt.

Nun kannst du dich mit deinem Benutzernamen : %user_login_name%
und dem Passwort auf der Homepage einloggen.

Sollten noch Fragen bestehen, schreib eine E-Mail an %webmaster_email% .

Viele Grüße
Die Webmaster',

    'SYSMAIL_REGISTRATION_WEBMASTER' => '#Betreff# Neue Registrierung bei %organization_long_name%
#Inhalt# Es hat sich ein neuer Benutzer auf %organization_homepage% registriert.

Nachname: %user_last_name%
Vorname:  %user_first_name%
E-Mail:   %user_email%


Diese Nachricht wurde automatisch erzeugt.',

    'SYSMAIL_NEW_PASSWORD' => '#Betreff# Logindaten für %organization_homepage%
#Inhalt# Hallo %user_first_name%,

du erhälst deine Logindaten für %organization_homepage% .
Benutzername: %user_login_name%
Passwort: %variable1%

Das Passwort wurde automatisch generiert.
Du solltest es nach deiner Anmeldung auf %organization_homepage% in deinem Profil ändern.

Viele Grüße
Die Webmaster',

    'SYSMAIL_ACTIVATION_LINK' => '#Betreff# Dein angefordertes Passwort
#Inhalt# Hallo %user_first_name%,

du hast ein neues Passwort angefordert!

Hier sind deine Daten:
Benutzername: %user_login_name%
Passwort: %variable1%

Damit du dein neues Passwort benutzen kannst, musst du es über den folgenden Link freischalten:

%variable2%

Das Passwort wurde automatisch generiert.
Du solltest es nach deiner Anmeldung auf %organization_homepage% in deinem Profil ändern.

Viele Grüße
Die Webmaster'
 );

// User-Create fuer alle Anmeldungen fuellen
$sql = 'UPDATE '. TBL_USERS. ' SET usr_usr_id_create = usr_id
         WHERE usr_usr_id_create IS NULL
           AND usr_login_name IS NOT NULL';
$gDb->query($sql);

// Texte fuer Systemmails pflegen
$sql = 'SELECT * FROM '. TBL_ORGANIZATIONS. ' ORDER BY org_id DESC';
$result_orga = $gDb->query($sql);

while($row_orga = $gDb->fetch_array($result_orga))
{
    $sql = "INSERT INTO ". TBL_TEXTS. " (txt_org_id, txt_name, txt_text)
                 VALUES (".$row_orga['org_id'].", 'SYSMAIL_REGISTRATION_USER', '".$systemmails_texts['SYSMAIL_REGISTRATION_USER']."')
                      , (".$row_orga['org_id'].", 'SYSMAIL_REGISTRATION_WEBMASTER', '".$systemmails_texts['SYSMAIL_REGISTRATION_WEBMASTER']."')
                      , (".$row_orga['org_id'].", 'SYSMAIL_NEW_PASSWORD', '".$systemmails_texts['SYSMAIL_NEW_PASSWORD']."')
                      , (".$row_orga['org_id'].", 'SYSMAIL_ACTIVATION_LINK', '".$systemmails_texts['SYSMAIL_ACTIVATION_LINK']."') ";
    $gDb->query($sql);

    // Default-Kategorie fuer Datum eintragen
    $sql = "INSERT INTO ". TBL_CATEGORIES. " (cat_org_id, cat_type, cat_name, cat_hidden, cat_sequence)
                                      VALUES (". $row_orga['org_id']. ", 'DAT', 'Allgemein', 0, 1)
                                           , (". $row_orga['org_id']. ", 'DAT', 'Kurse', 0, 1)
                                           , (". $row_orga['org_id']. ", 'DAT', 'Training', 0, 1)";
    $gDb->query($sql);
    $category_common = $gDb->insert_id();

    // Alle Termine der neuen Kategorie zuordnen
    $sql = "UPDATE ". TBL_DATES. " SET dat_cat_id = ". $category_common. "
             WHERE dat_cat_id is null
               AND dat_org_shortname LIKE '". $row_orga['org_shortname']. "'";
    $gDb->query($sql);

    // bei allen Alben ohne Ersteller, die Erstellungs-ID mit Webmaster befuellen,
    // damit das Feld auf NOT NULL gesetzt werden kann
    $sql = "SELECT min(mem_usr_id) as webmaster_id
              FROM ". TBL_MEMBERS. ", ". TBL_ROLES. ", ". TBL_CATEGORIES. "
             WHERE cat_org_id = ". $row_orga['org_id']. "
               AND rol_cat_id = cat_id
               AND rol_name   = 'Webmaster'
               AND mem_rol_id = rol_id ";
    $result = $gDb->query($sql);
    $row_webmaster = $gDb->fetch_array($result);

    $sql = "UPDATE ". TBL_PHOTOS. " SET pho_usr_id_create = ". $row_webmaster['webmaster_id']. "
             WHERE pho_usr_id_create IS NULL
               AND pho_org_shortname = '". $row_orga['org_shortname']."'";
    $gDb->query($sql);

    $sql = "UPDATE ". TBL_USERS. " SET usr_usr_id_create = ". $row_webmaster['webmaster_id']. "
             WHERE usr_usr_id_create IS NULL ";
    $gDb->query($sql);

    $sql = "SELECT * FROM ". TBL_CATEGORIES. "
             WHERE cat_org_id = ".$row_orga['org_id']. "
               AND cat_type   = 'ROL'";
    $result = $gDb->query($sql);

    $all_cat_ids = array();
    while($row_cat = $gDb->fetch_array($result))
    {
        $all_cat_ids[] = $row_cat['cat_id'];
    }
    $all_cat_str = implode(",", $all_cat_ids);

    // neue Rollenfelder fuellen
    $sql = "UPDATE ". TBL_ROLES. " SET rol_timestamp_create = rol_timestamp_change
                                     , rol_usr_id_create    = ". $row_webmaster['webmaster_id']. "
         WHERE rol_timestamp_change IS NOT NULL
           AND rol_usr_id_change IS NOT NULL
           AND rol_cat_id IN (".$all_cat_str.")";
    $gDb->query($sql);

    $sql = "UPDATE ". TBL_ROLES. " SET rol_timestamp_create = '".DATETIME_NOW."'
                                     , rol_usr_id_create    = ". $row_webmaster['webmaster_id']. "
         WHERE rol_timestamp_create IS NULL
           AND rol_cat_id IN (".$all_cat_str.")";
    $gDb->query($sql);

	// create object with current user field structure
	$gUserFields = new UserFields($db, $gCurrentOrganization);

    $gCurrentUser = new User($gDb, $gUserFields, $row_webmaster['webmaster_id']);
    $gCurrentOrganization->readData($row_orga['org_id']);

    // Default-Listen-Konfigurationen anlegen
    $address_list = new ListConfiguration($gDb);
    $address_list->setValue('lst_name', 'Adressliste');
    $address_list->setValue('lst_global', 1);
    $address_list->setValue('lst_default', 1);
    $address_list->addColumn(1, $gCurrentUser->getProperty('Nachname', 'usf_id'), 'ASC');
    $address_list->addColumn(2, $gCurrentUser->getProperty('Vorname', 'usf_id'), 'ASC');
    $address_list->addColumn(3, $gCurrentUser->getProperty('Geburtstag', 'usf_id'));
    $address_list->addColumn(4, $gCurrentUser->getProperty('Adresse', 'usf_id'));
    $address_list->addColumn(5, $gCurrentUser->getProperty('PLZ', 'usf_id'));
    $address_list->addColumn(6, $gCurrentUser->getProperty('Ort', 'usf_id'));
    $address_list->save();

    $phone_list = new ListConfiguration($gDb);
    $phone_list->setValue('lst_name', 'Telefonliste');
    $phone_list->setValue('lst_global', 1);
    $phone_list->addColumn(1, $gCurrentUser->getProperty('Nachname', 'usf_id'), 'ASC');
    $phone_list->addColumn(2, $gCurrentUser->getProperty('Vorname', 'usf_id'), 'ASC');
    $phone_list->addColumn(3, $gCurrentUser->getProperty('Telefon', 'usf_id'));
    $phone_list->addColumn(4, $gCurrentUser->getProperty('Handy', 'usf_id'));
    $phone_list->addColumn(5, $gCurrentUser->getProperty('E-Mail', 'usf_id'));
    $phone_list->addColumn(6, $gCurrentUser->getProperty('Fax', 'usf_id'));
    $phone_list->save();

    $contact_list = new ListConfiguration($gDb);
    $contact_list->setValue('lst_name', 'Kontaktdaten');
    $contact_list->setValue('lst_global', 1);
    $contact_list->addColumn(1, $gCurrentUser->getProperty('Nachname', 'usf_id'), 'ASC');
    $contact_list->addColumn(2, $gCurrentUser->getProperty('Vorname', 'usf_id'), 'ASC');
    $contact_list->addColumn(3, $gCurrentUser->getProperty('Geburtstag', 'usf_id'));
    $contact_list->addColumn(4, $gCurrentUser->getProperty('Adresse', 'usf_id'));
    $contact_list->addColumn(5, $gCurrentUser->getProperty('PLZ', 'usf_id'));
    $contact_list->addColumn(6, $gCurrentUser->getProperty('Ort', 'usf_id'));
    $contact_list->addColumn(7, $gCurrentUser->getProperty('Telefon', 'usf_id'));
    $contact_list->addColumn(8, $gCurrentUser->getProperty('Handy', 'usf_id'));
    $contact_list->addColumn(9, $gCurrentUser->getProperty('E-Mail', 'usf_id'));
    $contact_list->save();

    $former_list = new ListConfiguration($gDb);
    $former_list->setValue('lst_name', 'Mitgliedschaft');
    $former_list->setValue('lst_global', 1);
    $former_list->addColumn(1, $gCurrentUser->getProperty('Nachname', 'usf_id'));
    $former_list->addColumn(2, $gCurrentUser->getProperty('Vorname', 'usf_id'));
    $former_list->addColumn(3, $gCurrentUser->getProperty('Geburtstag', 'usf_id'));
    $former_list->addColumn(4, 'mem_begin');
    $former_list->addColumn(5, 'mem_end', 'DESC');
    $former_list->save();

    // Beta-Flag für Datenbank-Versionsnummer schreiben
    $sql = 'INSERT INTO '. TBL_PREFERENCES. ' (prf_org_id, prf_name, prf_value)
            VALUES ('.$row_orga['org_id'].', "db_version_beta", "1") ';
    $gDb->query($sql);
}

$sql = "UPDATE ". TBL_PHOTOS. " SET pho_timestamp_create = '".DATETIME_NOW."'
         WHERE pho_timestamp_create IS NULL ";
$gDb->query($sql);

// neue Userfelder fuellen
$sql = "UPDATE ". TBL_USERS. " SET usr_timestamp_create = usr_timestamp_change
         WHERE usr_timestamp_change IS NOT NULL ";
$gDb->query($sql);

$sql = "UPDATE ". TBL_USERS. " SET usr_timestamp_create = '".DATETIME_NOW."'
         WHERE usr_timestamp_create IS NULL ";
$gDb->query($sql);


// Datenstruktur nach Update anpassen
$sql = "ALTER TABLE ". TBL_USERS. " MODIFY COLUMN usr_timestamp_create datetime NOT NULL ";
$gDb->query($sql);

$sql = "ALTER TABLE ". TBL_ROLES. " MODIFY COLUMN rol_timestamp_create datetime NOT NULL ";
$gDb->query($sql);

$sql = "ALTER TABLE ". TBL_PHOTOS. " MODIFY COLUMN pho_timestamp_create datetime NOT NULL ";
$gDb->query($sql);

$sql = "ALTER TABLE ". TBL_DATES. " MODIFY COLUMN dat_cat_id int(11) unsigned NOT NULL ";
$gDb->query($sql);

$sql = "ALTER TABLE ". TBL_DATES. " DROP COLUMN dat_org_shortname ";
$gDb->query($sql);

//Neu Mailrechte installieren
//1. neue Spalten anlegen
	//passiert schon in upd_2_1_0_db.sql

//2.Webmaster mit globalem Mailsenderecht ausstatten
$sql = "UPDATE ". TBL_ROLES. " SET rol_mail_to_all = '1'
        WHERE rol_name = 'Webmaster'";
$gDb->query($sql);

//3. alte Mailrechte Übertragen
//3.1 eingeloggte konnten bisher eine Mail an diese Rolle schreiben
$sql = "UPDATE ". TBL_ROLES. " SET rol_mail_this_role = '2'
        WHERE rol_mail_login = 1";
$gDb->query($sql);

//3.2 ausgeloggte konnten bisher eine Mail an diese Rolle schreiben
$sql = "UPDATE ". TBL_ROLES. " SET rol_mail_this_role = '3'
        WHERE rol_mail_logout = 1";
$gDb->query($sql);

//4. Überflüssige Spalten löschen
$sql = "ALTER TABLE ". TBL_ROLES. "
		DROP rol_mail_login,
		DROP rol_mail_logout";
$gDb->query($sql);


//Neues Inventarmodulrecht installieren
//1. neue Spalte anlegen
	//passiert schon in upd_2_1_0_db.sql

//2.Webmaster mit Inventarverwaltungsrecht ausstatten
$sql = "UPDATE ". TBL_ROLES. " SET rol_inventory = '1'
        WHERE rol_name = 'Webmaster'";
$gDb->query($sql);



//Fototext updaten
$sql = "SELECT * FROM ". TBL_ORGANIZATIONS;
$result_orga = $gDb->query($sql);
while($row_orga = $gDb->fetch_array($result_orga))
{
	//erstmal gucken ob die Funktion bisher aktiviert war
	$sql = "SELECT prf_value
              FROM ". TBL_PREFERENCES. "
             WHERE prf_org_id = ". $row_orga['org_id']. "
               AND prf_name   = 'photo_image_text' ";
    $result = $gDb->query($sql);
    $row_photo_image_text = $gDb->fetch_array($result);

	//wenn ja
	if($row_photo_image_text['prf_value'] == 1)
	{
		$sql = "UPDATE ". TBL_PREFERENCES. "
				SET prf_value = '© ".$row_orga['org_homepage']."'
       			WHERE prf_org_id = ". $row_orga['org_id']. "
               	AND prf_name   = 'photo_image_text' ";
	}
	//wenn nicht
	else
	{
		$sql = "UPDATE ". TBL_PREFERENCES. "
				SET prf_value = ''
         		WHERE prf_org_id = ". $row_orga['org_id']. "
               	AND prf_name   = 'photo_image_text' ";
	}
	$gDb->query($sql);
}

?>