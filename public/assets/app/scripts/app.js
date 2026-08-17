"use strict";

document.addEventListener("DOMContentLoaded", function () {
    const d = document;

    // Breakpoints alinhados com Bootstrap
    const breakpoints = {
        md: 768,
        lg: 992
    };

    const sidebar = d.getElementById("sidebarMenu");
    const sidebarToggleDesktop = d.querySelector(".sidebar-toggle-desktop");

    // =========================
    // SIDEBAR MOBILE / DESKTOP
    // =========================
    if (sidebar) {
        const STORAGE_KEY = "sidebar-contracted";
        const isDesktop = () => window.innerWidth >= breakpoints.lg;

        const handleBodyState = () => {
            if (window.innerWidth < breakpoints.lg && sidebar.classList.contains("show")) {
                d.body.style.position = "fixed";
            } else {
                d.body.style.position = "relative";
            }
        };

        const applyResponsiveSidebarState = () => {
            if (window.innerWidth < breakpoints.lg) {
                sidebar.classList.remove("contracted", "sidebar-hovered");
                sidebar.classList.remove("show");
                d.body.style.position = "relative";
            } else {
                sidebar.classList.add("show");

                const isContracted = localStorage.getItem(STORAGE_KEY) === "true";
                sidebar.classList.toggle("contracted", isContracted);
                sidebar.classList.remove("sidebar-hovered");
                d.body.style.position = "relative";
            }
        };

        sidebar.addEventListener("shown.bs.collapse", handleBodyState);
        sidebar.addEventListener("hidden.bs.collapse", handleBodyState);

        window.addEventListener("resize", applyResponsiveSidebarState);

        // desktop: toggle contracted
        if (sidebarToggleDesktop) {
            sidebarToggleDesktop.addEventListener("click", function () {
                if (!isDesktop()) return;

                const willContract = !sidebar.classList.contains("contracted");

                sidebar.classList.toggle("contracted", willContract);
                sidebar.classList.remove("sidebar-hovered");

                localStorage.setItem(STORAGE_KEY, String(willContract));
            });
        }

        // desktop: hover expand
        sidebar.addEventListener("mouseenter", function () {
            if (!isDesktop()) return;
            if (!sidebar.classList.contains("contracted")) return;

            sidebar.classList.add("sidebar-hovered");
        });

        sidebar.addEventListener("mouseleave", function () {
            if (!isDesktop()) return;

            sidebar.classList.remove("sidebar-hovered");
        });

        applyResponsiveSidebarState();
    }

    // Notification bell
    const iconNotifications = d.querySelector(".notification-bell");

    if (iconNotifications) {
        iconNotifications.addEventListener("shown.bs.dropdown", function () {
            iconNotifications.classList.remove("unread");
        });
    }

    d.querySelectorAll("[data-menu-subtoggle='true']").forEach(function (trigger) {
        trigger.addEventListener("click", function (event) {
            event.preventDefault();
            event.stopPropagation();

            const parent = trigger.closest(".dropdown-submenu");
            const submenu = parent ? parent.querySelector(":scope > .dropdown-menu") : null;

            if (!parent || !submenu) {
                return;
            }

            const willOpen = !submenu.classList.contains("show");

            parent.parentElement?.querySelectorAll(":scope > .dropdown-submenu").forEach(function (item) {
                item.classList.remove("is-open");
                item.querySelectorAll(":scope > .dropdown-menu.show").forEach(function (openMenu) {
                    openMenu.classList.remove("show");
                });
                item.querySelectorAll(":scope > [data-menu-subtoggle='true']").forEach(function (link) {
                    link.setAttribute("aria-expanded", "false");
                });
            });

            parent.classList.toggle("is-open", willOpen);
            submenu.classList.toggle("show", willOpen);
            trigger.setAttribute("aria-expanded", String(willOpen));
        });
    });

    d.querySelectorAll(".app-nav-dropdown").forEach(function (dropdown) {
        dropdown.addEventListener("hide.bs.dropdown", function () {
            dropdown.querySelectorAll(".dropdown-menu.show").forEach(function (menu) {
                menu.classList.remove("show");
            });

            dropdown.querySelectorAll(".dropdown-submenu.is-open").forEach(function (item) {
                item.classList.remove("is-open");
            });

            dropdown.querySelectorAll("[data-menu-subtoggle='true']").forEach(function (link) {
                link.setAttribute("aria-expanded", "false");
            });
        });
    });

    // Background helpers
    d.querySelectorAll("[data-background]").forEach(function (el) {
        el.style.backgroundImage = `url("${el.getAttribute("data-background")}")`;
    });

    d.querySelectorAll("[data-background-lg]").forEach(function (el) {
        if (d.body.clientWidth > breakpoints.lg) {
            el.style.backgroundImage = `url("${el.getAttribute("data-background-lg")}")`;
        }
    });

    d.querySelectorAll("[data-background-color]").forEach(function (el) {
        el.style.backgroundColor = el.getAttribute("data-background-color");
    });

    d.querySelectorAll("[data-color]").forEach(function (el) {
        el.style.color = el.getAttribute("data-color");
    });

    // Bootstrap tooltips
    d.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    if (typeof applyMasks === "function") {
        applyMasks($(document));
    }

    d.querySelectorAll('[data-tooltip="true"]').forEach(function (el) {
        if (el._tooltip) {
            return;
        }

        el._tooltip = new bootstrap.Tooltip(el, {
            customClass: "custom-tooltip"
        });
    });

    d.body.addEventListener("mouseover", function (e) {
        const el = e.target.closest('[data-tooltip="true"]');
        if (!el || el._tooltip) return;

        el._tooltip = new bootstrap.Tooltip(el, {
            customClass: "custom-tooltip"
        });
    });

    // Bootstrap popovers
    d.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
        new bootstrap.Popover(el);
    });

    d.body.addEventListener("mouseover", function (e) {
        const el = e.target.closest("[data-ui-tooltip]");
        if (!el || el._tooltip) return;
        el._tooltip = new bootstrap.Tooltip(el);
    });

    function initFormValidation($root) {
        if (!window.jQuery || !$.fn.validate) {
            return;
        }

        const $scope = $root && $root.length ? $root : $(document);

        const errorClass = "validate-error";
        const bootstrapErrorClass = "is-invalid";
        const passwordMinLength = Number(window.passwordMinLength || 5);

        function getSelectpickerWrapper($el) {
            if (!$el || !$el.length) {
                return $();
            }

            if ($el.hasClass("selectpicker")) {
                return $el.parent(".bootstrap-select");
            }

            const $wrapper = $el.closest(".bootstrap-select");
            return $wrapper.length ? $wrapper : $el.parent(".bootstrap-select");
        }

        $.validator.setDefaults({
            errorClass: errorClass,
            validClass: "",
            errorElement: "div",

            highlight: function (element) {
                const $el = $(element);
                const $group = $el.closest(".input-group");
                const $selectpicker = getSelectpickerWrapper($el);

                if ($selectpicker.length) {
                    $selectpicker.addClass(bootstrapErrorClass);
                    $selectpicker.find(".dropdown-toggle").addClass(bootstrapErrorClass);
                    $el.removeClass(bootstrapErrorClass);
                } else if ($group.length) {
                    $group.addClass(bootstrapErrorClass);
                    $el.removeClass(bootstrapErrorClass);
                } else {
                    $el.addClass(bootstrapErrorClass);
                }
            },

            unhighlight: function (element) {
                const $el = $(element);
                const $group = $el.closest(".input-group");
                const $selectpicker = getSelectpickerWrapper($el);

                if ($selectpicker.length) {
                    $selectpicker.removeClass(bootstrapErrorClass);
                    $selectpicker.find(".dropdown-toggle").removeClass(bootstrapErrorClass);
                    $el.removeClass(bootstrapErrorClass);
                } else if ($group.length) {
                    $group.removeClass(bootstrapErrorClass);
                    $el.removeClass(bootstrapErrorClass);
                } else {
                    $el.removeClass(bootstrapErrorClass);
                }
            },

            errorPlacement: function (error, element) {
                error.addClass("invalid-feedback");

                const $selectpicker = getSelectpickerWrapper($(element));
                const $group = $(element).closest(".input-group");

                if ($selectpicker.length) {
                    error.insertAfter($selectpicker);
                } else if ($group.length) {
                    error.insertAfter($group);
                } else {
                    error.insertAfter(element);
                }
            }
        });

        window.errorClass = errorClass;
        window.bootstrapErrorClass = bootstrapErrorClass;
        window.passwordMinLength = passwordMinLength;

        $scope.find(".form-validate").addBack(".form-validate").each(function () {
            const $form = $(this);

            if ($form.data("validator")) {
                return;
            }

            $form.validate({
                submitHandler: function (form) {
                    const $modal = $(form).closest(".modal");
                    const $submitButton = $(form).find('button[type="submit"], input[type="submit"]').filter(":enabled:visible").first();

                    loadingButton($submitButton.length ? $submitButton : null);
                    setModalInteractionLocked($modal, true);

                    if ($modal.length && typeof window.setModalLoading === "function") {
                        window.setModalLoading($modal, true, "Salvando...");
                    }

                    form.submit();
                }
            });
        });
    }

    window.initFormValidation = initFormValidation;

    initFormValidation($(document));

    if (window.jQuery && $.fn.select2) {
        $.fn.select2.defaults.set("language", {
            noResults: function () {
                return "Nenhum resultado encontrado";
            },
            searching: function () {
                return "Buscando...";
            },
            errorLoading: function () {
                return "Nao foi possivel carregar os resultados";
            },
            inputTooShort: function () {
                return "Digite mais caracteres";
            },
            loadingMore: function () {
                return "Carregando mais resultados...";
            },
            maximumSelected: function () {
                return "Voce atingiu o limite de selecoes";
            }
        });

        $(".select2").each(function () {
            const $select = $(this);

            if ($select.hasClass("select2-hidden-accessible")) {
                return;
            }

            const search = $select.data("search");
            const dropdownParentSelector = $select.data("dropdownParent");
            const dropdownParent = dropdownParentSelector ? $(dropdownParentSelector) : null;

            $select.select2({
                minimumResultsForSearch: (search === false || search === "false") ? Infinity : 0,
                width: "100%",
                theme: "bootstrap-5",
                dropdownParent: dropdownParent && dropdownParent.length ? dropdownParent : undefined
            });

            $select.on("select2:open", function () {
                const searchField = $(this).data("select2")?.dropdown?.$search?.[0];

                if (searchField) {
                    searchField.focus();
                }
            });
        });
    }

    // Notificações toastr (Notyf.js)
    // Instância global
    function normalizeSelectpickerSearchText(value) {
        return String(value ?? "")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLowerCase();
    }

    function buildSelectpickerSearchTokens(text) {
        const normalized = normalizeSelectpickerSearchText(text);
        const collapsed = normalized.replace(/[^a-z0-9]+/g, " ").trim();
        const compact = normalized.replace(/[^a-z0-9]+/g, "");
        const digits = normalized.replace(/\D+/g, "");
        const tokens = [collapsed, compact, digits].filter(Boolean);

        return [...new Set(tokens)].join(" ");
    }

    function enhanceSelectpickerSearch($root) {
        if (!window.jQuery) {
            return;
        }

        const $scope = $root && $root.length ? $root : $(document);

        $scope.find("select.selectpicker").addBack("select.selectpicker").each(function () {
            const $select = $(this);

            $select.find("option").each(function () {
                const $option = $(this);
                const baseTokens = String($option.attr("data-tokens") || "").trim();
                const generatedTokens = buildSelectpickerSearchTokens($option.text());
                const tokens = [baseTokens, generatedTokens].filter(Boolean).join(" ").trim();

                if (tokens) {
                    $option.attr("data-tokens", tokens);
                }
            });
        });
    }

    window.normalizeSelectpickerSearchText = normalizeSelectpickerSearchText;
    window.buildSelectpickerSearchTokens = buildSelectpickerSearchTokens;
    window.enhanceSelectpickerSearch = enhanceSelectpickerSearch;

    if (window.jQuery && $.fn.selectpicker && $.fn.selectpicker.Constructor) {
        $.fn.selectpicker.Constructor.DEFAULTS.liveSearchNormalize = true;
        enhanceSelectpickerSearch($(document));
    }

    const _notyf = new Notyf({
        duration: 3000,
        position: {
            x: "right",
            y: "bottom"
        },
        dismissible: true,
        ripple: true,
        types: [
            {
                type: "success",
                background: "#22c55e",
                icon: {
                    className: "fa-solid fa-circle-check",
                    tagName: "i",
                    color: "#fff"
                }
            },
            {
                type: "error",
                background: "#ef4444",
                icon: {
                    className: "fa-solid fa-circle-xmark",
                    tagName: "i",
                    color: "#fff"
                }
            },
            {
                type: "warning",
                background: "#f59e0b",
                icon: {
                    className: "fa-solid fa-triangle-exclamation",
                    tagName: "i",
                    color: "#fff"
                }
            },
            {
                type: "info",
                background: "#3b82f6",
                icon: {
                    className: "fas fa-info-circle",
                    tagName: "i",
                    color: "#fff"
                }
            },
        ]
    });

    /**
     * Helper para notificações
     */
    function notify(
        message,
        type = "success",
        duration = 5,
        x = "right",
        y = "bottom",
        close = true
    ) {

        // alias bootstrap
        if (type === "danger") {
            type = "error";
        }

        _notyf.open({
            type: type,
            message: message,
            duration: duration * 1000,
            dismissible: close,
            position: {
                x: x,
                y: y
            }
        });
    }

    window.notify = notify;

    function showToastr(msg, classe = "success", title = "", side = "top right", duration = 3000, extras = {}) {
        if (!msg || typeof toastr === "undefined") {
            return;
        }

        if (classe === "danger") {
            classe = "error";
        }

        const positionMap = {
            "top left": "toast-top-left",
            "top right": "toast-top-right",
            "bottom left": "toast-bottom-left",
            "bottom right": "toast-bottom-right"
        };

        const positionClass = positionMap[side] || "toast-top-right";

        toastr[classe](msg, title, {
            positionClass: positionClass,
            timeOut: duration,
            ...extras
        });
    }

    window.showToastr = showToastr;

    function flushFlashQueue() {
        if (!Array.isArray(window.appFlashQueue) || !window.appFlashQueue.length) {
            return;
        }

        const queue = window.appFlashQueue.splice(0, window.appFlashQueue.length);

        queue.forEach(function (item) {
            if (!item || !item.message) {
                return;
            }

            notify(
                item.message,
                item.type || "info",
                Number(item.duration || 5),
                item.x || "right",
                item.y || "bottom",
                item.close !== false
            );
        });
    }

    function flushFlashToastrQueue() {
        if (!Array.isArray(window.appFlashToastrQueue) || !window.appFlashToastrQueue.length) {
            return;
        }

        const queue = window.appFlashToastrQueue.splice(0, window.appFlashToastrQueue.length);

        queue.forEach(function (item) {
            if (!item || !item.message) {
                return;
            }

            showToastr(
                item.message,
                item.type || "info",
                item.title || "",
                item.side || "top right",
                Number(item.duration || 5000),
                item.extras || {}
            );
        });
    }

    flushFlashQueue();
    flushFlashToastrQueue();

    // notify("Sucess", "success");
    // notify("Erro", "danger");
    // notify("Aviso", "warning");
    // notify("Info", "info");

    document.querySelectorAll(".toggle-pass").forEach(function (btn) {
        btn.addEventListener("click", function () {
            const input = this.parentElement.querySelector("input");
            const icon = this.querySelector("i");

            if (!input || !icon) return;

            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("uil-eye");
                icon.classList.add("uil-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("uil-eye-slash");
                icon.classList.add("uil-eye");
            }
        });
    });


});

