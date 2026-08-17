$(document).ready(function () {

    const themeBody = $("body").attr("data-layout-color");

    // MOSTRAR ICONE AO ABRIR O MODAL
    $('.modal-confirm').on('shown.bs.modal', function (event) {
        const icon = $(this).find(".modal-header").find("i");
        icon.addClass("zoom");
    });

    // MOSTRAR ICONE AO FECHAR O MODAL
    $('.modal-confirm').on('hidden.bs.modal', function (event) {
        const icon = $(this).find(".modal-header").find("i");
        icon.removeClass("zoom");
    });

    $('#dragOver').on('dragover', function(event) {

        console.log("dragover");
        // handleDragOver(this.parentElement, event);
    });

    // MUDAR O MODO DA COR DO TEMA
    $("#light-dark-mode").on("click", function(e) {
        e.preventDefault();

        let current_theme = $("body").attr("data-layout-color");
        let new_theme = (current_theme === "light") ? "dark" : "light";

        // todos os segmentos do path atual (sem vazios)
        const path = window.location.pathname.split("/").filter(Boolean);

        // pega os segmentos do url_base (se url_base for algo como "http://localhost/meuprojeto")
        let baseSegments = [];
        try {
            // garante que url_base seja uma URL válida
            baseSegments = new URL(url_base, window.location.origin).pathname.split("/").filter(Boolean);
        } catch (err) {
            console.warn("url_base inválida ou ausente, fallback ativado", err);
            baseSegments = [];
        }

        // o módulo começa logo depois dos segmentos da base
        const moduleIndex = baseSegments.length;
        // pega o segmento correspondente (se existir), senão tenta outros fallbacks
        let section = path[moduleIndex] || path[0] || "";

        // Se quiser tolerância extra: se não encontrou nada, procura por admin/cliente em qualquer lugar do path
        if (!section) {
            const found = path.find(p => p === "admin" || p === "cliente");
            if (found) section = found;
        }

        // Se ainda não tiver seção válida, aborta graciosamente
        if (!section) {
            console.error("Não foi possível detectar a seção (admin/cliente). path:", path, "baseSegments:", baseSegments);
            $.toast({
                text: "Não foi possível determinar o módulo (admin/cliente).",
                position: "bottom-right",
                icon: "warning"
            });
            return;
        }

        // monta a URL final com cuidado para não duplicar barras
        const url = url_base.replace(/\/+$/, "") + "/" + section.replace(/^\/+|\/+$/g, "");

        console.log("Usando seção:", section, "-> POST:", url + "/tema/" + new_theme);

        $.ajax({
            type: "POST",
            url: url + "/tema/" + new_theme,
            dataType: "json",
            success: function (response) {
                if (response.update) {
                    setTheme(new_theme);
                } else {
                    $.toast({
                        text: "Houve um erro ao tentar mudar o tema",
                        position: "bottom-right",
                        icon: "warning",
                    });
                }
            },
            error: function(xhr, status, err) {
                console.error("Erro AJAX:", status, err);
                $.toast({
                    text: "Erro ao comunicar com o servidor",
                    position: "bottom-right",
                    icon: "error",
                });
            }
        });
    });




}); // jquery ends

// BALANÇAR O SININHO DE NOTIFICAÇÃO A CADA 10s
const bell_notification = $('.dripicons-bell.noti-icon').parent();
setInterval(function() {
    bell_notification.addClass('animate__animated animate__tada')
    .on('animationend', () => {
        bell_notification.removeClass('animate__animated animate__tada');
    });
}, 5000);

// ATIVAR O TOOLTIP DO BOOTSTRAP PELO ATRIBUTO data-tooltip=true
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-tooltip=true]'))
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl, {
        "customClass":"custom-tooltip",
    })
});

// ADICIONAR UMA CLASSE DIFERENTE AO MENU
function activeMenu(element) {

    // VERIFICAR QUAL É O TIPO DE LAYOUT
    const layout = $("body").attr("data-layout");

    const menu = $("[data-menu=" + element + "]");

    menu.addClass("active");
    menu.parent().addClass("menuitem-active");
    menu.parent().parent().parent().addClass("show");
    menu.parent().parent().parent().parent().addClass("menuitem-active");

    const t = menu.parent().parent().parent().parent().parent().parent();

    if (t.attr("id") !== "sidebar-menu") {
        t.addClass("show");
        t.parent().addClass("menuitem-active");
    }

    const e = t.parent().parent().parent();
    const a = t.parent().parent().parent().parent();

    if (e.attr("id") !== "wrapper") {
        e.addClass("show");
    }

    if (a.is("body")) {
        a.addClass("menuitem-active");
    }

    // custom
    if (layout == "topnav") {
        const parent_menu = menu.parent();
        if (parent_menu.hasClass("dropdown-menu")) {
            parent_menu.parent().find("> a").addClass("active");
        }
    }
}

// function logout() {
//     Logout(url_admin + "/logout");
// }



