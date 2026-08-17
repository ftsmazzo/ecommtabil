/* ---------------------------------------------------------
   Service Worker - Web Push Handler (Corrigido e Organizado)
--------------------------------------------------------- */

self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", event => event.waitUntil(self.clients.claim()));

/* ---------------------------------------------------------
   PUSH Event
--------------------------------------------------------- */
self.addEventListener("push", event => {
  event.waitUntil((async () => {

    let payload = {};

    if (event.data) {
      try {
        payload = event.data.json();
      } catch {
        payload = { title: "Nova notificação", body: event.data.text() };
      }
    } else {
      payload = {
        title: "Notificação",
        body: "Você tem uma nova mensagem."
      };
    }

    const title = payload.title || "Nova notificação";

    const options = {
      body: payload.body || "",
      icon: payload.icon || "/storage/layout/icon-192.png",
      badge: payload.badge || "/storage/layout/badge-72.png",
      image: payload.image || undefined,

      vibrate: payload.vibrate || [100, 50, 100],

      tag: payload.tag || undefined,
      renotify: payload.renotify || false,

      data: {
        url: payload.url || null,
        payloadData: payload.data || null,
        receivedAt: Date.now()
      },

      actions: Array.isArray(payload.actions) ? payload.actions : []
    };

    return self.registration.showNotification(title, options);

  })());
});

/* ---------------------------------------------------------
   CLICK Event
--------------------------------------------------------- */
self.addEventListener("notificationclick", event => {
  event.notification.close();

  const action = event.action || null;
  const data = event.notification.data || {};

  const targetUrl = data.url
      ? new URL(data.url, self.location.origin).href
      : null;

  // Clique no corpo da notificação
  if (!action) {
      if (targetUrl) {
          return event.waitUntil(clients.openWindow(targetUrl));
      }
      return;
  }

  event.waitUntil((async () => {

    // ---------------- ACTION: FECHAR ----------------
    if (action === "close") {
        return;
    }

    // ---------------- ACTION: ABRIR URL ----------------
    if (action === "open-url") {
        return clients.openWindow(targetUrl || "/");
    }

    // ---------------- ACTION: ABRIR APP ----------------
    if (action === "open-app") {
        return clients.openWindow("/");
    }

    // ---------------- ACTION: CONFIRMAR ----------------
    if (action.startsWith("confirm-")) {
        const id = action.replace("confirm-", "");
        console.log("Confirmação recebida do SW ->", id);
        return;
    }

    // ---------------- ACTION: REJEITAR ----------------
    if (action.startsWith("reject-")) {
        const id = action.replace("reject-", "");
        console.log("Rejeição recebida do SW ->", id);
        return;
    }

    // Se não for ação válida, tenta abrir a URL
    if (targetUrl) {
        return clients.openWindow(targetUrl);
    }

  })());
});

/* ---------------------------------------------------------
   CLOSE Event
--------------------------------------------------------- */
self.addEventListener("notificationclose", event => {
  // opcional
});

/* ---------------------------------------------------------
   Subscription Change
--------------------------------------------------------- */
self.addEventListener("pushsubscriptionchange", event => {
  event.waitUntil((async () => {
    console.warn("pushsubscriptionchange — cliente precisará reassinar.");
  })());
});


/* ---------------------------------------------------------
   Utils
--------------------------------------------------------- */
function urlBase64ToUint8Array(base64String) {
  const padding = "=".repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, "+").replace(/_/g, "/");
  const rawData = atob(base64);
  const output = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    output[i] = rawData.charCodeAt(i);
  }
  return output;
}
