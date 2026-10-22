<?php
namespace Admidio\Users\Service;

use Admidio\Infrastructure\Exception;
use Admidio\ProfileFields\ValueObjects\ProfileFields;
use Admidio\Users\Entity\User;

/**
 * Field authorization shared by profile form generation and saving.
 */
class ProfileEditService
{
    public function __construct(private ?User $editor)
    {
    }

    /**
     * Return only fields editable for every target, before any target values are read.
     *
     * @param User[] $users
     * @return \Admidio\ProfileFields\Entity\ProfileField[]
     */
    public function getEditableFields(ProfileFields $profileFields, array $users): array
    {
        if ($this->editor === null || empty($users)) {
            throw new Exception('SYS_NO_RIGHTS');
        }

        $fields = array();
        foreach ($profileFields->getProfileFields() as $name => $field) {
            foreach ($users as $user) {
                if (!$this->editor->allowedEditProfileField($user, $name)) {
                    continue 2;
                }
            }
            $fields[$name] = $field;
        }

        return $fields;
    }

    /**
     * Extract profile values from a validated form and authorize the entire selection before writing.
     * A null editor is used only for anonymous registration, whose fields are defined by its form.
     *
     * @param User[] $users
     * @param array $formValues Values returned by FormPresenter::validate()
     * @throws Exception
     */
    public function validateProfileValues(array $users, array $formValues): array
    {
        $profileValues = array_filter($formValues, static function ($key) {
            return !str_starts_with($key, 'usr_')
                && !in_array($key, array('adm_password_confirm', 'adm_org_id', 'adm_captcha_code'));
        }, ARRAY_FILTER_USE_KEY);

        if ($this->editor !== null) {
            foreach ($users as $user) {
                foreach ($profileValues as $name => $value) {
                    if (!$this->editor->allowedEditProfileField($user, $name)) {
                        throw new Exception('SYS_NO_RIGHTS');
                    }
                }
            }
        }

        return $profileValues;
    }
}
