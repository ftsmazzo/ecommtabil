let cachedChartData = null;

$(document).on('themeChart:changed', function (e, newTheme) {
    if (cachedChartData) {
        renderCharts(cachedChartData);
    }
});

$(document).ready(function () {

    // CHAMAR RESULTADOS
    getResults($("#form-demonstrativo-resutados").serialize());

    $("#form-demonstrativo-resutados").submit(function(e) {
        e.preventDefault();
        $("#btn-send").addClass('disabled').prop('disabled', true).html('<i class="fa-solid fa-arrows-rotate fa-spin"></i> Carregando...');
        getResults($(this).serialize());
        return false;
    });

    // ============================
    // NAO DEIXAR SELECIONAR SEM SER SEQUENCIAL
    // ============================
    let ultimaSelecao = [];

    $('#meses').on('changed.bs.select', function () {
        let selecionados = $(this).val();

        if (!selecionados || selecionados.length <= 1) {
            ultimaSelecao = selecionados;
            return;
        }

        // Converte para número e ordena
        let meses = selecionados.map(Number).sort((a, b) => a - b);

        let sequencial = true;
        for (let i = 1; i < meses.length; i++) {
            if (meses[i] !== meses[i - 1] + 1) {
                sequencial = false;
                break;
            }
        }

        if (!sequencial) {
            alert('Os meses devem ser selecionados de forma sequencial.');
            $('#meses').selectpicker('val', ultimaSelecao);
        } else {
            ultimaSelecao = selecionados;
        }
    });

});


// 🔹 Função auxiliar para mostrar mensagem de carregamento
function showLoadingMessage(containerId, message) {
    const theme = $('body').attr("data-layout-color");
    const el = document.getElementById(containerId);
    if (el) {
        echarts.dispose(el); // remove gráfico anterior
        el.innerHTML = `
            <div class="fa-fade" style="
                display:flex;
                justify-content:center;
                align-items:center;
                height:100%;
                color:${theme === 'dark' ? '#ccc' : '#555'};
                font-size:20px;
                font-weight:500;
            ">
                <i class="fa-solid fa-arrows-rotate fa-spin me-2"></i>
                ${message}
            </div>
        `;
    }
}

function renderCharts(response) {

    const theme = $('body').attr("data-layout-color");

    ['grafico_cascata', 'grafico_mensal', 'grafico_anos', 'grafico_franquia', 'grafico_despesa'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            echarts.dispose(el); // remove instância anterior
            el.innerHTML = "";   // remove mensagem de carregamento
        }
    });

    let chart_cascata, chart_mensal, chart_anos, chart_franquia, chart_despesa;

    if (theme == 'dark') {
        chart_cascata = echarts.init(document.getElementById('grafico_cascata'), 'dark-blue');
        chart_mensal = echarts.init(document.getElementById('grafico_mensal'), 'dark-blue');
        chart_anos = echarts.init(document.getElementById('grafico_anos'), 'dark-blue');
        chart_franquia = echarts.init(document.getElementById('grafico_franquia'), 'dark-blue');
        chart_despesa = echarts.init(document.getElementById('grafico_despesa'), 'dark-blue');
    } else {
        chart_cascata = echarts.init(document.getElementById('grafico_cascata'));
        chart_mensal = echarts.init(document.getElementById('grafico_mensal'));
        chart_anos = echarts.init(document.getElementById('grafico_anos'));
        chart_franquia = echarts.init(document.getElementById('grafico_franquia'));
        chart_despesa = echarts.init(document.getElementById('grafico_despesa'));
    }

    montarGraficoCascata(chart_cascata, response, theme);

    montarGraficoMensal(chart_mensal, response, theme);

    montarGraficoFranquia(chart_franquia, response, theme);

    montarGraficoAno(chart_anos, response, theme);

    montarGraficoDespesa(chart_despesa, response, theme);

    // Redimensionar Gráficos
    // $(window).on('resize', function () {
    $(window).off('resize.chartdre').on('resize.chartdre', function () {
        chart_cascata.resize();
        chart_mensal.resize();
        chart_anos.resize();
        chart_franquia.resize();
        chart_despesa.resize();
    });
}

