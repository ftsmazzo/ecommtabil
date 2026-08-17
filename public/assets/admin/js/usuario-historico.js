"use strict";

$(document).ready(function () {
    const $table = $("#table-historico");
    const $exportButton = $("#exportXls");
    const $startDate = $("#filter-data-inicial");
    const $endDate = $("#filter-data-final");
    const $clearDate = $("#filter-data-limpar");
    const $counter = $("#history-counter .history-counter-value");
    let table = null;

    if (!$table.length) {
        return;
    }

    function parseBrDateTimeToLocal(value) {
        const text = String(value || "").trim();
        const parts = text.split(" ");
        const dateParts = (parts[0] || "").split("/");
        const timeParts = (parts[1] || "00:00").split(":");

        if (dateParts.length !== 3) {
            return "";
        }

        const hour = timeParts[0] || "00";
        const minute = timeParts[1] || "00";

        return `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}T${hour}:${minute}`;
    }

    if ($.fn.dataTable && $.fn.dataTable.ext && $.fn.dataTable.ext.search) {
        $.fn.dataTable.ext.search.push(function (settings, data) {
            if (!settings.nTable || settings.nTable.id !== "table-historico") {
                return true;
            }

            const start = $startDate.val();
            const end = $endDate.val();
            const current = parseBrDateTimeToLocal(data[0]);

            if (!current) {
                return true;
            }

            if (start && current < start) {
                return false;
            }

            if (end && current > end) {
                return false;
            }

            return true;
        });
    }

    if ($.fn.DataTable && !$.fn.DataTable.isDataTable($table[0])) {
        table = $table.DataTable(window.buildDataTableOptions({
            dom: window.DataTableLayouts?.swapped,
            pageLength: 25,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "Todos"]],
            order: [[0, "desc"]],
            columnDefs: [
                {
                    targets: 0,
                    type: "datetime-br"
                }
            ]
        }));

        $table.closest(".dataTables_wrapper").addClass("table-datatable-swapped");
    } else if ($.fn.DataTable) {
        table = $table.DataTable();
    }

    if (table) {
        const updateCounter = function () {
            const total = table.rows({ filter: "applied" }).count();
            if ($counter.length) {
                $counter.text(total);
            }
        };

        $startDate.add($endDate).on("change", function () {
            table.draw();
        });

        $clearDate.on("click", function () {
            $startDate.val("");
            $endDate.val("");
            table.draw();
        });

        $table.on("draw.dt", function () {
            updateCounter();
        });

        updateCounter();
    }

    if (!$exportButton.length || !$.fn.table2excel) {
        return;
    }

    $exportButton.on("click", function () {
        const $button = $(this);

        loadingButton($button);

        window.setTimeout(function () {
            $table.table2excel({
                name: "Historico",
                filename: "historico-login",
                exclude_inputs: false
            });

            window.setTimeout(function () {
                loadingButton($button, true);
            }, 800);
        }, 50);
    });
});
