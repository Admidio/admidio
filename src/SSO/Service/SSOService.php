<?php
namespace Admidio\SSO\Service;

use Admidio\SSO\Entity\SSOClient;

use Exception;

use Admidio\Infrastructure\Database;
use Admidio\Users\Entity\User;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\UI\Presenter\PagePresenter;


class SSOService {
    protected Database $db;
    protected User $currentUser;

    protected string $columnPrefix;
    protected string $table;

    public function __construct(Database $db, User $currentUser) {
        global $gSettingsManager;
        $this->db           = $db;
        $this->currentUser  = $currentUser;
    }

    public function initializeClientObject($database): ?SSOClient {
        return new SSOClient($database, null, 'sso');
    }

    public function createClientObject($clientUUID = null, $clientID = null): ?SSOClient {
        $client = $this->initializeClientObject($this->db);
        if (!empty($clientUUID)) {
            $client->readDataByUuid($clientUUID);
        } elseif (!empty($clientID)) {
            $client->readDatabyEntityId($clientID);
        }
        return $client;
    }

    public function getClientFromID($clientID) {
        $client = $this->createClientObject(null, $clientID);
        if ($client->isNewRecord()) {
            throw new Exception("SSO client '$clientID' not found in database. Please check the SSO client settings and configure the client in Admidio.");
        }
        return $client;
    }

    public function getClientFromUUID($clientUUID) {
        $client = $this->createClientObject($clientUUID);
        if ($client->isNewRecord()) {
            throw new Exception("SSO client with UUID '$clientUUID' not found in database. Please check the SSO client settings and configure the client in Admidio.");
        }
        return $client;
    }

    /**
     * Returns an associative array with labels and links for the static IdP configuration data 
     * (metadata/discovery URL, SSO/SLO endpoints, etc.).
     * @return array Associative arry, the keys will be the displayed labels, each entry has the form
     *     ['value' => 'linkHTML', 'id' => 'uniqueIDinForm', 'style' => 'additionalCSSstyles']
     *   where the 'style' key is optional, but 'value' and 'id' are required.
     */
    public function getStaticSettings() : array {
        return [];
    }

