$(document).ready(function () {

    applyMasks($(document));

    if (typeof flatpickr !== "undefined") {
        initFlatpickrMonth($(document));
    }

    // Select2
    // Definindo as traduções de forma global para todos os selects na página
    $.fn.select2.defaults.set('language', {
        noResults: function() {
            return "Nenhum resultado encontrado"; // Tradução de "No results found"
        },
        searching: function() {
            return "Buscando..."; // Tradução de "Searching..."
        },
        errorLoading: function() {
            return "Não foi possível carregar os resultados"; // Tradução de erro
        },
        inputTooShort: function() {
            return "Digite mais caracteres"; // Tradução de "Input too short"
        },
        loadingMore: function() {
            return "Carregando mais resultados..."; // Tradução de "Loading more results..."
        },
        maximumSelected: function() {
            return "Você atingiu o limite de seleções"; // Tradução de "You can only select one item"
        }
    });

    // Select 2
    if ($(".select2").length > 0) {
        $('.select2').each(function() {
            var $select = $(this);

            var search = $select.data('search'); // Acessa o valor do data-search

            // Configura o Select2 com base no valor do data-search
            $select.select2({
                minimumResultsForSearch: (search === false || search === 'false') ? Infinity : 0,
                width: '100%',
                theme: "bootstrap-5",
                // Exemplo de como habilitar a busca via ajax (caso precise)
                // ajax: {
                //     url: url_base + "/clientes/search",
                //     dataType: "json",
                //     processResults: function (data) {
                //         return {
                //             results: data
                //         };
                //     }
                // }
            });

            // Foca o campo de busca assim que o Select2 for aberto
            $select.on('select2:open', function() {
                var searchField = $(this).data('select2').dropdown.$search[0]; // Acessa o input de pesquisa do select2
                if (searchField) {
                    searchField.focus(); // Foca no campo de pesquisa
                }
            });
        });
    }


    // Variável global para armazenar o último botão de submit clicado
    let lastClickedSubmitButton = null;

    // 1. Captura o botão clicado antes da validação
    $(".form-validate").on('click', 'button[type="submit"], input[type="submit"]', function() {
        lastClickedSubmitButton = this;
    });

    $.validator.methods.step = function () {
        return true;
    };

    // FORMULARIO PADRÃO
    $(".form-validate").each(function() {
        $(this).validate({
            submitHandler: function(form) {

                let submitButton;

                // Tenta usar o botão capturado no evento click
                if (lastClickedSubmitButton && $(lastClickedSubmitButton).closest(form).length) {
                    submitButton = $(lastClickedSubmitButton);
                } else {
                    // Fallback: se não capturou o botão (ex: submit via Enter),
                    // seleciona o primeiro botão de submit do formulário.
                    submitButton = $(form).find('button[type=submit], input[type=submit]').first();
                }

                // Aplica o efeito de carregamento APENAS no botão correto
                loadingButton(submitButton, false); // false para desabilitar/carregar

                // Submete o formulário normalmente
                form.submit();
            }
        });
    });

    $(".list-or-not").on("change", function () {
        const selectedValues = $(this).val(); // Pega os valores selecionados

        // Se "NÃO" for selecionado, desmarcar os outros
        if (selectedValues && selectedValues.includes("NÃO")) {
            $(this).val(["NÃO"]); // Mantém apenas "NÃO" selecionado
        }
        // Se qualquer outro for selecionado, desmarcar "NÃO"
        else if (selectedValues && selectedValues.length > 0) {
            const filtered = selectedValues.filter(value => value !== "NÃO");
            $(this).val(filtered); // Remove "NÃO" dos selecionados
        }

        $(this).selectpicker("refresh"); // Atualiza o estado do selectpicker
    });

    // INSERIR UMA VÍRGULA AO DIGITAR NO CAMPO, APENAS NÚMEROS E VÍRGULA
    // USAR UM data-limit NO ELEMENTO PARA DEFINIR AS CASAS DECIMAIS
    $(document).on("keypress keyup blur", ".numeric-comma", function(event) {
        var $this = $(this);
        if (
            (event.which != 44 || $this.val().indexOf(',') != -1) &&
            (event.which != 45 || $this.val().indexOf('-') != -1) &&
            ((event.which < 48 || event.which > 57) && (event.which != 0 && event.which != 8))) {
               event.preventDefault();
        }

        var text = $(this).val();
        if (
            (event.which == 44) && (text.indexOf(',') == -1)
        ) {
            setTimeout(function() {
                if ($this.val().substring($this.val().indexOf(',')).length > 3) {
                    $this.val($this.val().substring(0, $this.val().indexOf(',') + 3));
                }
            }, 1);
        }

        limit = $this.data("limit");

        if (limit) {
            if ((text.indexOf(',') != -1) &&
                (text.substring(text.indexOf(',')).length > limit) &&
                (event.which != 0 && event.which != 8) &&
                ($(this)[0].selectionStart >= text.length - limit)) {
                    event.preventDefault();
            }
        }
    });

    // FOCAR NO PRÓXIMO INPUT AO APERTAR ENTER
    $(document).on("keydown", ".focus-on-enter", function(event) {
        if (event.key === "Enter") {
            event.preventDefault(); // Impede o envio do formulário

            var inputs = $(".focus-on-enter"); // Seleciona todos os inputs de quantidade
            var index = inputs.index(this); // Pega o índice do input atual

            if (index < inputs.length - 1) {
                // Se não for o último input, foca no próximo
                inputs.eq(index + 1).focus();
            }
        }
    });

    // File inputs
    $(".custom-file-input").on("change", function() {
        var fileName = $(this).val().split("\\").pop();
        $(this).siblings(".custom-file-label").addClass("selected").html(fileName);
    });

    // PLACEHOLDER ON INPUT DATE, TRANSFORMA INPUT TEXT EM DATE AO FOCAR
    $('.text-date-toggle').on('focus', function() {
        $(this).attr('type', 'date');
    }).on('blur focusout', function() {
        if ($(this).val()=="") {
            $(this).attr('type', 'text');
        }
    });

    // SETAR VALOR MINIMO E MAXIMO EM DATEPICKER (RANGE)
    $('[data-range-max]').on('change', function() {
        var maxDateInput = $(this).val();
        if (maxDateInput) {
            var element = $(this).data("range-max");
            var input = $(element);
            input.attr('min', maxDateInput);
        }
    });

    $('[data-range-min]').on('change', function() {
        var minDateInput = $(this).val();
        if (minDateInput) {
            var element = $(this).data("range-min");
            var input = $(element);
            input.attr('max', minDateInput);
        }
    });

    // Mostrar Senha
    $(".show-pass").on("click", function(event) {
        event.stopPropagation();
        input = $(this).parent().find('input');
        if (input.prop("type")=="password") {
            input.prop("type", "text");
            $(this).parent().find("span").html('<i class="uil-eye-slash"></i>');
        }
        else if (input.prop("type")=="text") {
            input.prop("type", "password");
            $(this).parent().find("span").html('<i class="uil-eye"></i>');
        }
    });

    // Habilitar o envio do formulario ao clicar Enter
    // e Enter + Shift quebra a linha
    $("textarea.sendOnEnter").on('keydown', function(event) {
        // Verifica se a tecla pressionada é Enter
        if (event.key === 'Enter') {
            // Verifica se a tecla Shift também está sendo pressionada
            if (!event.shiftKey) {
                // Impede a ação padrão (quebrar a linha)
                event.preventDefault();
                // Encontra o formulário ao qual o textarea pertence
                var form = this.closest('form');
                // Envia o formulário
                form.submit();
            }
        }
    });

    // Habilitar Inputs Readonly
    $("#enableInput, .enableInput").on("click", function(){

        const input = $(this).parent().find("input");
        const disabled = input.is(":disabled");
        const icon = $(this).find("i");

        if (disabled) {
            input.removeAttr("disabled").focus();
            icon.removeClass("fa-arrows-rotate").addClass("fa-times");
            $(this).attr("data-bs-original-title", "Cancelar");
        } else {
            input.prop("disabled", true).removeClass("has-error");
            icon.addClass("fa-arrows-rotate").removeClass("fa-times");
            $(this).attr("data-bs-original-title", "Alterar E-mail");
            input.parent().parent().find("label.has-error").hide();
        }

    });

    // Busca CEP
    $(document).on("blur", "[name=cep], #cep", function () {

        const $form = $(this).closest("form");

        // ✅ filtro: só roda se esse form tiver os campos que você preenche
        const hasEndereco =
            $form.find("[name=endereco], #endereco").length &&
            $form.find("[name=bairro], #bairro").length &&
            $form.find("[name=cidade], #cidade").length &&
            $form.find("[name=estado], #estado").length;

        if (!hasEndereco) return;

        let cep = $(this).val().replace(/\D/g, "");

        const limpa = () => {
            $form.find("[name=endereco], #endereco").val("");
            $form.find("[name=bairro], #bairro").val("");
            $form.find("[name=cidade], #cidade").val("");
            $form.find("[name=estado], #estado").val("");
            $form.find("[name=pais], #pais").val("");
        };

        if (!cep) return limpa();

        if (!/^[0-9]{8}$/.test(cep)) {
            limpa();
            return alert("Formato de CEP inválido.");
        }

        $form.find("[name=endereco], #endereco").val("...");
        $form.find("[name=bairro], #bairro").val("...");
        $form.find("[name=cidade], #cidade").val("...");
        $form.find("[name=estado], #estado").val("...");
        $form.find("[name=pais], #pais").val("...");

        $.getJSON("https://viacep.com.br/ws/" + cep + "/json/?callback=?", function (dados) {
            if (!("erro" in dados)) {
                $form.find("[name=endereco], #endereco").val(dados.logradouro);
                $form.find("[name=bairro], #bairro").val(dados.bairro);
                $form.find("[name=cidade], #cidade").val(dados.localidade);

                const $estado = $form.find("[name=estado], #estado");
                $estado.val(dados.uf).trigger("change");

                // só refresca se for selectpicker mesmo
                if ($estado.hasClass("selectpicker")) $estado.selectpicker("refresh");

                $form.find("[name=pais], #pais").val("Brasil");

                $form.find("[name=numero], #numero").focus();
            } else {
                limpa();
                alert("CEP não encontrado.");
            }
        });
    });

    // Pesquisar CNPJ na API https://receitaws.com.br/api
    $("#searchCNPJ").on("click", (e) => {
        $("#overlay-load").show();
        var cnpj = $("#cnpj").val();

        $.ajax({
            type: "post",
            url: url_admin + "/cnpj",
            data: {cnpj},
            dataType: "json",
            beforeSend: function() {
                $("input[name=nascimento]").val("...");
                $("input[name=razao]").val("...");
                $("input[name=nome]").val("...");
                $("input[name=fantasia]").val("...");
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
                $("input[name=fantasia]").val(response.fantasia);
                $("input[name=cep]").val(response.cep);
                $("input[name=bairro]").val(response.bairro);
                $("input[name=endereco]").val(response.logradouro);
                $("input[name=numero]").val(response.numero);
                $("input[name=complemento]").val(response.complemento);
                $("input[name=cidade]").val(response.municipio);
                $("input[name=estado]").val(response.uf);

                $("#overlay-load").hide();

                console.log(response);

            }
        });
    });

    // Pessoa Física ou Jurídica
    $(document).on("change", "[name=pessoa]", function () {
        const $form = $(this).closest("form");
        const val = ($(this).val() || "").trim();

        // filtro: só roda se existir .pes nesse form
        if (!$form.find(".pes").length) return;

        // sempre esconde os labels/inputs alternáveis
        $form.find(".pes").hide();

        // se não escolheu nada ainda, mostra os genéricos (se existir)
        if (!val) {
            $form.find(".pes:not(.F):not(.J)").show(); // ex: ".pes" genérico
            return;
        }

        // só aceita F/J
        if (val !== "F" && val !== "J") return;

        $form.find(".pes." + val).show();

        if (val === "F") {
            $form.find("input[name=razao]").attr("placeholder", "Nome Completo");
            $form.find("input[name=nome]").attr("placeholder", "Apelido");
            $form.find("input[name=rg_ie]").attr("placeholder", "RG");
        } else {
            $form.find("input[name=razao]").attr("placeholder", "Razão Social");
            $form.find("input[name=nome]").attr("placeholder", "Nome Fantasia");
            $form.find("input[name=rg_ie]").attr("placeholder", "Inscrição Estadual");
        }
    });


    // RANGE SLIDE
    $(".form__slider .range0").on("input", function() {

        const min = this.min
        const max = this.max
        const val = this.value

        this.style.backgroundSize = (val - min) * 100 / (max - min) + '% 100%';

        left = (val - min) * 100 / (max - min)

        const sign = $(this).parent().find(".sign");

        sign.find("span").html(val);
        sign.css({"left":left+"%"});
    });

    $(".form__slider .range1").on("input", function() {

        this.value = Math.min(this.value,this.parentNode.childNodes[5].value-1);
        var value=(100/(parseInt(this.max)-parseInt(this.min)))*parseInt(this.value)-(100/(parseInt(this.max)-parseInt(this.min)))*parseInt(this.min);
        var children = this.parentNode.childNodes[1].childNodes;
        children[1].style.width=value+'%';
        children[5].style.left=value+'%';
        children[7].style.left=value+'%';children[11].style.left=value+'%';
        children[11].childNodes[1].innerHTML=this.value;

    });

    $(".form__slider .range2").on("input", function() {

        this.value=Math.max(this.value,this.parentNode.childNodes[3].value-(-1));
        var value=(100/(parseInt(this.max)-parseInt(this.min)))*parseInt(this.value)-(100/(parseInt(this.max)-parseInt(this.min)))*parseInt(this.min);
        var children = this.parentNode.childNodes[1].childNodes;
        children[3].style.width=(100-value)+'%';
        children[5].style.right=(100-value)+'%';
        children[9].style.left=value+'%';children[13].style.left=value+'%';
        children[13].childNodes[1].innerHTML=this.value;
    });

    // trocar a ordem ao clicar no span.order
    function resolveUrl($el) {
        const route = $el.data("route");

        // 1) se tiver data-route: url_base + route
        if (route) {
        return (typeof url_base !== "undefined" ? url_base : "") + route;
        }

        // 2) se não tiver: url atual (sem query/hash) + "/order"
        let current = window.location.href.split("#")[0].split("?")[0];
        current = current.replace(/\/$/, "");
        return current + "/order";
    }

    $(document).on("click", ".order", function () {

        const $span = $(this);

        if ($span.data("editing")) return;
        $span.data("editing", 1);

        const id = $span.data("id");
        const original = $.trim($span.text()) || "0";
        const url = resolveUrl($span);

        const $input = $('<input type="number" min="0" step="1" class="form-control form-control-sm" style="width:90px;">');
        $input.val(original);
        $input.data("id", id);
        $input.data("original", original);

        $span.replaceWith($input);
        $input.focus().select();

        function backToSpan(val) {
            const $newSpan = $('<span class="order"></span>');
            $newSpan.attr("data-id", id);

            // mantém o data-route se existia
            const route = $span.data("route");
            if (route) $newSpan.attr("data-route", route);

            $newSpan.text(val);
            $input.replaceWith($newSpan);
        }

        function salvarSeMudou() {

            const newVal = String(parseInt($input.val() || "0", 10));
            const oldVal = String(parseInt($input.data("original") || "0", 10));

            if (newVal === oldVal) {
                backToSpan(oldVal);
                return;
            }

            $input.prop("disabled", true);

            $.ajax({
                url: url,
                method: "POST",
                dataType: "json",
                data: {
                    id: id,
                    ordem: newVal,
                },
                success: function (resp) {
                    if (resp && resp.success) {
                        const shown = (resp.ordem !== undefined && resp.ordem !== null) ? resp.ordem : newVal;
                        backToSpan(shown);
                    } else {
                        backToSpan(oldVal);
                        showToastr((resp && resp.message) ? resp.message : "Falha ao salvar.", "error");
                    }
                },
                error: function () {
                    backToSpan(oldVal);
                    showToastr("Erro ao salvar.", "error");
                }
            });
        }

        $input.on("keydown", function (e) {

            if (e.key === "Escape") {
                e.preventDefault();
                backToSpan($input.data("original"));
                return;
            }

            if (e.key === "Enter") {
                e.preventDefault();
                salvarSeMudou();
            }
        });

        // 👉 BLUR agora também salva se mudou
        $input.on("blur", function () {
            salvarSeMudou();
        });

    });


}); // jquery ends





