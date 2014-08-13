<?php
/******************************************************************************
 * Create and edit dates
 *
 * Copyright    : (c) 2004 - 2013 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * dat_id   - ID of the event that should be edited
 * headline - Headline for the event
 *            (Default) Events
 * copy : true - The event of the dat_id will be copied and the base for this new event
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');

// Initialize and check the parameters
$getDateId   = admFuncVariableIsValid($_GET, 'dat_id', 'numeric', 0);
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', $gL10n->get('DAT_DATES'));
$getCopy     = admFuncVariableIsValid($_GET, 'copy', 'boolean', 0);

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($gPreferences['enable_dates_module'] == 0)
{
    // das Modul ist deaktiviert
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

if(!$gCurrentUser->editDates())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// lokale Variablen der Uebergabevariablen initialisieren
$dateRegistrationPossible = 0;
$dateCurrentUserAssigned  = 0;

// set headline of the script
if($getDateId > 0)
{
    $headline = $gL10n->get('SYS_EDIT_VAR', $getHeadline);
}
else
{
    $headline = $gL10n->get('SYS_CREATE_VAR', $getHeadline);
}

$gNavigation->addUrl(CURRENT_URL, $headline);

// create date object
$date = new TableDate($gDb);

if(isset($_SESSION['dates_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben

    // first set date and time field to a datetime and add this to date class
    $_SESSION['dates_request']['dat_begin'] = $_SESSION['dates_request']['date_from'].' '.$_SESSION['dates_request']['date_from_time'];
    $_SESSION['dates_request']['dat_end']   = $_SESSION['dates_request']['date_to'].' '.$_SESSION['dates_request']['date_to_time'];

	$date->setArray($_SESSION['dates_request']);

    // get the selected roles for visibility
    $dateRoles = $_SESSION['dates_request']['date_roles'];
    
	// check if a registration to this event is possible
    if(array_key_exists('date_registration_possible', $_SESSION['dates_request']))
    {
        $dateRegistrationPossible = $_SESSION['dates_request']['date_registration_possible'];
    }
	
	// check if current user is assigned to this date
    if(array_key_exists('date_current_user_assigned', $_SESSION['dates_request']))
    {
        $dateCurrentUserAssigned = $_SESSION['dates_request']['date_current_user_assigned'];
    }
    
    unset($_SESSION['dates_request']);
}
else
{
    // read all roles that could see this event
    if($getDateId == 0)
    {
        // bei neuem Termin Datum mit aktuellen Daten vorbelegen
        $date->setValue('dat_begin', date('Y-m-d H:00:00', time()+3600));
        $date->setValue('dat_end', date('Y-m-d H:00:00', time()+7200));
    
        if($getCopy == false)
        {
            // a new event will be visible for all users per default
            $date->setVisibleRoles(array('0'));
            $dateRoles = array(0);
        }
    }
    else
    {
        $date->readDataById($getDateId);

        // get the saved roles for visibility
        $dateRoles = $date->getVisibleRoles();
        
        if($getCopy)
        {
            $date->setValue('dat_id', 0);
            $getDateId = 0;
        }
        
        // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
        if($date->editRight() == false)
        {
            $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
        }
    }
	
	// check if a registration to this event is possible
	if($date->getValue('dat_rol_id') > 0)
	{
		$dateRegistrationPossible = 1;
	}
	// check if current user is assigned to this date
	$dateCurrentUserAssigned = $gCurrentUser->isLeaderOfRole($date->getValue('dat_rol_id'));
}

if($date->getValue('dat_rol_id') > 0)
{
	$dateRoleID = $date->getValue('dat_rol_id');
}
else
{
	$dateRoleID = '0';
}


// create html page object
$page = new HtmlPage();

$page->addJavascriptFile($g_root_path.'/adm_program/system/js/date-functions.js');
$page->addJavascript('
    // Funktion blendet Zeitfelder ein/aus
    function setAllDay() {
		if ($("#dat_all_day:checked").val() !== undefined) {
			$("#date_from_time").hide();
			$("#date_to_time").hide();
        }
        else {
			$("#date_from_time").show("slow");
			$("#date_to_time").show("slow");
        }
    }
	
	function setDateParticipation() {
		if ($("#date_registration_possible:checked").val() !== undefined) {
			$("#date_current_user_assigned_group").show("slow");
			$("#dat_max_members_group").show("slow");
		}
		else {
			$("#date_current_user_assigned_group").hide();
			$("#dat_max_members_group").hide();
		}
	}

    // Funktion belegt das Datum-bis entsprechend dem Datum-Von
    function setDateTo() {
        var dateFrom = Date.parseDate($("#date_from").val(), "'.$gPreferences['system_date'].'");
        var dateTo   = Date.parseDate($("#date_to").val(), "'.$gPreferences['system_date'].'");

        if(dateFrom.getTime() > dateTo.getTime()) {
            $("#date_to").val($("#date_from").val());
        }
    }
	
	function setLocationCountry() {
		if($("#dat_location").val().length > 0) {
			$("#dat_country_group").show();
			$("#dat_country").focus();
		}
		else {
			$("#dat_country_group").hide();
		}
	}');
	
$page->addJavascript('
	var dateRoleID = '.$dateRoleID.';
	
    setAllDay();
	setDateParticipation();
	setLocationCountry();
	
	$("#date_registration_possible").click(function() {setDateParticipation();});
	$("#dat_all_day").click(function() {setAllDay();});
	$("#dat_location").change(function() {setLocationCountry();});
	$("#date_from").change(function() {setDateTo();});
	
	// if date participation should be removed than ask user
	$("#btn_save").click(function (event) {
		if(dateRoleID > 0 && $("#date_registration_possible").is(":checked") == false) {
			var msg_result = confirm("'.$gL10n->get('DAT_REMOVE_APPLICATION').'");
			if(msg_result) {
				$("#dates_edit_form").submit();
			}
            else  {
                event.preventDefault();
            }
		}
		else {
			$("#dates_edit_form").submit();
		}
	});', true);

// show back link
$page->addHtml($gNavigation->getHtmlBackButton());

// add headline and title of module
$page->addHeadline($headline);
 
// show form
$form = new HtmlForm('dates_edit_form', $g_root_path.'/adm_program/modules/dates/dates_function.php?dat_id='.$getDateId.'&amp;mode=1', $page);
$form->openGroupBox('gb_title_location', $gL10n->get('SYS_TITLE').' & '.$gL10n->get('DAT_LOCATION'));
    $form->addTextInput('dat_headline', $gL10n->get('SYS_TITLE'), $date->getValue('dat_headline'), 100, FIELD_MANDATORY);
    
    // if a map link should be shown in the event then show help text and a field where the user could choose the country
    if($gPreferences['dates_show_map_link'] == true)
    {
        $form->addTextInput('dat_location', $gL10n->get('DAT_LOCATION'), $date->getValue('dat_location'), 50, FIELD_DEFAULT, 'text', 'DAT_LOCATION_LINK');
    
    	if(strlen($date->getValue('dat_country')) == 0 && $getDateId == 0)
    	{
    		$date->setValue('dat_country', $gPreferences['default_country']);
    	}
        $form->addSelectBox('dat_country', $gL10n->get('SYS_COUNTRY'), $gL10n->getCountries(), FIELD_DEFAULT, $date->getValue('dat_country', 'database'), true);
    }
    else
    {
        $form->addTextInput('dat_location', $gL10n->get('DAT_LOCATION'), $date->getValue('dat_location'), 50, FIELD_DEFAULT);
    }
    
    // if room selection is activated then show a selectbox with all rooms
    if($gPreferences['dates_show_rooms'] == true)
    {
        if($gDbType == 'mysql')
        {
    	    $sql = 'SELECT room_id, CONCAT(room_name, \' (\', room_capacity, \'+\', IFNULL(room_overhang, \'0\'), \')\') FROM '.TBL_ROOMS.' ORDER BY room_name';        
        }
        else
        {
    	    $sql = 'SELECT room_id, room_name || \' (\' || room_capacity || \'+\' || COALESCE(room_overhang, \'0\') || \')\' FROM '.TBL_ROOMS.' ORDER BY room_name';
        }
        $form->addSelectBoxFromSql('dat_room_id', $gL10n->get('SYS_ROOM'), $gDb, $sql, FIELD_DEFAULT, $date->getValue('dat_room_id'));
    }
$form->closeGroupBox();
$form->openGroupBox('gb_period_calendar', $gL10n->get('SYS_PERIOD').' & '.$gL10n->get('DAT_CALENDAR'));
    $form->addCheckbox('dat_all_day', $gL10n->get('DAT_ALL_DAY'), $date->getValue('dat_all_day'));
    $form->addTextInput('date_from', $gL10n->get('SYS_START'), $date->getValue('dat_begin'), 0, FIELD_MANDATORY, 'datetime');
    $form->addTextInput('date_to', $gL10n->get('SYS_END'), $date->getValue('dat_end'), 0, FIELD_MANDATORY, 'datetime');
    $form->addSelectBoxForCategories('dat_cat_id', $gL10n->get('DAT_CALENDAR'), $gDb, 'DAT', 'EDIT_CATEGORIES', FIELD_MANDATORY, $date->getValue('dat_cat_id'));
$form->closeGroupBox();
$form->openGroupBox('gb_visibility_registration', $gL10n->get('DAT_VISIBILITY').' & '.$gL10n->get('SYS_REGISTRATION'));
    // add a multiselectbox to the form where the user can choose all roles that should see this event
    // first read all relevant roles from database and create an array with them
    $sql = 'SELECT * FROM '. TBL_ROLES. ', '. TBL_CATEGORIES. '
             WHERE rol_valid   = 1
               AND rol_visible = 1
               AND rol_cat_id  = cat_id
               AND (  cat_org_id  = '. $gCurrentOrganization->getValue('org_id'). '
                   OR cat_org_id IS NULL )
             ORDER BY cat_sequence, rol_name';
    $resultList = $gDb->query($sql);
    $roles = array(array('0', $gL10n->get('SYS_ALL').' ('.$gL10n->get('SYS_ALSO_VISITORS').')', null));

    while($row = $gDb->fetch_array($resultList))
    {
        $roles[] = array($row['rol_id'], $row['rol_name'], $row['cat_name']);
    }
    $form->addSelectBox('date_roles', $gL10n->get('DAT_VISIBLE_TO'), $roles, FIELD_MANDATORY, $dateRoles, false, true);
    
    $form->addCheckbox('dat_highlight', $gL10n->get('DAT_HIGHLIGHT_DATE'), $date->getValue('dat_highlight'));
    
    // if current organization has a parent organization or is child organizations then show option to set this announcement to global
	if($gCurrentOrganization->getValue('org_org_id_parent') > 0 || $gCurrentOrganization->hasChildOrganizations())
	{
        // show all organizations where this organization is mother or child organization
        $organizations = '- '.$gCurrentOrganization->getValue('org_longname').',<br />- ';
        $organizations .= implode(',<br />- ', $gCurrentOrganization->getOrganizationsInRelationship(true, true, true));

        $form->addCheckbox('dat_global', $gL10n->get('SYS_ENTRY_MULTI_ORGA'), $date->getValue('dat_global'), FIELD_DEFAULT, array('SYS_DATA_GLOBAL', $organizations));
	}
    $form->addCheckbox('date_registration_possible', $gL10n->get('DAT_REGISTRATION_POSSIBLE'), $dateRegistrationPossible, FIELD_DEFAULT, 'DAT_LOGIN_POSSIBLE');
    $form->addCheckbox('date_current_user_assigned', $gL10n->get('DAT_PARTICIPATE_AT_DATE'), $dateCurrentUserAssigned, FIELD_DEFAULT, 'DAT_PARTICIPATE_AT_DATE_DESC');
    $form->addTextInput('dat_max_members', $gL10n->get('DAT_PARTICIPANTS_LIMIT'), $date->getValue('dat_max_members'), 5, FIELD_MANDATORY, 'number', 'DAT_MAX_MEMBERS');
$form->closeGroupBox();
$form->openGroupBox('gb_description', $gL10n->get('SYS_DESCRIPTION'));
    $form->addEditor('dat_description', null, $date->getValue('dat_description'), FIELD_MANDATORY);
$form->closeGroupBox();

$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), THEME_PATH.'/icons/disk.png');
$form->addHtml(admFuncShowCreateChangeInfoById($date->getValue('dat_usr_id_create'), $date->getValue('dat_timestamp_create'), $date->getValue('dat_usr_id_change'), $date->getValue('dat_timestamp_change')));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();
exit();

// Html des Modules ausgeben
echo '
	<div class="groupBox" id="admVisibilityRegistration">
			<div class="groupBoxHeadline" id="admVisibilityRegistrationHead">
				<a class="iconShowHide" href="javascript:showHideBlock(\'admVisibilityRegistrationBody\', \''.$gL10n->get('SYS_FADE_IN').'\', \''.$gL10n->get('SYS_HIDE').'\')"><img
				id="admVisibilityRegistrationBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$gL10n->get('SYS_HIDE').'" title="'.$gL10n->get('SYS_HIDE').'" /></a>'.$gL10n->get('DAT_VISIBILITY').' & '.$gL10n->get('SYS_REGISTRATION').'
			</div>

			<div class="groupBoxBody" id="admVisibilityRegistrationBody">
				<ul class="formFieldList">
					<li id="liRoles"></li>
					<li>
						<dl>
							<dt>&nbsp;</dt>
							<dd><span id="add_attachment" class="iconTextLink">
									<a href="javascript:addRoleSelection(0)"><img
									src="'. THEME_PATH. '/icons/add.png" alt="'.$gL10n->get('DAT_ADD_ROLE').'" /></a>
									<a href="javascript:addRoleSelection(0)">'.$gL10n->get('DAT_ADD_ROLE').'</a>
								</span></dd>
						</dl>
					</li>
                    <li>
						<dl>
							<dt>&nbsp;</dt>
							<dd>
								<input type="checkbox" id="dat_highlight" name="dat_highlight"';
								if($date->getValue('dat_highlight') == 1)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
								<label for="dat_highlight">'.$gL10n->get('DAT_HIGHLIGHT_DATE').'</label>
							</dd>
						</dl>
					</li>';

					// besitzt die Organisation eine Elternorga oder hat selber Kinder, so kann die Ankuendigung auf "global" gesetzt werden
					if($gCurrentOrganization->getValue('org_org_id_parent') > 0
						|| $gCurrentOrganization->hasChildOrganizations())
					{
						echo '
						<li>
							<dl>
								<dt>&nbsp;</dt>
								<dd>
									<input type="checkbox" id="dat_global" name="dat_global" ';
									if($date->getValue('dat_global') == 1)
									{
										echo ' checked="checked" ';
									}
									echo ' value="1" />
									<label for="dat_global">'.$gL10n->get('SYS_ENTRY_MULTI_ORGA').'</label>
									<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=SYS_DATA_GLOBAL&amp;inline=true"><img 
										onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=SYS_DATA_GLOBAL\',this)" onmouseout="ajax_hideTooltip()"
										class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
								</dd>
							</dl>
						</li>';
					}

					echo '
					<li>
						<dl>
							<dt>&nbsp;</dt>
							<dd>
								<input type="checkbox" id="dateRegistrationPossible" name="dateRegistrationPossible"';
								if($dateRegistrationPossible == 1)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
								<label for="dateRegistrationPossible">'.$gL10n->get('DAT_REGISTRATION_POSSIBLE').'</label>
								<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DAT_LOGIN_POSSIBLE&amp;inline=true"><img 
									onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DAT_LOGIN_POSSIBLE\',this)" 
									onmouseout="ajax_hideTooltip()" class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
							</dd>
						</dl>
					</li>
					<li id="admAssignYourself">
						<dl>
							<dt>&nbsp;</dt>
							<dd>
								<input type="checkbox" id="dateCurrentUserAssigned" name="dateCurrentUserAssigned"';
								if($dateCurrentUserAssigned == 1)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
								<label for="dateCurrentUserAssigned">'.$gL10n->get('DAT_PARTICIPATE_AT_DATE').'</label>
								<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DAT_PARTICIPATE_AT_DATE_DESC&amp;inline=true"><img 
									onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DAT_PARTICIPATE_AT_DATE_DESC\',this)" 
									onmouseout="ajax_hideTooltip()" class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
							</dd>
						</dl>
					</li>
					<li id="admMaxMembers">
						<dl>
							<dt><label for="dat_max_members">'.$gL10n->get('DAT_PARTICIPANTS_LIMIT').':</label></dt>
							<dd>
								<input type="text" id="dat_max_members" name="dat_max_members" style="width: 50px;" maxlength="5" value="'.($date->getValue('dat_max_members')).'" />
								<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DAT_MAX_MEMBERS&amp;inline=true"><img 
									onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DAT_MAX_MEMBERS\',this)" 
									onmouseout="ajax_hideTooltip()" class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
							</dd>
						</dl>
					</li>
				</ul>
			</div>
		</div>';

?>