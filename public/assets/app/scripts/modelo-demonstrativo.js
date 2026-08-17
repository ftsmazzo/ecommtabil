(function () {
    "use strict";

    var $form = $("#form-modelo-demonstrativo-arvore");
    var $raiz = document.getElementById("dre-arvore-raiz");
    var $vazia = document.getElementById("dre-arvore-vazia");
    var template = document.getElementById("dre-no-template");
    var contasDisponiveis = window.dreContasDisponiveis || [];
    var tmpCounter = 0;

    function esconderVazia() {
        if ($vazia) $vazia.style.display = "none";
    }

    function rotuloConta(c) {
        return c.nome + " (" + (c.natureza === "aumenta" ? "A" : "D") + ")";
    }

    function popularSelectContas(select, idSelecionado) {
        var valorAtual = idSelecionado != null ? String(idSelecionado) : (select.value || "");
        select.innerHTML = "";

        var optVazia = document.createElement("option");
        optVazia.value = "";
        optVazia.textContent = "— Selecione uma conta —";
        select.appendChild(optVazia);

        var grupos = { sintetica: null, analitica: null };
        var rotulosGrupo = { sintetica: "Sintéticas", analitica: "Analíticas" };

        contasDisponiveis.forEach(function (c) {
            if (!grupos[c.tipo]) {
                grupos[c.tipo] = document.createElement("optgroup");
                grupos[c.tipo].label = rotulosGrupo[c.tipo] || c.tipo;
            }
            var opt = document.createElement("option");
            opt.value = String(c.id);
            opt.textContent = rotuloConta(c);
            grupos[c.tipo].appendChild(opt);
        });

        ["sintetica", "analitica"].forEach(function (tipo) {
            if (grupos[tipo]) select.appendChild(grupos[tipo]);
        });

        select.value = valorAtual;
    }

    function initSortable(ul) {
        if (!ul || ul.sortableInstance) return;
        ul.sortableInstance = Sortable.create(ul, {
            group: "dre-arvore",
            handle: ".dre-no-handle",
            animation: 150,
            direction: "vertical",
            swapThreshold: 0.65,
            invertSwap: true,
            emptyInsertThreshold: 30,
            fallbackOnBody: true,
            onEnd: function (evt) {
                aplicarRegraExclusividade(evt.to.closest(".dre-no"));
            },
        });
    }

    function aplicarRegraExclusividade(liPai) {
        if (!liPai) return;
        var filhosUl = liPai.querySelector(":scope > .dre-no-filhos");
        var tipo = liPai.dataset.tipo;
        if ((tipo === "conta" || tipo === "totalizador") && filhosUl && filhosUl.children.length > 0) {
            var $select = $(liPai).find("> .dre-no-linha > .dre-no-tipo-select");
            $select.val("organizador").trigger("change");
        }
    }

    function aplicarTipo(li, tipo) {
        li.dataset.tipo = tipo;
        li.className = li.className.replace(/dre-no--\S+/g, "").trim() + " dre-no--" + tipo;

        var $li = $(li);
        var $nome = $li.find("> .dre-no-linha > .dre-no-nome");
        var $selectConta = $li.find("> .dre-no-linha > .dre-no-select-conta");
        var $igual = $li.find("> .dre-no-linha > .dre-no-igual");
        var $addFilho = $li.find("> .dre-no-linha > .dre-no-add-filho");
        var $filhosUl = $li.find("> .dre-no-filhos");
        var selectContaEl = li.querySelector(":scope > .dre-no-linha > .dre-no-select-conta");

        $igual.toggleClass("d-none", tipo !== "totalizador");

        if (tipo === "conta") {
            $nome.addClass("d-none");
            $selectConta.removeClass("d-none");
            $addFilho.addClass("d-none");
            $filhosUl.empty();
            if (selectContaEl && !selectContaEl.dataset.populated) {
                popularSelectContas(selectContaEl);
                selectContaEl.dataset.populated = "1";
            }
        } else if (tipo === "totalizador") {
            $nome.removeClass("d-none");
            $selectConta.addClass("d-none");
            $addFilho.addClass("d-none");
            if (selectContaEl) selectContaEl.value = "";
            $filhosUl.empty();
        } else {
            $nome.removeClass("d-none");
            $selectConta.addClass("d-none");
            $addFilho.removeClass("d-none");
            if (selectContaEl) selectContaEl.value = "";
        }
    }

    function criarNo(ulDestino) {
        var frag = template.content.cloneNode(true);
        var li = frag.querySelector("li");
        li.dataset.id = "";
        li.dataset.idTmp = "tmp-" + (++tmpCounter);
        ulDestino.appendChild(li);
        initSortable(li.querySelector(":scope > .dre-no-filhos"));
        esconderVazia();
        return li;
    }

    function removerNo(li) {
        li.remove();
    }

    $(document).on("change", ".dre-no-tipo-select", function () {
        var li = this.closest(".dre-no");
        aplicarTipo(li, this.value);
    });

    $(document).on("click", ".dre-no-add-filho", function () {
        var li = this.closest(".dre-no");
        var ulFilhos = li.querySelector(":scope > .dre-no-filhos");
        criarNo(ulFilhos);
    });

    $(document).on("click", ".dre-no-remove", function () {
        var li = this.closest(".dre-no");
        removerNo(li);
    });

    document.getElementById("dre-add-raiz").addEventListener("click", function () {
        criarNo($raiz);
    });

    function serializarArvore(ul) {
        return Array.prototype.slice.call(ul.children).map(function (li, i) {
            var tipo = li.dataset.tipo;
            var no = {
                id: li.dataset.id || null,
                tipo: tipo,
                nome: "",
                ordem: i,
            };

            if (tipo === "conta") {
                var select = li.querySelector(":scope > .dre-no-linha > .dre-no-select-conta");
                var valor = select ? select.value : "";
                var opt = select && select.options[select.selectedIndex];
                no.nome = opt && opt.value ? opt.textContent.replace(/\s*\([AD]\)$/, "").trim() : "";
                no.id_conta = valor ? parseInt(valor, 10) : null;
                no.filhos = [];
            } else {
                var nomeInput = li.querySelector(":scope > .dre-no-linha > .dre-no-nome");
                no.nome = nomeInput ? nomeInput.value.trim() : "";
                no.id_conta = null;
                var filhosUl = li.querySelector(":scope > .dre-no-filhos");
                no.filhos = filhosUl ? serializarArvore(filhosUl) : [];
            }

            return no;
        });
    }

    $form.on("submit", function () {
        var arvore = serializarArvore($raiz);
        document.getElementById("dre-arvore-json").value = JSON.stringify(arvore);
    });

    document.querySelectorAll(".dre-no-filhos, #dre-arvore-raiz").forEach(initSortable);
    document.querySelectorAll(".dre-no[data-tipo='conta'] > .dre-no-linha > .dre-no-select-conta").forEach(function (select) {
        popularSelectContas(select, select.value);
        select.dataset.populated = "1";
    });
})();
