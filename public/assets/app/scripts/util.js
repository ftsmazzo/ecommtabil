// =========================
// HELPERS VISUAIS / DOM
// =========================

// Resolve URLs curtas da app sem precisar escrever ../../../ nas views.
// Exemplos:
// resolveAppUrl("admin/usuarios") -> app.base + "/admin/usuarios"
// resolveAppUrl("delete/10", "current") -> URL atual + "/delete/10"
function resolveAppUrl(path, mode = "app") {
    const value = String(path || "").trim();

    if (!value) {
        return window.location.href;
    }

    if (/^(https?:)?\/\//i.test(value) || value.startsWith("#")) {
        return value;
    }

    if (value.startsWith("../") || value.startsWith("./")) {
        return new URL(value, window.location.href).href;
    }

    if (mode === "current") {
        return `${window.location.href.replace(/\/+$/, "")}/${value.replace(/^\/+/, "")}`;
    }

    return `${String(app?.base || "").replace(/\/+$/, "")}/${value.replace(/^\/+/, "")}`;
}

// Helper legado/rápido para confirmar exclusão por rota.
// Exemplos:
// Delete("delete/10")
// Delete("admin/usuarios/delete", 10)
function Delete(route, id) {
    const routePath = `${route ? route : "delete"}${id ? `/${id}` : ""}`;
    const routeMode = /^(delete|update|edit|view|find)(\/|$)/i.test(routePath) ? "current" : "app";
    const url = resolveAppUrl(routePath, routeMode);
    const theme = $("body").attr("data-layout-color");

    $.confirm({
        title: "Confirmacao",
        content: "Deseja realmente excluir este registro?",
        theme: `modern ${theme}`,
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
                    location.href = url;
                }
            },
            cancel: {
                text: "Nao"
            }
        }
    });
}

// Confirma uma exclusão a partir de uma URL já resolvida ou curta.
// Pensado para uso declarativo com [data-delete].
// Exemplo:
// confirmDeleteUrl("admin/clientes/delete/10")
function confirmDeleteUrl(url, options = {}) {
    const theme = $("body").attr("data-layout-color");
    const finalUrl = resolveAppUrl(url, options.mode || "app");

    $.confirm({
        title: options.title || "Confirmacao",
        content: options.content || "Deseja realmente excluir este registro?",
        theme: `modern ${theme}`,
        backgroundDismiss: true,
        icon: "fa-solid fa-circle-xmark",
        type: "coral",
        typeAnimated: true,
        animateFromElement: false,
        buttons: {
            confirm: {
                text: options.confirmText || "Sim",
                btnClass: "btn-coral",
                action: function () {
                    location.href = finalUrl;
                }
            },
            cancel: {
                text: options.cancelText || "Nao"
            }
        }
    });
}

// Troca o tema visual do cenário atual e atualiza ícone/tooltip do toggle.
function setTheme(theme) {
    const layout = $("body").attr("data-layout");

    $("body").attr("data-layout-color", theme);

    if (layout === "detached") {
        $("body").attr("data-leftbar-theme", theme);
    }

    if (theme === "dark") {
        $("#light-dark-mode").find("i").removeClass().addClass("uil uil-sun noti-icon");
        $("#light-dark-mode").attr("data-bs-original-title", "Modo Claro");
    } else {
        $("#light-dark-mode").find("i").removeClass().addClass("uil uil-moon noti-icon");
        $("#light-dark-mode").attr("data-bs-original-title", "Modo Escuro");
    }

    $(document).trigger("themeChart:changed", [theme]);
}

// Permite rolar horizontalmente um container usando a roda do mouse.
function scrollHorizontally(el, event) {
    if (el.scrollWidth > el.clientWidth) {
        el.scrollLeft += event.deltaY;
        event.preventDefault();
    }
}

// Formata valor para padrão brasileiro.
// Exemplo:
// formatRealBr(10) -> "R$ 10,00"
function formatRealBr(valor, cifrao = true) {
    const numero = parseFloat(valor || 0);

    return new Intl.NumberFormat("pt-BR", {
        style: cifrao ? "currency" : "decimal",
        currency: "BRL",
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(numero);
}

// Limpa os campos de endereço do formulário atual.
// Usado internamente pelo fluxo de CEP.
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

// Coloca os campos de endereço em estado de carregamento.
// Usado internamente pelo fluxo de CEP.
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

// Preenche os campos de endereço com a resposta do ViaCEP.
// Campos suportados: rua, endereco, logradouro, bairro, cidade, uf, estado, pais e numero.
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

// Consulta um CEP no ViaCEP.
// Retornos de erro possíveis no callback: empty, invalid, not_found, request_error.
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

// Aplica a busca automática de CEP em um campo específico.
// Uso manual:
// applyCepLookup("#cep")
// Uso automático:
// já existe bind global no blur de [name=cep] e #cep
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
        alert("Formato de CEP invalido.");
        return;
    }

    loadingCepForm($form);

    buscarCep(cep, function (dados) {
        preencherCepForm($form, dados);
    }, function (reason) {
        limparCepForm($form);

        if (reason === "not_found") {
            alert("CEP nao encontrado.");
        } else if (reason === "request_error") {
            alert("Erro ao consultar o CEP.");
        }
    });
}

