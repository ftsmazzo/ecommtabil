if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js')
        .then(function(reg) {
            // console.log("Service Worker registrado com sucesso!", reg);
        })
        .catch(function(err) {
            // console.error("Erro ao registrar o Service Worker:", err);
        });
}