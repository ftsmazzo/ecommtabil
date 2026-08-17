$(document).ready(function () {

    const url_admin = app.base + "/admin";

    $("#p-check-all").change(function(){
        const stat = this.checked;
        $("#permissoes").find("input[type=checkbox]").prop("checked", stat);
    });

    $("#p-check-all-est").change(function(){
        const stat = this.checked;
        $("#estabelecimentos").find("input[type=checkbox]").prop("checked", stat);
    });

    // MARCAR ESTABELECIMENTOS
    $(".check-franqueado").on("change", function() {
        const isChecked = this.checked;
        $(this).closest(".list-estabelecimentos").find(".list input[type=checkbox]").prop("checked", isChecked);
    });

    // SETAR PERFIL
    $("[name=id_perfil]").on("change", function() {
        const id_perfil = $(this).val();

        // Seleciona todos os checkboxes de permissão uma única vez para otimizar.
        const allPermissionsCheckboxes = $("#permissoes input[type=checkbox]");

        // 1. Primeiro, desmarca todas as permissões.
        // Isso garante que, ao trocar de perfil, as permissões antigas sejam limpas
        // antes de marcar as novas.
        allPermissionsCheckboxes.prop("checked", false);

        // Se um perfil válido foi selecionado...
        if (id_perfil) {
            // 2. Faz a requisição para obter as permissões do perfil.
            $.get(url_admin + "/perfis/" + id_perfil + "/permissoes", function(permissionIds) {
                // 'permissionIds' é o array retornado pela sua API, ex: [1, 3, 25]

                // 3. Itera sobre cada checkbox de permissão.
                allPermissionsCheckboxes.each(function() {
                    // 'this' aqui é o elemento <input> do checkbox atual.
                    const checkbox = $(this);

                    // Pega o valor do checkbox (ex: "1", "2", "3"...)
                    // Usamos parseInt para garantir que estamos comparando números com números.
                    const checkboxValue = parseInt(checkbox.val(), 10);

                    // 4. Verifica se o valor do checkbox está no array de permissões.
                    if (permissionIds.includes(checkboxValue)) {
                        // Se estiver, marca o checkbox.
                        checkbox.prop("checked", true);
                    }
                });
            });
        }
    });


    if ($("#form-user-novo").is(":visible")) {

        $("#form-user-novo").validate({
            rules: {
                login: {
                    required: true,
                    remote: url_admin + "/estabelecimentos/usuarios/find"
                }
            },
            messages: {
                login: {
                    remote: "Este login já está sendo usado. Por favor use outro."
                }
            },
            submitHandler: function(form) {
                loadingButton("#form-user-novo button[type=submit]");
                form.submit();
            }
        });
    }

    if ($("#form-user-editar").is(":visible")) {

        $("#form-user-editar").validate({
            rules: {
                login: {
                    required: true,
                    remote: url_admin + "/estabelecimentos/usuarios/find"
                }
            },
            messages: {
                login: {
                    remote: "Este login já está sendo usado. Por favor use outro."
                }
            },
            submitHandler: function(form) {
                loadingButton("#form-user-editar button[type=submit]");
                form.submit();
            }
        });
    }

    if ($("#formPass").is(":visible")) {

        $("#formPass").validate({
            rules: {
                old: {
                    required: true,
                    remote: url_admin + "/pass/find"
                },
                senha: {
                    required: true
                },
                confirm: {
                    required: true,
                    equalTo: "#senha",
                },
            },
            messages: {
                old: {
                    remote: "Senha Atual Incorreta."
                },
                confirm: {
                    equalTo: "As senhas não são iguais."
                }
            },
            submitHandler: function(form) {
                loadingButton("#formPass button[type=submit]");
                form.submit();
            }
        });

    }

    // PLANO DE CONTAS
    $('#modal-grupo').on('show.bs.modal', function (event) {

        var button = $(event.relatedTarget);
        var action = button.data('action');

        const body = $(this).find(".modal-body");

        if (action=="insert") {

            $(this).find(".modal-header>.modal-title").html("Inserir Novo Grupo");
            body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $("#form-grupo").attr("action", url_admin + "/grupos/insert");

        } else if (action=="update") {

            var id = button.data('id');

            $(this).find(".modal-header>.modal-title").html("Editar Grupo");
            body.find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("...");
            $("#form-grupo").attr("action", url_admin + "/grupos/update");
            $("#input-id").val(id);

            $.ajax({
                url: url_admin + "/grupos/find",
                type: "post",
                data : { id },
                dataType: "json",
                success: function(data) {

                    if (data.error) {
                        showToastr(data.message, "error");
                        // fechar o modal
                    const el = document.getElementById('modal-grupo');
                    const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
                    modal.hide();
                        return;
                    }

                    body.find("input[name=codigo]").val(data.codigo).trigger("focus").focus();
                    body.find("input[name=descricao]").val(data.descricao);
                    body.find("select[name=id_tipo]").val(data.id_tipo).trigger('change');
                    body.find("select[name=id_dre]").val(data.id_dre).trigger('change');

                    if (data.recorrencia) {
                        $("[name=recorrencia]").prop("checked", true);
                    } else {
                        $("[name=recorrencia]").prop("checked", false);
                    }
                },
                error: function(xhr) {
                    let resp = xhr.responseJSON;

                    if (resp && resp.message) {
                        showToastr(resp.message, "error");
                    } else {
                        showToastr("Erro ao buscar o registro", "error");
                    }

                const el = document.getElementById('modal-grupo');
                    const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
                    modal.hide();
                }
            });
        }

        $("#form-grupo").validate({
            rules: {
                descricao : { required:true },
                id_tipo : { required:true },
                id_dre : { required:true },
            },
            submitHandler: function(form) {
                loadingButton("#form-grupo button[type=submit]");
                form.submit();
            }
        });
    });

    // PLANO DE CONTAS (ALIAS)
    $('#modal-alias').on('show.bs.modal', function (event) {

        var target = $(event.relatedTarget);
        var action = target.data('action');

        const body = $(this).find(".modal-body");

        if (action=="insert") {

            $(this).find(".modal-header>.modal-title").html("Inserir Nova Variação de Grupo");
            body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $("#form-alias").attr("action", url_admin + "/grupos/alias/insert");

            $("#input-codigo-original").val(target.data('codigo'));
            $("#input-descricao-original").val(target.data('descricao'));

        } else if (action=="update") {

            var id = target.data('id');

            console.log("id: " + id);

            $(this).find(".modal-header>.modal-title").html("Editar Variação de Grupo");
            body.find("input:not(#input-id-alias):not([type=checkbox]):not([type=radio]):not([name=csrf]):not(.no-clear)").val("");

            $("#form-alias").attr("action", url_admin + "/grupos/alias/update");
            $("#input-id-alias").val(id);
            $("#input-codigo-original").val(target.data('codigo'));
            $("#input-descricao-original").val(target.data('descricao'));

            console.log(target);

            $.ajax({
                url: url_admin + "/grupos/alias/find",
                type: "post",
                data : { id },
                dataType: "json",
                success: function(data) {

                    if (data.error) {
                        showToastr(data.message, "error");
                        // fechar o modal
                        const el = document.getElementById('modal-alias');
                        const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
                        modal.hide();
                        return;
                    }

                    body.find("input[name=codigo]").val(data.codigo).trigger("focus").focus();
                    body.find("#input-descricao-original").val(data.descricao_original);
                    body.find("#input-descricao-variacao").val(data.descricao);
                },
                error: function(xhr) {
                    let resp = xhr.responseJSON;

                    if (resp && resp.message) {
                        showToastr(resp.message, "error");
                    } else {
                        showToastr("Erro ao buscar o registro", "error");
                    }

                    const el = document.getElementById('modal-alias');
                    const modal = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
                    modal.hide();
                }
            });
        }

        $("#form-alias").validate({
            rules: {
                descricao : { required:true },
            },
            submitHandler: function(form) {
                loadingButton("#form-alias button[type=submit]");
                form.submit();
            }
        });
    });

    // MODELOS DE CONTROLE
    // $('#modal-controle-modelo').on('show.bs.modal', function (event) {

    //     const body = $(this).find(".modal-body");

    //     body.find('#checkAll').off('change.checkAll').on('change.checkAll', function () {
    //         const checked = $(this).prop('checked');
    //         body.find('label[for="checkAll"]').text(checked ? 'Desmarca Todos' : 'Marca Todos');
    //         body.find('.form-check-input').not(this).prop('checked', checked);
    //     });

    //     $("#form-cadastro-modelo").validate({
    //         submitHandler: function(form) {
    //             loadingButton("#form-cadastro-modelo button[type=submit]");
    //             form.submit();
    //         }
    //     });
    // });

    // // MODELOS DE CONTROLE
    // $('#modal-controle-mensal').on('show.bs.modal', function (event) {

    //     var target = $(event.relatedTarget);
    //     var action = target.data('action');
    //     var id = target.data('id');
    //     var nome = target.data('nome');

    //     const body = $(this).find(".modal-body");

    //     body.find("select:not([name=status]):not([name=mes]),input:not([type=checkbox]):not([type=radio]):not([name=csrf]):not([name=ano])").val("");
    //     body.find("input[name=modelo]").val(id);
    //     body.find("input[name=descricao]").val(nome);

    //     $("#form-controle-mensal").validate({
    //         submitHandler: function(form) {
    //             loadingButton("#form-controle-mensal button[type=submit]");
    //             form.submit();
    //         }
    //     });
    // });

    // if ($("#table-grupo").is(":visible")) {

    //     $('#input-tipo, #input-dre').select2({
    //         minimumResultsForSearch: Infinity,
    //         width: '100%',
    //         theme: "bootstrap5",
    //         dropdownParent: $('#modal-grupo'),
    //     });
    // }

    // TIPO DE CONTA
    $('#modal-tipo-conta').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');
        const body = $(this).find(".modal-body");

        if (action=="insert") {

            $(this).find(".modal-header>.modal-title").html("Inserir Novo Tipo de Conta");
            $("#form-tipo-conta").attr("action", url_admin + "/tipos-conta/insert");
            body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");

        } else if (action=="update") {

            var id = button.data('id');
            $(this).find(".modal-header>.modal-title").html("Editar Tipo de Conta");
            body.find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $("#form-tipo-conta").attr("action", url_admin + "/tipos-conta/update");
            $("#input-id").val(id);

            $.ajax({
                url: url_admin + "/tipos-conta/find",
                type: "post",
                data : { id },
                dataType : 'json',
                success: function(obj) {
                    $("input[name=descricao]").val(obj.descricao).trigger("focus").focus();
                    if (obj.subtotal === 1) {
                        $("input[name=subtotal]").prop("checked", true);
                    } else {
                        $("input[name=subtotal]").prop("checked", false);
                    }
                },
            });
        }

        $("#form-tipo-conta").validate({
            rules: {
                descricao : { required:true },
            },
            submitHandler: function(form) {
                loadingButton("#form-tipo-conta button[type=submit]");
                form.submit();
            }
        });
    });

    // BANCOS
    $('#modal-banco').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');
        const body = $(this).find(".modal-body");

        if (action=="insert") {

            $(this).find(".modal-header>.modal-title").html("Inserir Novo Banco");
            $("#form-banco").attr("action", url_admin + "/bancos/insert");
            body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
        }

        else if (action=="update") {

            var id = button.data('id');
            $(this).find(".modal-header>.modal-title").html("Editar Banco");
            body.find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $("#form-banco").attr("action", url_admin + "/bancos/update");
            $("#input-id").val(id);

            $.ajax({
                url: url_admin + "/bancos/find",
                type: "post",
                data : { id },
                dataType: 'json',
                success: function(obj) {
                    $("input[name=codigo]").val(obj.codigo).trigger("focus").focus();
                    $("input[name=nome]").val(obj.nome);
                },
            });
        }

        $("#form-banco").validate({
            submitHandler: function(form) {
                loadingButton("#form-banco [type=submit]");
                form.submit();
            }
        });
    });

    // SERVIÇOS
    $('#modal-servico').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');
        const body = $(this).find(".modal-body");

        if (action=="insert") {

            $(this).find(".modal-header>.modal-title").html("Inserir Novo Serviço");
            $("#form-servico").attr("action", url_admin + "/servicos/insert");
            body.find("select:not([name=heranca]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf]):not([name=tipo])").val("");
            $(".qtde-inicial").show();

        } else if (action=="update") {

            var id = button.data('id');
            $(this).find(".modal-header>.modal-title").html("Editar Serviço");
            body.find("input:not([type=checkbox]):not([type=radio]):not([name=csrf]):not([name=tipo])").val("");
            $("#form-servico").attr("action", url_admin + "/servicos/update");
            $("#input-id").val(id);

            $.ajax({
                url: url_admin + "/servicos/find",
                type: "post",
                data : { id },
                dataType: 'json',
                success: function(obj) {

                    body.find("[name=nome]").val(obj.nome).trigger("focus").focus();
                    body.find("[name=heranca]").val(obj.heranca).selectpicker("refresh");
                    body.find("[name=cor]").val(obj.cor);
                    // body.find("[name=valor]").val(obj.valor);

                    if (obj.heranca == "anterior" || obj.heranca == "zerado") {
                        $(".qtde-inicial").show();
                    } else {
                        $(".qtde-inicial").hide();
                    }
                },
            });
        }

        $("#form-servico").validate({
            submitHandler: function(form) {
                loadingButton("#form-servico [type=submit]");
                form.submit();
            }
        });
    });

    // DREs
    $('#modal-dre').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');
        const body = $(this).find(".modal-body");

        if (action=="insert") {

            $(this).find(".modal-header>.modal-title").html("Inserir Nova DRE");
            $("#form-dre").attr("action", url_admin + "/dre/insert");
            body.find("textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");

        } else if (action=="update") {

            var id = button.data('id');
            $(this).find(".modal-header>.modal-title").html("Editar DRE");
            body.find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $("#form-dre").attr("action", url_admin + "/dre/update");
            $("#input-id").val(id);

            $.ajax({
                url: url_admin + "/dre/find",
                type: "post",
                data : { id },
                dataType: 'json',
                success: function(obj) {
                    $("[name=codigo]").val(obj.codigo).trigger("focus").focus();
                    $("[name=descricao]").val(obj.descricao);
                    $("[name=tipo]").val(obj.tipo);
                    $("[name=tipo_despesa]").val(obj.tipo_despesa);
                    // if (obj.subtotal === 1) {
                    //     $("input[name=subtotal]").prop("checked", true);
                    // } else {
                    //     $("input[name=subtotal]").prop("checked", false);
                    // }
                },
            });
        }

        $("#form-dre").validate({
            submitHandler: function(form) {
                loadingButton("#form-dre button[type=submit]");
                form.submit();
            }
        });
    });

    // Modalidades
    $('#modal-modalidade').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');
        const body = $(this).find(".modal-body");

        if (action=="insert") {

            body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Inserir Nova Modalidade");
            $("#form-modalidade").attr("action", url_admin + "/modalidades/insert");

        } else if (action=="update") {

            var id = button.data('id');
            $(this).find(".modal-header>.modal-title").html("Editar Modalidade");
            body.find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $("#form-modalidade").attr("action", url_admin + "/modalidades/update");
            $("#input-id").val(id);

            $.ajax({
                url: url_admin + "/modalidades/find",
                type: "post",
                data : { id },
                success: function(data) {
                    var obj = $.parseJSON(data);
                    body.find("input[name=descricao]").val(obj.descricao).trigger("focus").focus();
                },
            });
        }

        $("#form-modalidade").validate({
            submitHandler: function(form) {
                loadingButton("#form-modalidade button[type=submit]");
                form.submit();
            }
        });
    });

    // Auditoria
    if ($("#form-auditoria").is(":visible")) {

        $('#id_estabelecimento').on('change', function() {

            // BUSCAR DATAS DE CONTAGEM
            $.ajax({
                url: url_admin + "/inventario/listar-datas",
                type: "POST",
                data: {
                    id_estabelecimento: $(this).val()
                },
                beforeSend: function() {
                    $('#start, #end')
                        .html('<option value="">Carregando...</option>')
                        .prop('disabled', true)
                        .trigger('change');
                },
                success: function(response) {
                    try {
                        let datas = JSON.parse(response); // Converte a resposta para JSON

                        $('#start, #end').empty();

                        if (Array.isArray(datas) && datas.length > 0) {
                            $('#start, #end').append('<option value="">Selecione uma data</option>');

                            datas.forEach(function(data) {
                                let dataISO = new Date(data); // Converte para objeto Date
                                let dataBR = dataISO.toLocaleDateString('pt-BR', { timeZone: 'UTC' }); // Formato DD/MM/YYYY

                                $('#start, #end').append(`<option value="${data}">${dataBR}</option>`);
                            });

                            $('#start, #end').prop('disabled', false).trigger('change');

                        } else {
                            $('#start, #end')
                                .html('<option value="">Nenhuma data encontrada</option>')
                                .prop('disabled', false)
                                .trigger('change');
                        }

                    } catch (error) {
                        console.error("Erro ao processar resposta:", error);
                        $('#start, #end')
                            .html('<option value="">Erro ao carregar</option>')
                            .prop('disabled', false)
                            .trigger('change');
                    }
                },
                error: function() {
                    $('#start, #end')
                        .html('<option value="">Erro ao carregar</option>')
                        .prop('disabled', false)
                        .trigger('change');
                }
            });


        });

        // Método customizado para verificar se a data 'start' é menor ou igual à data 'end'
        $.validator.addMethod("startBeforeEnd", function (value, element) {
            var startDate = $('#start').val(); // Data de início
            var endDate = $('#end').val(); // Data de término

            // Se ambos os campos tiverem valores e 'start' for maior que 'end', retorna false
            if (startDate && endDate) {
                return new Date(startDate) <= new Date(endDate);
            }

            return true; // Retorna true caso os campos estejam vazios ou válidos
        }, "A data de início não pode ser maior que a data de término.");

        $("#form-auditoria").validate({
            ignore: ":hidden, [type='time']",
            rules: {
                start: {
                    required: true,
                    date: true,
                    startBeforeEnd: true
                },
                end: {
                    required: true,
                    date: true,
                }
            },
            submitHandler: function(form) {

                $.ajax({
                    type: "post",
                    url: url_admin + "/inventario/auditoria",
                    data: $(form).serialize(),
                    beforeSend: function() {
                        $('#list')
                            .find(".card-body")
                            .html("<h2 class='align-items-center d-flex fa-fade justify-content-center p-5 text-center text-uppercase w-100'><i class='fa fa-rotate fa-spin me-3'></i> Carregando ...</h2>");
                        $('#list').show();
                    },
                    success: function (response) {

                        $('#list').find(".card-body").html(response);

                    }
                });
            }
        });
    }

    if ($("#form-contagem-busca").is(":visible")) {

        $('#form-contagem-busca').validate({
            submitHandler: function(form) {

                $.ajax({
                    type: "post",
                    url: url_admin + "/inventario/auditoria/contagem/busca",
                    data: $(form).serialize(),
                    beforeSend: function() {
                        $('#list-products')
                            .html("<h2 class='align-items-center d-flex fa-fade justify-content-center p-5 text-center text-uppercase w-100'><i class='fa fa-rotate fa-spin me-3'></i> Carregando Produtos ...</h2>");

                    },
                    success: function (response) {
                        $("#list-products").html(response);

                        $('#form-inventario-produtos').validate({
                            submitHandler: function(form) {
                                loadingButton("#form-contagem-busca button[type=submit]");
                                form.submit();
                            }
                        });

                    }
                });
            }
        });
    }

    if ($("#form-inventario-produtos").is(":visible")) {
    }

    // Cadastro de Produtos
    if ($("#form-produto").is(":visible")){

        $("#form-produto").validate({
            submitHandler: function(form) {
                loadingButton("#form-produto button[type=submit]");
                form.submit();
            }
        });
    }

    // Cadastro de Perfis
    if ($("#form-perfil").is(":visible")){

        $("#form-perfil").validate({
            submitHandler: function(form) {
                loadingButton("#form-perfil button[type=submit]");
                form.submit();
            }
        });
    }

    // TIPO DE MOVIMENTAÇÃO
    $('#modal-tipo-movimentacao').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');
        const body = $(this).find(".modal-body");

        if (action == "insert") {

            $(this).find(".modal-header>.modal-title").html("Inserir Novo Tipo de Movimentação");
            body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $("#form-tipo-movimentacao").attr("action", url_admin + "/tipos-movimentacao/insert");

        } else if (action == "update") {

            var id = button.data('id');
            $(this).find(".modal-header>.modal-title").html("Editar Tipo de Movimentação");
            body.find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $("#form-tipo-movimentacao").attr("action", url_admin + "/tipos-movimentacao/update");
            $("#input-id").val(id);

            $.ajax({
                url: url_admin + "/tipos-movimentacao/find",
                type: "post",
                data : { id },
                dataType : 'json',
                success: function(obj) {
                    body.find("input[name=descricao]").val(obj.descricao).trigger("focus").focus();
                    if (obj.subtotal === 1) {
                        body.find("input[name=subtotal]").prop("checked", true);
                    } else {
                        body.find("input[name=subtotal]").prop("checked", false);
                    }
                },
            });
        }

        $("#form-tipo-movimentacao").validate({
            submitHandler: function(form) {
                loadingButton("#form-tipo-movimentacao button[type=submit]");
                form.submit();
            }
        });
    });

    // MOVIMENTAÇÃO INTERNA
    $('#modal-movimentacao-interna').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');
        const body = $(this).find(".modal-body");

        if (action == "insert") {

            $(this).find(".modal-header>.modal-title").html("Inserir Nova Movimentação Interna");
            body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $("#form-movimentacao-interna").attr("action", url_admin + "/movimentacao-interna/insert");

        } else if (action == "update") {

            var id = button.data('id');
            $(this).find(".modal-header>.modal-title").html("Editar Movimentação Interna");
            body.find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $("#form-movimentacao-interna").attr("action", url_admin + "/movimentacao-interna/update");
            $("#input-id").val(id);

            $.ajax({
                url: url_admin + "/movimentacao-interna/find",
                type: "post",
                data : { id },
                dataType : 'json',
                success: function(obj) {
                    body.find("input[name=descricao]").val(obj.descricao).trigger("focus").focus();
                    if (obj.subtotal === 1) {
                        body.find("input[name=subtotal]").prop("checked", true);
                    } else {
                        body.find("input[name=subtotal]").prop("checked", false);
                    }
                },
            });
        }

        $("#form-movimentacao-interna").validate({
            submitHandler: function(form) {
                loadingButton("#form-movimentacao-interna button[type=submit]");
                form.submit();
            }
        });
    });

    // Cadastro de Fornedores
    if ($("#form-franqueado").is(":visible")){

        $("#form-franqueado").validate({
            rules: {
                razao : { required:true },
                cpf   : {
                    cpf: function(element) {
                        return $("#pessoa").val() == "F";
                    }
                },
                cnpj  : {
                    cnpj: function(element) {
                        return $("#pessoa").val() == "J";
                    }
                },
            },
            submitHandler: function(form) {
                loadingButton("#form-franqueado button[type=submit]");
                form.submit();
            }
        });
    }

    // Cadastro de Sindicatos
    if ($("#form-sindicato").is(":visible")){

        $("#form-sindicato").validate({
            rules: {
                razao : { required:true },
                cnpj  : { cnpj: true },
                fundacao : { dateBR: true },
            },
            submitHandler: function(form) {
                loadingButton("#form-sindicato button[type=submit]");
                form.submit();
            }
        });
    }

    // Cadastro de Estabelecimentos
    if ($("#form-estabelecimento").is(":visible")){

        $("#form-estabelecimento").validate({
            rules: {
                cpf   : {
                    cpf: function(element) {
                        return $("#pessoa").val() == "F";
                    }
                },
                cnpj  : {
                    cnpj: function(element) {
                        return $("#pessoa").val() == "J";
                    }
                },
            },
            submitHandler: function(form) {
                loadingButton("#form-estabelecimento button[type=submit]");
                form.submit();
            }
        });
    }

    // EDITAR EMPRESA
    if ($("#form-empresa").is(":visible")) {

        const $formEmpresa = $("#form-empresa");

        $("#form-empresa").validate({
            rules: {
                razao : { required:true },
                nascimento: {
                    dateBR: true
                },
                estado: {
                    inArray: ["AC", "AL", "AP", "AM", "BA", "CE", "DF", "ES", "GO", "MA", "MT", "MS", "MG", "PA", "PB", "PR", "PE", "PI", "RJ", "RN", "RS", "RO", "RR", "SC", "SP", "SE", "TO"]
                }
            },
            messages: {
                estado: {
                    inArray: "Estado inválido"
                }
            },
            submitHandler: function(form) {
                loadingButton("#form-cliente button[type=submit]");
                form.submit();
            }
        });
    }

    $(document).on("change", "[name=controle_ponto]", function () {
        const $form = $(this).closest("form");
        const on = $(this).val() === "1";

        const $start = $form.find(".controle_ponto_periodo_start");
        const $end   = $form.find(".controle_ponto_periodo_end");

        // se estiver dentro de fieldset disabled, nunca vai liberar
        const $fs = $start.closest("fieldset[disabled]");

        if (on) {
            if ($fs.length) $fs.prop("disabled", false).removeAttr("disabled");

            $start.prop("readonly", false).removeAttr("readonly");
            $end  .prop("readonly", false).removeAttr("readonly");

            $start.prop("disabled", false).removeAttr("disabled");
            $end  .prop("disabled", false).removeAttr("disabled");

            // se tiver CSS travando clique
            $start.css("pointer-events", "auto");
            $end.css("pointer-events", "auto");

            $start.focus();
        } else {
            $start.prop("readonly", true).attr("readonly", "readonly");
            $end  .prop("readonly", true).attr("readonly", "readonly");
        }

        console.log("START ELEMENT =>", $start.get(0));
        console.log({
            on,
            readonlyProp: $start.prop("readonly"),
            readonlyAttr: $start.attr("readonly"),
            disabledProp: $start.prop("disabled"),
            disabledAttr: $start.attr("disabled"),
            fieldsetDisabled: $fs.length ? true : false,
            pointerEvents: $start.css("pointer-events"),
        });
    });


    $('#cliente-modal-empresa').on('shown.bs.modal', function (event) {

        var $modal = $(this);
        var target = $(event.relatedTarget);
        var id = target.data('id');

        var $body = $modal.find(".modal-body");

        $.ajax({
            url: url_admin + "/clientes/empresa/find",
            type: "post",
            data: { id },
            success: function (html) {
                $body.html(html);

                initAjaxForms($modal);
                applyMasks($modal);

                if (typeof flatpickr !== "undefined") {
                    initFlatpickrMonth($modal);
                }

                $body.find("[name=pessoa]").trigger("change");
                $body.find("[name=controle_ponto]").trigger("change");
            }
        });

    });

    // TELA DE MENSAGENS
    $("#accept-ia-tema").on("click", function () {

        const $btn = $(this);

        // rodar botão
        $btn.prop("disabled", true)
            .find("i")
            .removeClass("uil uil-check")
            .addClass('fa fa-rotate fa-spin');

        $("#refused-ia-tema").prop("disabled", true);

        // ajax para salvar o tema_ia no tema
        $.ajax({
            type: "post",
            url: url_admin + "/intranet/mensagem/tema/accept",
            data: {id: $btn.data("id")},
            dataType: "json",
            success: function (response) {

                if (response.success) {

                    $("#transform-tema").hide();
                    // aparecer com o iput
                    $("input[name=tema]").show();

                } else {

                    $btn.prop("disabled", false)
                        .find("i")
                        .removeClass("fa fa-rotate fa-spin")
                        .addClass('uil uil-check');
                    $("#refused-ia-tema").prop("disabled", false);

                    showToastr(response.message ?? "Houve um erro ao aceitar o tema.", "error");
                }
            }
        });
    });

    $("#refuse-ia-tema").on("click", function () {

        const $btn = $(this);

        // rodar botão
        $btn.prop("disabled", true)
            .find("i")
            .removeClass("uil uil-times")
            .addClass('fa fa-rotate fa-spin');

        $("#accept-ia-tema").prop("disabled", true);

        // ajax para salvar o tema_ia no tema
        $.ajax({
            type: "post",
            url: url_admin + "/intranet/mensagem/tema/refuse",
            data: {id: $btn.data("id")},
            dataType: "json",
            success: function (response) {

                if (response.success) {

                    $("#transform-tema").hide();
                    // aparecer com o iput
                    $("input[name=tema]").val("").show();

                    notify(response.message ?? "Tema aceito com sucesso.", "success");

                } else {

                    $btn.prop("disabled", false)
                        .find("i")
                        .removeClass("fa fa-rotate fa-spin")
                        .addClass('uil uil-times');

                    $("#accept-ia-tema").prop("disabled", false);

                    notify(response.message ?? "Houve um erro ao aceitar o tema.", "error");
                }
            }
        });
    });

    $("#accept-ia-categoria").on("click", function () {

        const $btn = $(this);

        // rodar botão
        $btn.prop("disabled", true)
            .find("i")
            .removeClass("uil uil-check")
            .addClass('fa fa-rotate fa-spin');

        $("#refused-ia-categoria").prop("disabled", true);

        // ajax para salvar o categoria_ia no categoria
        $.ajax({
            type: "post",
            url: url_admin + "/intranet/mensagem/categoria/accept",
            data: {id: $btn.data("id")},
            dataType: "json",
            success: function (response) {

                if (response.success) {

                    $("#transform-categoria").hide();
                    // aparecer com o iput
                    $("input[name=categoria]").show();

                    notify(response.message ?? "Categoria aceita com sucesso.", "success");

                } else {

                    $btn.prop("disabled", false)
                        .find("i")
                        .removeClass("fa fa-rotate fa-spin")
                        .addClass('uil uil-check');

                    $("#refused-ia-categoria").prop("disabled", false);

                    notify(response.message ?? "Houve um erro ao aceitar o categoria.", "error");
                }
            }
        });
    });

    $("#refuse-ia-categoria").on("click", function () {

        const $btn = $(this);

        // rodar botão
        $btn.prop("disabled", true)
            .find("i")
            .removeClass("uil uil-times")
            .addClass('fa fa-rotate fa-spin');

        $("#accept-ia-categoria").prop("disabled", true);

        // ajax para salvar o categoria_ia no categoria
        $.ajax({
            type: "post",
            url: url_admin + "/intranet/mensagem/categoria/refuse",
            data: {id: $btn.data("id")},
            dataType: "json",
            success: function (response) {

                if (response.success) {

                    $("#transform-categoria").hide();
                    // aparecer com o iput
                    $("input[name=categoria]").val("").show();

                } else {

                    $btn.prop("disabled", false)
                        .find("i")
                        .removeClass("fa fa-rotate fa-spin")
                        .addClass('uil uil-times');

                    $("#accept-ia-categoria").prop("disabled", false);

                    showToastr(response.message ?? "Houve um erro ao aceitar o tema.", "error");
                }
            }
        });
    });


    // ===============================================
    // MODAL ENVIAR MENSAGEM PARA O FRANQUEADO
    // ===============================================

    // $(document).on('show.bs.modal', '#modal-mensagem-franquedo', function (e) {

    //     const $modal = $(this);
    //     const $btn = $(e.relatedTarget);
    //     if (!$btn.length) return;

    //     const email = String($btn.data('email') ?? '');
    //     const whatsapp = String($btn.data('whatsapp') ?? '');
    //     const nome = String($btn.data('nome') ?? '');
    //     const id = $btn.data('id');

    //     const emailValido = email && email !== 'false';
    //     const whatsappValido = whatsapp && whatsapp !== 'false';

    //     // ===== mês atual =====
    //     const meses = [
    //         'janeiro','fevereiro','março','abril','maio','junho',
    //         'julho','agosto','setembro','outubro','novembro','dezembro'
    //     ];
    //     const mesAtual = meses[new Date().getMonth()];

    //     // ===== guarda template original =====
    //     const $txt = $modal.find('#mensagem');
    //     if (!$txt.data('template')) {
    //         $txt.data('template', $txt.val());
    //     }

    //     // ===== texto destinatário =====
    //     $modal.find('#modal-mensagem-nome').text(nome || '—');
    //     $modal.find('#modal-mensagem-email')
    //         .html(emailValido ? `E-mail: ${email}` : 'E-mail: <i>Não informado</i>');
    //     $modal.find('#modal-mensagem-whatsapp')
    //         .html(whatsappValido ? `WhatsApp: ${whatsapp}` : 'WhatsApp: <i>Não informado</i>');

    //     // ===== hidden =====
    //     $modal.find('#destEmail').val(emailValido ? email : '');
    //     $modal.find('#destWhatsapp').val(whatsappValido ? whatsapp : '');
    //     $modal.find('#destNome').val(nome || '');
    //     $modal.data('id', id);

    //     // ===== switches =====
    //     $modal.find('#switchEmail')
    //         .prop('checked', !!emailValido)
    //         .prop('disabled', !emailValido);

    //     $modal.find('#switchWhatsapp')
    //         .prop('checked', !!whatsappValido)
    //         .prop('disabled', !whatsappValido);

    //     // ===== mensagem dinâmica =====
    //     const template = $txt.data('template') || '';
    //     const msgFinal = template
    //         .replace(/{{\s*nome\s*}}/gi, nome || '')
    //         .replace(/{{\s*mes\s*}}/gi, mesAtual);

    //     $txt.val($.trim(msgFinal));
    // });

    // $("#form-mensagem-franquedo").validate({
    //     submitHandler: function(form) {

    //         const $modal = $('#modal-mensagem-franquedo');
    //         const id = $modal.data('id');

    //         const emailSelecionado = $('#switchEmail').is(':checked');
    //         const wppSelecionado = $('#switchWhatsapp').is(':checked');

    //         if (!emailSelecionado && !wppSelecionado) {
    //             showToastr('Selecione ao menos E-mail ou WhatsApp.', 'danger');
    //             return false;
    //         }

    //         const email = emailSelecionado ? $('#destEmail').val() : '';
    //         const whatsapp = wppSelecionado ? $('#destWhatsapp').val() : '';

    //         // const mensagem = $('#mensagem').val();
    //         const htmlMensagem = tinymce.get('mensagem') ? tinymce.get('mensagem').getContent() : $('#mensagem').val();

    //         $.ajax({
    //             url: url_admin + '/franqueados/enviar-mensagem',
    //             method: 'POST',
    //             data: { id, email, whatsapp, mensagem : htmlMensagem },
    //             dataType: 'json',
    //             beforeSend: function () {
    //                 loadingButton("#btnEnviarMensagemFranqueado");
    //             },
    //             success: function (data) {

    //                 loadingButton("#btnEnviarMensagemFranqueado", true);

    //                 if (data.success) {
    //                     showToastr(data.messages.join("<br>"), "success");
    //                     $('#modal-mensagem-franquedo').modal('hide');
    //                 } else {
    //                     showToastr(data.messages.join("<br>"), "danger");
    //                 }
    //             },
    //             error: function () {
    //                 loadingButton("#btnEnviarMensagemFranqueado", true);
    //                 showToastr("Erro na requisição.", "danger");
    //             }
    //         });

    //         return false; // impede submit padrão
    //     }
    // });

    // ===============================
    // FUNÇÃO MÊS ATUAL
    // ===============================
    function getMesAtualPtbr() {
        const meses = [
            'janeiro','fevereiro','março','abril','maio','junho',
            'julho','agosto','setembro','outubro','novembro','dezembro'
        ];
        return meses[new Date().getMonth()];
    }


    // ===============================
    // ABRIR MODAL
    // ===============================
    $(document).on('show.bs.modal', '#modal-mensagem-franquedo', function (e) {

        const $modal = $(this);
        const $btn = $(e.relatedTarget);
        if (!$btn.length) return;

        const nome = String($btn.data('nome') ?? '');
        const email = String($btn.data('email') ?? '');
        const whatsapp = String($btn.data('whatsapp') ?? '');
        const id = $btn.data('id');

        const emailValido = email && email !== 'false';
        const whatsappValido = whatsapp && whatsapp !== 'false';

        const mesAtual = getMesAtualPtbr();

        const $txt = $modal.find('#mensagem');
        const textareaId = $txt.attr('id');

        // ============================
        // GUARDA TEMPLATE ORIGINAL
        // ============================
        if (!$txt.data('template')) {

            let templateInicial;

            if (window.tinymce && tinymce.get(textareaId)) {
                templateInicial = tinymce.get(textareaId).getContent({ format: 'html' });
            } else {
                templateInicial = $txt.val();
            }

            $txt.data('template', templateInicial);
        }

        const template = $txt.data('template') || '';

        // ============================
        // SUBSTITUI VARIÁVEIS
        // ============================
        const msgFinal = template
            .replace(/{{\s*nome\s*}}/gi, nome)
            .replace(/{{\s*mes\s*}}/gi, mesAtual);

        // ============================
        // SETA CONTEÚDO (TINYMCE OU TEXTAREA)
        // ============================
        if (window.tinymce && tinymce.get(textareaId)) {
            tinymce.get(textareaId).setContent(msgFinal);
        } else {
            $txt.val($.trim(msgFinal));
        }

        // ============================
        // TEXTO DESTINATÁRIO
        // ============================
        $modal.find('#modal-mensagem-nome').text(nome || '—');

        $modal.find('#modal-mensagem-email')
            .html(emailValido ? `E-mail: ${email}` : 'E-mail: <i>Não informado</i>');

        $modal.find('#modal-mensagem-whatsapp')
            .html(whatsappValido ? `WhatsApp: ${whatsapp}` : 'WhatsApp: <i>Não informado</i>');

        // ============================
        // HIDDEN
        // ============================
        $modal.find('#destEmail').val(emailValido ? email : '');
        $modal.find('#destWhatsapp').val(whatsappValido ? whatsapp : '');
        $modal.find('#destNome').val(nome || '');
        $modal.data('id', id);

        // ============================
        // SWITCHES
        // ============================
        $modal.find('#switchEmail')
            .prop('checked', !!emailValido)
            .prop('disabled', !emailValido);

        $modal.find('#switchWhatsapp')
            .prop('checked', !!whatsappValido)
            .prop('disabled', !whatsappValido);
    });


    // ===============================
    // VALIDATE + AJAX
    // ===============================
    $(function () {

        const $form = $("#form-mensagem-franquedo");
        if (!$form.length) return;

        $form.validate({

            rules: {
                mensagem: {
                    required: true
                }
            },

            messages: {
                mensagem: "A mensagem é obrigatória."
            },

            submitHandler: function(form) {

                const $modal = $('#modal-mensagem-franquedo');
                const id = $modal.data('id');

                const emailSelecionado = $('#switchEmail').is(':checked');
                const wppSelecionado = $('#switchWhatsapp').is(':checked');

                if (!emailSelecionado && !wppSelecionado) {
                    showToastr('Selecione ao menos E-mail ou WhatsApp.', 'danger');
                    return false;
                }

                const email = emailSelecionado ? $('#destEmail').val() : '';
                const whatsapp = wppSelecionado ? $('#destWhatsapp').val() : '';

                let mensagem;

                if (window.tinymce && tinymce.get('mensagem')) {
                    mensagem = tinymce.get('mensagem').getContent({ format: 'html' });
                } else {
                    mensagem = $('#mensagem').val();
                }

                $.ajax({
                    url: url_admin + '/franqueados/enviar-mensagem',
                    method: 'POST',
                    data: { id, email, whatsapp, mensagem },
                    dataType: 'json',

                    beforeSend: function () {
                        loadingButton("#btnEnviarMensagemFranqueado");
                    },

                    success: function (data) {

                        loadingButton("#btnEnviarMensagemFranqueado", true);

                        const msg = (data.messages && data.messages.length)
                            ? data.messages.join("<br>")
                            : (data.msg || "Erro ao enviar.");

                        showToastr(msg, data.success ? "success" : "danger");

                        if (data.success) {
                            $modal.modal('hide');
                        }
                    },

                    error: function () {
                        loadingButton("#btnEnviarMensagemFranqueado", true);
                        showToastr("Erro na requisição.", "danger");
                    }
                });

                return false;
            }
        });

    });



    function initAjaxForms($root) {

        // ✅ select2 dentro de modal: precisa dropdownParent
        $root.find("select.select2").each(function () {
            var $el = $(this);
            if ($el.data("select2")) $el.select2("destroy");

            $el.select2({
                dropdownParent: $root
            });
        });

        // ✅ bootstrap-select
        $root.find(".selectpicker").each(function () {
            var $el = $(this);
            if ($el.data("selectpicker")) {
                $el.selectpicker("refresh");
            } else {
                $el.selectpicker();
            }
        });

        // ✅ validate: inicializa apenas forms que tenham o que você espera
        $root.find("form").each(function () {
            var $form = $(this);

            // filtro: só aplica se for "form de empresa" (ou parecido)
            if (!$form.find("[name=razao]").length) return;

            // evita “Cannot reinitialize validator”
            if ($form.data("validator")) {
                $form.removeData("validator");
                $form.removeData("unobtrusiveValidation");
            }

            $form.validate({
                rules: {
                    razao: { required: true },
                    nascimento: { dateBR: true },
                    estado: {
                        inArray: ["AC","AL","AP","AM","BA","CE","DF","ES","GO","MA","MT","MS","MG","PA","PB","PR","PE","PI","RJ","RN","RS","RO","RR","SC","SP","SE","TO"]
                    }
                },
                messages: {
                    estado: { inArray: "Estado inválido" }
                },
                submitHandler: function (form) {
                    loadingButton($form.find("button[type=submit]"));
                    form.submit();
                }
            });
        });
    }

    $('#modal-cadastro').on('show.bs.modal', function (event) {

        var target = $(event.relatedTarget);

        var action = target.data('action');
        var list = target.data('list');
        var alias = target.data('alias');

        $("#input-list").val(list);

        console.log(target);

        const body = $(this).find(".modal-body");

        if (action == "insert") {

            $(this).find(".modal-header>.modal-title").html("Inserir " + alias);
            body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not(.no-clear)").val("");
            $("#form-cadastro").attr("action", url_admin + "/cadastros/insert");

        } else if (action == "update") {

            var id = target.data('id');

            $(this).find(".modal-header>.modal-title").html("Editar " + alias);
            body.find("input:not([type=checkbox]):not([type=radio]):not(.no-clear)").val("");
            $("#form-cadastro").attr("action", url_admin + "/cadastros/update");
            $("#input-id").val(id);
            $("#input-descricao").val(target.data("descricao"));
        }

        $("#form-cadastro").validate({
            submitHandler: function(form) {
                loadingButton("#form-cadastro button[type=submit]");
                form.submit();
            }
        });

    });

    // CADASTRO DE CONTATOS (ESTABELECIMENTOS)
    $('#modal-contato').on('show.bs.modal', function (event) {

        var target = $(event.relatedTarget);

        console.log("contato");

        var action = target.data('action');

        const body = $(this).find(".modal-body");

        if (action == "insert") {

            $(this).find(".modal-header>.modal-title").html("Inserir Contato");
            body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not(.no-clear)").val("");
            $("#form-cadastro").attr("action", url_admin + "/estabelecimentos/contato/insert");

        } else if (action == "update") {

            // var id = target.data('id');

            // $(this).find(".modal-header>.modal-title").html("Editar " + alias);
            // body.find("input:not([type=checkbox]):not([type=radio]):not(.no-clear)").val("");
            // $("#form-cadastro").attr("action", url_admin + "/cadastros/update");
            // $("#input-id").val(id);
            // $("#input-descricao").val(target.data("descricao"));
        }

        $("#form-contato").validate({
            submitHandler: function(form) {
                loadingButton("#form-contato button[type=submit]");
                form.submit();
            }
        });

        $('#contato-funcao').select2({
            minimumResultsForSearch: Infinity,
            width: '100%',
            theme: "bootstrap5",
            dropdownParent: $('#modal-contato'),
            placeholder: 'Informe a Função'
        });

    });

    // CADASTRO DE CONTATOS (EMPRESA)
    $('#modal-contato-empresa').on('show.bs.modal', function (event) {

        var target = $(event.relatedTarget);

        var action = target.data('action');

        console.log(action);

        const modal = $(this);
        const body = modal.find(".modal-body");
        const form = $("#formEmpresaContato");

        if (action == "insert") {

            modal.find(".modal-header>.modal-title").html("Inserir Contato");
            body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not(.no-clear)").val("");
            form.attr("action", url_admin + "/empresas/contato/insert");

        } else if (action == "update") {

            var id = target.data('id');

            console.log(id);

            modal.find(".modal-header>.modal-title").html("Editar Contato");

            body.find("input:not([type=checkbox]):not([type=radio]):not(.no-clear)").val("");

            form.attr("action", url_admin + "/empresas/contato/update");

            $.ajax({
                type: "post",
                url: url_admin + "/empresas/contato/find",
                data: {id},
                dataType: "json",
                success: function (response) {

                    if (response.error) {
                        showToastr(response?.message || 'Erro ao processar solicitação', 'danger');
                        return;
                    }

                    dado = response.dado

                    body.find("[name=nome]").val(dado?.nome);
                    body.find("[name=funcao]").val(dado?.funcao);
                    body.find("[name=telefone]").val(dado?.telefone);
                    body.find("[name=whatsapp]").val(dado?.whatsapp);
                    body.find("[name=email]").val(dado?.email);
                    body.find("[name=observacoes]").val(dado?.observacoes);
                    body.find("[name=id]").val(dado?.id);

                },
                error: function (xhr, status, error) {
                    showToastr('Erro ao processar solicitação', 'danger');
                }
            });
        }

        form.validate({
            submitHandler: function(form) {
                loadingButton("#formEmpresaContato button[type=submit]");
                form.submit();
            }
        });

    });

    // CADASTRO DE EXTINTOR (EMPRESA)
    $('#modal-extintor-empresa').on('show.bs.modal', function (event) {

        var target = $(event.relatedTarget);

        var action = target.data('action');
        var empresa = target.data('empresa');

        console.log(target);

        const modal = $(this);
        const body = modal.find(".modal-body");
        const form = $("#formEmpresaExtintor");

        if (action == "insert") {

            modal.find(".modal-header>.modal-title").html("Inserir Extintor");
            body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not(.no-clear)").val("");
            form.attr("action", url_admin + "/empresas/extintor/insert");

            $.ajax({
                type: "post",
                url: url_admin + "/empresas/extintor/new",
                data: {
                    tipo : "empresa",
                    id: empresa
                },
                success: function (response) {

                    if (response.error) {
                        showToastr(response?.message || 'Erro ao processar solicitação', 'danger');
                        return;
                    }

                    body.html(response);

                    $("#formEmpresaExtintor").validate({
                        submitHandler: function(form) {
                            loadingButton("#formEmpresaExtintor button[type=submit]");
                            form.submit();
                        }
                    });
                }
            });
        }

        console.log(action);

        /*



        } else if (action == "update") {

            var id = target.data('id');

            console.log(id);

            modal.find(".modal-header>.modal-title").html("Editar Contato");

            body.find("input:not([type=checkbox]):not([type=radio]):not(.no-clear)").val("");

            form.attr("action", url_admin + "/empresas/contato/update");

            $.ajax({
                type: "post",
                url: url_admin + "/empresas/contato/find",
                data: {id},
                dataType: "json",
                success: function (response) {

                    if (response.error) {
                        showToastr(response?.message || 'Erro ao processar solicitação', 'danger');
                        return;
                    }

                    dado = response.dado

                    body.find("[name=nome]").val(dado?.nome);
                    body.find("[name=funcao]").val(dado?.funcao);
                    body.find("[name=telefone]").val(dado?.telefone);
                    body.find("[name=whatsapp]").val(dado?.whatsapp);
                    body.find("[name=email]").val(dado?.email);
                    body.find("[name=observacoes]").val(dado?.observacoes);
                    body.find("[name=id]").val(dado?.id);

                },
                error: function (xhr, status, error) {
                    showToastr('Erro ao processar solicitação', 'danger');
                }
            });
        }

        form.validate({
            submitHandler: function(form) {
                loadingButton("#formEmpresaContato button[type=submit]");
                form.submit();
            }
        });

        */

    });






    // Função para enviar requisição AJAX
    $("#update-produtos").on("click", function() {
        const id_estabelecimento = $("#id").val();

        // Coletar todos os checkboxes marcados (visíveis ou não)
        const checkedProducts = [];
        $("#table-produtos-relacionados tbody .form-check-input:checked").each(function() {
            checkedProducts.push($(this).val()); // Adicionar o valor do checkbox ao array
        });

        $.ajax({
            url: url_admin + "/estabelecimentos/produto",
            type: "POST",
            data: {
                produtos: checkedProducts, // Enviar todos os IDs dos produtos selecionados
                id: id_estabelecimento,
            },
            success: function(response) {
                if (response == "true") {
                    showToastr("Produtos atualizados com sucesso");
                } else {
                    showToastr("Houve um erro ao tentar atualizar os produtos", 'error');
                }
            },
            error: function(error) {
                console.error("Erro:", error);
            }
        });
    });

    // // Processar dados das planilhas importadas
    // $(".btn-process-dados").on("click", function(e) {

    //     e.preventDefault();

    //     const $this = $(this);
    //     const id = $this.data("id");

    //     $.ajax({
    //         type: "post",
    //         url: url_admin + "/dre/dados/processar",
    //         data: { id },
    //         dataType: 'json',
    //         beforeSend: function() {
    //             $(".situacao-" + id).html("<i class='fa-beat-fade'>Processando...</i>");
    //             $(".mensagem-" + id).text("");
    //             // desabilita botão, mas não remove
    //             $this.prop("disabled", true).addClass("disabled");
    //             $(".btn-del-" + id).prop("disabled", true).addClass("disabled");
    //         },
    //         timeout: 120000, // 2 minutos
    //         error: function(xhr, status, err) {
    //             // volta UI ao estado inicial
    //             showToastr("Erro ao processar (timeout/erro de conexão). Tente novamente.", 'error');
    //             $(".situacao-" + id).html('<span class="badge bg-danger">Não Processado</span>');
    //             $(".mensagem-" + id).removeClass("text-success").addClass("text-danger").text("Erro de execução. Tente novamente.");
    //             // reabilita botões
    //             $this.prop("disabled", false).removeClass("disabled");
    //             $(".btn-del-" + id).prop("disabled", false).removeClass("disabled");
    //         },
    //         success: function (response) {

    //             console.log(response);

    //             if (response.error == true) {
    //                 // mostrar mensagem
    //                 showToastr(response.alert ?? response.message, 'error');
    //                 // mostrar situacao
    //                 $(".situacao-" + id).html('<span class="badge bg-danger">Não Processado</span>');
    //                 $(".mensagem-" + id).removeClass("text-success").addClass("text-danger").text(response.message);
    //             } else {
    //                 // mostrar mensagem
    //                 showToastr(response.message);
    //                 // mostrar situacao
    //                 $(".situacao-" + id).html('<span class="badge bg-success">Concluído</span>');
    //                 $(".mensagem-" + id).removeClass("text-danger").addClass("text-success").text(response.message);
    //             }

    //             // ao final, reabilita botões conforme status
    //             if (response.error) {
    //                 $this.prop("disabled", false).removeClass("disabled");
    //                 $(".btn-del-" + id).prop("disabled", false).removeClass("disabled");
    //             } else {
    //                 // sucesso: pode manter removido se quiser
    //                 $this.remove();
    //                 $(".btn-del-" + id).remove();
    //             }
    //         }
    //     });
    // });

    $(".btn-process-dados").on("click", function(e) {

        e.preventDefault();

        const $this = $(this);
        const id = $this.data("id");

        $.ajax({
            type: "post",
            url: url_admin + "/dre/dados/processar",
            data: { id },
            dataType: 'json',
            beforeSend: function() {
                $(".situacao-" + id).html("<i class='fa-beat-fade'>Processando...</i>");
                $(".mensagem-" + id).text("");
                $(".btn-del-" + id).prop("disabled", true).addClass("disabled");
                $this.prop("disabled", true).addClass("disabled");
                $this.find("i").addClass("fa-spin");
            },
            timeout: 120000,
            error: function(xhr, status, err) {
                showToastr("Erro ao processar (timeout/erro de conexão).", 'error');

                $(".situacao-" + id).html('<span class="badge bg-danger">Não Processado</span>');
                $(".mensagem-" + id)
                    .removeClass("text-success")
                    .addClass("text-danger")
                    .text("Erro de execução. Tente novamente.");

                $this.prop("disabled", false).removeClass("disabled");
                $this.find("i").removeClass("fa-spin");
                $(".btn-del-" + id).prop("disabled", false).removeClass("disabled");
            },
            success: function (response) {

                // console.log(response);

                // transforma \n -> <br>
                let msgHtml = response.message.replace(/\n/g, "<br>");

                if (response.error === true) {

                    showToastr(msgHtml, 'error');

                    $(".situacao-" + id).html('<span class="badge bg-danger">Não Processado</span>');
                    $(".mensagem-" + id)
                        .removeClass("text-success")
                        .addClass("text-danger")
                        .html(msgHtml);   // <-- AQUI: usar .html()

                    $this.prop("disabled", false).removeClass("disabled");
                    $this.find("i").removeClass("fa-spin");
                    $(".btn-del-" + id).prop("disabled", false).removeClass("disabled");

                } else {

                    showToastr(msgHtml, 'success');

                    $(".situacao-" + id).html('<span class="badge bg-success">Concluído</span>');
                    $(".mensagem-" + id)
                        .removeClass("text-danger")
                        .addClass("text-success")
                        .html(msgHtml);   // <-- AQUI TAMBÉM .html()

                    $this.remove();
                    $(".btn-del-" + id).remove();
                }
            }

        });
    });






    // Processar dados das planilhas importadas
    $(".btn-process-vendas").on("click", function(e) {

        e.preventDefault();

        const $this = $(this);
        const id = $this.data("id");

        $.ajax({
            type: "post",
            url: url_admin + "/vendas/dados/processar",
            data: { id },
            dataType: 'json',
            beforeSend: function() {
                // MUDAR A SITUAÇÃO PARA PROCESSANDO
                $(".situacao-" + id).html("<i class='fa-beat-fade'>Processando...</i>");
                $(".mensagem-" + id).text("");
                // REMOVER BOTÃO
                $this.remove();
                // REMOVER BOTÃO DE CANCELAR
                $(".btn-del-" + id).remove();
            },
            success: function (response) {

                if (response.error == true) {
                    // mostrar mensagem
                    showToastr(response.alert ?? response.message, 'error');
                    // mostrar situacao
                    $(".situacao-" + id).html('<span class="badge bg-danger">Não Processado</span>');
                    $(".mensagem-" + id).removeClass("text-success").addClass("text-danger").text(response.message);
                } else {
                    // mostrar mensagem
                    showToastr(response.message);
                    // mostrar situacao
                    $(".situacao-" + id).html('<span class="badge bg-success">Concluído</span>');
                    $(".mensagem-" + id).removeClass("text-danger").addClass("text-success").text(response.message);
                }
            }
        });
    });

    // Processar dados das planilhas importadas (Inventario)
    $(".btn-process").on("click", function(e) {

        e.preventDefault();

        const $this = $(this);
        const id = $this.data("id");

        $.ajax({
            type: "post",
            url: url_admin + "/inventario/dados/processar",
            data: { id },
            dataType: 'json',
            beforeSend: function() {
                // MUDAR A SITUAÇÃO PARA PROCESSANDO
                $(".situacao-" + id).html("<i class='fa-beat-fade'>Processando...</i>");
                $(".mensagem-" + id).text("");
                // REMOVER BOTÃO
                $this.remove();
                // REMOVER BOTÃO DE CANCELAR
                $(".btn-del-" + id).remove();
            },
            success: function (response) {

                if (response.error == true) {
                    // mostrar mensagem
                    showToastr(response.alert ?? response.message, 'error');
                    // mostrar situacao
                    $(".situacao-" + id).html('<span class="badge bg-danger">Não Processado</span>');
                    $(".mensagem-" + id).removeClass("text-success").addClass("text-danger").text(response.message);
                } else {
                    // mostrar mensagem
                    showToastr(response.message);
                    // mostrar situacao
                    $(".situacao-" + id).html('<span class="badge bg-success">Concluído</span>');
                    $(".mensagem-" + id).removeClass("text-danger").addClass("text-success").text(response.message);
                }

            }
        });

    });


    // Visualizar registros de DRE importados
    var tabelaDadosDRE;

    $('#modal-dados-imported').on('show.bs.modal', function (event) {

        var target = $(event.relatedTarget);
        var id = target.data('id');

        if ($.fn.DataTable.isDataTable('#tabela-dados')) {
            tabelaDadosDRE.destroy(); // Destroi a tabela antiga se já existir

            // Limpa o corpo da tabela e insere uma linha com a mensagem de "Carregando..."
            var tableBody = $('#tabela-dados tbody');
            tableBody.html('');
        }

        tabelaDadosDRE = $('#tabela-dados').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 1000,
            lengthMenu: [
                [100, 500, 1000, -1],
                [100, 500, 1000, "Todos"]
            ],
            scrollY: '400px',
            scrollCollapse: true,
            ajax: {
                url: url_admin + "/dre/dados/listar",
                type: "POST",
                data: { id: id },
            },
            columns: [
                { data: "estabelecimento" },
                { data: "plano" },
                { data: "descricao" },
                {
                    data: "data",
                    createdCell: function (td, cellData, rowData, row, col) {
                        $(td).addClass('no-wrap');
                    }
                },
                { data: "valor" },
            ],
            order: [
            ],
            language: {
                sProcessing: "Carregando..."
            }
        });
    });

    // Visualizar registros de Vendas importados
    var tabelaDadosVendas;

    $('#modal-vendas-imported').on('show.bs.modal', function (event) {

        var target = $(event.relatedTarget);
        var id = target.data('id');

        if ($.fn.DataTable.isDataTable('#tabela-dados')) {
            tabelaDadosVendas.destroy(); // Destroi a tabela antiga se já existir

            // Limpa o corpo da tabela e insere uma linha com a mensagem de "Carregando..."
            var tableBody = $('#tabela-dados tbody');
            tableBody.html('');
        }

        tabelaDadosVendas = $('#tabela-dados').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 1000,
            lengthMenu: [
                [100, 500, 1000, -1],
                [100, 500, 1000, "Todos"]
            ],
            scrollY: '400px',
            scrollCollapse: true,
            ajax: {
                url: url_admin + "/vendas/dados/listar",
                type: "POST",
                data: { id: id },
            },
            columns: [
                { data: "data" },
                { data: "hora" },
                { data: "cupom" },
                { data: "pdv" },
                { data: "caixa" },
                { data: "valor_bruto" },
                { data: "valor_desconto" },
                { data: "valor_acrescimo" },
                { data: "valor_liquido" },
                { data: "quantidade_formas" },
                { data: "delivery" },
                { data: "tipo" },
            ],
            order: [
            ],
            language: {
                sProcessing: "Carregando..."
            }
        });
    });


    // Visualizar registros importados
    var tabelaInventario;

    $('#modal-imported').on('show.bs.modal', function (event) {

        var target = $(event.relatedTarget);
        var id = target.data('id');

        if ($.fn.DataTable.isDataTable('#tabela-inventario')) {
            tabelaInventario.destroy(); // Destroi a tabela antiga se já existir

            // Limpa o corpo da tabela e insere uma linha com a mensagem de "Carregando..."
            var tableBody = $('#tabela-inventario tbody');
            tableBody.html('');
        }

        tabelaInventario = $('#tabela-inventario').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 1000,
            lengthMenu: [
                [100, 500, 1000, -1],
                [100, 500, 1000, "Todos"]
            ],
            scrollY: '400px',
            scrollCollapse: true,
            ajax: {
                url: url_admin + "/inventario/dados/listar",
                type: "POST",
                data: { id: id },
            },
            columns: [
                {
                    data: "data",
                    createdCell: function (td, cellData, rowData, row, col) {
                        $(td).addClass('no-wrap');
                    }
                },
                { data: "produto" },
                { data: "unidade" },
                { data: "mov_estoque" },
                { data: "tipo_estoque" },
                { data: "operacao" },
                { data: "tipo" },
            ],
            language: {
                sProcessing: "Carregando..."
            }
        });
    });

    // Cadastro de Clientes
    if ($("#formCliente").is(":visible")){

        $("#formCliente").validate({
            rules: {
                faturamento_folha_valor : {
                    required : function () {
                        return $("[name=faturamento_folha_tipo]:selected").val() == "FIXO";
                    }
                },
                faturamento_financeiro_valor : {
                    required : function () {
                        return $("[name=faturamento_financeiro_tipo]:selected").val() == "FIXO";
                    }
                },
            },
            submitHandler: function(form) {
                loadingButton("#formCliente button[type=submit]");
                form.submit();
            }
        });
    }

    // Cadastro de Fornedores
    if ($("#form-convencao").is(":visible")){

        $("#form-convencao").validate({
            rules: {
                arquivo : {
                    extension : 'pdf'
                },
            },
            messages: {
                arquivo : {
                    extension : 'Extensão inválida. Apenas arquivos PDF.'
                },
            },
            submitHandler: function(form) {
                loadingButton("#form-convencao button[type=submit]");
                form.submit();
            }
        });
    }

    //  INSERIR ITEM NA CONVENÇÃO
    $('#modal-item').on('show.bs.modal', function (event) {

        var target = $(event.relatedTarget);
        var action = target.data('action');
        const body = $(this).find(".modal-body");

        if (action == "insert") {

            $(this).find(".modal-header>.modal-title").html("Inserir novo item");
            body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not(.no-clear)").val("");

        } else if (action=="update") {

            const id = target.data('id');

            $(this).find(".modal-header>.modal-title").html("Editar item");
            body.find("input:not([type=checkbox]):not([type=radio]):not(.no-clear)").val("");

            $.ajax({
                url: url_admin + "/convencoes/itens/find",
                type: "post",
                data : { id },
                dataType: 'json',
                success: function(obj) {
                    $("#item-id").val(id);
                    $("#item-descricao").val(obj.descricao);
                    $("#item-valor").val(obj.valor);
                },
            });
        }

        $("#form-item").validate({
            submitHandler: function(form) {

                loadingButton("#form-item [type=submit]");

                $.ajax({
                    type: "post",
                    url: url_admin + "/convencoes/itens/register",
                    data: {
                        id: $("#item-id").val(),
                        id_convencao: $("#id_convencao").val(),
                        descricao: $("#item-descricao").val(),
                        valor: $("#item-valor").val(),
                        act: action,
                    },
                    dataType: 'json',
                    success: function (response) {

                        // ADICIONAR LINHA NA TABELA
                        $("#table-convencao-itens tbody").append(response.line);

                        // AGORA, ATUALIZE O DATATABLES
                        var table = $('#table-convencao-itens').DataTable();

                        if (response.action == "insert") {

                            // USE O MÉTODO ADD E DRAW() PARA ATUALIZAR A TABELA
                            table.row.add($(response.line)).draw(false);

                            // MOSTRAR MENSAGEM
                            if (response.error == false) {
                                showToastr("Item inserido com sucesso");
                            } else {
                                showToastr("Houve um erro ao tentar inserir o item");
                            }

                            // LIMPAR CAMPOS
                            $("#item-id").val("");
                            $("#item-descricao").val("");
                            $("#item-valor").val("");

                        } else if (response.action == "update") {

                            var line = $("#table-convencao-itens tbody tr#line-" + response.id);
                            var row = table.row(line);

                            row.cell(row.index(), 0).data(response.descricao);
                            row.cell(row.index(), 1).data(response.valor);
                            table.draw(false);

                            // MOSTRAR MENSAGEM
                            if (response.error == false) {
                                showToastr("Item atualizado com sucesso");
                            } else {
                                showToastr("Houve um erro ao tentar atualizar o item");
                            }

                            // FECHE O MODAL
                            var modal = bootstrap.Modal.getInstance(document.getElementById('modal-item'));
                            modal.hide();
                        }

                        // RESTAURAR BOTAO
                        restoreButton("#form-item [type=submit]");

                        return false;
                    }
                });
            }
        });
    });

    // EXCLUIR ITEM NA CONVENÇÃO
    $(document).on('click', '.del-item', function() {

        const $el = $(this);
        const id = $el.data('id'); // ID do item
        const theme = $("body").attr("data-layout-color");

        $.confirm({
            title: 'Confirmação',
            content: 'Deseja realmente excluir este item?',
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

                        $.ajax({
                            url: url_admin + "/convencoes/itens/delete",
                            type: "post",
                            data: {id: id},
                            dataType: 'json',
                            success: function(data) {

                                if (data.error == true) {

                                    showToastr(data.msg, 'error');

                                } else {

                                    line = $("#table-convencao-itens tbody tr#line-" + id);

                                    line.fadeOut('fast', function() {

                                        // ATUALIZAR DATATABLE
                                        var table = $('#table-convencao-itens').DataTable();

                                        table.row(line).remove();
                                        table.draw();

                                        showToastr("Item excluído com sucesso");
                                    });

                                }
                            },
                            error: function(xhr) {
                                console.log("Erro ao inserir item: " + xhr.responseText);
                            },
                        });
                    }
                },
                cancel: {
                    text: 'Não',
                }
            }
        });

    });


    if ($("#form-usuario-novo").is(":visible")) {

        $("#form-usuario-novo").validate({
            rules: {
                login: {
                    required: true,
                    remote: url_admin + "/usuarios/find"
                }
            },
            messages: {
                login: {
                    remote: "Este login já está sendo usado. Por favor use outro."
                }
            },
            submitHandler: function(form) {
                loadingButton("#form-usuario-novo button[type=submit]");
                form.submit();
            }
        });
    }

    $("#form-usuario-editar").validate({
        rules: {
            login: {
                required: true,
                remote: url_admin + "/usuarios/find"
            }
        },
        messages: {
            login: {
                remote: "Este login já está sendo usado. Por favor use outro."
            }
        },
        submitHandler: function(form) {
            loadingButton("#form-usuario-editar button[type=submit]");
            form.submit();
        }
    });

    $("#modal-bloqueio").on('show.bs.modal', function (e) {
        const target = e.relatedTarget;
        const acao = $(target).data('acao');
        const modal = $(this);
        modal.find(".modal-body input[name=acao]").val(acao);

        if (acao == "bloquear") {
            modal.find(".modal-header h5").html("Bloquear Cliente");
            modal.find(".modal-body .display").html("Motivo do Bloqueio");
            modal.find(".modal-footer button[type='submit']").removeClass("btn-success").addClass("btn-danger").html("BLOQUEAR");
        } else {
            modal.find(".modal-header h5").html("Desbloquear Cliente");
            modal.find(".modal-body .display").html("Motivo do Desbloqueio");
            modal.find(".modal-footer button[type='submit']").removeClass("btn-danger").addClass("btn-success").html("DESBLOQUEAR");
        }
    });

    $("#modal-historico").on('show.bs.modal', function (e) {
        const target = e.relatedTarget;
        const id = $(target).data('id');
        const modal = $(this);

        console.log(id);

    //     $.ajax({
    //         url: url_admin + "/clientes/servicos/historico",
    //         type: "post",
    //         data: {id: id},
    //         success: function(data) {
    //             modal.find(".modal-body").html(data);
    //         },
    //         error: function(xhr) {
    //             console.log("Erro ao carregar histórico: " + xhr.responseText);
    //         },
    //     });

    });


    /*
    // Cadastro de Categorias
    $('#modal-categoria').on('shown.bs.modal', function (event) {

        var button = $(event.relatedTarget);
        var action = button.data('action');

        if (action == "insert") {

            var id = button.data('id');

            $(this).find("textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Inserir Nova Categoria");
            $(this).find(".modal-content").find("form").attr("action", url_base + "/categorias/insert");

            if (id) {

                // SE FOR UM SUBCATEGORIA, JÁ SETAR O PAI
                $("#id_pai").val(id);
                $("[name=id_pai]").val(id);
                $("#pai").show();

            } else {

                $("#pai").hide();
                $("#id_pai").val("");
                $("[name=id_pai]").val("");
            }

        } else if (action == "update") {

            var id = button.data('id');
            $(this).find("textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("...");
            $(this).find(".modal-header>.modal-title").html("Editar Categoria");
            $(this).find(".modal-body").find("#input-id").val(id);
            $(this).find(".modal-content").find("form").attr("action", url_base + "/categorias/update");

            $("#pai").hide();
            $("#id_pai").val("");

            $.ajax({
                url: url_base + "/categorias/find",
                type: "post",
                data : { id:id },
                success: function(data) {
                    var obj = $.parseJSON(data);
                    $("input[name=descricao]").val(obj.descricao).trigger("focus").focus();
                    $("input[name=codigo]").val(obj.codigo);
                    $("input[name=ordem]").val(obj.ordem);
                    if (obj.id_pai > 0) {
                        $("#id_pai").val(obj.id_pai); //.selectpicker("refresh");
                    } else {
                        $("#id_pai").val(""); //.selectpicker("refresh");
                    }
                },
            });
        }

        $("#form-categoria").validate({
            rules: {
                descricao  : { required:true },
            },
            submitHandler: function(form) {
                loadingButton();
                form.submit();
            }
        });
    });

    // Cadastro de Marcas
    $('#modal-marca').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');

        if (action=="insert") {

            $(this).find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Inserir Nova Marca");
            $(this).find(".modal-content").find("form").attr("action", url_base+"/marcas/insert");
        }

        else if (action=="update") {

            var id = button.data('id');
            $(this).find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Editar Marca");
            $(this).find(".modal-body").find("#input-id").val(id);
            $(this).find(".modal-content").find("form").attr("action", url_base+"/marcas/update");

            $.ajax({
                url: url_base+"/marcas/find",
                type: "post",
                data : { id:id },
                success: function(data) {
                    var obj = $.parseJSON(data);
                    $("input[name=nome]").val(obj.nome).trigger("focus").focus();
                },
            });
        }

        $("#form-marca").validate({
            rules: {
                nome : { required:true },
            },
            submitHandler: function(form) {
                loadingButton();
                form.submit();
            }
        });
    });

    // Cadastro de Endereços do Cliente
    $('#modal-endereco').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');

        const body = $(this).find(".modal-body");

        if (action == "insert") {

            $(this).find(".modal-header>.modal-title").html("Inserir Novo Endereço");
            $("#form-endereco").attr("action", url_base + "/clientes/enderecos/insert");
            body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=id_cliente])").val("");

        } else if (action == "update") {

            console.log("update!");

            var id = button.data('id');
            $(this).find(".modal-header>.modal-title").html("Editar Endereço");
            $("#form-endereco").attr("action", url_base + "/clientes/enderecos/update");
            body.find("input:not([type=checkbox]):not([type=radio]):not([name=id_cliente])").val("");

            $.ajax({
                url: url_base + "/clientes/enderecos/find",
                type: "post",
                data : { id:id },
                dataType : 'json',
                success: function(obj) {
                    $("#input-id").val(obj.id);

                    body.find("input[name=descricao]").val(obj.descricao);
                    body.find("input[name=cep]").val(obj.cep);
                    body.find("input[name=endereco]").val(obj.endereco);
                    body.find("input[name=numero]").val(obj.numero);
                    body.find("input[name=complemento]").val(obj.complemento);
                    body.find("input[name=bairro]").val(obj.bairro);
                    body.find("input[name=cidade]").val(obj.cidade);
                    body.find("input[name=estado]").val(obj.estado);
                    body.find("input[name=pais]").val(obj.pais);

                    if (obj.principal == 1) {
                        body.find("input[name=principal]").prop("checked", true);
                    } else {
                        body.find("input[name=principal]").prop("checked", false);
                    }

                },
            });
        }

        $("#form-endereco").validate({
            rules: {
                estado: {
                    inArray: ["AC", "AL", "AP", "AM", "BA", "CE", "DF", "ES", "GO", "MA", "MT", "MS", "MG", "PA", "PB", "PR", "PE", "PI", "RJ", "RN", "RS", "RO", "RR", "SC", "SP", "SE", "TO"]
                }
            },
            messages: {
                estado: {
                    inArray: "Estado inválido"
                }
            },
            submitHandler: function(form) {
                loadingButton("#form-endereco button[type=submit]");
                form.submit();
            }
        });

    });

    // Cadastro de Preços no Produto
    $('#modal-fornecedor').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');

        const body = $(this).find(".modal-body");

        if (action == "insert") {

            $(this).find(".modal-header>.modal-title").html("Inserir Novo Fornecedor no Produto");
            $("#form-produto-fornecedor").attr("action", url_base + "/produtos/fornecedor/insert");
            body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=id_produto])").val("");

        } else if (action == "update") {

            var id = button.data('id');
            $(this).find(".modal-header>.modal-title").html("Editar Preço");
            $("#form-produto-fornecedor").attr("action", url_base + "/produtos/fornecedor/update");
            body.find("input:not([type=checkbox]):not([type=radio]):not([name=id_produto])").val("");

            $.ajax({
                url: url_base+"/produtos/fornecedor/find",
                type: "post",
                data : { id:id },
                dataType : 'json',
                success: function(obj) {
                    $("#input-id").val(obj.id);
                    body.find("[name=id_fornecedor]").val(obj.id_fornecedor).trigger('change');
                    body.find("input[name=valor_custo]").val(obj.valor_custo);
                    body.find("input[name=valor_venda]").val(obj.valor_venda);
                    body.find("input[name=desconto_maximo]").val(obj.desconto_maximo);
                    body.find("input[name=sku]").val(obj.sku);
                    body.find("input[name=codigo_barras]").val(obj.codigo_barras);
                    body.find("textarea[name=observacoes]").val(obj.observacoes);
                },
            });
        }

        $("#form-produto-fornecedor").validate({
            submitHandler: function(form) {
                loadingButton("#form-produto-fornecedor button[type=submit]");
                form.submit();
            }
        });
    });
    */




    // Cadastro de Clientes
    if ($("#form-cliente").is(":visible")) {

        $("#form-cliente").validate({
            rules: {
                razao : { required:true },
                descricao : {
                    required: function() {
                        return $("#endereco").val() != "" || $("#cidade").val() != "";
                    }
                },
                cpf   : {
                    cpf: function(element) {
                        return $("#pessoa").val() == "F";
                    }
                },
                cnpj  : {
                    cnpj: function(element) {
                        return $("#pessoa").val() == "J";
                    }
                },
                nascimento: {
                    dateBR: true
                },
                estado: {
                    inArray: ["AC", "AL", "AP", "AM", "BA", "CE", "DF", "ES", "GO", "MA", "MT", "MS", "MG", "PA", "PB", "PR", "PE", "PI", "RJ", "RN", "RS", "RO", "RR", "SC", "SP", "SE", "TO"]
                }
            },
            messages: {
                estado: {
                    inArray: "Estado inválido"
                }
            },
            submitHandler: function(form) {
                loadingButton("#form-cliente button[type=submit]");
                form.submit();
            }
        });
    }

    if ($("#form-edit-cliente").is(":visible")){

        $("#form-edit-cliente").validate({
            rules: {
                razao : { required:true },
                cpf   : {
                    cpf: function(element) {
                        return $("#pessoa").val() == "F";
                    }
                },
                cnpj  : {
                    cnpj: function(element) {
                        return $("#pessoa").val() == "J";
                    }
                },
            },
            submitHandler: function(form) {
                loadingButton("#form-edit-cliente button[type=submit]");
                form.submit();
            }
        });
    }

    // Cadastro de Extintores
    if ($("#form-extintor").is(":visible")) {

        $("#form-extintor").validate({
            submitHandler: function(form) {
                loadingButton("#form-extintor button[type=submit]");
                form.submit();
            }
        });
    }




    // Cadastro de Ramos
    // $('#modal-ramo').on('show.bs.modal', function (event) {

    //     var button = $(event.relatedTarget);
    //     var action = button.data('action');

    //     const modal = $(this);
    //     const body = modal.find(".modal-body");

    //     if (action == "insert") {

    //         modal.find(".modal-header>.modal-title").html("Inserir Novo Ramo");
    //         body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
    //         $("#form-ramo").attr("action", url_admin + "/ramos/insert");

    //     } else if (action == "update") {

    //         var id = button.data('id');
    //         modal.find(".modal-header>.modal-title").html("Editar Ramo");
    //         body.find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
    //         $("#input-id").val(id);
    //         $("#form-ramo").attr("action", app.base + "/ramos/update");

    //         $.ajax({
    //             url: app.base + "/ramos/find",
    //             type: "post",
    //             data : { id:id },
    //             dataType: 'json',
    //             beforeSend: function() {
    //                 body.find("input[name=descricao]").val("Carregando...");
    //                 body.find("select[name=icone]").val("").selectpicker("refresh");
    //             },
    //             success: function(data) {
    //                 body.find("input[name=descricao]").val(data.descricao).trigger("focus").focus();
    //                 body.find("select[name=icone]").val(data.icone).selectpicker("refresh");
    //             },
    //         });
    //     }

    //     $("#form-ramo").validate({
    //         rules: {
    //             descricao : {
    //                 required: true
    //             }
    //         },
    //         submitHandler: function(form) {
    //             loadingButton("#form-ramo button[type=submit]");
    //             form.submit();
    //         }
    //     });
    // });

    // // Cadastro de Fases
    // $('#modal-fase').on('show.bs.modal', function (event) {
    //     var button = $(event.relatedTarget);
    //     var action = button.data('action');

    //     const modal = $(this);
    //     const body = modal.find(".modal-body");

    //     if (action == "insert") {

    //         modal.find(".modal-header>.modal-title").html("Inserir Nova Fase");
    //         body.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
    //         $("#form-fase").attr("action", app.base + "/fases/insert");

    //     } else if (action == "update") {

    //         var id = button.data('id');
    //         modal.find(".modal-header>.modal-title").html("Editar Fase");
    //         body.find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
    //         $("#input-id").val(id);
    //         $("#form-fase").attr("action", app.base + "/fases/update");

    //         $.ajax({
    //             url: app.base + "/fases/find",
    //             type: "post",
    //             data : { id:id },
    //             dataType: 'json',
    //             beforeSend: function() {
    //                 body.find("input[name=descricao]").val("Carregando...");
    //                 body.find("input[name=cor]").val("");
    //                 body.find("#ex-badge").text("");
    //                 body.find("[name=dependencia]").val("");
    //             },
    //             success: function(obj) {
    //                 body.find("input[name=descricao]").val(obj.descricao).trigger("focus").focus();
    //                 body.find("input[name=cor]").val(obj.cor);
    //                 body.find("#ex-badge").css({"background-color":obj.cor}).text(obj.descricao);
    //                 body.find("[name=dependencia]").val(obj.dependencia);
    //                 body.find("[name=observacoes]").val(obj.observacoes);
    //                 if (obj.aberto) {
    //                     body.find("input[name=aberto]").prop("checked", true);
    //                 } else {
    //                     body.find("input[name=aberto]").prop("checked", false);
    //                 }
    //             },
    //         });
    //     }

    //     $("#form-fase").validate({
    //         rules: {
    //             descricao : {
    //                 required: true
    //             }
    //         },
    //         submitHandler: function(form) {
    //             loadingButton("#form-fase button[type=submit]");
    //             form.submit();
    //         }
    //     });
    // });

    // Cadastro de Pedidos
    // if ($("#form-extintor").is(":visible")) {

    //     $("#form-extintor").validate({
    //         submitHandler: function(form) {
    //             loadingButton("#form-extintor button[type=submit]");
    //             form.submit();
    //         }
    //     });

    //     $('#produto').select2({
    //         minimumResultsForSearch: 0,
    //         width: '100%',
    //         theme: "bootstrap5",
    //         dropdownParent: $('#modal-add-item'),
    //     });

    //     // Inicializar os popovers para os botões de "Excluir"
    //     $(document).on('click', '.del-item', function() {

    //         const theme = $("body").attr("data-layout-color");
    //         const id = $(this).data("id");

    //         console.log(id);

    //         $.confirm({
    //             title: 'Confirmação',
    //             content: 'Deseja realmente excluir este item?',
    //             // autoClose: 'cancel|8000',
    //             theme : 'modern ' + theme,
    //             backgroundDismiss: true,
    //             icon : 'fa-solid fa-circle-xmark',
    //             type : 'coral',
    //             typeAnimated : true,
    //             animateFromElement: false,
    //             buttons: {
    //                 confirm: {
    //                     text: 'Sim',
    //                     btnClass: 'btn-coral',
    //                     action: function () {

    //                         $.ajax({
    //                             url: url_base + "/pedidos/itens/delete",
    //                             type: "post",
    //                             data: {id},
    //                             dataType: 'json',
    //                             success: function(data) {

    //                                 if (data.error == true) {

    //                                     showToastr(data.msg, 'error');

    //                                 } else {

    //                                     showToastr("Item excluído com sucesso");

    //                                     if (data.table && Array.isArray(data.table)) {

    //                                         // Verifica se a tabela já foi inicializada e, se sim, destrói a instância anterior
    //                                         if ($.fn.dataTable.isDataTable('#table-itens')) {
    //                                             $("#table-itens").DataTable().clear().destroy();
    //                                         }

    //                                         // Agora inicialize a tabela com as novas configurações
    //                                         var table = $("#table-itens").DataTable({
    //                                             paging: false,
    //                                             columnDefs: [
    //                                                 {
    //                                                     targets: [4, 5, 6, 7],
    //                                                     type: "currency-br",
    //                                                 },
    //                                                 {
    //                                                     targets: [4, 7, 8],
    //                                                     createdCell: function(td, cellData, rowData, row, col) {
    //                                                         $(td).addClass('text-end');
    //                                                     }
    //                                                 },
    //                                             ]
    //                                         });

    //                                         // Limpar todas as linhas existentes na tabela
    //                                         table.clear();

    //                                         // Adicionar as novas linhas
    //                                         data.table.forEach(function(row) {
    //                                             table.row.add([
    //                                                 row.numero,
    //                                                 row.produto,
    //                                                 row.fornecedor,
    //                                                 row.sku,
    //                                                 row.valor,
    //                                                 row.quantidade,
    //                                                 row.desconto,
    //                                                 row.subtotal,
    //                                                 row.botao
    //                                             ]);
    //                                         });

    //                                         // Redesenhar a tabela
    //                                         table.draw();

    //                                     } else {
    //                                         console.error("Nenhum dado retornado ou formato inválido.");
    //                                     }

    //                                     $("#tfoot-total").html(data.total);
    //                                     $("#info-total").html(data.total);
    //                                     if (data.nitens == 0) {
    //                                         $("#info-bar .itens").html("");
    //                                     } else {
    //                                         $("#info-bar .itens").html("(" + data.nitens + (data.nitens > 1 ? " ITENS" : " ITEM") + ")");
    //                                     }

    //                                 }
    //                             },
    //                             error: function(xhr) {
    //                                 alert("Erro ao inserir item: " + xhr.responseText);
    //                             },
    //                         });
    //                     }
    //                 },
    //                 cancel: {
    //                     text: 'Não',
    //                 }
    //             }
    //         });
    //     });

    // }


    // // Cadastro de itens do Produto
    // $('#modal-add-item').on('shown.bs.modal', function (event) {

    //     const body = $(this).find(".modal-body");

    //     $("#produto").on("change", function() {

    //         // BUSCAR INFORMAÇÕES DO PRODUTO
    //         var selectedOption = $(this).find('option:selected');
    //         var preco = selectedOption.data('price');
    //         var desconto = selectedOption.data('discount');

    //         $("#valor_fornecedor").val(formatRealBr(preco, false));
    //         $("#valor").val(formatRealBr(preco, false));
    //         $("#desconto").attr("data-max", desconto);
    //     });

    //     $('#desconto').on('input', function () {
    //         const valorBase = parseValor($('#valor_fornecedor').val());
    //         const desconto = parseValor($(this).val());
    //         if (desconto >= 0) {
    //             const valorDescontado = valorBase - (valorBase * desconto / 100);
    //             $('#valor').val(formatRealBr(valorDescontado, false));
    //             calcularSubtotal();
    //         }
    //     });

    //     $('#valor').on('input', function () {
    //         const valorBase = parseValor($('#valor_fornecedor').val());
    //         const valorDigitado = parseValor($(this).val());

    //         if (valorDigitado < valorBase) {
    //             const desconto = ((valorBase - valorDigitado) / valorBase) * 100;
    //             $('#desconto').val(desconto.toFixed(3).replace('.', ','));
    //         } else {
    //             $('#desconto').val('');
    //         }
    //         calcularSubtotal();
    //     });

    //     $('#quantidade').on('input', function () {
    //         calcularSubtotal();
    //     });

    //     $("#form-add-item").validate({
    //         submitHandler: function(form) {

    //             loadingButton("#form-add-item button[type=submit]");

    //             $.ajax({
    //                 url: url_base + "/pedidos/itens/insert",
    //                 type: "post",
    //                 data: $(form).serialize(),
    //                 dataType: 'json',
    //                 success: function(data) {

    //                     if (data.error) {

    //                         showToastr(data.msg, 'error');

    //                     } else {

    //                         showToastr("Item adicionado com sucesso");

    //                         if (data.table && Array.isArray(data.table)) {
    //                             // var table = $("#table-itens").DataTable();

    //                             // Verifica se a tabela já foi inicializada e, se sim, destrói a instância anterior
    //                             if ($.fn.dataTable.isDataTable('#table-itens')) {
    //                                 $("#table-itens").DataTable().clear().destroy();
    //                             }

    //                             // Agora inicialize a tabela com as novas configurações
    //                             var table = $("#table-itens").DataTable({
    //                                 paging: false,
    //                                 columnDefs: [
    //                                     {
    //                                         targets: [4, 5, 6, 7],
    //                                         type: "currency-br",
    //                                     },
    //                                     {
    //                                         targets: [4, 7, 8],
    //                                         createdCell: function(td, cellData, rowData, row, col) {
    //                                             $(td).addClass('text-end');
    //                                         }
    //                                     },
    //                                 ]
    //                             });

    //                             // Limpar todas as linhas existentes na tabela
    //                             table.clear();

    //                             // Adicionar as novas linhas
    //                             data.table.forEach(function(row) {
    //                                 table.row.add([
    //                                     row.numero,
    //                                     row.produto,
    //                                     row.fornecedor,
    //                                     row.sku,
    //                                     row.valor,
    //                                     row.quantidade,
    //                                     row.desconto,
    //                                     row.subtotal,
    //                                     row.botao
    //                                 ]);
    //                             });

    //                             // Redesenhar a tabela
    //                             table.draw();
    //                         } else {
    //                             console.error("Nenhum dado retornado ou formato inválido.");
    //                         }

    //                         $("#tfoot-total").html(data.total);
    //                         $("#info-total").html(data.total);
    //                         if (data.nitens == 0) {
    //                             $("#info-bar .itens").html("");
    //                         } else {
    //                             $("#info-bar .itens").html("(" + data.nitens + (data.nitens > 1 ? " ITENS" : " ITEM") + ")");
    //                         }

    //                         // Limpar todos os campos, exceto o id_pedido
    //                         $("#form-add-item").find(':input, select, textarea').not('#id_pedido').each(function() {
    //                             if (this.type === 'checkbox' || this.type === 'radio') {
    //                                 this.checked = false; // Desmarca checkboxes e radio buttons
    //                             } else if ($(this).hasClass('select2')) {
    //                                 $(this).val(null).trigger('change'); // Reseta selects com Select2
    //                             } else if (this.tagName.toLowerCase() === 'select') {
    //                                 $(this).prop('selectedIndex', 0); // Reseta selects normais para o primeiro valor
    //                             } else {
    //                                 $(this).val(''); // Limpa campos de texto e textareas
    //                             }
    //                         });
    //                     }

    //                     // Restaurar botão de envio
    //                     restoreButton("#form-add-item button[type=submit]");
    //                 },
    //                 error: function(xhr) {
    //                     alert("Erro ao inserir item: " + xhr.responseText);
    //                     restoreButton("#form-add-item button[type=submit]");
    //                 },
    //             });

    //             return false;
    //         }
    //     });

    // });

    // function parseValor(valor) {
    //     // Converte um valor string com vírgula para float
    //     return parseFloat(valor.replace(/\./g, '').replace(',', '.')) || 0;
    // }

    // function calcularSubtotal() {
    //     const valor = parseValor($('#valor').val());
    //     const quantidade = parseValor($('#quantidade').val());
    //     console.log('Valor:', valor, 'Quantidade:', quantidade);
    //     const subtotal = valor * quantidade;
    //     console.log('Subtotal:', subtotal);
    //     $('#subtotal').val(formatRealBr(subtotal, false)); // Formatar resultado no padrão brasileiro
    // }

    /*



    $("#form-job-receita").validate({
        ignore: ":hidden",
        submitHandler: function(form) {
            loadingButton("#form-job-receita button[type=submit]");
            form.submit();
        }
    });

    $("#form-job-despesa").validate({
        ignore: ":hidden",
        submitHandler: function(form) {
            loadingButton("#form-job-despesa button[type=submit]");
            form.submit();
        }
    });

    $("[name=id_condicao]").on("change", function() {
        id = $(this).val();
        $(".cond").hide();
        $(".cond-"+ id).show();
        if (id != 1) {
            $("input[name=vencimento]").val("");
        }
    });

    // Recalcular Parcelas
    $("input#recalcular").on("change", function(){
        $("#aviso-parcelas").toggle();
    });





    // CLIENTES
    if ($("#form-cliente").is(":visible") || $("#form-edit-cliente").is(":visible")) {

        // Pessoa Física ou Jurídica
        $("[name=pessoa]").on("change", function(){

            val = $(this).val();
            $(".pes").hide();
            $(".pes."+val).show();

            if (val=="F") {
                $("input[name=razao]").attr("placeholder", "Nome Completo");
                $("input[name=nome]").attr("placeholder", "Apelido");
                $("input[name=rg_ie]").attr("placeholder", "RG");
            } else {
                $("input[name=razao]").attr("placeholder", "Razão Social");
                $("input[name=nome]").attr("placeholder", "Nome Fantasia");
                $("input[name=rg_ie]").attr("placeholder", "Inscrição Estadual");
            }
        });

        // Pesquisar CNPJ na API https://receitaws.com.br/api
        $("#searchCNPJ").on("click", (e) => {
            $("#overlay-load").show();
            var cnpj = $("#cnpj").val();

            $.ajax({
                type: "post",
                url: url_admin + "/clientes/cnpj",
                data: {cnpj:cnpj},
                dataType: "json",
                beforeSend: function() {
                    $("input[name=nascimento]").val("...");
                    $("input[name=razao]").val("...");
                    $("input[name=nome]").val("...");
                    $("input[name=cep]").val("...");
                    $("input[name=bairro]").val("...");
                    $("input[name=endereco]").val("...");
                    $("input[name=numero]").val("...");
                    $("input[name=complemento]").val("...");
                    $("input[name=cidade]").val("...");
                    $("input[name=estado]").val("...");
                },
                success: function (response) {

                    $("input[name=nascimento]").val(response.abertura);
                    $("input[name=razao]").val(response.nome);
                    $("input[name=nome]").val(response.fantasia);
                    $("input[name=cep]").val(response.cep);
                    $("input[name=bairro]").val(response.bairro);
                    $("input[name=endereco]").val(response.logradouro);
                    $("input[name=numero]").val(response.numero);
                    $("input[name=complemento]").val(response.complemento);
                    $("input[name=cidade]").val(response.municipio);
                    $("input[name=estado]").val(response.uf);

                    $("#overlay-load").hide();
                }
            });
        });

        $("#form-cliente").validate({
            rules: {
                razao : { required:true },
                descricao : {
                    required: function() {
                        return $("#endereco").val() != "" || $("#cidade").val() != "";
                    }
                },
                cpf   : {
                    cpf: function(element) {
                        return $("#pessoa").val() == "F";
                    }
                },
                cnpj  : {
                    cnpj: function(element) {
                        return $("#pessoa").val() == "J";
                    }
                },
            },
            submitHandler: function(form) {
                loadingButton("#form-cliente button[type=submit]");
                form.submit();
            }
        });

        $("#form-edit-cliente").validate({
            rules: {
                razao : { required:true },
                // cpf   : {
                //     cpf: function(element) {
                //         return $("#pessoa").val() == "F";
                //     }
                // },
                // cnpj  : {
                //     cnpj: function(element) {
                //         return $("#pessoa").val() == "J";
                //     }
                // },
            },
            submitHandler: function(form) {
                loadingButton("#form-edit-cliente button[type=submit]");
                form.submit();
            }
        });
    }


    // JOBS
    $("#form-job").validate({
        submitHandler: function(form) {
            loadingButton("#form-job button[type=submit]");
            form.submit();
        }
    });

    // PROPOSTAS
    $("#form-proposta-update").validate({
        submitHandler: function(form) {
            loadingButton("#form-proposta-update button[type=submit]");
            form.submit();
        }
    });

    // PROPOSTA TENS
    $('#proposta-item').on('show.bs.modal', function (event) {

        const button = $(event.relatedTarget);
        const id = button.data('id');
        const proposta = button.data('proposta');

        const modal = $(this);
        const body = modal.find(".modal-body");

        if (id) {

            modal.find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            modal.find(".modal-header>.modal-title").html("Editar Item da Proposta");
            modal.find(".modal-content").find("form").attr("action", url_admin + "/propostas/item/update");

            body.find("input[name=id]").val(id);

            $.ajax({
                url: url_admin + "/propostas/item/find",
                type: "post",
                data : { id:id },
                dataType: 'json',
                beforeSend: function () {
                    body.find("[name=titulo]").val("Carregando...");
                    body.find("[name=ordem]").val("Carregando...");
                    body.find("[name=texto]").val("Carregando...");
                },
                success: function(response) {
                    body.find("[name=titulo]").val(response.titulo);
                    body.find("[name=ordem]").val(response.ordem);
                    body.find("[name=texto]").val(response.texto);
                },
            });

        } else {

            modal.find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf]):not([name=id_proposta])").val("");
            modal.find(".modal-header>.modal-title").html("Inserir Novo Item na Proposta");
            modal.find(".modal-content").find("form").attr("action", url_admin + "/propostas/item/insert");

            // body.find("input[name=id_proposta]").val(proposta);
        }

        $("#form-proposta-item").validate({
            rules: {
                titulo : { required:true },
            },
            submitHandler: function(form) {
                loadingButton("#proposta-item button[type=submit]");
                form.submit();
            }
        });

        $("#proposta_parametro").on("change", function() {
            const textAtual = $("#proposta-item-texto").val();
            const textoSelecionado = $(this).val();
            $("#proposta-item-texto").val(textAtual + textoSelecionado + "\n");
        });

    });


    // MODELOS DE CONTRATO
    $("#form-modelo").validate({
        submitHandler: function(form) {
            loadingButton("#form-modelo button[type=submit]");
            form.submit();
        }
    });

    // HOSPEDAGENS
    $("#form-hospedagem").validate({
        submitHandler: function(form) {
            loadingButton("#form-hospedagem button[type=submit]");
            form.submit();
        }
    });

    // EMISSÃO DE BOLETO
    $('#emitir-boleto').on('show.bs.modal', function (event) {

        const button = $(event.relatedTarget);
        const parcela = button.data('parcela');

        const modal = $(this);
        const body = modal.find(".modal-body");

        $.ajax({
            url: url_admin + "/boletos/create",
            type: "post",
            data : { id:parcela },
            dataType: 'json',
            beforeSend: function () {
                body.find("[name=cliente]").val("Carregando...");
                body.find("[name=documento]").val("Carregando...");
                body.find("[name=descricao]").val("Carregando...");
                body.find("[name=valor]").val("Carregando...");
                body.find("[name=vencimento]").val("Carregando...");
            },
            success: function(response) {
                if (response.error == true) {

                    body.html('<h2 class="p-5 text-center fw-300">' + response.message + '</h2>');
                    modal.find('.modal-footer').hide();

                } else {

                    body.find("[name=id_parcela]").val(response.id_parcela);
                    body.find("[name=cliente]").val(response.razao);
                    body.find("[name=pessoa]").val(response.pessoa);
                    body.find("[name=documento]").val(response.documento);
                    body.find("[name=descricao]").val(response.descricao);
                    body.find("[name=valor]").val(response.valor);
                    body.find("[name=vencimento]").val(response.vencimento);
                }
            },
        });
    });

    // INFORMAR NÚMERO DO BOLETO
    $('#importar-boleto').on('show.bs.modal', function (event) {

        const button = $(event.relatedTarget);
        const parcela = button.data('parcela');

        const modal = $(this);
        const body = modal.find(".modal-body");

        body.find("[name=id_parcela]").val(parcela);

        $("#search-billet").on("click", function() {

            const numero = modal.find("[name=numero]").val();

            if (numero) {

                modal.find("[name=numero]").removeClass("has-error");

                $.ajax({
                    url: url_admin + "/boletos/search",
                    type: "post",
                    data : {
                        id : parcela,
                        numero : numero,
                    },
                    beforeSend: function () {
                        body.find("#load-billet").html('<h2 class="p-5 text-center fw-300">Carregando informações...</h2>');
                    },
                    success: function(response) {
                        body.find("#load-billet").html(response);

                        $("#form-import").validate({
                            submitHandler: function(form) {
                                loadingButton("#form-import button[type=submit]");
                                form.submit();
                            }
                        });
                    },
                });

            } else {

                modal.find("[name=numero]").addClass("has-error");
            }

        });
    });

    // VISUALIZAR INFORMAÇÕES DO BOLETO
    $('#visualizar-boleto').on('show.bs.modal', function (event) {

        const button = $(event.relatedTarget);
        const parcela = button.data('parcela');

        const modal = $(this);
        const body = modal.find(".modal-body");

        $.ajax({
            url: url_admin + "/boletos/view",
            type: "post",
            data : { id:parcela },
            beforeSend: function () {
                body.html('<h2 class="p-5 text-center fw-300">Carregando informações...</h2>');
            },
            success: function(response) {
                body.html(response);
            },
        });
    });

    // HISTORICO
    // $('#add-history').on('show.bs.modal', function (event) {
    //     var button = $(event.relatedTarget);
    //     var action = button.data('action');

    //     $(this).find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf]:not([name=id_job])").val("");
    //     $(this).find(".modal-header>.modal-title").html("Inserir Novo Histórico");
    //     $(this).find(".modal-content").find("form").attr("action", url_admin + "/jobs/historico/insert");

    // });



    $("#form-add-document").validate({
        rules: {
            descricao : { required:true },
        },
        submitHandler: function(form) {
            loadingButton("#form-add-document button[type=submit]");
            form.submit();
        }
    });

    // TAREFAS
    if ($("#lembretes").is(":visible")) {

        $("#lembretes").sortable({
            update: function(event, ui) {
                var order = $("#lembretes .postit").map(function() {
                    return $(this).attr("data-id");
                }).get();

                $.post(app.base + "/lembrete/order", {ordem:order});
            }
        });

        $('#add-lembrete').on('show.bs.modal', function (event) {

            $(this).find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");

            $("#form-lembrete").validate({
                rules: {
                    texto : { required:true },
                },
                submitHandler: function(form) {
                    loadingButton("#form-lembrete button[type=submit]");
                    form.submit();
                }
            });
        });

        $(".postit .close").on("click", function() {

            const postit = $(this).parent();
            const id = postit.data("id");

            $.post(app.base + "/lembrete/delete", {id:id},
                function (data, textStatus, jqXHR) {
                    postit.fadeOut();
                }
            );
        });
    }

    // SENHAS
    $('#formSenha').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');

        if (action=="insert") {

            $(this).find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf]):not([name=id_cliente])").val("");
            $(this).find(".modal-header>.modal-title").html("Inserir Nova Senha");
            $(this).find(".modal-content").find("form").attr("action", url_admin + "/clientes/senha/insert");

        } else if (action=="update") {

            var id = button.data('id');
            $(this).find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Editar DRE");
            $(this).find(".modal-body").find("#input-id").val(id);
            $(this).find(".modal-content").find("form").attr("action", url_admin + "/clientes/senha/update");

            $.ajax({
                url: url_admin + "/clientes/senha/find",
                type: "post",
                data : { id:id },
                success: function(data) {
                    var obj = $.parseJSON(data);
                    $("input[name=descricao]").val(obj.descricao).trigger("focus").focus();
                    $("input[name=url]").val(obj.url).trigger("focus").focus();
                    $("textarea[name=texto]").val(obj.texto).trigger("focus").focus();
                    $("textarea[name=observacoes]").val(obj.observacoes).trigger("focus").focus();
                },
            });
        }

        $("#form-senha").validate({
            rules: {
                descricao : { required:true },
                texto : { required:true },
            },
            submitHandler: function(form) {
                loadingButton("#formSenha button[type=submit]");
                form.submit();
            }
        });
    });

    // DREs
    $('#modal-dre').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');

        if (action=="insert") {

            $(this).find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Inserir Nova DRE");
            $(this).find(".modal-content").find("form").attr("action", url_admin + "/dre/insert");

        } else if (action=="update") {

            var id = button.data('id');
            $(this).find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Editar DRE");
            $(this).find(".modal-body").find("#input-id").val(id);
            $(this).find(".modal-content").find("form").attr("action", url_admin + "/dre/update");

            $.ajax({
                url: url_admin + "/dre/find",
                type: "post",
                data : { id:id },
                success: function(data) {
                    var obj = $.parseJSON(data);
                    $("input[name=descricao]").val(obj.descricao).trigger("focus").focus();
                    if (obj.subtotal === 1) {
                        $("input[name=subtotal]").prop("checked", true);
                    } else {
                        $("input[name=subtotal]").prop("checked", false);
                    }
                },
            });
        }

        $("#formCadDre").validate({
            rules: {
                descricao : { required:true },
            },
            submitHandler: function(form) {
                loadingButton("#formCadDre button[type=submit]");
                form.submit();
            }
        });
    });

    // TIPO DE CONTA
    $('#formTipo').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');

        if (action=="insert") {

            $(this).find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Inserir Novo Tipo de Conta");
            $(this).find(".modal-content").find("form").attr("action", url_admin + "/tipos-conta/insert");

        } else if (action=="update") {

            var id = button.data('id');
            $(this).find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Editar Tipo de Conta");
            $(this).find(".modal-body").find("#input-id").val(id);
            $(this).find(".modal-content").find("form").attr("action", url_admin + "/tipos-conta/update");

            $.ajax({
                url: url_admin + "/tipos-conta/find",
                type: "post",
                data : { id:id },
                success: function(data) {
                    var obj = $.parseJSON(data);
                    $("input[name=codigo]").val(obj.codigo).trigger("focus").focus();
                    $("input[name=descricao]").val(obj.descricao);
                    $("#ck-" + obj.tipo).prop("checked", true);
                },
            });
        }

        $("#formCadTipo").validate({
            submitHandler: function(form) {
                loadingButton("#formCadTipo button[type=submit]");
                form.submit();
            }
        });
    });

    // PLANO DE CONTAS
    $('#formGrupo').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');

        if (action=="insert") {

            $(this).find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Inserir Novo Grupo");
            $(this).find(".modal-content").find("form").attr("action", url_admin + "/grupos/insert");

        } else if (action=="update") {

            var id = button.data('id');
            $(this).find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Editar Grupo");
            $(this).find(".modal-body").find("#input-id").val(id);
            $(this).find(".modal-content").find("form").attr("action", url_admin + "/grupos/update");

            $.ajax({
                url: url_admin + "/grupos/find",
                type: "post",
                data : { id:id },
                success: function(data) {
                    var obj = $.parseJSON(data);
                    $("input[name=codigo]").val(obj.codigo).trigger("focus").focus();
                    $("input[name=descricao]").val(obj.descricao);
                    $("select[name=id_tipo]").val(obj.id_tipo).selectpicker("refresh");
                    $("select[name=id_dre]").val(obj.id_dre).selectpicker("refresh");
                },
            });
        }

        $("#form-grupo").validate({
            rules: {
                descricao : { required:true },
                id_tipo : { required:true },
                id_dre : { required:true },
            },
            submitHandler: function(form) {
                loadingButton("#form-grupo button[type=submit]");
                form.submit();
            }
        });
    });

    // CONTA BANCÁRIA
    $('#formConta').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');

        if (action=="insert") {

            $(this).find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Inserir Nova Conta Bancária");
            $(this).find(".modal-content").find("form").attr("action", url_admin + "/contas/insert");

        } else if (action=="update") {

            var id = button.data('id');
            $(this).find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Editar Conta Bancária");
            $(this).find(".modal-body").find("#input-id").val(id);
            $(this).find(".modal-content").find("form").attr("action", url_admin + "/contas/update");

            $.ajax({
                url: url_admin + "/contas/find",
                type: "post",
                data : { id:id },
                success: function(data) {
                    var obj = $.parseJSON(data);

                    console.log(obj);

                    $("input[name=nome]").val(obj.nome).trigger("focus").focus();
                    $("[name=status]").val(obj.status).selectpicker("refresh");
                    $("input[name=agencia]").val(obj.agencia);
                    $("input[name=conta]").val(obj.conta);
                    $("[name=id_banco]").val(obj.id_banco).selectpicker("refresh");
                    $("[name=tipo]").val(obj.tipo).selectpicker("refresh");
                    $("input[name=saldo_inicial]").val(obj.saldo_inicial);
                    $("input[name=data_inicial]").val(obj.data_inicial);

                    if (obj.padrao_receita) {
                        $("input[name=padrao_receita]").prop("checked", true);
                    } else {
                        $("input[name=padrao_receita]").prop("checked", false);
                    }

                    if (obj.padrao_despesa) {
                    $("input[name=padrao_despesa]").prop("checked", true);
                    } else {
                        $("input[name=padrao_despesa]").prop("checked", false);
                    }
                },
            });
        }

        $("#formCadConta").validate({
            submitHandler: function(form) {
                loadingButton("#formCadConta button[type=submit]");
                form.submit();
            }
        });
    });

    // CONDIÇÕES DE PAGAMENTO
    $('#formCondicao').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');

        if (action=="insert") {

            $(this).find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Inserir Nova Condição de Pagamento");
            $(this).find(".modal-content").find("form").attr("action", app.base + "/condicoes/insert");
        }

        else if (action=="update") {

            var id = button.data('id');
            $(this).find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Editar Condição de Pagamento");
            $(this).find(".modal-body").find("#input-id").val(id);
            $(this).find(".modal-content").find("form").attr("action", app.base + "/condicoes/update");

            $.ajax({
                url: app.base + "/condicoes/find",
                type: "post",
                data : { id:id },
                success: function(data) {
                    var obj = $.parseJSON(data);
                    $("input[name=descricao]").val(obj.descricao).trigger("focus").focus();
                    $("select[name=status]").val(obj.status);
                    $("input[name=dias]").val(obj.dias);
                },
            });
        }

        $("#formCadCondicao").validate({
            rules: {
                descricao  : { required:true },
            },
            submitHandler: function(form) {
                loadingButton();
                form.submit();
            }
        });
    });

    // FORMAS DE PAGAMENTO
    $('#formForma').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var action = button.data('action');

        if (action=="insert") {

            $(this).find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Inserir Nova Forma de Pagamento");
            $(this).find(".modal-content").find("form").attr("action", url_admin + "/formas/insert");
        }

        else if (action=="update") {

            var id = button.data('id');
            $(this).find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
            $(this).find(".modal-header>.modal-title").html("Editar Forma de Pagamento");
            $(this).find(".modal-body").find("#input-id").val(id);
            $(this).find(".modal-content").find("form").attr("action", url_admin + "/formas/update");

            $.ajax({
                url: url_admin + "/formas/find",
                type: "post",
                data : { id:id },
                beforeSend: function (data) {
                    $("input[name=descricao]").val("Carregando...");
                },
                success: function(data) {
                    var obj = $.parseJSON(data);
                    $("input[name=descricao]").val(obj.descricao).trigger("focus").focus();
                    $("select[name=status]").val(obj.status);
                    if (obj.baixa_automatica==1) {
                        $("input[name=baixa_automatica]").attr("checked", true);
                    } else {
                        $("input[name=baixa_automatica]").removeAttr("checked");
                    }
                },
            });
        }

        $("#formCadForma").validate({
            rules: {
                descricao : { required:true },
            },
            submitHandler: function(form) {
                loadingButton();
                form.submit();
            }
        });
    });







    // BAIXAR PARCELA
    $('#baixar-parcela').on('show.bs.modal', function (event) {

        const t = $(event.relatedTarget);

        console.log(t);

        const body = $(this).find(".modal-body");

        body.find("[name=id]").val(t.data("id"));
        body.find("[name=valor]").val(t.data("valor"));

        $("#formBaixarParcela").validate({
            submitHandler: function(form) {
                loadingButton("#formBaixarParcela [type=submit]");
                form.submit();
            }
        });
    });

    // DESPESAS (PARCELAS)
    if ($("#tableDespesasParcelas").is(":visible")) {
        $('#tableDespesasParcelas').DataTable({
            order: [],
            columnDefs  : [
                { orderable:false, targets:0 },
                { type:'date-br', targets:1 },
                { type:'currency-br', targets:3 },
                // { orderable:false, targets:5 }
            ],
        });
    }

    // ESCOLHER FILTRO
    $("select.date-select").on("change", function() {

        var val = $(this).val();
        var div = $(this).parent().parent().find(".date-personal");
        var select = $(this).parent().parent().find(".date-personal");

        if (val=="personal") {
            div.show();
        } else {
            div.hide();
        }
    });







    // // Estabelecimentos
    // $('#formEstabelecimento').on('show.bs.modal', function (event) {
    //     var button = $(event.relatedTarget);
    //     var action = button.data('action');

    //     if (action=="insert") {

    //         $(this).find("select:not([name=status]),textarea,input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
    //         $(this).find(".modal-header>.modal-title").html("Inserir Novo Estabelecimento");
    //         $(this).find(".modal-content").find("form").attr("action", url_admin + "/estabelecimentos/insert");

    //     } else if (action=="update") {

    //         var id = button.data('id');
    //         $(this).find("input:not([type=checkbox]):not([type=radio]):not([name=csrf])").val("");
    //         $(this).find(".modal-header>.modal-title").html("Editar Estabelecimento");
    //         $(this).find(".modal-body").find("#input-id").val(id);
    //         $(this).find(".modal-content").find("form").attr("action", url_admin + "/estabelecimentos/update");

    //         $.ajax({
    //             url: url_admin + "/estabelecimentos/find",
    //             type: "post",
    //             data : { id:id },
    //             success: function(data) {
    //                 var obj = $.parseJSON(data);
    //                 $("[name=id_modalidade]").val(obj.id_modalidade);
    //                 $("input[name=nome]").val(obj.nome).trigger("focus").focus();
    //             },
    //         });
    //     }

    //     $("#formCadEstabelecimento").validate({
    //         rules: {
    //             id_modalidade : { required:true },
    //             nome : { required:true },
    //         },
    //         submitHandler: function(form) {
    //             loadingButton("#formCadEstabelecimento button[type=submit]");
    //             form.submit();
    //         }
    //     });
    // });

    */

});