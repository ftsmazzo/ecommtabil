(function () {
    const config = window.webPushTopbarConfig || null;
    const toggle = document.querySelector("[data-webpush-toggle='true']");

    if (!config || !toggle || typeof window.WebPush === "undefined") {
        return;
    }

    const statusText = toggle.querySelector("[data-webpush-status-text]");
    const statusBadge = toggle.querySelector("[data-webpush-status-badge]");

    let busy = false;

    function setUi(text, badgeText, badgeClass, disabled = false) {
        if (statusText) {
            statusText.textContent = text;
        }

        if (statusBadge) {
            statusBadge.textContent = badgeText;
            statusBadge.className = "badge filled-outlined " + badgeClass;
        }

        toggle.classList.toggle("disabled", disabled);
        toggle.setAttribute("aria-disabled", disabled ? "true" : "false");
    }

    function inform(message, type = "info") {
        if (typeof window.notify === "function") {
            window.notify(message, type);
            return;
        }

        alert(message);
    }

    async function refreshState() {
        if (!("Notification" in window) || !("serviceWorker" in navigator)) {
            setUi("Nao suportado neste navegador", "Indisponivel", "bg-secondary", true);
            return;
        }

        if (Notification.permission === "denied") {
            setUi("Bloqueadas no navegador", "Bloqueadas", "bg-danger");
            return;
        }

        try {
            const subscribed = await window.WebPush.isSubscribed();

            if (subscribed) {
                setUi("Ativas neste navegador", "Ativadas", "bg-success");
            } else {
                setUi("Clique para ativar", "Desativadas", "bg-warning");
            }
        } catch (error) {
            console.error(error);
            setUi("Nao foi possivel verificar", "Erro", "bg-danger");
        }
    }

    async function onToggle(event) {
        event.preventDefault();

        if (busy) {
            return;
        }

        if (!("Notification" in window) || !("serviceWorker" in navigator)) {
            inform("Este navegador nao suporta notificacoes push.", "warning");
            return;
        }

        if (Notification.permission === "denied") {
            inform("As notificacoes estao bloqueadas no navegador. Libere nas configuracoes do site.", "warning");
            return;
        }

        busy = true;
        setUi("Processando...", "...", "bg-primary", true);

        try {
            const subscribed = await window.WebPush.isSubscribed();

            if (subscribed) {
                await window.WebPush.unsubscribe();
                inform("Notificacoes desativadas neste navegador.", "success");
            } else {
                await window.WebPush.subscribe();
                inform("Notificacoes ativadas com sucesso.", "success");
            }
        } catch (error) {
            console.error(error);
            inform(error?.message || "Nao foi possivel alterar o status das notificacoes.", "danger");
        } finally {
            busy = false;
            await refreshState();
        }
    }

    window.WebPush.init({
        publicKey: config.publicKey,
        autoSubscribe: false,
        userToken: config.userToken,
        endpoints: config.endpoints || {}
    });

    toggle.addEventListener("click", onToggle);
    refreshState();
})();