// =========================
// ACTIVE MENU
// =========================
function activeMenu(element, options = {}) {
    const {
        highlightParent = false,
        closeOthers = true
    } = options;

    const menus = document.querySelectorAll(`[data-menu-root="primary"] [data-menu="${element}"]`);
    if (!menus.length) return;

    menus.forEach(function (menu) {
        const root = menu.closest('[data-menu-root="primary"]');

        if (!root) return;

        root.querySelectorAll('.nav-link.active, .dropdown-item.active').forEach(el => {
            el.classList.remove('active');
        });

        if (closeOthers) {
            root.querySelectorAll('.multi-level.collapse.show').forEach(el => {
                el.classList.remove('show');
            });

            root.querySelectorAll('.nav-link[aria-expanded="true"], .dropdown-item[aria-expanded="true"]').forEach(el => {
                el.setAttribute('aria-expanded', 'false');
            });

            root.querySelectorAll('.dropdown-menu.show').forEach(el => {
                el.classList.remove('show');
            });

            root.querySelectorAll('.dropdown-submenu.is-open, .dropdown.show').forEach(el => {
                el.classList.remove('is-open', 'show');
            });
        }

        menu.classList.add('active');

        let currentCollapse = menu.closest('.multi-level.collapse');
        const visited = new Set();

        while (currentCollapse && !visited.has(currentCollapse.id)) {
            visited.add(currentCollapse.id);
            currentCollapse.classList.add('show');

            const trigger = root.querySelector(
                `.nav-link[data-bs-target="#${currentCollapse.id}"], .nav-link[href="#${currentCollapse.id}"]`
            );

            if (!trigger) break;

            trigger.setAttribute('aria-expanded', 'true');

            if (highlightParent) {
                trigger.classList.add('active');
            }

            currentCollapse = trigger.parentElement?.closest('.multi-level.collapse') || null;
        }

        const isHorizontalMenu = !!root.classList.contains('app-horizontal-menu');

        if (isHorizontalMenu) {
            const closeHorizontalDropdowns = function () {
                root.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                    const bsDrop = bootstrap.Dropdown.getInstance(toggle);
                    if (bsDrop) bsDrop.hide();
                });
                root.querySelectorAll('.dropdown-menu.show').forEach(function (el) {
                    el.classList.remove('show');
                });
                root.querySelectorAll('.dropdown.show, .app-nav-dropdown.show').forEach(function (el) {
                    el.classList.remove('show');
                });
            };
            closeHorizontalDropdowns();
            setTimeout(closeHorizontalDropdowns, 0);
        }

        if (!isHorizontalMenu) {
            let currentDropdown = menu.closest('.dropdown-menu');

            while (currentDropdown) {
                currentDropdown.classList.add('show');

                const trigger = currentDropdown.previousElementSibling;
                if (!trigger) break;

                trigger.setAttribute('aria-expanded', 'true');

                const dropdownItem = currentDropdown.closest('.dropdown-submenu, .dropdown');
                if (dropdownItem) {
                    dropdownItem.classList.add('is-open', 'show');
                }

                if (highlightParent) {
                    trigger.classList.add('active');
                }

                currentDropdown = dropdownItem?.parentElement?.closest('.dropdown-menu') || null;
            }
        }
    });
}

