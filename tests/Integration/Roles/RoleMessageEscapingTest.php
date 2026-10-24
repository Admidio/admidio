<?php

namespace Admidio\Tests\Integration\Roles;

use Admidio\Session\Entity\Session;
use Admidio\Tests\Support\AdministratorTestCase;
use Admidio\Tests\Support\AdmidioTestFixture;
use Admidio\UI\Presenter\GroupsRolesPresenter;
use DOMDocument;
use DOMXPath;

class RoleMessageEscapingTest extends AdministratorTestCase
{
    private mixed $previousSession;

    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('THEME_PATH')) {
            define('THEME_PATH', ADMIDIO_PATH . '/themes/simple');
        }
        if (!defined('THEME_URL')) {
            define('THEME_URL', ADMIDIO_URL . '/themes/simple');
        }
        $this->previousSession = $GLOBALS['gCurrentSession'] ?? null;
        $GLOBALS['gCurrentSession'] = new Session($this->getDatabase(), COOKIE_PREFIX);
    }

    protected function tearDown(): void
    {
        $GLOBALS['gCurrentSession'] = $this->previousSession;
        parent::tearDown();
    }

    public static function roleNames(): array
    {
        return array(
            'inline script' => array('" onmouseover="alert(document.domain)" x="'),
            'external script' => array('" onmouseover="$.getScript(\'http://localhost:6767/x.js\')" x="'),
            'HTML' => array('<img src=x onerror=alert(1)>'),
            'encoded HTML' => array('&lt;img src=x onerror=alert(1)&gt;'),
            'ordinary punctuation' => array('Förderer "A" & O\'Brien'),
        );
    }

    /**
     * @dataProvider roleNames
     */
    public function testRoleNamesRemainTextInAttributesAndDialogs(string $roleName): void
    {
        global $gCurrentOrgId, $gCurrentUser, $gCurrentUserId;

        require_once dirname(__DIR__, 3) . '/modules/profile/roles_functions.php';

        $db = $this->getDatabase();
        $fixture = new AdmidioTestFixture($db);
        $category = $fixture->createAndSaveCategory('Message escaping', 'ROL', $gCurrentOrgId);
        $role = $fixture->createAndSaveRoleInCategory('Message escaping', $category['cat_id']);
        // Also cover stored legacy/imported values independently of input normalization.
        $db->queryPrepared('UPDATE ' . TBL_ROLES . ' SET rol_name = ? WHERE rol_id = ?', array($roleName, $role['rol_id']));

        // Exercise current, former and future memberships through the actual profile renderer.
        $periods = array(
            array(DATE_NOW, DATE_MAX),
            array('2000-01-01', '2000-01-02'),
            array('2999-01-01', DATE_MAX),
        );
        foreach ($periods as $period) {
            $membership = $fixture->assignUserToRolePeriod($gCurrentUserId, $role['rol_id'], $period[0], $period[1]);
            $statement = $db->queryPrepared(
                'SELECT * FROM ' . TBL_MEMBERS . '
                 INNER JOIN ' . TBL_ROLES . ' ON rol_id = mem_rol_id
                 INNER JOIN ' . TBL_CATEGORIES . ' ON cat_id = rol_cat_id
                 WHERE mem_id = ?',
                array($membership['mem_id'])
            );
            $html = getRoleMemberships('test_memberships', $gCurrentUser, $statement);
            $this->assertMessagesContainOnlyRoleText($html, $roleName, 1);
        }

        // Both overview templates offer different confirmation dialogs for active/inactive roles.
        foreach (array(GroupsRolesPresenter::ROLE_TYPE_ACTIVE, GroupsRolesPresenter::ROLE_TYPE_INACTIVE) as $roleType) {
            $db->queryPrepared('UPDATE ' . TBL_ROLES . ' SET rol_valid = ? WHERE rol_id = ?', array($roleType === GroupsRolesPresenter::ROLE_TYPE_ACTIVE, $role['rol_id']));
            foreach (array('createCards', 'createPermissionsList') as $method) {
                $presenter = new GroupsRolesPresenter('test_role_messages');
                $presenter->$method($category['cat_uuid'], $roleType);
                $this->assertMessagesContainOnlyRoleText($presenter->getPageContent(), $roleName, $method === 'createCards' ? 2 : 1);
            }
        }
    }

    private function assertMessagesContainOnlyRoleText(string $html, string $roleName, int $expectedMessages): void
    {
        $document = $this->parseHtml($html);
        $xpath = new DOMXPath($document);
        $messages = $xpath->query('//a[@data-message]');
        $this->assertCount($expectedMessages, $messages);

        foreach ($messages as $link) {
            $this->assertFalse($link->hasAttribute('onmouseover'), 'The role name must not inject attributes.');
            $this->assertFalse($link->hasAttribute('x'));
            $this->assertTrue($link->hasAttribute('data-href'), 'The confirmation action must remain intact.');

            // getAttribute decodes HTML entities just as jQuery.data does. messageBox then uses .html().
            $dialog = $this->parseHtml('<div>' . $link->getAttribute('data-message') . '</div>');
            $dialogXpath = new DOMXPath($dialog);
            $this->assertSame(0, $dialogXpath->query('//img | //script | //*[@onerror or @onmouseover]')->length);
            $strong = $dialog->getElementsByTagName('strong');
            $this->assertCount(1, $strong, 'The translated dialog must retain its formatting.');
            $this->assertSame($roleName, $strong->item(0)->textContent);
        }
    }

    private function parseHtml(string $html): DOMDocument
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        return $document;
    }
}
