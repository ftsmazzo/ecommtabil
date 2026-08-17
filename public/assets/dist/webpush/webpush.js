/*
|--------------------------------------------------------------------------
| WebPush Manager - v1.1 (Ajustado para funcionar no 1º carregamento)
|--------------------------------------------------------------------------
*/

window.WebPush = (function () {

    let publicKey = null;
    let userToken = null;
    let auto = false;

    let endpoints = {
        save: "/notificacoes/webpush/subscription-save",
        delete: "/notificacoes/webpush/subscription-delete"
    };

    /* -------------------------------------------------------------
     * Inicialização
     * ---------------------------------------------------------- */
    function init(options) {

        publicKey = options.publicKey || null;
        userToken = options.userToken || null;
        auto = options.autoSubscribe || false;

        if (!publicKey) {
            console.error("[WebPush] PublicKey não definida!");
            return;
        }

        if (options.endpoints) {
            endpoints = { ...endpoints, ...options.endpoints };
        }

        if (!("serviceWorker" in navigator)) {
            console.warn("[WebPush] Service Worker não suportado neste navegador");
            return;
        }

        // Sempre registra e espera ativar
        registerServiceWorker().then(async reg => {

            // Aguarda ativar antes de qualquer subscribe
            await ensureServiceWorkerActivated(reg);

            if (auto) {
                autoSubscribe(reg);
            }
        });
    }

    /* -------------------------------------------------------------
     * Registrar SW
     * ---------------------------------------------------------- */
    async function registerServiceWorker() {
        try {
            const reg = await navigator.serviceWorker.register("/sw.js");
            console.log("[WebPush] SW registrado:", reg);
            return reg;
        } catch (err) {
            console.error("[WebPush] Erro ao registrar SW:", err);
        }
    }

    /* -------------------------------------------------------------
     * Garante que o SW está 100% ativado
     * ---------------------------------------------------------- */
    async function ensureServiceWorkerActivated(reg) {

        if (reg.installing) {
            await waitWorkerState(reg.installing, "activated");
        }

        if (reg.waiting) {
            await waitWorkerState(reg.waiting, "activated");
        }

        // navigator.serviceWorker.ready garante que está ativo
        await navigator.serviceWorker.ready;
    }

    function waitWorkerState(worker, desiredState) {
        return new Promise(resolve => {

            if (worker.state === desiredState) {
                return resolve();
            }

            worker.addEventListener("statechange", () => {
                if (worker.state === desiredState) {
                    resolve();
                }
            });
        });
    }

    /* -------------------------------------------------------------
     * Solicitar permissão
     * ---------------------------------------------------------- */
    async function requestPermission() {
        const result = await Notification.requestPermission();
        return result === "granted";
    }

    /* -------------------------------------------------------------
     * Inscrição manual
     * ---------------------------------------------------------- */
    async function subscribe() {
        return new Promise(async (resolve, reject) => {

            if (!userToken) {
                return reject("userToken não definido.");
            }

            const granted = await requestPermission();
            if (!granted) return reject("Permissão negada.");

            const reg = await navigator.serviceWorker.ready;
            if (!reg) return reject("Service Worker não pronto.");

            const sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey)
            });

            sendSubscriptionToServer(sub)
                .then(() => resolve(sub))
                .catch(reject);
        });
    }

    /* -------------------------------------------------------------
     * Auto subscribe
     * ---------------------------------------------------------- */
    async function autoSubscribe(reg) {

        if (!userToken) {
            console.warn("[WebPush] userToken não fornecido. AutoSubscribe seguirá sem salvar.");
        }

        if (Notification.permission === "default") {
            const granted = await requestPermission();
            if (!granted) return;
        }

        if (Notification.permission !== "granted") return;

        const readyReg = await navigator.serviceWorker.ready;
        const existing = await readyReg.pushManager.getSubscription();

        if (!existing) {
            console.log("[WebPush] Nenhuma inscrição. Criando...");
            await subscribe();
        } else {
            console.log("[WebPush] Já inscrito.");
        }
    }

    /* -------------------------------------------------------------
     * Cancelar inscrição
     * ---------------------------------------------------------- */
    async function unsubscribe() {

        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        if (!sub) return;

        await deleteSubscriptionFromServer(sub);
        await sub.unsubscribe();

        // console.log("[WebPush] Cancelado.");
    }

    /* -------------------------------------------------------------
     * Verificar inscrição
     * ---------------------------------------------------------- */
    async function isSubscribed() {
        if (!('serviceWorker' in navigator)) return false;

        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();

        return !!sub;
    }

    /* -------------------------------------------------------------
     * Enviar para backend
     * ---------------------------------------------------------- */
    async function sendSubscriptionToServer(sub) {

        // console.log("🚀 Enviando subscription pro backend...", sub);

        const payload = {
            userToken,
            endpoint: sub.endpoint,
            keys: {
                p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(sub.getKey("p256dh")))),
                auth: btoa(String.fromCharCode.apply(null, new Uint8Array(sub.getKey("auth"))))
            }
        };

        // console.log("📦 Payload:", payload);

        const res = await fetch(endpoints.save, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const text = await res.text();
        // console.log("📥 Response status:", res.status);
        // console.log("📥 Response body:", text);
        // console.log("📥 Content-Type:", res.headers.get("content-type"));

        let json = null;
        try { json = text ? JSON.parse(text) : null; } catch(e) {}

        if (!json) throw new Error("Resposta não é JSON válido (veja Response body no console).");
        if (!json.success) throw new Error(json.message || "Backend retornou success=false.");
    }

    /* -------------------------------------------------------------
     * Apagar do backend
     * ---------------------------------------------------------- */
    async function deleteSubscriptionFromServer(sub) {
        const payload = { endpoint: sub.endpoint };

        const res = await fetch(endpoints.delete, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        });

        const text = await res.text();
        // console.log("[WebPush] delete status:", res.status);
        // console.log("[WebPush] delete body:", text);

        let json = null;
        try { json = text ? JSON.parse(text) : null; } catch (e) {}

        if (!json) {
            throw new Error("Delete: backend não retornou JSON válido (veja delete body no console).");
        }

        if (!res.ok || !json.success) {
            throw new Error(json.message || json.error || `Delete: HTTP ${res.status}`);
        }

        return json;
    }

    /* -------------------------------------------------------------
     * Convert VAPID
     * ---------------------------------------------------------- */
    function urlBase64ToUint8Array(base64String) {
        const pad = "=".repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + pad).replace(/-/g, "+").replace(/_/g, "/");
        const raw = atob(base64);
        const arr = new Uint8Array(raw.length);
        for (let i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
        return arr;
    }

    return {
        init,
        subscribe,
        unsubscribe,
        isSubscribed
    };

})();
