<?php
/**
 ***********************************************************************************************
 * Data conversion for version 2.4.3
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// select all member ids where we find multiple role / user assignments
$sql = 'SELECT mem5.mem_id AS member_id
          FROM '.TBL_MEMBERS.' mem5
         WHERE exists (SELECT 1 FROM '.TBL_MEMBERS.' mem2
                        WHERE mem2.mem_rol_id = mem5.mem_rol_id
                          AND mem2.mem_usr_id = mem5.mem_usr_id
                          AND mem2.mem_end    = mem5.mem_end
                          AND mem2.mem_id    <> mem5.mem_id )
           AND mem5.mem_id NOT IN (
                       SELECT MIN(mem.mem_id)
                         FROM '.TBL_MEMBERS.' mem
                        WHERE exists (SELECT 1 FROM '.TBL_MEMBERS.' mem2
                                       WHERE mem2.mem_rol_id = mem.mem_rol_id
                                         AND mem2.mem_usr_id = mem.mem_usr_id
                                         AND mem2.mem_end    = mem.mem_end
                                         AND mem2.mem_id    <> mem.mem_id )
                     GROUP BY mem.mem_rol_id, mem.mem_usr_id, mem.mem_end)';
$membersStatement = $gDb->query($sql);

// do a loop over all members because mysql don't support subqueries in delete statements
while($rowMember = $membersStatement->fetch())
{
    $sql = 'DELETE FROM '.TBL_MEMBERS.' WHERE mem_id = '.$rowMember['member_id'];
    $gDb->query($sql);
}
