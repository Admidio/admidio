<?php
/**
 * The page hooks, and the typed filter they are the first consumer of.
 *
 * `PagePresenter` cannot be constructed here - it reads the settings, the organization, the language
 * and the session out of the database - so what this file executes is the real `Hooks` engine and a
 * stand-in page that carries the three lines of `PagePresenter::show()` and `setHtmlID()` verbatim.
 * The assertion about the order of the assignments is the point: it is what the defect of finding 91
 * was, and the stand-in reproduces the constructor-then-setter sequence of the real class.
 */
require __DIR__ . '/bootstrap.php';

use Admidio\Hooks\Hooks;

/** The parts of PagePresenter that the page hooks touch, over a real Smarty-like variable bag. */
class ProbePage
{
    protected string $id = '';
    protected string $title = '';
    protected string $headline = '';
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

    /** the head of show(), as it is after the patch */
    public function show(): void
    {
        $this->title = Hooks::applyTypedFilters('page_title', $this->title, $this);
        $this->headline = Hooks::applyTypedFilters('page_headline', $this->headline, $this);
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

function aPage(): ProbePage
{
    Hooks::reset();
    $page = new ProbePage();
    $page->setHtmlID('adm_contacts');
    $page->setTitle('Admidio - Contacts');
    $page->setHeadline('Contacts');

    return $page;
}

// ------------------------------------------------------------------ the ID reaches the page at all

$legacy = new LegacyPage();
$legacy->setHtmlID('adm_contacts');
$legacy->show();
check(
    'the previous setHtmlID() left the body id empty, which is what this test pins',
    $legacy->assigned['id'] === '',
    var_export($legacy->assigned['id'], true)
);

$page = aPage();
$page->show();
check('now the id of the page is what was set', $page->assigned['id'] === 'adm_contacts', $page->assigned['id']);

// ------------------------------------------------------------------------------ the two text filters

$page = aPage();
Hooks::addFilter('page_title', function (string $title) {
    return $title . ' | Example Club';
});
Hooks::addFilter('page_headline', function (string $headline, ProbePage $subject) {
    return ($subject->getHtmlID() === 'adm_contacts') ? 'Our contacts' : $headline;
});
$page->show();

check('page_title filters the title', $page->assigned['title'] === 'Admidio - Contacts | Example Club', $page->assigned['title']);
// the headline filter above only answers 'Our contacts' because it was handed the page and asked it
check('page_headline filters the headline and is handed the page', $page->assigned['headline'] === 'Our contacts', $page->assigned['headline']);

// ------------------------------------------------------------------ the filters run in priority order

$page = aPage();
Hooks::addFilter('page_title', function (string $title) { return $title . ' second'; }, 20);
Hooks::addFilter('page_title', function (string $title) { return $title . ' first'; }, 5);
$page->show();
check(
    'a lower priority runs earlier',
    $page->assigned['title'] === 'Admidio - Contacts first second',
    $page->assigned['title']
);

// --------------------------------------------------------------------------- page_before_render last

$page = aPage();
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
check('page_before_render runs after the filters', $order === array('title', 'render'), implode(',', $order));
check(
    'and what it changes still reaches the page',
    $page->assigned['headline'] === 'Changed at the last moment',
    $page->assigned['headline']
);

// ------------------------------------------------------------------- a wrongly typed result throws

$page = aPage();
Hooks::addFilter('page_title', function () {
    return array('not', 'a', 'string');
});
$message = '';
try {
    $page->show();
} catch (\Throwable $exception) {
    $message = $exception->getMessage();
}
check('a filter that answers with the wrong type is refused', str_contains($message, 'returned array instead of string'), $message);
check('and it names the hook', str_contains($message, 'page_title'), $message);

// the untyped applyFilters() still passes anything through, for the sites that want that
Hooks::reset();
Hooks::addFilter('anything', function () {
    return array('still', 'fine');
});
check('applyFilters() is unchanged', Hooks::applyFilters('anything', '') === array('still', 'fine'));

// a bool filter has to answer with a bool, which is what component_visible will need
Hooks::reset();
Hooks::addFilter('permitted', function () {
    return 1;
});
$refused = false;
try {
    Hooks::applyTypedFilters('permitted', true);
} catch (\Throwable $exception) {
    $refused = str_contains($exception->getMessage(), 'returned int instead of bool');
}
check('an int is not a bool', $refused);

Hooks::reset();
Hooks::addFilter('permitted', function () {
    return false;
});
check('and a bool is', Hooks::applyTypedFilters('permitted', true) === false);

exit(testSummary());
