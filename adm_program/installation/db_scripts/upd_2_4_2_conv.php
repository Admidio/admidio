<?php
/******************************************************************************
 * Data conversion for version 2.4.2
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// alter ip columns for IPv6
if($gDbType == 'mysql')
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
