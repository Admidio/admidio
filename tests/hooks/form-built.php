<?php
/**
 * form_built: the point at which a form is finished, and the element API that makes the hook useful.
 *
 * The real `FormPresenter` is executed here - it needs nothing but the autoloader - so what is checked
 * is the actual form, the actual dispatch and the actual validate().
 */
require __DIR__ . '/bootstrap.php';

use Admidio\Hooks\Hooks;
use Admidio\UI\Presenter\FormPresenter;

function aForm(string $id = 'adm_probe_form'): FormPresenter
{
    Hooks::reset();
    $form = new FormPresenter($id, 'form.tpl', 'index.php');
    $form->addInput('first_name', 'First name', '');
    $form->addInput('last_name', 'Last name', '');

    return $form;
}

function elementIds(FormPresenter $form): string
{
    return implode(',', array_keys($form->getElements()));
}

// ------------------------------------------------------------------------- the hook fires exactly once

$form = aForm();
$dispatched = array();
Hooks::addAction('form_built', function (FormPresenter $built) use (&$dispatched) {
    $dispatched[] = $built->getId();
});

check('nothing is dispatched while the form is being built', $dispatched === array());

$form->finalize();
check('finalize() dispatches form_built with the form', $dispatched === array('adm_probe_form'), implode(',', $dispatched));

$form->finalize();
$form->getElements();
$form->getAttributes();
check('and never again, however often the form is read', count($dispatched) === 1, (string)count($dispatched));

// ------------------------------------------------------------- every way of reading a finished form

foreach (array('getElements', 'getAttributes') as $reader) {
    $form = aForm();
    $dispatched = array();
    Hooks::addAction('form_built', function (FormPresenter $built) use (&$dispatched) {
        $dispatched[] = $built->getId();
    });
    $form->$reader();
    check($reader . '() finalizes the form', count($dispatched) === 1, (string)count($dispatched));
}

// ---------------------------------------------------------- a callback can change what is rendered

$form = aForm();
Hooks::addAction('form_built', function (FormPresenter $built) {
    $built->removeElement('last_name');
    $built->insertElement('nickname', array('id' => 'nickname', 'type' => 'text', 'label' => 'Nickname'), 'first_name');
    $built->replaceElement('first_name', array('id' => 'first_name', 'type' => 'text', 'label' => 'Given name'));
});

check(
    'a callback can remove, insert and replace elements',
    elementIds($form) === 'adm_csrf_token,nickname,first_name',
    elementIds($form)
);
check(
    'and the replacement is what the form holds',
    $form->getElement('first_name')['label'] === 'Given name',
    (string)$form->getElement('first_name')['label']
);

// ------------------------------------------------------------ and the change reaches the validation

$form = aForm();
Hooks::addAction('form_built', function (FormPresenter $built) {
    $built->removeElement('last_name');
});
$form->finalize();

$rejected = false;
try {
    $form->validate(array('adm_csrf_token' => $form->getCsrfToken(), 'first_name' => 'John', 'last_name' => 'Doe'));
} catch (\Throwable $exception) {
    $rejected = str_contains($exception->getMessage(), 'Invalid payload');
}
check('a removed element is not accepted by validate() any more', $rejected);

$accepted = $form->validate(array('adm_csrf_token' => $form->getCsrfToken(), 'first_name' => 'John'));
check('what is left still validates', array_key_exists('first_name', $accepted), implode(',', array_keys($accepted)));

// ----------------------------------------------- reading one element does not finish the form early

$form = aForm();
$dispatched = 0;
Hooks::addAction('form_built', function () use (&$dispatched) {
    $dispatched++;
});

check('hasElement() does not finalize', $form->hasElement('first_name') && $dispatched === 0);
check('getElement() does not finalize', $form->getElement('first_name') !== null && $dispatched === 0);

// InventoryItemPresenter does exactly this: look at an element, then add more
$form->addInput('city', 'City', '');
check('so a presenter can keep building afterwards', $dispatched === 0);
check('and the later element is part of the form', str_contains(elementIds($form), 'city'), elementIds($form));
check('which is only now finished', $dispatched === 1, (string)$dispatched);

// --------------------------------------------------- the flag survives the trip through the session

$form = aForm();
$form->finalize();
$restored = unserialize(serialize($form));
$dispatched = 0;
Hooks::addAction('form_built', function () use (&$dispatched) {
    $dispatched++;
});
$restored->getElements();
check('a form that comes back from the session is not built a second time', $dispatched === 0, (string)$dispatched);

