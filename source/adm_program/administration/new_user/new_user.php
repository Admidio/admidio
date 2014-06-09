<?php
/******************************************************************************
 * Show list with new user registrations
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Only Webmasters can confirm new users. Otherwise exit.
if($gCurrentUser->approveUsers() == false)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// check if module is active
if($gPreferences['registration_mode'] == 0)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// Navigation in module starts here
$gNavigation->clear();
$gNavigation->addUrl(CURRENT_URL);

// Select new Members of the group
$sql    = 'SELECT usr_id, usr_login_name, reg_timestamp, last_name.usd_value as last_name,
                  first_name.usd_value as first_name, email.usd_value as email
             FROM '. TBL_REGISTRATIONS. ', '. TBL_USERS. ' 
             LEFT JOIN '. TBL_USER_DATA. ' as last_name
               ON last_name.usd_usr_id = usr_id
              AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
             LEFT JOIN '. TBL_USER_DATA. ' as first_name
               ON first_name.usd_usr_id = usr_id
              AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
             LEFT JOIN '. TBL_USER_DATA. ' as email
               ON email.usd_usr_id = usr_id
              AND email.usd_usf_id = '. $gProfileFields->getProperty('EMAIL', 'usf_id'). '
            WHERE usr_valid = 0
			  AND reg_usr_id = usr_id
			  AND reg_org_id = '.$gCurrentOrganization->getValue('org_id').'
            ORDER BY last_name, first_name ';
$usr_result   = $gDb->query($sql);
$member_found = $gDb->num_rows($usr_result);

if ($member_found == 0)
{
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('NWU_NO_REGISTRATIONS'), $gL10n->get('SYS_REGISTRATION'));
}

// Html-Head output
$gLayout['title']  = $gL10n->get('NWU_NEW_REGISTRATIONS');
$gLayout['header'] = '
    <script type="text/javascript"><!--
        $(document).ready(function() {
            $("a[rel=\'lnkDelete\']").colorbox({rel:\'nofollow\', height: \'280px\', onComplete:function(){$("#admButtonNo").focus();}});
        }); 
    //--></script>';

$table = new HtmlTableBasic('', 'tableList');
$table->addAttribute('cellspacing', '0', 'table');
$table->addRow();
$table->addColumn($gL10n->get('SYS_NAME'), array('colspan' => '2'), 'th');
$table->addColumn($gL10n->get('SYS_USERNAME'), null, 'th');
$table->addColumn($gL10n->get('SYS_EMAIL'), null, 'th');
$table->addColumn($gL10n->get('SYS_FEATURES'), array('style' => 'text-align: center;'), 'th');
$table->addTableBody();

while($row = $gDb->fetch_array($usr_result))
{
    $timestampCreate = new DateTimeExtended($row['reg_timestamp'], 'Y-m-d H:i:s');
    $datetimeCreate  = $timestampCreate->format($gPreferences['system_date'].' '.$gPreferences['system_time']);
    
    $table->addRow('', array('class' => 'tableMouseOver'));
    $table->addAttribute('id', 'row_user_'.$row['usr_id']);
    $table->addColumn('<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$row['usr_id'].'">'.$row['last_name'].', '.$row['first_name'].'</a>');
    $table->addColumn('<img class="iconInformation" src="'. THEME_PATH. '/icons/calendar_time.png"
                            alt="'.$gL10n->get('NWU_REGISTERED_ON', $datetimeCreate).'" title="'.$gL10n->get('NWU_REGISTERED_ON', $datetimeCreate).'" />');
    $table->addColumn($row['usr_login_name']);
    $mailLink = '';
    if($gPreferences['enable_mail_module'] == 1)
    {
        $mailLink = '<a href="'.$g_root_path.'/adm_program/modules/messages/messages.php?usr_id='.$row['usr_id'].'">'.$row['email'].'</a>';
    }
    else
    {
        $mailLink  = '<a href="mailto:'.$row['email'].'">'.$row['email'].'</a>';
    }
    $table->addColumn($mailLink);
    $table->addColumn('<a class="iconLink" href="'.$g_root_path.'/adm_program/administration/new_user/new_user_assign.php?new_user_id='.$row['usr_id'].'"><img 
                            src="'. THEME_PATH. '/icons/new_registrations.png" alt="'.$gL10n->get('NWU_ASSIGN_REGISTRATION').'" title="'.$gL10n->get('NWU_ASSIGN_REGISTRATION').'" /></a>
                        <a class="iconLink" rel="lnkDelete" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=nwu&amp;element_id=row_user_'.
                            $row['usr_id'].'&amp;name='.urlencode($row['first_name'].' '.$row['last_name']).'&amp;database_id='.$row['usr_id'].'"><img 
                            src="'. THEME_PATH. '/icons/delete.png" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'" /></a>', array('style' => 'text-align: center;')); 

    }

// Write html output
require(SERVER_PATH. '/adm_program/system/overall_header.php');
echo '<h1 class="moduleHeadline">'.$gLayout['title'].'</h1>'. $table->getHtmlTable();
require(SERVER_PATH. '/adm_program/system/overall_footer.php');

?>