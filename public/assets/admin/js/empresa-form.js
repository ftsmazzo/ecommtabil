"use strict";

$(document).ready(function () {
    const config = window.empresaFormConfig || {};
    const $form = $("#form-empresa-novo, #form-empresa-editar").first();

    if (!$form.length) {
        return;
    }

    applyMasks($form);

    const $clienteSelect = $form.find("#id_cliente");
    if ($clienteSelect.length && $.fn.selectpicker) {
        $clienteSelect.selectpicker({
            liveSearch: true,
            liveSearchNormalize: true,
            width: "100%",
            noneSelectedText: "Selecione um cliente...",
        });
    }

    const $pessoa     = $form.find("[name=pessoa]");
    const $documento  = $form.find("[name=documento]");
    const $searchCnpj = $form.find("#searchCNPJ");
    const $rgIe       = $form.find("[name=rg_ie]");
    const $razao      = $form.find("[name=razao]");
    const $nome       = $form.find("[name=nome]");
    const $nascimento = $form.find("[name=nascimento]");

    function syncPersonFields() {
        const pessoa = String($pessoa.val() || "J");
        const isFisica = pessoa === "F";

        $documento.css({
            "border-top-right-radius":    isFisica ? "0.375rem" : "",
            "border-bottom-right-radius": isFisica ? "0.375rem" : "",
        });

        $documento.attr("placeholder", isFisica ? "CPF" : "CNPJ");
        $razao.attr("placeholder",     isFisica ? "Nome Completo" : "Razao Social");
        $nome.attr("placeholder",      isFisica ? "Apelido" : "Nome Fantasia");
        $rgIe.attr("placeholder",      isFisica ? "RG" : "Inscricao Estadual");
        $nascimento.attr("placeholder", "dd/mm/aaaa");
    }

    function fillCompanyData(data) {
        if (!data) { return; }
        if (data.pessoa)      { $pessoa.val(data.pessoa).trigger("change"); }
        if (data.documento)   { $documento.val(data.documento).trigger("input"); }
        if (data.razao)       { $razao.val(data.razao); }
        if (data.nome)        { $nome.val(data.nome); }
        if (data.nascimento)  { $nascimento.val(data.nascimento); }

        const fields = ["contato","telefone","whatsapp","email","site",
                        "cep","endereco","numero","complemento","bairro","cidade","estado","pais"];
        fields.forEach(function (f) {
            if (data[f]) { $form.find("[name=" + f + "]").val(data[f]).trigger("input"); }
        });
    }

    let validator = null;

    if ($form.length && $.fn.validate) {
        validator = $form.data("validator") || $form.validate();
        validator.settings.submitHandler = function (form) {
            const $btn = $(form).find('button[type="submit"], input[type="submit"]').filter(":enabled:visible").first();
            loadingButton($btn.length ? $btn : null);
            form.submit();
        };
        $form.find("[name='email']").rules("add", { email: true });
    }

    bindPessoaForm($form, { validator: validator });
    $pessoa.on("change", syncPersonFields);
    syncPersonFields();

    $searchCnpj.on("click", function () {
        const documento = String($documento.val() || "").replace(/\D/g, "");

        if (!documento) {
            notify("Informe um CNPJ para consulta.", "warning");
            return;
        }

        $.ajax({
            url: config.findUrl || "",
            method: "POST",
            dataType: "json",
            data: { cnpj: documento },
            beforeSend: function () { loadingButton($searchCnpj, false); },
            success: function (response) {
                if (response && response.error) {
                    notify(response.message || "Nao foi possivel consultar o CNPJ.", "danger");
                    return;
                }
                fillCompanyData(response.data || {});
                setTimeout(function () { $razao.trigger("focus"); }, 0);
                notify(response.message || "Dados carregados com sucesso.", "success");
            },
            error: function () { notify("Erro ao consultar o CNPJ.", "danger"); },
            complete: function () { loadingButton($searchCnpj, true); }
        });
    });

    // Valida CPF no modal de responsável
    if (typeof window.registerAjaxModalInitializer === "function") {
        window.registerAjaxModalInitializer(function ($scope) {
            const $f = $scope.find("#form-empresa-responsavel").first();
            if (!$f.length || $f.data("cpfRuleBound")) { return; }
            $f.data("cpfRuleBound", true);
            const $cpf = $f.find("#er-cpf");
            if ($cpf.length && $.fn.validate) {
                const v = $f.data("validator") || $f.validate();
                $cpf.rules("add", { cpfBR: true, messages: { cpfBR: "Informe um CPF válido." } });
            }
        });
    }

    // Delete responsável via AJAX
    if (config.responsavelDeleteUrl) {
        $(document).off("click.deleteResponsavel").on("click.deleteResponsavel", ".btn-delete-responsavel", function () {
            const $btn = $(this);
            const id   = $btn.data("id");
            const theme = $("body").attr("data-layout-color");

            $.confirm({
                title: "Confirmação",
                content: "Deseja realmente excluir este responsável?",
                theme: "modern " + theme,
                backgroundDismiss: true,
                icon: "fa-solid fa-circle-xmark",
                type: "coral",
                typeAnimated: true,
                animateFromElement: false,
                buttons: {
                    confirm: {
                        text: "Sim",
                        btnClass: "btn-coral",
                        action: function () {
                            loadingButton($btn, false);
                            $.ajax({
                                url: config.responsavelDeleteUrl,
                                method: "POST",
                                dataType: "json",
                                data: { id: id, csrf: config.csrf },
                                success: function (res) {
                                    if (res && res.error) {
                                        notify(res.message || "Erro ao excluir.", "danger");
                                        loadingButton($btn, true);
                                        return;
                                    }
                                    $('tr[data-responsavel-id="' + id + '"]').fadeOut(200, function () {
                                        $(this).remove();
                                        if ($("#responsaveis-table-wrap tbody tr").length === 0) {
                                            location.reload();
                                        }
                                    });
                                    notify(res.message || "Responsável removido.", "success");
                                },
                                error: function () {
                                    notify("Erro ao excluir responsável.", "danger");
                                    loadingButton($btn, true);
                                }
                            });
                        }
                    },
                    cancel: { text: "Não" }
                }
            });
        });
    }
});
