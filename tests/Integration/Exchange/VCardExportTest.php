<?php
/**
 * vCard Export Tests
 *
 * Tests the contact card a profile can be downloaded as. User::getVCard() writes vCard 3.0 and only
 * includes the fields the current user is allowed to see, so the card of the same person can differ
 * depending on who asks for it.
 */

namespace Admidio\Tests\Integration\Exchange;

use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\DatabaseTestCase;
use Admidio\Tests\Support\PermissionContext;
use Admidio\Users\Entity\User;

class VCardExportTest extends DatabaseTestCase
{
    use PermissionContext;

    /**
     * The organization created by the installation.
     */
    private const ORG_ID = 1;

    protected function getFixture(): AdmidioTestFixture
    {
        return new AdmidioTestFixture($this->getDatabase());
    }

    /**
     * The administrator that the installation created.
     */
    private function administrator(): User
    {
        $sql = 'SELECT usr_id FROM ' . TBL_USERS . ' WHERE usr_login_name = ?';
        $usrId = (int) $this->getDatabase()->queryPrepared($sql, ['admin'])->fetchColumn();

        return new User($this->getDatabase(), $GLOBALS['gProfileFields'], $usrId);
    }

    /**
     * Run a callback as the administrator of the installed organization.
     */
    private function asAdministrator(callable $callback)
    {
        return $this->withCurrentUser($this->administrator(), self::ORG_ID, true, $callback);
    }

    /**
     * Create a contact with the given profile values.
     *
     * @param array<string,string> $values
     * @return array{usr_id: int, usr_uuid: string}
     */
    private function createContact(string $login, array $values): array
    {
        $fixture = $this->getFixture();
        $user = $fixture->createAndSaveUser($login, $login . '@example.local');

        $this->asAdministrator(function () use ($user, $values) {
            $entity = new User($this->getDatabase(), $GLOBALS['gProfileFields'], $user['usr_id']);
            $entity->saveChangesWithoutRights();
            foreach ($values as $field => $value) {
                $entity->setValue($field, $value);
            }
            $entity->save();
        });

        return $user;
    }

    /**
     * The vCard of a contact, read as the administrator.
     */
    private function vCardOf(int $usrId): string
    {
        return $this->asAdministrator(function () use ($usrId) {
            $user = new User($this->getDatabase(), $GLOBALS['gProfileFields'], $usrId);

            return $user->getVCard();
        });
    }

    /**
     * Split a vCard into its lines.
     *
     * @return array<int,string>
     */
    private function linesOf(string $vCard): array
    {
        return array_values(array_filter(preg_split('/\R/', $vCard), static fn ($line) => $line !== ''));
    }

    /**
     * Test the frame of the card
     *
     * @testdox A vCard begins and ends with the markers of version 3.0
     */
    public function testVCardBeginsAndEndsWithTheMarkersOfVersionThree(): void
    {
        $contact = $this->createContact('vcardframe', array('LAST_NAME' => 'Muster', 'FIRST_NAME' => 'Erika'));

        $lines = $this->linesOf($this->vCardOf($contact['usr_id']));

        $this->assertEquals('BEGIN:VCARD', $lines[0]);
        $this->assertEquals('VERSION:3.0', $lines[1]);
        $this->assertEquals('END:VCARD', end($lines));
    }

    /**
     * Test the name of the card
     *
     * @testdox A vCard carries the structured and the displayed name
     */
    public function testVCardCarriesTheStructuredAndTheDisplayedName(): void
    {
        $contact = $this->createContact('vcardname', array('LAST_NAME' => 'Muster', 'FIRST_NAME' => 'Erika'));

        $vCard = $this->vCardOf($contact['usr_id']);

        // the structured name is last name first, the displayed one reads naturally
        $this->assertStringContainsString('N:Muster;Erika;', $vCard);
        $this->assertStringContainsString('FN:Erika Muster', $vCard);

        // the login name is offered as the nickname
        $this->assertStringContainsString('NICKNAME:vcardname', $vCard);
    }

    /**
     * Test the contact details
     *
     * @testdox A vCard carries the address and the email address of the contact
     */
    public function testVCardCarriesTheAddressAndTheEmailAddress(): void
    {
        $contact = $this->createContact('vcarddetails', array(
            'LAST_NAME' => 'Muster',
            'FIRST_NAME' => 'Erika',
            'STREET' => 'Hauptstrasse 1',
            'POSTCODE' => '1010',
            'CITY' => 'Wien',
            'EMAIL' => 'erika@example.local'
        ));

        $vCard = $this->vCardOf($contact['usr_id']);

        $this->assertStringContainsString('ADR;TYPE=home:', $vCard);
        $this->assertStringContainsString('Hauptstrasse 1', $vCard);
        $this->assertStringContainsString('Wien', $vCard);
        $this->assertStringContainsString('1010', $vCard);
        $this->assertStringContainsString('EMAIL;TYPE=home:erika@example.local', $vCard);
    }

    /**
     * Test that the card identifies the record
     *
     * @testdox A vCard identifies the contact by the unique id of the user
     */
    public function testVCardIdentifiesTheContactByTheUniqueIdOfTheUser(): void
    {
        $contact = $this->createContact('vcarduid', array('LAST_NAME' => 'Muster', 'FIRST_NAME' => 'Erika'));

        $vCard = $this->vCardOf($contact['usr_id']);

        // the uid lets an address book recognise the same person again on the next export
        $this->assertStringContainsString('UID:urn:uuid:' . $contact['usr_uuid'], $vCard);

        // and the card says when it was written
        $this->assertMatchesRegularExpression('/REV:\d{8}T\d{6}Z/', $vCard);
    }

    /**
     * Test that empty fields are left out
     *
     * @testdox A field the contact has no value for does not appear in the vCard
     */
    public function testFieldWithoutAValueDoesNotAppearInTheVCard(): void
    {
        $contact = $this->createContact('vcardsparse', array('LAST_NAME' => 'Muster', 'FIRST_NAME' => 'Erika'));

        $vCard = $this->vCardOf($contact['usr_id']);

        // no email and no phone number were entered, so the card offers none
        $this->assertStringNotContainsString('EMAIL', $vCard);
        $this->assertStringNotContainsString('TEL', $vCard);

        // the name is there all the same
        $this->assertStringContainsString('FN:Erika Muster', $vCard);
    }
}
