/**
 ***********************************************************************************************
 * Folder tree of the documents and files module. The tree is used as sidebar to navigate through the
 * folders and in the dialog to move a file or folder, where the user selects the destination folder.
 *
 * The tree is a WAI-ARIA tree with one tab stop. The subfolders are loaded when a folder is expanded, only
 * the folders from the root to the current folder are expanded at the start.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @param {HTMLElement} tree    The list element with the role "tree" that will contain the folders.
 * @param {Object}      options
 * @param {string}      options.url          Url of the mode "tree" that returns the visible subfolders as JSON.
 * @param {string}      options.rootUuid     UUID of the root folder.
 * @param {string}      options.rootLabel    Name that is shown for the root folder.
 * @param {string}      options.rootUrl      Url of the root folder.
 * @param {boolean}     options.rootCanUpload Set to true if the user may upload to the root folder.
 * @param {string}      options.currentUuid  UUID of the current folder. It is highlighted in the tree.
 * @param {string[]}    options.path         UUIDs of the folders from the root to the current folder.
 * @param {boolean}     options.select       If true the folders are selected and not opened. Default false.
 * @param {string}      options.excludedUuid Select mode only: this folder and all its subfolders can't be selected.
 * @param {string}      options.currentLabel Select mode only: text for the current folder.
 * @param {boolean}     options.currentSelectable Select mode only: the current folder can be selected, e.g. to copy a file
 *                                           into its own folder. Default false.
 * @param {Function}    options.onSelect     Select mode only: called with the data of the selected folder or with null
 *                                           if the selection was removed.
 */
class AdmFolderTree {
    constructor(tree, options) {
        this.tree = tree;
        this.options = Object.assign({
            rootCanUpload: true,
            currentUuid: "",
            path: [],
            select: false,
            excludedUuid: "",
            currentLabel: "",
            currentSelectable: false,
            onSelect: function () {}
        }, options);
        this.buildPromise = null;
        this.selectedLink = null;

        this.tree.addEventListener("focusin", (event) => {
            const link = event.target.closest("a.admidio-folder-tree-link");
            if (link) {
                this.setTabStop(link);
            }
        });
        this.tree.addEventListener("keydown", (event) => this.handleKey(event));
        this.tree.addEventListener("click", (event) => this.handleClick(event));
    }

    /**
     * Build the tree with the root folder and expand all folders on the way to the current folder.
     * @returns {Promise} Resolved when the folders are shown.
     */
    build() {
        if (this.buildPromise) {
            return this.buildPromise;
        }

        const root = this.createNode({
            uuid: this.options.rootUuid,
            name: this.options.rootLabel,
            url: this.options.rootUrl,
            hasChildren: true,
            canUpload: this.options.rootCanUpload
        }, 1, false);
        this.tree.appendChild(root);
        this.getLink(root).tabIndex = 0;

        let chain = this.setExpanded(root, true);
        this.options.path.slice(1).forEach((uuid) => {
            chain = chain.then(() => {
                const parent = this.tree.querySelector("li[data-uuid=\"" + uuid + "\"]");
                return parent ? this.setExpanded(parent, true) : null;
            });
        });

        // Tab enters the tree at the current folder, unless the focus is already within the tree
        this.buildPromise = chain.then(() => {
            const active = this.tree.querySelector("a.active");
            if (active && !this.tree.contains(document.activeElement)) {
                this.setTabStop(active);
            }
        });
        return this.buildPromise;
    }

    /**
     * Open the folders down to a folder, e.g. the last destination of a move, and mark it with a text. The folder
     * is not selected. A folder that the user can't see is ignored.
     * @param {string} uuid  UUID of the folder.
     * @param {string} label Text that is shown at the folder.
     * @returns {Promise<boolean>} Resolved with true if the folder is shown in the tree.
     */
    openFolder(uuid, label) {
        return this.build()
            .then(() => this.loadPath(uuid))
            .then((path) => {
                let chain = Promise.resolve();
                path.slice(0, -1).forEach((folderUuid) => {
                    chain = chain.then(() => {
                        const item = this.tree.querySelector("li[data-uuid=\"" + folderUuid + "\"]");
                        return item ? this.setExpanded(item, true) : null;
                    });
                });

                return chain.then(() => {
                    const item = path.length > 0 ? this.tree.querySelector("li[data-uuid=\"" + uuid + "\"]") : null;
                    if (!item) {
                        return false;
                    }

                    const link = this.getLink(item);
                    if (label !== "" && !link.querySelector(".admidio-folder-tree-hint")) {
                        const hint = document.createElement("span");
                        hint.className = "admidio-folder-tree-hint";
                        hint.textContent = label;
                        link.appendChild(hint);
                    }
                    this.setTabStop(link);
                    link.scrollIntoView({block: "nearest"});
                    return true;
                });
            });
    }

