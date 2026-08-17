function toRealBr(v) {
  v = String(v ?? "0").replace(",", ".");
  var n = parseFloat(v);
  if (!isFinite(n)) n = 0;
  return n.toFixed(2).replace(".", ",");
}

$('#alterar-valor').on('show.bs.modal', function (event) {

    var target = $(event.relatedTarget);

    // ⚠️ NÃO use target.data() aqui (cache do jQuery)
    var servico = parseInt(target.attr('data-servico') || "0", 10);
    var origem  = target.attr('data-origem') || "cliente";
    var nome    = target.attr('data-nome') || "";
    var valor   = target.attr('data-valor'); // string

    const modal = $(this);
    const body  = modal.find(".modal-body");
    const form  = $("#form-alterar-valor");

    body.find("#service-name").html(nome);

    // preenche hidden
    form.find("[name=id_servico]").val(servico);
    form.find("[name=origem_atual]").val(origem);

    // preenche campo com o valor atual
    valor = (valor !== undefined && valor !== null && valor !== "") ? valor : "0";
    body.find("[name=valor]").val(toRealBr(valor));

    // (re)inicializa validação
    if (form.data("validator")) {
        form.validate().destroy();
    }

    form.validate({
        rules: {
            valor: { required: true }
        },
        submitHandler: function(frm) {

            var $btn = $("#form-alterar-valor button[type=submit]");
            loadingButton($btn);

            $.ajax({
                type: "post",
                url: url_admin + "/controle/servico/valor-update",
                data: form.serialize(),
                dataType: "json",
                success: function(resp){

                    loadingButton($btn, true);

                    if(!resp || !resp.ok){
                        alert(resp?.message || "Erro ao salvar");
                        return;
                    }

                    if (resp.valor_servico) {

                        const sid   = resp.valor_servico.id_servico;
                        const vnum  = resp.valor_servico.valor;

                        $("#ctrl-serv-" + sid).html(formatRealBr(vnum, true));

                        // ✅ Atualiza atributo E cache do jQuery (se alguém usar .data em outro lugar)
                        const $btnEdit = $('button[data-bs-target="#alterar-valor"][data-servico="' + sid + '"]');
                        $btnEdit.attr("data-valor", vnum);
                        $btnEdit.attr("data-origem", "manual");
                        $btnEdit.data("valor", vnum);
                        $btnEdit.data("origem", "manual");
                    }

                    if (resp.servico) {
                        $("#total-servico-" + resp.servico.id).html(resp.servico.quantidade);

                        const $valSrv = $("#valor-total-servico-" + resp.servico.id);
                        if ($valSrv.length) {
                            $valSrv.html(formatRealBr(resp.servico.valor, false));
                        }
                    }

                    if (resp.empresas && Array.isArray(resp.empresas)) {
                        resp.empresas.forEach(function(e){
                            if (!e || !e.id) return;

                            const $cell = $("#valor-total-empresa-" + e.id);
                            if ($cell.length) {
                                $cell.html(formatRealBr(e.valor, false));
                            }
                        });
                    }

                    if (resp.geral) {

                        $("#quantidade-total").html(resp.geral.quantidade);

                        if ($("#valor-total").length) {
                            $("#valor-total").html(formatRealBr(resp.geral.valor, false));
                        }

                        if ($("#funcionarios-total").length) {
                            $("#funcionarios-total").html(resp.geral.funcionarios);
                        }

                        // ✅ faltava esse:
                        const $display = $("#valor-total-display");
                        if ($display.length) {
                          $display.html(formatRealBr(resp.geral.valor, false));
                        }
                    }

                    $("#alterar-valor").modal("hide");

                },
                error: function () {
                    loadingButton($btn, true);
                    alert("Erro de comunicação ao atualizar valor.");
                }
            });
        }
    });

});

/* ============================
   JS FINAL UNIFICADO (OTIMIZADO)
   - sincroniza scroll da tabela com barra fixa
   - calcula inner-width compensando sidebar
   - atualiza --col1-width (coluna fixa)
   - cria e posiciona o muro fixo no lado esquerdo
   ============================ */

