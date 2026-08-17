"use strict";

(function () {
    function initCartaoBandeiraForm($scope) {
        const $root = $scope && $scope.length ? $scope : $(document);
        const $form = $root.find("#form-cartao-bandeira").first();

        if (!$form.length || $form.data("ajaxFormBound")) {
            return;
        }

        $form.data("ajaxFormBound", true);

        uppers("nome,bandeira", $root);

        const $logo = $root.find("#cb-logo");
        if ($logo.length && $.fn.dizuploader) {
            $logo.dizuploader();
        }

        bindDeleteLogo($root);
    }

    window.initCartaoBandeiraForm = initCartaoBandeiraForm;
    window.registerAjaxModalInitializer(initCartaoBandeiraForm);

    $(document).ready(function () {
        initCartaoBandeiraForm($(document));
    });
})();
