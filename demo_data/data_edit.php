<?php
/**
 ***********************************************************************************************
 * Edit data of database
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @param  int    $days Count of days for offset
 * @param  bool   $sub  If true the days are subtracted
 * @return string
 */
function addDaysToDate($days, $sub = false)
{
    $now = new DateTime();
    $daysOffset = new DateInterval('P'.$days.'D');
    $newDate = $sub ? $now->sub($daysOffset) : $now->add($daysOffset);

    return $newDate->format('Y-m-d');
}

// set birthday of user to today 25 years ago
$now = new DateTime();
$yearsBack = new DateInterval('P25Y');
$birthday = $now->sub($yearsBack)->format('Y-m-d');

$sqlQueries = array();

$sqlQueries[] = 'UPDATE '.TBL_USER_DATA.'
                    SET usd_value = \''.$birthday.'\'
                  WHERE usd_usr_id = 202
                    AND usd_usf_id = 10 ';

// set name of role to 4 days in future
$sqlQueries[] = 'UPDATE '.TBL_ROLES.'
                    SET rol_name = \''.$gL10n->get('DAT_DATE').' '.addDaysToDate(4).' 19:00 - 4\'
                  WHERE rol_id = 8 ';

// set membership of date role
$sqlQueries[] = 'UPDATE '.TBL_MEMBERS.'
                    SET mem_begin = \''.addDaysToDate(4, true).'\'
                  WHERE mem_id = 500 ';

$sqlQueries[] = 'UPDATE '.TBL_MEMBERS.'
                    SET mem_begin = \''.addDaysToDate(2, true).'\'
                  WHERE mem_id IN (501, 502, 503) ';

$sqlQueries[] = 'UPDATE '.TBL_MEMBERS.'
                    SET mem_begin = \''.addDaysToDate(1, true).'\'
                  WHERE mem_id = 504 ';

$sqlQueries[] = 'UPDATE '.TBL_REGISTRATIONS.'
                    SET reg_timestamp = \''.addDaysToDate(2, true).' 13:45:23\'
                  WHERE reg_id IN (1, 3) ';

$sqlQueries[] = 'UPDATE '.TBL_REGISTRATIONS.'
                    SET reg_timestamp = \''.addDaysToDate(1, true).' 20:54:12\'
                  WHERE reg_id IN (2) ';

// set date of announcements
$sqlQueries[] = 'UPDATE '.TBL_ANNOUNCEMENTS.'
                    SET ann_timestamp_create = \''.addDaysToDate(7, true).' 09:12:34\'
                  WHERE ann_id = 1 ';

$sqlQueries[] = 'UPDATE '.TBL_ANNOUNCEMENTS.'
                    SET ann_timestamp_create = \''.addDaysToDate(3, true).' 11:30:59\'
                      , ann_timestamp_change = \''.addDaysToDate(2, true).' 19:21:32\'
                  WHERE ann_id = 2 ';

$sqlQueries[] = 'UPDATE '.TBL_ANNOUNCEMENTS.'
                    SET ann_timestamp_create = \''.addDaysToDate(0).' 00:15:33\'
                  WHERE ann_id = 3 ';

// set dates of events
$sqlQueries[] = 'UPDATE '.TBL_DATES.'
                    SET dat_begin = \''.addDaysToDate(14).' 16:00:00\'
                      , dat_end   = \''.addDaysToDate(14).' 18:00:00\'
                  WHERE dat_id = 3 ';

$sqlQueries[] = 'UPDATE '.TBL_DATES.'
                    SET dat_begin = \''.addDaysToDate(4).' 19:00:00\'
                      , dat_end   = \''.addDaysToDate(4).' 23:30:00\'
                  WHERE dat_id = 4 ';

$sqlQueries[] = 'UPDATE '.TBL_DATES.'
                    SET dat_begin = \''.addDaysToDate(30).' 00:00:00\'
                      , dat_end   = \''.addDaysToDate(35).' 00:00:00\'
                  WHERE dat_id = 5 ';

