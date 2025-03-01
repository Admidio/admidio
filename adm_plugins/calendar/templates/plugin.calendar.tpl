<div id="plgCalendarContent" class="admidio-plugin-content">
    <h3>{$l10n->get('SYS_CALENDAR')}</h3>

    <table id="plgCalendarTable" class="w-100">
        <tr>
            <th style="text-align: center;" class="plgCalendarHeader">
                <a id="previousMonth" href="#" >&laquo;</a>
            </th>
            <th colspan="5" style="text-align: center;" class="plgCalendarHeader">{$monthYearHeadline}</th>
            <th style="text-align: center;" class="plgCalendarHeader">
                <a id="nextMonth" href="#">&raquo;</a>
            </th>
        </tr>
        <tr>
            <td class="plgCalendarWeekday"><strong>{$l10n->get('PLG_CALENDAR_MONDAY_SHORT')}</strong></td>
            <td class="plgCalendarWeekday"><strong>{$l10n->get('PLG_CALENDAR_TUESDAY_SHORT')}</strong></td>
            <td class="plgCalendarWeekday"><strong>{$l10n->get('PLG_CALENDAR_WEDNESDAY_SHORT')}</strong></td>
            <td class="plgCalendarWeekday"><strong>{$l10n->get('PLG_CALENDAR_THURSDAY_SHORT')}</strong></td>
            <td class="plgCalendarWeekday"><strong>{$l10n->get('PLG_CALENDAR_FRIDAY_SHORT')}</strong></td>
            <td class="plgCalendarWeekdaySaturday"><strong>{$l10n->get('PLG_CALENDAR_SATURDAY_SHORT')}</strong></td>
            <td class="plgCalendarWeekdaySunday"><strong>{$l10n->get('PLG_CALENDAR_SUNDAY_SHORT')}</strong></td>
        </tr>
        {$tableContent}
    </table>

    {if {$currentMonthYear} != {$monthYear}}
        <div id="plgCalendarReset">
            <a id="calendarResetLink" href="#">{$l10n->get('PLG_CALENDAR_CURRENT_MONTH')}</a>
        </div>
    {/if}

    <script type="text/javascript"><!--
        $("#calendarResetLink").click(function() {
            $.get({
                url: '{$urlAdmidio}/adm_plugins/{$pluginFolder}/calendar.php',
                cache: false,
                data: 'date_id={$currentMonthYear}',
                success: function(html) {
                    $('#plgCalendarContent').replaceWith(html);
                    $('.admidio-calendar-link').popover();
                }
            });
        });
        $("#previousMonth").click(function() {
            $.get({
                url: '{$urlAdmidio}/adm_plugins/{$pluginFolder}/calendar.php',
                cache: false,
                data: 'date_id={$dateIdLastMonth}',
                success: function (html) {
                    $('#plgCalendarContent').replaceWith(html);
                    $('.admidio-calendar-link').popover();
                }
            });
        });
        $("#nextMonth").click(function() {
            $.get({
                url: '{$urlAdmidio}/adm_plugins/{$pluginFolder}/calendar.php',
                cache: false,
                data: 'date_id={$dateIdNextMonth}',
                success: function (html) {
                    $('#plgCalendarContent').replaceWith(html);
                    $('.admidio-calendar-link').popover();
                }
            });
        });

        $(document).ready(function() {
            $(".admidio-calendar-link").popover();
        });
    --></script>
</div>
