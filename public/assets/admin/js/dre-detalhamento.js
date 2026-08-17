$(document).ready(function () {

    // CHAMAR RESULTADOS
    getResults($("#form-dre-detalhamento").serialize());

    $("#form-dre-detalhamento").submit(function(e) {
        e.preventDefault();
        $("#btn-send").addClass('disabled').prop('disabled', true).html('<i class="fa-solid fa-arrows-rotate fa-spin"></i> Carregando...');
        getResults($(this).serialize());
        return false;
    });

    // ============================
    // NAO DEIXAR SELECIONAR SEM SER SEQUENCIAL
    // ============================
    let ultimaSelecao = [];

    $('#meses').on('changed.bs.select', function () {
        let selecionados = $(this).val();

        if (!selecionados || selecionados.length <= 1) {
            ultimaSelecao = selecionados;
            return;
        }

        // Converte para número e ordena
        let meses = selecionados.map(Number).sort((a, b) => a - b);

        let sequencial = true;
        for (let i = 1; i < meses.length; i++) {
            if (meses[i] !== meses[i - 1] + 1) {
                sequencial = false;
                break;
            }
        }

        if (!sequencial) {
            alert('Os meses devem ser selecionados de forma sequencial.');
            $('#meses').selectpicker('val', ultimaSelecao);
        } else {
            ultimaSelecao = selecionados;
        }
    });
});


// 🔹 Função auxiliar para mostrar mensagem de carregamento
function showLoadingMessage(containerId, message) {
    const theme = $('body').attr("data-layout-color");
    const el = document.getElementById(containerId);
    if (el) {
        el.innerHTML = `
            <div class="fa-fade p-5" style="
                display:flex;
                justify-content:center;
                align-items:center;
                height:100%;
                color:${theme === 'dark' ? '#ccc' : '#555'};
                font-size:20px;
                font-weight:500;
            ">
                <i class="fa-solid fa-arrows-rotate fa-spin me-2"></i>
                ${message}
            </div>
        `;
    }
}

function getResults(data) {

    showLoadingMessage('list', 'Carregando dados...');

    $.ajax({
        type: "post",
        url: url_base + "/admin/dre/detalhamento/gerar",
        data: data ?? null,
        success: function (response) {
            // console.log(response);
            $('#list').html(response);
            $("#btn-send").removeClass('disabled').prop('disabled', false).html('<i class="uil uil-message"></i> Enviar');

            // // EXPANDIR / RECOLHER TABELA
            // $(".dre .expand-icon").click(function () {
            //     const $icon = $(this);
            //     const $row = $icon.closest("tr");
            //     const expand = !$icon.hasClass("open");
            //     let $next = $row.next();

            //     while ($next.length && !$next.hasClass("dre")) {
            //         if ($next.hasClass("tipo")) {
            //             expand ? $next.show() : $next.hide();

            //             // Fecha tudo se colapsar
            //             if (!expand) {
            //                 $next.find(".expand-icon").removeClass("open");
            //                 let $grupo = $next.next();
            //                 while ($grupo.length && $grupo.hasClass("grupo")) {
            //                     $grupo.hide();
            //                     $grupo = $grupo.next();
            //                 }
            //             }
            //         }
            //         $next = $next.next();
            //     }

            //     $icon.toggleClass("open");
            // });

            // $(".tipo .expand-icon").click(function () {
            //     const $icon = $(this);
            //     const $row = $icon.closest("tr");
            //     const expand = !$icon.hasClass("open");
            //     let $next = $row.next();

            //     while ($next.length && $next.hasClass("grupo")) {
            //         expand ? $next.show() : $next.hide();
            //         $next = $next.next();
            //     }

            //     $icon.toggleClass("open");
            // });
        }
    });
}

// Coloque isso 1 vez (fora do success do ajax)
$(document).off("click", ".dre .expand-icon").on("click", ".dre .expand-icon", function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $icon = $(this);
    const $row = $icon.closest("tr");
    const expand = !$icon.hasClass("open");
    let $next = $row.next();

    while ($next.length && !$next.hasClass("dre")) {
        if ($next.hasClass("tipo")) {
            expand ? $next.show() : $next.hide();

            if (!expand) {
                // colapsa tudo embaixo
                $next.find(".expand-icon").removeClass("open");
                let $grupo = $next.next();
                while ($grupo.length && $grupo.hasClass("grupo")) {
                    $grupo.hide();
                    $grupo = $grupo.next();
                }
            }
        }
        $next = $next.next();
    }

    $icon.toggleClass("open", expand);
});

$(document).off("click", ".tipo .expand-icon").on("click", ".tipo .expand-icon", function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $icon = $(this);
    const $row = $icon.closest("tr");
    const expand = !$icon.hasClass("open");
    let $next = $row.next();

    while ($next.length && $next.hasClass("grupo")) {
        expand ? $next.show() : $next.hide();
        $next = $next.next();
    }

    $icon.toggleClass("open", expand);
});

$(document).off("click", "#toggle-all").on("click", "#toggle-all", function (e) {
    e.preventDefault();
    e.stopPropagation();

    const $btn = $(this);

    // Se existir algum "tipo" escondido, então ainda não está tudo aberto => vamos abrir tudo
    const shouldExpand = $("#table-dre tbody tr.tipo:hidden").length > 0;

    if (shouldExpand) {
        // abre tudo (tipos e grupos)
        $("#table-dre tbody tr.tipo, #table-dre tbody tr.grupo").show();

        // marca ícones como abertos
        $("#table-dre tbody tr.dre .expand-icon, #table-dre tbody tr.tipo .expand-icon")
            .addClass("open")
            .attr("aria-expanded", "true");

        $btn.addClass("open").attr("aria-expanded", "true");

    } else {

        // fecha tudo (tipos e grupos)
        $("#table-dre tbody tr.tipo, #table-dre tbody tr.grupo").hide();

        // reseta ícones
        $("#table-dre tbody tr.dre .expand-icon, #table-dre tbody tr.tipo .expand-icon")
            .removeClass("open")
            .attr("aria-expanded", "false");

        $btn.removeClass("open").attr("aria-expanded", "false");
    }
});
