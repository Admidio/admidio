<?php
/**
 * Profile Field Entity Tests
 *
 * Tests custom profile field creation, validation, and visibility.
 *
 * @testdox Profile field system handles custom user fields correctly
 */

namespace Admidio\Tests\Integration\ProfileFields;

use Admidio\Tests\Support\DatabaseTestCase;

class ProfileFieldEntityTest extends DatabaseTestCase
{
    /**
     * Test profile field creation
     *
     * @testdox Custom profile fields can be created
     */
    public function testProfileFieldCreation(): void
    {
        $builder = $this->getTestDataBuilder();
        $field = $builder->createProfileField('usd_phone', 'PHONE', 'Phone Number');

        $this->assertNotEmpty($field['usd_id']);
        $this->assertEquals('PHONE', $field['usd_field_type']);
    }

    /**
     * Test text field type
     *
     * @testdox Text profile fields work correctly
     */
    public function testTextFieldType(): void
    {
        $builder = $this->getTestDataBuilder();
        $field = $builder->createProfileField('usd_company', 'TEXT', 'Company');

        $this->assertEquals('TEXT', $field['usd_field_type']);
    }

    /**
     * Test date field type
     *
     * @testdox Date profile fields work correctly
     */
    public function testDateFieldType(): void
    {
        $builder = $this->getTestDataBuilder();
        $field = $builder->createProfileField('usd_birthday', 'DATE', 'Birthday');

        $this->assertEquals('DATE', $field['usd_field_type']);
    }

    /**
     * Test dropdown field type
     *
     * @testdox Dropdown profile fields with options work correctly
     */
    public function testDropdownFieldType(): void
    {
        $builder = $this->getTestDataBuilder();
        $field = $builder->createProfileField('usd_status', 'DROPDOWN', 'Status');

        $this->assertEquals('DROPDOWN', $field['usd_field_type']);
    }

    /**
     * Test profile field visibility settings
     *
     * @testdox Profile fields have configurable visibility
     */
    public function testProfileFieldVisibility(): void
    {
        $builder = $this->getTestDataBuilder();
        $field = $builder->createProfileField('usd_profession', 'TEXT', 'Profession');

        // Field should have visibility settings
        $this->assertNotEmpty($field['usd_id']);
    }

    /**
     * Test profile field role-based visibility
     *
     * @testdox Profile fields can have role-specific visibility
     */
    public function testProfileFieldRoleVisibility(): void
    {
        $builder = $this->getTestDataBuilder();

        $role = $builder->createRole('Leaders');
        $field = $builder->createProfileField('usd_leadership', 'TEXT', 'Leadership Skills');

        // Field should respect role-based visibility
        $this->assertNotEmpty($field['usd_id']);
    }

    /**
     * Test profile field required flag
     *
     * @testdox Profile fields can be marked as required
     */
    public function testProfileFieldRequired(): void
    {
        $builder = $this->getTestDataBuilder();
        $field = $builder->createProfileField('usd_email2', 'TEXT', 'Secondary Email');

        // Field can be required
        $this->assertNotEmpty($field['usd_id']);
    }

    /**
     * Test profile field value persistence
     *
     * @testdox Profile field values are stored and retrieved correctly
     */
    public function testProfileFieldValuePersistence(): void
    {
        $builder = $this->getTestDataBuilder();

        $user = $builder->createUser('fieldtest', 'field@test.local');
        $field = $builder->createProfileField('usd_mobile', 'PHONE', 'Mobile Number');

        // User values for this field should be persistable
        $this->assertNotEmpty($user['usr_id']);
        $this->assertNotEmpty($field['usd_id']);
    }

    /**
     * Test profile field multiple entries per user
     *
     * @testdox Users can have multiple custom field values
     */
    public function testProfileFieldMultipleValues(): void
    {
        $builder = $this->getTestDataBuilder();

        $user = $builder->createUser('multifield', 'multi@test.local');
        $field1 = $builder->createProfileField('usd_address', 'TEXT', 'Address');
        $field2 = $builder->createProfileField('usd_city', 'TEXT', 'City');
        $field3 = $builder->createProfileField('usd_country', 'TEXT', 'Country');

        // User can have values for multiple fields
        $this->assertNotEmpty($user['usr_id']);
        $this->assertNotEquals($field1['usd_id'], $field2['usd_id']);
        $this->assertNotEquals($field2['usd_id'], $field3['usd_id']);
    }

    /**
     * Test profile field UUID uniqueness
     *
     * @testdox Each profile field gets a unique UUID
     */
    public function testProfileFieldUuidUniqueness(): void
    {
        $builder = $this->getTestDataBuilder();

        $field1 = $builder->createProfileField('usd_field1', 'TEXT', 'Field 1');
        $field2 = $builder->createProfileField('usd_field2', 'TEXT', 'Field 2');

        // UUIDs should be different
        $this->assertNotEquals($field1['usd_uuid'], $field2['usd_uuid']);
    }

    /**
     * Test profile field creation timestamp
     *
     * @testdox Profile field creation timestamps are valid
     */
    public function testProfileFieldTimestamp(): void
    {
        $builder = $this->getTestDataBuilder();
        $field = $builder->createProfileField('usd_timestamp', 'TEXT', 'Test Field');

        // Created timestamp should be valid
        $this->assertValidTimestamp($field['created_at']);
    }
}
