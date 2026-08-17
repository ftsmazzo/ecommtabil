"use strict";

(function () {
    function initFidelidadeRegraForm($scope) {
        const $root = $scope && $scope.length ? $scope : $(document);
        const $form = $root.find("#form-fidelidade-regra").first();

        if (!$form.length || $form.data("ajaxFormBound")) {
            return;
        }

        $form.data("ajaxFormBound", true);

        if (typeof applyMasks === "function") {
            applyMasks($form);
        }

        const $inicio = $root.find("#data_inicio");
        const $fim    = $root.find("#data_fim");
        if ($inicio.val()) $fim.attr("min", $inicio.val());
        if ($fim.val())    $inicio.attr("max", $fim.val());

        setTimeout(function () {
            if ($form.data("validator")) {
                $inicio.rules("remove", "min max");
                $fim.rules("remove", "min max");
            }
        }, 0);
    }

    window.initFidelidadeRegraForm = initFidelidadeRegraForm;
    window.registerAjaxModalInitializer(initFidelidadeRegraForm);

    $(document).ready(function () {
        initFidelidadeRegraForm($(document));
    });
})();
