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

exit(testSummary());
