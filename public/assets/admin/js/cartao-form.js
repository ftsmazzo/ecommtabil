$(function () {
    const $pageForm = $("#form-cartao-novo");
    const $pageCodigo = $("#codigo_unico");
    const $select = $("#id_cliente");
    const $modal = $("#modal-cliente-rapido");
    const $form = $("#form-cliente-rapido");
    const $submit = $("#btn-salvar-cliente-rapido");
    const $quickDocumento = $("#cliente_quick_documento");
    const $trocaModal = $("#modal-trocar-cliente");
    const $trocaSelect = $("#cliente_troca_id");
    const $novoCartaoModal = $("#modal-novo-cartao-cliente");
    const $novoCartaoForm = $("#form-cartao-cliente");
    const $novoCartaoCodigo = $("#cartao_novo_codigo");
    const $novoCartaoTokenNfc = $("#cartao_novo_token_nfc");
    const pageCodigoUrl       = String($pageForm.data("codigoCheckUrl") || "");
    const pageTokenNfcUrl     = String($pageForm.data("tokenNfcCheckUrl") || "");
    const novoCartaoCodigoUrl = String($novoCartaoForm.data("codigoCheckUrl") || "");
    const novoCartaoTokenNfcUrl = String($novoCartaoForm.data("tokenNfcCheckUrl") || "");
    const quickUrl            = String($pageForm.data("quickCreateUrl") || "");
    const clienteObrigatorio  = !!$pageForm.data("clienteObrigatorio");
    let quickSubmitting = false;
    let novoCartaoSubmitting = false;

    if ($select.length && $.fn.selectpicker) {
        if ($.fn.selectpicker.Constructor) {
            $.fn.selectpicker.Constructor.DEFAULTS.liveSearchNormalize = true;
        }

        if (typeof window.enhanceSelectpickerSearch === "function") {
            window.enhanceSelectpickerSearch($select);
        }

        $select.selectpicker({
            liveSearch: true,
            liveSearchNormalize: true,
            size: 8,
            noneSelectedText: "Selecione um cliente"
        });
    }

    if ($trocaSelect.length && $.fn.selectpicker) {
        $trocaSelect.selectpicker({
            liveSearch: true,
            liveSearchNormalize: true,
            size: 8,
            noneSelectedText: "Selecione um cliente"
        });
    }

    if ($.validator && !$.validator.methods.cpfOuCnpj) {
        $.validator.addMethod("cpfOuCnpj", function (value, element) {
            const digits = String(value || "").replace(/\D/g, "");

            if (!digits) {
                return true;
            }

            if (digits.length === 11 && $.validator.methods.cpfBR) {
                return $.validator.methods.cpfBR.call(this, digits, element);
            }

            if (digits.length === 14 && $.validator.methods.cnpjBR) {
                return $.validator.methods.cnpjBR.call(this, digits, element);
            }

            return false;
        }, "Informe um CPF ou CNPJ valido.");
    }

    if ($.validator && !$.validator.methods.codigoCartaoValido) {
        $.validator.addMethod("codigoCartaoValido", function (value) {
            const normalized = normalizeCartaoCodigoValue(value);

            return normalized !== "" && normalized !== "00000";
        }, "Informe um codigo maior que zero.");
    }

    function extractCartaoCodigoRaw(value) {
        return String(value || "")
            .replace(/\D/g, "")
            .replace(/^0+/, "")
            .slice(-6);
    }

    function normalizeCartaoCodigoValue(value) {
        const raw = extractCartaoCodigoRaw(value);
        return raw ? raw.padStart(6, "0") : "";
    }

    function moveCursorToEnd(input) {
        if (!input || typeof input.setSelectionRange !== "function") {
            return;
        }

        const length = String(input.value || "").length;
        input.setSelectionRange(length, length);
    }

    function formatCartaoCodigoInput($input) {
        if (!$input.length) {
            return "";
        }

        const formatted = normalizeCartaoCodigoValue($input.val());
        $input.val(formatted);
        return formatted;
    }

    function bindCodigoUnicoValidation($targetForm, $targetInput, checkUrl) {
        if (!$targetForm.length || !$targetInput.length || !$.fn.validate || !checkUrl) {
            return;
        }

        const validator = $targetForm.data("validator");
        if (!validator) {
            return;
        }

        const boundKey = `codigoRulesBound:${$targetForm.attr("id") || "form"}`;
        if ($targetForm.data(boundKey)) {
            return;
        }

        validator.settings.messages = {
            ...(validator.settings.messages || {}),
            codigo_unico: {
                ...(validator.settings.messages?.codigo_unico || {}),
                codigoCartaoValido: "Informe um codigo maior que zero.",
                remote: "Este codigo ja esta em uso."
            }
        };

        $targetInput.rules("add", {
            required: true,
            codigoCartaoValido: true,
            remote: {
                url: checkUrl,
                type: "get",
                data: {
                    codigo_unico: function () {
                        return normalizeCartaoCodigoValue($targetInput.val());
                    },
                    id: function () {
                        return $targetForm.find("[name='id']").val() || "";
                    }
                }
            }
        });

        $targetInput.on("keydown", function (e) {
            const key = e.key || "";
            const allowedKeys = [
                "Backspace", "Delete", "Tab", "ArrowLeft", "ArrowRight",
                "ArrowUp", "ArrowDown", "Home", "End"
            ];

            if (e.ctrlKey || e.metaKey || allowedKeys.includes(key)) {
                return;
            }

            if (!/^\d$/.test(key)) {
                e.preventDefault();
            }
        });

        $targetInput.on("input", function () {
            formatCartaoCodigoInput($targetInput);
            moveCursorToEnd(this);
            validator.element(this);
        });

        $targetInput.on("focus click", function () {
            const input = this;
            setTimeout(function () {
                moveCursorToEnd(input);
            }, 0);
        });

        $targetInput.on("blur", function () {
            formatCartaoCodigoInput($targetInput);
            validator.element(this);
        });

        formatCartaoCodigoInput($targetInput);

        $targetForm.data(boundKey, true);
    }

    function syncQuickDocumentoMask() {
        if (!$quickDocumento.length || !$.fn.mask) {
            return;
        }

        const value = String($quickDocumento.val() || "");
        const digits = value.replace(/\D/g, "");
        const mask = digits.length > 11 ? "00.000.000/0000-00" : "000.000.000-00";

        if ($quickDocumento.data("quickMask") === mask) {
            return;
        }

        $quickDocumento.unmask();
        $quickDocumento.mask(mask);
        $quickDocumento.data("quickMask", mask);

        if (value) {
            $quickDocumento.val(value);
        }
    }

    function setButtonLoading(loading) {
        if (!$submit.length) {
            return;
        }

        $submit.prop("disabled", loading);
        $submit.find(".btn-text").toggleClass("d-none", loading);
        $submit.find(".btn-loading").toggleClass("d-none", !loading);
    }

    function setNovoCartaoLoading(loading) {
        if (!$novoCartaoForm.length) {
            return;
        }

        const $button = $novoCartaoForm.find('button[type="submit"]').first();
        if (!$button.length) {
            return;
        }

        $button.prop("disabled", loading);
        $button.find(".btn-text").toggleClass("d-none", loading);
        $button.find(".btn-loading").toggleClass("d-none", !loading);
    }

    function resetQuickForm() {
        if (!$form.length) {
            return;
        }

        if ($form[0]) {
            $form[0].reset();
        }
    }

    function submitQuickForm() {
        if (quickSubmitting) {
            return;
        }

        if (!quickUrl) {
            notify("URL de cadastro rapido indisponivel.", "danger");
            return;
        }

        quickSubmitting = true;
        setButtonLoading(true);

        $.ajax({
            url: quickUrl,
            method: "POST",
            dataType: "json",
            data: $form.serialize()
        }).done(function (response) {
            if (!response || response.success !== true) {
                notify(response?.message || "Nao foi possivel cadastrar o cliente.", "danger");
                return;
            }

            const id = response.data?.id;
            const label = response.data?.label || "Cliente";

            if (id) {
                let $option = $select.find(`option[value="${id}"]`);

                if (!$option.length) {
                    $option = $(new Option(label, id, false, false));
                    $select.append($option);
                }

                $option.text(label);
                $select.find("option").prop("selected", false);
                $option.prop("selected", true);
                $select.val(String(id));

                if (typeof window.buildSelectpickerSearchTokens === "function") {
                    $option.attr("data-tokens", window.buildSelectpickerSearchTokens(label));
                }

                if (typeof window.enhanceSelectpickerSearch === "function") {
                    window.enhanceSelectpickerSearch($select);
                }

                if ($.fn.selectpicker) {
                    $select.selectpicker("refresh");
                    $select.selectpicker("val", String(id));
                    $select.selectpicker("render");
                } else {
                    $select.val(String(id)).trigger("change");
                }

                $select.trigger("change");
            }

            notify(response.message || "Cliente cadastrado com sucesso.", "success");
            bootstrap.Modal.getOrCreateInstance($modal[0]).hide();
        }).fail(function (xhr) {
            const message = xhr.responseJSON?.message || "Nao foi possivel cadastrar o cliente.";
            notify(message, "danger");
        }).always(function () {
            quickSubmitting = false;
            setButtonLoading(false);
        });
    }

    function bindQuickSubmitHandler() {
        const validator = $form.data("validator");

        if (!validator) {
            return;
        }

        validator.settings.messages = {
            ...(validator.settings.messages || {}),
            documento: {
                cpfOuCnpj: "Informe um CPF ou CNPJ valido."
            }
        };

        $quickDocumento.rules("remove", "cpfBR cnpjBR cpfOuCnpj");
        $quickDocumento.rules("add", {
            cpfOuCnpj: true
        });

        validator.settings.submitHandler = function () {
            submitQuickForm();
            return false;
        };
    }

    function openTrocaClienteModal() {
        if (!$trocaModal.length) {
            return;
        }

        if ($trocaSelect.length && $.fn.selectpicker) {
            $trocaSelect.selectpicker("refresh");

            if (clienteObrigatorio && !$trocaSelect.val()) {
                const firstVal = $trocaSelect.find("option[value!='']").first().val();
                if (firstVal) {
                    $trocaSelect.selectpicker("val", String(firstVal));
                }
            }
        }

        bootstrap.Modal.getOrCreateInstance($trocaModal[0]).show();
    }

    function applyTrocaCliente() {
        if (!$trocaSelect.length) {
            return;
        }

        const id = String($trocaSelect.val() ?? "");
        const semCliente = id === "";

        if (semCliente && clienteObrigatorio) {
            notify("Este cartão deve ter um cliente vinculado.", "warning");
            return;
        }
        const label = String($trocaSelect.find("option:selected").text() || "").trim();

        const $hidden = $("#id_cliente_hidden");
        if ($hidden.length) {
            $hidden.val(id);
        }

        if ($select.prop("disabled")) {
            // Edit mode: the hidden input handles submission; the disabled selectpicker is visual only.
            // Use native val() + refresh (not selectpicker("val","")) to avoid triggering noneSelectedText.
            if (semCliente) {
                $select.val("");
                try { if ($.fn.selectpicker) { $select.selectpicker("refresh"); } } catch (e) { /* ignore */ }
            }
        } else {
            $select.find("option").prop("selected", false);

            if (semCliente) {
                $select.val("");
            } else {
                let $option = $select.find(`option[value="${id}"]`);
                if (!$option.length) {
                    $option = $(new Option(label || "Cliente", id, false, true));
                    $select.append($option);
                }
                $option.prop("selected", true);
                $select.val(id);
            }

            try {
                if ($.fn.selectpicker) {
                    $select.selectpicker("val", semCliente ? "" : id);
                    $select.selectpicker("refresh");
                    $select.selectpicker("render");
                }
            } catch (e) { /* selectpicker not available */ }

            $select.trigger("change");
        }

        const $feedback = $("#troca-cliente-feedback");
        const $feedbackNome = $("#troca-cliente-nome");
        if ($feedback.length && $feedbackNome.length) {
            $feedbackNome.text(semCliente ? "Sem Cliente (vínculo removido)" : label);
            $feedback.removeClass("d-none");
        }

        notify(
            semCliente
                ? "Vínculo com cliente removido. Salve para confirmar."
                : "Cliente selecionado. Salve o formulário para confirmar.",
            "info"
        );

        bootstrap.Modal.getOrCreateInstance($trocaModal[0]).hide();
    }

    function bindNovoCartaoSubmitHandler() {
        if (!$novoCartaoForm.length || !$.fn.validate) {
            return;
        }

        const validator = $novoCartaoForm.data("validator");
        if (!validator) {
            return;
        }

        validator.settings.ignore = ":hidden";
        validator.settings.submitHandler = function () {
            if (novoCartaoSubmitting) {
                return false;
            }

            novoCartaoSubmitting = true;
            setNovoCartaoLoading(true);
            $novoCartaoForm[0].submit();
            return false;
        };

        bindCodigoUnicoValidation($novoCartaoForm, $novoCartaoCodigo, novoCartaoCodigoUrl);

        if (novoCartaoTokenNfcUrl && $novoCartaoTokenNfc.length) {
            validator.settings.messages = {
                ...(validator.settings.messages || {}),
                token_nfc: {
                    ...(validator.settings.messages?.token_nfc || {}),
                    remote: "Este token NFC ja esta em uso."
                }
            };

            $novoCartaoTokenNfc.rules("add", {
                remote: {
                    url: novoCartaoTokenNfcUrl,
                    type: "get",
                    data: {
                        token_nfc: function () { return $novoCartaoTokenNfc.val(); },
                        id: function () { return $novoCartaoForm.find("[name='id']").val() || ""; }
                    }
                }
            });
        }
    }

    if ($pageForm.length && typeof window.initFormValidation === "function") {
        window.initFormValidation($pageForm);
    }

    bindCodigoUnicoValidation($pageForm, $pageCodigo, pageCodigoUrl);

    // Remote de unicidade do token NFC
    if (pageTokenNfcUrl) {
        const validatorNfc = $pageForm.data("validator");
        if (validatorNfc) {
            validatorNfc.settings.messages = {
                ...(validatorNfc.settings.messages || {}),
                token_nfc: {
                    ...(validatorNfc.settings.messages?.token_nfc || {}),
                    remote: "Este token NFC já está em uso."
                }
            };
            $("#token_nfc").rules("add", {
                remote: {
                    url: pageTokenNfcUrl,
                    type: "get",
                    data: {
                        token_nfc: function () { return $("#token_nfc").val(); },
                        id: function () { return $pageForm.find("[name='id']").val() || ""; }
                    }
                }
            });
        }
    }

    // Leitor NFC injeta UID + Enter; previne submit acidental ao capturar o campo
    $("#token_nfc").on("keydown", function (e) {
        if (e.key === "Enter") {
            e.preventDefault();
            $(this).trigger("blur");
        }
    });

    $novoCartaoTokenNfc.on("keydown", function (e) {
        if (e.key === "Enter") {
            e.preventDefault();
            $(this).trigger("blur");
        }
    });

    if ($novoCartaoModal.length) {
        $novoCartaoModal.on("shown.bs.modal", function () {
            if (typeof applyMasks === "function") {
                applyMasks($novoCartaoModal);
            }

            if (typeof window.initFormValidation === "function") {
                window.initFormValidation($novoCartaoModal);
            }

            bindNovoCartaoSubmitHandler();
        });

        $novoCartaoModal.on("hidden.bs.modal", function () {
            novoCartaoSubmitting = false;
            setNovoCartaoLoading(false);
        });
    }

    $modal.on("shown.bs.modal", function () {
        if (typeof applyMasks === "function") {
            applyMasks($modal);
        }

        if (typeof window.initFormValidation === "function") {
            window.initFormValidation($modal);
        }

        bindQuickSubmitHandler();
        syncQuickDocumentoMask();
    });

    $modal.on("hidden.bs.modal", function () {
        quickSubmitting = false;
        resetQuickForm();
        setButtonLoading(false);
    });

    $quickDocumento.on("input blur", syncQuickDocumentoMask);
    syncQuickDocumentoMask();
    bindQuickSubmitHandler();
    bindNovoCartaoSubmitHandler();

    $(document).off("click.cartaoTrocarCliente").on("click.cartaoTrocarCliente", "#btn-trocar-cliente", function (e) {
        e.preventDefault();
        openTrocaClienteModal();
    });

    $(document).off("click.cartaoAplicarTroca").on("click.cartaoAplicarTroca", "#btn-aplicar-troca-cliente", function (e) {
        e.preventDefault();
        applyTrocaCliente();
    });

    $form.on("submit", function (e) {
        if ($form.data("validator")) {
            return;
        }

        e.preventDefault();
        submitQuickForm();
    });
});
