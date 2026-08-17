$(document).ready(function () {

    url_admin = app.base + "/admin";
    url_cliente = app.base + "/cliente";

    // Editar o registro ao clicar 2 vezes sobre a linha da tabela
    $(".table-list tbody tr").on("dblclick", function() {
        id = $(this).data("id");
        window.location = window.location.href + '/editar/' + id;
    });

    // TABELAS COM CHECKBOXES
    $("input#check-all-rows").click(function(){
        var checked_status = this.checked;
        var table = $(this).parent().parent().parent().parent().parent();

        console.log(table)

        table.find("tbody input[type=checkbox]").each(function(){
            this.checked = checked_status;

            if (checked_status===true) {
                $(this).parent().parent().parent().addClass("marked");
            } else {
                $(this).parent().parent().parent().removeClass("marked");
            }
        });
        enableActions();
    });

    // ABRIR EDIÇÃO AO DAR UM DUPLO CLIQUE NA LINHA
    $(".table-marked tbody tr.editable").not(".dropdown-toggle").not(":checkbox").on("dblclick", function(e) {

        e.preventDefault();

        var id = $(this).data("id");

        $("#up__parcela__id").val(id);

        var myModal = new bootstrap.Modal(document.getElementById('editarParcela'),{
            keyboard: false,
            backdrop: 'static'
        });

        myModal.show();

    });

    // CLICAR NA TABELA DE AÇÕES
    $(".table-marked tbody input[type=checkbox]").on("change", function(e) {

        enableActions();

        const line = $(this).parent().parent().parent();
        // const checkbox = line.find("input[type=checkbox]");

        var marked = line.hasClass("marked");

        if (marked) {

            line.removeClass("marked");

        } else {

            line.addClass("marked");
        }
    });

    // EXPORTAR AUDITORIA
    $(document).on('click', '#exportAuditoriaXls', function() {

        var $button = $(this); // Referência ao botão
        var originalText = $button.html(); // Salva o texto original do botão

        // Adiciona o ícone e desabilita o botão
        $button.prop('disabled', true).html(originalText + ' <i class="fa fa-rotate fa-spin"></i>');

        // Dados a serem enviados
        var data = $("#form-auditoria").serialize();

        $.ajax({
            url: url_admin + '/inventario/auditoria/exportar/xls',
            type: 'POST',
            data: data,
            xhrFields: {
                responseType: 'blob'  // Permite receber o arquivo binário
            },
            success: function(response) {
                // Criar um link para baixar o arquivo gerado
                var blob = response;
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'Autitoria Semanal.xlsx'; // Nome do arquivo
                link.click(); // Inicia o download
            },
            error: function(xhr, status, error) {
                showToastr('Ocorreu um erro ao gerar o arquivo.', 'danger');
            },
            complete: function() {
                // Remove o ícone e reabilita o botão após o processo
                $button.prop('disabled', false).html(originalText);
            }
        });
    });

    $(document).on('click', '#exportAuditoriaPdf', function() {

        var $button = $(this); // Referência ao botão
        var originalText = $button.html(); // Salva o texto original do botão

        // Adiciona o ícone e desabilita o botão
        $button.prop('disabled', true).html(originalText + ' <i class="fa fa-rotate fa-spin"></i>');

        // Dados a serem enviados
        var data = $("#form-auditoria").serialize();

        $.ajax({
            url: url_admin + '/inventario/auditoria/exportar/pdf',
            type: 'POST',
            data: data,
            xhrFields: {
                responseType: 'blob'  // Permite receber o arquivo binário
            },
            success: function(response) {
                // Criar um link para baixar o arquivo gerado
                var blob = response;
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'Autitoria Semanal.pdf'; // Nome do arquivo
                link.click(); // Inicia o download
            },
            error: function(xhr, status, error) {
                showToastr('Ocorreu um erro ao gerar o arquivo.', 'danger');
            },
            complete: function() {
                // Remove o ícone e reabilita o botão após o processo
                $button.prop('disabled', false).html(originalText);
            }
        });
    });
});

function enableActions(table) {

    if (!table)
        table = ".table-parcelas";

    var checkeds = $(table + " tbody tr input[type=checkbox]:checked");

    var n = checkeds.length;

    if (n > 0) {

        // Encontrar Valor
        var total = 0;

        $(checkeds).each(function() {

            tr = $(this).parent().parent().parent();

            total += parseFloat(tr.attr("data-valor"));

        });

        total = total.toFixed(2);

        $("#actions").fadeIn("fast");
        $("#actions .ncheck").html(n);
        $("#actions .vcheck").html(formatReal(total));

    } else {

        $("#actions").fadeOut("fast");
    }
}

