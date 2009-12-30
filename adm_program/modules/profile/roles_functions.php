<?php
if ('roles_functions.php' == basename($_SERVER['SCRIPT_FILENAME']))
{
    die('Diese Seite darf nicht direkt aufgerufen werden !');
}
function getRolesFromDatabase($g_db,$user_id,$g_current_organization)
{
	require_once('../../system/common.php');
	// Alle Rollen auflisten, die dem Mitglied zugeordnet sind
	$sql = 'SELECT *
			  FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_ORGANIZATIONS. '
			 WHERE mem_rol_id = rol_id
			   AND mem_begin <= "'.DATE_NOW.'"
			   AND mem_end    > "'.DATE_NOW.'"
			   AND mem_usr_id = '.$user_id.'
			   AND rol_valid  = 1
			   AND rol_cat_id = cat_id
			   AND cat_org_id = org_id
			   AND org_id     = '. $g_current_organization->getValue('org_id'). '
			 ORDER BY org_shortname, cat_sequence, rol_name';
	return $g_db->query($sql);
}
function getFormerRolesFromDatabase($g_db,$user_id,$g_current_organization)
{
	require_once('../../system/common.php');
	$sql    = 'SELECT *
				 FROM '. TBL_MEMBERS. ', '. TBL_ROLES. ', '. TBL_CATEGORIES. ', '. TBL_ORGANIZATIONS. '
				WHERE mem_rol_id = rol_id
				  AND mem_end   <= "'.DATE_NOW.'"
				  AND mem_usr_id = '.$user_id.'
				  AND rol_valid  = 1
				  AND rol_cat_id = cat_id
				  AND cat_org_id = org_id
				  AND org_id     = '. $g_current_organization->getValue('org_id'). '
				ORDER BY org_shortname, cat_sequence, rol_name';
	return $g_db->query($sql);
}
function getRoleMemberships($g_db,$g_current_user,$user,$result_role,$count_role,$directOutput)
{
	$roleMemHTML = "";
	$roleMemHTML = '<ul class="formFieldList" id="role_list">';
			while($row = $g_db->fetch_array($result_role))
			{
				if($g_current_user->viewRole($row['mem_rol_id']) && $row['rol_visible']==1)
				{
					// jede einzelne Rolle anzeigen
					$roleMemHTML .= '<li id="role_'. $row['mem_rol_id']. '">
						<dl>
							<dt>
								'. $row['cat_name']. ' - ';
									if($g_current_user->viewRole($row['mem_rol_id']))
									{
										$roleMemHTML .= '<a href="'. $g_root_path. '/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $row['mem_rol_id']. '" title="'. $row['rol_description']. '">'. $row['rol_name']. '</a>';
									}
									else
									{
										echo $row['rol_name'];
									}
									if($row['mem_leader'] == 1)
									{
										$roleMemHTML .= ' - Leiter';
									}
								$roleMemHTML .= '&nbsp;
							</dt>
							<dd>
								seit '. mysqldate('d.m.y', $row['mem_begin']);
								// Datum für die Bearbeitung der Mitgliedschaft wird vorbereitet
								$rol_from = mysqldatetime('d.m.y', $row['mem_begin']);
								$rol_to = NULL;
								if ($row['mem_end'] != '9999-12-31')
								{
								   $rol_to = mysqldatetime('d.m.y', $row['mem_end']);
								}

								if($g_current_user->assignRoles())
								{
									// Löschen wird nur bei anderen Webmastern ermöglicht
									if (($row['rol_name'] == 'Webmaster' && $g_current_user->getValue('usr_id') != $a_user_id) || ($row['rol_name'] != 'Webmaster'))
									{
										$roleMemHTML .= '
										<a class="iconLink" href="javascript:profileJS.deleteRole('.$row['rol_id'].', \''.$row['rol_name'].'\')"><img
											src="'.THEME_PATH.'/icons/delete.png" alt="Rolle löschen" title="Rolle löschen" /></a>';
									}
									else
									{
										$roleMemHTML .= '
										<a class="iconLink"><img src="'.THEME_PATH.'/icons/dummy.png" alt=""/></a>';
									}
									// Bearbeiten des Datums nicht bei Webmastern möglich
									if ($row['rol_name'] != 'Webmaster')
									{
										$roleMemHTML .= '<a class="iconLink" style="cursor:pointer;" onclick="toggleDetailson('.$row['rol_id'].')"><img
											src="'.THEME_PATH.'/icons/edit.png" alt="Datum ändern" title="Datum ändern" /></a>';
									}
									else
									{
										$roleMemHTML .= '<a class="iconLink"><img src="'.THEME_PATH.'/icons/dummy.png" alt=""/></a>';
									}

								}
							$roleMemHTML .= '</dd>
						</dl>
					</li>
					<li id="mem_rol_'.$row['rol_id'].'" style="text-align: right; visibility: hidden; display: none;">
						<form action="'.$g_root_path.'/adm_program/modules/profile/roles_date.php?usr_id='.$user->getValue("usr_id").'&amp;mode=1&amp;rol_id='.$row['rol_id'].'" method="post">
							<div>
								<label for="begin'.$row['rol_name'].'">Beginn:</label>
								<input type="text" id="begin'.$row['rol_name'].'" name="rol_begin" size="10" maxlength="20" value="'.$rol_from.'"/>&nbsp;
								<label for="end'.$row['rol_name'].'">Ende:</label>
								<input type="text" id="end'.$row['rol_name'].'" name="rol_end" size="10" maxlength="20" value="'.$rol_to.'"/>
								<a class="iconLink" href="javascript:linkaendern(\''.$row['rol_name'].'\',\''.$row['rol_id'].'\')" id="enter'.$row['rol_name'].'"><img src="'.THEME_PATH.'/icons/disk.png" alt="Speichern" title="Speichern"/></a>
								<a class="iconLink" href="javascript:toggleDetailsoff('.$row['rol_id'].')"><img src="'.THEME_PATH.'/icons/delete.png" alt="Abbrechen" title="Abbrechen"/></a>
							</div>
						</form>
					</li>';
					$count_show_roles++;
				}
			}

			if($count_show_roles == 0)
			{
				$roleMemHTML .= 'Diese Person ist kein Mitglied der Organisation '.
				$g_current_organization->getValue('org_longname'). ' bzw. es sind keine Rollen sichtbar.';
			}
		$roleMemHTML .= '</ul>';
	if($directOutput)
	{
		echo $roleMemHTML;
		return "";
	}
	else
	{
		return $roleMemHTML;	
	}
}
function getFormerRoleMemberships($g_db,$g_current_user,$user,$result_role,$count_role,$directOutput)
{
	$formerRoleMemHTML = "";
	$formerRoleMemHTML = '<ul class="formFieldList" id="former_role_list">';
							while($row = $g_db->fetch_array($result_role))
							{
								if($g_current_user->viewRole($row['mem_rol_id']))
								{
									// jede einzelne Rolle anzeigen
									$formerRoleMemHTML .= '
									<li id="former_role_'. $row['mem_rol_id']. '">
										<dl>
											<dt>'.
												$row['cat_name'];
												if($g_current_user->viewRole($row['mem_rol_id']))
												{
													$formerRoleMemHTML .= ' - <a href="'.$g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_id='. $row['mem_rol_id']. '">'. $row['rol_name']. '</a>';
												}
												else
												{
													$formerRoleMemHTML .= ' - '. $row['rol_name'];
												}
												if($row['mem_leader'] == 1)
												{
													$formerRoleMemHTML .= ' - Leiter';
												}
											$formerRoleMemHTML .= '</dt>
											<dd>
												vom '. mysqldate('d.m.y', $row['mem_begin']). '
												bis '. mysqldate('d.m.y', $row['mem_end']);
												if($g_current_user->isWebmaster())
												{
													$formerRoleMemHTML .= '
													<a class="iconLink" href="javascript:profileJS.deleteFormerRole('. $row['rol_id']. ', \''. $row['rol_name']. '\')"><img
														src="'. THEME_PATH. '/icons/delete.png" alt="Rolle löschen" title="Rolle löschen" /></a>';
												}
											$formerRoleMemHTML .= '</dd>
										</dl>
									</li>';
									$count_show_roles++;
								}
							}
							if($count_show_roles == 0 && $count_role > 0)
							{
								$formerRoleMemHTML .= 'Es können keine ehemalige Rollenmitgliedschaften angezeigt werden.';
							}
						$formerRoleMemHTML .= '</ul>';
	if($directOutput)
	{
		echo $formerRoleMemHTML;
		return "";
	}
	else
	{
		return $formerRoleMemHTML;	
	}
}
?>