<?php
/******************************************************************************
 * Termine anlegen und bearbeiten
 *
 * Copyright    : (c) 2004 - 2011 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Uebergaben:
 *
 * dat_id   - ID des Termins, der bearbeitet werden soll
 * headline - Ueberschrift, die ueber den Terminen steht
 *            (Default) Termine
 * copy : true - der uebergebene ID-Termin wird kopiert und kann neu gespeichert werden
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/login_valid.php');
require_once('../../system/classes/form_elements.php');
require_once('../../system/classes/table_date.php');
require_once('../../system/classes/table_rooms.php');
require_once('../../system/classes/table_roles.php');

if ($g_preferences['enable_bbcode'] == 1)
{
    require_once('../../system/bbcode.php');
}

// pruefen ob das Modul ueberhaupt aktiviert ist
if ($g_preferences['enable_dates_module'] == 0)
{
    // das Modul ist deaktiviert
    $g_message->show($g_l10n->get('SYS_MODULE_DISABLED'));
}

if(!$g_current_user->editDates())
{
    $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
}

// Uebergabevariablen pruefen und ggf. initialisieren
$get_dat_id   = admFuncVariableIsValid($_GET, 'dat_id', 'numeric', 0);
$get_headline = admFuncVariableIsValid($_GET, 'headline', 'string', $g_l10n->get('DAT_DATES'));
$get_copy     = admFuncVariableIsValid($_GET, 'copy', 'boolean', 0);

// lokale Variablen der Uebergabevariablen initialisieren
$date_login   = 0;
$date_assign_yourself = 0;

$_SESSION['navigation']->addUrl(CURRENT_URL);

// Terminobjekt anlegen
$date = new TableDate($g_db);

if($get_dat_id > 0)
{
    $date->readData($get_dat_id);
    
    if($get_copy)
    {
        $date->setValue('dat_id', 0);
        $get_dat_id = 0;
    }
    
    // Pruefung, ob der Termin zur aktuellen Organisation gehoert bzw. global ist
    if($date->editRight() == false)
    {
        $g_message->show($g_l10n->get('SYS_NO_RIGHTS'));
    }
}
else
{
    // bei neuem Termin Datum mit aktuellen Daten vorbelegen
    $date->setValue('dat_begin', date('Y-m-d H:00:00', time()));
    $date->setValue('dat_end', date('Y-m-d H:00:00', time()+3600));
    
    // wurde ein Kalender uebergeben, dann diesen vorbelegen
    if(strlen($get_headline) > 0)
    {
        $sql = 'SELECT cat_id FROM '.TBL_CATEGORIES.' WHERE cat_name = \''.$get_headline.'\'';
        $g_db->query($sql);
        $row = $g_db->fetch_array();
        $date->setValue('dat_cat_id', $row['cat_id']);
    }
}

if(isset($_SESSION['dates_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
	$date->setArray($_SESSION['dates_request']);

    // ausgewaehlte Rollen vorbelegen
    $numberRoleSelect = 1;
    $arrRoles = array();
    while(isset($_SESSION['dates_request']['role_'.$numberRoleSelect]))
    {
        $arrRoles[] = $_SESSION['dates_request']['role_'.$numberRoleSelect];
        $numberRoleSelect++;
    }
    $date->setVisibleRoles($arrRoles);
    
    $date_from  = $_SESSION['dates_request']['date_from'];
    $time_from  = $_SESSION['dates_request']['time_from'];
    $date_to    = $_SESSION['dates_request']['date_to'];
    $time_to    = $_SESSION['dates_request']['time_to'];
    if(array_key_exists('date_login', $_SESSION['dates_request']))
    {
        $date_login = $_SESSION['dates_request']['date_login'];
    }
    else
    {
        $date_login = 0;
    }
    if(array_key_exists('date_assign_yourself', $_SESSION['dates_request']))
    {
        $date_assign_yourself = $_SESSION['dates_request']['date_assign_yourself'];
    }
    else
    {
        $date_assign_yourself = 0;
    }
    
    unset($_SESSION['dates_request']);
}
else
{
    // Zeitangaben von/bis aus Datetime-Feld aufsplitten
    $date_from = $date->getValue('dat_begin', $g_preferences['system_date']);
    $time_from = $date->getValue('dat_begin', $g_preferences['system_time']);

    // Datum-Bis nur anzeigen, wenn es sich von Datum-Von unterscheidet
    $date_to = $date->getValue('dat_end', $g_preferences['system_date']);
    $time_to = $date->getValue('dat_end', $g_preferences['system_time']);
    
    if($get_dat_id == 0)
    {
        // Sichtbar fuer alle wird per Default vorbelegt
        $date->setVisibleRoles(array('-1'));
    }
    else
    {
        $date->getVisibleRoles();
    }
}

// Html-Kopf ausgeben
if($get_dat_id > 0)
{
    $g_layout['title'] = $g_l10n->get('SYS_EDIT_VAR', $get_headline);
}
else
{
    $g_layout['title'] = $g_l10n->get('SYS_CREATE_VAR', $get_headline);
}

$g_layout['header'] = '
<script type="text/javascript" src="'.$g_root_path.'/adm_program/system/js/date-functions.js"></script>
<script type="text/javascript" src="'.$g_root_path.'/adm_program/libs/calendar/calendar-popup.js"></script>
<link rel="stylesheet" href="'.THEME_PATH. '/css/calendar.css" type="text/css" />
<script type="text/javascript"><!--
    // Funktion blendet Zeitfelder ein/aus
    function setAllDay()
    {
		if ($("#dat_all_day:checked").val() !== undefined)
        {
			$("#time_from").hide();
			$("#time_to").hide();
        }
        else
        {
			$("#time_from").show("slow");
			$("#time_to").show("slow");
        }
    }
	
	
	function setDateParticipation()
	{
		if ($("#date_login:checked").val() !== undefined)
		{
			$("#admAssignYourself").show("slow");
			$("#admMaxMembers").show("slow");
		}
		else
		{
			$("#admAssignYourself").hide();
			$("#admMaxMembers").hide();
		}
	}

    // Funktion belegt das Datum-bis entsprechend dem Datum-Von
    function setDateTo()
    {
        var dateFrom = Date.parseDate($("#date_from").val(), "'.$g_preferences['system_date'].'");
        var dateTo   = Date.parseDate($("#date_to").val(), "'.$g_preferences['system_date'].'");

        if(dateFrom.getTime() > dateTo.getTime())
        {
            $("#date_to").val($("#date_from").val());
        }
    }

    var calPopup = new CalendarPopup("calendardiv");
    calPopup.setCssPrefix("calendar");
    var numberRoleSelect = 1;

    function addRoleSelection(roleID)
    {
        $.ajax({url: "dates_function.php?mode=5&number_rol_select=" + numberRoleSelect + "&rol_id=" + roleID, type: "GET", async: false, 
            success: function(data){
                if(numberRoleSelect == 1)
                {
                    $("#liRoles").html($("#liRoles").html() + data);
                }
                else
                {
                    number = numberRoleSelect - 1;
                    $("#roleID_"+number).after(data);
                }
            }});
        numberRoleSelect++;
    }
    
    function removeRoleSelection(id)
    {
        $("#"+id).hide("slow");
        $("#"+id).remove();
    }
	
	function setLocationCountry()
	{
		if($("#dat_location").val().length > 0)
		{
			$("#admDateCountry").show("slow");
		}
		else
		{
			$("#admDateCountry").hide();
		}
	}

    $(document).ready(function() 
    {
        setAllDay();
		setDateParticipation();
		setLocationCountry();
        $("#dat_headline").focus();
		
		$("#date_login").click(function() {setDateParticipation();});
		$("#dat_all_day").click(function() {setAllDay();});
		$("#dat_location").change(function() {setLocationCountry();});';

        // alle Rollen anzeigen, die diesen Termin sehen duerfen
        foreach($date->getVisibleRoles() as $key => $roleID)
        {
            $g_layout['header'] .= 'addRoleSelection('.$roleID.');';
        }

        if($date->getValue('dat_rol_id') > 0)
        {
            $dateRoleID = $date->getValue('dat_rol_id');
        }
        else
        {
            $dateRoleID = '0';
        }
    $g_layout['header'] .= '}); 

    var dateRoleID = '.$dateRoleID.';
    //öffnet Messagefenster
    function submitForm()
    {
        if(dateRoleID > 0 && !document.getElementById("date_login").checked)
        {
            var msg_result = confirm("'.$g_l10n->get('DAT_REMOVE_APPLICATION').'");
            if(msg_result)
            {
                $("#formDate").submit();
            }
        }
        else
        {
            $("#formDate").submit();
        }
    }
//--></script>';

//Script für BBCode laden
$javascript = "";
if ($g_preferences['enable_bbcode'] == 1)
{
    $javascript = getBBcodeJS('dat_description');
}
$g_layout['header'] .= $javascript;
require(SERVER_PATH. '/adm_program/system/overall_header.php');
 
// Html des Modules ausgeben
echo '
<form method="post" id="formDate" action="'.$g_root_path.'/adm_program/modules/dates/dates_function.php?dat_id='.$get_dat_id.'&amp;mode=1">
<div class="formLayout" id="edit_dates_form">
    <div class="formHead">'. $g_layout['title']. '</div>
    <div class="formBody">
		<div class="groupBox" id="admTitleLocation">
			<div class="groupBoxHeadline" id="admTitleLocationHead">
				<a class="iconShowHide" href="javascript:showHideBlock(\'admTitleLocationBody\', \''.$g_l10n->get('SYS_FADE_IN').'\', \''.$g_l10n->get('SYS_HIDE').'\')"><img
				id="admTitleLocationBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$g_l10n->get('SYS_HIDE').'" title="'.$g_l10n->get('SYS_HIDE').'" /></a>'.$g_l10n->get('SYS_TITLE').' & '.$g_l10n->get('DAT_LOCATION').'
			</div>

			<div class="groupBoxBody" id="admTitleLocationBody">
				<ul class="formFieldList">
					<li>
						<dl>
							<dt><label for="dat_headline">'.$g_l10n->get('SYS_TITLE').':</label></dt>
							<dd>
								<input type="text" id="dat_headline" name="dat_headline" style="width: 345px;" maxlength="100" value="'. $date->getValue('dat_headline'). '" />
								<span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
							</dd>
						</dl>
					</li>
					<li>
						<dl>
							<dt><label for="dat_location">'.$g_l10n->get('DAT_LOCATION').':</label></dt>
							<dd>
								<input type="text" id="dat_location" name="dat_location" style="width: 345px;" maxlength="50" value="'. $date->getValue('dat_location').$date->getValue('dat_country'). '" />';
								if($g_preferences['dates_show_map_link'])
								{
									echo '<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DAT_LOCATION_LINK&amp;inline=true"><img 
										onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DAT_LOCATION_LINK\',this)" 
										onmouseout="ajax_hideTooltip()" class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>';
								}
							echo '</dd>
						</dl>
					</li>';

					if($g_preferences['dates_show_map_link'])
					{
						if(strlen($date->getValue('dat_country')) == 0 && $get_dat_id == 0)
						{
							$date->setValue('dat_country', $g_preferences['default_country']);
						}
						echo '<li id="admDateCountry">
							<dl>
								<dt><label for="dat_location">'.$g_l10n->get('SYS_COUNTRY').':</label></dt>
								<dd>
									<select size="1" id="dat_country" name="dat_country">
										<option value="">- '.$g_l10n->get('SYS_PLEASE_CHOOSE').' -</option>';
										foreach($g_l10n->getCountries() as $key => $value)
										{
											echo '<option value="'.$key.'" ';
											if($value == $date->getValue('dat_country'))
											{
												echo ' selected="selected" ';
											}
											echo '>'.$value.'</option>';
										}
									echo '</select>
								</dd>
							</dl>
						</li>';
					}

					if($g_preferences['dates_show_rooms']==1) //nur wenn Raumauswahl aktiviert ist
					{
						echo'<li>
								<dl>
									<dt><label for="dat_room_id">'.$g_l10n->get('SYS_ROOM').':</label></dt>
									<dd>
										<select id="dat_room_id" name="dat_room_id" size="1">
											<option value="0"';
											if($date->getValue('dat_room_id') == 0)
											{
												echo ' selected="selected" ';
											}
											echo '>'.$g_l10n->get('SYS_NONE').'</option>';
				
											$sql = 'SELECT room_id, room_name, room_capacity, room_overhang 
													  FROM '.TBL_ROOMS.'
													 ORDER BY room_name';
											$result = $g_db->query($sql);
				
											while($row = $g_db->fetch_array($result))
											{
												echo '<option value="'.$row['room_id'].'"';
													if($date->getValue('dat_room_id') == $row['room_id'])
													{
														echo ' selected="selected" ';
													}
												echo '>'.$row['room_name'].' ('.$row['room_capacity'].'+'.$row['room_overhang'].')</option>';
											}
										echo '</select>
									</dd>
								</dl>
							</li>';
					}
				echo '</ul>
			</div>
		</div>

		<div class="groupBox" id="admPeriodCalendar">
			<div class="groupBoxHeadline" id="admPeriodCalendarHead">
				<a class="iconShowHide" href="javascript:showHideBlock(\'admPeriodCalendarBody\', \''.$g_l10n->get('SYS_FADE_IN').'\', \''.$g_l10n->get('SYS_HIDE').'\')"><img
				id="admPeriodCalendarBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$g_l10n->get('SYS_HIDE').'" title="'.$g_l10n->get('SYS_HIDE').'" /></a>'.$g_l10n->get('SYS_PERIOD').' & '.$g_l10n->get('DAT_CALENDAR').'
			</div>

			<div class="groupBoxBody" id="admPeriodCalendarBody">
				<ul class="formFieldList">
					<li>
						<dl>
							<dt><label for="date_from">'.$g_l10n->get('SYS_START').':</label></dt>
							<dd>
								<span>
									<input type="text" id="date_from" name="date_from" onchange="javascript:setDateTo();" size="10" maxlength="10" value="'.$date_from.'" />
									<a class="iconLink" id="anchor_date_from" href="javascript:calPopup.select(document.getElementById(\'date_from\'),\'anchor_date_from\',\''.$g_preferences['system_date'].'\',\'date_from\',\'date_to\',\'time_from\',\'time_to\');"><img 
										src="'.THEME_PATH.'/icons/calendar.png" alt="'.$g_l10n->get('SYS_SHOW_CALENDAR').'" title="'.$g_l10n->get('SYS_SHOW_CALENDAR').'" /></a>
									<span id="calendardiv" style="position: absolute; visibility: hidden; "></span>
								</span>
								<span style="margin-left: 10px;">
									<input type="text" id="time_from" name="time_from" size="5" maxlength="5" value="'.$time_from.'" />
									<span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
								</span>
								<span style="margin-left: 15px;">
									<input type="checkbox" id="dat_all_day" name="dat_all_day" ';
									if($date->getValue('dat_all_day') == 1)
									{
										echo ' checked="checked" ';
									}
									echo ' value="1" />
									<label for="dat_all_day">'.$g_l10n->get('DAT_ALL_DAY').'</label>
								</span>
							</dd>
						</dl>
					</li>
					<li>
						<dl>
							<dt><label for="date_to">'.$g_l10n->get('SYS_END').':</label></dt>
							<dd>
								<span>
									<input type="text" id="date_to" name="date_to" size="10" maxlength="10" value="'.$date_to.'" />
									<a class="iconLink" id="anchor_date_to" href="javascript:calPopup.select(document.getElementById(\'date_to\'),\'anchor_date_to\',\''.$g_preferences['system_date'].'\',\'date_from\',\'date_to\',\'time_from\',\'time_to\');"><img 
										src="'.THEME_PATH.'/icons/calendar.png" alt="'.$g_l10n->get('SYS_SHOW_CALENDAR').'" title="'.$g_l10n->get('SYS_SHOW_CALENDAR').'" /></a>
								</span>
								<span style="margin-left: 10px;">
									<input type="text" id="time_to" name="time_to" size="5" maxlength="5" value="'.$time_to.'" />
									<span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'" id="timeToMandatory"';
									if($date->getValue('dat_repeat_type') != 0)
									{
										echo ' style="visibility: hidden;"';
									}
									echo '>*</span>
								</span>
							</dd>
						</dl>
					</li>
					<li>
						<dl>
							<dt><label for="dat_cat_id">'.$g_l10n->get('DAT_CALENDAR').':</label></dt>
							<dd>
								'.FormElements::generateCategorySelectBox('DAT', $date->getValue('dat_cat_id'), 'dat_cat_id').'
								<span class="mandatoryFieldMarker" title="'.$g_l10n->get('SYS_MANDATORY_FIELD').'">*</span>
							</dd>
						</dl>
					</li>
				</ul>
			</div>
		</div>';
	  
		echo'
		<div class="groupBox" id="admVisibilityRegistration">
			<div class="groupBoxHeadline" id="admVisibilityRegistrationHead">
				<a class="iconShowHide" href="javascript:showHideBlock(\'admVisibilityRegistrationBody\', \''.$g_l10n->get('SYS_FADE_IN').'\', \''.$g_l10n->get('SYS_HIDE').'\')"><img
				id="admVisibilityRegistrationBodyImage" src="'. THEME_PATH. '/icons/triangle_open.gif" alt="'.$g_l10n->get('SYS_HIDE').'" title="'.$g_l10n->get('SYS_HIDE').'" /></a>'.$g_l10n->get('DAT_VISIBILITY').' & '.$g_l10n->get('SYS_REGISTRATION').'
			</div>

			<div class="groupBoxBody" id="admVisibilityRegistrationBody">
				<ul class="formFieldList">
					<li id="liRoles"></li>
					<li>
						<dl>
							<dt>&nbsp;</dt>
							<dd><span id="add_attachment" class="iconTextLink">
									<a href="javascript:addRoleSelection(0)"><img
									src="'. THEME_PATH. '/icons/add.png" alt="'.$g_l10n->get('DAT_ADD_ROLE').'" /></a>
									<a href="javascript:addRoleSelection(0)">'.$g_l10n->get('DAT_ADD_ROLE').'</a>
								</span></dd>
						</dl>
					</li>';

					// besitzt die Organisation eine Elternorga oder hat selber Kinder, so kann die Ankuendigung auf "global" gesetzt werden
					if($g_current_organization->getValue('org_org_id_parent') > 0
						|| $g_current_organization->hasChildOrganizations())
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
									<label for="dat_global">'.$g_l10n->get('SYS_ENTRY_MULTI_ORGA').'</label>
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
								<input type="checkbox" id="date_login" name="date_login"';
								if($date->getValue('dat_rol_id') > 0 || $date_login == 1)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
								<label for="date_login">'.$g_l10n->get('DAT_REGISTRATION_POSSIBLE').'</label>
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
								<input type="checkbox" id="date_assign_yourself" name="date_assign_yourself"';
								if($date->getValue('dat_rol_id') > 0 || $date_assign_yourself == 1)
								{
									echo ' checked="checked" ';
								}
								echo ' value="1" />
								<label for="date_assign_yourself">'.$g_l10n->get('DAT_PARTICIPATE_AT_DATE').'</label>
								<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DAT_PARTICIPATE_AT_DATE_DESC&amp;inline=true"><img 
									onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DAT_PARTICIPATE_AT_DATE_DESC\',this)" 
									onmouseout="ajax_hideTooltip()" class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
							</dd>
						</dl>
					</li>
					<li id="admMaxMembers">
						<dl>
							<dt><label for="dat_max_members">'.$g_l10n->get('DAT_PARTICIPANTS_LIMIT').':</label></dt>
							<dd>
								<input type="text" id="dat_max_members" name="dat_max_members" style="width: 50px;" maxlength="5" value="'.($date->getValue('dat_max_members') ? $date->getValue('dat_max_members') : '').'" />
								<a rel="colorboxHelp" href="'. $g_root_path. '/adm_program/system/msg_window.php?message_id=DAT_MAX_MEMBERS&amp;inline=true"><img 
									onmouseover="ajax_showTooltip(event,\''.$g_root_path.'/adm_program/system/msg_window.php?message_id=DAT_MAX_MEMBERS\',this)" 
									onmouseout="ajax_hideTooltip()" class="iconHelpLink" src="'. THEME_PATH. '/icons/help.png" alt="Help" title="" /></a>
							</dd>
						</dl>
					</li>
				</ul>
			</div>
		</div>
            
		<ul class="formFieldList">';
            if ($g_preferences['enable_bbcode'] == 1)
            {
               printBBcodeIcons();
            }
            echo '
            <li>
                <dl>
                    <dt><label for="dat_description">'.$g_l10n->get('SYS_DESCRIPTION').':</label>';
                        if($g_preferences['enable_bbcode'] == 1)
                        {
                            printEmoticons();
                        }
                    echo '</dt>
                    <dd>
                        <textarea id="dat_description" name="dat_description" style="width: 345px;" rows="10" cols="40">'. $date->getValue('dat_description'). '</textarea>
                    </dd>
                </dl>
            </li>
        </ul>

        <hr />';

        if($date->getValue('dat_usr_id_create') > 0)
        {
            // Infos der Benutzer, die diesen DS erstellt und geaendert haben
            echo '<div class="editInformation">';
                $user_create = new User($g_db, $date->getValue('dat_usr_id_create'));
                echo $g_l10n->get('SYS_CREATED_BY', $user_create->getValue('FIRST_NAME'). ' '. $user_create->getValue('LAST_NAME'), $date->getValue('dat_timestamp_create'));

                if($date->getValue('dat_usr_id_change') > 0)
                {
                    $user_change = new User($g_db, $date->getValue('dat_usr_id_change'));
                    echo '<br />'.$g_l10n->get('SYS_LAST_EDITED_BY', $user_change->getValue('FIRST_NAME'). ' '. $user_change->getValue('LAST_NAME'), $date->getValue('dat_timestamp_change'));
                }
            echo '</div>';
        }

        echo '<div class="formSubmit">
            <button id="btnSave" type="button" onclick="javascript:submitForm();"><img src="'. THEME_PATH. '/icons/disk.png" alt="'.$g_l10n->get('SYS_SAVE').'" />&nbsp;'.$g_l10n->get('SYS_SAVE').'</button>
        </div>
    </div>
</div>
</form>

<ul class="iconTextLinkList">
    <li>
        <span class="iconTextLink">
            <a href="'.$g_root_path.'/adm_program/system/back.php"><img
            src="'. THEME_PATH. '/icons/back.png" alt="'.$g_l10n->get('SYS_BACK').'" /></a>
            <a href="'.$g_root_path.'/adm_program/system/back.php">'.$g_l10n->get('SYS_BACK').'</a>
        </span>
    </li>
</ul>';

require(SERVER_PATH. '/adm_program/system/overall_footer.php');
?>