function gerarBadge(porcentagem, tipo = 'normal') {

    if (porcentagem !== null) {

        // const valorAbsoluto = Math.abs(porcentagem).toFixed(0) + '%';
        const valorAbsoluto = porcentagem.replace('-', '') + '%';
        let classe = 'text-success';
        let icone = 'fa-solid fa-square-arrow-up-right';

        if (tipo === 'inverso') {
            if (porcentagem > 0) {
                // Diminuiu a despesa (bom)
                classe = 'text-success';
                text = "Abaixou";
                icone = 'fa-solid fa-square-arrow-up-right fa-rotate-90'; // seta pra baixo
            } else {
                // Aumentou a despesa (ruim)
                classe = 'text-danger';
                text = "Aumentou";
                icone = 'fa-solid fa-square-arrow-up-right'; // seta pra cima
            }
        } else {
            if (porcentagem < 0) {
                // Caiu receita ou lucro (ruim)
                classe = 'text-danger';
                text = "Abaixou";
                icone = 'fa-solid fa-square-arrow-up-right fa-rotate-90'; // seta pra baixo
            } else {
                // Subiu receita ou lucro (bom)
                classe = 'text-success';
                text = "Aumentou";
                icone = 'fa-solid fa-square-arrow-up-right'; // seta pra cima
            }
        }

        return `<span class="${classe}"><i class="${icone}"></i> ${text} ${valorAbsoluto}</span>`;

    } else {

        return '';
    }
}

function getResults(data) {

    showLoadingMessage('table-demonstrativo', 'Carregando dados...');

    ['grafico_cascata', 'grafico_mensal', 'grafico_anos', 'grafico_franquia', 'grafico_despesa'].forEach(id => {
        showLoadingMessage(id, 'Carregando dados...');
    });

    $.ajax({
        type: "post",
        url: url_base + "/admin/dre/demonstrativo/visao-geral/gerar",
        data: data ?? null,
        dataType: "json",
        beforeSend: function() {
            $("#total-receitas").html('<small class="fa-fade">Carregando...</small>');
            $("#total-despesas-fixas").html('<small class="fa-fade">Carregando...</small>');
            $("#total-despesas-variaveis").html('<small class="fa-fade">Carregando...</small>');
            $("#total-resultado-liquido").html('<small class="fa-fade">Carregando...</small>');

            $("#total-receitas-porc").html("");
            $("#total-despesas-fixas-porc").html("");
            $("#total-despesas-variaveis-porc").html("");
            $("#total-resultado-liquido-porc").html("");

            $("#observacoes").html("").hide();

        },
        success: function (response) {

            $("#total-receitas").html(response.total_receitas.valor);
            $("#total-despesas-fixas").html(response.total_despesas_fixas.valor);
            $("#total-despesas-variaveis").html(response.total_despesas_variaveis.valor);
            $("#total-resultado-liquido").html(response.total_resultado.valor);

            $("#total-receitas-porc").html(gerarBadge(response.total_receitas.porcentagem, 'normal'));
            $("#total-despesas-fixas-porc").html(gerarBadge(response.total_despesas_fixas.porcentagem, 'inverso'));
            $("#total-despesas-variaveis-porc").html(gerarBadge(response.total_despesas_variaveis.porcentagem, 'inverso'));
            $("#total-resultado-liquido-porc").html(gerarBadge(response.total_resultado.porcentagem, 'normal'));

            showInfos(response.infos);

            if (response.observacao) {
                $("#observacoes").html(response.observacao).show();
            }

            $("#table-demonstrativo").html(response.tabela);

            cachedChartData = response;
            renderCharts(response);

            $("#btn-send").removeClass('disabled').prop('disabled', false).html('<i class="uil uil-message"></i> Enviar');

        },
        error: function(xhr, status, error) {
            console.error("Erro ao carregar dados da DRE:", error);
            $("#total-receitas").html('<small class="text-danger">Erro ao carregar</small>');
            $("#total-despesas-fixas").html('<small class="text-danger">Erro ao carregar</small>');
            $("#total-despesas-variaveis").html('<small class="text-danger">Erro ao carregar</small>');
            $("#total-resultado-liquido").html('<small class="text-danger">Erro ao carregar</small>');
        }
    });
}


function showInfos(infos) {
    atualizarPopover("#info-receitas", infos.receitas);
    atualizarPopover("#info-despesas-fixas", infos.despesas_fixas);
    atualizarPopover("#info-despesas-variaveis", infos.despesas_variaveis);
}