function setModalInteractionLocked(modal, locked = true) {
    const $modal = modal ? $(modal).first() : $();

    if (!$modal.length) {
        return;
    }

    $modal.data("interactionLocked", Boolean(locked));
    $modal.attr("data-interaction-locked", locked ? "1" : "0");

    const $dismissButtons = $modal.find(".btn-close, [data-bs-dismiss='modal']");
    const $cancelButtons = $modal.find(".modal-footer button:not([type='submit']), .modal-footer a[data-bs-dismiss='modal']");

    $dismissButtons.prop("disabled", locked).attr("aria-disabled", locked ? "true" : "false");
    $cancelButtons.prop("disabled", locked).attr("aria-disabled", locked ? "true" : "false");
}

window.setModalInteractionLocked = setModalInteractionLocked;

$(document).on("hidden.bs.modal", ".modal", function () {
    setModalInteractionLocked(this, false);

    if (typeof window.setModalLoading === "function") {
        window.setModalLoading(this, false);
    }
});

// =========================
// TABELAS
// =========================
document.addEventListener("DOMContentLoaded", function () {
    const DefaultTable = {
        normalizar(texto) {
            return String(texto ?? "")
                .toLowerCase()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "");
        },

        debounce(fn, delay = 300) {
            let timer;
            return function (...args) {
                clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), delay);
            };
        },

        langs: {
            "pt-br": {
                pagination: {
                    first: "Primeira",
                    first_title: "Primeira página",
                    last: "Última",
                    last_title: "Última página",
                    prev: "Anterior",
                    prev_title: "Página anterior",
                    next: "Próxima",
                    next_title: "Próxima página",
                    page_size: "Por página"
                },
                headerFilters: {
                    default: "Filtrar..."
                }
            }
        },

        defaults() {
            return {
                layout: "fitColumns",
                movableColumns: true,
                resizableColumns: true,
                pagination: true,
                paginationSize: 25,
                paginationSizeSelector: [10, 25, 50, 100],
                locale: "pt-br",
                langs: this.langs,
                placeholder: "Nenhum registro encontrado"
            };
        },

        init(selector, options = {}) {
            const config = {
                ...this.defaults(),
                ...options
            };

            return new Tabulator(selector, config);
        },

        bindGlobalSearch(table, inputSelector) {
            const input = document.querySelector(inputSelector);

            if (!input) {
                return;
            }

            let termoBusca = "";

            const filtroGlobal = (data) => {
                if (!termoBusca) {
                    return true;
                }

                return Object.values(data).some((valor) => {
                    return this.normalizar(valor).includes(termoBusca);
                });
            };

            table.setFilter(filtroGlobal);

            input.addEventListener("input", (e) => {
                termoBusca = this.normalizar(e.target.value.trim());
                table.refreshFilter();
            });
        },

        bindCounter(table, infoSelector, formatFn = null) {
            const info = document.querySelector(infoSelector);

            if (!info) {
                return;
            }

            const render = () => {
                const total = table.getDataCount("active");
                const pagina = table.getPage();
                const porPagina = table.getPageSize();

                if (!total) {
                    info.textContent = "0 registros";
                    return;
                }

                let inicio = ((pagina - 1) * porPagina) + 1;
                let fim = Math.min(pagina * porPagina, total);

                const texto = formatFn
                    ? formatFn({ inicio, fim, total, pagina, porPagina })
                    : `${inicio}-${fim} de ${total} registros`;

                info.textContent = texto;
            };

            table.on("tableBuilt", render);
            table.on("dataFiltered", render);
            table.on("dataLoaded", render);
            table.on("pageLoaded", render);
            table.on("pageSizeChanged", render);

            render();
        }
    };

    window.DefaultTable = DefaultTable;

    const DataTableDefaults = {
        language: {
            search: "",
            searchPlaceholder: "Buscar...",
            lengthMenu: "_MENU_",
            info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
            infoEmpty: "Mostrando 0 a 0 de 0 registros",
            infoFiltered: "(filtrado de _MAX_ registros)",
            zeroRecords: "Nenhum registro encontrado",
            emptyTable: "Nenhum registro encontrado",
            paginate: {
                first: "Primeira",
                last: "Ultima",
                next: "Proxima",
                previous: "Anterior"
            }
        },
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        info: true,
        paging: true,
        searching: true,
        responsive: true
    };

    const DataTableLayouts = {
        alt: '<"dt-layout-top d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3"<"dt-layout-search"f><"dt-layout-info text-sm-end"i>>rt<"dt-layout-bottom d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mt-3"<"dt-layout-length"l><"dt-layout-pagination"p>>',
        swapped: '<"dt-layout-top d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3"<"dt-layout-search"f><"dt-layout-info text-sm-end"i>>rt<"dt-layout-bottom d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mt-3"<"dt-layout-length"l><"dt-layout-pagination"p>>'
    };

    window.DataTableDefaults = DataTableDefaults;
    window.DataTableLayouts = DataTableLayouts;

    function normalizeDataTableSearchValue(value) {
        return DefaultTable.normalizar(
            String(value ?? "").replace(/<[^>]*>/g, " ")
        );
    }

    function setDataTableGridClass($wrapper, selector, classes) {
        const $target = $wrapper.find(selector).first();

        if (!$target.length) {
            return;
        }

        const $column = $target.closest("div[class*='col-']");

        if (!$column.length) {
            return;
        }

        $column
            .removeClass(function (_, current) {
                return String(current || "")
                    .split(/\s+/)
                    .filter(function (className) {
                        return /^col-/.test(className);
                    })
                    .join(" ");
            })
            .addClass(classes);
    }

    function adjustDataTableWrapperGrid($wrapper) {
        setDataTableGridClass($wrapper, "div.dataTables_length", "col-12 col-sm-5");
        setDataTableGridClass($wrapper, "div.dataTables_filter", "col-12 col-sm-7");
        setDataTableGridClass($wrapper, "div.dataTables_info", "col-12 col-md-4");
        setDataTableGridClass($wrapper, "div.dataTables_paginate", "col-12 col-md-8");
    }

    window.buildDataTableOptions = function (options = {}) {
        return {
            ...DataTableDefaults,
            ...options,
            language: {
                ...DataTableDefaults.language,
                ...(options.language || {})
            }
        };
    };

    if (window.jQuery && $.fn.DataTable) {
        if ($.fn.dataTable.ext.type.search) {
            $.fn.dataTable.ext.type.search.string = function (data) {
                return normalizeDataTableSearchValue(data);
            };

            $.fn.dataTable.ext.type.search.html = function (data) {
                return normalizeDataTableSearchValue(data);
            };
        }

        $(".table-datatable").each(function () {
            const $table = $(this);

            if ($.fn.DataTable.isDataTable(this)) {
                return;
            }

            const options = window.buildDataTableOptions($table.data("datatable-options") || {});
            const originalInitComplete = options.initComplete;

            if (($table.hasClass("table-datatable-alt") || $table.hasClass("table-datatable-swapped")) && !options.dom) {
                options.dom = DataTableLayouts.alt;
            }

            options.initComplete = function (...args) {
                const api = this.api();
                const $wrapper = $(api.table().container());
                const $searchInput = $wrapper.find(".dataTables_filter input");

                adjustDataTableWrapperGrid($wrapper);

                $searchInput.off(".DT");
                $searchInput.on("input", function () {
                    api.search(DefaultTable.normalizar($(this).val())).draw();
                });

                if (typeof originalInitComplete === "function") {
                    originalInitComplete.apply(this, args);
                }
            };

            $table.DataTable(options);
            const wrapper = $table.closest(".dataTables_wrapper");

            if ($table.hasClass("table-datatable-alt") || $table.hasClass("table-datatable-swapped")) {
                wrapper.addClass("table-datatable-alt");
            }
        });
    }

    if (typeof Tabulator !== "undefined") {
        document.querySelectorAll(".table-tabulator").forEach(function (el, index) {
            if (el.dataset.tabulatorInitialized === "true") {
                return;
            }

            if (!el.id) {
                el.id = `table-tabulator-${index + 1}`;
            }

            const toolbar = document.createElement("div");
            toolbar.className = "d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3";

            const search = document.createElement("input");
            search.type = "search";
            search.id = `${el.id}-search`;
            search.className = "form-control form-control-sm";
            search.placeholder = "Buscar...";
            search.style.maxWidth = "280px";

            const info = document.createElement("div");
            info.id = `${el.id}-info`;
            info.className = "small text-muted";
            info.textContent = "0 registros";

            toolbar.append(search, info);
            el.parentNode.insertBefore(toolbar, el);

            const table = DefaultTable.init(el, {
                paginationSize: 25
            });

            DefaultTable.bindGlobalSearch(table, `#${search.id}`);
            DefaultTable.bindCounter(table, `#${info.id}`, function ({ inicio, fim, total }) {
                return `${inicio}-${fim} de ${total} registros`;
            });

            el.dataset.tabulatorInitialized = "true";
        });
    }
});