// JANELA DE CONFIRMAÇÃO ANTES DE EXCLUIR UM REGISTRO
function Delete(route, id) {

    const url = window.location.href + '/'
        + (route ? route : 'delete')
        + (id ? '/' + id : '');

    // PEGAR O TEMA
    const theme = $("body").attr("data-layout-color");

    $.confirm({
        title: 'Confirmação',
        content: 'Deseja realmente excluir este registro?',
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

function showToastr(msg, classe = 'success', title = '', side = 'top right', duration = 3000, extras = {}) {
    // Verifica se a mensagem foi fornecida
    if (!msg) {
        console.error('A mensagem é obrigatória.');
        return;
    }

    if (classe == "danger") {
        classe = "error";
    }

    // Mapeia as posições simplificadas para as posições do Toastr
    const positionMap = {
        "top left": "toast-top-left",
        "top right": "toast-top-right",
        "bottom left": "toast-bottom-left",
        "bottom right": "toast-bottom-right"
    };

    // Obtem a posição do Toastr
    const positionClass = positionMap[side] || "toast-top-right"; // Padrão: top right

    // Chama o toastr com a classe, título, e posição configuráveis
    toastr[classe](msg, title, {
        "positionClass": positionClass, // Posição configurável
        "timeOut": duration, // Duração do alerta
        ...extras // Aplica configurações extras, caso existam
    });
}


// function deletar(id, act) {

//     modal_html = '<div class="modal fade" id="modal-delete" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content">                            <div class="modal-header bg-danger text-white">                                <h6 class="modal-title" id="exampleModalLabel">Confirmação</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><h5 class="no-strong"><i class="fa fa-times-circle text-danger pe-2"></i> Confirma a exclusão?</h5></div><div class="modal-footer"><button type="button" class="btn btn-md btn-light bd" data-bs-dismiss="modal">Não</button><a href="'+window.location.href+'/'+(act?act:'delete')+'/'+id+'" class="btn btn-md btn-danger btn-confirm-del">Sim</a></div></div></div></div>';

//     $("body").append(modal_html);
//     new bootstrap.Modal(document.getElementById('modal-delete')).show();

//     $("button[data-dismiss=modal]").prop("disabled", false).removeClass('btn-disabled disabled');
//     $(".btn-confirm-del").prop("disabled", false).removeClass('btn-disabled disabled').html("Sim");

//     // Botão de confirmação de exclusão
//     $('#modal-delete').on('shown.bs.modal', function (event) {
//         $(".btn-confirm-del").on("click", function(e) {
//             $("button[data-dismiss=modal]").prop("disabled", true).addClass('btn-disabled disabled');
//             $(this).prop("disabled", true).addClass('btn-disabled disabled').html($(this).text() + " <i class='bx bx-loader bx-spin'></i>");
//         });
//     });

//     return false;
// }

// MUDAR O TEMA DO CENÁRIO
function setTheme(theme) {

    const layout = $("body").attr("data-layout");

    $("body").attr("data-layout-color", theme);

    if (layout == "detached") {
        $("body").attr("data-leftbar-theme", theme);
    }

    if (theme == "dark") {
        // MUDAR ÍCONE
        $("#light-dark-mode").find("i").removeClass().addClass("uil uil-sun noti-icon");
        // MUDAR TÍTULO DO TOOLTIP
        $("#light-dark-mode").attr("data-bs-original-title", "Modo Claro");
    } else {
        // MUDAR ÍCONE
        $("#light-dark-mode").find("i").removeClass().addClass("uil uil-moon noti-icon");
        // MUDAR TÍTULO DO TOOLTIP
        $("#light-dark-mode").attr("data-bs-original-title", "Modo Escuro");
    }

    // 🔔 Dispara um evento global
    $(document).trigger('themeChart:changed', [theme]);
}

function scrollHorizontally(el, event) {
    if (el.scrollWidth > el.clientWidth) {
        // Se houver uma barra de rolagem horizontal disponível
        el.scrollLeft += event.deltaY;
        // Impedir a rolagem vertical
        event.preventDefault();
    }
}

function handleDragOver(container, event) {

    var rect = container.getBoundingClientRect();

    console.log("saiu");
    console.log(rect);


    // // Verifique se o cursor está próximo da borda da janela visível
    // var offset = 50; // Margem de 50 pixels

    // if (event.clientX < rect.left + offset) {
    //   // Rolar para a esquerda
    //   container.scrollLeft -= 10; // Ajuste a velocidade de rolagem conforme necessário
    //   event.preventDefault(); // Impedir a ação padrão de arrastar para fora da área
    // } else if (event.clientX > rect.right - offset) {
    //   // Rolar para a direita
    //   container.scrollLeft += 10; // Ajuste a velocidade de rolagem conforme necessário
    //   event.preventDefault(); // Impedir a ação padrão de arrastar para fora da área
    // }
}

function formatRealBr(valor, cifrao = true) {

    valor = parseFloat(valor || 0);

    return new Intl.NumberFormat('pt-BR', {
        style: cifrao ? 'currency' : 'decimal',
        currency: 'BRL',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(valor);
}


// Notificações toastr (Notyf.js)
// Instância global
const _notyf = new Notyf({
    duration: 3000,
    position: {
        x: "right",
        y: "bottom"
    },
    dismissible: true,
    ripple: true,
    types: [
        {
            type: "success",
            background: "#22c55e",
            icon: {
                className: "fa-solid fa-circle-check",
                tagName: "i",
                color: "#fff"
            }
        },
        {
            type: "error",
            background: "#ef4444",
            icon: {
                className: "fa-solid fa-circle-xmark",
                tagName: "i",
                color: "#fff"
            }
        },
        {
            type: "warning",
            background: "#f59e0b",
            icon: {
                className: "fa-solid fa-triangle-exclamation",
                tagName: "i",
                color: "#fff"
            }
        },
        {
            type: "info",
            background: "#3b82f6",
            icon: {
                className: "fas fa-info-circle",
                tagName: "i",
                color: "#fff"
            }
        },
    ]
});

/**
 * Helper para notificações
 */
function notify(message, type = "success", duration = 5, x = "right", y = "bottom", close = true) {

    // alias bootstrap
    if (type === "danger") {
        type = "error";
    }

    _notyf.open({
        type: type,
        message: message,
        duration: duration * 1000,
        dismissible: close,
        position: {
            x: x,
            y: y
        }
    });
}