document.addEventListener("DOMContentLoaded", () => {

  const wrapper = document.getElementById("table-scroll");   // área que rola
  const table   = document.getElementById("table-controle"); // tabela real
  const fixed   = document.getElementById("scroll-sync");    // barra fixa

  if (!wrapper || !table || !fixed) return;


  // ============================
  // HOVER DE COLUNA
  // ============================
// Bloquear por índice (0-based): ex 0 = primeira coluna
  const blockedIndexes = new Set([5]); // ajuste aqui

  // Classe opcional no TH para bloquear coluna
  const blockedClass = "no-col-hover";

  function clearHovers() {
    table.querySelectorAll(".col-hover").forEach(el => el.classList.remove("col-hover"));
    table.querySelectorAll(".cell-hover").forEach(el => el.classList.remove("cell-hover"));
  }

  // pega a “linha de header” mais confiável (thead tr, senão primeira tr com th)
  function getHeaderRow() {
    return table.querySelector("thead tr")
      || table.querySelector("tr:has(th)")
      || null;
  }

  function isBlockedColumn(colIndex) {
    if (blockedIndexes.has(colIndex)) return true;

    const headerRow = getHeaderRow();
    if (!headerRow) return false;

    const headerCell = headerRow.children[colIndex];
    return !!(headerCell && headerCell.classList.contains(blockedClass));
  }

  table.addEventListener("mouseover", function (e) {
    const cell = e.target.closest("td, th");
    if (!cell || !table.contains(cell)) return;

    const colIndex = cell.cellIndex;
    if (colIndex == null) return;

    clearHovers();

    // se a coluna for bloqueada, não aplica NADA
    if (isBlockedColumn(colIndex)) return;

    // só aplica quando NÃO estiver bloqueada
    cell.classList.add("cell-hover");

    // pinta a coluna (menos a célula atual)
    table.querySelectorAll("tr").forEach(tr => {
      const colCell = tr.children[colIndex];
      if (!colCell) return;
      if (colCell === cell) return;
      colCell.classList.add("col-hover");
    });
  });

  table.addEventListener("mouseleave", function () {
    clearHovers();
  });




  /* ---------- cria inner da barra fixa, se não existir ---------- */
  let inner = fixed.firstElementChild;
  if (!inner) {
    inner = document.createElement("div");
    inner.style.height = "1px";
    fixed.appendChild(inner);
  }

  /* ---------- cria muro esquerdo fixo ---------- */
  let wall = document.getElementById("table-left-wall");
  if (!wall) {
    wall = document.createElement("div");
    wall.id = "table-left-wall";
    document.body.appendChild(wall);
  }

  /* ---------- recalcula a largura do inner ---------- */
  function updateInnerWidth() {
    const wrapperScrollWidth = wrapper.scrollWidth;
    const fixedClientWidth   = fixed.clientWidth;
    const wrapperClientWidth = wrapper.clientWidth;

    const innerWidth = Math.max(
      0,
      Math.ceil(wrapperScrollWidth + (fixedClientWidth - wrapperClientWidth))
    );

    inner.style.width = innerWidth + "px";
    fixed.scrollLeft = wrapper.scrollLeft;

    fixStickyWidths();
    // positionWall();
  }

  /* ---------- sincronização bidirecional ---------- */
  let fromFixed = false, fromWrapper = false;

  fixed.addEventListener("scroll", () => {
    if (fromWrapper) return;
    fromFixed = true;
    wrapper.scrollLeft = fixed.scrollLeft;
    fromFixed = false;
  }, { passive: true });

  wrapper.addEventListener("scroll", () => {
    if (fromFixed) return;
    fromWrapper = true;
    fixed.scrollLeft = wrapper.scrollLeft;
    fromWrapper = false;
  }, { passive: true });

  /* ---------- ajusta largura real da coluna fixa ---------- */
  function fixStickyWidths() {
    const th1 = table.querySelector("th:nth-child(1)");
    if (!th1) return;

    const w1 = th1.offsetWidth;
    document.documentElement.style.setProperty("--col1-width", `${w1}px`);
    document.documentElement.style.setProperty("--col-offset", "0px");
  }

  /* ---------- posiciona o muro lateral ---------- */
  function positionWall() {
    const rect = wrapper.getBoundingClientRect();

    if (rect.bottom <= 0 || rect.top >= window.innerHeight) {
      wall.style.display = "none";
      return;
    }

    wall.style.display = "block";
    wall.style.left = `${Math.round(rect.left)}px`;
    wall.style.top = `${Math.round(rect.top)}px`;
    wall.style.height = `${Math.round(rect.height)}px`;
  }

  /* ---------- inicialização ---------- */
  requestAnimationFrame(updateInnerWidth);
  setTimeout(updateInnerWidth, 80);
  window.addEventListener("resize", updateInnerWidth);
//   window.addEventListener("scroll", positionWall);

  /* ---------- Observe mudanças no wrapper e tabela ---------- */
  if (window.ResizeObserver) {
    const ro = new ResizeObserver(updateInnerWidth);
    ro.observe(wrapper);
    ro.observe(table);
  } else {
    let last = wrapper.scrollWidth;
    setInterval(() => {
      if (wrapper.scrollWidth !== last) {
        last = wrapper.scrollWidth;
        updateInnerWidth();
      }
    }, 300);
  }

  /* ---------- ajuste final da coluna fixa ---------- */
  function ajustarSticky() {
    const col1 = table.querySelector("th:nth-child(1)");
    if (!col1) return;

    const larguraReal = col1.getBoundingClientRect().width;
    document.documentElement.style.setProperty("--col1-width", `${larguraReal}px`);
  }

  window.addEventListener("load", ajustarSticky);
  window.addEventListener("resize", ajustarSticky);
});


/* Peity Graficos */
$(document).ready(function () {
    $(".peity.pie").peity("pie")
    $(".peity.pie.normal").peity("pie", { fill: ["#888", "#f2f2f2"] })
});

/** Popover Ajax */

