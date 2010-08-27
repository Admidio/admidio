<?php
/******************************************************************************
 * Profil/Registrierung wird angelegt bzw. gespeichert
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * user_id :  ID des Benutzers, dessen Profil bearbeitet werden soll
 * new_user : 0 - (Default) vorhandenen User bearbeiten
 *            1 - Neuen Benutzer hinzufuegen.
 *            2 - Registrierung entgegennehmen
 *            3 - Registrierung zuordnen/akzeptieren
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/system_mail.php');
require_once('../../system/classes/table_members.php');

// im ausgeloggten Zustand koennen nur Registrierungen gespeichert werden
if($g_valid_login == false)
{
    $_GET['new_user'] = 2;
}

// Uebergabevariablen pruefen

$new_user = 0;
$usr_id   = 0;

if(array_key_exists('new_user', $_GET) && is_numeric($_GET['new_user']))
{
    $new_user = $_GET['new_user'];
}

// User-ID nur uebernehmen, wenn ein vorhandener Benutzer auch bearbeitet wird
if(isset($_GET['user_id']) && ($new_user == 0 || $new_user == 3))
{
    if(is_numeric($_GET['user_id']) == false)
    {
        $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
    }
    $usr_id  = $_GET['user_id'];
}

// pruefen, ob Modul aufgerufen werden darf
switch($new_user)
{
    case 0:
        // prueft, ob der User die notwendigen Rechte hat, das entsprechende Profil zu aendern
        if($g_current_user->editProfile($usr_id) == false)
        {
            $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
        }
        break;

    case 1:
        // prueft, ob der User die notwendigen Rechte hat, neue User anzulegen
        if($g_current_user->editUsers() == false)
        {
            $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
        }
        break;

    case 2:
    case 3:
        // Registrierung deaktiviert, also auch diesen Modus sperren
        if($g_preferences['registration_mode'] == 0)
        {
            $g_message->show($g_l10n->get('SYS_PHR_MODULE_DISABLED'));
        }
        break;
}

$_SESSION['profile_request'] = $_REQUEST;

if(!isset($_POST['usr_login_name']))
{
    $_POST['usr_login_name'] = '';
}

// User auslesen
$user = new User($g_db, $usr_id);


/*------------------------------------------------------------*/
// Feldinhalte pruefen der User-Klasse zuordnen
/*------------------------------------------------------------*/

// bei Registrierung muss Loginname und Pw geprueft werden
if($new_user == 2)
{
    if(strlen($_POST['usr_login_name']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', 'Benutzername'));
    }

    // Passwort sollte laenger als 6 Zeichen sein
    if(strlen($_POST['usr_password']) < 6)
    {
        $g_message->show($g_l10n->get('PRO_PHR_PASSWORD_LENGTH'));
    }

    // beide Passwortfelder muessen identisch sein
    if ($_POST['usr_password'] != $_POST['password2'])
    {
        $g_message->show($g_l10n->get('PRO_PHR_PASSWORDS_NOT_EQUAL'));
    }

    if(strlen($_POST['usr_password']) == 0)
    {
        $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', 'Passwort'));
    }
}

// nun alle Profilfelder pruefen
foreach($user->userFieldData as $field)
{
    $post_id = 'usf-'. $field->getValue('usf_id');    
    
    if(isset($_POST[$post_id])) 
    {
        // Pflichtfelder muessen gefuellt sein
        // E-Mail bei Restrierung immer !!!
        if(($field->getValue('usf_mandatory') == 1 && strlen($_POST[$post_id]) == 0)
        || ($new_user == 2 && $field->getValue('usf_name_intern') == 'EMAIL' && strlen($_POST[$post_id]) == 0))
        {
            $g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $field->getValue('usf_name')));
        }
        
        if(strlen($_POST[$post_id]) > 0)
        {
            // Pruefungen fuer die entsprechenden Datentypen
            if($field->getValue('usf_type') == 'CHECKBOX')
            {
                // Checkbox darf nur 1 oder 0 haben
                if($_POST[$post_id] != 0 && $_POST[$post_id] != 1)
                {
                    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
                }
            }
            elseif($field->getValue('usf_type') == 'DATE')
            {
                // Datum muss gueltig sein und formatiert werden
                $date = new DateTimeExtended($_POST[$post_id], $g_preferences['system_date'], 'date');
                if($date->valid() == false)
                {
                    $g_message->show($g_l10n->get('SYS_PHR_DATE_INVALID', $field->getValue('usf_name'), $g_preferences['system_date']));
                }
                $_POST[$post_id] = $date->format('Y-m-d');
            }
            elseif($field->getValue('usf_type') == 'EMAIL')
            {
                // Pruefung auf gueltige E-Mail-Adresse
                if(!isValidEmailAddress($_POST[$post_id]))
                {
                    $g_message->show($g_l10n->get('SYS_PHR_EMAIL_INVALID'));
                }        
            }
            elseif($field->getValue('usf_type') == 'NUMERIC')
            {
                // Zahl muss numerisch sein
                if(is_numeric(strtr($_POST[$post_id], ',.', '00')) == false)
                {
                    $g_message->show($g_l10n->get('PRO_PHR_FIELD_NUMERIC', $field->getValue('usf_name')));
                }
            }
        }

        $user->setValue($field->getValue('usf_name_intern'), $_POST[$post_id]);
    }
    else
    {
        // Checkboxen uebergeben bei 0 keinen Wert, deshalb diesen hier setzen
        if($field->getValue('usf_type') == 'CHECKBOX')
        {
            $user->setValue($field->getValue('usf_name_intern'), '0');
        }
        elseif($field->getValue('usf_mandatory') == 1)
        {
    		$g_message->show($g_l10n->get('SYS_PHR_FIELD_EMPTY', $field->getValue('usf_name')));
        }
    }
}