// =========================
// DEIXAR INPUTS MAIUSCULOS
// =========================
function uppers(elements, root) {
    var $root = root ? $(root) : $(document);
    if (elements!=""){
        if (elements=="*") {
            $root.find("input, select, textarea").prependClass("input-upper");
        } else {
            var inputs = elements.split(",");
            inputs.forEach(function(name){
                $root.find("[name='" + name + "']").prependClass("input-upper");
            });
        }
    }
}

// =========================
// DEIXAR CAMPOS OBRIGATORIOS
// =========================
function requireds(elements, root) {
    var $root = root ? $(root) : $(document);

    if (elements !== "") {
        var appendRequiredMark = function ($label) {
            if (!$label || !$label.length) {
                return;
            }

            if ($label.find(".required-mark").length) {
                return;
            }

            $label.append(' <span class="text-danger required-mark">*</span>');
        };

        var markRequired = function (fieldName) {
            var $fields = $root.find("[name='" + fieldName + "']");

            $fields.each(function () {
                var $field = $(this);
                $field.prop("required", true);
                $field.attr("title", "Campo Obrigatório");

                var fieldId = $field.attr("id");
                if (fieldId) {
                    var $labelFor = $root.find("label[for='" + fieldId + "']");
                    $labelFor.attr("title", "Campo Obrigatório");
                    appendRequiredMark($labelFor.first());
                } else {
                    var $groupLabel = $field.closest(".mb-3, .form-group, .col-12").find("label.form-label").first();
                    $groupLabel.attr("title", "Campo Obrigatório");
                    appendRequiredMark($groupLabel);
                }
            });
        };

        if (elements === "*") {
            $root.find("input, select, textarea").each(function () {
                var $field = $(this);
                $field.prop("required", true);
                $field.attr("title", "Campo Obrigatório");

                var fieldId = $field.attr("id");
                if (fieldId) {
                    var $labelFor = $root.find("label[for='" + fieldId + "']");
                    $labelFor.attr("title", "Campo Obrigatório");
                    appendRequiredMark($labelFor.first());
                } else {
                    var $groupLabel = $field.closest(".mb-3, .form-group, .col-12").find("label.form-label").first();
                    $groupLabel.attr("title", "Campo Obrigatório");
                    appendRequiredMark($groupLabel);
                }
            });
        } else {
            var inputs = elements.split(",");
            inputs.forEach(function(name){
                markRequired(name);
            });
        }
    }
}