function atualizarPopover(selector, conteudo) {
    const el = document.querySelector(selector);
    if (!el) return;

    const oldInstance = bootstrap.Popover.getInstance(el);
    if (oldInstance) oldInstance.dispose();

    if (conteudo == null || conteudo === "") {
        el.style.display = "none";
        el.removeAttribute("data-bs-content");
        return;
    }

    el.style.display = "inline-block";
    el.setAttribute("data-bs-content", conteudo);

    new bootstrap.Popover(el, {
        content: conteudo,
        html: true,
        trigger: "hover focus"
    });
}


// ============================
// HELPERS GLOBAIS
// ============================

function formatShort(n) {
    const abs = Math.abs(n);
    if (abs >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.', ',') + ' Mi';
    if (abs >= 1_000) return Math.round(n / 1_000) + ' Mil';
    return Math.round(n).toString();
}

function fmtBR(n) {
    return (Number(n) || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    });
}

function calcPercent(atual, anterior) {
    if (!anterior) return null;
    return ((atual - anterior) / Math.abs(anterior)) * 100;
}

// MONTAGENS DOS GRÁFICOS
const colorBlue = '#5470c6';
const colorRed = '#ea5a5a';

// function montarGraficoCascata(chart, response, theme) {

//     const meses   = (response.grafico_lucro.meses   || []).slice();
//     const valores = (response.grafico_lucro.valores || []).slice();

//     const valoresAnterior = response.resultado_ano_anterior?.valores || [];

//     // ============================
//     // CASCATA — ANO ATUAL
//     // ============================
//     const dados = [];
//     const acumulado = [];
//     let soma = 0;

//     for (let i = 0; i < valores.length; i++) {
//         const v = Number(valores[i]) || 0;
//         const inicio = soma;
//         soma += v;
//         const fim = soma;

//         dados.push([i, inicio, fim]);
//         acumulado.push(fim);
//     }

//     // ============================
//     // ACUMULADO — ANO ANTERIOR
//     // ============================
//     const acumuladoAnterior = [];
//     let somaAnterior = 0;

//     for (let i = 0; i < valoresAnterior.length; i++) {
//         somaAnterior += Number(valoresAnterior[i]) || 0;
//         acumuladoAnterior.push(somaAnterior);
//     }

//     // ============================
//     // OPTION
//     // ============================
//     const option = {
//         backgroundColor: theme === 'dark' ? '#37404a' : '#fff',

//         grid: {
//             left: 60,
//             right: 20,
//             top: 40,
//             bottom: 40
//         },

//         tooltip: {
//             trigger: 'axis',
//             axisPointer: { type: 'shadow' },

//             formatter: (params) => {
//                 const idx = params[0].dataIndex;

//                 const mes = meses[idx];

//                 const valorMesAtual = valores[idx] || 0;
//                 const valorMesAnterior = valoresAnterior[idx] || 0;

//                 const acumAtual = acumulado[idx] || 0;
//                 const acumAnterior = acumuladoAnterior[idx] || 0;

//                 const pct = calcPercent(acumAtual, acumAnterior);
//                 const pctTexto = pct === null
//                     ? '—'
//                     : `${pct > 0 ? '+' : ''}${pct.toFixed(1).replace('.', ',')}%`;

//                 return `
//                     <b>${mes.toUpperCase()}</b>
//                     <br><br>

//                     <b>Ano Atual</b><br>
//                     Valor do mês: R$ ${fmtBR(valorMesAtual)}<br>
//                     Acumulado: R$ ${fmtBR(acumAtual)}

//                     <br><br>

//                     <b>Ano Anterior</b><br>
//                     Valor do mês: R$ ${fmtBR(valorMesAnterior)}<br>
//                     Acumulado: R$ ${fmtBR(acumAnterior)}

//                     <br><br>

//                     <b>Diferença</b><br>
//                     ${pctTexto}
//                 `;
//             }
//         },

//         xAxis: {
//             type: 'category',
//             data: meses
//         },

//         yAxis: {
//             type: 'value',
//             axisLabel: {
//                 formatter: (v) => formatShort(v)
//             }
//         },

//         series: [
//             // ============================
//             // CASCATA — ANO ATUAL
//             // ============================
//             {
//                 name: 'Resultado Líquido Ano Atual',
//                 type: 'custom',

//                 renderItem: (params, api) => {
//                     const xIndex = api.value(0);
//                     const inicio = api.value(1);
//                     const fim = api.value(2);
//                     const diff = fim - inicio;

