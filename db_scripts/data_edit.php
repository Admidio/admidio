<?php
/******************************************************************************
 * Edit data of database
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// set birthday of user to today 25 years ago
$day   = date('d', time());
$month = date('m', time());
$year  = date('Y', time()) - 25;

$sql = 'UPDATE '.TBL_USER_DATA.' SET usd_value = \''.$year.'-'.$month.'-'.$day.'\'
		 WHERE usd_usr_id = 202
		   AND usd_usf_id = 10 ';
$db->query($sql);

// set name of role to 4 days in future
$time = time() + (60*60*24*4);
$sql = 'UPDATE '.TBL_ROLES.' SET rol_name = \'Termin '.date('Y', $time).'-'.date('m', $time).'-'.date('d', $time).' 19:00 - 4\'
		 WHERE rol_id = 8 ';
$db->query($sql);

// set membership of date role
$time = time() - (60*60*24*4);
$sql = 'UPDATE '.TBL_MEMBERS.' SET mem_begin = \''.date('Y', $time).'-'.date('m', $time).'-'.date('d', $time).'\'
		 WHERE mem_id = 500 ';
$db->query($sql);

$time = time() - (60*60*24*2);
$sql = 'UPDATE '.TBL_MEMBERS.' SET mem_begin = \''.date('Y', $time).'-'.date('m', $time).'-'.date('d', $time).'\'
		 WHERE mem_id IN (501, 502, 503) ';
$db->query($sql);

$time = time() - (60*60*24*1);
$sql = 'UPDATE '.TBL_MEMBERS.' SET mem_begin = \''.date('Y', $time).'-'.date('m', $time).'-'.date('d', $time).'\'
		 WHERE mem_id = 504 ';
$db->query($sql);

// set date of announcements
$time = time() - (60*60*24*7);
$sql = 'UPDATE '.TBL_ANNOUNCEMENTS.' SET ann_timestamp_create = \''.date('Y', $time).'-'.date('m', $time).'-'.date('d', $time).'\'
		 WHERE ann_id = 1 ';
$db->query($sql);

$time = time() - (60*60*24*3);
$time2 = time() - (60*60*24*2);
$sql = 'UPDATE '.TBL_ANNOUNCEMENTS.' SET ann_timestamp_create = \''.date('Y', $time).'-'.date('m', $time).'-'.date('d', $time).'\'
                                       , ann_timestamp_change = \''.date('Y', $time2).'-'.date('m', $time2).'-'.date('d', $time2).'\'
		 WHERE ann_id = 2 ';
$db->query($sql);

?>