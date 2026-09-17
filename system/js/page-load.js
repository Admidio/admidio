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
    $(".admidio-send-csrf-token").click(function() {
        redirectPost($(this).data("url"), {adm_csrf_token: $(this).data("csrf-token")});
    });

    // function to handle dynamic weekday prefix on date fields
    $(document).on('change input', '.admidio-date-with-weekday', function() {
        const val = $(this).val();
        const weekdaySpan = $('#' + $(this).attr('id') + '_weekday');
        if (!weekdaySpan.length) {
            return;
        }
        if (!val) {
            weekdaySpan.text('');
            return;
        }
        const parts = val.split('-');
        if (parts.length === 3) {
            const year = parseInt(parts[0], 10);
            const month = parseInt(parts[1], 10) - 1;
            const day = parseInt(parts[2], 10);
            const date = new Date(year, month, day);
            const format = weekdaySpan.data('weekday-format') || 'short';
            const lang = document.documentElement.lang || 'default';
            let weekday = date.toLocaleDateString(lang, { weekday: format });
            if (format === 'short') {
                weekday = weekday.replace(/\.$/, '');
            }
            weekdaySpan.text(weekday);
        }
    });
}

$(function() {
    initializeEvents();
});
