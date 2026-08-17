$(document).ready(function () {

    if ($(".datatable-only-searching").is(":visible")) {

        // Datatable padrão
        $(".datatable-only-searching").DataTable({
            paging: false,
            paging: false,
            info: false,
            ordering: false
        });
    }

    if ($("#table-users").is(":visible")) {

        // Datatable padrão
        $("#table-users").DataTable({
            // paging: false,
            // language: {
            //     url: app.base + "/public/assets/dist/datatable/lang/Portuguese-Brasil.json",
            // },
            // columnDefs : [
            //     {  targets: [4, 5, 6, 7], type: "currency-br" },
            // ],
        });
    }

    if ($("#table-itens").is(":visible")) {

        // Datatable padrão
        $("#table-itens").DataTable({
            paging: false,
            // language: {
            //     url: app.base + "/public/assets/dist/datatable/lang/Portuguese-Brasil.json",
            // },
            columnDefs : [
                {  targets: [4, 5, 6, 7], type: "currency-br" },
            ],
        });
    }

    if ($(".datatable-cadastros").is(":visible")) {

        // Datatable padrão
        $(".datatable-cadastros").DataTable({
            searching: false,
            paging: false,
            info: false,
        });
    }

    if ($("#table-uploads").is(":visible")) {

        // Datatable padrão
        $("#table-uploads").DataTable({
            // paging: false,
            // language: {
            //     url: app.base + "/public/assets/dist/datatable/lang/Portuguese-Brasil.json",
            // },
            order: [
                [2, "desc"],
            ],
            columnDefs : [
                {  targets: [2], type: "datetime-br" },
            ],
        });
    }

    if ($("#table-extintores").is(":visible")) {

        // Datatable padrão
        $("#table-extintores").DataTable({
            // paging: false,
            // language: {
            //     url: app.base + "/public/assets/dist/datatable/lang/Portuguese-Brasil.json",
            // },
            order: [
                [3, "asc"],
            ],
            columnDefs : [
                {  targets: [3], type: "date-br" },
            ],
        });
    }

    if ($("#table-servicos").is(":visible")) {

        // Datatable padrão
        $("#table-servicos").DataTable({
            paging: false,
            order: [],
            columnDefs : [
                {  targets: [2], type: "currency-br" },
            ],
        });
    }

    // CONTAR PRODUTOS SELECIONADOS
    function countProdutos() {
        let selectedCount = $('#table-produtos-relacionados').DataTable()
            .rows() // Pega todas as linhas (não apenas as visíveis)
            .nodes() // Obtém os elementos DOM de cada linha
            .to$() // Converte para jQuery
            .find(".form-check-input:checked") // Filtra os checkboxes marcados
            .length; // Conta os checkboxes marcados

        if (selectedCount > 0) {
            $("#update-produtos").prop("disabled", false);
            $(".checkeds").text(selectedCount + (selectedCount > 1 ? " produtos selecionados" : " produto selecionado"));
        } else {
            $("#update-produtos").prop("disabled", true);
            $(".checkeds").text("Nenhum produto selecionado");
        }
    }

    // Evento para capturar cliques nos checkboxes
    $(document).on("click", "#table-produtos-relacionados tbody .form-check-input", function (e) {
        e.stopPropagation();
        $(this).closest("tr").toggleClass("selected", $(this).prop("checked"));
        countProdutos();
    });

    // Evento para o "Selecionar Todos"
    $(document).on("click", "#checkAllProdutos", function () {
        let isChecked = $(this).prop("checked");

        // Marca/desmarca todos os checkboxes, incluindo os que estão fora da página atual
        $('#table-produtos-relacionados').DataTable()
            .rows()
            .nodes()
            .to$()
            .find(".form-check-input")
            .prop("checked", isChecked)
            .closest("tr").toggleClass("selected", isChecked);

        countProdutos(); // Atualiza a contagem
    });


    // CADASTRO DE CLIENTES
    $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        if ($("#table-empresas-relacionadas").is(":visible") && !$.fn.DataTable.isDataTable("#table-empresas-relacionadas")) {
            $("#table-empresas-relacionadas").DataTable({
                paging: false,
                order: [[0, "asc"]],
            });
        }
    });

    // ATUALIZAR VALORES DOS PRODUTOS
    $(document).on('click', '.table-servicos-valores .valor-cell', function() {

        // pegar a cela
        const $cell = $(this);

        // evita abrir segunda vez
        if ($cell.find('input').length) return;

        // valor atual armazenado no data-valor (já no formato "2,50" se você usou acima)
        const valorAtual = String($cell.data('valor') || '').trim();

        // 🔹 Esconde e remove tooltip completamente antes de editar (versão segura)
        try {
            if ($cell.data('bs.tooltip')) {
                $cell.tooltip('hide');
                // espera um pouquinho para o fade-out terminar antes do dispose
                setTimeout(() => {
                    try { $cell.tooltip('dispose'); } catch (e) {}
                    removerTooltipsFlutuantes();
                }, 200);
            } else {
                removerTooltipsFlutuantes();
            }
        } catch (e) {
            removerTooltipsFlutuantes();
        }

        // cria input
        const $input = $('<input type="tel" class="form-control form-control-sm text-end input-money">')
            .val(valorAtual)
            .css({
                'max-width': '200px',
                'display': 'inline-block',
                'text-align': 'right',
                'padding-right': '6px'
            });

        $cell.html($input);
        $input.focus().select();

        // aplica máscara (se estiver usando plugin .mask)
        if ($.fn.mask) {
            $input.mask("#.##0,00", { reverse: true });
        }

        // função que executa salvar/fechar (compartilhada por blur/enter/escape)
        const finalizarEdicao = function(opcoes) {
            // opcoes: { cancelar:bool }
            opcoes = opcoes || {};
            const cancelar = !!opcoes.cancelar;

            // pega valor digitado (raw)
            let novoValorRaw = $input.val() == null ? '' : $input.val().trim();

            // se cancelar (ESC)
            if (cancelar) {
                $cell.html('R$ ' + formatarMoedaBR(valorAtual));
                // restaura tooltip com leve atraso para evitar conflitos de cálculo do bootstrap
                setTimeout(() => restaurarTooltip($cell), 50);
                return;
            }

            // se vazio, volta ao valor antigo
            if (novoValorRaw === '') {
                $cell.html('R$ ' + formatarMoedaBR(valorAtual));
                setTimeout(() => restaurarTooltip($cell), 50);
                return;
            }

            // formata o valor já para exibição / envio
            const novoFormatado = formatarMoedaBR(novoValorRaw);

            // se não mudou, apenas restaura
            if (novoFormatado === formatarMoedaBR(valorAtual)) {
                $cell.html('R$ ' + novoFormatado);
                setTimeout(() => restaurarTooltip($cell), 50);
                return;
            }

            // caso mudou, faz ajax
            $.ajax({
                url: url_base + '/admin/clientes/servico/update',
                method: 'POST',
                data: {
                    id: $cell.data('id'),
                    valor: novoFormatado // envia já formatado (ou adapte se seu backend espera outro formato)
                },
                dataType: 'json',
                success: function(resp) {
                    if (resp.error == false) {
                        $cell
                            .data('valor', novoFormatado)
                            .html('R$ ' + novoFormatado);

                            // ✅ Atualiza ícone
                            atualizarIconeSemValor($cell, novoFormatado);

                    } else {
                        showToastr("Houve um erro ao atualizar o valor", "danger");
                        $cell.html('R$ ' + formatarMoedaBR(valorAtual));
                    }
                },
                error: function() {
                    showToastr("Houve um erro ao atualizar o valor", "danger");
                    $cell.html('R$ ' + formatarMoedaBR(valorAtual));
                },
                complete: function() {
                    // garante restauração do tooltip mesmo que bootstrap esteja ocupado
                    setTimeout(() => restaurarTooltip($cell), 50);
                }
            });
        };

        // keydown para Enter / ESC
        $input.on('keydown', function(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                finalizarEdicao({ cancelar: true });
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                finalizarEdicao();
                return;
            }
        });

        // blur: usamos pequeno timeout para dar prioridade a eventuais clicks que disparem antes
        $input.on('blur', function(e) {
            // delay curto para evitar conflito com clique que abre outra coisa
            setTimeout(function() {
                // se o input já foi removido por outro fluxo, ignora
                if (!$cell.find('input').length) return;
                finalizarEdicao();
            }, 120);
        });

        // evita que clique dentro do input feche imediatamente por algum handler global
        $input.on('click', function(e) { e.stopPropagation(); });
    });

    // 🔸 Atualiza o ícone de "serviço sem valor" conforme o valor numérico
    function atualizarIconeSemValor($cell, valor) {

        const $linha = $cell.closest('tr');
        let $icone = $linha.find('i.text-danger');

        // Pegar a classe atual do ícone (se existir)
        let classes = $icone.attr('class') || 'text-danger';

        // normaliza o número
        const numero = parseFloat(String(valor).replace(/\./g, '').replace(',', '.')) || 0;

        console.log(numero);

        // se for maior que 0 → esconder o ícone
        if (numero > 0) {
            $icone.hide();
            return;
        }

        // se for 0 → mostrar (ou criar se não existir)
        if ($icone.length === 0) {
            $icone = $('<i/>', {
                class: classes,
                title: 'Serviço sem valor',
                'data-tooltip': 'true'
            });

            // adiciona no primeiro <td>
            $linha.find('td:first').append(' ').append($icone);
        }

        // mostra o ícone
        $icone.show();

        // (re)inicializa tooltip do ícone
        try {
            if ($icone.data('bs.tooltip')) {
                $icone.tooltip('dispose');
            }
            $icone.tooltip({ trigger: 'hover', container: 'body' });
        } catch (e) { /* ignora se tooltip não disponível */ }
    }


}); // jquery ends


