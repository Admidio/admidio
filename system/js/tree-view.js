/**
 ***********************************************************************************************
 * Tree view for hierarchical data, e.g. the folders of the documents and files module.
 *
 * The tree is a WAI-ARIA tree with one tab stop. The child nodes are loaded from the server when a node is
 * expanded, at the start only the nodes from the root to the current node are expanded. The nodes are links.
 *
 * The server answers the url of the tree with JSON:
 * {"status": "success", "nodes": [{"id": "...", "name": "...", "url": "...", "hasChildren": true}]}
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
 * @param {Object}      options.root         The root node: id, name and url.
 * @param {string}      options.icon         Bootstrap icon of the nodes. Default "bi-folder-fill".
 * @param {string}      options.currentId    Id of the current node. It is highlighted in the tree.
 * @param {string[]}    options.path         Ids of the nodes from the root to the current node.
 */
class AdmTreeView {
    constructor(tree, options) {
        this.tree = tree;
        this.options = Object.assign({
            idParam: "node_id",
            icon: "bi-folder-fill",
            currentId: "",
            path: []
        }, options);
        this.options.root = Object.assign({url: ""}, this.options.root);
        this.buildPromise = null;

        this.tree.addEventListener("focusin", (event) => {
            const link = event.target.closest("a.admidio-tree-link");
            if (link) {
                this.setTabStop(link);
            }
        });
        this.tree.addEventListener("keydown", (event) => this.handleKey(event));
    }

    /**
     * Build the tree with the root node and expand all nodes on the way to the current node.
     * @returns {Promise} Resolved when the nodes are shown.
     */
    build() {
        if (this.buildPromise) {
            return this.buildPromise;
        }

        const root = this.createNode(Object.assign({hasChildren: true}, this.options.root), 1);
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
     * @param {Object}  node     Data of the node: id, name, url, hasChildren
     * @param {number}  level    Level of the node in the tree, the root is 1.
     */
    createNode(node, level) {
        const item = document.createElement("li");
        item.className = "admidio-tree-node";
        item.setAttribute("role", "none");
        item.dataset.id = node.id;

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

        if (node.id === this.options.currentId) {
            link.classList.add("active");
            link.setAttribute("aria-current", "page");
        }
        line.appendChild(link);
        item.appendChild(line);

        return item;
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
        item.loading = this.loadNodes(item.dataset.id).then((nodes) => {
            nodes.forEach((node) => {
                group.appendChild(this.createNode(node, level));
            });
        });
        return item.loading;
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
            default:
                return;
        }

        event.preventDefault();
        if (target) {
            target.focus();
        }
    }
}