// =========================
// MASCARAS GLOBAIS
// =========================
function applyMasks($root) {
    $root = $root && $root.length ? $root : $(document);

    const selectors = [
        '.input-number', '.input-milhar', '.input-decimal', '.input-money',
        '.input-uf', '.input-datebr', '.input-time', '.input-timesec',
        '.input-cep', '.input-cpf', '.input-cnpj', '.input-dia-mes',
        '.input-fone', '.input-phone', '.input-cpf-cnpj', '.input-placa'
    ].join(',');

    $root.find(selectors).not('[data-mask="none"]').unmask();

    $root.find('.input-number').mask("#0", { reverse: true });
    $root.find('.input-milhar').mask("#.##0", { reverse: true });
    $root.find('.input-decimal').mask("#.##0,0", { reverse: true });
    $root.find('.input-money').mask("#.##0,00", { reverse: true });
    $root.find('.input-uf').mask("SS");
    $root.find('.input-datebr').mask("00/00/0000");
    $root.find('.input-time').mask("00:00");
    $root.find('.input-timesec').mask("00:00:00");
    $root.find('.input-cep').mask("00.000-000");
    $root.find('.input-cpf').mask("000.000.000-00");
    $root.find('.input-dia-mes').mask("00/00");

    const alphaNumericMaskOptions = {
        translation: {
            'A': { pattern: /[A-Za-z0-9]/ },
            '0': { pattern: /[0-9]/ }
        }
    };

    const cnpjMask = 'AA.AAA.AAA/AAAA-00';
    const cpfMask = '000.000.000-00';

    function normalizeAlphaNumeric(value) {
        return String(value || '')
            .toUpperCase()
            .replace(/[^0-9A-Z]/g, '');
    }

    function syncUppercase(field) {
        const current = String(field.val() || '');
        const upper = current.toUpperCase();

        if (current !== upper) {
            field.val(upper);
        }
    }

    $root.find('.input-cnpj').mask(cnpjMask, {
        ...alphaNumericMaskOptions,
        onKeyPress: function (val, e, field, options) {
            syncUppercase(field);
            field.mask(cnpjMask, options);
        }
    });

    const nineDigits = function (val) {
        return val.replace(/\D/g, '').length === 11 ? '(00) 00000-0000' : '(00) 0000-00009';
    };

    const phoneOptions = {
        onKeyPress: function (val, e, field, options) {
            field.mask(nineDigits.apply({}, arguments), options);
        }
    };

    $root.find('.input-fone,.input-phone').not('[data-mask="none"]').mask(nineDigits, phoneOptions);

    // CPF ou CNPJ, incluindo o novo formato alfanumerico da Receita Federal.
    const cpfCnpjBehavior = function (val) {
        const normalized = normalizeAlphaNumeric(val);

        if (/^[0-9]{0,11}$/.test(normalized) && normalized.length <= 11) {
            return cpfMask;
        }

        return cnpjMask;
    };

    $root.find('.input-cpf-cnpj').not('[data-mask="none"]').mask(cpfCnpjBehavior, {
        ...alphaNumericMaskOptions,
        onKeyPress: function (val, e, field, options) {
            syncUppercase(field);
            field.mask(cpfCnpjBehavior.apply({}, arguments), options);
        }
    });

    const placaAntigaMask = 'AAA-0000';
    const placaNovaMask = 'AAA0A00';

    const placaBehavior = function (val) {
        return val.length === 8 ? placaNovaMask : placaAntigaMask;
    };

    $root.find('.input-placa').mask(placaBehavior, {
        onKeyPress: function (val, e, field, options) {
            field.mask(placaBehavior.apply({}, arguments), options);
        },
        translation: {
            'A': { pattern: /[A-Za-z]/ },
            '0': { pattern: /[0-9]/ }
        }
    });
}

