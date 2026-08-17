"use strict";

function normalizePessoaType(value) {
    return String(value || "J").trim().toUpperCase() === "F" ? "F" : "J";
}

function syncPessoaLabels($form, pessoa) {
    const type = normalizePessoaType(pessoa);

    $form.find(".pes").addClass("d-none");
    $form.find(`.pes.${type}`).removeClass("d-none none");

    return type;
}

function syncPessoaDocumentoMask($input, pessoa) {
    const type = normalizePessoaType(pessoa);

    $input.unmask();

    if (type === "F") {
        $input.mask("000.000.000-00");
        return type;
    }

    const alphaNumericMaskOptions = {
        translation: {
            A: { pattern: /[A-Za-z0-9]/ },
            0: { pattern: /[0-9]/ }
        }
    };

    const cnpjMask = "AA.AAA.AAA/AAAA-00";

    $input.mask(cnpjMask, {
        ...alphaNumericMaskOptions,
        onKeyPress: function (val, e, field, options) {
            const current = String(field.val() || "");
            const upper = current.toUpperCase();

            if (current !== upper) {
                field.val(upper);
            }

            field.mask(cnpjMask, options);
        }
    });

    return type;
}

function syncPessoaDocumentoRules($input, validator, pessoa) {
    if (!validator) {
        return;
    }

    const type = normalizePessoaType(pessoa);

    $input.rules("remove", "cpfBR cnpjBR");

    if (type === "F") {
        $input.rules("add", {
            cpfBR: true
        });
        return;
    }

    $input.rules("add", {
        cnpjBR: true
    });
}

function syncPessoaCnpjButton($button, $input, pessoa) {
    if (!$button || !$button.length) {
        return;
    }

    const type = normalizePessoaType(pessoa);
    const documento = String($input.val() || "").replace(/\D/g, "");
    const enabled = type === "J" && documento.length === 14;

    $button
        .prop("disabled", !enabled)
        .attr("title", enabled ? "Buscar dados do CNPJ" : "Informe um CNPJ válido para buscar");
}

function bindPessoaForm($form, options = {}) {
    $form = $form && $form.length ? $form : $();

    if (!$form.length) {
        return null;
    }

    const settings = {
        pessoaSelector: "[name=pessoa]",
        documentoSelector: "[name=documento]",
        cnpjButtonSelector: "#searchCNPJ",
        onChange: null,
        ...options
    };

    const $pessoa = $form.find(settings.pessoaSelector).first();
    const $documento = $form.find(settings.documentoSelector).first();
    const $cnpjButton = settings.cnpjButtonSelector ? $form.find(settings.cnpjButtonSelector).first() : $();

    if (!$pessoa.length || !$documento.length) {
        return null;
    }

    function sync() {
        const pessoa = normalizePessoaType($pessoa.val());
        const isFisica = pessoa === "F";
        const isJuridica = pessoa === "J";

        syncPessoaLabels($form, pessoa);
        syncPessoaDocumentoMask($documento, pessoa);
        syncPessoaDocumentoRules($documento, settings.validator || null, pessoa);
        syncPessoaCnpjButton($cnpjButton, $documento, pessoa);

        if (typeof settings.onChange === "function") {
            settings.onChange({
                $form,
                $pessoa,
                $documento,
                $cnpjButton,
                pessoa,
                isFisica,
                isJuridica
            });
        }
    }

    $pessoa.on("change", sync);
    $documento.on("input blur", function () {
        syncPessoaCnpjButton($cnpjButton, $documento, $pessoa.val());
    });

    sync();

    return {
        $form,
        $pessoa,
        $documento,
        $cnpjButton,
        sync
    };
}

function uppers(elements, root) {
    var $root = root ? $(root) : $(document);

    if (elements !== "") {
        if (elements === "*") {
            $root.find("input, select, textarea").prependClass("input-upper");
            return;
        }

        elements.split(",").forEach(function (name) {
            $root.find("[name='" + name + "']").prependClass("input-upper");
        });
    }
}