$(document).on("blur", "[name=cep], #cep", function () {
    applyCepLookup(this);
});

// Resolve o elemento alvo de um modal a partir de seletor, jQuery ou HTMLElement.
function resolveModalElement(target) {
    if (!target) {
        return null;
    }

    if (target instanceof HTMLElement) {
        return target;
    }

    const $target = $(target).first();
    return $target.length ? $target.get(0) : null;
}

// Registro genérico de inicializadores para conteúdo carregado via AJAX em modais.
// Cada arquivo JS de formulário registra o seu callback uma vez.
window.ajaxModalInitializers = window.ajaxModalInitializers || [];

function registerAjaxModalInitializer(callback) {
    if (typeof callback !== "function") {
        return;
    }

    if (!window.ajaxModalInitializers.includes(callback)) {
        window.ajaxModalInitializers.push(callback);
    }
}

window.registerAjaxModalInitializer = registerAjaxModalInitializer;

// Garante que o overlay de loading existe dentro de .modal-body e retorna ele.
function ensureModalLoading($modal) {
    const $body = $($modal).find(".modal-body").first();

    if (!$body.length) {
        return $();
    }

    $body.addClass("position-relative");

    let $overlay = $body.children(".js-modal-loading").first();

    if (!$overlay.length) {
        $overlay = $(
            '<div class="js-modal-loading d-none position-absolute top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white bg-opacity-75" style="z-index:20">' +
                '<div class="spinner-border text-primary mb-2" role="status" aria-hidden="true"></div>' +
                '<div class="small text-muted js-modal-loading-text">Carregando...</div>' +
            '</div>'
        );

        $body.prepend($overlay);
    }

    return $overlay;
}

// Exibe ou oculta o overlay de loading do modal, desabilitando interações enquanto ativo.
function setModalLoading(modal, state, message) {
    const $modal  = $(modal).first();
    const $body   = $modal.find(".modal-body").first();
    const $overlay = ensureModalLoading($modal);
    const loading  = !!state;

    $overlay.toggleClass("d-none", !loading);

    if (message) {
        $overlay.find(".js-modal-loading-text").text(message);
    }

    $modal.find(".modal-header .btn-close, .modal-footer button, .modal-footer a.btn")
        .prop("disabled", loading)
        .toggleClass("disabled", loading);
}

window.setModalLoading = setModalLoading;

// Abre um modal Bootstrap e carrega conteúdo remoto dentro de .modal-body.
// Uso:
// openAjaxModal({
//     modal: "#modalPadrao",
//     url: "admin/clientes/10/detalhes",
//     title: "Detalhes do cliente"
// })
//
// Também existe uso declarativo:
// data-modal-ajax="admin/clientes/10/detalhes"
// data-modal-target="#modalPadrao"
function openAjaxModal(options = {}) {
    const modalElement = resolveModalElement(options.modal || options.target);

    if (!modalElement) {
        return null;
    }

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const $modal = $(modalElement);
    const $title = $modal.find(".modal-title").first();
    const $body = $modal.find(".modal-body").first();
    const url = options.url;

    if (typeof window.setModalInteractionLocked === "function") {
        window.setModalInteractionLocked($modal, false);
    }

    if (options.title && $title.length) {
        $title.text(options.title);
    }

    if (!url) {
        if ($body.length) {
            $body.html('<div class="py-4 text-center text-danger">URL do modal nao informada.</div>');
        }

        modal.show();
        return modal;
    }

    if ($body.length) {
        $body.html('<div style="min-height:160px"></div>');
    }

    modal.show();
    setModalLoading($modal, true);

    $.ajax({
        url: url,
        method: (options.method || "GET").toUpperCase(),
        data: options.data || {},
        dataType: options.dataType || "html",
        success: function (response) {
            if ($body.length) {
                $body.html(response);
            }

            setModalLoading($modal, false);

            if (typeof window.setModalInteractionLocked === "function") {
                window.setModalInteractionLocked($modal, false);
            }

            applyMasks($modal);

            if (typeof window.initFormValidation === "function") {
                window.initFormValidation($modal);
            }

            window.ajaxModalInitializers.forEach(function (initializer) {
                try {
                    initializer($modal, response, modalElement, modal);
                } catch (error) {
                    console.error("Erro ao inicializar modal AJAX:", error);
                }
            });

            if (typeof options.onLoaded === "function") {
                options.onLoaded(response, modalElement, modal);
            }
        },
        error: function (xhr) {
            const fallback = '<div class="py-4 text-center text-danger">Erro ao carregar o conteudo.</div>';
            const responseMessage = xhr?.responseJSON?.message;

            if ($body.length) {
                $body.html(typeof responseMessage === "string" ? responseMessage : fallback);
            }

            setModalLoading($modal, false);

            if (typeof window.setModalInteractionLocked === "function") {
                window.setModalInteractionLocked($modal, false);
            }

            if (typeof options.onError === "function") {
                options.onError(xhr, modalElement, modal);
            }
        }
    });

    return modal;
}

