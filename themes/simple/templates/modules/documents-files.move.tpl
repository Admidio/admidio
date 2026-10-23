<div id="adm_move_copy" data-start-copy="{if $startCopy}1{else}0{/if}"
    data-headline-move="{$headlines.move|escape}" data-headline-copy="{$headlines.copy|escape}"
    data-description-move="{$descriptions.move|escape}" data-description-copy="{$descriptions.copy|escape}"
    data-label-move="{$l10n->get('SYS_MOVE_TO')|escape}" data-label-copy="{$l10n->get('SYS_COPY_TO')|escape}"
    data-action-url-move="{$formUrls.move}" data-action-url-copy="{$formUrls.copy}" data-copy-check-url="{$copyCheckUrl}">

<p id="adm_move_copy_description" class="lead" aria-live="polite">{$description}</p>

{if $canSwitch}
    {* the switch is outside of the form, so its value is not sent with the form *}
    <div class="btn-group mb-3 admidio-move-copy-switch" role="group" aria-label="{$l10n->get('SYS_MOVE')} / {$l10n->get('SYS_COPY')}">
        <input type="radio" class="btn-check" name="adm_move_copy" id="adm_move_copy_move" value="move" autocomplete="off"{if !$startCopy} checked{/if}>
        <label class="btn btn-outline-primary" for="adm_move_copy_move"><i class="bi bi-folder-symlink"></i> {$l10n->get('SYS_MOVE')}</label>
        <input type="radio" class="btn-check" name="adm_move_copy" id="adm_move_copy_copy" value="copy" autocomplete="off"{if $startCopy} checked{/if}>
        <label class="btn btn-outline-primary" for="adm_move_copy_copy"><i class="bi bi-copy"></i> {$l10n->get('SYS_COPY')}</label>
    </div>
{/if}

<form {foreach $attributes as $attribute}
        {$attribute@key}="{$attribute}"
    {/foreach}>
    <div class="admidio-form-required-notice"><span>{$l10n->get('SYS_REQUIRED_INPUT')}</span></div>

    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_csrf_token']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_destination_folder_uuid']}
    {include 'sys-template-parts/form.input.tpl' data=$elements['adm_accept_new_name']}

    <div id="adm_destination_folder_group" class="admidio-form-group admidio-form-group-required row mb-3">
        <label id="adm_destination_folder_label" for="adm_destination_folder_tree" class="col-sm-3 col-form-label">{if $startCopy}{$l10n->get('SYS_COPY_TO')}{else}{$l10n->get('SYS_MOVE_TO')}{/if}</label>
        <div class="col-sm-9">
            <div class="admidio-folder-picker">
                <ul id="adm_destination_folder_tree" class="admidio-folder-tree" role="tree" aria-label="{if $startCopy}{$l10n->get('SYS_COPY_TO')}{else}{$l10n->get('SYS_MOVE_TO')}{/if}"
                    aria-required="true" data-url="{$treeUrl}" data-root-uuid="{$treeRootUuid}" data-root-url="{$treeRootUrl}"
                    data-root-can-upload="{if $treeRootCanUpload}1{else}0{/if}" data-current-uuid="{$treeCurrentUuid}"
                    data-excluded-uuid="{$treeExcludedUuid}" data-path="{$treePath|escape}"
                    data-root-label="{$l10n->get('SYS_DOCUMENTS_FILES')}" data-current-label="{$l10n->get('SYS_MOVE_CURRENT_FOLDER')}"
                    data-last-label="{$l10n->get('SYS_MOVE_LAST_FOLDER')}"></ul>
            </div>
            <div id="adm_destination_folder_summary" class="form-text admidio-folder-picker-summary" aria-live="polite"
                data-text-empty="{$l10n->get('SYS_MOVE_SELECT_FOLDER')}">{$l10n->get('SYS_MOVE_SELECT_FOLDER')}</div>
        </div>
    </div>

    {* A file with the name exists in the destination folder: the user may accept that the copy gets a new name *}
    <div id="adm_copy_conflict" class="row mb-3" hidden>
        <div class="col-sm-9 offset-sm-3">
            <div class="alert alert-warning mb-0" role="alert">
                <p id="adm_copy_conflict_text" class="mb-2"></p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="adm_copy_accept_name">
                    <label class="form-check-label" for="adm_copy_accept_name" id="adm_copy_accept_label"></label>
                </div>
            </div>
        </div>
    </div>

    {include 'sys-template-parts/form.button.tpl' data=$elements['adm_btn_move']}
    <div class="form-alert" style="display: none;">&nbsp;</div>
</form>

</div>

