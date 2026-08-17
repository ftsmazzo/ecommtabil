"use strict";

(function () {
    function syncTipoLabel($root) {
        const tipo = $root.find("#tipo").val();
        if (!tipo) return;

        const $fixoWrap = $root.find(".valor-group-fixo");
        const $pctWrap  = $root.find(".valor-group-pct");
        const $fixoInput = $root.find("#valor_fixo");
        const $pctInput  = $root.find("#valor_pct");

        if (tipo === "FIXO") {
            const val = $pctInput.val();
            $pctWrap.hide();
            $pctInput.prop("disabled", true).removeAttr("required");
            $fixoWrap.show();
            $fixoInput.prop("disabled", false).attr("required", true);
            if (val && !$fixoInput.val()) $fixoInput.val(val);
        } else {
            const val = $fixoInput.val();
            $fixoWrap.hide();
            $fixoInput.prop("disabled", true).removeAttr("required");
            $pctWrap.show();
            $pctInput.prop("disabled", false).attr("required", true);
            if (val && !$pctInput.val()) $pctInput.val(val);
        }
    }

    function initCashbackRegraForm($scope) {
        const $root = $scope && $scope.length ? $scope : $(document);
        const $form = $root.find("#form-cashback-regra").first();

        if (!$form.length || $form.data("ajaxFormBound")) {
            return;
        }

        $form.data("ajaxFormBound", true);

        if (typeof applyMasks === "function") {
            applyMasks($form);
        }

        syncTipoLabel($root);

        const $inicio = $root.find("#data_inicio");
        const $fim    = $root.find("#data_fim");
        if ($inicio.val()) $fim.attr("min", $inicio.val());
        if ($fim.val())    $inicio.attr("max", $fim.val());

        // O browser já bloqueia via min/max na UI; remove do Validate para evitar falso positivo
        setTimeout(function () {
            if ($form.data("validator")) {
                $inicio.rules("remove", "min max");
                $fim.rules("remove", "min max");
            }
        }, 0);

        $root.find("#tipo").on("change", function () {
            syncTipoLabel($root);
        });
    }

    window.initCashbackRegraForm = initCashbackRegraForm;
    window.registerAjaxModalInitializer(initCashbackRegraForm);

    $(document).ready(function () {
        initCashbackRegraForm($(document));
    });

})();