//                     const coordX = api.coord([xIndex, 0])[0];
//                     const y0 = api.coord([0, inicio])[1];
//                     const y1 = api.coord([0, fim])[1];

//                     const barWidth = api.size([1, 0])[0] * 0.6;
//                     const yBar = Math.min(y0, y1);
//                     const height = Math.abs(y1 - y0);
//                     const textY = yBar + height / 2;

//                     return {
//                         type: 'group',
//                         children: [
//                             {
//                                 type: 'rect',
//                                 shape: {
//                                     x: coordX - barWidth / 2,
//                                     y: yBar,
//                                     width: barWidth,
//                                     height: height
//                                 },
//                                 style: {
//                                     fill: diff >= 0 ? colorBlue : colorRed
//                                 }
//                             },
//                             {
//                                 type: 'text',
//                                 style: {
//                                     x: coordX,
//                                     y: textY,
//                                     text: diff === 0 ? '' : formatShort(diff),
//                                     textAlign: 'center',
//                                     textVerticalAlign: 'middle',
//                                     fill: '#fff',
//                                     fontSize: 12,
//                                     fontWeight: 600
//                                 }
//                             }
//                         ]
//                     };
//                 },

//                 encode: {
//                     x: 0,
//                     y: [1, 2]
//                 },

//                 data: dados
//             },

//             // ============================
//             // LINHA — ACUMULADO ANO ANTERIOR
//             // ============================
//             {
//                 name: 'Acumulado Ano Anterior',
//                 type: 'line',
//                 data: acumuladoAnterior,
//                 smooth: true,
//                 symbol: 'circle',
//                 symbolSize: 8,
//                 lineStyle: {
//                     width: 3,
//                     color: '#f1c40f'
//                 },
//                 itemStyle: {
//                     color: '#f1c40f'
//                 },
//                 emphasis: {
//                     focus: 'series'
//                 }
//             }
//         ]
//     };

//     chart.setOption(option, true);
// }

// MONTAGENS DOS GRÁFICOS

