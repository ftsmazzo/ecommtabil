$(document).ready(function () {

    // Configuações Padrão DataTable
    if ($.fn.DataTable) {

        $.extend( true, $.fn.DataTable.defaults, {
            pageLength: 25,
            lengthMenu: [[25, 50, 100, -1], [25, 50, 100, "Todos"]],
            language: {
                url: app.base + "/public/assets/dist/datatable/lang/Portuguese-Brasil.json",
            }
        });

        // Datatable padrão
        if ($('.dataTable').is(":visible")) {
            $(".dataTable").DataTable();
        }

        if ($(".datatable-no-paging").is(":visible")) {
            $(".datatable-no-paging").DataTable({
                paging: false
            });
        }

        if ($(".datatable-no-paging-and-searching").is(":visible")) {
            $(".datatable-no-paging-and-searching").DataTable({
                paging: false,
                searching: false,
            });
        }

        if ($(".dataTable-no-order-no-paging").is(":visible")) {
            $(".dataTable-no-order-no-paging").DataTable({
                paging: false,
                ordering: false,
            });
        }

        if ($(".datatable-empty-order").is(":visible")) {
            $(".datatable-empty-order").DataTable({
                // paging: false,
                // ordering: false,
                order: []
            });
        }
    }

    // Selecionar ou desmarcar todos os checkboxes visíveis
    $('#checkAll').on('click', function () {
        const isChecked = $(this).prop('checked');
        const table = $(this).closest(".table-marked");

        // Alterar os checkboxes das linhas visíveis
        table.find('tbody tr:visible .form-check-input')
            .prop('checked', isChecked) // Usa o estado do botão "checkAll"
            .trigger('change'); // Acionar evento para atualizar a linha

        // Atualizar todas as linhas visíveis
        table.find('tbody tr:visible').toggleClass('selected', isChecked);

        enableActions();
    });

    $(".table-marked tbody tr").on("click", function(e) {
        // Evitar que o clique no checkbox dispare o evento da linha
        if (!$(e.target).is(".form-check-input, .form-check-label")) {
            // Alternar o checkbox
            const checkbox = $(this).find(".form-check-input");
            checkbox.prop("checked", !checkbox.prop("checked"));

            // Enviar requisição AJAX
            const checked = checkbox.prop("checked");
            const value = checkbox.val();

            // Alternar a classe da linha
            $(this).toggleClass("selected", checked);
        }

        enableActions();
    });

}); // jquery ends