$(function () {

    // $('.btn-responsavel').each(function () {

    //     // Inicializa o popover vazio
    //     $(this).popover({
    //         trigger: 'hover',
    //         html: true,
    //         sanitize: false,
    //         content: '<div class="text-center p-2">Carregando...</div>',
    //         placement: 'top'
    //     });

    // });

    // Evento de mouseenter para AJAX
    $(document).on('mouseenter', '.btn-responsavel', function () {

        var $btn = $(this);
        var id = $btn.data('id');
        var pop = bootstrap.Popover.getInstance(this);

        pop.show(); // mostra imediatamente com "Carregando..."

        // Se quiser evitar requisições múltiplas:
        if ($btn.data('loaded')) return;


        // $.ajax({
            // url: '/rota/detalhes',
            // type: 'GET',
            // data: { id: id },
            // success: function (html) {

                // popover real no DOM
                var $popover = $('.popover.show');
                var $popoverBody = $popover.find('.popover-body');

                if ($popover.length) {
                    // $popoverBody.html(html);

                    $popoverBody.html("ID: " + id)

                    // reposiciona após mudar tamanho
                    pop._popper.update();

                    // marca como carregado (cache)
                    $btn.data('loaded', true);
                }
            // }
        // });

    });


    // ============================
    // MODAL DE ADICIONAR SERVIÇOS
    // ============================

    let current = {
        id_controle: null,
        id_empresa: null,
        id_servico: null
    };

    // permite o inline setar o "current" e reaproveitar atualizarTabelaMae(response)
    window.__ces_set_current = function (idc, ide, ids) {
      current.id_controle = idc;
      current.id_empresa  = ide;
      current.id_servico  = ids;
    };


    let $modal = $('#modal-add-servico');
    let $body  = $modal.find('.modal-body');
    let $cell  = null;


    /* ============================
    * TOOLTIP
    * ============================ */

    // ✅ FIX 1: "trigger" aqui é jQuery object; .closest('td') nele não funciona como você espera.
    // ✅ FIX 1: sempre pegar o TD real (HTMLElement), esconder/dispose e limpar tooltips órfãos.
    function esconderTooltip(trigger) {
        try {
            const el = trigger && trigger[0] ? trigger[0] : trigger; // aceita jQuery ou HTMLElement
            const td = el ? el.closest('td') : null;

            if (!td) {
                removerTooltipsFlutuantes();
                return;
            }

            $cell = $(td);

            // bootstrap tooltip instance
            try {
                const inst = bootstrap.Tooltip.getInstance(td);
                if (inst) {
                    inst.hide();
                    inst.dispose();
                }
            } catch (e) {}

            // jQuery tooltip (se existir)
            try {
                if ($cell.data('bs.tooltip')) {
                    $cell.tooltip('hide');
                    setTimeout(() => {
                        try { $cell.tooltip('dispose'); } catch (e) {}
                        removerTooltipsFlutuantes();
                    }, 50);
                } else {
                    removerTooltipsFlutuantes();
                }
            } catch (e) {
                removerTooltipsFlutuantes();
            }

        } catch (e) {
            removerTooltipsFlutuantes();
        }
    }

    /* ============================
    * LOADING
    * ============================ */
    function setLoading() {
        $body.html(`
            <div class="text-center p-5">
                <div class="spinner-border" role="status"></div>
            </div>
        `);
    }

    /* ============================
    * MODAL TITLE
    * ============================ */
    function setModalTitle(title) {
        $modal.find('.modal-title').text(title);
    }

    /* ============================
    * AJAX LOADERS
    * ============================ */
    function carregarFormAdicionar() {
        setLoading();
        setModalTitle("Adicionar Serviço");

        $.post(
            app.base + "/admin/controle/servico/form-adicionar",
            current,
            function (html) {
                $body.html(html);
                $body.find('input[name="quantidade"]').focus();
                bindForm();
                bindBotoes();
            }
        );
    }

    function carregarHistorico() {
        setLoading();
        setModalTitle("Histórico de Lançamentos");

        $.post(
            app.base + "/admin/controle/servico/historico",
            current,
            function (html) {
                $body.html(html);
                bindBotoes();
            }
        );
    }

    /* ============================
    * FORM
    * ============================ */
    function bindForm() {

        let $form = $("#form-controle-add-servico");
        if (!$form.length) return;

        bindQuantidade();

        $form.validate({
            submitHandler: function (form) {
                enviarFormulario(form);
            }
        });
    }

    function bindQuantidade() {

        $body.find('.plus').off().on('click', function () {
            let $input = $(this).siblings('input[type="number"]');
            let val = parseInt($input.val()) || 0;
            $input.val(val + 1).trigger('change');
        });

        $body.find('.minus').off().on('click', function () {
            let $input = $(this).siblings('input[type="number"]');
            let val = parseInt($input.val()) || 1;
            if (val > 1) {
                $input.val(val - 1).trigger('change');
            }
        });
    }

    function enviarFormulario(form) {

        $.ajax({
            type: "post",
            url: app.base + "/admin/controle/servico/adicionar",
            data: $(form).serialize(),
            dataType: "json",
            beforeSend: function () {
                loadingButton("#form-controle-add-servico button[type=submit]");
            },
            success: function (response) {

                if (response.error) {
                    showToastr(response?.error || 'Erro ao processar solicitação', 'danger');
                    return;
                }

                atualizarTabelaMae(response);
                $modal.modal('hide');
            },
            error: function () {
                showToastr('Erro ao processar solicitação', 'danger');
            }
        });
    }

    /* ============================
    * ATUALIZA TABELA MÃE
    * ============================ */
    function atualizarTabelaMae(response) {

        // console.log("atualizarTabelaMae called", response);

        const empresa = response.empresa;
        const servico = response.servico;
        const geral   = response.geral;

        // tenta pegar o id_controle do response (preferencial)
        // fallback para variável global current (se existir)
        const idControle =
            (response && response.current && response.current.id_controle)
                ? response.current.id_controle
                : (typeof current !== "undefined" ? current.id_controle : null);

        if (!idControle) {
            console.warn("atualizarTabelaMae: id_controle não encontrado no response");
            return;
        }

        /* 🔹 CÉLULA EMPRESA x SERVIÇO */
        (function () {

            const selTd =
                'td.js-cell-servico' +
                '[data-controle="' + idControle + '"]' +
                '[data-empresa="' + empresa.id + '"]' +
                '[data-servico="' + servico.id + '"]';

            const $td = $(selTd);

            if ($td.length) {

                // modo inline (span)
                const $span = $td.find(".js-qtd");
                if ($span.length) {
                    // response.total = quantidade final da célula
                    $span.text(String(response.total));
                    return;
                }
            }

            // fallback antigo (modo link/modal)
            $(
                'a[data-controle="' + idControle + '"]' +
                '[data-empresa="' + empresa.id + '"]' +
                '[data-servico="' + servico.id + '"]'
            ).html(String(response.total));

        })();

        /* 🔹 TOTAL POR SERVIÇO (linha QUANTIDADE TOTAL) */
        if ($("#total-servico-" + servico.id).length) {
            $("#total-servico-" + servico.id).html(servico.quantidade);
        }

        /* 🔹 TOTAL POR EMPRESA (quantidade) */
        if ($("#total-empresa-" + empresa.id).length) {
            $("#total-empresa-" + empresa.id).html(empresa.quantidade);
        }

        // só atualiza a coluna de funcionários se o backend disser que mudou
        if (response && response.funcionarios_atualizados === true) {
            /* 🔹 TOTAL FUNCIONÁRIOS DO MÊS (por empresa) */
            if ($("#total-funcionarios-mes-" + empresa.id).length) {
                $("#total-funcionarios-mes-" + empresa.id).html(empresa.total_funcionarios_mes);
            }

            /* 🔹 TOTAL FUNCIONÁRIOS GERAL (rodapé) */
            if ($("#funcionarios-total").length) {
                $("#funcionarios-total").html(geral.funcionarios);
            }
        }

        /* 🔹 TOTAL GERAL (quantidade) */
        if ($("#quantidade-total").length) {
            $("#quantidade-total").html(geral.quantidade);
        }


        /* 🔹 VALORES (quando a tela tem valores) */

        // valor total por empresa
        if ($("#valor-total-empresa-" + empresa.id).length) {
            $("#valor-total-empresa-" + empresa.id)
                .html(formatRealBr(empresa.valor, false));
        }

        // valor total por serviço
        if ($("#valor-total-servico-" + servico.id).length) {
            $("#valor-total-servico-" + servico.id)
                .html(formatRealBr(servico.valor, false));
        }

        // valor total geral
        if ($("#valor-total").length) {
            $("#valor-total")
                .html(formatRealBr(geral.valor, false));
        }

        // display alternativo (se existir)
        const $display = $("#valor-total-display");
        if ($display.length) {
            console.log("existe o display!");
            $display.html(formatRealBr(geral.valor, false));
        }


    }

    // expõe globalmente para o inline
    window.__ces_apply_response = atualizarTabelaMae;


    /* ============================
    * BOTÕES
    * ============================ */
    function bindBotoes() {

        $body.off("click", ".btn-historico")
            .on("click", ".btn-historico", function () {
                carregarHistorico();
            });

        $body.off("click", ".btn-voltar")
            .on("click", ".btn-voltar", function () {
                carregarFormAdicionar();
            });

        $body.off("click", ".btn-fechar-modal")
            .on("click", ".btn-fechar-modal", function () {
                $modal.modal('hide');
            });
    }

    /* ============================
    * ESTORNAR
    * ============================ */
    $(document).on("click", ".btn-estornar", function () {

        let idHistorico = $(this).data("id");

        $.confirm({
            title: 'Confirmação',
            content: 'Deseja realmente estornar este lançamento?',
            type: 'red',
            buttons: {
                confirm: {
                    text: 'Estornar',
                    btnClass: 'btn-red',
                    action: function () {
                        estornarLancamento(idHistorico);
                    }
                },
                cancel: {
                    text: 'Cancelar'
                }
            }
        });
    });

    function estornarLancamento(idHistorico) {

        $.post(
            app.base + "/admin/controle/servico/estornar",
            { id: idHistorico },
            function (response) {

                if (response.error) {
                    showToastr(response?.error || 'Erro ao estornar lançamento', 'danger');
                    return;
                }

                atualizarTabelaMae(response);
                carregarHistorico();
            },
            "json"
        );
    }

    /* ============================
    * MODAL EVENTS
    * ============================ */
    $modal.on('shown.bs.modal', function (event) {

        let trigger = $(event.relatedTarget || window.__ces_context_trigger);
        window.__ces_context_trigger = null;

        current.id_controle = trigger.data('controle');
        current.id_empresa  = trigger.data('empresa');
        current.id_servico  = trigger.data('servico');
        current.acao        = trigger.data('acao');

        esconderTooltip(trigger);

        if (current.acao === 'historico') {
            carregarHistorico();
        } else {
            carregarFormAdicionar();
        }

    }).on('hidden.bs.modal', function () {

        if ($cell) restaurarTooltip($cell);

        $body.html('');
        current = {
            id_controle: null,
            id_empresa: null,
            id_servico: null,
            acao: null
        };
    });

});