    // the UUIDs of the folders from the root to the folder, empty if the folder is not available for the user
    loadPath(uuid) {
        const url = new URL(this.options.url, window.location.href);
        url.searchParams.set("folder_uuid", uuid);
        url.searchParams.set("with_path", "1");
        return fetch(url, {credentials: "same-origin"})
            .then((response) => response.json())
            .then((data) => (data.status === "success" && Array.isArray(data.path) ? data.path : []))
            .catch(() => []);
    }

    /**
     * @returns {string} UUID of the selected folder or an empty string. Only in select mode.
     */
    getSelectedUuid() {
        return this.selectedLink ? this.selectedLink.closest("li").dataset.uuid : "";
    }

    getLink(item) {
        return item.querySelector(":scope > .admidio-folder-tree-line > a");
    }

    // only one folder is in the tab order
    setTabStop(link) {
        const current = this.tree.querySelector("a[tabindex=\"0\"]");
        if (current && current !== link) {
            current.tabIndex = -1;
        }
        link.tabIndex = 0;
    }

    // fetch the visible subfolders of a folder
    loadFolders(uuid) {
        const url = new URL(this.options.url, window.location.href);
        url.searchParams.set("folder_uuid", uuid);
        return fetch(url, {credentials: "same-origin"})
            .then((response) => response.json())
            .then((data) => (data.status === "success" ? data.folders : []))
            .catch(() => []);
    }

    /**
     * Create the list item of a folder, the subfolders are added when the item is expanded.
     * @param {Object}  folder   Data of the folder: uuid, name, url, hasChildren, canUpload
     * @param {number}  level    Level of the folder in the tree, the root is 1.
     * @param {boolean} excluded Select mode only: the folder is within the excluded folder.
     */
    createNode(folder, level, excluded) {
        const item = document.createElement("li");
        item.className = "admidio-folder-tree-node";
        item.setAttribute("role", "none");
        item.dataset.uuid = folder.uuid;
        item.dataset.excluded = excluded ? "1" : "0";
        item.dataset.canUpload = folder.canUpload ? "1" : "0";

        const line = document.createElement("div");
        line.className = "admidio-folder-tree-line";

        // the button is only for the mouse, the keyboard uses the arrow keys of the folder
        if (folder.hasChildren) {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "admidio-folder-tree-toggle";
            button.tabIndex = -1;
            button.setAttribute("aria-hidden", "true");
            button.innerHTML = "<i class=\"bi bi-chevron-right\"></i>";
            button.addEventListener("click", () => {
                this.setExpanded(item, !item.classList.contains("expanded"));
            });
            line.appendChild(button);
        } else {
            const spacer = document.createElement("span");
            spacer.className = "admidio-folder-tree-toggle";
            line.appendChild(spacer);
        }

        const link = document.createElement("a");
        link.className = "admidio-folder-tree-link";
        link.href = folder.url;
        link.title = folder.name;
        link.tabIndex = -1;
        link.setAttribute("role", "treeitem");
        link.setAttribute("aria-level", level);
        if (folder.hasChildren) {
            link.setAttribute("aria-expanded", "false");
        }
        link.innerHTML = "<i class=\"bi bi-folder-fill\"></i> ";
        link.appendChild(document.createTextNode(folder.name));

        if (this.options.select) {
            const isExcluded = excluded || folder.uuid === this.options.excludedUuid;
            item.dataset.excluded = isExcluded ? "1" : "0";
            link.setAttribute("aria-selected", "false");
            if (!this.isSelectable(folder, isExcluded)) {
                link.setAttribute("aria-disabled", "true");
            }
            if (folder.uuid === this.options.currentUuid && this.options.currentLabel !== "") {
                const hint = document.createElement("span");
                hint.className = "admidio-folder-tree-hint";
                hint.textContent = this.options.currentLabel;
                link.appendChild(hint);
            }
        } else if (folder.uuid === this.options.currentUuid) {
            link.classList.add("active");
            link.setAttribute("aria-current", "page");
        }
        line.appendChild(link);
        item.appendChild(line);

        return item;
    }

    // in select mode a folder can be selected if the user may upload there and it is neither the current folder
    // (except if the current folder is allowed) nor the moved folder with its subfolders
    isSelectable(folder, excluded) {
        return folder.canUpload && !excluded && (folder.uuid !== this.options.currentUuid || this.options.currentSelectable);
    }

