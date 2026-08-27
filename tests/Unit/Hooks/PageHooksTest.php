<?php
/**
 * The page hooks, and the typed filter they are the first consumer of.
 *
 * `PagePresenter` cannot be constructed here - it reads the settings, the organization, the language
 * and the session out of the database - so what this executes is the real `Hooks` engine and a
 * stand-in page that carries the three lines of `PagePresenter::show()` and `setHtmlID()` verbatim.
 * The assertion about the order of the assignments is the point: it is what the defect of finding 91
 * was, and the stand-in reproduces the constructor-then-setter sequence of the real class.
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Tests\Support\AdmidioTestCase;
use Throwable;

/** The parts of PagePresenter that the page hooks touch, over a real Smarty-like variable bag. */
class ProbePage
{
    protected string $id = '';
    protected string $title = '';
    protected string $headline = '';
    protected bool $textFiltered = false;
    public array $assigned = array();

    public function __construct()
    {
        // assignBasicSmartyVariables(), from the constructor
        $this->assigned['id'] = $this->id;
        $this->assigned['title'] = $this->title;
        $this->assigned['headline'] = $this->headline;
    }

    /** setHtmlID(), as it is after the patch */
    public function setHtmlID(string $htmlID): void
    {
        $this->id = $htmlID;
        $this->assigned['id'] = $this->id;
    }

    public function getHtmlID(): string
    {
        return $this->id;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setHeadline(string $headline): void
    {
        $this->headline = $headline;
    }

    /**
     * filterText(), as it is after the patch: page_title and page_headline run exactly once,
     * however many times this is called and whether it runs before or after show().
     */
    public function filterText(): void
    {
        if ($this->textFiltered) {
            return;
        }
        $this->textFiltered = true;

        $this->title = Hooks::applyTypedFilters('page_title', $this->title, $this);
        $this->headline = Hooks::applyTypedFilters('page_headline', $this->headline, $this);
    }

    /** getFilteredHeadline(), as it is after the patch: for a caller that needs it before show(). */
    public function getFilteredHeadline(): string
    {
        $this->filterText();

        return $this->headline;
    }

    /** the head of show(), as it is after the patch */
    public function show(): void
    {
        $this->filterText();
        Hooks::doAction('page_before_render', $this);

        $this->assigned['id'] = $this->id;
        $this->assigned['title'] = $this->title;
        $this->assigned['headline'] = $this->headline;
    }
}

/** setHtmlID() as it was: the member is set, the template never hears about it. */
class LegacyPage extends ProbePage
{
    public function setHtmlID(string $htmlID): void
    {
        $this->id = $htmlID;
    }

    public function show(): void
    {
        // the old show() assigned neither the id nor the two texts
    }
}

class PageHooksTest extends AdmidioTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Hooks::reset();
    }

    private function aPage(): ProbePage
    {
        Hooks::reset();
        $page = new ProbePage();
        $page->setHtmlID('adm_contacts');
        $page->setTitle('Admidio - Contacts');
        $page->setHeadline('Contacts');

        return $page;
    }

    public function testThePreviousSetHtmlIdLeftTheBodyIdEmptyWhichIsWhatThisTestPins(): void
    {
        $legacy = new LegacyPage();
        $legacy->setHtmlID('adm_contacts');
        $legacy->show();

        $this->assertSame('', $legacy->assigned['id']);
    }

    public function testNowTheIdOfThePageIsWhatWasSet(): void
    {
        $page = $this->aPage();
        $page->show();

        $this->assertSame('adm_contacts', $page->assigned['id']);
    }

    public function testTheTwoTextFilters(): void
    {
        $page = $this->aPage();
        Hooks::addFilter('page_title', function (string $title) {
            return $title . ' | Example Club';
        });
        Hooks::addFilter('page_headline', function (string $headline, ProbePage $subject) {
            return ($subject->getHtmlID() === 'adm_contacts') ? 'Our contacts' : $headline;
        });
        $page->show();

        $this->assertSame('Admidio - Contacts | Example Club', $page->assigned['title'], 'page_title filters the title');
        // the headline filter above only answers 'Our contacts' because it was handed the page and asked it
        $this->assertSame('Our contacts', $page->assigned['headline'], 'page_headline filters the headline and is handed the page');
    }

    public function testGetFilteredHeadlineRunsTheFilterExactlyOnceEitherSideOfShow(): void
    {
        $page = $this->aPage();
        $calls = 0;
        Hooks::addFilter('page_headline', function (string $headline) use (&$calls) {
            $calls++;
            return $headline;
        });
        $first = $page->getFilteredHeadline();
        $second = $page->getFilteredHeadline();

        $this->assertSame('Contacts', $first, 'a caller can read the filtered headline before show()');
        $this->assertSame(1, $calls, 'asking twice does not filter twice');

        $page->show();
        $this->assertSame(1, $calls, 'show() finds the work already done');
        $this->assertSame($second, $page->assigned['headline'], 'and the page renders with the same value');
    }

    public function testTheFiltersRunInPriorityOrder(): void
    {
        $page = $this->aPage();
        Hooks::addFilter('page_title', function (string $title) { return $title . ' second'; }, 20);
        Hooks::addFilter('page_title', function (string $title) { return $title . ' first'; }, 5);
        $page->show();

        $this->assertSame('Admidio - Contacts first second', $page->assigned['title'], 'a lower priority runs earlier');
    }

    public function testPageBeforeRenderRunsAfterTheFiltersLast(): void
    {
        $page = $this->aPage();
        $order = array();
        Hooks::addFilter('page_title', function (string $title) use (&$order) {
            $order[] = 'title';
            return $title;
        });
        Hooks::addAction('page_before_render', function (ProbePage $subject) use (&$order) {
            $order[] = 'render';
            $subject->setHeadline('Changed at the last moment');
        });
        $page->show();

        $this->assertSame(array('title', 'render'), $order);
        $this->assertSame('Changed at the last moment', $page->assigned['headline'], 'and what it changes still reaches the page');
    }

    public function testAWronglyTypedResultThrowsAndNamesTheHook(): void
    {
        $page = $this->aPage();
        Hooks::addFilter('page_title', function () {
            return array('not', 'a', 'string');
        });

        $message = '';
        try {
            $page->show();
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
        }
        $this->assertStringContainsString('returned array instead of string', $message);
        $this->assertStringContainsString('page_title', $message);
    }

    public function testUntypedApplyFiltersStillPassesAnythingThrough(): void
    {
        Hooks::reset();
        Hooks::addFilter('anything', function () {
            return array('still', 'fine');
        });

        $this->assertSame(array('still', 'fine'), Hooks::applyFilters('anything', ''));
    }

    public function testABoolFilterHasToAnswerWithABool(): void
    {
        Hooks::reset();
        Hooks::addFilter('permitted', function () {
            return 1;
        });

        $refused = false;
        try {
            Hooks::applyTypedFilters('permitted', true);
        } catch (Throwable $exception) {
            $refused = str_contains($exception->getMessage(), 'returned int instead of bool');
        }
        $this->assertTrue($refused, 'an int is not a bool');

        Hooks::reset();
        Hooks::addFilter('permitted', function () {
            return false;
        });
        $this->assertFalse(Hooks::applyTypedFilters('permitted', true), 'and a bool is');
    }
}
