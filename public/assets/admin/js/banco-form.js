"use strict";

(function () {
    function initBancoForm($scope) {
        const $root = $scope && $scope.length ? $scope : $(document);
        const $form = $root.find("#form-banco").first();

        if (!$form.length || $form.data("ajaxFormBound")) {
            return;
        }

        $form.data("ajaxFormBound", true);

        uppers("nome", $root);

        const $cnpj = $root.find("#cnpj");
        if ($cnpj.length && typeof $.fn.mask === "function") {
            $cnpj.mask("00.000.000/0000-00");
        }

        const $logo = $root.find("#banco-logo");
        if ($logo.length && $.fn.dizuploader) {
            $logo.dizuploader();
        }

        bindDeleteLogo($root);
    }

    window.initBancoForm = initBancoForm;
    window.registerAjaxModalInitializer(initBancoForm);

    $(document).ready(function () {
        initBancoForm($(document));
    });
})();
