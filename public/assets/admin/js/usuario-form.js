"use strict";

$(document).ready(function () {
    const config = window.adminUsuarioFormConfig || {};
    const passwordMinLength = Number(window.passwordMinLength || 5);
    const $form = $("#form-usuario-novo, #form-usuario-editar").first();
    const isNewForm = $form.is("#form-usuario-novo");

    if (!$form.length) {
        return;
    }

    applyMasks($form);
    uppers("nome,cargo", $form);

    const $foto = $("#foto");
    const $profile = $form.find("[name=id_perfil]");
    const $permissionsWrap = $("#permissoes");
    const $permissions = $permissionsWrap.find("input[type=checkbox][name='permissoes[]']");
    const $toggleAll = $("#p-check-all");
    const $login = $form.find("[name='login']");
    const loginCheckUrl = config.loginCheckUrl || "";
    const profilePermissionsUrl = config.profilePermissionsUrl || "";
    const currentUserId = config.currentUserId || "";

    if ($foto.length && $.fn.dizuploader) {
        $foto.dizuploader();
    }

    if ($form.length && $.fn.validate) {
        const validator = $form.data("validator") || $form.validate();
        const loginRules = {
            required: true
        };
        const senhaRules = isNewForm
            ? {
                required: true,
                minlength: passwordMinLength
            }
            : {
                minlength: passwordMinLength
            };

        validator.settings.ignore = ":hidden:not([name='senha']):not([name='permissoes[]'])";
        validator.settings.messages = {
            ...(validator.settings.messages || {}),
            login: {
                ...(validator.settings.messages?.login || {}),
                remote: "Este login já está em uso."
            }
        };
        validator.settings.submitHandler = function (form) {
            const $submitButton = $(form).find('button[type="submit"], input[type="submit"]').filter(":enabled:visible").first();
            loadingButton($submitButton.length ? $submitButton : null);
            form.submit();
        };

        if (loginCheckUrl) {
            loginRules.remote = {
                url: loginCheckUrl,
                type: "get",
                data: {
                    login: function () {
                        return $login.val();
                    },
                    id: function () {
                        return currentUserId;
                    }
                }
            };
        }

        $form.find("[name='nome']").rules("add", {
            required: true
        });

        $login.rules("add", loginRules);
        $form.find("[name='senha']").rules("add", senhaRules);

        $login.on("blur change keyup", function () {
            if ($(this).is(":disabled")) {
                return;
            }

            validator.element(this);
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

    function applyProfilePermissions(permissionIds) {
        const normalized = (permissionIds || []).map(function (value) {
            return parseInt(value, 10);
        });

        $permissions.prop("checked", false);

        $permissions.each(function () {
            const checkboxValue = parseInt($(this).val(), 10);

            if (normalized.includes(checkboxValue)) {
                $(this).prop("checked", true);
            }
        });

        updateAllToggleStates();
    }

    if ($profile.length && profilePermissionsUrl) {
        $profile.on("change", function () {
            const idPerfil = $(this).val();
            applyProfilePermissions([]);

            if (!idPerfil) {
                return;
            }

            $.get(profilePermissionsUrl.replace("__ID__", idPerfil), function (permissionIds) {
                applyProfilePermissions(permissionIds);
            });
        });
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

    $(document).on("click", "#enableInput, .enableInput", function () {
        $login.prop("disabled", false).trigger("focus");
        $login.valid();
        $(this).remove();
    });

    updateAllToggleStates();
});