$login_name_changed = false;
$forum_old_username = '';

if($g_current_user->isWebmaster() || $new_user > 0)
{
    // Loginname darf nur vom Webmaster bzw. bei Neuanlage geaendert werden    
    if($_POST['usr_login_name'] != $user->getValue('usr_login_name'))
    {
        if(strlen($_POST['usr_login_name']) > 0)
        {
            // pruefen, ob der Benutzername bereits vergeben ist
            $sql = 'SELECT usr_id FROM '. TBL_USERS. '
                     WHERE usr_login_name LIKE "'. $_POST['usr_login_name']. '"';
            $g_db->query($sql);

            if($g_db->num_rows() > 0)
            {
                $row = $g_db->fetch_array();

                if(strcmp($row['usr_id'], $usr_id) != 0)
                {
                    $g_message->show($g_l10n->get('PRO_PHR_LOGIN_NAME_EXIST'));
                }
            }

            // pruefen, ob der Benutzername bereits im Forum vergeben ist, 
            // Benutzernamenswechesel und diese Dinge
            if($g_preferences['enable_forum_interface'])
            {
                // pruefen, ob der Benutzername bereits im Forum vergeben ist
                if($g_forum->userExists($_POST['usr_login_name']))
                {
                    $g_message->show($g_l10n->get('SYS_PHR_FORUM_USER_EXIST'));
                }
                
                // bisherigen Loginnamen merken, damit dieser spaeter im Forum geaendert werden kann
                $forum_old_username = '';
                if(strlen($user->getValue('usr_login_name')) > 0)
                {
                    $forum_old_username = $user->getValue('usr_login_name');
                }
            }
        }

        $login_name_changed = true;
        $user->setValue('usr_login_name', $_POST['usr_login_name']);
    }    
}

// falls Registrierung, dann die entsprechenden Felder noch besetzen
if($new_user == 2)
{
    $user->setValue('usr_valid', 0);
    $user->setValue('usr_reg_org_shortname', $g_current_organization->getValue('org_shortname'));
    $user->setValue('usr_password', $_POST['usr_password']);
}


// Falls der User sich registrieren wollte, aber ein Captcha geschaltet ist,
// muss natuerlich der Code ueberprueft werden
if ($new_user == 2 && $g_preferences['enable_registration_captcha'] == 1)
{
    if ( !isset($_SESSION['captchacode']) || admStrToUpper($_SESSION['captchacode']) != admStrToUpper($_POST['captcha']) )
    {
		if($g_preferences['captcha_type']=='pic') {$g_message->show($g_l10n->get('SYS_CAPTCHA_CODE_INVALID'));}
		else if($g_preferences['captcha_type']=='calc') {$g_message->show($g_l10n->get('SYS_CAPTCHA_CALC_CODE_INVALID'));}
    }
}

