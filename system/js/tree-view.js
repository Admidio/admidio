/**
 ***********************************************************************************************
 * Tree view for hierarchical data, e.g. the folders of the documents and files module.
 *
 * The tree is a WAI-ARIA tree with one tab stop. The child nodes are loaded from the server when a node is
 * expanded, at the start only the nodes from the root to the current node are expanded. The tree can be used
 * for navigation (the nodes are links) or to select a node, e.g. in a form.
 *
 * The server answers the url of the tree with JSON:
 * {"status": "success", "nodes": [{"id": "...", "name": "...", "url": "...", "hasChildren": true, "selectable": true}]}
 * and, if the parameter with_path=1 is set, also "path": the ids of the nodes from the root to the node.
 *
 * A select field of a form (FormPresenter::addTreeSelect) is initialized automatically, see the end of the file.
 *
 * @copyright The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @param {HTMLElement} tree    The list element with the role "tree" that will contain the nodes.
 * @param {Object}      options
 * @param {string}      options.url          Url that returns the child nodes as JSON.
 * @param {string}      options.idParam      Name of the url parameter with the id of the parent node. Default "node_id".
 * @param {Object}      options.root         The root node: id, name, url and selectable.
 * @param {string}      options.icon         Bootstrap icon of the nodes. Default "bi-folder-fill".
 * @param {string}      options.currentId    Id of the current node. It is highlighted in the tree.
 * @param {string[]}    options.path         Ids of the nodes from the root to the current node.
 * @param {boolean}     options.select       If true the nodes are selected and not opened. Default false.
 * @param {string}      options.excludedId   Select mode only: this node and all its child nodes can't be selected.
 * @param {string}      options.currentLabel Select mode only: text for the current node.
 * @param {boolean}     options.currentSelectable Select mode only: the current node can be selected. Default false.
 * @param {Function}    options.onSelect     Select mode only: called with the data of the selected node or with null
 *                                           if the selection was removed.
 */
class AdmTreeView {
    constructor(tree, options) {
        this.tree = tree;
        this.options = Object.assign({
            idParam: "node_id",
            icon: "bi-folder-fill",
            currentId: "",
            path: [],
            select: false,
            excludedId: "",
            currentLabel: "",
            currentSelectable: false,
            onSelect: function () {}
        }, options);
        this.options.root = Object.assign({url: "", selectable: true}, this.options.root);
        this.buildPromise = null;
        this.selectedLink = null;

        this.tree.addEventListener("focusin", (event) => {
            const link = event.target.closest("a.admidio-tree-link");
            if (link) {
                this.setTabStop(link);
            }
        });
        this.tree.addEventListener("keydown", (event) => this.handleKey(event));
        this.tree.addEventListener("click", (event) => this.handleClick(event));
    }

    /**
     * Build the tree with the root node and expand all nodes on the way to the current node.
     * @returns {Promise} Resolved when the nodes are shown.
     */
    build() {
        if (this.buildPromise) {
            return this.buildPromise;
        }

        const root = this.createNode(Object.assign({hasChildren: true}, this.options.root), 1, false);
        this.tree.appendChild(root);
        this.getLink(root).tabIndex = 0;

        let chain = this.setExpanded(root, true);
        this.options.path.slice(1).forEach((id) => {
            chain = chain.then(() => {
                const parent = this.getItem(id);
                return parent ? this.setExpanded(parent, true) : null;
            });
        });

        // Tab enters the tree at the current node, unless the focus is already within the tree
        this.buildPromise = chain.then(() => {
            const active = this.tree.querySelector("a.active");
            if (active && !this.tree.contains(document.activeElement)) {
                this.setTabStop(active);
            }
        });
        return this.buildPromise;
    }

    /**
     * Open the nodes down to a node, e.g. the node that was selected last time, and mark it with a text. The node
     * is not selected. A node that the user can't see is ignored.
     * @param {string} id    Id of the node.
     * @param {string} label Text that is shown at the node.
     * @returns {Promise<boolean>} Resolved with true if the node is shown in the tree.
     */
    openNode(id, label) {
        return this.build()
            .then(() => this.loadPath(id))
            .then((path) => {
                let chain = Promise.resolve();
                path.slice(0, -1).forEach((nodeId) => {
                    chain = chain.then(() => {
                        const item = this.getItem(nodeId);
                        return item ? this.setExpanded(item, true) : null;
                    });
                });

                return chain.then(() => {
                    const item = path.length > 0 ? this.getItem(id) : null;
                    if (!item) {
                        return false;
                    }
                    const link = this.getLink(item);
                    if (label !== "" && !link.querySelector(".admidio-tree-hint")) {
                        const hint = document.createElement("span");
                        hint.className = "admidio-tree-hint";
                        hint.textContent = label;
                        link.appendChild(hint);
                    }
                    this.setTabStop(link);
                    link.scrollIntoView({block: "nearest"});
                    return true;
                });
            });
    }