function montarGraficoCascata(chart, response, theme) {

    const meses   = (response.grafico_lucro.meses   || []).slice();
    const valores = (response.grafico_lucro.valores || []).slice();

    const valoresAnterior = response.resultado_ano_anterior?.valores || [];

    // ============================
    // CASCATA — ANO ATUAL
    // ============================
    const dados = [];
    const acumulado = [];
    let soma = 0;

    for (let i = 0; i < valores.length; i++) {
        const v = Number(valores[i]) || 0;
        const inicio = soma;
        soma += v;
        const fim = soma;

        dados.push([i, inicio, fim]);
        acumulado.push(fim);
    }

    // ============================
    // ACUMULADO — ANO ANTERIOR
    // ============================
    const acumuladoAnterior = [];
    let somaAnterior = 0;

    for (let i = 0; i < valoresAnterior.length; i++) {
        somaAnterior += Number(valoresAnterior[i]) || 0;
        acumuladoAnterior.push(somaAnterior);
    }

    // ============================
    // EXTRA: BARRA DE ACUMULADO (INDEPENDENTE)
    // ============================
    const lenMeses = meses.length;      // tamanho original (sem "ACUMULADO")
    const totalAtual = soma;            // total acumulado ano atual (fim da cascata)
    const totalAnterior = somaAnterior; // total acumulado ano anterior
    meses.push('ACUMULADO');            // nova categoria no eixo X

    // ============================
    // OPTION
    // ============================
    const option = {
        backgroundColor: theme === 'dark' ? '#37404a' : '#fff',

        grid: {
            left: 60,
            right: 20,
            top: 40,
            bottom: 40
        },

        // legend: {
        //     data: ['Ano Anterior', 'Ano Atual']
        // },

        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },

            formatter: (params) => {
                const idx = params[0].dataIndex;

                // Tooltip do "ACUMULADO"
                if (idx === lenMeses) {
                    const pct = calcPercent(totalAtual, totalAnterior);
                    const pctTexto = pct === null
                        ? '—'
                        : `${pct > 0 ? '+' : ''}${pct.toFixed(1).replace('.', ',')}%`;

                    return `
                        <b>${String(meses[idx]).toUpperCase()}</b>
                        <br><br>

                        <b>Ano Atual</b><br>
                        Acumulado: R$ ${fmtBR(totalAtual)}

                        <br><br>

                        <b>Ano Anterior</b><br>
                        Acumulado: R$ ${fmtBR(totalAnterior)}

                        <br><br>

                        <b>Diferença</b><br>
                        ${pctTexto}
                    `;
                }

                // Tooltip dos meses normais
                const mes = meses[idx];

                const valorMesAtual = valores[idx] || 0;
                const valorMesAnterior = valoresAnterior[idx] || 0;

                const acumAtual = acumulado[idx] || 0;
                const acumAnterior = acumuladoAnterior[idx] || 0;

                const pct = calcPercent(acumAtual, acumAnterior);
                const pctTexto = pct === null
                    ? '—'
                    : `${pct > 0 ? '+' : ''}${pct.toFixed(1).replace('.', ',')}%`;

                return `
                    <b>${String(mes).toUpperCase()}</b>
                    <br><br>

                    <b>Ano Atual</b><br>
                    Valor do mês: R$ ${fmtBR(valorMesAtual)}<br>
                    Acumulado: R$ ${fmtBR(acumAtual)}

                    <br><br>

                    <b>Ano Anterior</b><br>
                    Valor do mês: R$ ${fmtBR(valorMesAnterior)}<br>
                    Acumulado: R$ ${fmtBR(acumAnterior)}

                    <br><br>

                    <b>Diferença</b><br>
                    ${pctTexto}
                `;
            }
        },

        xAxis: {
            type: 'category',
            data: meses
        },

        yAxis: {
            type: 'value',
            axisLabel: {
                formatter: (v) => formatShort(v)
            }
        },

        series: [
            // ============================
            // CASCATA — ANO ATUAL
            // ============================
            {
                name: 'Resultado Líquido Ano Atual',
                type: 'custom',

                renderItem: (params, api) => {
                    const xIndex = api.value(0);
                    const inicio = api.value(1);
                    const fim = api.value(2);
                    const diff = fim - inicio;

                    const coordX = api.coord([xIndex, 0])[0];
                    const y0 = api.coord([0, inicio])[1];
                    const y1 = api.coord([0, fim])[1];

                    const barWidth = api.size([1, 0])[0] * 0.6;
                    const yBar = Math.min(y0, y1);
                    const height = Math.abs(y1 - y0);
                    const textY = yBar + height / 2;

                    return {
                        type: 'group',
                        children: [
                            {
                                type: 'rect',
                                shape: {
                                    x: coordX - barWidth / 2,
                                    y: yBar,
                                    width: barWidth,
                                    height: height
                                },
                                style: {
                                    fill: diff >= 0 ? colorBlue : colorRed
                                }
                            },
                            {
                                type: 'text',
                                style: {
                                    x: coordX,
                                    y: textY,
                                    text: diff === 0 ? '' : formatShort(diff),
                                    textAlign: 'center',
                                    textVerticalAlign: 'middle',
                                    fill: '#fff',
                                    fontSize: 12,
                                    fontWeight: 600
                                }
                            }
                        ]
                    };
                },

                encode: {
                    x: 0,
                    y: [1, 2]
                },

                data: dados
            },

            // ============================
            // LINHA — ACUMULADO ANO ANTERIOR
            // ============================
            {
                name: 'Acumulado Ano Anterior',
                type: 'line',
                data: [...acumuladoAnterior, totalAnterior], // <- agora acompanha o "ACUMULADO"
                smooth: true,
                symbol: 'circle',
                symbolSize: 8,
                lineStyle: {
                    width: 3,
                    // color: '#f1c40f'
                },
                // itemStyle: {
                    // color: '#f1c40f'
                // },
                emphasis: {
                    focus: 'series'
                }
            },

            // ============================
            // BARRA — ACUMULADO (INDEPENDENTE, do zero)
            // ============================
            {
                name: 'Acumulado Ano Atual',
                type: 'bar',
                barWidth: '50%',
                data: Array(meses.length).fill(null).map((_, i) => (i === lenMeses ? totalAtual : null)),
                itemStyle: {
                    color: totalAtual >= 0 ? colorBlue : colorRed
                },
                label: {
                    show: true,
                    position: 'inside',
                    formatter: (p) => (p.value == null ? '' : formatShort(p.value)),
                    color: '#fff',
                    fontSize: 12,
                    fontWeight: 600
                },
                emphasis: {
                    focus: 'series'
                }
            }
        ]
    };

    chart.setOption(option, true);
}


