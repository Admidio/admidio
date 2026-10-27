{if strlen($infoAlert) > 0}
    <div class="alert alert-info" role="alert"><i class="bi bi-info-circle-fill"></i>{$infoAlert}</div>
{/if}

<div class="admidio-documents-layout">
    <aside id="adm_documents_files_tree_pane" class="admidio-folder-sidebar collapsed">
        <div class="admidio-folder-sidebar-header">
            <span class="admidio-folder-sidebar-title"><i class="bi bi-diagram-3-fill"></i> {$l10n->get('SYS_FOLDER_TREE')}</span>
            <button type="button" id="adm_documents_files_tree_collapse" class="admidio-folder-sidebar-collapse"
                    aria-expanded="false" aria-controls="adm_documents_files_tree_nav" title="{$l10n->get('SYS_FOLDER_TREE')}"
                    aria-label="{$l10n->get('SYS_FOLDER_TREE')}"><i class="bi bi-chevron-bar-left"></i><span class="admidio-folder-sidebar-collapse-label">{$l10n->get('SYS_FOLDER_TREE')}</span></button>
        </div>
        <nav id="adm_documents_files_tree_nav" class="admidio-folder-tree-pane" aria-label="{$l10n->get('SYS_FOLDER_TREE')}">
            <ul id="adm_documents_files_tree" class="admidio-tree" role="tree" aria-label="{$l10n->get('SYS_FOLDER_TREE')}" data-url="{$treeUrl}"
                data-root-uuid="{$treeRootUuid}" data-current-uuid="{$treeCurrentUuid}" data-path="{$treePath|escape}"
                data-root-label="{$l10n->get('SYS_DOCUMENTS_FILES')}"></ul>
        </nav>
    </aside>
    <div id="adm_documents_files_list_pane" class="admidio-documents-list">
    <table id="adm_documents_files_table" class="table table-hover" width="100%" style="width: 100%;"
        data-label-share-link="{$l10n->get('SYS_SHARE_LINK')}" data-label-copy-link="{$l10n->get('SYS_COPY_LINK')}"
        data-label-copied="{$l10n->get('SYS_COPIED_CLIPBOARD')}">
        <thead>
            <tr>
                <th><i class="bi bi-folder-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_FOLDER')} / {$l10n->get('SYS_FILE_TYPE')}"></i></th>
                <th>{$l10n->get('SYS_NAME')}</th>
                <th style="word-break: break-word;">{$l10n->get('SYS_DATE_MODIFIED')}</th>
                <th class="text-end">{$l10n->get('SYS_SIZE')}</th>
                <th class="text-end">{$l10n->get('SYS_COUNTER')}</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            {foreach $list as $row}
                <tr id="row_{$row.uuid}">
                    <td><i class="{$row.icon}" data-bs-toggle="tooltip" title="{$row.title}"></i></td>
                    <td style="word-break: break-word;"><a href="{$row.url}"{if !$row.folder} data-adm-file-preview{/if}>{$row.name}</a>
                        {if strlen($row.description) > 0}
                            <i class="bi bi-info-circle-fill admidio-info-icon" data-bs-toggle="popover"
                                data-bs-html="true" data-bs-trigger="hover click" data-bs-placement="auto"
                                title="{$l10n->get('SYS_DESCRIPTION')}" data-bs-content="{$row.description}"></i>
                        {/if}
                    </td>
                    <td>{$row.timestamp}</td>
                    <td class="text-end">{$row.size}</td>
                    <td class="text-end">{$row.counter}</td>
                    <td class="text-end">
                        {include 'sys-template-parts/list.functions.tpl' data=$row}

                        {if $row.existsInFileSystem == false}
                            <i class="bi bi-exclamation-triangle-fill" style="color:red;" data-bs-toggle="popover" data-bs-trigger="hover click" data-bs-placement="left"
                               title="{$l10n->get('SYS_WARNING')}" data-bs-content="{if $row.folder}{$l10n->get('SYS_FOLDER_NOT_EXISTS')}{else}{$l10n->get('SYS_FILE_NOT_EXIST_DELETE_FROM_DB')}{/if}"></i>
                        {/if}
                    </td>
                </tr>
            {/foreach}
        </tbody>
    </table>
    </div>
</div>