$sqlQueries[] = 'UPDATE '.TBL_DATES.'
                    SET dat_begin = \''.addDaysToDate(10).' 15:00:00\'
                      , dat_end   = \''.addDaysToDate(10).' 19:00:00\'
                  WHERE dat_id = 6 ';

$sqlQueries[] = 'UPDATE '.TBL_DATES.'
                    SET dat_begin = \''.addDaysToDate(2, true).' 05:30:00\'
                      , dat_end   = \''.addDaysToDate(1, true).' 15:00:00\'
                  WHERE dat_id = 7 ';

$sqlQueries[] = 'UPDATE '.TBL_DATES.'
                    SET dat_begin = \''.addDaysToDate(4, true).' 17:00:00\'
                      , dat_end   = \''.addDaysToDate(4, true).' 18:30:00\'
                  WHERE dat_id = 8 ';

$sqlQueries[] = 'UPDATE '.TBL_DATES.'
                    SET dat_begin = \''.addDaysToDate(3).' 17:00:00\'
                      , dat_end   = \''.addDaysToDate(3).' 18:30:00\'
                  WHERE dat_id = 9 ';

$sqlQueries[] = 'UPDATE '.TBL_DATES.'
                    SET dat_begin = \''.addDaysToDate(10).' 17:00:00\'
                      , dat_end   = \''.addDaysToDate(10).' 18:30:00\'
                  WHERE dat_id = 10 ';

$sqlQueries[] = 'UPDATE '.TBL_DATES.'
                    SET dat_begin = \''.addDaysToDate(17).' 17:00:00\'
                      , dat_end   = \''.addDaysToDate(17).' 18:30:00\'
                  WHERE dat_id = 11 ';

$sqlQueries[] = 'UPDATE '.TBL_DATES.'
                    SET dat_begin = \''.addDaysToDate(24).' 17:00:00\'
                      , dat_end   = \''.addDaysToDate(24).' 18:30:00\'
                  WHERE dat_id = 12 ';

$sqlQueries[] = 'UPDATE '.TBL_DATES.'
                    SET dat_begin = \''.addDaysToDate(31).' 17:00:00\'
                      , dat_end   = \''.addDaysToDate(31).' 18:30:00\'
                  WHERE dat_id = 13 ';

$sqlQueries[] = 'UPDATE '.TBL_DATES.'
                    SET dat_begin = \''.addDaysToDate(20).' 13:00:00\'
                      , dat_end   = \''.addDaysToDate(20).' 14:00:00\'
                  WHERE dat_id = 14 ';

$sqlQueries[] = 'UPDATE '.TBL_FOLDERS.'
                    SET fol_timestamp = \''.addDaysToDate(7, true).'\'
                  WHERE fol_id = 1 ';

$sqlQueries[] = 'UPDATE '.TBL_GUESTBOOK.'
                    SET gbo_timestamp_create = \''.addDaysToDate(14, true).' 12:14:42\'
                  WHERE gbo_id = 1 ';

$sqlQueries[] = 'UPDATE '.TBL_GUESTBOOK.'
                    SET gbo_timestamp_create = \''.addDaysToDate(5, true).' 20:16:42\'
                  WHERE gbo_id = 2 ';

$sqlQueries[] = 'UPDATE '.TBL_GUESTBOOK_COMMENTS.'
                    SET gbc_timestamp_create = \''.addDaysToDate(4, true).' 16:23:12\'
                  WHERE gbc_id = 1 ';

$sqlQueries[] = 'UPDATE '.TBL_LINKS.'
                    SET lnk_timestamp_create = \''.addDaysToDate(4, true).'\'
                      , lnk_timestamp_change = \''.addDaysToDate(3, true).'\'
                  WHERE lnk_id = 1 ';

$sqlQueries[] = 'UPDATE '.TBL_LINKS.'
                    SET lnk_timestamp_create = \''.addDaysToDate(4, true).'\'
                  WHERE lnk_id = 2 ';

$sqlQueries[] = 'UPDATE '.TBL_LINKS.'
                    SET lnk_timestamp_create = \''.addDaysToDate(4, true).'\'
                  WHERE lnk_id = 3 ';

foreach ($sqlQueries as $sqlQuery)
{
    $db->queryPrepared($sqlQuery);
}
