<?php

namespace Admidio\Tests\Integration\Inventory;

use Admidio\Inventory\Entity\ItemField;
use Admidio\Inventory\Entity\SelectOptions;
use Admidio\Inventory\ValueObjects\ItemsData;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\UI\Presenter\InventoryPresenter;
use Admidio\Users\Entity\User;
use Admidio\Infrastructure\Language;
use Admidio\Infrastructure\Utils\StringUtils;

/** Export and print searches must find formatted values, not just stored IDs. */
final class InventoryExportSearchTest extends AdministratorTestCase
{
    public static function searches(): array
    {
        $cases = [];
        foreach (['csv', 'xlsx', 'ods', 'pdf', 'print'] as $mode) {
            foreach (['category', 'keeper', 'borrower', 'dropdown', 'date', 'status', 'raw', 'combined', 'unicode', 'entity', 'missing'] as $field) {
                $cases[$mode . ' ' . $field] = [$mode, $field];
            }
        }
        return $cases;
    }

    /** @dataProvider searches */
    public function testSearchFindsValuesPresentInUnfilteredOutput(string $mode, string $field): void
    {
        global $gCurrentOrgId, $gProfileFields, $gSettingsManager;

        $db = $this->getDatabase();
        $fixture = new AdmidioTestFixture($db);
        $category = $fixture->createAndSaveCategory('Cameracategory ÄÖÜ & Zubehör', 'IVT', $gCurrentOrgId);
        $role = $fixture->createAndSaveRole('Review members', $gCurrentOrgId);
        $users = [];
        foreach (['keeper' => 'Keeperlabel', 'borrower' => 'Borrowerlabel'] as $key => $lastName) {
            $created = $fixture->createAndSaveUser('review_' . $key, $key . '@example.local');
            $user = new User($db, $gProfileFields, $created['usr_id']);
            $user->setValue('FIRST_NAME', 'Review');
            $user->setValue('LAST_NAME', $lastName);
            $user->save();
            $fixture->assignUserToRole($created['usr_id'], $role['rol_id']);
            $users[$key] = $created['usr_id'];
        }
        $dropdown = new ItemField($db);
        $dropdown->setValue('inf_org_id', $gCurrentOrgId);
        $dropdown->setValue('inf_name', 'Review transport');
        $dropdown->setValue('inf_type', 'DROPDOWN');
        $dropdown->save();
        $option = new SelectOptions($db, (int)$dropdown->getValue('inf_id'));
        $option->setValue('ifo_inf_id', $dropdown->getValue('inf_id'));
        $option->setValue('ifo_value', 'Transportlabel');
        $option->save();
        $date = new ItemField($db);
        $date->setValue('inf_org_id', $gCurrentOrgId);
        $date->setValue('inf_name', 'Review date');
        $date->setValue('inf_type', 'DATE');
        $date->save();
        $gSettingsManager->set('system_date', 'd.m.Y');
        $items = new ItemsData($db, $gCurrentOrgId);
        $items->readItemData('');
        $items->createNewItem($category['cat_uuid']);
        $items->setValue('ITEMNAME', 'CameraReview');
        $items->setValue('KEEPER', (string)$users['keeper']);
        $items->setValue('LAST_RECEIVER', (string)$users['borrower']);
        $items->setValue('BORROW_DATE', '2020-02-14');
        $items->setValue('RETURN_DATE', '2020-02-15');
        $items->setValue($dropdown->getValue('inf_name_intern'), (string)$option->getValue('ifo_id'));
        $items->setValue($date->getValue('inf_name_intern'), '2020-02-14');
        $items->saveItemData();
        $search = [
            'category' => 'Cameracategory', 'keeper' => 'Keeperlabel', 'borrower' => 'Borrowerlabel',
            'dropdown' => 'Transportlabel', 'date' => '14.02.2020',
            'status' => Language::translateIfTranslationStrId('SYS_INVENTORY_FILTER_IN_USE_ITEMS'),
            'raw' => 'CameraReview', 'combined' => 'CameraReview Transportlabel',
            'unicode' => 'äöü', 'entity' => '&', 'missing' => 'NoSuchVisibleValue',
        ][$field];
        $previousGet = $_GET;
        try {
            $_GET = [];
            $all = (new InventoryPresenter(false))->prepareData($mode);
            $this->assertCount(1, $all['rows']);
            $visible = html_entity_decode(strip_tags(implode(' ', $all['rows'][0]['data'])), ENT_QUOTES | ENT_HTML5);
            if ($field !== 'missing') {
                foreach (explode(' ', $search) as $word) {
                    $this->assertStringContainsString(StringUtils::strToLower($word), StringUtils::strToLower($visible), 'Fixture must actually display the searched value.');
                }
            }
            $_GET = ['items_filter_string' => $search];
            $filtered = (new InventoryPresenter(false))->prepareData($mode);
            $this->assertCount($field === 'missing' ? 0 : 1, $filtered['rows'], 'Displayed-value filter: ' . $field . ' in ' . $mode);
        } finally {
            $_GET = $previousGet;
        }
    }
}
