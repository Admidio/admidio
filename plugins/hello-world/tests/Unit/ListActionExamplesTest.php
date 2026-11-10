<?php
/**
 * The examples of the list_row_actions filter, and the example of how a plugin tests such a filter:
 * the changes are a method of their own, so they run without the plugin being enabled.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 */

namespace AdmidioPlugin\HelloWorld\Tests\Unit;

use Admidio\Tests\Unit\Plugins\Support\PluginTestCase;
use AdmidioPlugin\HelloWorld\ListActionExamples;

// A test runs without the plugin loader, which would otherwise find the classes through the manifest.
require_once dirname(__DIR__, 2) . '/src/Greeting.php';
require_once dirname(__DIR__, 2) . '/src/ListActionExamples.php';

final class ListActionExamplesTest extends PluginTestCase
{
    private function ids(array $actions): array
    {
        return array_map(static fn(array $action) => $action['id'] ?? null, $actions);
    }

    private function action(string $id): array
    {
        return array('id' => $id, 'url' => '/' . $id, 'icon' => 'bi bi-circle', 'tooltip' => $id);
    }

    public function testTheContactsGetAGreetActionAndTheEmailActionComesFirst(): void
    {
        $actions = ListActionExamples::apply(
            array($this->action('edit'), $this->action('send_email'), $this->action('delete')),
            'contacts',
            array('first_name' => 'Jane', 'last_name' => 'Doe')
        );

        $this->assertSame(array('send_email', 'edit', 'delete', 'hello-world_greet'), $this->ids($actions));
        $this->assertArrayNotHasKey('dataHref', $actions[3], 'the message only has an OK button');
    }

    public function testTheAnnouncementsCannotBeCopied(): void
    {
        $actions = ListActionExamples::apply(array($this->action('edit'), $this->action('copy')), 'announcements', array());

        $this->assertSame(array('edit'), $this->ids($actions));
    }

    public function testTheDeleteActionOfTheMenuIsRed(): void
    {
        $actions = ListActionExamples::apply(array($this->action('edit'), $this->action('delete')), 'menu', array());

        $this->assertArrayNotHasKey('class', $actions[0]);
        $this->assertSame('text-danger', $actions[1]['class']);
    }

    public function testOtherListsAreNotTouched(): void
    {
        $actions = array($this->action('edit'), $this->action('copy'));

        $this->assertSame($actions, ListActionExamples::apply($actions, 'categories', array()));
    }
}
