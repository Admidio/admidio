<?php
/**
 * Regression coverage for the format of the OIDC claims built from the profile of a user.
 *
 * A date claim was sent in the date format of the installation (03.01.1988), but OIDC Core 1.0,
 * section 5.1, requires YYYY-MM-DD; the conformance tests oidcc-scope-profile and oidcc-scope-all
 * failed on it. The profile claim updated_at was not sent at all, and gender was the localized
 * text of the profile field instead of the OIDC values "male" and "female".
 */

namespace Admidio\Tests\Integration\Sso;

use Admidio\SSO\Entity\OIDCClient;
use Admidio\SSO\Entity\UserEntity;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;

class SsoUserClaimsTest extends AdministratorTestCase
{
    use SsoClientFixture;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * @testdox The birthdate claim is sent as YYYY-MM-DD
     */
    public function testTheBirthdateIsSentInTheOidcFormat(): void
    {
        $claims = $this->claimsOfCurrentUser($this->createRestrictedClient($this->suffix(), array()));

        $this->assertSame('1988-01-03', $claims['birthdate'] ?? null);
    }

    /**
     * @testdox A date field mapped to a custom claim is sent as YYYY-MM-DD
     */
    public function testAMappedDateFieldIsSentInTheOidcFormat(): void
    {
        $client = $this->createRestrictedClient($this->suffix(), array());
        $client->setFieldMapping(array('date_of_birth' => 'BIRTHDAY'));
        $client->save();

        $claims = $this->claimsOfCurrentUser($client);

        $this->assertSame('1988-01-03', $claims['date_of_birth'] ?? null);
    }

    /**
     * @testdox The updated_at claim is the time of the last change of the user
     */
    public function testUpdatedAtIsTheTimeOfTheLastChange(): void
    {
        global $gCurrentUserId;

        $claims = $this->claimsOfCurrentUser($this->createRestrictedClient($this->suffix(), array()));

        $user = new UserEntity($this->getDatabase(), null, null, (int) $gCurrentUserId);
        $column = (string) $user->getValue('usr_timestamp_change') !== '' ? 'usr_timestamp_change' : 'usr_timestamp_create';

        $this->assertSame((int) $user->getValue($column, 'U'), $claims['updated_at'] ?? null);
    }

    /**
     * @dataProvider genderProvider
     * @testdox The gender option $option is sent in the values of OIDC
     */
    public function testTheGenderIsSentInTheOidcValues(int $option, ?string $expected): void
    {
        global $gCurrentUserId, $gProfileFields, $gL10n;

        $client = $this->createRestrictedClient($this->suffix(), array());
        $user = new UserEntity($this->getDatabase(), $gProfileFields, $client, (int) $gCurrentUserId);
        $user->setValue('GENDER', $option === 0 ? '' : $option);

        // Options beyond male and female keep the text of the profile field
        $expected ??= $option === 0 ? null : $gL10n->get('SYS_DIVERSE');
        $this->assertSame($expected, $user->getClaims()['gender'] ?? null);
    }

    /**
     * @return array<string,array{0:int,1:string|null}>
     */
    public static function genderProvider(): array
    {
        return array(
            'male' => array(1, 'male'),
            'female' => array(2, 'female'),
            'diverse' => array(3, null),
            'not set' => array(0, null)
        );
    }

    /**
     * Build the claims of the current user for a client, with a known birthday.
     *
     * @return array<string,mixed>
     */
    private function claimsOfCurrentUser(OIDCClient $client): array
    {
        global $gCurrentUserId, $gProfileFields;

        $user = new UserEntity($this->getDatabase(), $gProfileFields, $client, (int) $gCurrentUserId);
        // Only set in memory: the claims are built from the loaded user
        $user->setValue('BIRTHDAY', '1988-01-03');

        return $user->getClaims();
    }
}
