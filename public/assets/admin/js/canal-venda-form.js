"use strict";

(function () {
    function initCanalVendaForm($scope) {
        const $root = $scope && $scope.length ? $scope : $(document);
        const $form = $root.find("#form-canal-venda").first();

        if (!$form.length || $form.data("ajaxFormBound")) {
            return;
        }

        $form.data("ajaxFormBound", true);

        uppers("nome", $root);

        const $logo = $root.find("#cv-logo");
        if ($logo.length && $.fn.dizuploader) {
            $logo.dizuploader();
        }

        bindDeleteLogo($root);
    }

    window.initCanalVendaForm = initCanalVendaForm;
    window.registerAjaxModalInitializer(initCanalVendaForm);

    $(document).ready(function () {
        initCanalVendaForm($(document));
    });
})();