{if count($unregisteredList) > 0}
    <h2>{$l10n->get('SYS_UNMANAGED_FILES')}</h2>
    <p class="lead">{$l10n->get('SYS_ADDITIONAL_FILES')}</p>
    <div class="table-responsive">
        <table id="documents-files-unregistered-table" class="table table-hover" width="100%" style="width: 100%;">
            <thead>
            <tr>
                <th><i class="bi bi-folder-fill" data-bs-toggle="tooltip" title="{$l10n->get('SYS_FOLDER')} / {$l10n->get('SYS_FILE_TYPE')}"></i></th>
                <th>{$l10n->get('SYS_NAME')}</th>
                <th class="text-end">{$l10n->get('SYS_SIZE')}</th>
                <th>&nbsp;</th>
            </tr>
            </thead>
            <tbody>
            {foreach $unregisteredList as $row}
                <tr>
                    <td><i class="{$row.icon}" data-bs-toggle="tooltip" title="{$row.title}"></i></td>
                    <td>{$row.name}</td>
                    <td class="text-end">{$row.size}</td>
                    <td class="text-end">
                        <a class="admidio-icon-link admidio-send-csrf-token" data-url="{$row.url}" data-csrf-token="{$row.csrfToken}">
                            <i class="bi bi-plus-circle" data-bs-toggle="tooltip" title="{$l10n->get('SYS_ADD_TO_DATABASE')}"></i>
                        </a>
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
{/if}

{literal}
<script>
// Share the link of a file or folder with the share dialog of the system. If the browser can't share
// links, the action copies the link to the clipboard and shows the icon and text of copying.
document.addEventListener("DOMContentLoaded", function () {
    const table = document.getElementById("adm_documents_files_table");
    // without the functions of common_functions.js, e.g. an older cached version, the action stays a normal link
    if (!table || typeof shareOrCopyLink !== "function" || typeof canShareLink !== "function") return;

    const shareLinkLabel = table.dataset.labelShareLink;
    const copyLinkLabel = table.dataset.labelCopyLink;
    const copiedLabel = table.dataset.labelCopied;

    // a tooltip is only shown if the element had a title, so the tooltip is created again with the new title
    const setTooltip = function (icon, title, options) {
        const tooltip = bootstrap.Tooltip.getInstance(icon);
        if (tooltip) {
            tooltip.dispose();
        }
        icon.setAttribute("title", title);
        return new bootstrap.Tooltip(icon, options || {});
    };

    if (canShareLink()) {
        table.querySelectorAll("a.admidio-share-link").forEach(function (link) {
            const icon = link.querySelector("i");
            icon.className = "bi bi-share";
            if (link.classList.contains("dropdown-item")) {
                icon.setAttribute("title", shareLinkLabel);
                link.lastChild.nodeValue = " " + shareLinkLabel;
            } else {
                setTooltip(icon, shareLinkLabel);
            }
        });
    }

    // show a check icon and the tooltip "copied" at the icon of the link, within the dropdown of small
    // screens at the icon that opens the dropdown, because the dropdown is closed after the click
    const showCopied = function (link) {
        const dropdown = link.closest(".dropdown");
        const icon = dropdown ? dropdown.querySelector("[data-bs-toggle=\"dropdown\"] i") : link.querySelector("i");
        if (!icon) return;

        const iconClass = icon.className;
        const title = icon.getAttribute("title") || icon.getAttribute("data-bs-original-title") || "";
        icon.className = "bi bi-clipboard-check";
        const copiedTooltip = setTooltip(icon, copiedLabel, {trigger: "manual"});
        copiedTooltip.show();

        setTimeout(function () {
            copiedTooltip.dispose();
            icon.className = iconClass;
            icon.setAttribute("title", title);
            if (title !== "") {
                new bootstrap.Tooltip(icon);
            }
        }, 2000);
    };

    table.addEventListener("click", function (e) {
        const link = e.target.closest("a.admidio-share-link");
        if (!link) return;
        e.preventDefault();

        const nameLink = link.closest("tr").querySelector("td:nth-child(2) a");
        const name = nameLink ? nameLink.textContent.trim() : document.title;
        shareOrCopyLink(link.href, name, copyLinkLabel).then(function (result) {
            if (result === "copied") {
                showCopied(link);
            }
        });
    });
});
</script>
{/literal}