function requireds(elements, root) {
    var $root = root ? $(root) : $(document);

    if (elements === "") {
        return;
    }

    var appendRequiredMark = function ($label) {
        if (!$label || !$label.length || $label.find(".required-mark").length) {
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

        return;
    }

    elements.split(",").forEach(function (name) {
        markRequired(name);
    });
}

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

function limparCepForm($form) {
    $form.find("[name=rua], #rua").val("");
    $form.find("[name=endereco], #endereco").val("");
    $form.find("[name=logradouro], #logradouro").val("");
    $form.find("[name=bairro], #bairro").val("");
    $form.find("[name=cidade], #cidade").val("");
    $form.find("[name=uf], #uf").val("");
    $form.find("[name=estado], #estado").val("");
    $form.find("[name=pais], #pais").val("");
}

function loadingCepForm($form) {
    $form.find("[name=rua], #rua").val("...");
    $form.find("[name=endereco], #endereco").val("...");
    $form.find("[name=logradouro], #logradouro").val("...");
    $form.find("[name=bairro], #bairro").val("...");
    $form.find("[name=cidade], #cidade").val("...");
    $form.find("[name=uf], #uf").val("...");
    $form.find("[name=estado], #estado").val("...");
    $form.find("[name=pais], #pais").val("...");
}

function preencherCepForm($form, dados) {
    const logradouro = dados.logradouro || "";
    const bairro = dados.bairro || "";
    const cidade = dados.localidade || "";
    const uf = dados.uf || "";

    $form.find("[name=rua], #rua").val(logradouro);
    $form.find("[name=endereco], #endereco").val(logradouro);
    $form.find("[name=logradouro], #logradouro").val(logradouro);
    $form.find("[name=bairro], #bairro").val(bairro);
    $form.find("[name=cidade], #cidade").val(cidade);

    const $uf = $form.find("[name=uf], #uf");
    const $estado = $form.find("[name=estado], #estado");

    $uf.val(uf).trigger("change");
    $estado.val(uf).trigger("change");

    if ($uf.hasClass("selectpicker")) {
        $uf.selectpicker("refresh");
    }

    if ($estado.hasClass("selectpicker")) {
        $estado.selectpicker("refresh");
    }

    $form.find("[name=pais], #pais").val("Brasil");

    const $numero = $form.find("[name=numero], #numero").first();

    if ($numero.length) {
        $numero.trigger("focus");
    }
}

function buscarCep(cep, onSuccess, onError) {
    const normalizedCep = String(cep || "").replace(/\D/g, "");

    if (!normalizedCep) {
        if (typeof onError === "function") {
            onError("empty");
        }
        return;
    }

    if (!/^[0-9]{8}$/.test(normalizedCep)) {
        if (typeof onError === "function") {
            onError("invalid");
        }
        return;
    }

    $.getJSON(`https://viacep.com.br/ws/${normalizedCep}/json/?callback=?`, function (dados) {
        if (!("erro" in dados)) {
            if (typeof onSuccess === "function") {
                onSuccess(dados);
            }
            return;
        }

        if (typeof onError === "function") {
            onError("not_found");
        }
    }).fail(function () {
        if (typeof onError === "function") {
            onError("request_error");
        }
    });
}

function applyCepLookup($field) {
    const $input = $($field);

    if (!$input.length) {
        return;
    }

    const $form = $input.closest("form");

    if (!$form.length) {
        return;
    }

    const hasEnderecoFields =
        $form.find("[name=endereco], #endereco, [name=logradouro], #logradouro, [name=rua], #rua").length &&
        $form.find("[name=bairro], #bairro").length &&
        $form.find("[name=cidade], #cidade").length &&
        $form.find("[name=estado], #estado, [name=uf], #uf").length;

    if (!hasEnderecoFields) {
        return;
    }

    const cep = String($input.val() || "").replace(/\D/g, "");

    if (!cep) {
        limparCepForm($form);
        return;
    }

    if (!/^[0-9]{8}$/.test(cep)) {
        limparCepForm($form);
        notify("Formato de CEP invalido.", "warning");
        return;
    }

    loadingCepForm($form);

    buscarCep(cep, function (dados) {
        preencherCepForm($form, dados);
    }, function (reason) {
        limparCepForm($form);

        if (reason === "not_found") {
            notify("CEP nao encontrado.", "warning");
        } else if (reason === "request_error") {
            notify("Erro ao consultar o CEP.", "danger");
        }
    });
}