// ============================
// INLINE EDIT - CELULA SERVICO
// ============================
// (function () {

//   // trava pra não abrir 2 edições ao mesmo tempo
//   let editing = null;

//   function startEdit(td) {
//     if (!td) return;

//     // não deixa editar enquanto já tem outra célula editando
//     if (editing && editing !== td) {
//       const prevInput = editing.querySelector("input.js-inline-input");
//       if (prevInput) finishEdit(prevInput, { save: false });
//     }

//     // se já tem input, ignora
//     if (td.querySelector("input.js-inline-input")) return;

//     const span = td.querySelector(".js-qtd");
//     if (!span) return;

//     const oldValue = parseInt(span.textContent.trim(), 10) || 0;

//     const input = document.createElement("input");
//     input.type = "number";
//     input.min = "0";
//     input.step = "1";
//     input.value = oldValue;
//     input.className = "form-control form-control-sm js-inline-input";
//     input.style.maxWidth = "70px";
//     input.style.display = "inline-block";
//     input.style.textAlign = "center";

//     // guarda o valor antigo NO INPUT (mais seguro do que td.dataset)
//     input.dataset.oldValue = String(oldValue);

//     // troca span por input
//     span.style.display = "none";
//     td.appendChild(input);

//     editing = td;

//     // foco rápido/estável
//     requestAnimationFrame(() => {
//       input.focus({ preventScroll: true });
//       input.select();
//     });

