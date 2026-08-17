"use strict";

$(document).ready(function () {
    const config = window.clienteFormConfig || {};
    const $form = $("#form-cliente-novo, #form-cliente-editar").first();

    if (!$form.length) {
        return;
    }

    applyMasks($form);

    const $pessoa = $form.find("[name=pessoa]");
    const $documento = $form.find("[name=documento]");
    const $searchCnpj = $form.find("#searchCNPJ");
    const $rgIe = $form.find("[name=rg_ie]");
    const $razao = $form.find("[name=razao]");
    const $nome = $form.find("[name=nome]");
    const $nascimento = $form.find("[name=nascimento]");
    const estadosValidos = Array.isArray(config.estados) ? config.estados : [];
    let validator = null;

    function syncEstadoRules() {
        if (!validator) {
            return;
        }

        const estadoRules = {};

        if (estadosValidos.length) {
            estadoRules.inArray = estadosValidos;
        }

        $form.find("[name=estado]").rules("remove", "inArray");

        if (Object.keys(estadoRules).length) {
            $form.find("[name=estado]").rules("add", estadoRules);
        }
    }

    function syncPersonFields() {
        const pessoa = String($pessoa.val() || "J");
        const isFisica = pessoa === "F";
        const isJuridica = pessoa === "J";

        if (isFisica) {
            $documento.css({
                "border-top-right-radius": "0.375rem",
                "border-bottom-right-radius": "0.375rem"
            });
        } else {
            $documento.css({
                "border-top-right-radius": "",
                "border-bottom-right-radius": ""
            });
        }

        if (isFisica) {
            $documento.attr("placeholder", "CPF");
            $razao.attr("placeholder", "Nome Completo");
            $nome.attr("placeholder", "Apelido");
            $rgIe.attr("placeholder", "RG");
            $nascimento.attr("placeholder", "dd/mm/aaaa");
        } else {
            $documento.attr("placeholder", "CNPJ");
            $razao.attr("placeholder", "Razao Social");
            $nome.attr("placeholder", "Nome Fantasia");
            $rgIe.attr("placeholder", "Inscricao Estadual");
            $nascimento.attr("placeholder", "dd/mm/aaaa");
        }

        syncEstadoRules();
    }

    function fillCompanyData(data) {
        if (!data) {
            return;
        }

        if (data.pessoa) {
            $pessoa.val(data.pessoa).trigger("change");
        }

        if (data.documento) {
            $documento.val(data.documento).trigger("input");
        }

        if (data.razao) {
            $razao.val(data.razao);
        }

        if (data.nome) {
            $nome.val(data.nome);
        }

        if (data.nascimento) {
            $nascimento.val(data.nascimento);
        }

        if (data.contato) {
            $form.find("[name=contato]").val(data.contato);
        }

        if (data.telefone) {
            $form.find("[name=telefone]").val(data.telefone).trigger("input");
        }

        if (data.whatsapp) {
            $form.find("[name=whatsapp]").val(data.whatsapp).trigger("input");
        }

        if (data.email) {
            $form.find("[name=email]").val(data.email);
        }

        if (data.site) {
            $form.find("[name=site]").val(data.site);
        }

        if (data.cep) {
            $form.find("[name=cep]").val(data.cep);
        }

        if (data.endereco) {
            $form.find("[name=endereco]").val(data.endereco);
        }

        if (data.numero) {
            $form.find("[name=numero]").val(data.numero);
        }

        if (data.complemento) {
            $form.find("[name=complemento]").val(data.complemento);
        }

        if (data.bairro) {
            $form.find("[name=bairro]").val(data.bairro);
        }

        if (data.cidade) {
            $form.find("[name=cidade]").val(data.cidade);
        }

        if (data.estado) {
            $form.find("[name=estado]").val(data.estado);
        }

        if (data.pais) {
            $form.find("[name=pais]").val(data.pais);
        }
    }

    if ($form.length && $.fn.validate) {
        validator = $form.data("validator") || $form.validate();

        validator.settings.messages = {
            ...(validator.settings.messages || {}),
            documento: {
                required: "Informe o documento.",
                cpfBR: "Informe um CPF valido.",
                cnpjBR: "Informe um CNPJ valido.",
            },
            estado: {
                inArray: "Informe uma UF valida.",
            },
            razao: {
                required: "Informe a razao social ou nome.",
            }
        };

        validator.settings.submitHandler = function (form) {
            const $submitButton = $(form).find('button[type="submit"], input[type="submit"]').filter(":enabled:visible").first();
            loadingButton($submitButton.length ? $submitButton : null);
            form.submit();
        };

        $form.find("[name='email']").rules("add", {
            email: true
        });
    }

    bindPessoaForm($form, {
        validator: validator
    });

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
            data: {
                cnpj: documento
            },
            beforeSend: function () {
                loadingButton($searchCnpj, false);
            },
            success: function (response) {
                if (response && response.error) {
                    notify(response.message || "Nao foi possivel consultar o CNPJ.", "danger");
                    return;
                }

                fillCompanyData(response.data || {});
                setTimeout(function () {
                    $razao.trigger("focus");
                }, 0);
                notify(response.message || "Dados carregados com sucesso.", "success");
            },
            error: function () {
                notify("Erro ao consultar o CNPJ.", "danger");
            },
            complete: function () {
                loadingButton($searchCnpj, true);
            }
        });
    });
});
