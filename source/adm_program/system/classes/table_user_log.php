<?php
/******************************************************************************
 * Class manages access to database table adm_user_log
 *
 * Copyright    : (c) 2012 M.Schuetze
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu, ein Objekt fuer das User-Log zu erstellen.
 * Ein Log-Eintrag wird ueber diese Klasse in die Datenbank eingefuegt
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 *
 * newLogEntry()    - neuen Log-Eintrag schreiben
 *
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableUserLog extends TableAccess
{
    // Konstruktor
    public function __construct(&$db)
    {
        parent::__construct($db, TBL_USER_LOG, 'usl');
    }

    // inserts new value
    // $usr_id    = Log-Eintrag f. User mit ID $usr_id
    // $field_id  = Nummer des geaenderten Datenfeldes
    // $old_value = alter Wert
    // $new_value = neuer Wert
    // $comment   = (optionaler) Kommentar
    public function newLogEntry($usr_id,$field_id,$old_value,$new_value,$comment= "")
    {
    $this->setValue("usl_usr_id", $usr_id);
    $this->setValue("usl_usf_id", $field_id);
    $this->setValue("usl_value_old", $old_value);
    $this->setValue("usl_value_new", $new_value);
    $this->setValue("usl_comm", $comment);
    $this->save();
    }
}
?>