// JANELA DE CONFIRMAÇÃO ANTES DE EXCLUIR UM REGISTRO
function DeleteInventario(route, id, qtde) {

    const url = window.location.href + '/'
        + (route ? route : 'delete')
        + (id ? '/' + id : '');

    // PEGAR O TEMA
    const theme = $("body").attr("data-layout-color");

    const msg =  qtde > 0
        ? 'Deseja realmente excluir os ' + qtde + ' registros?'
        : 'Deseja realmente excluir este registro?';

    $.confirm({
        title: 'Confirmação',
        content: msg,
        // autoClose: 'cancel|8000',
        theme : 'modern ' + theme,
        backgroundDismiss: true,
        icon : 'fa-solid fa-circle-xmark',
        type : 'coral',
        typeAnimated : true,
        animateFromElement: false,
        buttons: {
            confirm: {
                text: 'Sim',
                btnClass: 'btn-coral',
                action: function () {
                    location.href = url;
                }
            },
            cancel: {
                text: 'Não',
            }
        }
    });
}

// function baixarParcela(id) {

//     // PEGAR O TEMA
//     const theme = $("body").attr("data-layout-color");

//     $.confirm({
//         title: 'Baixar Parcela?',
//         content: '' +
//         '<form id="formBaixarParcela" action="' + app.base + '/parcela/baixar" method="POST">' +
//             '<input type="hidden" name="id">' +
//             '<div class="mb-3 text-start">' +
//                 '<label title="Campo Obrigatório" class="form-label">Valor <span class="required-field">*</span></label>' +
//                 '<div class="input-group">' +
//                     '<span class="input-group-text input-group-addon addon-start">R$</span>' +
//                     '<input type="tel" class="form-control border-start-0 input-money" name="valor" autocomplete="off" placeholder="0,00" id="baixar__parcela__valor" required>' +
//                     '<input type="hidden" id="up__parcela__valor_original" val="">' +
//                 '</div>' +
//             '</div>' +
//             '<div class="mb-3 text-start">' +
//                 '<label title="Campo Obrigatório" class="form-label">Data de Pagamento <span class="required-field">*</span></label>' +
//                 '<div class="input-group">' +
//                     '<input type="date" class="form-control" name="data" autocomplete="off" value="' + ((new Date()).toISOString().split('T')[0]) + '" required>' +
//                 '</div>' +
//             '</div>' +
//         '</form>',
//         theme: 'modern ' + theme, //  'supervan', 'material', 'bootstrap'
//         backgroundDismiss: true,
//         icon: 'uil uil-check-circle',
//         type: 'green', // red, green, orange, blue, purple, dark
//         typeAnimated : true,
//         animateFromElement: false,
//         buttons: {
//             formSubmit: {
//                 text: 'Baixar',
//                 btnClass: 'btn-green',
//                 action: function () {

//                     var name = this.$content.find('[name=valor]').val();
//                     if(!name){
//                         $.alert('O Valor é Obrigatório');
//                         return false;
//                     }

//                     var date = this.$content.find('[name=data]').val();
//                     if(!date){
//                         $.alert('O Data é Obrigatória');
//                         return false;
//                     }

//                     $('#formBaixarParcela').submit();
//                 }
//             },
//             cancel: {
//                 text: 'Cancelar',
//             }
//         },
//         onContentReady: function () {

//             // ATRIBUIR ID
//             this.$content.find('input[name=id]').val(id);
//             // COLOCAR A MÁSCARA DE MOEDA NO CAMPO
//             this.$content.find('input[name=valor]').mask("#.##0,00", {reverse: true});

//             // bind to events
//             var jc = this;
//             this.$content.find('form').on('submit', function (e) {
//                 // if the user submits the form by pressing enter in the field.
//                 e.preventDefault();
//                 // reference the button and click it
//                 jc.$$formSubmit.trigger('click');
//             });
//         }
//     });

// }

// function baixarParcelas() {

//     // PEGAR O TEMA
//     const theme = $("body").attr("data-layout-color");