//     // Enter salva / Esc cancela
//     input.addEventListener("keydown", (e) => {
//       if (e.key === "Enter") {
//         e.preventDefault();
//         finishEdit(input, { save: true });
//       } else if (e.key === "Escape") {
//         e.preventDefault();
//         finishEdit(input, { save: false });
//       }
//     });

//     // blur salva (mas se já finalizou pelo keydown, não faz nada)
//     input.addEventListener("blur", () => finishEdit(input, { save: true }));
//   }


// function finishEdit(td, cancel = false) {
//   if (!td) return;

//   const input = td.querySelector("input.js-inline-input");
//   const span  = td.querySelector(".js-qtd");

//   if (!input || !span) return;

//   // trava para não executar 2x (blur + enter etc)
//   if (td.__finishing) return;
//   td.__finishing = true;

//   const oldVal = parseInt(td.dataset.oldValue || "0", 10) || 0;
//   let newVal   = parseInt(input.value || "0", 10);

//   if (!Number.isFinite(newVal) || newVal < 0) newVal = oldVal;

//   // ids da célula
//   const idControle = parseInt(td.dataset.controle || "0", 10) || 0;
//   const idEmpresa  = parseInt(td.dataset.empresa  || "0", 10) || 0;
//   const idServico  = parseInt(td.dataset.servico  || "0", 10) || 0;

//   // 1) remove input e restaura span IMEDIATO (sem lag visual)
//   try {
//     input.remove();
//   } catch (e) {
//     if (input.parentNode) input.parentNode.removeChild(input);
//   }

//   span.style.display = "";
//   span.textContent = String(cancel ? oldVal : newVal);

//   // limpa estado
//   delete td.dataset.oldValue;
//   td.classList.remove("is-editing");

//   // tooltip órfão
//   removerTooltipsFlutuantes();

//   // 2) se cancelou ou não mudou: encerra
//   if (cancel || newVal === oldVal) {
//     td.__finishing = false;
//     td.__editing = false;
//     return;
//   }

//   // 3) faz salvar (mandando quantidade_final, backend calcula delta)
//   const payload = {
//     id_controle: idControle,
//     id_empresa:  idEmpresa,
//     id_servico:  idServico,
//     quantidade_final: newVal
//   };

//   // feedback simples (opcional)
//   td.classList.add("opacity-75");


// }




//   // delegação: clique no span da célula
//   $(document).on("click", "td.js-cell-servico .js-qtd", function (e) {
//       e.preventDefault();
//       e.stopPropagation();

//       const td = this.closest("td.js-cell-servico");
//       if (!td) return;

//       // ✅ some tooltip ANTES de mexer no DOM
//       esconderTooltipDoTD(td);

//       // se o conteúdo clicado estiver dentro de <a>, esconde no <a> também
//       const a = this.closest("a");
//       if (a) esconderTooltipDoTD(a);

//       // evita "tooltip fantasma" que o bootstrap às vezes deixa no body
//       removerTooltipsFlutuantes();

//       startEdit(td);
//   });


//   // impede que clique no ícone de histórico dispare o edit
//   $(document).on("click", "td.js-cell-servico .js-hist", function (e) {
//     e.stopPropagation();
//   });

// })();