// DESABILITAR O BOTÃO DE SUBMIT, E MOSTRAR MENSAGEM DE ENVIANDO


function restoreButton(element) {

    const $button = resolveLoadingButton(element);

    if (!$button.length) {
        return;
    }

    const originalHtml = $button.data('original-html');

    if (originalHtml !== undefined) {
        if ($button.is('input')) {
            $button.val(originalHtml);
        } else {
            $button.html(originalHtml);
        }
    } else if (!$button.is('input')) {
        $button.find(".btn-spinner").remove();
    }

    $button.removeAttr('disabled');
    $('button[data-dismiss="modal"]').removeAttr('disabled');
}

// Limpa valores do formulário de cep.
function limpa_formulario_cep() {
    $("#rua,#endereco,#logradouro,#bairro,#cidade,#uf,#estado").val("");
    $("input[name=rua],input[name=endereco],input[name=logradouro],input[name=bairro],input[name=cidade],input[name=uf],input[name=estado]").val("");
}

// Inicia Flatpickr
function initFlatpickrMonth($root) {

    // localiza pt-BR (só precisa uma vez, mas ok chamar)
    if (typeof flatpickr !== "undefined" && flatpickr.l10ns && flatpickr.l10ns.pt) {
        flatpickr.localize(flatpickr.l10ns.pt);
    }

    $root.find(".flatpickr-month").each(function () {

        // ✅ se já existe (ex: abrir modal de novo), destrói pra reiniciar certo no modal
        if (this._flatpickr) this._flatpickr.destroy();

        // ✅ se estiver dentro de modal, joga o calendário pra dentro do modal
        const $modal = $(this).closest(".modal");
        const appendTarget = $modal.length ? $modal.get(0) : document.body;

        flatpickr(this, {
            locale: "pt",
            dateFormat: "Y-m",   // valor real: 2025-11
            altFormat: "F Y",    // exibe: Novembro 2025
            altInput: true,
            plugins: [
                new monthSelectPlugin({
                    shorthand: false,
                    dateFormat: "Y-m",
                    altFormat: "F Y"    // garante o texto bonito
                })
            ],
            appendTo: appendTarget,  // ✅ LINHA QUE FALTAVA PRA NÃO ZOAR NO MODAL

            onReady: function (selectedDates, dateStr, instance) {
                const btnClear = document.createElement("button");
                btnClear.type = "button";
                btnClear.className = "flatpickr-clear-btn";
                btnClear.innerHTML = '<i class="uil uil-trash-alt"></i> Limpar';

                btnClear.style.marginLeft = "0";
                btnClear.style.padding = "10px 8px";
                btnClear.style.border = "none";
                btnClear.style.borderRadius = "4px";
                btnClear.style.background = "transparent";
                btnClear.style.cursor = "pointer";

                btnClear.addEventListener("click", function () {
                    instance.clear();
                    instance.close();
                });

                instance.calendarContainer.appendChild(btnClear);
            }
        });
    });
}


