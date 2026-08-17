// =========================
// HELPERS DE TEXTO / DADOS
// =========================

function stripHtmlToText(html) {
    const tmp = document.createElement("div");
    tmp.innerHTML = html ?? "";

    return String(tmp.textContent || tmp.innerText || "")
        .replace(/\u200B/g, "")
        .trim();
}

function formatDate(date) {
    if (!date) {
        return "";
    }

    const parts = String(date).split("-");

    return parts.length === 3 ? parts.reverse().join("/") : String(date);
}

function string_to_slug(str, separator = "-") {
    return String(str || "")
        .trim()
        .toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .replace(/[^a-z0-9\s-]/g, "")
        .replace(/\s+/g, "-")
        .replace(/-+/g, "-")
        .replace(/^-+|-+$/g, "")
        .replace(/-/g, separator);
}

// =========================
// HELPERS JQUERY
// =========================

jQuery.fn.extend({
    prependClass: function (newClasses) {
        return this.each(function () {
            const currentClasses = $(this).prop("class") || "";
            $(this).removeClass(currentClasses).addClass(`${newClasses} ${currentClasses}`.trim());
        });
    }
});