/*------------------------------------------------------------*/
// Benutzerdaten in Datenbank schreiben
/*------------------------------------------------------------*/

if($user->getValue('usr_id') == 0)
{
    // der User wird gerade angelegt und die ID kann erst danach in das Create-Feld gesetzt werden
    $user->save();
    if($new_user == 1)
    {
        $user->setValue('usr_usr_id_create', $g_current_user->getValue('usr_id'));
    }
    else
    {
        $user->setValue('usr_usr_id_create', $user->getValue('usr_id'));
    }
}

// Aenderungen speichern
$ret_code = $user->save();

//Falls Registrierung nur fuer Terminanmeldung 
if(isset($_SESSION['login_rol_id']) && $_SESSION['login_rol_id'] > 0)
{
    $members = new TableMembers($g_db);
    $members->setValue('mem_rol_id', $_SESSION['login_rol_id']);
    $members->setValue('mem_usr_id', $user->getValue('usr_id'));
    $members->save();
    //User sofort freischalten
    $user->setValue('usr_valid',1);
    $user->save();
}
// wurde der Loginname vergeben oder geaendert, so muss ein Forumaccount gepflegt werden
// bei einer Bestaetigung der Registrierung muss der Account aktiviert werden
if($g_preferences['enable_forum_interface'] && ($login_name_changed || $new_user == 3))
{
    $set_admin = false;
    if($g_preferences['forum_set_admin'] == 1 && $user->isWebmaster())
    {
        $set_admin = true;
    }
    $g_forum->userSave($user->getValue('usr_login_name'), $user->getValue('usr_password'), $user->getValue('EMAIL'), $forum_old_username, $new_user, $set_admin);
}

// wenn Daten des eingeloggten Users geaendert werden, dann Session-Variablen aktualisieren
if($user->getValue('usr_id') == $g_current_user->getValue('usr_id'))
{
    $g_current_user = $user;
}

unset($_SESSION['profile_request']);
$_SESSION['navigation']->deleteLastUrl();

