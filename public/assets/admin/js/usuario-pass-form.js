"use strict";

$(document).ready(function () {
    const config = window.adminUsuarioPassFormConfig || {};
    const passwordMinLength = Number(window.passwordMinLength || 5);
    const $form = $("#form-usuario-senha");

    if (!$form.length || !$.fn.validate) {
        return;
    }

    const $currentPassword = $form.find("[name='old']");
    const validator = $form.data("validator") || $form.validate();

    validator.settings.ignore = ":hidden";
    validator.settings.messages = {
        ...(validator.settings.messages || {}),
        old: {
            ...(validator.settings.messages?.old || {}),
            remote: "A senha atual informada esta incorreta."
        },
        confirm: {
            ...(validator.settings.messages?.confirm || {}),
            equalTo: "As senhas nao conferem."
        }
    };
    validator.settings.submitHandler = function (form) {
        const $submitButton = $(form).find('button[type="submit"], input[type="submit"]').filter(":enabled:visible").first();
        loadingButton($submitButton.length ? $submitButton : null);
        form.submit();
    };

    $currentPassword.rules("add", {
        required: true,
        remote: {
            url: config.verifyCurrentPasswordUrl || "",
            type: "get",
            data: {
                old: function () {
                    return $currentPassword.val();
                }
            }
        }
    });

    $form.find("[name='senha']").rules("add", {
        required: true,
        minlength: passwordMinLength
    });

    $form.find("[name='confirm']").rules("add", {
        required: true,
        minlength: passwordMinLength,
        equalTo: "#senha"
    });

    $currentPassword.on("blur change keyup", function () {
        validator.element(this);
    });
});
