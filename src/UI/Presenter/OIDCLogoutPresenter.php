<?php
namespace Admidio\UI\Presenter;

use Admidio\Infrastructure\Exception;

class OIDCLogoutPresenter extends PagePresenter
{
    /**
     * @throws Exception
     */
    public function __construct(string $clientName = '')
    {
        global $gL10n;

        parent::__construct();

        $headline = $gL10n->get('SYS_SSO_OIDC_LOGOUT_CONFIRMATION');
        $this->setHtmlID('admidio-oidc-logout');
        $this->setTitle($headline);
        $this->setContentFullWidth();
        $this->hideBackLink();

        $this->getSmartyTemplate()->assign('oidcLogoutHeadline', $headline);
        $this->getSmartyTemplate()->assign('oidcLogoutClientName', $clientName);
    }

    /**
     * @throws Exception
     */
    public function createConfirmationForm(): void
    {
        global $gCurrentSession, $gL10n;

        $form = new FormPresenter(
            'adm_oidc_logout_form',
            'modules/sso_oidc.logout-confirm.tpl',
            CURRENT_URL,
            $this,
            array('showRequiredFields' => false)
        );

        $form->addDescription(
            'oidc_logout_description',
            $gL10n->get('SYS_SSO_OIDC_LOGOUT_CONFIRMATION_DESC')
        );
        $form->addSubmitButton(
            'adm_button_logout',
            $gL10n->get('SYS_LOGOUT'),
            array('icon' => 'bi-box-arrow-right')
        );
        $form->addButton(
            'adm_button_cancel',
            $gL10n->get('SYS_CANCEL'),
            array(
                'icon' => 'bi-x-lg',
                'type' => 'submit',
                'class' => 'btn-secondary admidio-margin-bottom'
            )
        );

        $form->addToHtmlPage(false);
        $gCurrentSession->addFormObject($form);
    }
}