// ============================
// INLINE EDIT - CELULA SERVICO
// ============================
(function () {

  let editingTd = null;

  function startEdit(td) {
    if (!td) return;

    if (editingTd && editingTd !== td) finishEdit(editingTd, true);

    if (td.querySelector("input.js-inline-input")) return;

    const span = td.querySelector(".js-qtd");
    if (!span) return;

    esconderTooltipDoTD(td);

    const oldValue = parseInt(span.textContent.trim(), 10) || 0;
    td.dataset.oldValue = String(oldValue);

    const input = document.createElement("input");
    input.type = "number";
    input.min = "0";
    input.step = "1";
    input.value = oldValue;
    input.className = "form-control form-control-sm js-inline-input";
    input.style.width = "70px";
    input.style.margin = "0 auto";
    input.style.display = "block";
    input.style.textAlign = "center";

    span.style.display = "none";
    td.appendChild(input);

    editingTd = td;

    input.focus({ preventScroll: true });
    input.select();

    input.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        finishEdit(td, true);
      } else if (e.key === "Escape") {
        e.preventDefault();
        finishEdit(td, false);
      }
    });

    input.addEventListener("blur", () => finishEdit(td, true), { once: true });
  }

  function finishEdit(td, save = true) {
    if (!td) return;

    const input = td.querySelector("input.js-inline-input");
    const span  = td.querySelector(".js-qtd");

    if (!input || !span) {
      editingTd = null;
      return;
    }

    if (td.__finishing) return;
    td.__finishing = true;

    const oldVal = parseInt(td.dataset.oldValue || "0", 10) || 0;
    const newVal = parseInt(input.value || "0", 10) || 0;

    if (input.parentNode === td) td.removeChild(input);

    span.style.display = "";
    span.textContent = String(save ? newVal : oldVal);

    delete td.__finishing;
    editingTd = null;

    removerTooltipsFlutuantes();

    if (!save) return;
    if (newVal === oldVal) return;

    const delta = newVal - oldVal;

    // otimista: atualiza oldValue já
    td.dataset.oldValue = String(newVal);

    saveInlineDelta(td, newVal, delta);

  }

  function getCsrf() {
    // tenta achar qualquer csrf escondido na página
    const el = document.querySelector('input[name="csrf"]');
    return el ? el.value : "";
  }

  function saveInlineDelta(td, qtde, delta) {

    const id_controle = parseInt(td.dataset.controle || "0", 10) || 0;
    const id_empresa  = parseInt(td.dataset.empresa  || "0", 10) || 0;
    const id_servico  = parseInt(td.dataset.servico  || "0", 10) || 0;

    if (!id_controle || !id_empresa || !id_servico) return;

    const $span = $(td).find(".js-qtd");
    const oldVal = parseInt($(td).data("oldValue") || "0", 10) || 0;

    // seta current pro atualizarTabelaMae funcionar
    if (window.__ces_set_current) window.__ces_set_current(id_controle, id_empresa, id_servico);

    $.ajax({
      type: "post",
      url: app.base + "/admin/controle/servico/inline-salvar",
      dataType: "json",
      data: {
        csrf: getCsrf(),
        id_controle,
        id_empresa,
        id_servico,
        delta,
        quantidade: qtde
      },
      success: function (resp) {

          if (resp?.error) {
            $span.text(String(oldVal));
            showToastr(resp?.message || "Erro ao salvar", "danger");
            return;
          }

          if (window.__ces_apply_response) {
            window.__ces_apply_response(resp);
          }
      },
      error: function () {
        showToastr("Erro ao salvar", "danger");
      }
    });
  }

  $(document).on("click", "td.js-cell-servico .js-qtd", function (e) {
    e.preventDefault();
    e.stopPropagation();
    const td = this.closest("td.js-cell-servico");
    startEdit(td);
  });

  $(document).on("click", "td.js-cell-servico", function (e) {
    if (e.target && e.target.matches("input.js-inline-input")) return;
    const td = this;
    if (!td.classList.contains("js-cell-servico")) return;
    startEdit(td);
  });

})();








// ============================
// CONTEXT MENU - BOTÃO DIREITO
// ============================
(function () {

  const menu = document.getElementById("ces-context-menu");
  if (!menu) return;

  const btnLancar = document.getElementById("ces-cm-lancar");
  const btnHist   = document.getElementById("ces-cm-historico");

  // estado do item clicado
  let cm = { id_controle: 0, id_empresa: 0, id_servico: 0 };

  function hideMenu() {
    menu.style.display = "none";
  }

  function showMenu(x, y) {
    menu.style.left = x + "px";
    menu.style.top  = y + "px";
    menu.style.display = "block";
  }

  // abre modal usando teu fluxo atual (shown.bs.modal lê data-*)
  function openModal(acao) {
    hideMenu();

    // cria um "trigger fake" com os data-* que teu modal já espera
    const a = document.createElement("a");
    a.href = "javascript:void(0);";
    a.dataset.bsToggle = "modal";
    a.dataset.bsTarget = "#modal-add-servico";
    a.dataset.controle = String(cm.id_controle);
    a.dataset.empresa  = String(cm.id_empresa);
    a.dataset.servico  = String(cm.id_servico);
    a.dataset.acao     = String(acao);

    // abre via bootstrap (para preencher event.relatedTarget)
    const elModal = document.getElementById("modal-add-servico");
    if (!elModal) return;

    const modal = bootstrap.Modal.getOrCreateInstance(elModal);

    // guardamos o trigger globalmente.
    window.__ces_context_trigger = a;
    modal.show();
  }

  // botão direito na célula
  document.addEventListener("contextmenu", function (e) {
    const td = e.target.closest("td.js-cell-servico");
    if (!td) return;

    e.preventDefault();

    cm.id_controle = parseInt(td.dataset.controle || "0", 10) || 0;
    cm.id_empresa  = parseInt(td.dataset.empresa  || "0", 10) || 0;
    cm.id_servico  = parseInt(td.dataset.servico  || "0", 10) || 0;

    showMenu(e.clientX, e.clientY);
  });

  // ações do menu
  btnLancar.addEventListener("click", function () {
    openModal("add");
  });

  btnHist.addEventListener("click", function () {
    openModal("historico");
  });

  // fechar ao clicar fora / scroll / ESC
  document.addEventListener("click", function (e) {
    if (menu.style.display === "block" && !menu.contains(e.target)) hideMenu();
  });

  window.addEventListener("scroll", hideMenu, true);
  window.addEventListener("resize", hideMenu);

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") hideMenu();
  });

})();


function removerTooltipsFlutuantes() {
  // remove apenas tooltips ÓRFÃOS (sem nenhum elemento apontando aria-describedby)
  // e com pequeno delay pra não quebrar a transição do Bootstrap
  setTimeout(() => {
    document.querySelectorAll(".tooltip").forEach((tip) => {
      const id = tip.getAttribute("id");
      if (!id) return;

      // se ainda existe algum elemento "dono" desse tooltip, não remove
      const owner = document.querySelector(`[aria-describedby="${id}"]`);
      if (owner) return;

      // remove com segurança
      try { tip.remove(); } catch (e) {}
    });
  }, 200);
}