// =========================
// HELPERS DE FORMULARIO
// =========================
$(document).on("change", "[data-range-max]", function () {
    const value = $(this).val();
    const target = $(this).data("rangeMax");

    if (!target) {
        return;
    }

    $(target).attr("min", value || "");
});

$(document).on("change", "[data-range-min]", function () {
    const value = $(this).val();
    const target = $(this).data("rangeMin");

    if (!target) {
        return;
    }

    $(target).attr("max", value || "");
});

$(document).on("keypress keyup blur", ".numeric-comma", function (event) {
    const $input = $(this);
    const text = String($input.val() || "");
    const limit = parseInt($input.data("limit"), 10);

    if (
        (event.which !== 44 || text.indexOf(",") !== -1) &&
        (event.which !== 45 || text.indexOf("-") !== -1) &&
        ((event.which < 48 || event.which > 57) && event.which !== 0 && event.which !== 8)
    ) {
        event.preventDefault();
    }

    if (event.which === 44 && text.indexOf(",") === -1) {
        setTimeout(function () {
            const currentValue = String($input.val() || "");
            const commaIndex = currentValue.indexOf(",");

            if (commaIndex !== -1 && currentValue.substring(commaIndex).length > 3) {
                $input.val(currentValue.substring(0, commaIndex + 3));
            }
        }, 1);
    }

    if (
        Number.isInteger(limit) &&
        text.indexOf(",") !== -1 &&
        text.substring(text.indexOf(",")).length > limit &&
        event.which !== 0 &&
        event.which !== 8 &&
        this.selectionStart >= text.length - limit
    ) {
        event.preventDefault();
    }
});

