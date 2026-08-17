"use strict";

// Formulario de situacao de cliente carregado via modal AJAX.
// O util.js abre o modal e, quando o HTML chega, dispara os inicializadores
// registrados em window.ajaxModalInitializers.
(function () {
    function initClienteSituacaoForm($scope) {
        const $root = $scope && $scope.length ? $scope : $(document);
        const $form = $root.find("#form-cliente-situacao").first();

        if (!$form.length || $form.data("ajaxFormBound")) {
            return;
        }

        $form.data("ajaxFormBound", true);

        uppers("descricao", $root);
    }

    window.initClienteSituacaoForm = initClienteSituacaoForm;
    window.registerAjaxModalInitializer(initClienteSituacaoForm);

    $(document).ready(function () {
        initClienteSituacaoForm($(document));
    });
})();