    /**
     * Returns a HTML array representation with labels and links for the static IdP configuration data 
     * (metadata/discovery URL, SSO/SLO endpoints, etc.).
     * @return array HTML array of the static IdP settings, including copy images, links, etc.
     */
    public function getStaticSettingsHTML(string $id = 'sso_staticsettings', string $class = '') : string {
        global $gL10n;
        $staticSettings = $this->getStaticSettings();
 

        $first = true;
        $html = '<table id="' . $id . '" style="width: 100%" class="' . $class . '"><tbody>';
        
        foreach ($staticSettings as $label => $value) {
            $html .= '<tr><td>';
            if ($first) {
                $html .= '<a id="' . $id . '_caret" class="admidio-icon-link admidio-open-close-caret" data-target="' . $id . '_contents">
                        <i class="bi bi-caret-right-fill"></i>
                    </a>';
            }
            $html .= $gL10n->get($label) . ':&nbsp;</td>
            <td><div class="copy-container" id="' . $value['id'] . '"' . 
                        (array_key_exists('style', $value) ? (' style="' . $value['style'] . '"') : '') .
                '>' . $value['value'] . '</div></td></tr>';
            if ($first) {
                $html .= '</tbody>
                        <tbody id="' . $id . '_contents" style="display: none">
                ';
            }
            $first = false;
        }
        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Save data from the SSO client edit form into the database (works for both SAML and OIDC).
     * @throws Exception
     */
    public function save($getClientUUID)
    {
        global $gCurrentSession;

        // check form field input and sanitized it from malicious content
        $clientEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $clientEditForm->validate($_POST);
        $client = $this->createClientObject($getClientUUID);

        $this->db->startTransaction();
        $this->saveCustomClientSettings($formValues, $client);

        // Collect all field mappings and the catch-all checkbox
        // If a SSO field is left empty, use the admidio name!
        $ssoFields = $formValues['fieldsmap_sso']??[];
        $admFields = $formValues['fieldsmap_Admidio']??[];
        $ssoFields = array_map(function ($a, $b) { return (!empty($a)) ? $a : $b;}, $ssoFields, $admFields);
        $client->setFieldMapping(array_combine($ssoFields, $admFields), $formValues['sso_fields_no_other']??false);
        
        // Collect all role mappings and the catch-all checkbox
        $ssoRoles = $formValues['rolesmap_sso']??[];
        $admRoles = $formValues['rolesmap_Admidio']??[];
        $ssoRoles = array_map( function($s, $a) { 
                if (empty($s)) {
                    $role = new Role($this->db, $a);
                    return $role->readableName();
                } else { 
                    return $s; 
                }
            }, $ssoRoles, $admRoles);
        $client->setRoleMapping(array_combine($ssoRoles, $admRoles), $formValues['sso_roles_all_other']??false);

        // write all other form values
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, $this->columnPrefix . '_')) {
                $client->setValue($key, $value);
            }
        }

        $client->save();

        // save changed roles rights of the menu
        if (isset($_POST['sso_roles_access'])) {
            $accessRoles = array_map('intval', $_POST['sso_roles_access']);
        } else {
            $accessRoles = array();
        }

        $accessRolesRights = new RolesRights($this->db, $this->getRolesRightName(), $client->getValue($client->getKeyColumnName()));
        $accessRolesRights->saveRoles($accessRoles);

        $this->db->endTransaction();
    }

    /**
     * Let SSO implementation save further client settings (e.g. a hashed client secret for OIDC, etc.)
     * @param array $formValues
     * @param \Admidio\SSO\Entity\SSOClient $client
     * @return void
     */
    protected function saveCustomClientSettings(array &$formValues, SSOClient $client) {
    }

    protected function getRolesRightName(): string {
        return 'sso_access';
    }


    
    /**
     * Return all client Ids stored in the database. For each client ID, the full client can be 
     * retrieved by the method getClientFromID($clientID).
     * @return array Returns an array with all client Ids
     * @throws Exception
     */
    public function getClientIds(): array
    {
        $sql = 'SELECT ' . $this->columnPrefix . '_client_id
          FROM ' . $this->table . ' AS clients';
        $clients = array();
        $clientsStatement = $this->db->queryPrepared($sql, []);
        while ($row = $clientsStatement->fetch()) {
            $clients[] = $row[
                
                
                $this->columnPrefix . '_client_id'];
        }
        return $clients;
    }
    
    /**
     * Return all numeric Ids of clients stored in the database.
     * @return array Returns an array with all numeric  Ids
     * @throws Exception
     */
    public function getIds(): array
    {
        $sql = 'SELECT ' . $this->columnPrefix . '_id
          FROM ' . $this->table . ' AS clients';
        $clients = array();
        $clientsStatement = $this->db->queryPrepared($sql, []);
        while ($row = $clientsStatement->fetch()) {
            $clients[] = $row[$this->columnPrefix . '_id'];
        }
        return $clients;
    }

    /**
     * Return all UUIDs of clients stored in the database.
     * @return array Returns an array with all UUIDs
     * @throws Exception
     */
    public function getUUIDs(): array
    {
        $sql = 'SELECT ' . $this->columnPrefix . '_uuid
          FROM ' . $this->table . ' AS clients';
        $clients = array();
        $clientsStatement = $this->db->queryPrepared($sql, []);
        while ($row = $clientsStatement->fetch()) {
            $clients[] = $row[$this->columnPrefix . '_uuid'];
        }
        return $clients;
    }


    public function showSSOLoginForm(SSOClient $client, ?string $message = null) {
        global $gNavigation, $gL10n;

        if (!isset($_SESSION['login_forward_url'])) {
            $_SESSION['login_forward_url'] = CURRENT_URL;
            // GET variables are included in the current URL, but POST variables need to be added
            if (!empty($_POST)) {
                $_SESSION['login_forward_url_post'] = $_POST;
            }
        }
        $headline = $gL10n->get('SYS_LOGIN_TO', array($client->readableName()));

        // remember url (will be removed in login_check)
        $gNavigation->addUrl(CURRENT_URL, $headline);

        // create html page object
        $page = PagePresenter::withHtmlIDAndHeadline('admidio-login', $headline);
        if (!empty($message)) {
            $page->addHtml($message);
        }
        // Use javascript to hide the menu bar on the left and the registration 
        $page->addJavascript('$("#adm_sidebar").hide()', true);

        // TODO_RK: Add "Cancel / Return to SP without logging in" button with JS!
        $loginModule = new \ModuleLogin();
        $loginModule->addHtmlLogin($page, '');
        $page->show();
        exit;
    }

}    