$(document).on("keydown", ".focus-on-enter", function (event) {
    if (event.key !== "Enter") {
        return;
    }

    event.preventDefault();

    const $inputs = $(".focus-on-enter:visible:enabled");
    const index = $inputs.index(this);

    if (index > -1 && index < $inputs.length - 1) {
        $inputs.eq(index + 1).trigger("focus");
    }
});

$(document).on("keydown", "textarea.sendOnEnter", function (event) {
    if (event.key !== "Enter" || event.shiftKey) {
        return;
    }

    event.preventDefault();

    const form = this.closest("form");

    if (!form) {
        return;
    }

    if ($(form).hasClass("form-validate") && $(form).data("validator")) {
        $(form).trigger("submit");
        return;
    }

    form.submit();
});

$(document).on("change", "[name=pessoa]", function () {
    const $form = $(this).closest("form");

    if (!$form.find(".pes").length) {
        return;
    }

    const pessoa = normalizePessoaType($(this).val());
    syncPessoaLabels($form, pessoa);

    const $documento = $form.find("[name=documento]").first();
    if ($documento.length) {
        syncPessoaDocumentoMask($documento, pessoa);
        syncPessoaDocumentoRules($documento, $form.data("validator") || null, pessoa);
        syncPessoaCnpjButton($form.find("#searchCNPJ").first(), $documento, pessoa);
    }

    const isFisica = pessoa === "F";
    const $razao = $form.find("input[name=razao]").first();
    const $nome = $form.find("input[name=nome]").first();
    const $rgIe = $form.find("input[name=rg_ie]").first();

    if (isFisica) {
        if ($razao.length) {
            $razao.attr("placeholder", "Nome Completo");
        }
        if ($nome.length) {
            $nome.attr("placeholder", "Apelido");
        }
        if ($rgIe.length) {
            $rgIe.attr("placeholder", "RG");
        }
    } else {
        if ($razao.length) {
            $razao.attr("placeholder", "Razão Social");
        }
        if ($nome.length) {
            $nome.attr("placeholder", "Nome Fantasia");
        }
        if ($rgIe.length) {
            $rgIe.attr("placeholder", "Inscrição Estadual");
        }
    }
});

