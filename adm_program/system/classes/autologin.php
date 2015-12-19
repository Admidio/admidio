<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2015 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class AutoLogin
 * @brief Handle auto login with Admidio and manage it in the database
 *
 * The class search in the database table @b adm_auto_login for the session id.
 * If there is an entry for that id then it reads the user id and set this
 * user to the current session. Now the current session has become a valid user
 * that is automatically login.
 * @par Examples
 * @code // create a valid user login for a Admidio session from auto login
 * $autoLogin = new AutoLogin($gDb, $gSessionId);
 * $autoLogin->setValidLogin($gCurrentSession, $_COOKIE['ADMIDIO_ID']);@endcode
 * @code // delete an auto login
 * $autoLogin = new AutoLogin($gDb, $gSessionId);
 * $autoLogin->delete(); @endcode
 */
class AutoLogin extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_auto_login.
     * If the id is set than the specific auto login will be loaded.
     * @param object     $database Object of the class Database. This should be the default global object @b $gDb.
     * @param string|int $session  The recordset of the auto login with this session will be loaded.
     *                             If session isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $session = 0)
    {
        parent::__construct($database, TBL_AUTO_LOGIN, 'atl');

        // if not numeric than the session id is commited
        if(is_numeric($session))
        {
            $this->readDataById($session);
        }
        else
        {
            $this->readDataByColumns(array('atl_auto_login_id' => $session));
        }

    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update the changed columns.
     * If the table has columns for creator or editor than these column with their timestamp will be updated.
     * The current organization, last login and ip adress will be set per default.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset
     *                                if table has columns like @b usr_id_create or @b usr_id_changed
     */
    public function save($updateFingerPrint = true)
    {
        if($this->new_record)
        {
            // Insert
            global $gCurrentOrganization;
            $this->setValue('atl_org_id', $gCurrentOrganization->getValue('org_id'));
            $this->setValue('atl_last_login', DATETIME_NOW);
            $this->setValue('atl_ip_address', $_SERVER['REMOTE_ADDR']);

            // Tabelle aufraeumen, wenn ein neuer Datensatz geschrieben wird
            $this->tableCleanup();
        }
        else
        {
            // Update
            $this->setValue('atl_last_login', DATETIME_NOW);
            $this->setValue('atl_ip_address', $_SERVER['REMOTE_ADDR']);
        }
        parent::save($updateFingerPrint);
    }

    /**
     * Method will clean the database table @b adm_auto_login.
     * All login that had their last login one year ago will be deleted.
     */
    public function tableCleanup()
    {
        // Zeitpunkt bestimmen, ab dem die Auto-Logins geloescht werden, mind. 1 Jahr alt
        $currDateTime = new DateTime();
        $oneYearDateInterval = new DateInterval('P1Y');
        $oneYearBeforeDateTime = $currDateTime->sub($oneYearDateInterval);
        $date_session_delete = $oneYearBeforeDateTime->format('Y.m.d H:i:s');

        $sql = 'DELETE FROM '.TBL_AUTO_LOGIN.'
                 WHERE atl_last_login < \''. $date_session_delete. '\'';
        $this->db->query($sql);
    }
}