$form = aForm();                       // aForm() resets the registry, so listen again afterwards
$dispatched = 0;
Hooks::addAction('form_built', function () use (&$dispatched) {
    $dispatched++;
});
$restored = unserialize(serialize($form));
$restored->getElements();
check('but one that was stored unfinished still gets its chance', $dispatched === 1, (string)$dispatched);

// ------------------------------------------------------------------ form_select_options

/** A form with the three element types whose entries validate() enforces. */
function aFormWithChoices(): FormPresenter
{
    Hooks::reset();
    $form = new FormPresenter('adm_choices_form', 'form.tpl', 'index.php');
    $form->addSelectBox('role', 'Role', array(1 => 'Choir', 2 => 'Board', 3 => 'Youth'));
    $form->addRadioButton('gender', 'Gender', array('f' => 'female', 'm' => 'male'));
    $form->addInput('note', 'Note', '');

    return $form;
}

function offeredIds(FormPresenter $form, string $elementId): string
{
    // getElements() finishes the form, getElement() deliberately does not
    $element = $form->getElements()[$elementId];
    $values = $element['values'];
    // a select carries a list of id/value pairs, a radio an array of value to label
    $ids = ($element['type'] === 'select') ? array_column($values, 'id') : array_keys($values);

    return implode(',', $ids);
}

$form = aFormWithChoices();
check(
    'without a filter the entries are the ones the form was given',
    offeredIds($form, 'role') === ',1,2,3',
    offeredIds($form, 'role')
);

// --------------------------------------------------------------------- a filter can take one away

$form = aFormWithChoices();
Hooks::addFilter('form_select_options', function (array $values, string $elementId, string $type) {
    if ($elementId !== 'role') {
        return $values;
    }

    return array_values(array_filter($values, function (array $entry) {
        return (string)$entry['id'] !== '2';
    }));
});

check('a filter can remove an entry of a select', offeredIds($form, 'role') === ',1,3', offeredIds($form, 'role'));
check('and leaves the other elements alone', offeredIds($form, 'gender') === 'f,m', offeredIds($form, 'gender'));

// ------------------------------------------------------------- and the removal is enforced on POST

$accepted = $form->validate(array('adm_csrf_token' => $form->getCsrfToken(), 'role' => '1', 'gender' => 'f', 'note' => ''));
check('an entry that is still offered is accepted', ($accepted['role'] ?? null) == 1, var_export($accepted['role'] ?? null, true));

$rejected = false;
try {
    $form->validate(array('adm_csrf_token' => $form->getCsrfToken(), 'role' => '2', 'gender' => 'f', 'note' => ''));
} catch (\Throwable $exception) {
    $rejected = true;
}
check('the entry the filter removed is refused, not only hidden', $rejected);

// --------------------------------------------------------------------------------- a radio group

$form = aFormWithChoices();
Hooks::addFilter('form_select_options', function (array $values, string $elementId) {
    if ($elementId !== 'gender') {
        return $values;
    }
    unset($values['m']);

    return $values;
});
check('a radio group keeps its own shape', offeredIds($form, 'gender') === 'f', offeredIds($form, 'gender'));

$rejected = false;
try {
    $form->validate(array('adm_csrf_token' => $form->getCsrfToken(), 'role' => '1', 'gender' => 'm', 'note' => ''));
} catch (\Throwable $exception) {
    $rejected = true;
}
check('and its removed entry is refused too', $rejected);

// ----------------------------------------------------------------- only the enforced types are asked

$form = aFormWithChoices();
$asked = array();
Hooks::addFilter('form_select_options', function (array $values, string $elementId, string $type) use (&$asked) {
    $asked[] = $elementId . ':' . $type;
    return $values;
});
$form->finalize();
check(
    'the filter is asked for the selects and radios only, never for a text field',
    $asked === array('role:select', 'gender:radio'),
    implode(' ', $asked)
);

// ------------------------------------------------------------------ it runs after form_built

$form = aFormWithChoices();
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
check(
    'a select that form_built added is filtered as well',
    $order === array('built', 'options:role', 'options:gender', 'options:room'),
    implode(' ', $order)
);

// -------------------------------------------------------------------- the answer has to be an array

$form = aFormWithChoices();
Hooks::addFilter('form_select_options', function () {
    return 'not an array';
});
$refused = false;
try {
    $form->finalize();
} catch (\Throwable $exception) {
    $refused = str_contains($exception->getMessage(), 'returned string instead of array');
}
check('a filter that does not answer with an array is refused', $refused);

exit(testSummary());
