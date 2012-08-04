<?php
/******************************************************************************
 * Class manages access to database table adm_texts
 *
 * Copyright    : (c) 2004 - 2012 The Admidio Team
 * Homepage     : http://www.admidio.org
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Diese Klasse dient dazu ein Textobjekt zu erstellen.
 * Texte koennen ueber diese Klasse in der Datenbank verwaltet werden.
 *
 * Es stehen die Methoden der Elternklasse TableAccess zur Verfuegung.
 *
 *****************************************************************************/

require_once(SERVER_PATH. '/adm_program/system/classes/table_access.php');

class TableText extends TableAccess
{
	/** Constuctor that will create an object of a recordset of the table adm_texts. 
	 *  If the id is set than the specific text will be loaded.
	 *  @param $db Object of the class database. This should be the default object $gDb.
	 *  @param $name The recordset of the text with this name will be loaded. If name isn't set than an empty object of the table is created.
	 */
    public function __construct(&$db, $name = '')
    {
        parent::__construct($db, TBL_TEXTS, 'txt', $name);
    }
    
    // bei Textfeldern sollen Anfuehrungszeichen erhalten bleiben
    public function getValue($field_name, $format = '')
    {
        if($field_name == 'txt_text')
        {
            return $this->dbColumns['txt_text'];
        }
        else
        {
            return parent::getValue($field_name, $format);
        }
    }

    // interne Funktion, die Defaultdaten fur Insert und Update vorbelegt
    // die Funktion wird innerhalb von save() aufgerufen
    public function save($updateFingerPrint = true)
    {
        if($this->new_record)
        {
            // Insert
            global $gCurrentOrganization;
            $this->setValue('txt_org_id', $gCurrentOrganization->getValue('org_id'));
        }
        parent::save($updateFingerPrint);
    }    
}
?>