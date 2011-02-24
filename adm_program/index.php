<?php
/******************************************************************************
 * Liste aller Module und Administrationsseiten von Admidio
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// wenn noch nicht installiert, dann Install-Dialog anzeigen
if(!file_exists('../config.php'))
{
    $location = 'Location: ../adm_install/index.php';
    header($location);
    exit();
}

require_once('system/common.php');

if($g_current_user->isWebmaster())
{
    // der Installationsordner darf aus Sicherheitsgruenden nicht existieren
    if($g_debug == 0 && file_exists('../adm_install'))
    {
        $g_message->show($g_l10n->get('SYS_INSTALL_FOLDER_EXIST'));
    }
}

// Url-Stack loeschen
$_SESSION['navigation']->clear();

// Html-Kopf ausgeben
$g_layout['title']  = 'Admidio '.$g_l10n->get('SYS_OVERVIEW');
$g_layout['header'] = '<link rel="stylesheet" href="'. THEME_PATH. '/css/overview_modules.css" type="text/css" />';

require(THEME_SERVER_PATH. '/overall_header.php');

// Html des Modules ausgeben
echo '
<h1 class="moduleHeadline">'.$g_current_organization->getValue('org_longname').'</h1>

<ul class="iconTextLinkList">';
    if($g_valid_login == 1)
    {
        echo '<li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/logout.php"><img
                src="'.THEME_PATH.'/icons/door_in.png" alt="'.$g_l10n->get('SYS_LOGOUT').'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/logout.php">'.$g_l10n->get('SYS_LOGOUT').'</a>
            </span>
        </li>';
    }
    else
    {
        echo '<li>
            <span class="iconTextLink">
                <a href="'.$g_root_path.'/adm_program/system/login.php"><img
                src="'.THEME_PATH.'/icons/key.png" alt="'.$g_l10n->get('SYS_LOGIN').'" /></a>
                <a href="'.$g_root_path.'/adm_program/system/login.php">'.$g_l10n->get('SYS_LOGIN').'</a>
            </span>
        </li>';

        if($g_preferences['registration_mode'] > 0)
        {
            echo '<li>
                <span class="iconTextLink">
                    <a href="'.$g_root_path.'/adm_program/system/registration.php"><img
                    src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$g_l10n->get('SYS_REGISTRATION').'" /></a>
                    <a href="'.$g_root_path.'/adm_program/system/registration.php">'.$g_l10n->get('SYS_REGISTRATION').'</a>
                </span>
            </li>';
        }
    }
echo '</ul>

<div class="formLayout" id="modules_list_form">
    <div class="formHead">'.$g_l10n->get('SYS_MODULES').'</div>
    <div class="formBody">
        <ul class="formFieldList">';
            if( $g_preferences['enable_announcements_module'] == 1
            || ($g_preferences['enable_announcements_module'] == 2 && $g_valid_login))
            {
                echo '
                <li>
                    <dl>
                        <dt>
                            <a href="'.$g_root_path.'/adm_program/modules/announcements/announcements.php"><img
                            src="'.THEME_PATH.'/icons/announcements_big.png" alt="'.$g_l10n->get('ANN_ANNOUNCEMENTS').'" title="'.$g_l10n->get('ANN_ANNOUNCEMENTS').'" /></a>
                        </dt>
                        <dd>
                            <span class="veryBigFontSize"><a href="'.$g_root_path.'/adm_program/modules/announcements/announcements.php">'.$g_l10n->get('ANN_ANNOUNCEMENTS').'</a></span><br />
                            <span class="smallFontSize">'.$g_l10n->get('ANN_ANNOUNCEMENTS_DESC').'</span>
                        </dd>
                    </dl>
                </li>';
            }

            if($g_preferences['enable_download_module'] == 1)
            {
                echo '
                <li>
                    <dl>
                        <dt>
                            <a href="'.$g_root_path.'/adm_program/modules/downloads/downloads.php"><img
                            src="'.THEME_PATH.'/icons/download_big.png" alt="'.$g_l10n->get('DOW_DOWNLOADS').'" title="'.$g_l10n->get('DOW_DOWNLOADS').'" /></a>
                        </dt>
                        <dd>
                            <span class="veryBigFontSize"><a href="'.$g_root_path.'/adm_program/modules/downloads/downloads.php">'.$g_l10n->get('DOW_DOWNLOADS').'</a></span><br />
                            <span class="smallFontSize">'.$g_l10n->get('DOW_DOWNLOADS_DESC').'</span>
                        </dd>
                    </dl>
                </li>';
            }

            if($g_preferences['enable_mail_module'] == 1)
            {
                echo '
                <li>
                    <dl>
                        <dt>
                            <a href="'.$g_root_path.'/adm_program/modules/mail/mail.php"><img
                            src="'.THEME_PATH.'/icons/email_big.png" alt="'.$g_l10n->get('SYS_EMAIL').'" title="'.$g_l10n->get('SYS_EMAIL').'" /></a>
                        </dt>
                        <dd>
                            <span class="veryBigFontSize"><a href="'.$g_root_path.'/adm_program/modules/mail/mail.php">'.$g_l10n->get('SYS_EMAIL').'</a></span><br />
                            <span class="smallFontSize">'.$g_l10n->get('MAI_EMAIL_DESC').'</span>
                        </dd>
                    </dl>
                </li>';
            }

            if($g_preferences['enable_photo_module'] == 1 
            || ($g_preferences['enable_photo_module'] == 2 && $g_valid_login))
            {
                echo '
                <li>
                    <dl>
                        <dt>
                            <a href="'.$g_root_path.'/adm_program/modules/photos/photos.php"><img
                            src="'.THEME_PATH.'/icons/photo_big.png" alt="'.$g_l10n->get('PHO_PHOTOS').'" title="'.$g_l10n->get('PHO_PHOTOS').'" /></a>
                        </dt>
                        <dd>
                            <span class="veryBigFontSize"><a href="'.$g_root_path.'/adm_program/modules/photos/photos.php">'.$g_l10n->get('PHO_PHOTOS').'</a></span><br />
                            <span class="smallFontSize">'.$g_l10n->get('PHO_PHOTOS_DESC').'</span>
                        </dd>
                    </dl>
                </li>';
            }

            if( $g_preferences['enable_guestbook_module'] == 1
            || ($g_preferences['enable_guestbook_module'] == 2 && $g_valid_login))
            {
                echo '
                <li>
                    <dl>
                        <dt>
                            <a href="'.$g_root_path.'/adm_program/modules/guestbook/guestbook.php"><img
                            src="'.THEME_PATH.'/icons/guestbook_big.png" alt="'.$g_l10n->get('GBO_GUESTBOOK').'" title="'.$g_l10n->get('GBO_GUESTBOOK').'" /></a>
                        </dt>
                        <dd>
                            <span class="veryBigFontSize"><a href="'.$g_root_path.'/adm_program/modules/guestbook/guestbook.php">'.$g_l10n->get('GBO_GUESTBOOK').'</a></span><br />
                            <span class="smallFontSize">'.$g_l10n->get('GBO_GUESTBOOK_DESC').'</span>
                        </dd>
                    </dl>
                </li>';
            }

            echo '
            <li>
                <dl>
                    <dt>
                        <a href="'.$g_root_path.'/adm_program/modules/lists/lists.php"><img
                        src="'.THEME_PATH.'/icons/lists_big.png" alt="'.$g_l10n->get('LST_LISTS').'" title="'.$g_l10n->get('LST_LISTS').'" /></a>
                    </dt>
                    <dd>
                        <span class="veryBigFontSize"><a href="'.$g_root_path.'/adm_program/modules/lists/lists.php">'.$g_l10n->get('LST_LISTS').'</a></span>&nbsp;&nbsp;
                        &#91; <a href="'.$g_root_path.'/adm_program/modules/lists/mylist.php">'.$g_l10n->get('LST_MY_LIST').'</a>&nbsp;|
                        <a href="'.$g_root_path.'/adm_program/modules/lists/lists.php?active_role=0">'.$g_l10n->get('ROL_INACTIV_ROLE').'</a> &#93;<br />
                        <span class="smallFontSize">'.$g_l10n->get('LST_LISTS_DESC').'</span>
                    </dd>
                </dl>
            </li>';

            echo '
            <li>
                <dl>
                    <dt>
                        <a href="'.$g_root_path.'/adm_program/modules/profile/profile.php"><img
                        src="'.THEME_PATH.'/icons/profile_big.png" alt="'.$g_l10n->get('PRO_MY_PROFILE').'" title="'.$g_l10n->get('PRO_MY_PROFILE').'" /></a>
                    </dt>
                    <dd>
                        <span class="veryBigFontSize"><a href="'.$g_root_path.'/adm_program/modules/profile/profile.php">'.$g_l10n->get('PRO_MY_PROFILE').'</a></span>';
                        if($g_valid_login)
                        {
                            echo '&nbsp;&nbsp;
                            &#91; <a href="'.$g_root_path.'/adm_program/modules/profile/profile_new.php?user_id='.$g_current_user->getValue('usr_id').'">'.$g_l10n->get('PRO_EDIT_MY_PROFILE').'</a> &#93;';
                        }
                        echo '<br />
                        <span class="smallFontSize">'.$g_l10n->get('PRO_MY_PROFILE_DESC').'</span>
                    </dd>
                </dl>
            </li>';


            if( $g_preferences['enable_dates_module'] == 1
            || ($g_preferences['enable_dates_module'] == 2 && $g_valid_login))
            {
                echo '
                <li>
                    <dl>
                        <dt>
                            <a href="'.$g_root_path.'/adm_program/modules/dates/dates.php"><img
                            src="'.THEME_PATH.'/icons/dates_big.png" alt="'.$g_l10n->get('DAT_DATES').'" title="'.$g_l10n->get('DAT_DATES').'" /></a>
                        </dt>
                        <dd>
                            <span class="veryBigFontSize"><a href="'.$g_root_path.'/adm_program/modules/dates/dates.php">'.$g_l10n->get('DAT_DATES').'</a></span>&nbsp;&nbsp;
                            &#91; <a href="'.$g_root_path.'/adm_program/modules/dates/dates.php?mode=old">'.$g_l10n->get('DAT_PREVIOUS_DATES', $g_l10n->get('DAT_DATES')).'</a> &#93;<br />
                            <span class="smallFontSize">'.$g_l10n->get('DAT_DATES_DESC').'</span>
                        </dd>
                    </dl>
                </li>';
            }


            if( $g_preferences['enable_weblinks_module'] == 1
            || ($g_preferences['enable_weblinks_module'] == 2 && $g_valid_login))
            {
                echo '
                <li>
                    <dl>
                        <dt>
                            <a href="'.$g_root_path.'/adm_program/modules/links/links.php"><img
                            src="'.THEME_PATH.'/icons/weblinks_big.png" alt="'.$g_l10n->get('LNK_WEBLINKS').'" title="'.$g_l10n->get('LNK_WEBLINKS').'" /></a>
                        </dt>
                        <dd>
                            <span class="veryBigFontSize"><a href="'.$g_root_path.'/adm_program/modules/links/links.php">'.$g_l10n->get('LNK_WEBLINKS').'</a></span><br />
                            <span class="smallFontSize">'.$g_l10n->get('LNK_WEBLINKS_DESC').'</span>
                        </dd>
                    </dl>
                </li>';
            }
            

            // Wenn das Forum aktiv ist, dieses auch in der Uebersicht anzeigen.
            if($g_preferences['enable_forum_interface'])
            {
                if($g_forum->session_valid)
                {
                    $forumstext = $g_l10n->get('SYS_FORUM_LOGIN_DESC', $g_forum->user, $g_forum->sitename, $g_forum->getUserPM($g_current_user->getValue('usr_login_name')));
                }
                else
                {
                    $forumstext = $g_l10n->get('SYS_FORUM_DESC');
                }
                echo '
                <li>
                    <dl>
                        <dt>
                            <a href="'. $g_forum->url. '"><img src="'. THEME_PATH. '/icons/forum_big.png" alt="'.$g_l10n->get('SYS_FORUM').'" title="'.$g_l10n->get('SYS_FORUM').'" /></a>
                        </dt>
                        <dd>
                            <span class="veryBigFontSize"><a href="'. $g_forum->url. '">'.$g_l10n->get('SYS_FORUM').'</a></span><br />
                            <span class="smallFontSize">'.$forumstext.'</span>
                        </dd>
                    </dl>
                </li>';
            }
        echo '
        </ul>
    </div>
</div>';

if($g_current_user->isWebmaster() || $g_current_user->assignRoles() || $g_current_user->approveUsers() || $g_current_user->editUsers())
{
    echo '
    <div class="formLayout" id="administration_list_form">
        <div class="formHead">'.$g_l10n->get('SYS_ADMINISTRATION').'</div>
        <div class="formBody">
            <ul class="formFieldList">';
                if($g_current_user->approveUsers() && $g_preferences['registration_mode'] > 0)
                {
                    echo '
                    <li>
                        <dl>
                            <dt>
                                <a href="'.$g_root_path.'/adm_program/administration/new_user/new_user.php"><img
                                src="'.THEME_PATH.'/icons/new_registrations_big.png" alt="'.$g_l10n->get('NWU_MANAGE_NEW_REGISTRATIONS').'" title="'.$g_l10n->get('NWU_MANAGE_NEW_REGISTRATIONS').'" /></a>
                            </dt>
                            <dd>
                                <span class="veryBigFontSize"><a href="'.$g_root_path.'/adm_program/administration/new_user/new_user.php">'.$g_l10n->get('NWU_MANAGE_NEW_REGISTRATIONS').'</a></span><br />
                                <span class="smallFontSize">'.$g_l10n->get('NWU_MANAGE_NEW_REGISTRATIONS_DESC').'</span>
                            </dd>
                        </dl>
                    </li>';
                }

                if($g_current_user->editUsers())
                {
                    echo '
                    <li>
                        <dl>
                            <dt>
                                <a href="'.$g_root_path.'/adm_program/administration/members/members.php"><img
                                src="'.THEME_PATH.'/icons/user_administration_big.png" alt="'.$g_l10n->get('MEM_USER_MANAGEMENT').'" title="'.$g_l10n->get('MEM_USER_MANAGEMENT').'" /></a>
                            </dt>
                            <dd>
                                <span class="veryBigFontSize"><a href="'.$g_root_path.'/adm_program/administration/members/members.php">'.$g_l10n->get('MEM_USER_MANAGEMENT').'</a></span><br />
                                <span class="smallFontSize">'.$g_l10n->get('MEM_USER_MANAGEMENT_DESC').'</span>
                            </dd>
                        </dl>
                    </li>';
                }

                if($g_current_user->assignRoles())
                {
                    echo '
                    <li>
                        <dl>
                            <dt>
                                <a href="'.$g_root_path.'/adm_program/administration/roles/roles.php"><img
                                src="'.THEME_PATH.'/icons/roles_big.png" alt="'.$g_l10n->get('ROL_ROLE_ADMINISTRATION').'" title="'.$g_l10n->get('ROL_ROLE_ADMINISTRATION').'" /></a>
                            </dt>
                            <dd>
                                <span class="veryBigFontSize"><a href="'.$g_root_path.'/adm_program/administration/roles/roles.php">'.$g_l10n->get('ROL_ROLE_ADMINISTRATION').'</a></span><br />
                                <span class="smallFontSize">'.$g_l10n->get('ROL_ROLE_ADMINISTRATION_DESC').'</span>
                            </dd>
                        </dl>
                    </li>';
                }
                
                if($g_current_user->isWebmaster())
                {
                    echo '
                    <li>
                        <dl>
                            <dt>
                                <a href="'.$g_root_path.'/adm_program/administration/backup/backup.php"><img
                                src="'.THEME_PATH.'/icons/backup_big.png" alt="'.$g_l10n->get('BAC_DATABASE_BACKUP').'" title="'.$g_l10n->get('BAC_DATABASE_BACKUP').'" /></a>
                            </dt>
                            <dd>
                                <span class="veryBigFontSize"><a href="'.$g_root_path.'/adm_program/administration/backup/backup.php">'.$g_l10n->get('BAC_DATABASE_BACKUP').'</a></span><br />
                                <span class="smallFontSize">'.$g_l10n->get('BAC_DATABASE_BACKUP_DESC').'</span>
                            </dd>
                        </dl>
                    </li>';
                }

                if($g_current_user->isWebmaster())
                {
                    echo '
                    <li>
                        <dl>
                            <dt>
                                <a href="'.$g_root_path.'/adm_program/administration/organization/organization.php"><img
                                src="'. THEME_PATH. '/icons/options_big.png" alt="'.$g_l10n->get('ORG_ORGANIZATION_PROPERTIES').'" title="'.$g_l10n->get('ORG_ORGANIZATION_PROPERTIES').'" /></a>
                            </dt>
                            <dd>
                                <span class="veryBigFontSize"><a href="'.$g_root_path.'/adm_program/administration/organization/organization.php">'.$g_l10n->get('ORG_ORGANIZATION_PROPERTIES').'</a></span><br />
                                <span class="smallFontSize">'.$g_l10n->get('ORG_ORGANIZATION_PROPERTIES_DESC').'</span>
                            </dd>
                        </dl>
                    </li>';
                }

            echo '
            </ul>
        </div>
    </div>';
}

require(THEME_SERVER_PATH. '/overall_footer.php');

?>