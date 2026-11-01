<?php

namespace Admidio\Tests\Unit\UI;

use Admidio\Infrastructure\Utils\SecurityUtils;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Smarty\Smarty;

class ConfirmationMessageEscapingTest extends TestCase
{
    public static function confirmationMessages(): array
    {
        $cases = array();
        foreach (array(
            'sys-template-parts/list.functions.tpl',
            'modules/announcements.cards.tpl',
            'modules/forum.cards.tpl',
            'modules/inventory.item-fields.list.tpl',
            'modules/weblinks.cards.tpl',
            'modules/photos.albums.tpl',
            'modules/profile.view.relations.tpl',
            'modules/organizations.edit.tpl'
        ) as $template) {
            foreach (array(
                '" onmouseover="alert(1)" x="',
                '<img src=x onerror=alert(1)>',
                '<script>alert(1)</script>',
                '&lt;img src=x onerror=alert(1)&gt;',
                'Förderer "A" & O\'Brien'
            ) as $name) {
                $cases[] = array($template, $name);
            }
        }
        return $cases;
    }

    /**
     * @dataProvider confirmationMessages
     */
    public function testNamesRemainTextAfterAttributeAndDialogParsing(string $template, string $name): void
    {
        $smarty = new Smarty();
        $smarty->setTemplateDir(dirname(__DIR__, 3) . '/themes/simple/templates');
        $smarty->setCompileDir(sys_get_temp_dir());
        $smarty->registerPlugin('function', 'array_key_exists', 'Admidio\Infrastructure\Plugins\Smarty::arrayKeyExists');
        $smarty->registerPlugin('function', 'is_translation_string_id', static fn(array $params): bool => false);

        // Input follows the presenter contract: names are escaped for the dialog HTML.
        // Include a quote in the translation to also exercise attribute escaping.
        $l10n = new class {
            public function get(string $id, array $params = array()): string
            {
                return in_array($id, array('SYS_WANT_DELETE_ENTRY', 'SYS_DELETE_ORGANIZATION'), true)
                    ? 'Delete "<strong>' . $params[0] . '</strong>"?'
                    : $id;
            }
        };
        $escapedName = SecurityUtils::encodeHTML($name);
        $action = array(
            'dataHref' => 'callUrlHideElement(\'entry\', \'delete\', \'csrf\')',
            'dataMessage' => $l10n->get('SYS_WANT_DELETE_ENTRY', array($escapedName)),
            'icon' => 'bi bi-trash',
            'tooltip' => 'Delete'
        );
        $entry = array(
            'uuid' => 'entry', 'name' => $escapedName, 'title' => $escapedName,
            'url' => '', 'urlEdit' => '', 'editable' => true, 'actions' => array($action),
            'description' => '', 'text' => '', 'category' => '', 'categoryUUID' => '',
            'userCreatedProfilePhotoUrl' => '', 'userCreatedName' => '', 'userCreatedTimestamp' => '',
            'repliesCount' => 0, 'views' => 0, 'userProfilePhotoUrl' => '',
            'userUUID' => '', 'userName' => '', 'timestamp' => '',
            'dataType' => '', 'mandatory' => '', 'system' => false,
            'destination' => '', 'destinationHost' => '', 'counter' => 0,
            'editUrl' => '', 'deleteUrl' => 'delete',
            'imageUrl' => '', 'date' => '', 'locked' => false, 'folderMissing' => false,
            'photoCount' => 0, 'relationName' => $escapedName, 'urlUserProfile' => '',
            'userFirstName' => '', 'userLastName' => '', 'urlRelationDelete' => 'delete'
        );
        $elements = array();
        foreach (array('adm_csrf_token', 'org_shortname', 'org_longname', 'org_homepage', 'org_email_administrator') as $id) {
            $elements[$id] = array('property' => 4, 'type' => 'hidden', 'id' => $id, 'value' => '', 'attributes' => array());
        }
        $elements['adm_button_save'] = array('id' => 'save', 'class' => '', 'link' => '', 'attributes' => array(), 'icon' => '', 'value' => 'Save');
        $smarty->assign(array(
            'l10n' => $l10n, 'data' => $entry, 'cards' => array($entry), 'list' => array($entry),
            'categories' => array(array('name' => '', 'links' => array($entry))),
            'pagination' => '', 'urlAdmidio' => '', 'showCategories' => false,
            'target' => '_blank', 'csrfToken' => 'csrf',
            'albumInfo' => null, 'photos' => array(), 'albums' => array($entry),
            'albumsCount' => 1, 'albumPagination' => '',
            'userRelations' => array($entry), 'isAdministratorUsers' => true, 'showRelationsCreateEdit' => false,
            // Organization rows come directly from the database, without HTML escaping.
            'organizationsList' => array(array(
                'org_uuid' => 'entry', 'org_shortname' => '', 'org_longname' => $name,
                'org_homepage' => '', 'org_email_administrator' => ''
            )),
            'attributes' => array(), 'elements' => $elements
        ));

        $document = $this->parseHtml($smarty->fetch($template));
        $links = (new DOMXPath($document))->query('//a[@data-message]');
        $this->assertCount($template === 'sys-template-parts/list.functions.tpl' ? 2 : 1, $links);
        foreach ($links as $link) {
            $this->assertFalse($link->hasAttribute('onmouseover'));
            $this->assertFalse($link->hasAttribute('x'));
            $this->assertTrue($link->hasAttribute('data-href'));
            // Reading the attribute decodes entities; messageBox then parses it as HTML.
            $dialog = $this->parseHtml('<div>' . $link->getAttribute('data-message') . '</div>');
            $this->assertSame(0, (new DOMXPath($dialog))->query('//img | //script | //*[@onerror or @onmouseover]')->length);
            $strong = $dialog->getElementsByTagName('strong');
            $this->assertCount(1, $strong);
            $this->assertSame($name, $strong->item(0)->textContent);
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
