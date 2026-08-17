const errorClass = "validate-error";
const bootstrapErrorClass = "is-invalid";
const passwordMinLength = Number(window.passwordMinLength || 5);

(function () {
    if (typeof Notyf === "undefined") {
        return;
    }

    const _notyf = new Notyf({
        duration: 5000,
        position: { x: "right", y: "bottom" },
        dismissible: true,
        ripple: true,
        types: [
            {
                type: "success",
                background: "#22c55e",
                icon: { className: "fa-solid fa-circle-check", tagName: "i", color: "#fff" }
            },
            {
                type: "error",
                background: "#ef4444",
                icon: { className: "fa-solid fa-circle-xmark", tagName: "i", color: "#fff" }
            },
            {
                type: "warning",
                background: "#f59e0b",
                icon: { className: "fa-solid fa-triangle-exclamation", tagName: "i", color: "#fff" }
            },
            {
                type: "info",
                background: "#3b82f6",
                icon: { className: "fas fa-info-circle", tagName: "i", color: "#fff" }
            }
        ]
    });

    function notify(message, type, duration, x, y, close) {
        type = type || "info";
        duration = Number(duration || 5) * 1000;
        x = x || "right";
        y = y || "bottom";
        close = close !== false;

        if (type === "danger") {
            type = "error";
        }

        _notyf.open({ type: type, message: message, duration: duration, dismissible: close, position: { x: x, y: y } });
    }

    window.notify = notify;

    function flushFlashQueue() {
        if (!Array.isArray(window.appFlashQueue) || !window.appFlashQueue.length) {
            return;
        }

        const queue = window.appFlashQueue.splice(0, window.appFlashQueue.length);

        queue.forEach(function (item) {
            if (!item || !item.message) {
                return;
            }

            notify(item.message, item.type, item.duration, item.x, item.y, item.close);
        });
    }

    flushFlashQueue();
}());

function getSelectpickerWrapper($el) {
    if (!$el || !$el.length) {
        return $();
    }

    if ($el.hasClass("selectpicker")) {
        return $el.parent(".bootstrap-select");
    }

    const $wrapper = $el.closest(".bootstrap-select");
    return $wrapper.length ? $wrapper : $el.parent(".bootstrap-select");
}

$.validator.setDefaults({
    errorClass: errorClass,
    validClass: "",
    errorElement: "div",

    highlight: function (element) {
        const $el = $(element);
        const $group = $el.closest(".input-group");
        const $selectpicker = getSelectpickerWrapper($el);

        if ($selectpicker.length) {
            $selectpicker.addClass(bootstrapErrorClass);
            $selectpicker.find(".dropdown-toggle").addClass(bootstrapErrorClass);
            $el.removeClass(bootstrapErrorClass);
        } else if ($group.length) {
            $group.addClass(bootstrapErrorClass);
            $el.removeClass(bootstrapErrorClass);
        } else {
            $el.addClass(bootstrapErrorClass);
        }
    },

    unhighlight: function (element) {
        const $el = $(element);
        const $group = $el.closest(".input-group");
        const $selectpicker = getSelectpickerWrapper($el);

        if ($selectpicker.length) {
            $selectpicker.removeClass(bootstrapErrorClass);
            $selectpicker.find(".dropdown-toggle").removeClass(bootstrapErrorClass);
            $el.removeClass(bootstrapErrorClass);
        } else if ($group.length) {
            $group.removeClass(bootstrapErrorClass);
            $el.removeClass(bootstrapErrorClass);
        } else {
            $el.removeClass(bootstrapErrorClass);
        }
    },

    errorPlacement: function (error, element) {
        error.addClass("invalid-feedback");

        const $selectpicker = getSelectpickerWrapper($(element));
        const $group = $(element).closest(".input-group");

        if ($selectpicker.length) {
            error.insertAfter($selectpicker);
        } else if ($group.length) {
            error.insertAfter($group);
        } else {
            error.insertAfter(element);
        }
    }
});

if ($("#form-login").is(":visible")) {
    $("#form-login").validate({
        rules: {
            login: { required: true },
            senha: { required: true }
        },
        submitHandler: function (form) {
            submitWithRecaptcha(form);
        }
    });
}

if ($("#form-password-request").is(":visible")) {
    $("#form-password-request").validate({
        rules: {
            email: {
                required: true,
                email: true
            }
        },
        submitHandler: function (form) {
            submitWithRecaptcha(form);
        }
    });
}

if ($("#form-password-reset").is(":visible")) {
    $("#form-password-reset").validate({
        rules: {
            senha: {
                required: true,
                minlength: passwordMinLength
            },
            confirm: {
                required: true,
                minlength: passwordMinLength,
                equalTo: "#password"
            }
        },
        submitHandler: function (form) {
            submitWithRecaptcha(form);
        }
    });
}

function submitWithRecaptcha(form) {
    if (typeof rkey !== "undefined") {
        waitForRecaptcha(
            function () {
                grecaptcha.ready(function () {
                    grecaptcha.execute(rkey, { action: "submit" }).then(function (token) {
                        upsertRecaptchaToken(form, token);
                        loadingButton($(form).find('button[type="submit"]'));
                        form.submit();
                    }).catch(function () {
                        loadingButton($(form).find('button[type="submit"]'));
                        form.submit();
                    });
                });
            },
            0,
            function () {
                // reCAPTCHA não carregou após 8s — submete sem token
                loadingButton($(form).find('button[type="submit"]'));
                form.submit();
            }
        );
        return;
    }

    loadingButton($(form).find('button[type="submit"]'));
    form.submit();
}

function waitForRecaptcha(callback, attempts = 0, fallback) {
    if (typeof grecaptcha !== "undefined" && typeof grecaptcha.ready === "function") {
        callback();
        return;
    }

    if (attempts >= 80) {
        if (typeof fallback === "function") {
            fallback();
        }
        return;
    }

    window.setTimeout(function () {
        waitForRecaptcha(callback, attempts + 1, fallback);
    }, 100);
}

function upsertRecaptchaToken(form, token) {
    const $hidden = $(form).find('input[name="recaptcha"]');

    if ($hidden.length) {
        $hidden.val(token);
        return;
    }

    $('<input type="hidden" name="recaptcha">')
        .val(token)
        .appendTo(form);
}

function loadingButton(element) {
    if (!element || !element.length) {
        element = $("button[type='submit']");
    }

    const text = element.data("original-text") || String(element.text() || "").trim();

    element
        .data("original-text", text)
        .prop("disabled", true)
        .html(text + ' <span class="btn-spinner" aria-hidden="true"></span>');
}

$(".toggle-pass").on("click", function () {
    const $input = $(this).siblings("input");
    const $icon = $(this).find("i");

    if ($input.attr("type") === "password") {
        $input.attr("type", "text");
        $icon.removeClass("uil-eye").addClass("uil-eye-slash");
    } else {
        $input.attr("type", "password");
        $icon.removeClass("uil-eye-slash").addClass("uil-eye");
    }
});