function montarGraficoMensal(chart, response, theme) {

    const meses    = response.grafico_mensal?.meses || [];

    const atual = (response.grafico_mensal?.atual || []).map(v =>
        v !== null ? Math.round(v) : 0
    );

    const anterior = (response.grafico_mensal?.anterior || []).map(v =>
        v !== null ? Math.round(v) : 0
    );

    const option = {
        backgroundColor: theme === 'dark' ? '#37404a' : '#fff',

        grid: {
            left: 60,
            right: 20,
            top: 40,
            bottom: 40
        },

        legend: {
            data: ['Ano Anterior', 'Ano Atual']
        },

        xAxis: {
            type: 'category',
            data: meses
        },

        yAxis: {
            type: 'value',
            axisLabel: {
                formatter: (v) =>
                    v.toLocaleString('pt-BR', { maximumFractionDigits: 0 })
            }
        },

        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },

            formatter: (params) => {
                let html = `<b>${params[0].axisValue.toUpperCase()}</b>`;

                params.forEach(p => {
                    html += `<br><br>
                        ${p.marker}
                        <b>${p.seriesName}</b><br>
                        R$ ${Number(p.data || 0).toLocaleString('pt-BR', {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0
                        })}
                    `;
                });

                return html;
            }
        },

        series: [
            // ============================
            // ANO ATUAL — BARRA NORMAL
            // ============================
            {
                name: 'Ano Atual',
                type: 'bar',
                data: atual,
                barWidth: 30,
                // itemStyle: {
                //     color: '#ff9500'
                // }
            },

            // ============================
            // ANO ANTERIOR — INDICADOR FINO
            // ============================
            {
                name: 'Ano Anterior',
                type: 'pictorialBar',
                data: anterior,
                symbol: 'rect',
                symbolSize: [30, 4],
                symbolPosition: 'end',
                z: 10,
                // itemStyle: {
                //     color: '#213574'
                // }
            }
        ]
    };

    chart.setOption(option, true);
}

function montarGraficoFranquia(chart, response, theme) {

    // GRAFICO DE RESULTADOS POR FRANQUIA
    const franquias = response.grafico_franquia.franquias;
    const anoAtual = response.grafico_franquia.ano_atual.map(v =>
        Math.round(v)
    );

    const anoAnterior = response.grafico_franquia.ano_anterior.map(v =>
        v === 0 ? null : Math.round(v)
    );

    const option = {
        backgroundColor: theme === 'dark' ? '#37404a' : '#fff',
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            backgroundColor: theme === 'dark' ? '#37404a' : '#fff',
            borderColor: theme === 'dark' ? '#555' : '#ccc',
            textStyle: {
                color: theme === 'dark' ? '#fff' : '#000'
            },
            formatter: function (params) {
                const formatar = val => 'R$ ' + val.toLocaleString('pt-BR', { minimumFractionDigits: 0 });
                let txt = `<strong>${params[0].name}</strong><br/>`;
                params.forEach(item => {
                    if (item.data !== null) {
                        txt += `${item.marker} ${item.seriesName}: ${formatar(item.data)}<br/>`;
                    }
                });
                return txt;
            }
        },
        legend: {
            data: ['Ano Atual', 'Ano Anterior'],
            top: 'top'
        },
        grid: {
            left: '5%',
            right: '5%',
            top: 50,
            bottom: franquias.length > 15 ? 80 : 40,
            containLabel: true
        },
        xAxis: {
            type: 'category',
            data: franquias,
            axisLabel: {
                rotate: 45,
                formatter: val => val.length > 15 ? val.slice(0, 15) + '...' : val
            }
        },
        yAxis: {
            type: 'value',
            axisLabel: {
                formatter: val => val.toLocaleString('pt-BR', { minimumFractionDigits: 0 })
            }
        },
        series: [
            {
                name: 'Ano Atual',
                type: 'bar',
                data: anoAtual,
                barWidth: 30,
                // itemStyle: {
                //     color: '#ff9500'
                // }
            },
            {
                name: 'Ano Anterior',
                type: 'pictorialBar',
                data: anoAnterior,
                symbol: 'rect',
                symbolSize: [30, 4],
                symbolPosition: 'end',
                z: 10,
                // itemStyle: {
                //     color: '#213574'
                // }
            }
        ]
    };

    // Condicionalmente adiciona o scroll horizontal
    if (franquias.length > 18) {
        option.dataZoom = [
            {
                type: 'slider',
                show: true,
                start: 0,
                end: 40,
                xAxisIndex: 0,
                height: 20,
                bottom: 40
            }
        ];
    }

    // Inicializa o gráfico
    chart.setOption(option);
}

