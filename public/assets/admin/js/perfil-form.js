"use strict";

$(document).ready(function () {
    const config = window.adminPerfilFormConfig || {};
    const $form = $("#form-perfil");
    const $permissionsWrap = $("#permissoes");
    const $permissions = $permissionsWrap.find("input[type=checkbox][name='permissoes[]']");
    const $toggleAll = $("#p-check-all");

    void config;

    if ($form.length && $.fn.validate) {
        $form.validate({
            rules: {
                nome: {
                    required: true
                }
            },
            submitHandler: function (form) {
                const $submitButton = $(form).find('button[type="submit"], input[type="submit"]').filter(":enabled:visible").first();
                loadingButton($submitButton.length ? $submitButton : null);
                form.submit();
            }
        });
    }

    function updateGlobalToggleState() {
        const total = $permissions.length;
        const checked = $permissions.filter(":checked").length;

        $toggleAll.prop("checked", total > 0 && checked === total);
        $toggleAll.prop("indeterminate", checked > 0 && checked < total);
    }

    function updateCardToggleState($card) {
        const $cardPermissions = $card.find("input[type=checkbox][name='permissoes[]']");
        const $cardToggle = $card.find(".permission-card-toggle");
        const total = $cardPermissions.length;
        const checked = $cardPermissions.filter(":checked").length;

        $cardToggle.prop("checked", total > 0 && checked === total);
        $cardToggle.prop("indeterminate", checked > 0 && checked < total);
    }

    function updateAllToggleStates() {
        $permissionsWrap.find(".permission-block").each(function () {
            updateCardToggleState($(this));
        });

        updateGlobalToggleState();
    }

    $toggleAll.on("change", function () {
        $permissions.prop("checked", $(this).is(":checked"));
        updateAllToggleStates();
    });

    $permissionsWrap.on("change", ".permission-card-toggle", function () {
        const $card = $(this).closest(".permission-block");
        const checked = $(this).is(":checked");

        $card.find("input[type=checkbox][name='permissoes[]']").prop("checked", checked);
        updateAllToggleStates();
    });

    $permissions.on("change", function () {
        updateAllToggleStates();
    });

    updateAllToggleStates();
});
