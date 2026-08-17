"use strict";

// Formulario de ramo de cliente carregado via modal AJAX.
// O util.js abre o modal e, quando o HTML chega, dispara os inicializadores
// registrados em window.ajaxModalInitializers.
(function () {
    function initClienteRamoForm($scope) {
        const $root = $scope && $scope.length ? $scope : $(document);
        const $form = $root.find("#form-cliente-ramo").first();

        if (!$form.length || $form.data("ajaxFormBound")) {
            return;
        }

        $form.data("ajaxFormBound", true);

        uppers("descricao", $root);
    }

    window.initClienteRamoForm = initClienteRamoForm;
    window.registerAjaxModalInitializer(initClienteRamoForm);

    $(document).ready(function () {
        initClienteRamoForm($(document));
    });
})();
