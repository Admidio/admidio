<?php
namespace Admidio\SSO\Service;

use Admidio\SSO\Entity\SSOClient;

use Exception;

use Admidio\Infrastructure\Database;
use Admidio\Users\Entity\User;
use Admidio\Roles\Entity\Role;
use Admidio\Roles\Entity\RolesRights;
use Admidio\UI\Presenter\PagePresenter;


abstract class SSOService {
    protected Database $db;
    protected User $currentUser;

    protected string $columnPrefix;
    protected string $table;

    public function __construct(Database $db, User $currentUser) {
        global $gSettingsManager;
        $this->db           = $db;
        $this->currentUser  = $currentUser;
    }

    abstract public function initializeClientObject(Database $database): ?SSOClient;

    public function createClientObject($clientUUID = null, $clientID = null): ?SSOClient {
        global $gCurrentOrgId;

        $client = $this->initializeClientObject($this->db);

        $columns = array(
            $this->columnPrefix . '_org_id' => $gCurrentOrgId
        );

        if (!empty($clientUUID)) {
            $columns[$this->columnPrefix . '_uuid'] = $clientUUID;
        } elseif (!empty($clientID)) {
            $columns[$this->columnPrefix . '_client_id'] = $clientID;
        } else {
            return $client;
        }

        $client->readDataByColumns($columns);

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
     * Return organization-scoped values from the SSO client table.
     *
     * @return array<int,mixed>
     * @throws Exception
     */
    private function getOrganizationClientValues(string $columnSuffix): array
    {
        global $gCurrentOrgId;

        $column = $this->columnPrefix . '_' . $columnSuffix;

        $sql = 'SELECT ' . $column . '
                FROM ' . $this->table . '
                WHERE ' . $this->columnPrefix . '_org_id = ?';
        $statement = $this->db->queryPrepared($sql, array($gCurrentOrgId));

        $values = array();
        while ($row = $statement->fetch()) {
            $values[] = $row[$column];
        }

        return $values;
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
     * Save data from the SSO client edit form into the database (works for both SAML and OIDC).
     * @throws Exception
     */
    public function save($getClientUUID)
    {
        global $gCurrentSession;

        // check form field input and sanitized it from malicious content
        $clientEditForm = $gCurrentSession->getFormObject($_POST['adm_csrf_token']);
        $formValues = $clientEditForm->validate($_POST);

        if (isset($_POST['sso_roles_access'])) {
            $accessRoles = array_map('intval', $_POST['sso_roles_access']);
        } else {
            $accessRoles = array();
        }

        $this->saveData($getClientUUID, $formValues, $accessRoles);
    }

    /**
     * Save already validated SSO client data.
     *
     * @param string|null $clientUUID UUID of an existing client or null for a new client.
     * @param array $formValues Validated SSO client values.
     * @param array<int,int|string> $accessRoles Roles that are allowed to use this client.
     * @return SSOClient
     * @throws Exception
     */
    public function saveData(?string $clientUUID, array $formValues, array $accessRoles = array()): SSOClient
    {
        $client = $this->createClientObject($clientUUID);

        $this->db->startTransaction();
        $this->saveCustomClientSettings($formValues, $client);

        // Collect all field mappings and the catch-all checkbox
        // If a SSO field is left empty, use the admidio name!
        $ssoFields = $formValues['fieldsmap_sso'] ?? array();
        $admFields = $formValues['fieldsmap_Admidio'] ?? array();
        $ssoFields = array_map(
            function ($ssoField, $admField) {
                return !empty($ssoField) ? $ssoField : $admField;
            },
            $ssoFields,
            $admFields
        );
        if ($this->columnPrefix === 'smc') {
            // SAML: include all remaining Admidio fields with their internal field name. The SAML
            // edit form names the checkbox sso_fields_all_other, the CLI sso_fields_no_other.
            $fieldMappingCatchall = $formValues['sso_fields_all_other'] ?? $formValues['sso_fields_no_other'] ?? false;
        } else {
            // OIDC: suppress standard claims that are not explicitly mapped.
            $fieldMappingCatchall = $formValues['sso_fields_no_other'] ?? false;
        }
        $client->setFieldMapping(
            array_combine($ssoFields, $admFields),
            (bool) $fieldMappingCatchall
        );
        
        // Collect all role mappings and the catch-all checkbox. Several Admidio roles may be mapped
        // to the same client role, so the assignments are passed on as a list of pairs. Combining
        // them into an array keyed by the client role name would silently drop all but one of them.
        // If a client role is left empty, use the Admidio role name!
        $ssoRoles = array_values($formValues['rolesmap_sso'] ?? array());
        $admRoles = array_values($formValues['rolesmap_Admidio'] ?? array());
        $roleMapping = array();
        foreach ($admRoles as $index => $admRole) {
            $ssoRole = $ssoRoles[$index] ?? '';
            if (empty($ssoRole)) {
                $role = new Role($this->db, $admRole);
                $ssoRole = $role->readableName();
            }
            $roleMapping[] = array($ssoRole, $admRole);
        }
        $client->setRoleMapping(
            $roleMapping,
            (bool) ($formValues['sso_roles_all_other'] ?? false)
        );

        // write all other form values
        foreach ($formValues as $key => $value) {
            if (str_starts_with($key, $this->columnPrefix . '_')) {
                $client->setValue($key, $value);
            }
        }

        $client->save();

        $accessRolesRights = new RolesRights($this->db, $this->getRolesRightName(), $client->getValue($client->getKeyColumnName()));
        $accessRolesRights->saveRoles(array_map('intval', $accessRoles));
 
        $this->db->endTransaction();

        return $client;
    }

    /**
     * Let SSO implementation save further client settings (e.g. a hashed client secret for OIDC, etc.)
     * @param array $formValues
     * @param SSOClient $client
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
        return $this->getOrganizationClientValues('client_id');
    }
    
    /**
     * Return all numeric Ids of clients stored in the database.
     * @return array Returns an array with all numeric  Ids
     * @throws Exception
     */
    public function getIds(): array
    {
        return $this->getOrganizationClientValues('id');
    }

    /**
     * Return all UUIDs of clients stored in the database.
     * @return array Returns an array with all UUIDs
     * @throws Exception
     */
    public function getUUIDs(): array
    {
        return $this->getOrganizationClientValues('uuid');
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
        $page = new PagePresenter();
        $page->setHtmlID('admidio-sso-login');
        $page->setTitle($headline);
        $page->setContentFullWidth();
        $page->hideBackLink();

        $page->getSmartyTemplate()->assign('ssoLoginHeadline', $headline);
        $page->getSmartyTemplate()->assign('ssoLoginMessage', $message ?? '');


        // TODO_RK: Add "Cancel / Return to SP without logging in" button with JS!
        $cancelUrl = CURRENT_URL . (str_contains(CURRENT_URL, '?') ? '&' : '?') . 'sso_cancel=1';
        $cancelPostData = !empty($_POST) ? $_POST : null;

        $loginModule = new \ModuleLogin();
        $loginModule->addHtmlLogin($page, '', 'modules/sso.login.tpl', $cancelUrl, $cancelPostData);

        $page->show();
        exit;
    }

}    