// Delete declarativo.
// Exemplo:
// <a data-delete="admin/clientes/delete/10">Excluir</a>
$(document).on("click", "[data-delete]", function (e) {
    e.preventDefault();

    const $trigger = $(this);
    const url = $trigger.data("delete") || $trigger.attr("href");

    if (!url) {
        return;
    }

    confirmDeleteUrl(url, {
        title: $trigger.data("deleteTitle"),
        content: $trigger.data("deleteContent"),
        confirmText: $trigger.data("deleteConfirm"),
        cancelText: $trigger.data("deleteCancel")
    });
});

// Modal AJAX declarativo.
// Exemplo:
// <button
//   data-modal-ajax="admin/clientes/10/detalhes"
//   data-modal-target="#modalPadrao"
//   data-modal-title="Detalhes"
// >
$(document).on("click", "[data-modal-ajax]", function (e) {
    e.preventDefault();

    const $trigger = $(this);

    openAjaxModal({
        modal: $trigger.data("modalTarget"),
        url: $trigger.data("modalAjax") || $trigger.attr("href"),
        method: $trigger.data("modalMethod") || "GET",
        title: $trigger.data("modalTitle"),
        dataType: $trigger.data("modalType") || "html"
    });
});

$(document).on("hide.bs.modal", ".modal", function (e) {
    if (!$(this).data("interactionLocked")) {
        return;
    }

    e.preventDefault();
});

// Abre $.confirm antes de navegar para uma URL.
// Exemplo:
// ConfirmLink(this.href, 'Aplicar bônus?'); return false;
// ConfirmLink('/rota', 'Mensagem', { title: 'Título', type: 'blue' });
function ConfirmLink(url, msg, opts) {
    opts = opts || {};
    const theme = $("body").attr("data-layout-color");

    $.confirm({
        title: opts.title || "Confirmação",
        content: msg,
        theme: `modern ${theme}`,
        backgroundDismiss: true,
        icon: opts.icon || "fa-solid fa-circle-exclamation",
        type: opts.type || "orange",
        typeAnimated: true,
        animateFromElement: false,
        buttons: {
            confirm: {
                text: opts.confirmText || "Sim",
                btnClass: opts.btnClass || "btn-orange",
                action: function () { location.href = url; }
            },
            cancel: {
                text: opts.cancelText || "Não"
            }
        }
    });
}

// Vincula o botão de excluir logo via AJAX.
// Uso: <button class="btn-delete-logo" data-delete-logo-url="..." data-id="..." data-csrf="..." data-target="id-do-wrapper">
function bindDeleteLogo($scope) {
    const $root = $scope && $scope.length ? $scope : $(document);

    $root.find(".btn-delete-logo").off("click.deleteLogo").on("click.deleteLogo", function () {
        const $btn   = $(this);
        const url    = $btn.data("deleteLogoUrl");
        const id     = $btn.data("id");
        const csrf   = $btn.data("csrf");
        const target = $btn.data("target");

        if (!url || !id) { return; }

        loadingButton($btn, false);

        $.ajax({
            url:      url,
            method:   "POST",
            dataType: "json",
            data:     { id: id, csrf: csrf },
            success: function (res) {
                if (res && res.error) {
                    notify(res.message || "Erro ao remover logo.", "danger");
                    return;
                }

                if (target) {
                    $("#" + target).remove();
                }

                var recordId = $btn.data("recordId");
                if (recordId) {
                    $('tr[data-record-id="' + recordId + '"] td.logo-cell').html(
                        '<span class="text-muted"><i class="uil uil-image-slash"></i></span>'
                    );
                }

                notify(res.message || "Logo removido com sucesso.", "success");
            },
            error: function () {
                notify("Erro ao remover logo.", "danger");
            },
            complete: function () {
                loadingButton($btn, true);
            }
        });
    });
}

window.bindDeleteLogo = bindDeleteLogo;

// =========================
// INTERCEPTOR DE SESSÃO EXPIRADA
// =========================
// Quando o servidor retorna 401 com auth:false, redireciona a página inteira
// para o login em vez de injetar HTML de login dentro de um modal/AJAX.
$(function () {
    $(document).ajaxError(function (event, xhr) {
        if (xhr.status === 401) {
            var json = null;

            try {
                json = JSON.parse(xhr.responseText);
            } catch (e) {}

            var redirectUrl = (json && json.redirect)
                ? json.redirect
                : (xhr.getResponseHeader("X-Auth-Redirect") || null);

            if (json && json.auth === false) {
                if (redirectUrl) {
                    window.location.href = redirectUrl;
                } else {
                    window.location.reload();
                }
            }
        }
    });
});
