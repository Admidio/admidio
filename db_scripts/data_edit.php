<?php
/******************************************************************************
 * Edit data of database
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
 
function addDaysToDate($days)
{
	$time = time() + (60*60*24*$days);
	return date('Y', $time).'-'.date('m', $time).'-'.date('d', $time);
}

// set birthday of user to today 25 years ago
$day   = date('d', time());
$month = date('m', time());
$year  = date('Y', time()) - 25;

$sql = 'UPDATE '.TBL_USER_DATA.' SET usd_value = \''.$year.'-'.$month.'-'.$day.'\'
		 WHERE usd_usr_id = 202
		   AND usd_usf_id = 10 ';
$db->query($sql);

// set name of role to 4 days in future
$sql = 'UPDATE '.TBL_ROLES.' SET rol_name = \'Termin '.addDaysToDate(4).' 19:00 - 4\'
		 WHERE rol_id = 8 ';
$db->query($sql);

// set membership of date role
$sql = 'UPDATE '.TBL_MEMBERS.' SET mem_begin = \''.addDaysToDate(4).'\'
		 WHERE mem_id = 500 ';
$db->query($sql);

$sql = 'UPDATE '.TBL_MEMBERS.' SET mem_begin = \''.addDaysToDate(2).'\'
		 WHERE mem_id IN (501, 502, 503) ';
$db->query($sql);

$sql = 'UPDATE '.TBL_MEMBERS.' SET mem_begin = \''.addDaysToDate(1).'\'
		 WHERE mem_id = 504 ';
$db->query($sql);

// set date of announcements
$sql = 'UPDATE '.TBL_ANNOUNCEMENTS.' SET ann_timestamp_create = \''.addDaysToDate(7).'\'
		 WHERE ann_id = 1 ';
$db->query($sql);

$sql = 'UPDATE '.TBL_ANNOUNCEMENTS.' SET ann_timestamp_create = \''.addDaysToDate(3).'\'
                                       , ann_timestamp_change = \''.addDaysToDate(2).'\'
		 WHERE ann_id = 2 ';
$db->query($sql);

// set dates of events
$sql = 'UPDATE '.TBL_DATES.' SET dat_begin = \''.addDaysToDate(14).'\'
                               , dat_end = \''.addDaysToDate(14).'\'
		 WHERE dat_id = 3 ';
$db->query($sql);

$sql = 'UPDATE '.TBL_DATES.' SET dat_begin = \''.addDaysToDate(4).'\'
                               , dat_end = \''.addDaysToDate(4).'\'
		 WHERE dat_id = 4 ';
$db->query($sql);

$sql = 'UPDATE '.TBL_DATES.' SET dat_begin = \''.addDaysToDate(30).'\'
                               , dat_end = \''.addDaysToDate(35).'\'
		 WHERE dat_id = 5 ';
$db->query($sql);

$sql = 'UPDATE '.TBL_DATES.' SET dat_begin = \''.addDaysToDate(10).'\'
                               , dat_end = \''.addDaysToDate(10).'\'
		 WHERE dat_id = 6 ';
$db->query($sql);

$sql = 'UPDATE '.TBL_DATES.' SET dat_begin = \''.addDaysToDate(-1).'\'
                               , dat_end = \''.addDaysToDate(0).'\'
		 WHERE dat_id = 7 ';
$db->query($sql);


$sql = 'UPDATE '.TBL_FOLDERS.' SET fol_timestamp = \''.addDaysToDate(-7).'\'
		 WHERE fol_id = 1 ';
$db->query($sql);


$sql = 'UPDATE '.TBL_GUESTBOOK.' SET gbo_timestamp_create = \''.addDaysToDate(-14).'\'
		 WHERE gbo_id = 1 ';
$db->query($sql);

$sql = 'UPDATE '.TBL_GUESTBOOK.' SET gbo_timestamp_create = \''.addDaysToDate(-5).'\'
		 WHERE gbo_id = 2 ';
$db->query($sql);


$sql = 'UPDATE '.TBL_GUESTBOOK_COMMENTS.' SET gbc_timestamp_create = \''.addDaysToDate(-4).'\'
		 WHERE gbc_id = 1 ';
$db->query($sql);


$sql = 'UPDATE '.TBL_LINKS.' SET lnk_timestamp_create = \''.addDaysToDate(-4).'\'
                               , lnk_timestamp_change = \''.addDaysToDate(-3).'\'
		 WHERE lnk_id = 1 ';
$db->query($sql);

$sql = 'UPDATE '.TBL_LINKS.' SET lnk_timestamp_create = \''.addDaysToDate(-4).'\'
		 WHERE lnk_id = 2 ';
$db->query($sql);

$sql = 'UPDATE '.TBL_LINKS.' SET lnk_timestamp_create = \''.addDaysToDate(-4).'\'
		 WHERE lnk_id = 3 ';
$db->query($sql);


?>