    // the ids of the nodes from the root to the node, empty if the node is not available for the user
    loadPath(id) {
        const url = new URL(this.options.url, window.location.href);
        url.searchParams.set(this.options.idParam, id);
        url.searchParams.set("with_path", "1");
        return fetch(url, {credentials: "same-origin"})
            .then((response) => response.json())
            .then((data) => (data.status === "success" && Array.isArray(data.path) ? data.path : []))
            .catch(() => []);
    }

    /**
     * @returns {string} Id of the selected node or an empty string. Only in select mode.
     */
    getSelectedId() {
        return this.selectedLink ? this.selectedLink.closest("li").dataset.id : "";
    }

    getItem(id) {
        return Array.from(this.tree.querySelectorAll("li.admidio-tree-node")).find((item) => item.dataset.id === id) || null;
    }

    getLink(item) {
        return item.querySelector(":scope > .admidio-tree-line > a");
    }

    // only one node is in the tab order
    setTabStop(link) {
        const current = this.tree.querySelector("a[tabindex=\"0\"]");
        if (current && current !== link) {
            current.tabIndex = -1;
        }
        link.tabIndex = 0;
    }

    // fetch the child nodes of a node
    loadNodes(id) {
        const url = new URL(this.options.url, window.location.href);
        url.searchParams.set(this.options.idParam, id);
        return fetch(url, {credentials: "same-origin"})
            .then((response) => response.json())
            .then((data) => (data.status === "success" && Array.isArray(data.nodes) ? data.nodes : []))
            .catch(() => []);
    }

    /**
     * Create the list item of a node, the child nodes are added when the item is expanded.
     * @param {Object}  node     Data of the node: id, name, url, hasChildren, selectable
     * @param {number}  level    Level of the node in the tree, the root is 1.
     * @param {boolean} excluded Select mode only: the node is within the excluded node.
     */
    createNode(node, level, excluded) {
        const item = document.createElement("li");
        item.className = "admidio-tree-node";
        item.setAttribute("role", "none");
        item.dataset.id = node.id;
        item.dataset.excluded = excluded ? "1" : "0";
        item.dataset.selectable = node.selectable === false ? "0" : "1";

        const line = document.createElement("div");
        line.className = "admidio-tree-line";

        // the button is only for the mouse, the keyboard uses the arrow keys of the node
        if (node.hasChildren) {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "admidio-tree-toggle";
            button.tabIndex = -1;
            button.setAttribute("aria-hidden", "true");
            button.innerHTML = "<i class=\"bi bi-chevron-right\"></i>";
            button.addEventListener("click", () => {
                this.setExpanded(item, !item.classList.contains("expanded"));
            });
            line.appendChild(button);
        } else {
            const spacer = document.createElement("span");
            spacer.className = "admidio-tree-toggle";
            line.appendChild(spacer);
        }

        const link = document.createElement("a");
        link.className = "admidio-tree-link";
        link.href = node.url || "#";
        link.title = node.name;
        link.tabIndex = -1;
        link.setAttribute("role", "treeitem");
        link.setAttribute("aria-level", level);
        if (node.hasChildren) {
            link.setAttribute("aria-expanded", "false");
        }
        const icon = document.createElement("i");
        icon.className = "bi " + this.options.icon;
        link.append(icon, " ", node.name);

        if (this.options.select) {
            const isExcluded = excluded || node.id === this.options.excludedId;
            item.dataset.excluded = isExcluded ? "1" : "0";
            link.setAttribute("aria-selected", "false");
            if (!this.isSelectable(item)) {
                link.setAttribute("aria-disabled", "true");
            }
            if (node.id === this.options.currentId && this.options.currentLabel !== "") {
                const hint = document.createElement("span");
                hint.className = "admidio-tree-hint";
                hint.textContent = this.options.currentLabel;
                link.appendChild(hint);
            }
        } else if (node.id === this.options.currentId) {
            link.classList.add("active");
            link.setAttribute("aria-current", "page");
        }
        line.appendChild(link);
        item.appendChild(line);

        return item;
    }