function montarGraficoAno(chart, response, theme) {

    const dados = response.grafico_ano;

    const option = {
        backgroundColor: theme === 'dark' ? '#37404a' : '#fff',

        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            backgroundColor: theme === 'dark' ? '#37404a' : '#fff',
            borderColor: theme === 'dark' ? '#555' : '#ccc',
            textStyle: {
                color: theme === 'dark' ? '#fff' : '#000'
            },
            formatter: function (params) {
                const index = params[0].dataIndex;
                const d = dados.detalhes[index];

                const formatar = v =>
                    'R$ ' + Math.round(v).toLocaleString('pt-BR');

                let txt = `<strong>Ano:</strong> ${d.ano}<br/><br/>`;
                txt += `<strong>Resultado:</strong> ${formatar(d.resultado)}<br/>`;
                txt += `<strong>Receitas:</strong> ${formatar(d.receitas)}<br/>`;
                txt += `<strong>Despesas:</strong> ${formatar(d.despesas)}<br/>`;

                if (d.crescimento_anterior !== null) {
                    const sinal = d.crescimento_anterior > 0 ? '+' : '';
                    txt += `<br/><strong>Vs. ano anterior:</strong> ${sinal}${d.crescimento_anterior}%`;
                }

                if (d.observacao) {
                    txt += `<br/><i>${d.observacao}</i>`;
                }

                return txt;
            }
        },

        xAxis: {
            type: 'value',
            axisLabel: { show: false },
            splitLine: { show: false },
            axisLine: { show: false },
            axisTick: { show: false }
        },

        yAxis: {
            type: 'category',
            data: dados.anos,
            inverse: true,
            axisTick: { show: false },
            axisLine: { show: false }
        },

        series: [
            {
                type: 'bar',
                data: dados.valores,
                barWidth: 30,
                itemStyle: {
                    color: colorBlue,
                    borderRadius: 6
                },
                label: {
                    show: true,
                    position: 'right',
                    formatter: val =>
                        Math.round(val.data / 1_000_000 * 100) / 100
                            .toString()
                            .replace('.', ',') + ' Mi',
                    color: theme === 'dark' ? '#ddd' : '#333',
                    fontWeight: 'bold'
                }
            }
        ]
    };

    chart.setOption(option);
}

function montarGraficoDespesa(chart, response, theme) {

    const fixas = Math.abs(Number(response.grafico_despesas?.fixas || 0));
    const variaveis = Math.abs(Number(response.grafico_despesas?.variaveis || 0));

    const total = fixas + variaveis;

    const formatBR = (v) =>
        v.toLocaleString('pt-BR', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });

    const option = {
        backgroundColor: theme === 'dark' ? '#37404a' : '#fff',

        tooltip: {
            trigger: 'item',
            backgroundColor: theme === 'dark' ? '#37404a' : '#fff',
            borderColor: theme === 'dark' ? '#555' : '#ccc',
            textStyle: {
                color: theme === 'dark' ? '#fff' : '#000'
            },
            formatter: (params) => {
                return `
                    <strong>${params.name}</strong><br>
                    Valor: R$ ${formatBR(params.value)}<br>
                    Percentual: ${params.percent.toFixed(1)}%
                `;
            }
        },

        legend: {
            orient: 'vertical',
            left: 'left',
            data: ['Despesas Fixas', 'Despesas Variáveis']
        },

        // color: ['#213574', '#ff9500'],

        series: [
            {
                name: 'Despesas',
                type: 'pie',
                radius: ['50%', '70%'],
                center: ['50%', '50%'],

                avoidLabelOverlap: true,

                label: {
                    show: true,
                    position: 'outside',
                    formatter: ({ percent }) => `${percent.toFixed(1)}%`,
                    fontSize: 12
                },

                labelLine: {
                    length: 12,
                    length2: 8
                },

                data: [
                    { value: fixas, name: 'Despesas Fixas' },
                    { value: variaveis, name: 'Despesas Variáveis' }
                ]
            }
        ],

        graphic: {
            type: 'text',
            left: 'center',
            top: 'center',
            style: {
                text: `R$ ${formatBR(total)}`,
                fontSize: 18,
                fontWeight: 'bold',
                fill: theme === 'dark' ? '#fff' : '#333'
            }
        }
    };

    chart.setOption(option, true);
}