//     $.confirm({
//         title: 'Baixar Parcelas Selecionadas',
//         content: '' +
//         '<div class="mb-3">' +
//             '<label title="Campo Obrigatório" class="form-label">Data de Pagamento <span class="field-required">*</span></label>' +
//             '<input type="date" class="form-control" name="data" autocomplete="off" value="' + ((new Date()).toISOString().split('T')[0]) + '" required>' +
//         '</div>' +
//         '<div class="">' +
//             '<div class="text-center">' +
//                 '<input class="form-check-input check-input-success me-2" type="checkbox" id="checkDataVencimento" name="data_vencimento" value="1">' +
//                 '<label class="form-check-label cursor-pointer" for="checkDataVencimento">' +
//                     'Usar mesma data do vencimento' +
//                 '</label>' +
//             '</div>' +
//         '</div>',
//         theme: 'modern ' + theme, //  'supervan', 'material', 'bootstrap'
//         backgroundDismiss: true,
//         icon: 'uil uil-check-circle',
//         type: 'green', // red, green, orange, blue, purple, dark
//         typeAnimated : true,
//         animateFromElement: false,
//         buttons: {
//             confirm: {
//                 text: 'Baixar',
//                 btnClass: 'btn-green',
//                 action: function () {

//                     data = this.$content.find("input[name=data]").val();
//                     check = this.$content.find("#checkDataVencimento").is(":checked") ? 1 : 0;

//                     $("#formParcelas").append('<input type="hidden" name="data_parcela" value="'+data+'">');
//                     $("#formParcelas").append('<input type="hidden" name="mesma_data" value="'+check+'">');
//                     $("#formParcelas").append('<input type="hidden" name="act" value="baixar">');
//                     $("#formParcelas").submit();
//                 }
//             },
//             cancel: {
//                 text: 'Cancelar',
//             }
//         },
//         onContentReady: function () {
//             var checkbox = this.$content.find('input[type=checkbox]');
//             $("[for=checkDataVencimento]").on('click', function (e) {
//                 e.preventDefault();
//                 checkbox.prop("checked", !checkbox.prop("checked"));
//             });
//         }
//     });

// }

// function estornarParcelas() {

//     // PEGAR O TEMA
//     const theme = $("body").attr("data-layout-color");

//     $.confirm({
//         title: 'Estornar Parcelas',
//         content: 'Deseja realmente estonar estas parcelas?',
//         theme: 'modern ' + theme, //  'supervan', 'material', 'bootstrap'
//         backgroundDismiss: true,
//         icon: 'uil uil-arrow-circle-up',
//         type: 'orange', // red, green, orange, blue, purple, dark
//         typeAnimated : true,
//         animateFromElement: false,
//         buttons: {
//             confirm: {
//                 text: 'Estornar',
//                 btnClass: 'btn-orange',
//                 action: function () {
//                     $("#formParcelas").append('<input type="hidden" name="act" value="estornar">');
//                     $("#formParcelas").submit();
//                 }
//             },
//             cancel: {
//                 text: 'Cancelar',
//             }
//         },
//         onContentReady: function () {
//             // Adiciona uma classe ao conteúdo durante a inicialização
//             this.$content.addClass('font-size-18');
//         }
//     });

// }

// function cancelarParcela(id) {

//     // PEGAR O TEMA
//     const theme = $("body").attr("data-layout-color");

//     $.confirm({
//         title: 'Cancelar Parcela?',
//         titleClass: 'font-size-23',
//         content: '' +
//         '<form id="formCancelarParcela" action="' + app.base + '/parcela/cancelar" method="POST">'+
//             '<input type="hidden" name="id">'+
//             '<div class="mb-3">'+
//                 '<b>Deseja Realmente Cancelar Esta Parcela?</b>'+
//             '</div>'+
//             '<div>'+
//                 '<label class="form-label mb-2 fw-800">Ação no Pagamento</label>'+
//                 // '<div class="form-check mb-2">'+
//                 //     '<input class="form-check-input bg-main" type="radio" name="acao" value="atualizar" id="cancelAtualizar" checked>'+
//                 //     '<label class="form-check-label cursor-pointer" for="cancelAtualizar">'+
//                 //         '<small>Atualizar valor total da despesa</small>'+
//                 //     '</label>'+
//                 // '</div>'+
//                 '<div class="form-check text-start ps-4">' +
//                     '<input class="form-check-input check-input-danger" type="checkbox" name="acao" value="recalcular" id="cancelRecalcular">' +
//                     '<label class="form-check-label cursor-pointer" for="cancelRecalcular">' +
//                         'Redistribuir diferença nas parcelas abertas' +
//                     '</label>' +
//                 '</div>' +
//             '</div>'+
//         '</form>',
//         theme: 'modern ' + theme, //  'supervan', 'material', 'bootstrap'
//         backgroundDismiss: true,
//         icon: 'uil uil-times-circle',
//         type: 'red', // red, green, orange, blue, purple, dark
//         typeAnimated : true,
//         animateFromElement: false,
//         buttons: {
//             formSubmit: {
//                 text: 'Cancelar',
//                 btnClass: 'btn-red',
//                 action: function () {
//                     $('#formCancelarParcela').submit();
//                 }
//             },
//             cancel: {
//                 text: 'Cancelar',
//             }
//         },
//         onContentReady: function () {
//             var checkbox = this.$content.find('input[type=checkbox]');
//             $("[for=cancelRecalcular]").on('click', function (e) {
//                 e.preventDefault();
//                 checkbox.prop("checked", !checkbox.prop("checked"));
//             });

