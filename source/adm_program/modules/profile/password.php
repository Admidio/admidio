<?php
/******************************************************************************
 * Passwort neu vergeben
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * usr_id     - Passwort der übergebenen User-Id aendern
 * mode   : 0 - (Default) Anzeige des Passwordaenderungsformulars
 *          1 - Passwortaenderung wird verarbeitet
 * inline : 1 - für z.B.: colorbox nur Content ohne Header wird angezeigt
 *
 *****************************************************************************/
 
require_once('../../system/common.php');
require_once('../../system/login_valid.php');
 
// nur Webmaster duerfen fremde Passwoerter aendern
if($g_current_user->isWebmaster() == false && $g_current_user->getValue('usr_id') != $_GET['usr_id'])
{
    $g_message->setExcludeThemeBody();
    $g_message->show($g_l10n->get('SYS_PHR_NO_RIGHTS'));
}

// Uebergabevariablen pruefen

if(isset($_GET['usr_id']) && is_numeric($_GET['usr_id']) == false)
{
    $g_message->setExcludeThemeBody();
    $g_message->show($g_l10n->get('SYS_INVALID_PAGE_VIEW'));
}
$inlineView = 0;
if(isset($_GET['inline']) && is_numeric($_GET['inline']) == true)
{
    $inlineView = 1;
}

if(isset($_GET['mode']) && is_numeric($_GET['mode']) && $_GET['mode'] == 1)
{
    /***********************************************************************/
    /* Formular verarbeiten */
    /***********************************************************************/
    if($g_current_user->isWebmaster() && $g_current_user->getValue('usr_id') != $_GET['usr_id'] )
    {
        $_POST['old_password'] = '';
    }
    
    if( (strlen($_POST['old_password']) > 0 || $g_current_user->isWebmaster() )
    && strlen($_POST['new_password']) > 0
    && strlen($_POST['new_password2']) > 0)
    {
        if(strlen($_POST['new_password']) > 5)
        {
            if ($_POST['new_password'] == $_POST['new_password2'])
            {
                // pruefen, ob altes Passwort korrekt eingegeben wurde              
                $user = new User($g_db, $_GET['usr_id']);
                $old_password_crypt = md5($_POST['old_password']);

                // Webmaster duerfen fremde Passwörter so aendern
                if($user->getValue('usr_password') == $old_password_crypt || $g_current_user->isWebmaster() && $g_current_user->getValue('usr_id') != $_GET['usr_id'] )
                {
                    $user->setValue('usr_password', $_POST['new_password']);
                    $user->save();

                    // Paralell im Forum aendern, wenn Forum aktiviert ist
                    if($g_preferences['enable_forum_interface'])
                    {
                        $g_forum->userSave($user->getValue('usr_login_name'), $user->getValue('usr_password'), $user->getValue('E-Mail'), '', 3);
                    }

                    // wenn das PW des eingeloggten Users geaendert wird, dann Session-Variablen aktualisieren
                    if($user->getValue('usr_id') == $g_current_user->getValue('usr_id'))
                    {
                        $g_current_user->setValue('usr_password', $user->getValue('usr_password'));
                    }

                    $g_message->setForwardUrl('javascript:self.parent.tb_remove()');
                    $phrase = $g_l10n->get('PRO_PHR_PASSWORD_CHANGED')."<SAVED/>";
                }
                else
                {
                    $phrase = $g_l10n->get('PRO_PHR_PASSWORD_OLD_WRONG');
                }
            }
            else
            {
                $phrase = $g_l10n->get('PRO_PHR_PASSWORDS_NOT_EQUAL');
            }
        }
        else
        {
            $phrase = $g_l10n->get('PRO_PHR_PASSWORD_LENGTH');
        }
    }
    else
    {
        $phrase = $g_l10n->get('SYS_PHR_FIELDS_EMPTY');
    }
	if ($inlineView == 0)
	{
		$g_message->setExcludeThemeBody();
		$g_message->show($phrase);
	}
	else
	{
		echo $phrase;
	}
}
else
{
    /***********************************************************************/
    /* Passwortformular anzeigen */
    /***********************************************************************/
    
    // Html-Kopf ausgeben
    $g_layout['title']    = 'Passwort bearbeiten';
    $g_layout['includes'] = false;
    if ($inlineView == 0)
	{
		require(THEME_SERVER_PATH. '/overall_header.php');
	}

    // Html des Modules ausgeben
    echo '
    <form id="passwordForm" action="'. $g_root_path. '/adm_program/modules/profile/password.php?usr_id='. $_GET['usr_id']. '&mode=1&inline=1" method="post">
    <div class="formLayout" id="password_form" style="width: 300px">
        <div class="formHead">'. $g_layout['title']. '</div>
        <div class="formBody">
            <ul class="formFieldList">';
                if($g_current_user->getValue('usr_id') == $_GET['usr_id'] )
                {
                echo'
                    <li>
                        <dl>
                            <dt><label for="old_password">Aktuelles Passwort:</label></dt>
                            <dd><input type="password" id="old_password" name="old_password" size="12" maxlength="20" />
                                <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span></dd>
                        </dl>
                    </li>
                    <li><hr /></li>';
                }    
                echo'
                <li>
                    <dl>
                        <dt><label for="new_password">Neues Passwort:</label></dt>
                        <dd><input type="password" id="new_password" name="new_password" size="12" maxlength="20" />
                            <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
                            <img onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=PRO_PHR_PASSWORD_DESCRIPTION\',this)" onmouseout="ajax_hideTooltip()"
                                class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Hilfe" title="" />
                        </dd>
                    </dl>
                </li>
                <li>
                    <dl>
                        <dt><label for="new_password2">Wiederholen:</label></dt>
                        <dd><input type="password" id="new_password2" name="new_password2" size="12" maxlength="20" />
                            <span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span></dd>
                    </dl>
                </li>
            </ul>

            <hr />

            <div class="formSubmit">
                <button name="save" type="submit" value="'.$g_l10n->get('SYS_SAVE').'"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$g_l10n->get('SYS_SAVE').'" />&nbsp;'.$g_l10n->get('SYS_SAVE').'</button>
            </div>
        </div>
    </form>';
    if ($inlineView == 0)
	{  
		require(THEME_SERVER_PATH. '/overall_footer.php');
	}
}

?>