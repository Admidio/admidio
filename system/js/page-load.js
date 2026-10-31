/**
 ***********************************************************************************************
 * JavaScript code that will be loaded on every page of Admidio. It contains functions to show
 * and hide elements, to call urls and handle the response, to show message boxes and to move
 * table rows.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

function initializeEvents() {
    // function to handle modal window and load data from url
    $(document).on('click', '.openPopup', function (){
        $('#adm_modal .modal-dialog').attr('class', 'modal-dialog ' + $(this).attr('data-class'));
        $('#adm_modal .modal-content').load($(this).attr('data-href'),function(){
            const myModal = new bootstrap.Modal($('#adm_modal'));
            myModal.show();
        });
    });
    // function to handle modal messagebox window
    $(document).on('click', '.admidio-messagebox', function (){
        messageBox($(this).data('message'), $(this).data('title'), $(this).data('type'), $(this).data('buttons'), $(this).data('href'));
    });

    // remove data from modal if modal is closed
    $("body").on("hidden.bs.modal", ".modal", function() {
        $(this).removeData("bs.modal");
    });

    // function to call a url with a CSRF token and handle the response
    // delegated so that buttons added later (e.g. inside a modal) work as well
    $(document).on("click", ".admidio-send-csrf-token", function() {
        redirectPost($(this).data("url"), {adm_csrf_token: $(this).data("csrf-token")});
    });
}

// Native mobile select validation also applies to forms loaded later, e.g. in a dialog.
// Register once here rather than in initializeEvents(), which may run several times.
(() => {
    // Preferences can include this script more than once.
    if (window.admidioMobileSelectValidationInitialized) {
        return;
    }
    window.admidioMobileSelectValidationInitialized = true;

    let reportedField = null;

    document.addEventListener('invalid', async function(event) {
        const field = event.target;
        if (field === reportedField || field.tagName !== 'SELECT' || field.multiple
            || field.classList.contains('select2-hidden-accessible') || !field.form
            || window.matchMedia('(min-width: 768px)').matches) {
            return;
        }

        event.preventDefault();
        const firstInvalid = Array.from(field.form.elements)
            .find(control => control.willValidate && !control.validity.valid);
        if (firstInvalid !== field) {
            return;
        }

        // Open Bootstrap containers from the outside in before focusing their field.
        const containers = [];
        for (let parent = field.parentElement; parent && parent !== field.form; parent = parent.parentElement) {
            if (parent.matches('.tab-pane, .collapse, .collapsing')) {
                containers.unshift(parent);
            }
        }
        for (const container of containers) {
            if (container.classList.contains('tab-pane') && getComputedStyle(container).display === 'none') {
                const trigger = document.querySelector('[data-bs-toggle="tab"][data-bs-target="#' + CSS.escape(container.id) + '"]');
                if (trigger) {
                    await new Promise(resolve => {
                        trigger.addEventListener('shown.bs.tab', resolve, {once: true});
                        bootstrap.Tab.getOrCreateInstance(trigger).show();
                    });
                }
            } else if (container.classList.contains('collapsing')
                || (container.classList.contains('collapse') && getComputedStyle(container).display === 'none')) {
                await new Promise(resolve => {
                    const collapse = bootstrap.Collapse.getOrCreateInstance(container, {toggle: false});
                    const show = () => collapse.show();
                    container.addEventListener('shown.bs.collapse', function() {
                        container.removeEventListener('hidden.bs.collapse', show);
                        resolve();
                    }, {once: true});
                    // If the user submitted while it was closing, open it after that transition.
                    container.addEventListener('hidden.bs.collapse', show, {once: true});
                    show();
                });
            }
        }

        requestAnimationFrame(() => {
            field.focus({preventScroll: true});
            field.scrollIntoView({block: 'center', behavior: 'instant'});
            // Let scrolling settle before requesting the standard browser popup.
            requestAnimationFrame(() => {
                reportedField = field;
                field.reportValidity();
                reportedField = null;
            });
        });
    }, true);
})();

$(function() {
    initializeEvents();
});
