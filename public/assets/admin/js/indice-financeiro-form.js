"use strict";

(function () {
    // Inicializa o formulário principal do índice (modal new ou page editar)
    function initIndiceFinanceiroForm($scope) {
        const $root = $scope && $scope.length ? $scope : $(document);
        const $form = $root.find("#form-indice-financeiro").first();

        if (!$form.length || $form.data("ajaxFormBound")) {
            return;
        }

        $form.data("ajaxFormBound", true);
        uppers("nome,sigla", $root);
    }

    // Inicializa o formulário de histórico dentro do modal
    function initIndiceHistoricoForm($scope) {
        const $root = $scope && $scope.length ? $scope : $(document);
        const $form = $root.find("#form-indice-historico").first();

        if (!$form.length || $form.data("ajaxFormBound")) {
            return;
        }

        $form.data("ajaxFormBound", true);
    }

    // Bind do botão excluir no histórico inline (AJAX)
    function bindDeleteHistorico() {
        const cfg = window.indiceFinanceiroConfig || {};

        $(document).off("click.deleteHistorico").on("click.deleteHistorico", ".btn-delete-historico", function () {
            const $btn = $(this);
            const id   = $btn.data("id");
            const url  = cfg.historicoDeleteUrl;
            const csrf = cfg.csrf;

            if (!url || !id) { return; }

            const theme = $("body").attr("data-layout-color");

            $.confirm({
                title: "Confirmação",
                content: "Deseja realmente excluir este registro?",
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
                                url: url,
                                method: "POST",
                                dataType: "json",
                                data: { id: id, csrf: csrf },
                                success: function (res) {
                                    if (res && res.error) {
                                        notify(res.message || "Erro ao excluir.", "danger");
                                        loadingButton($btn, true);
                                        return;
                                    }
                                    $('tr[data-historico-id="' + id + '"]').fadeOut(200, function () {
                                        $(this).remove();
                                        if ($("#historico-table-wrap tbody tr").length === 0) {
                                            location.reload();
                                        }
                                    });
                                    notify(res.message || "Registro removido.", "success");
                                },
                                error: function () {
                                    notify("Erro ao excluir registro.", "danger");
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

    window.initIndiceFinanceiroForm = initIndiceFinanceiroForm;
    window.initIndiceHistoricoForm  = initIndiceHistoricoForm;

    window.registerAjaxModalInitializer(initIndiceFinanceiroForm);
    window.registerAjaxModalInitializer(initIndiceHistoricoForm);

    $(document).ready(function () {
        initIndiceFinanceiroForm($(document));
        initIndiceHistoricoForm($(document));
        bindDeleteHistorico();
    });
})();