{literal}
<script>
// The folder tree is a sidebar that is collapsed at the start, so only a small button is shown above the list. The
// button opens and collapses the sidebar and the browser remembers the choice. The subfolders are loaded when a folder is expanded, only the folders from the root to the current
// folder are expanded at the start.
document.addEventListener("DOMContentLoaded", function () {
    const pane = document.getElementById("adm_documents_files_tree_pane");
    const tree = document.getElementById("adm_documents_files_tree");
    if (!pane || !tree) return;

    const storageKey = "admidio.documents_files.folder_tree";
    const currentUuid = tree.dataset.currentUuid;

    const collapseButton = document.getElementById("adm_documents_files_tree_collapse");
    if (!collapseButton) return;

    // "1" = sidebar open, "2" = sidebar collapsed (default)
    const readState = function () {
        try {
            return window.localStorage.getItem(storageKey) === "1" ? "1" : "2";
        } catch (e) {
            return "2";
        }
    };

    const saveState = function (state) {
        try {
            window.localStorage.setItem(storageKey, state);
        } catch (e) {
            // the choice is only not remembered
        }
    };

    const rootUrl = new URL(window.location.href);
    rootUrl.search = "";
    rootUrl.hash = "";
    const folderTree = new AdmTreeView(tree, {
        url: tree.dataset.url,
        idParam: "folder_uuid",
        root: {id: tree.dataset.rootUuid, name: tree.dataset.rootLabel, url: rootUrl.href},
        currentId: currentUuid,
        path: JSON.parse(tree.dataset.path)
    });

    // the width of the list changes, so DataTables must calculate the visible columns again
    const adjustTable = function () {
        if (!window.jQuery || !jQuery.fn.dataTable || !jQuery.fn.dataTable.isDataTable("#adm_documents_files_table")) return;

        const table = jQuery("#adm_documents_files_table").DataTable();
        table.columns.adjust();
        if (table.responsive) {
            table.responsive.recalc();
        }
    };

    // The collapsed button has the height and the position of the entries menu of the table, or of the search
    // field if there is no entries menu, so they are on one line, whatever size the theme gives them. The entries
    // menu is moved right beside the button.
    const alignButton = function () {
        const header = collapseButton.parentElement;
        const lengthMenu = document.querySelector("#adm_documents_files_list_pane .dt-length");
        header.style.top = "";
        collapseButton.style.height = "";
        if (lengthMenu) {
            lengthMenu.style.marginLeft = "";
        }
        // on small screens the collapsed sidebar is a bar above the list, the button is only aligned beside the table
        if (!pane.classList.contains("collapsed") || window.getComputedStyle(header).position !== "absolute") return;

        const reference = document.querySelector("#adm_documents_files_list_pane .dt-length select, #adm_documents_files_list_pane .dt-search input");
        if (!reference) return;

        const referenceBox = reference.getBoundingClientRect();
        const layoutBox = pane.parentElement.getBoundingClientRect();
        header.style.top = (referenceBox.top - layoutBox.top) + "px";
        collapseButton.style.height = referenceBox.height + "px";

        if (lengthMenu && reference.matches("select")) {
            // distance between the button and the entries menu, independent of the theme
            // the menu may also move left if the theme has put it further right, but not out of the list
            lengthMenu.style.marginLeft = "0px";
            const listLeft = document.getElementById("adm_documents_files_list_pane").getBoundingClientRect().left;
            const shift = collapseButton.getBoundingClientRect().right + 6 - reference.getBoundingClientRect().left;
            const minShift = listLeft - lengthMenu.getBoundingClientRect().left;
            lengthMenu.style.marginLeft = Math.max(minShift, shift) + "px";
        }
    };

    // On large screens the sidebar reaches down to the bottom of the window, also if the list has only a few
    // entries, and leaves the space for the footer of the page. A longer list makes it higher, as before. On small
    // screens the sidebar is above the list.
    const fitSidebar = function () {
        pane.style.minHeight = "";
        if (pane.classList.contains("collapsed") || window.getComputedStyle(pane.parentElement).flexDirection !== "row") return;

        // the space below the sidebar that is needed for the footer and for other content below the list
        const footer = document.getElementById("adm_imprint");
        let spaceBelow = 0;
        if (footer) {
            spaceBelow = footer.getBoundingClientRect().bottom - pane.parentElement.getBoundingClientRect().bottom;
        }

        // The sidebar has a bottom margin of 16px on large screens, like the space to its left, and the page keeps
        // 16px free below the footer.
        const top = pane.getBoundingClientRect().top + window.scrollY;
        const available = window.innerHeight - top - spaceBelow - 32;
        if (available > pane.offsetHeight) {
            pane.style.minHeight = available + "px";
        }
    };

    const setState = function (state) {
        const open = state === "1";
        pane.classList.toggle("collapsed", !open);
        collapseButton.setAttribute("aria-expanded", open ? "true" : "false");
        if (open) {
            folderTree.build();
        }
        saveState(state);
        adjustTable();
        alignButton();
        fitSidebar();
        window.setTimeout(function () {
            adjustTable();
            alignButton();
            fitSidebar();
        }, 250);
    };

    collapseButton.addEventListener("click", function () {
        setState(pane.classList.contains("collapsed") ? "1" : "2");
    });

    // the table controls are created by DataTables, so the button is aligned again when they are there
    window.addEventListener("resize", alignButton);
    window.addEventListener("load", alignButton);
    window.addEventListener("resize", fitSidebar);
    window.addEventListener("load", fitSidebar);
    if (window.jQuery) {
        jQuery("#adm_documents_files_table").on("init.dt draw.dt", alignButton);
    }

    setState(readState());
});
</script>
{/literal}