//             // ATRIBUIR ID
//             this.$content.find('input[name=id]').val(id);

//             // bind to events
//             var jc = this;
//             this.$content.find('form').on('submit', function (e) {
//                 // if the user submits the form by pressing enter in the field.
//                 e.preventDefault();
//                 // reference the button and click it
//                 jc.$$formSubmit.trigger('click');
//             });

//         }
//     });

// }

// function cancelarParcelas() {

//     // PEGAR O TEMA
//     const theme = $("body").attr("data-layout-color");

//     $.confirm({
//         title: 'Cancelar Parcelas Selecionadas?',
//         titleClass: 'font-size-23',
//         content: '' +
//         '<div>' +
//             '<label class="form-label mb-2 fw-800">Ação no Pagamento</label>' +
//             // '<div class="form-check mb-2">' +
//                 // '<input class="form-check-input bg-main" type="radio" name="acao" value="atualizar" id="cancelAtualizar" checked>' +
//                 // '<label class="form-check-label cursor-pointer" for="cancelAtualizar">' +
//                     // '<small>Atualizar valor total da despesa</small>' +
//                 // '</label>' +
//             // '</div>' +
//             '<div class="form-check text-start ps-4">' +
//                 '<input class="form-check-input check-input-danger" type="checkbox" name="acao" value="recalcular" id="cancelRecalcular">' +
//                 '<label class="form-check-label cursor-pointer" for="cancelRecalcular">' +
//                     'Redistribuir diferença nas parcelas abertas' +
//                 '</label>' +
//             '</div>' +
//         '</div>',
//         theme: 'modern ' + theme, //  'supervan', 'material', 'bootstrap'
//         backgroundDismiss: true,
//         icon: 'uil uil-times-circle',
//         type: 'red', // red, green, orange, blue, purple, dark
//         typeAnimated : true,
//         animateFromElement: false,
//         buttons: {
//             confirm: {
//                 text: 'Cancelar',
//                 btnClass: 'btn-red',
//                 action: function () {

//                     const check = this.$content.find("#cancelRecalcular").is(":checked") ? 1 : 0;
//                     $("#formParcelas").append('<input type="hidden" name="recalcular_valor" value="'+check+'">');
//                     $("#formParcelas").append('<input type="hidden" name="act" value="cancelar">');
//                     $("#formParcelas").submit();
//                 }
//             },
//             cancel: {
//                 text: 'Cancelar',
//             }
//         },
//         onContentReady: function () {
//             var checkbox = this.$content.find('input[type=checkbox]');
//             $("[for=cancelRecalcular]").on('click', function (e) {
//                 e.preventDefault();
//                 checkbox.prop("checked", !checkbox.prop("checked"));
//             });
//         }
//     });

// }

// function excluirParcela(id) {

//     // PEGAR O TEMA
//     const theme = $("body").attr("data-layout-color");

//     $.confirm({
//         title: 'Excluir Parcela?',
//         content: '' +
//         '<form id="formExcluirParcela" action="' + app.base + '/parcela/excluir" method="POST">' +
//             '<input type="hidden" name="id">' +
//             '<div class="mb-3">' +
//                 'Deseja Realmente Excluir Esta Parcela?' +
//             '</div>' +
//         '</form>',
//         theme: 'modern ' + theme, //  'supervan', 'material', 'bootstrap'
//         backgroundDismiss: true,
//         icon: 'uil uil-trash-alt',
//         type: 'red', // red, green, orange, blue, purple, dark
//         typeAnimated : true,
//         animateFromElement: false,
//         buttons: {
//             formSubmit: {
//                 text: 'Excluir',
//                 btnClass: 'btn-red',
//                 action: function () {
//                     $('#formExcluirParcela').submit();
//                 }
//             },
//             cancel: {
//                 text: 'Cancelar',
//             }
//         },
//         onContentReady: function () {

//             // ATRIBUIR ID
//             this.$content.find('input[name=id]').val(id);
//         }
//     });

// }
