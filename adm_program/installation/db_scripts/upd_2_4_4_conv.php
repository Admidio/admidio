<?php
/******************************************************************************
 * Data conversion for version 2.4.3
 *
 * Copyright    : (c) 2004 - 2015 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 https://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

// select all member ids where we find multiple role / user assignments
$sql = 'select mem5.mem_id as member_id
          from '.TBL_MEMBERS.' mem5
         where exists (select 1 from '.TBL_MEMBERS.' mem2
                        where mem2.mem_rol_id = mem5.mem_rol_id
                          and mem2.mem_usr_id = mem5.mem_usr_id
                          and mem2.mem_end = mem5.mem_end
                          and mem2.mem_id <> mem5.mem_id )
           and mem5.mem_id not in (
                       select min(mem.mem_id)
                         from '.TBL_MEMBERS.' mem
                        where exists (select 1 from '.TBL_MEMBERS.' mem2
                                       where mem2.mem_rol_id = mem.mem_rol_id
                                         and mem2.mem_usr_id = mem.mem_usr_id
                                         and mem2.mem_end = mem.mem_end
                                         and mem2.mem_id <> mem.mem_id )
                        group by mem.mem_rol_id, mem.mem_usr_id, mem.mem_end)';
$resultMembers = $gDb->query($sql);

// do a loop over all members because mysql don't support subqueries in delete statements
while($rowMember = $gDb->fetch_array($resultMembers))
{
    $sql = 'delete from '.TBL_MEMBERS.' where mem_id = '.$rowMember['member_id'];
    $gDb->query($sql);
}
