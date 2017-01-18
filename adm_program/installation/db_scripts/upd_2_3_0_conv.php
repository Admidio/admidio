<?php
/**
 ***********************************************************************************************
 * Data conversion for version 2.3.0
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

 // create new indices
$sql = 'ALTER TABLE '.TBL_PREFERENCES.' DROP INDEX ak_org_id_name';
$gDb->query($sql, false);
$sql = 'ALTER TABLE '.TBL_USERS.' DROP INDEX ak_usr_login_name';
$gDb->query($sql, false);
$sql = 'ALTER TABLE '.TBL_USER_DATA.' DROP INDEX ak_usr_usf_id';
$gDb->query($sql, false);
$sql = 'ALTER TABLE '.TBL_MEMBERS.' DROP INDEX ak_rol_usr_id';
$gDb->query($sql, false);
$sql = 'ALTER TABLE '.TBL_USER_FIELDS.' DROP INDEX ak_name_intern';
$gDb->query($sql, false);

$sql = 'CREATE UNIQUE INDEX IDX_PRF_ORG_ID_NAME ON '.TBL_PREFERENCES.' (prf_org_id, prf_name)';
$gDb->query($sql);
$sql = 'CREATE UNIQUE INDEX IDX_USR_LOGIN_NAME ON '.TBL_USERS.' (usr_login_name)';
$gDb->query($sql);
$sql = 'CREATE UNIQUE INDEX IDX_USD_USR_USF_ID ON '.TBL_USER_DATA.' (usd_usr_id, usd_usf_id)';
$gDb->query($sql);
$sql = 'CREATE UNIQUE INDEX IDX_MEM_ROL_USR_ID ON '.TBL_MEMBERS.' (mem_rol_id, mem_usr_id)';
$gDb->query($sql);
$sql = 'CREATE UNIQUE INDEX IDX_USF_NAME_INTERN ON '.TBL_USER_FIELDS.' (usf_name_intern)';
$gDb->query($sql);

// get id of webmaster
$sql = 'SELECT MIN(mem_usr_id) AS webmaster_id
          FROM '.TBL_MEMBERS.'
    INNER JOIN '.TBL_ROLES.'
            ON rol_id = mem_rol_id
    INNER JOIN '.TBL_CATEGORIES.'
            ON cat_id = rol_cat_id
         WHERE rol_name   = \'Webmaster\'
           AND cat_org_id = (SELECT MIN(org_id) FROM '.TBL_ORGANIZATIONS.')';
$pdoStatement = $gDb->query($sql);
$rowWebmaster = $pdoStatement->fetch();

// if messenger category exists than transform the field definition
$sql = 'SELECT cat_id FROM '.TBL_CATEGORIES.' WHERE cat_type = \'USF\' AND cat_name_intern = \'MESSENGER\' ';
$pdoStatement = $gDb->query($sql);
$rowCategory = $pdoStatement->fetch();

if($pdoStatement->rowCount() > 0)
{
    $sql = 'SELECT usf_id
              FROM '.TBL_USER_FIELDS.'
             WHERE usf_cat_id = '.$rowCategory[0].'
               AND usf_name_intern = \'AOL_INSTANT_MESSENGER\' ';
    $pdoStatement = $gDb->query($sql);
    $rowProfileField = $pdoStatement->fetch();

    if($rowProfileField[0] > 0)
    {
        $sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name = \'INS_AOL_INSTANT_MESSENGER\'
                                             , usf_icon = \'aim.png\'
                                             , usf_description = null
                 WHERE usf_id = '.$rowProfileField[0];
        $gDb->query($sql);
    }

    $sql = 'SELECT usf_id
              FROM '.TBL_USER_FIELDS.'
             WHERE usf_cat_id = '.$rowCategory[0].'
               AND usf_name_intern = \'GOOGLE_TALK\' ';
    $pdoStatement = $gDb->query($sql);
    $rowProfileField = $pdoStatement->fetch();

    if($rowProfileField[0] > 0)
    {
        $sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name = \'INS_GOOGLE_PLUS\'
                                             , usf_name_intern = \'GOOGLE_PLUS\'
                                             , usf_icon = \'google_plus.png\'
                                             , usf_description = \''.$gL10n->get('INS_GOOGLE_PLUS_DESC').'\'
                                             , usf_url = \'https://plus.google.com/#user_content#/posts\'
                 WHERE usf_id = '.$rowProfileField[0];
        $gDb->query($sql);
    }

    $sql = 'SELECT usf_id
              FROM '.TBL_USER_FIELDS.'
             WHERE usf_cat_id = '.$rowCategory[0].'
               AND usf_name_intern = \'ICQ\' ';
    $pdoStatement = $gDb->query($sql);
    $rowProfileField = $pdoStatement->fetch();

    if($rowProfileField[0] > 0)
    {
        $sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name = \'INS_ICQ\'
                                             , usf_icon = \'icq.png\'
                                             , usf_description = \''.$gL10n->get('INS_ICQ_DESC').'\'
                                             , usf_url = \'https://www.icq.com/people/#user_content#\'
                 WHERE usf_id = '.$rowProfileField[0];
        $gDb->query($sql);
    }

    $sql = 'SELECT usf_id
              FROM '.TBL_USER_FIELDS.'
             WHERE usf_cat_id = '.$rowCategory[0].'
               AND usf_name_intern = \'MSN_MESSENGER\' ';
    $pdoStatement = $gDb->query($sql);
    $rowProfileField = $pdoStatement->fetch();

    if($rowProfileField[0] > 0)
    {
        $sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name = \'INS_WINDOWS_LIVE\'
                                             , usf_name_intern = \'WINDOWS_LIVE\'
                                             , usf_icon = \'windows_live.png\'
                                             , usf_description = null
                 WHERE usf_id = '.$rowProfileField[0];
        $gDb->query($sql);
    }

    $sql = 'SELECT usf_id
              FROM '.TBL_USER_FIELDS.'
             WHERE usf_cat_id = '.$rowCategory[0].'
               AND usf_name_intern = \'SKYPE\' ';
    $pdoStatement = $gDb->query($sql);
    $rowProfileField = $pdoStatement->fetch();

    if($rowProfileField[0] > 0)
    {
        $sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name = \'INS_SKYPE\'
                                             , usf_icon = \'skype.png\'
                                             , usf_description = \''.$gL10n->get('INS_SKYPE_DESC').'\'
                 WHERE usf_id = '.$rowProfileField[0];
        $gDb->query($sql);
    }

    $sql = 'SELECT usf_id
              FROM '.TBL_USER_FIELDS.'
             WHERE usf_cat_id = '.$rowCategory[0].'
               AND usf_name_intern = \'YAHOO_MESSENGER\' ';
    $pdoStatement = $gDb->query($sql);
    $rowProfileField = $pdoStatement->fetch();

    if($rowProfileField[0] > 0)
    {
        $sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name = \'INS_YAHOO_MESSENGER\'
                                             , usf_icon = \'yahoo.png\'
                                             , usf_description = null
                 WHERE usf_id = '.$rowProfileField[0];
        $gDb->query($sql);
    }

    $sql = 'INSERT INTO '.TBL_USER_FIELDS.' (usf_cat_id, usf_type, usf_name_intern, usf_name, usf_description, usf_icon, usf_url, usf_system, usf_sequence, usf_usr_id_create, usf_timestamp_create)
                                       VALUES ('.$rowCategory[0].', \'TEXT\', \'FACEBOOK\', \'INS_FACEBOOK\', \''.$gL10n->get('INS_FACEBOOK_DESC').'\', \'facebook.png\', \'https://www.facebook.com/#user_content#\', 0, 7, '.$rowWebmaster[0].',\''. DATETIME_NOW.'\')
                                            , ('.$rowCategory[0].', \'TEXT\', \'TWITTER\', \'INS_TWITTER\', \''.$gL10n->get('INS_TWITTER_DESC').'\', \'twitter.png\', \'https://twitter.com/#user_content#\', 0, 8, '.$rowWebmaster[0].',\''. DATETIME_NOW.'\')
                                            , ('.$rowCategory[0].', \'TEXT\', \'XING\', \'INS_XING\', \''.$gL10n->get('INS_XING_DESC').'\', \'xing.png\', \'https://www.xing.com/profile/#user_content#\', 0, 9, '.$rowWebmaster[0].',\''. DATETIME_NOW.'\') ';
    $gDb->query($sql);

    $sql = 'UPDATE '.TBL_CATEGORIES.' SET cat_name = \'SYS_SOCIAL_NETWORKS\'
                                        , cat_name_intern = \'SOCIAL_NETWORKS\'
             WHERE cat_id = '.$rowCategory[0];
    $gDb->query($sql);
}
