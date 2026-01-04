<div class="table-responsive">
    <table id="adm_assign_role_membership" class="table table-condensed table-hover" style="max-width: 100%;">
        <thead>
            <tr>
                {foreach $headers as $key => $header}
                    <th style="text-align:{$columnAlign[$key]};">{$header}</th>
                {/foreach}
            </tr>
        </thead>
        <tbody>
            {* serverside processing data *}
        </tbody>
    </table>
</div>