function esconderTooltipDoTD(el) {
  try {
    if (!el) return;

    // bootstrap tooltip instance (pode dar null)
    const inst = bootstrap.Tooltip.getInstance(el);
    if (inst) {
      try { inst.hide(); } catch (e) {}
      try { inst.dispose(); } catch (e) {}
    }

    // jQuery tooltip (caso tenha sido criado via $(el).tooltip())
    if (window.jQuery) {
      const $el = $(el);

      // só tenta se realmente existe instância
      if ($el.data("bs.tooltip")) {
        try { $el.tooltip("hide"); } catch (e) {}
        try { $el.tooltip("dispose"); } catch (e) {}
      }
    }

  } catch (e) {
    // ignora
  }

  // remove tooltips órfãos (bootstrap deixa no body às vezes)
  removerTooltipsFlutuantes();
}


// ===============================
// POPOVER AVATARES (Bootstrap 5.3)
// ===============================
document.addEventListener('DOMContentLoaded', () => {

  // Fecha todos
  const hideAll = () => {
    document.querySelectorAll('.js-avatar-pop[aria-describedby]').forEach(a => {
      const inst = bootstrap.Popover.getInstance(a);
      if (inst) inst.hide();
    });
  };

  document.querySelectorAll('.js-avatar-pop').forEach((el) => {

    // Se já existir instância antiga, mata (evita sumir botões / duplicar handlers)
    const old = bootstrap.Popover.getInstance(el);
    if (old) old.dispose();

    const pop = new bootstrap.Popover(el, {
      trigger: 'manual',
      html: true,
      sanitize: false,
      placement: 'top',
      container: 'body'
      // content vem do data-bs-content automaticamente
    });

    let hideTimer = null;

    const clearHide = () => {
      if (hideTimer) clearTimeout(hideTimer);
      hideTimer = null;
    };

    const scheduleHide = () => {
      clearHide();
      hideTimer = setTimeout(() => pop.hide(), 250);
    };

    el.addEventListener('mouseenter', () => {
      clearHide();
      hideAll();     // opcional: garante 1 aberto por vez
      pop.show();
    });

    el.addEventListener('mouseleave', () => {
      scheduleHide();
    });

    // Quando o popover existir no DOM, liga hover no próprio balão
    el.addEventListener('shown.bs.popover', () => {
      const tip = pop.getTipElement();
      if (!tip) return;

      if (tip.dataset.boundHover === '1') return;
      tip.dataset.boundHover = '1';

      tip.addEventListener('mouseenter', () => clearHide());
      tip.addEventListener('mouseleave', () => scheduleHide());
    });
  });

  // Clicar fora fecha
  document.addEventListener('click', (e) => {
    const inPopover = e.target.closest('.popover');
    const inTrigger = e.target.closest('.js-avatar-pop');
    if (inPopover || inTrigger) return;
    hideAll();
  });

});


// ===============================
// MODAL: ENVIAR MENSAGEM
// ===============================

// Abrir modal a partir do popover
$(document).on('click', '.js-msg-resp', function () {

  const btn = $(this);

  const idUsuario = btn.data('id-usuario');
  const nome = btn.data('nome') || '—';
  const funcao = btn.data('funcao'); // R | C

  // encontra o popover e o avatar que o abriu
  const popEl = btn.closest('.popover')[0];
  const popId = popEl ? popEl.getAttribute('id') : null;

  const trigger = popId
    ? document.querySelector('.js-avatar-pop[aria-describedby="' + popId + '"]')
    : null;

  const wrap = trigger ? $(trigger).closest('.resp-wrap') : $();

  const idControle   = wrap.data('id-controle');
  const idEmpresa    = wrap.data('id-empresa');
  const empresaNome  = wrap.data('empresa-nome') || '';
  const dataEntrega  = wrap.data('data-entrega') || '';

  // preenche modal
  $('#msg-resp-nome').text(nome);
  $('#msg-resp-texto').val('');

  $('#msg-resp-id-usuario').val(idUsuario);
  $('#msg-resp-id-controle').val(idControle);
  $('#msg-resp-id-empresa').val(idEmpresa);
  $('#msg-resp-funcao').val(funcao);

  // monta chips
  const chipsWrap = $('#msg-atalhos');
  chipsWrap.empty();

  if (empresaNome) {
    chipsWrap.append(
      `<span class="msg-chip" data-text="Empresa: ${empresaNome}">Nome da Empresa</span>`
    );
  }

  if (dataEntrega) {
    chipsWrap.append(
      `<span class="msg-chip" data-text="Data de entrega: ${dataEntrega}">Data de Entrega</span>`
    );
  }

  // abre modal
  new bootstrap.Modal(document.getElementById('modal-msg-resp')).show();





});


// ===============================
// CLICK NOS CHIPS
// Insere texto no final do textarea
// ===============================
$(document).on('click', '.msg-chip', function () {

    const txt = $(this).data('text');
    const ta = $('#msg-resp-texto');

    let val = ta.val();

    // se não termina com espaço ou quebra de linha, adiciona um espaço
    if (val && !val.endsWith(' ') && !val.endsWith('\n')) {
        val += ' ';
    }

    ta.val(val + txt + ' ');
    ta.focus();
});


