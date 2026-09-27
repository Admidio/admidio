<table id="adm_table_category_reports" class="table table-hover" width="100%" style="width: 100%;">
    <thead>
        <tr>
            <th>{$l10n->get('SYS_CATEGORY_REPORT')}</th>
            <th>{$l10n->get('SYS_DESCRIPTION')}</th>
            <th class="text-end">{$l10n->get('SYS_NUMBER_OF_COLUMNS')}</th>
            <th class="text-center"><i class="bi bi-star-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DEFAULT_REPORT')}"></i></th>
            <th>&nbsp;</th>
        </tr>
    </thead>
    <tbody>
        {if count($reports) eq 0}
            <tr>
                <td colspan="5" class="text-center">{$l10n->get('SYS_NO_ENTRIES')}</td>
            </tr>
        {else}
        {foreach $reports as $report}
            <tr id="adm_category_report_{$report.id}">
                <td style="word-break: break-word;">
                    {if $report.urlEdit !== ''}
                        <a href="{$report.urlEdit}">{$report.name}</a>
                    {else}
                        {$report.name}
                    {/if}
                </td>
                <td style="word-break: break-word;">{$report.description}</td>
                <td class="text-end">{$report.columnCount}</td>
                <td class="text-center">
                    {if $report.default}
                        <i class="bi bi-star-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_DEFAULT_REPORT')}"></i>
                    {/if}
                </td>
                <td class="text-end">
                    {include 'sys-template-parts/list.functions.tpl' data=$report}
                </td>
            </tr>
        {/foreach}
        {/if}
    </tbody>
</table>