{literal}
<script>
// The destination folder is selected in the tree. The save button is only enabled while a folder is selected
// where the item can be moved or copied to. A file can be moved or copied, a switch changes the action. If a copy
// would get the name of an existing file, the user must accept that the copy gets a new name.
document.addEventListener("DOMContentLoaded", function () {
    const data = document.getElementById("adm_move_copy");
    const treeElement = document.getElementById("adm_destination_folder_tree");
    const field = document.getElementById("adm_destination_folder_uuid");
    const acceptField = document.getElementById("adm_accept_new_name");
    const summary = document.getElementById("adm_destination_folder_summary");
    const button = document.getElementById("adm_btn_move");
    const conflict = document.getElementById("adm_copy_conflict");
    const conflictText = document.getElementById("adm_copy_conflict_text");
    const acceptBox = document.getElementById("adm_copy_accept_name");
    const acceptLabel = document.getElementById("adm_copy_accept_label");
    if (!data || !treeElement || !field || !summary || !button) return;

    const form = treeElement.closest("form");
    let copying = data.dataset.startCopy === "1";
    let nameConflict = false;
    let checking = false;
    let checkId = 0;

    button.disabled = true;

    const updateButton = function () {
        button.disabled = field.value === "" || checking || (copying && nameConflict && !acceptBox.checked);
    };

    const clearConflict = function () {
        nameConflict = false;
        conflict.hidden = true;
        acceptBox.checked = false;
        acceptField.value = "0";
    };

    // ask the server if the copy would get the name of an existing file in the destination folder
    const checkName = function () {
        clearConflict();
        checking = false;
        if (!copying || field.value === "") {
            updateButton();
            return;
        }

        checking = true;
        updateButton();
        const id = ++checkId;
        const url = new URL(data.dataset.copyCheckUrl, window.location.href);
        url.searchParams.set("destination_uuid", field.value);
        fetch(url, {credentials: "same-origin"})
            .then(function (response) {
                return response.json();
            })
            .then(function (result) {
                if (id !== checkId) return;
                if (result.status === "success" && result.nameExists) {
                    nameConflict = true;
                    conflictText.textContent = result.messageNameExists;
                    acceptLabel.textContent = result.messageNewName;
                    conflict.hidden = false;
                }
            })
            .catch(function () {
                // the server checks the name again when the file is copied
            })
            .finally(function () {
                if (id !== checkId) return;
                checking = false;
                updateButton();
            });
    };

    const folderTree = new AdmFolderTree(treeElement, {
        url: treeElement.dataset.url,
        rootUuid: treeElement.dataset.rootUuid,
        rootLabel: treeElement.dataset.rootLabel,
        rootUrl: treeElement.dataset.rootUrl,
        rootCanUpload: treeElement.dataset.rootCanUpload === "1",
        currentUuid: treeElement.dataset.currentUuid,
        currentLabel: treeElement.dataset.currentLabel,
        currentSelectable: copying,
        excludedUuid: treeElement.dataset.excludedUuid,
        path: JSON.parse(treeElement.dataset.path),
        select: true,
        onSelect: function (folder) {
            if (folder === null) {
                field.value = "";
                summary.textContent = summary.dataset.textEmpty;
            } else {
                field.value = folder.uuid;
                summary.textContent = "";
                const icon = document.createElement("i");
                icon.className = "bi bi-folder-fill me-1";
                summary.append(icon, folder.path.join(" / "));
            }
            checkName();
        }
    });

    // When many files are moved, the tree is opened at the destination that was used last, so the user does not
    // have to work through the tree again. The folder is only marked, the user selects it.
    const lastDestinationKey = "admidio.documents_files.move_destination";
    folderTree.build().then(function () {
        let lastDestination = "";
        try {
            lastDestination = window.localStorage.getItem(lastDestinationKey) || "";
        } catch (e) {
            // without the storage of the browser the tree opens at the current folder
        }
        if (lastDestination === "") return;

        folderTree.openFolder(lastDestination, treeElement.dataset.lastLabel).then(function (shown) {
            if (!shown) {
                // the folder is deleted or the user can't see it
                try {
                    window.localStorage.removeItem(lastDestinationKey);
                } catch (e) {
                    // nothing to do
                }
            }
        });
    });

    button.addEventListener("click", function () {
        if (field.value === "") return;
        try {
            window.localStorage.setItem(lastDestinationKey, field.value);
        } catch (e) {
            // the destination is only not remembered
        }
    });

    acceptBox.addEventListener("change", function () {
        acceptField.value = acceptBox.checked ? "1" : "0";
        updateButton();
    });

    // switch between moving and copying a file
    const setAction = function (copy) {
        copying = copy;
        const action = copy ? "Copy" : "Move";
        form.setAttribute("action", data.dataset["actionUrl" + action]);
        // the description is a translated text of the server with the name of the file in bold
        document.getElementById("adm_move_copy_description").innerHTML = data.dataset["description" + action];
        const headline = document.querySelector(".admidio-module-headline");
        if (headline) {
            headline.textContent = data.dataset["headline" + action];
        }
        // the label of the tree names the action
        const label = data.dataset["label" + action];
        document.getElementById("adm_destination_folder_label").textContent = label;
        treeElement.setAttribute("aria-label", label);
        folderTree.setCurrentSelectable(copy);
        checkName();
    };

    document.querySelectorAll("input[name=\"adm_move_copy\"]").forEach(function (radio) {
        radio.addEventListener("change", function () {
            if (radio.checked) {
                setAction(radio.value === "copy");
            }
        });
    });
});
</script>
{/literal}
