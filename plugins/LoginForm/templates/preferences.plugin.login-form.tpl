<script>
    // when saving the form, we need to set the values from the table key and rank values to the hidden input fields and disable the table inputs
    $('#adm_preferences_form_login_form').on('submit', function () {
        const form = this;

        // Collect pairs from the table (one row = one pair)
        const pairs = [];
        const keyInputs = form.querySelectorAll('input[name^="login_form_ranks_key_"]');

        keyInputs.forEach(keyElement => {
            const row = keyElement.closest('tr');
            const valElement = row && row.querySelector('input[name^="login_form_ranks_value_"]');
            if (!valElement) {
                return
            }

            const key = keyElement.value.trim();
            const value = valElement.value.trim();
            if (key !== '' && value !== '') {
                // store numeric key; if you prefer strings remove Number()
                pairs.push({ key: Number(key), value });
            }
        });

        // Write into the hidden fields
        const keysHidden = form.querySelector('input[name="login_form_ranks_keys"]');
        const valuesHidden = form.querySelector('input[name="login_form_ranks"]');

        if (keysHidden) {
            keysHidden.value = pairs.map(pair => pair.key).join(',');
        }
        if (valuesHidden) {
            valuesHidden.value = JSON.stringify(pairs.map(pair => pair.value));
        }
        // diable the table inputs so that they are not submitted
        form.querySelectorAll('input[name^="login_form_ranks_key_"], input[name^="login_form_ranks_value_"]').forEach(el => { el.disabled = true; });
    });

    function addLoginFormRanksRow() {
        const table = document.querySelector('#login_form_ranks_table tbody');

        // Erstelle eine neue Tabellenzeile
        const rowCount = table.querySelectorAll('tr[id^="login_form_ranks_row_"]').length
        const newRow = table.insertRow(-1);

        // Key Input
        const keyCell = newRow.insertCell(0);
        const keyInput = document.createElement('input');
        keyInput.type = 'number';
        keyInput.id = 'login_form_ranks_key_' + rowCount;
        keyInput.name = 'login_form_ranks_key_' + rowCount;
        keyInput.className = 'form-control focus-ring';
        keyCell.appendChild(keyInput);

        // Value Input
        const valueCell = newRow.insertCell(1);
        const valueInput = document.createElement('input');
        valueInput.type = 'text';
        valueInput.id = 'login_form_ranks_value_' + rowCount;
        valueInput.name = 'login_form_ranks_value_' + rowCount;
        valueInput.className = 'form-control focus-ring';
        valueCell.appendChild(valueInput);
    }
</script>
<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.select.tpl' data=$elements['login_form_plugin_enabled']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['login_form_show_register_link']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['login_form_show_email_link']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['login_form_show_logout_link']}
    {include 'sys-template-parts/form.checkbox.tpl' data=$elements['login_form_enable_ranks']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['login_form_ranks']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['login_form_ranks_keys']}
    {include 'sys-template-parts/form.custom-content.tpl' data=$elements['login_form_ranks_table']}
    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_button_save_login_form']}

    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>
{$javascript}