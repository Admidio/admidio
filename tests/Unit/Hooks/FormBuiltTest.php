<?php
/**
 * form_built: the point at which a form is finished, and the element API that makes the hook useful.
 *
 * The real `FormPresenter` is executed here - it needs nothing but the autoloader - so what is
 * checked is the actual form, the actual dispatch and the actual validate().
 */

namespace Admidio\Tests\Unit\Hooks;

use Admidio\Hooks\Hooks;
use Admidio\Tests\Support\AdmidioTestCase;
use Admidio\UI\Presenter\FormPresenter;
use Psr\Log\NullLogger;
use Throwable;

class FormBuiltTest extends AdmidioTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Hooks::reset();
        // FormPresenter::validate() logs a rejected payload through $gLogger unconditionally.
        $GLOBALS['gLogger'] = $GLOBALS['gLogger'] ?? new NullLogger();
    }

    /** Hooks::reset() here, not only in setUp(), because some tests build more than one form. */
    private function aForm(string $id = 'adm_probe_form'): FormPresenter
    {
        Hooks::reset();
        $form = new FormPresenter($id, 'form.tpl', 'index.php');
        $form->addInput('first_name', 'First name', '');
        $form->addInput('last_name', 'Last name', '');

        return $form;
    }

    private function elementIds(FormPresenter $form): string
    {
        return implode(',', array_keys($form->getElements()));
    }

    /** A form with the three element types whose entries validate() enforces. */
    private function aFormWithChoices(): FormPresenter
    {
        Hooks::reset();
        $form = new FormPresenter('adm_choices_form', 'form.tpl', 'index.php');
        $form->addSelectBox('role', 'Role', array(1 => 'Choir', 2 => 'Board', 3 => 'Youth'));
        $form->addRadioButton('gender', 'Gender', array('f' => 'female', 'm' => 'male'));
        $form->addInput('note', 'Note', '');

        return $form;
    }

    private function offeredIds(FormPresenter $form, string $elementId): string
    {
        // getElements() finishes the form, getElement() deliberately does not
        $element = $form->getElements()[$elementId];
        $values = $element['values'];
        // a select carries a list of id/value pairs, a radio an array of value to label
        $ids = ($element['type'] === 'select') ? array_column($values, 'id') : array_keys($values);

        return implode(',', $ids);
    }

    public function testHookFiresExactlyOnce(): void
    {
        $form = $this->aForm();
        $dispatched = array();
        Hooks::addAction('form_built', function (FormPresenter $built) use (&$dispatched) {
            $dispatched[] = $built->getId();
        });

        $this->assertSame(array(), $dispatched, 'nothing is dispatched while the form is being built');

        $form->finalize();
        $this->assertSame(array('adm_probe_form'), $dispatched, 'finalize() dispatches form_built with the form');

        $form->finalize();
        $form->getElements();
        $form->getAttributes();
        $this->assertCount(1, $dispatched, 'and never again, however often the form is read');
    }

    public function testEveryWayOfReadingAFinishedFormFinalizesIt(): void
    {
        foreach (array('getElements', 'getAttributes') as $reader) {
            $form = $this->aForm();
            $dispatched = array();
            Hooks::addAction('form_built', function (FormPresenter $built) use (&$dispatched) {
                $dispatched[] = $built->getId();
            });
            $form->$reader();
            $this->assertCount(1, $dispatched, $reader . '() finalizes the form');
        }
    }

    public function testCallbackCanRemoveInsertAndReplaceElements(): void
    {
        $form = $this->aForm();
        Hooks::addAction('form_built', function (FormPresenter $built) {
            $built->removeElement('last_name');
            $built->insertElement('nickname', array('id' => 'nickname', 'type' => 'text', 'label' => 'Nickname'), 'first_name');
            $built->replaceElement('first_name', array('id' => 'first_name', 'type' => 'text', 'label' => 'Given name'));
        });

        $this->assertSame('adm_csrf_token,nickname,first_name', $this->elementIds($form));
        $this->assertSame('Given name', $form->getElement('first_name')['label'], 'the replacement is what the form holds');
    }

    public function testTheChangeReachesValidation(): void
    {
        $form = $this->aForm();
        Hooks::addAction('form_built', function (FormPresenter $built) {
            $built->removeElement('last_name');
        });
        $form->finalize();

        $rejected = false;
        try {
            $form->validate(array('adm_csrf_token' => $form->getCsrfToken(), 'first_name' => 'John', 'last_name' => 'Doe'));
        } catch (Throwable $exception) {
            $rejected = str_contains($exception->getMessage(), 'Invalid payload');
        }
        $this->assertTrue($rejected, 'a removed element is not accepted by validate() any more');

        $accepted = $form->validate(array('adm_csrf_token' => $form->getCsrfToken(), 'first_name' => 'John'));
        $this->assertArrayHasKey('first_name', $accepted, 'what is left still validates');
    }

    public function testReadingOneElementDoesNotFinishTheFormEarly(): void
    {
        $form = $this->aForm();
        $dispatched = 0;
        Hooks::addAction('form_built', function () use (&$dispatched) {
            $dispatched++;
        });

        $this->assertTrue($form->hasElement('first_name'), 'hasElement() does not finalize');
        $this->assertSame(0, $dispatched);
        $this->assertNotNull($form->getElement('first_name'), 'getElement() does not finalize');
        $this->assertSame(0, $dispatched);

        // InventoryItemPresenter does exactly this: look at an element, then add more
        $form->addInput('city', 'City', '');
        $this->assertSame(0, $dispatched, 'so a presenter can keep building afterwards');
        $this->assertStringContainsString('city', $this->elementIds($form), 'and the later element is part of the form');

        $form->getElements();
        $this->assertSame(1, $dispatched, 'which is only now finished');
    }

    public function testTheFlagSurvivesTheTripThroughTheSession(): void
    {
        $form = $this->aForm();
        $form->finalize();
        $restored = unserialize(serialize($form));
        $dispatched = 0;
        Hooks::addAction('form_built', function () use (&$dispatched) {
            $dispatched++;
        });
        $restored->getElements();
        $this->assertSame(0, $dispatched, 'a form that comes back from the session is not built a second time');
    }

    public function testAFormStoredUnfinishedStillGetsItsChance(): void
    {
        $form = $this->aForm();
        $dispatched = 0;
        Hooks::addAction('form_built', function () use (&$dispatched) {
            $dispatched++;
        });
        $restored = unserialize(serialize($form));
        $restored->getElements();
        $this->assertSame(1, $dispatched);
    }

    public function testWithoutAFilterTheEntriesAreTheOnesTheFormWasGiven(): void
    {
        $form = $this->aFormWithChoices();
        $this->assertSame(',1,2,3', $this->offeredIds($form, 'role'));
    }

    public function testAFilterCanRemoveAnEntryOfASelect(): void
    {
        $form = $this->aFormWithChoices();
        Hooks::addFilter('form_select_options', function (array $values, string $elementId, string $type) {
            if ($elementId !== 'role') {
                return $values;
            }

            return array_values(array_filter($values, function (array $entry) {
                return (string)$entry['id'] !== '2';
            }));
        });

        $this->assertSame(',1,3', $this->offeredIds($form, 'role'));
        $this->assertSame('f,m', $this->offeredIds($form, 'gender'), 'and leaves the other elements alone');
    }

    public function testTheRemovalIsEnforcedOnPost(): void
    {
        $form = $this->aFormWithChoices();
        Hooks::addFilter('form_select_options', function (array $values, string $elementId) {
            if ($elementId !== 'role') {
                return $values;
            }

            return array_values(array_filter($values, function (array $entry) {
                return (string)$entry['id'] !== '2';
            }));
        });

        $accepted = $form->validate(array('adm_csrf_token' => $form->getCsrfToken(), 'role' => '1', 'gender' => 'f', 'note' => ''));
        $this->assertEquals(1, $accepted['role'] ?? null, 'an entry that is still offered is accepted');

        $rejected = false;
        try {
            $form->validate(array('adm_csrf_token' => $form->getCsrfToken(), 'role' => '2', 'gender' => 'f', 'note' => ''));
        } catch (Throwable $exception) {
            $rejected = true;
        }
        $this->assertTrue($rejected, 'the entry the filter removed is refused, not only hidden');
    }

    public function testARadioGroupKeepsItsOwnShape(): void
    {
        $form = $this->aFormWithChoices();
        Hooks::addFilter('form_select_options', function (array $values, string $elementId) {
            if ($elementId !== 'gender') {
                return $values;
            }
            unset($values['m']);

            return $values;
        });
        $this->assertSame('f', $this->offeredIds($form, 'gender'));

        $rejected = false;
        try {
            $form->validate(array('adm_csrf_token' => $form->getCsrfToken(), 'role' => '1', 'gender' => 'm', 'note' => ''));
        } catch (Throwable $exception) {
            $rejected = true;
        }
        $this->assertTrue($rejected, 'and its removed entry is refused too');
    }

    public function testOnlyTheEnforcedTypesAreAsked(): void
    {
        $form = $this->aFormWithChoices();
        $asked = array();
        Hooks::addFilter('form_select_options', function (array $values, string $elementId, string $type) use (&$asked) {
            $asked[] = $elementId . ':' . $type;
            return $values;
        });
        $form->finalize();

        $this->assertSame(array('role:select', 'gender:radio'), $asked, 'the filter is asked for the selects and radios only, never for a text field');
    }

    public function testItRunsAfterFormBuilt(): void
    {
        $form = $this->aFormWithChoices();
        $order = array();
        Hooks::addAction('form_built', function (FormPresenter $built) use (&$order) {
            $order[] = 'built';
            $built->addSelectBox('room', 'Room', array(7 => 'Hall'));
        });
        Hooks::addFilter('form_select_options', function (array $values, string $elementId) use (&$order) {
            $order[] = 'options:' . $elementId;
            return $values;
        });
        $form->finalize();

        $this->assertSame(array('built', 'options:role', 'options:gender', 'options:room'), $order, 'a select that form_built added is filtered as well');
    }

    public function testTheAnswerHasToBeAnArray(): void
    {
        $form = $this->aFormWithChoices();
        Hooks::addFilter('form_select_options', function () {
            return 'not an array';
        });

        $refused = false;
        try {
            $form->finalize();
        } catch (Throwable $exception) {
            $refused = str_contains($exception->getMessage(), 'returned string instead of array');
        }
        $this->assertTrue($refused);
    }
}