// =========================
// MENSAGEM DE ENVIANDO NO BOTÃO
// =========================
function loadingButton(element, active = false) {

    const $button = resolveLoadingButton(element);

    if (!$button.length) {
        return;
    }

    if ($button.data('original-html') === undefined) {
        $button.data('original-html', $button.is('input') ? $button.val() : $button.html());
    }

    const sendingText = $button.data('sending')
        ? $button.data('sending')
        : ($button.is('input') ? $button.val() : String($button.text() || '').trim());

    if (active) {
        const originalHtml = $button.data('original-html') ?? sendingText;

        if ($button.is('input')) {
            $button.val(originalHtml);
        } else {
            $button.html(originalHtml);
        }

        $button.removeAttr('disabled');
    } else {
        $button.prop('disabled', true);
        setButtonLabel($button, sendingText, true);
    }

    // HABILITAR BOTÃO DE FECHAR MODAL CASO EXISTA
}

function resolveLoadingButton(element) {
    let $buttons = element ? $(element) : $('button[type="submit"], input[type="submit"]');

    if (!$buttons.length) {
        return $();
    }

    if ($buttons.length === 1) {
        return $buttons.first();
    }

    const activeElement = document.activeElement;

    if (activeElement) {
        const $activeButton = $buttons.filter(function () {
            return this === activeElement;
        }).first();

        if ($activeButton.length) {
            return $activeButton;
        }
    }

    const $focusedButton = $buttons.filter(':focus').first();

    if ($focusedButton.length) {
        return $focusedButton;
    }

    return $buttons.filter(':enabled:visible').first().length
        ? $buttons.filter(':enabled:visible').first()
        : $buttons.first();
}

function setButtonLabel($button, content, withSpinner) {
    const spinner = withSpinner ? ' <span class="btn-spinner" aria-hidden="true"></span>' : '';

    if ($button.is('input[type="submit"], input[type="button"]')) {
        $button.val(content);
        return;
    }

    $button.html(content + spinner);
}

// =========================
// JANELA DE CONFIRMAÇÃO DE SAÍDA DO SISTEMA
// =========================
function Logout(route) {

    // PEGAR O TEMA
    const theme = $("body").attr("data-layout-color");
    const layout = $("body").attr("data-layout");

    const type = (layout == "material" ? "dark" : "blue");

    $.confirm({
        title: 'Confirmação',
        content: 'Deseja realmente sair do sistema?',
        // autoClose: 'cancel|8000',
        theme : 'modern ' + theme, //  'supervan', 'material', 'bootstrap'
        backgroundDismiss: true,
        icon : 'fa-solid fa-arrow-right-from-bracket',
        type : type, // red, green, orange, blue, purple, dark
        typeAnimated : true,
        animateFromElement: false,
        buttons: {
            confirm: {
                text: 'Sim',
                btnClass: 'btn-' + type,
                action: function () {
                    location.href = route;
                }
            },
            cancel: {
                text: 'Não',
            }
        }
    });
}

$(document).on('click', '[data-logoff]', function (e) {
    e.preventDefault();
    const route = $(this).data("logoff");
    Logout(route);
});

// =========================
// HORIZONTAL MENU: toggle de ícones
// =========================
(function () {
    const STORAGE_KEY = 'horizontal-menu-hide-icons';
    const btn = document.getElementById('btn-menu-icons-toggle');
    const menu = document.querySelector('.app-horizontal-menu');

    if (!btn || !menu) return;

    const apply = (hide) => {
        menu.classList.toggle('hide-icons', hide);
        btn.title = hide ? 'Mostrar ícones do menu' : 'Ocultar ícones do menu';
    };

    apply(localStorage.getItem(STORAGE_KEY) === 'true');

    btn.addEventListener('click', function () {
        const willHide = !menu.classList.contains('hide-icons');
        localStorage.setItem(STORAGE_KEY, String(willHide));
        apply(willHide);
    });
})();