// ===============================
// ENVIAR MENSAGEM
// ===============================
$(document).on('click', '#btn-enviar-msg-resp', function () {

  const payload = {
    id_usuario: $('#msg-resp-id-usuario').val(),
    id_controle: $('#msg-resp-id-controle').val(),
    id_empresa: $('#msg-resp-id-empresa').val(),
    funcao: $('#msg-resp-funcao').val(),
    mensagem: $('#msg-resp-texto').val()
  };

  if (!payload.mensagem || !payload.mensagem.trim()) {
    $('#msg-resp-texto').focus();
    return;
  }

  $.post('/admin/controle/responsavel/mensagem', payload)
    .done(() => {
      bootstrap.Modal
        .getInstance(document.getElementById('modal-msg-resp'))
        .hide();
    })
    .fail(() => {
      alert('Falha ao enviar mensagem.');
    });
});



// ===============================
// BOTÃO: EXCLUIR (popover)
// ===============================
$(document).on('click', '.js-excluir-responsavel', function () {
    const btn = $(this);
    const idUsuario = btn.data('id-usuario');
    const funcao = btn.data('funcao');

    const popEl = btn.closest('.popover')[0];
    const popId = popEl ? popEl.getAttribute('id') : null;

    const trigger = popId
      ? document.querySelector('.js-avatar-pop[aria-describedby="' + popId + '"]')
      : null;

    const wrap = trigger ? $(trigger).closest('.resp-wrap') : $();

    const idControle = wrap.data('id-controle');
    const idEmpresa  = wrap.data('id-empresa');

    const url =
      '/admin/controle/responsavel/excluir' +
      '?id_controle=' + encodeURIComponent(idControle) +
      '&id_empresa='  + encodeURIComponent(idEmpresa) +
      '&id_usuario='  + encodeURIComponent(idUsuario) +
      '&funcao='      + encodeURIComponent(funcao);

    const theme = $("body").attr("data-layout-color");

    $.confirm({
        title: 'Confirmação',
        content: 'Deseja realmente excluir este usuario?',
        theme: 'modern ' + theme,
        backgroundDismiss: true,
        icon: 'fa-solid fa-circle-xmark',
        type: 'coral',
        typeAnimated: true,
        animateFromElement: false,
        buttons: {
            confirm: { text: 'Sim', btnClass: 'btn-coral', action: () => location.href = url },
            cancel: { text: 'Não' }
        }
    });
});

// ================
// GRAFICO DOS STATUS
// ================
const statusData = params.situacoes || {};

const chartEl = document.getElementById('chart-por-situacao');

if (chartEl && Object.keys(statusData).length) {

  const chart = echarts.init(chartEl);

  // Mapa de cores (Bootstrap-like)
  const bsColorMap = {
    success: '#198754',
    danger:  '#dc3545',
    warning: '#ffc107',
    info:    '#0dcaf0',
    primary: '#0d6efd',
    secondary:'#6c757d',
    light:   '#adb5bd',
    dark:    '#212529'
  };

  // dados (mantém somente > 0)
  const pieData = Object.values(statusData)
    // .filter(i => Number(i.quantidade) > 0) // não mostrar zeros
    .map(i => ({
      name: i.label,
      value: Number(i.quantidade),
      status: i.status,
      class: i.class,
      icon: i.icon,
      itemStyle: {
        color: bsColorMap[i.class] || '#6c757d'
      }
    }));

  const option = {
    tooltip: {
      trigger: 'item',
      formatter: (p) => `
        <div style="min-width:180px">
          <div><b>${p.data.name}</b> <span style="opacity:.7">(${p.data.status})</span></div>
          <div>Qtd: <b>${p.data.value}</b></div>
          <div>%: <b>${p.percent.toFixed(1)}%</b></div>
        </div>
      `
    },
    legend: {
      orient: 'vertical',
      top: 20,
      right: 10,
      type: 'scroll'
    },
    series: [
      {
        name: 'Status',
        type: 'pie',
        // radius: ['55%', '78%'], // deixar doughnt
        center: ['35%', '50%'],
        avoidLabelOverlap: true,
        itemStyle: {
          borderRadius: 8,
          borderWidth: 2,
          borderColor: '#fff'
        },
        // label: {
        //   show: true,
        //   formatter: (p) => `${p.name}\n${p.percent.toFixed(1)}%`
        // },
        // labelLine: {
        //   length: 12,
        //   length2: 10
        // },

        label: {
          show: false   // 🔥 remove texto externo
        },

        labelLine: {
          show: false   // 🔥 remove linhas indicadoras
        },
        data: pieData
      }
    ]
  };

  chart.setOption(option);
  window.addEventListener('resize', () => chart.resize());
}

// ================
// ENVIAR DOCUMENTOS
// ================

(function () {

    const $modal = $('#send-docs');
    const $loading = $modal.find('#docs-loading');
    const $content = $modal.find('#docs-content');

    let current = { id_controle: 0, id_empresa: 0 };

    $modal.on('show.bs.modal', function (ev) {
        const trigger = ev.relatedTarget;
        if (!trigger) return;

        current.id_controle = Number(trigger.getAttribute('data-id-controle') || 0);
        current.id_empresa  = Number(trigger.getAttribute('data-id-empresa') || 0);

        $loading.removeClass('d-none').text('Carregando...');
        $content.addClass('d-none').html('');

        $.ajax({
            type: 'POST',
            url: url_admin + '/controle/listar-documentos',
            data: {
              id_controle: current.id_controle,
              id_empresa: current.id_empresa
            },
            success: function (html) {
                $loading.addClass('d-none');
                $content.html(html).removeClass('d-none');
            },
            error: function (r) {
              res = JSON.parse(r);
              showToastr(res.mesage ?? 'Falha ao carregar os documentos.', 'danger');
            }
        });
    });

  // próxima etapa (upload) vamos implementar no próximo passo





})();