/*------------------------------------------------------------*/
// je nach Aufrufmodus auf die richtige Seite weiterleiten
/*------------------------------------------------------------*/
if($new_user == 2)
{
    /*------------------------------------------------------------*/
    // Registrierung eines neuen Benutzers
    // -> E-Mail an alle Webmaster schreiben
    /*------------------------------------------------------------*/
    // nur ausfuehren, wenn E-Mails auch unterstuetzt werden und die Webmasterbenachrichtung aktiviert ist
    if($g_preferences['enable_system_mails'] == 1 && $g_preferences['enable_registration_admin_mail'] == 1)
    {
        $sql = 'SELECT DISTINCT first_name.usd_value as first_name, last_name.usd_value as last_name, email.usd_value as email
                  FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_MEMBERS. ', '. TBL_USERS. '
                 RIGHT JOIN '. TBL_USER_DATA. ' email
                    ON email.usd_usr_id = usr_id
                   AND email.usd_usf_id = '. $g_current_user->getProperty('EMAIL', 'usf_id'). '
                   AND LENGTH(email.usd_value) > 0
                  LEFT JOIN '. TBL_USER_DATA. ' first_name
                    ON first_name.usd_usr_id = usr_id
                   AND first_name.usd_usf_id = '. $g_current_user->getProperty('FIRST_NAME', 'usf_id'). '
                  LEFT JOIN '. TBL_USER_DATA. ' last_name
                    ON last_name.usd_usr_id = usr_id
                   AND last_name.usd_usf_id = '. $g_current_user->getProperty('LAST_NAME', 'usf_id'). '
                 WHERE rol_approve_users = 1
                   AND rol_cat_id        = cat_id
                   AND cat_org_id        = '. $g_current_organization->getValue('org_id'). '
                   AND mem_rol_id        = rol_id
                   AND mem_begin        <= "'.DATE_NOW.'"
                   AND mem_end           > "'.DATE_NOW.'"
                   AND mem_usr_id        = usr_id
                   AND usr_valid         = 1 ';
        $result = $g_db->query($sql);

        while($row = $g_db->fetch_array($result))
        {
            // Mail an die Webmaster schicken, dass sich ein neuer User angemeldet hat
            $sysmail = new SystemMail($g_db);
            $sysmail->addRecipient($row['email'], $row['first_name']. ' '. $row['last_name']);

            if($sysmail->sendSystemMail('SYSMAIL_REGISTRATION_WEBMASTER', $user) == false)
            {
                $g_message->show($g_l10n->get('SYS_PHR_EMAIL_NOT_SEND', $row['email']));
            }
        }
    }
        
    if(isset($_SESSION['login_rol_id']))
    {
        if($_SESSION['login_rol_id'] > 0)
        {
            $g_message->setForwardUrl($_SESSION['navigation']->getPreviousUrl());
            $g_message->show($g_l10n->get('PRO_PHR_CONFIRM_REGISTRATION_DATE'));
        }
        else
        {
            $g_message->show($g_l10n->get('PRO_PHR_CONFIRM_REGISTRATION_SYSTEM'));
        }

        unset($_SESSION['login_rol_id']);
    }
    else
    {
        // nach Registrierungmeldung auf die Startseite verweisen
        $g_message->setForwardUrl($g_homepage);
        $g_message->show($g_l10n->get('SYS_PHR_SAVE'));
    }
}
elseif($new_user == 3 || $usr_id == 0)
{
    /*------------------------------------------------------------*/
    // neuer Benutzer wurde ueber Webanmeldung angelegt und soll nun zugeordnet werden
    // oder ein neuer User wurde in der Benutzerverwaltung angelegt
    /*------------------------------------------------------------*/

    if($usr_id > 0) // Webanmeldung
    {
        // User auf aktiv setzen
        $user->setValue('usr_valid', 1);
        $user->setValue('usr_reg_org_shortname', '');
        $user->save();

        // nur ausfuehren, wenn E-Mails auch unterstuetzt werden
        if($g_preferences['enable_system_mails'] == 1)
        {
            // Mail an den User schicken, um die Anmeldung zu bestaetigen
            $sysmail = new SystemMail($g_db);
            $sysmail->addRecipient($user->getValue('EMAIL'), $user->getValue('FIRST_NAME'). ' '. $user->getValue('LAST_NAME'));
            if($sysmail->sendSystemMail('SYSMAIL_REGISTRATION_USER', $user) == false)
            {
                $g_message->show($g_l10n->get('SYS_PHR_EMAIL_NOT_SEND', $user->getValue('EMAIL')));
            }
        }
    }

    // neuer User -> Rollen zuordnen
    if($g_current_user->assignRoles())
    {
        header('Location: roles.php?user_id='. $user->getValue('usr_id'). '&new_user=1');
        exit();
    }
    else
    {
        // da der angemeldete Benutzer keine Rechte besitzt Rollen zu zuordnen, 
        // wird der neue User der Default-Rolle zugeordnet
        if($g_preferences['profile_default_role'] == 0)
        {
            $g_message->show($g_l10n->get('PRO_PHR_NO_DEFAULT_ROLE'));
        }
        $member = new TableMembers($g_db);
        $member->startMembership($g_preferences['profile_default_role'], $user->getValue('usr_id'));
        
        $g_message->setForwardUrl($_SESSION['navigation']->getPreviousUrl(), 2000);
        $g_message->show($g_l10n->get('SYS_PHR_SAVE'));
    }
}
elseif($new_user == 0 && $user->getValue('usr_valid') == 0)
{
    // neue Registrierung bearbeitet
    $g_message->setForwardUrl($_SESSION['navigation']->getPreviousUrl(), 2000);
    $g_message->show($g_l10n->get('SYS_PHR_SAVE'));
}
else
{
    // zur Profilseite zurueckkehren    
    $g_message->setForwardUrl($_SESSION['navigation']->getUrl(), 2000);
    $g_message->show($g_l10n->get('SYS_PHR_SAVE'));
}
?>