    /**
     * Allow or forbid the selection of the current folder. A selection that is no longer possible is removed.
     * @param {boolean} selectable
     */
    setCurrentSelectable(selectable) {
        this.options.currentSelectable = selectable;
        this.tree.querySelectorAll("li").forEach((item) => {
            const link = this.getLink(item);
            const folder = {uuid: item.dataset.uuid, canUpload: item.dataset.canUpload === "1"};
            if (this.isSelectable(folder, item.dataset.excluded === "1")) {
                link.removeAttribute("aria-disabled");
            } else {
                link.setAttribute("aria-disabled", "true");
            }
        });

        if (this.selectedLink && this.selectedLink.getAttribute("aria-disabled") === "true") {
            this.selectedLink.setAttribute("aria-selected", "false");
            this.selectedLink.classList.remove("selected");
            this.selectedLink = null;
            this.options.onSelect(null);
        }
    }

    /**
     * Expand or collapse a folder.
     * @returns {Promise} Resolved when the subfolders are shown.
     */
    setExpanded(item, expanded) {
        const link = this.getLink(item);
        if (!link.hasAttribute("aria-expanded")) {
            return Promise.resolve();
        }

        let group = item.querySelector(":scope > ul");
        link.setAttribute("aria-expanded", expanded ? "true" : "false");
        item.classList.toggle("expanded", expanded);
        if (group) {
            group.hidden = !expanded;
        }
        if (!expanded || group) {
            return Promise.resolve();
        }

        group = document.createElement("ul");
        group.className = "admidio-folder-tree-group";
        group.setAttribute("role", "group");
        group.id = this.tree.id + "_group_" + item.dataset.uuid;
        link.setAttribute("aria-owns", group.id);
        item.appendChild(group);
        const level = parseInt(link.getAttribute("aria-level"), 10) + 1;
        const excluded = item.dataset.excluded === "1";
        item.loading = this.loadFolders(item.dataset.uuid).then((folders) => {
            folders.forEach((folder) => {
                group.appendChild(this.createNode(folder, level, excluded));
            });
        });
        return item.loading;
    }

    // select a folder in select mode
    select(link) {
        if (!this.options.select || link.getAttribute("aria-disabled") === "true") {
            return;
        }
        if (this.selectedLink) {
            this.selectedLink.setAttribute("aria-selected", "false");
            this.selectedLink.classList.remove("selected");
        }
        this.selectedLink = link;
        link.setAttribute("aria-selected", "true");
        link.classList.add("selected");
        this.options.onSelect({
            uuid: link.closest("li").dataset.uuid,
            name: link.title,
            path: this.getPath(link)
        });
    }

    // names of the folders from the root to the folder of the link
    getPath(link) {
        const names = [];
        let item = link.closest("li");
        while (item) {
            names.unshift(this.getLink(item).title);
            item = item.parentElement.closest("li");
        }
        return names;
    }

    handleClick(event) {
        const link = event.target.closest("a.admidio-folder-tree-link");
        if (!link || !this.options.select) {
            return;
        }
        event.preventDefault();
        this.select(link);
    }

    // keyboard support like in a file explorer
    handleKey(event) {
        const link = event.target.closest("a.admidio-folder-tree-link");
        if (!link || event.altKey || event.ctrlKey || event.metaKey) {
            return;
        }

        const item = link.closest("li");
        const links = Array.from(this.tree.querySelectorAll("a.admidio-folder-tree-link")).filter((entry) => !entry.closest("ul[hidden]"));
        const index = links.indexOf(link);
        let target = null;

        switch (event.key) {
            case "ArrowDown":
                target = links[index + 1];
                break;
            case "ArrowUp":
                target = links[index - 1];
                break;
            case "Home":
                target = links[0];
                break;
            case "End":
                target = links[links.length - 1];
                break;
            case "ArrowRight":
                if (link.getAttribute("aria-expanded") === "false") {
                    this.setExpanded(item, true);
                } else if (link.getAttribute("aria-expanded") === "true") {
                    // the subfolders may still be loading
                    (item.loading || Promise.resolve()).then(() => {
                        const child = item.querySelector(":scope > ul > li");
                        if (child) {
                            this.getLink(child).focus();
                        }
                    });
                }
                break;
            case "ArrowLeft":
                if (link.getAttribute("aria-expanded") === "true") {
                    this.setExpanded(item, false);
                } else {
                    const parent = item.parentElement.closest("li");
                    target = parent ? this.getLink(parent) : null;
                }
                break;
            case "Enter":
            case " ":
                if (!this.options.select) {
                    return;
                }
                this.select(link);
                break;
            default:
                return;
        }

        event.preventDefault();
        if (target) {
            target.focus();
        }
    }
}