function enableSelect2Sortable(selectSelector, hiddenSelector = null, select2Options = {}) {

    const $el = $(selectSelector);

    // ✅ NÃO reinicializa se já existe Select2
    if (!$el.data("select2")) {
        $el.select2({
        width: "100%",
        theme: "bootstrap-5",
        minimumResultsForSearch: 0,
        ...select2Options
        });
    }

    const $hidden = hiddenSelector ? $(hiddenSelector) : null;

    function getRendered() {
        return $el
        .next(".select2-container")
        .find("ul.select2-selection__rendered");
    }

    function getOrderedIdsFromUI($rendered) {
        const ids = [];

        $rendered.find("li.select2-selection__choice").each(function () {
        const d = $(this).data("data");
        if (d && d.id != null) {
            ids.push(String(d.id));
            return;
        }

        // fallback por title/texto
        const title = $(this).attr("title");
        if (!title) return;

        const $opt = $el.find("option").filter(function () {
            return $(this).text().trim() === title.trim();
        }).first();

        if ($opt.length) ids.push(String($opt.val()));
        });

        return ids;
    }

    function syncOrder() {
        const $rendered = getRendered();

        // se ainda não renderizou, não faz nada
        if (!$rendered.length) return;

        const uiOrder = getOrderedIdsFromUI($rendered);

        // ✅ se não conseguiu ler UI (raro), usa a seleção atual (não some nada)
        const selected = ($el.val() || []).map(String);
        const orderedIds = uiOrder.length ? uiOrder : selected;

        // hidden (se existir)
        if ($hidden && $hidden.length) {
        $hidden.val(orderedIds.join(","));
        }

        // nada selecionado -> ok, não mexe em options/val
        if (!orderedIds.length) return;

        // ✅ reordena os <option> no DOM (isso define a ordem “real”)
        orderedIds.forEach(id => {
        const $opt = $el.find("option").filter(function () {
            return String(this.value) === String(id);
        }).first();

        if ($opt.length) $el.append($opt);
        });

        // ✅ garante que o select continue com os mesmos selecionados (na ordem)
        $el.val(orderedIds).trigger("change.select2");
    }

    // sortable: precisa pegar o rendered atual
    const $rendered = getRendered();
    $rendered.sortable({
        items: "li.select2-selection__choice",
        cancel: "li.select2-search, input",
        tolerance: "pointer",
        float: true,
        placeholder: "select2-sortable-placeholder",
        forcePlaceholderSize: true,
        update: function () {
        syncOrder();
        }
    });

    // seleção / remoção
    $el.on("select2:select select2:unselect", function () {
        setTimeout(syncOrder, 0);
    });

    // inicial
    setTimeout(syncOrder, 0);

    return { $el, syncOrder };
}

function applyOptionOrderFromHidden(selectSelector, hiddenSelector) {
    const $select = $(selectSelector);
    const ordemStr = $(hiddenSelector).val() || "";
    if (!ordemStr.trim()) return;

    const ids = ordemStr.split(",").map(s => s.trim()).filter(Boolean);

    // move os options selecionados para o final, na ordem correta
    ids.forEach(id => {
        const $opt = $select.find(`option[value="${id.replace(/"/g, '\\"')}"]`);
        if ($opt.length) $select.append($opt);
    });

    // garante que os mesmos ids estão selecionados na mesma ordem
    $select.val(ids);
}
