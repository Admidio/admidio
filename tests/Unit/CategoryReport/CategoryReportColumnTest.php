<?php

namespace Admidio\Tests\Unit\CategoryReport;

use Admidio\CategoryReport\Entity\CategoryReportColumn;
use PHPUnit\Framework\TestCase;

class CategoryReportColumnTest extends TestCase
{
    /**
     * @dataProvider fieldProvider
     * @param array<string,int|string|null> $expected
     */
    public function testFieldIdentifierIsSplitIntoDatabaseColumns(string $field, array $expected): void
    {
        $this->assertSame($expected, CategoryReportColumn::getFieldDatabaseValues($field));
    }

    /** @return array<string,array{string,array<string,int|string|null>}> */
    public function fieldProvider(): array
    {
        $emptyReferences = array(
            'crc_usf_id' => null,
            'crc_rol_id' => null,
            'crc_cat_id' => null,
            'crc_special_field' => null
        );

        return array(
            'profile field' => array('p42', array_merge(array('crc_field_type' => 'profile_field'), $emptyReferences, array(
                'crc_usf_id' => 42
            ))),
            'role property' => array('w17', array_merge(array('crc_field_type' => 'role_membership_without_leader'), $emptyReferences, array(
                'crc_rol_id' => 17
            ))),
            'category' => array('c5', array_merge(array('crc_field_type' => 'role_category'), $emptyReferences, array(
                'crc_cat_id' => 5
            ))),
            'user field' => array('ulogin_name', array_merge(array('crc_field_type' => 'user_field'), $emptyReferences, array(
                'crc_special_field' => 'login_name'
            ))),
            'additional field' => array('ddummy', array_merge(array('crc_field_type' => 'membership_duration'), $emptyReferences, array(
                'crc_special_field' => 'dummy'
            ))),
            'unknown special field' => array('custom', array_merge(array('crc_field_type' => 'special_field'), $emptyReferences, array(
                'crc_special_field' => 'custom'
            )))
        );
    }
}
