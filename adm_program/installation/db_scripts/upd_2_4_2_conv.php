<?php
/**
 ***********************************************************************************************
 * Data conversion for version 2.4.2
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// alter ip columns for IPv6
if($gDbType === 'mysql')
{
    $sql = 'ALTER TABLE '.TBL_AUTO_LOGIN.' MODIFY COLUMN atl_ip_address varchar(39) NOT NULL';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_GUESTBOOK.' MODIFY COLUMN gbo_ip_address varchar(39) NOT NULL';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_GUESTBOOK_COMMENTS.' MODIFY COLUMN gbc_ip_address varchar(39) NOT NULL';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_SESSIONS.' MODIFY COLUMN ses_ip_address varchar(39) NOT NULL';
    $gDb->query($sql, false);
}
else
{
    $sql = 'ALTER TABLE '.TBL_AUTO_LOGIN.' ALTER COLUMN atl_ip_address TYPE varchar(39)';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_GUESTBOOK.' ALTER COLUMN gbo_ip_address TYPE varchar(39)';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_GUESTBOOK_COMMENTS.' ALTER COLUMN gbc_ip_address TYPE varchar(39)';
    $gDb->query($sql, false);
    $sql = 'ALTER TABLE '.TBL_SESSIONS.' ALTER COLUMN ses_ip_address TYPE varchar(39)';
    $gDb->query($sql, false);
}
