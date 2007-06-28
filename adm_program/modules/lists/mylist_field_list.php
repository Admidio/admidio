<?php
/******************************************************************************
 * Script mit HTML-Code fuer ein Feld der Eigenen-Liste-Konfiguration
 *
 * Copyright    : (c) 2004 - 2007 The Admidio Team
 * Homepage     : http://www.admidio.org
 * Module-Owner : Markus Fassbender
 *
 * Uebergaben:
 *
 * field_number : wenn ueber Ajax nachgeladen wird, steht hier die Nummer
 *                des Feldes, welches erzeugt werden soll
 ******************************************************************************
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * version 2 as published by the Free Software Foundation
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 *
 *****************************************************************************/
 
$b_ajax = false;

if(isset($_POST['field_number']))
{
    // Script wurde ueber Ajax aufgerufen
    include("../../system/common.php"); 

    $i = $_POST['field_number'];
    $b_ajax = true;
}

echo "<div style=\"text-align: center; width: 18%; float: left; margin-top: 5px;\">&nbsp;$i. Feld :&nbsp;</div>
    <div style=\"text-align: center; width: 37%; float: left; margin-top: 5px;\">
        <select size=\"1\" name=\"column$i\">
            <option value=\"\" ";
                if($b_ajax == true || $b_history == false)
                {
                    echo " selected ";
                }
                echo "></option>";

            //ggf zusaetzliche Felder auslesen und bereitstellen
            $category     = "";
            $b_stammdaten = false;

            foreach($g_current_user->db_user_fields as $key => $value)
            {     
                if($category != $value['cat_id'])
                {
                    if(strlen($category) > 0)
                    {
                        if($b_stammdaten)
                        {
                            // wenn Zurueck gewaehlt wurde, dann Felder mit den alten Werten vorbelegen
                            $photo_selected = "";
                            $login_selected   = "";
                            if($b_ajax == false && $b_history == true)
                            {
                                if($form_values["column$i"] == "usr_photo")
                                {
                                    $photo_selected = " selected ";                          
                                }
                                elseif($form_values["column$i"] == "usr_login_name")
                                {
                                    $login_selected = " selected ";                          
                                }
                            }
            
                            // Zusatzfelder z.B. usr_photo, mem_begin hinzufuegen
                            echo "<option value=\"usr_login_name\" $login_selected>Benutzername</option>
                                  <option value=\"usr_photo\" $photo_selected>Foto</option>";
                        }

                        echo "</optgroup>";
                    }

                    // Ajax gibt alles in UTF8 zurueck
                    if($b_ajax)
                    {
                        $category_name = utf8_encode($value['cat_name']);
                    }
                    else
                    {
                        $category_name = $value['cat_name'];
                    }                        

                    echo "<optgroup label=\"$category_name\">";

                    if($value['cat_name'] == "Stammdaten")
                    {
                        $b_stammdaten = true;
                    }
                    else
                    {
                        $b_stammdaten = false;
                    }
                    $category = $value['cat_id'];
                }

                //Nur Moderatoren duerfen sich gelockte Felder anzeigen lassen 
                if($value['usf_hidden'] == 0 || $g_current_user->assignRoles())
                {
                    // Ajax gibt alles in UTF8 zurueck
                    if($b_ajax)
                    {
                        $field_name = utf8_encode($value['usf_name']);
                    }
                    else
                    {
                        $field_name = $value['usf_name'];
                    }
                    // wenn Zurueck gewaehlt wurde, dann Felder mit den alten Werten vorbelegen
                    $selected = "";
                    if($b_ajax == false && $b_history == true
                    && $form_values["column$i"] == $value['usf_id'])
                    {
                        $selected = " selected ";                          
                    }

                    echo"<option value=\"". $value['usf_id']. "\" $selected >$field_name</option>";
                }
            } 
            
            // wenn Zurueck gewaehlt wurde, dann Felder mit den alten Werten vorbelegen
            $begin_selected = "";
            $end_selected   = "";
            if($b_ajax == false && $b_history == true)
            {
                if($form_values["column$i"] == "mem_begin")
                {
                    $begin_selected = " selected ";                          
                }
                elseif($form_values["column$i"] == "mem_end")
                {
                    $end_selected = " selected ";                          
                }
            }
            
            // nun noch Gruppe mit Rollendaten anhaengen
            echo "</optgroup>
            <optgroup label=\"Rollendaten\">
                <option value=\"mem_begin\" $begin_selected>Mitgliedsbeginn</option>
                <option value=\"mem_end\" $end_selected>Mitgliedsende</option>
            </optgroup>
        </select>&nbsp;&nbsp;
    </div>
    <div style=\"text-align: center; width: 18%; float: left; margin-top: 5px;\">
        <select size=\"1\" name=\"sort$i\">
            <option value=\"\" ";
                if($b_ajax == true || isset($form_values["sort$i"]) == false)
                {
                    echo " selected ";
                }
                echo ">&nbsp;</option>
            <option value=\"ASC\" ";
                if($b_ajax == false && $b_history == true
                && $form_values["sort$i"] == "ASC")
                {
                    echo " selected ";
                }
                echo ">A bis Z</option>
            <option value=\"DESC\" ";
                if($b_ajax == false && $b_history == true
                && $form_values["sort$i"] == "DESC")
                {
                    echo " selected ";
                }
                echo ">Z bis A</option>
        </select>
    </div>";
    if($b_ajax == false && $b_history == true)
    {
        $condition = $form_values["condition$i"];
    }
    else
    {
        $condition = "";
    }
    echo "<div style=\"text-align: center; width: 27%; float: left; margin-top: 5px;\">
        <input type=\"text\" name=\"condition$i\" size=\"15\" maxlength=\"30\" value=\"$condition\">
    </div>
    <span id=\"next_field_". ($i + 1). "\" style=\"clear: left;\"></span>";
?>