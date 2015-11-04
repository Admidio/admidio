<?php
/******************************************************************************
 * Data conversion for version 2.2.11
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

//validate old bb-codes before update
function validate_bbc($table, $idCol, $col)
{
    global $gDb;
    $bbcodes =array(array('o' => '[b]', 'c' => '[/b]'),
                    array('o' => '[i]', 'c' => '[/i]'),
                    array('o' => '[u]', 'c' => '[/u]'),
                    array('o' => '[big]', 'c' => '[/big]'),
                    array('o' => '[small]', 'c' => '[/small]'),
                    array('o' => '[center]', 'c' => '[/center]'),
                    array('o' => '[img', 'c' => '[/img]'),
                    array('o' => '[url', 'c' => '[/url]'),
                    array('o' => '[email', 'c' => '[/email]'));

    //get all entrys with bb-codes
    $sql = 'SELECT '.$idCol.', '.$col.'
            FROM '.$table. '
            WHERE '.$col.' LIKE \'%[%\'';
    $bbcodeStatement = $gDb->query($sql);

    //walk through all results
    while($row = $bbcodeStatement->fetchObject())
    {
        $sql_append = $row->$col;

        //once for each bb-code-type
        foreach($bbcodes as $bbcode)
        {
            //comepare number of opening and closeing tags
            $dif = substr_count($row->$col, $bbcode['o'])-substr_count($row->$col, $bbcode['c']);
            for($x=0; $x<$dif; $x++)
            {
                $sql_append .= $bbcode['c'];
            }
        }
        //update if nessecary
        if($sql_append != $row->$col)
        {
            $sql_update = 'UPDATE '.$table. '
                    SET '.$col.' = \''.$sql_append.'\' WHERE '.$idCol.' = \''.$row->$idCol.'\'';
            $gDb->query($sql_update);
        }

    }
}
validate_bbc(TBL_ANNOUNCEMENTS, 'ann_id', 'ann_description');
validate_bbc(TBL_DATES, 'dat_id', 'dat_description');
validate_bbc(TBL_GUESTBOOK, 'gbo_id', 'gbo_text');
validate_bbc(TBL_GUESTBOOK_COMMENTS, 'gbc_id', 'gbc_text');
validate_bbc(TBL_LINKS, 'lnk_id', 'lnk_description');
validate_bbc(TBL_ROOMS, 'room_id', 'room_description');


// check internal fieldname if name is unique, if not add suffix to name
$sql = 'SELECT usf_id, usf_name_intern FROM '.TBL_USER_FIELDS.' ORDER by usf_name_intern ';
$userFieldsStatement = $gDb->query($sql);
$lastNameIntern = '';
$i = 0;

while($row = $userFieldsStatement->fetch())
{
    $i++;
    if($row['usf_name_intern'] == $lastNameIntern)
    {
        $sql = 'UPDATE '.TBL_USER_FIELDS.' SET usf_name_intern = \''.$row['usf_name_intern'].'_0'.$i.'\'
                 WHERE usf_id = '.$row['usf_id'];
        $gDb->query($sql);
    }

    $lastNameIntern = $row['usf_name_intern'];
}
