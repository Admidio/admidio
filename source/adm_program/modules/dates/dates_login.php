<?php
/******************************************************************************
 * Anmeldung zu einem Termin
 *
 * Copyright    : (c) 2004 - 2009 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

require_once('../../system/common.php');
require_once('../../system/classes/table_date.php');
require_once('../../system/classes/table_members.php');

$req_dat_id = 0;
if(isset($_GET['dat_id']))
{
    $req_dat_id = $_GET['dat_id'];
}

$date   = new TableDate($g_db, $_GET['dat_id']);

if($_GET['login'] != 0)
{
    // Benutzer zum Termin anmelden
    $member = new TableMembers($g_db);
    $member->startMembership($date->getValue('dat_rol_id'),$g_current_user->getValue('usr_id'));
    $message = $g_l10n->get('DAT_PHR_ATTEND_DATE', $date->getValue('dat_headline'), $date->getValue('dat_begin'));
    
}
else
{
    $sql = 'DELETE FROM '.TBL_MEMBERS.' 
             WHERE mem_rol_id = "'.$date->getValue('dat_rol_id').'" 
               AND mem_usr_id = "'.$g_current_user->getValue('usr_id').'"';
    $g_db->query($sql);
    $message = $g_l10n->get('DAT_PHR_CANCEL_DATE', $date->getValue('dat_headline'), $date->getValue('dat_begin'));
}

$g_message->setForwardUrl($_SESSION['navigation']->getUrl());
$g_message->show($message, $g_l10n->get('DAT_ATTEND'));
?>
