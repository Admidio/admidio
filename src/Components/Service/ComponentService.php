<?php

namespace Admidio\Components\Service;

// Admidio namespaces
use Admidio\Infrastructure\Database;
use Admidio\Infrastructure\Exception;
use Admidio\Components\Entity\Component;

/**
 * @brief Class with methods to display the module pages.
 *
 * This class adds some functions that are used in the menu module to keep the
 * code easy to read and short
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class ComponentService
{
    public const MOVE_UP = 'UP';
    public const MOVE_DOWN = 'DOWN';

    protected Component $componentRessource;
    protected Database $db;
    protected string $comId;

    /**
     * @param Database $database Object of the class Database. This should be the default global object **$gDb**.
     * @param string $itemFieldUUID UUID if the profile field that should be managed within this class
     * @throws Exception
     */
    public function __construct(Database $database, string $comId = '')
    {
        $this->db = $database;
        $this->comId = $comId;
        $this->componentRessource = new Component($database);
        $this->componentRessource->readDataById($comId);
    }

    /**
     * Profile field will change the sequence one step up or one step down.
     * @param string $mode mode if the item field move up or down, values are ProfileField::MOVE_UP, ProfileField::MOVE_DOWN
     * @return bool Return true if the sequence of the category could be changed, otherwise false.
     * @throws Exception
     */
    public function moveSequence(string $mode): bool
    {
        global $gCurrentOrgId;

        $plgSequence = (int)$this->componentRessource->getValue('com_plg_sequence');
        $sql = 'UPDATE ' . TBL_COMPONENTS . '
                   SET com_plg_sequence = ? -- $usfSequence
                 WHERE com_type = \'ADM_PLUGIN\'
                   AND com_plg_sequence = ? -- $usfSequence -/+ 1';

        // item field will get one number lower and therefore move a position up in the list
        if ($mode === self::MOVE_UP) {
            $newSequence = $plgSequence - 1;
        } // item field will get one number higher and therefore move a position down in the list
        elseif ($mode === self::MOVE_DOWN) {
            $newSequence = $plgSequence + 1;
        }

        // update the existing entry with the sequence of the field that should get the new sequence
        $this->db->queryPrepared($sql, array($plgSequence, $newSequence));

        $this->componentRessource->setValue('com_plg_sequence', $newSequence);
        return $this->componentRessource->save();
    }

    /**
     * Iem field will change the complete sequence.
     * @param array $sequence the new sequence of item fields (field IDs)
     * @return bool Return true if the sequence of the category could be changed, otherwise false.
     * @throws Exception
     */
    public function setSequence(array $sequence): bool
    {
        global $gCurrentOrgId;
        $comId = $this->componentRessource->getValue('com_id');

        $sql = 'UPDATE ' . TBL_COMPONENTS . '
                   SET com_plg_sequence = ? -- new order sequence
                 WHERE com_id = ? -- field uuid;
                   AND com_type = \'ADM_PLUGIN\'
            ';

        $newSequence = -1;
        foreach ($sequence as $pos => $id) {
            if ($id == $comId) {
                // Store position for later update
                $newSequence = $pos;
            } else {
                $this->db->queryPrepared($sql, array($pos, $id));
            }
        }

        if ($newSequence >= 0) {
            $this->componentRessource->setValue('com_plg_sequence', $newSequence);
        }

        return $this->componentRessource->save();
    }
}