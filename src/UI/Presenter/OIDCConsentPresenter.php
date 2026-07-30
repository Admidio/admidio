<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;
use Admidio\SSO\Entity\OIDCClient;

/**
 * @brief Presenter for the OIDC consent page.
 *
 * This presenter creates the form that allows a user to approve or deny
 * an OIDC authorization request.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */
class OIDCConsentPresenter extends PagePresenter
{
    /**
     * Constructor creates the page object and initializes all parameters.
     * @param string $clientName Name of the OIDC client.
     * @throws Exception
     */
    public function __construct(string $clientName)
    {
        global $gL10n;

        parent::__construct();

        $this->setHtmlID('admidio-oidc-consent');
        $this->setHeadline($gL10n->get('SYS_SSO_OIDC_AUTHORIZE_CLIENT', array($clientName)));
    }

    /**
     * Create the consent form for an OIDC authorization request.
     * @param OIDCClient $client
     * @param array $scopes
     * @return void
     * @throws Exception
     */
    public function createConsentForm(OIDCClient $client, array $scopes): void
    {
        global $gCurrentSession, $gL10n;

        $form = new FormPresenter(
            'adm_oidc_consent_form',
            'modules/sso_oidc.consent.tpl',
            CURRENT_URL,
            $this,
            array('showRequiredFields' => false)
        );


        $form->addDescription(
            'oidc_consent_description',
            $gL10n->get(
                'SYS_SSO_OIDC_CONSENT_DESC',
                array(htmlspecialchars($client->readableName(), ENT_QUOTES))
            )
        );

        $form->addCustomContent(
            'oidc_consent_scopes',
            $gL10n->get('SYS_SSO_OIDC_REQUESTED_ACCESS'),
            $this->createScopeList($scopes)
        );

        $form->addSubmitButton(
            'adm_button_approve',
            $gL10n->get('SYS_SSO_OIDC_ALLOW_ACCESS'),
            array('icon' => 'bi-check-lg')
        );

        $form->addButton(
            'adm_button_deny',
            $gL10n->get('SYS_SSO_OIDC_DENY_ACCESS'),
            array(
                'icon' => 'bi-x-lg',
                'type' => 'submit',
                'class' => 'btn-danger'
            )
        );

        $form->addToHtmlPage(false);
        $gCurrentSession->addFormObject($form);
    }

    /**
     * Create a user-friendly list of the requested OIDC scopes.
     * @param array $scopes
     * @return string
     */
    private function createScopeList(array $scopes): string
    {
        global $gL10n;

        $scopeLanguageKeys = array(
            'openid' => array(
                'name' => 'SYS_SSO_OIDC_SCOPE_OPENID',
                'description' => 'SYS_SSO_OIDC_SCOPE_OPENID_DESC'
            ),
            'profile' => array(
                'name' => 'SYS_SSO_OIDC_SCOPE_PROFILE',
                'description' => 'SYS_SSO_OIDC_SCOPE_PROFILE_DESC'
            ),
            'email' => array(
                'name' => 'SYS_SSO_OIDC_SCOPE_EMAIL',
                'description' => 'SYS_SSO_OIDC_SCOPE_EMAIL_DESC'
            ),
            'phone' => array(
                'name' => 'SYS_SSO_OIDC_SCOPE_PHONE',
                'description' => 'SYS_SSO_OIDC_SCOPE_PHONE_DESC'
            ),
            'address' => array(
                'name' => 'SYS_SSO_OIDC_SCOPE_ADDRESS',
                'description' => 'SYS_SSO_OIDC_SCOPE_ADDRESS_DESC'
            ),
            'groups' => array(
                'name' => 'SYS_SSO_OIDC_SCOPE_GROUPS',
                'description' => 'SYS_SSO_OIDC_SCOPE_GROUPS_DESC'
            ),
            'custom' => array(
                'name' => 'SYS_SSO_OIDC_SCOPE_CUSTOM',
                'description' => 'SYS_SSO_OIDC_SCOPE_CUSTOM_DESC'
            )
        );

        $html = '<div class="list-group">';

        foreach ($scopes as $scope) {
            $scopeName = is_object($scope) && method_exists($scope, 'getIdentifier')
                ? $scope->getIdentifier()
                : (string)$scope;

            $escapedScopeName = htmlspecialchars($scopeName, ENT_QUOTES);

            if (array_key_exists($scopeName, $scopeLanguageKeys)) {
                $name = $gL10n->get($scopeLanguageKeys[$scopeName]['name']);
                $description = $gL10n->get($scopeLanguageKeys[$scopeName]['description']);
            } else {
                $name = $gL10n->get('SYS_SSO_OIDC_SCOPE_OTHER', array($escapedScopeName));
                $description = $gL10n->get('SYS_SSO_OIDC_SCOPE_OTHER_DESC');
            }

            $html .= '
                <div class="list-group-item">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-check-circle-fill text-success me-3 mt-1"></i>
                        <div>
                            <div class="fw-semibold">' . $name . '</div>
                            <div class="text-body-secondary">' . $description . '</div>
                            <div class="form-text">' . $escapedScopeName . '</div>
                        </div>
                    </div>
                </div>';
        }

        $html .= '</div>';

        return $html;
    }
}