    // in select mode a node can be selected if the server allows it and it is neither the current node
    // (except if the current node is allowed) nor the excluded node with its child nodes
    isSelectable(item) {
        return item.dataset.selectable === "1" && item.dataset.excluded !== "1"
            && (item.dataset.id !== this.options.currentId || this.options.currentSelectable);
    }

    /**
     * Allow or forbid the selection of the current node. A selection that is no longer possible is removed.
     * @param {boolean} selectable
     */
    setCurrentSelectable(selectable) {
        this.options.currentSelectable = selectable;
        this.tree.querySelectorAll("li.admidio-tree-node").forEach((item) => {
            const link = this.getLink(item);
            if (this.isSelectable(item)) {
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
     * Expand or collapse a node.
     * @returns {Promise} Resolved when the child nodes are shown.
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
        group.className = "admidio-tree-group";
        group.setAttribute("role", "group");
        group.id = this.tree.id + "_group_" + item.dataset.id;
        link.setAttribute("aria-owns", group.id);
        item.appendChild(group);
        const level = parseInt(link.getAttribute("aria-level"), 10) + 1;
        const excluded = item.dataset.excluded === "1";
        item.loading = this.loadNodes(item.dataset.id).then((nodes) => {
            nodes.forEach((node) => {
                group.appendChild(this.createNode(node, level, excluded));
            });
        });
        return item.loading;
    }

    // select a node in select mode
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
            id: link.closest("li").dataset.id,
            name: link.title,
            path: this.getPath(link)
        });
    }

    // names of the nodes from the root to the node of the link
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
        const link = event.target.closest("a.admidio-tree-link");
        if (!link || !this.options.select) {
            return;
        }
        event.preventDefault();
        this.select(link);
    }

    // keyboard support like in a file explorer
    handleKey(event) {
        const link = event.target.closest("a.admidio-tree-link");
        if (!link || event.altKey || event.ctrlKey || event.metaKey) {
            return;
        }

        const item = link.closest("li");
        const links = Array.from(this.tree.querySelectorAll("a.admidio-tree-link")).filter((entry) => !entry.closest("ul[hidden]"));
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
                    // the child nodes may still be loading
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

/**
 * Select fields of forms (FormPresenter::addTreeSelect): the selected node is written to the hidden field of the
 * form, which fires a "change" event, and its path is shown below the tree. If the field has a key to remember the
 * selection, the tree opens at the node that was used last time when the form was sent. The tree object is
 * available as property admTreeView of the element with the class admidio-tree-select.
 */
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".admidio-tree-select[data-tree-options]").forEach(function (container) {
        const options = JSON.parse(container.dataset.treeOptions);
        const field = document.getElementById(container.dataset.field);
        const tree = container.querySelector("ul[role=tree]");
        const summary = document.getElementById(container.dataset.summary);

        const treeView = new AdmTreeView(tree, Object.assign(options, {
            select: true,
            onSelect: function (node) {
                field.value = node === null ? "" : node.id;
                if (summary) {
                    summary.textContent = "";
                    if (node === null) {
                        summary.textContent = summary.dataset.textEmpty;
                    } else {
                        const icon = document.createElement("i");
                        icon.className = "bi " + treeView.options.icon + " me-1";
                        summary.append(icon, node.path.join(" / "));
                    }
                }
                field.dispatchEvent(new Event("change", {bubbles: true}));
            }
        }));
        container.admTreeView = treeView;

        treeView.build().then(function () {
            if (!options.rememberKey) {
                return;
            }
            let lastId = "";
            try {
                lastId = window.localStorage.getItem(options.rememberKey) || "";
            } catch (e) {
                // without the storage of the browser the tree opens at the current node
            }
            if (lastId === "") {
                return;
            }
            treeView.openNode(lastId, options.rememberLabel || "").then(function (shown) {
                if (!shown) {
                    // the node is deleted or the user can't see it
                    try {
                        window.localStorage.removeItem(options.rememberKey);
                    } catch (e) {
                        // nothing to do
                    }
                }
            });
        });

        // the selection is remembered when the form is sent
        const form = container.closest("form");
        if (form && options.rememberKey) {
            form.addEventListener("submit", function () {
                if (field.value === "") {
                    return;
                }
                try {
                    window.localStorage.setItem(options.rememberKey, field.value);
                } catch (e) {
                    // the selection is only not remembered
                }
            }, true);
        }
    });
});
