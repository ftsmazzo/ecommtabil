    let cachedChartData = null;

    $(document).on('themeChart:changed', function (e, newTheme) {
        if (cachedChartData) {
            renderCharts(cachedChartData);
        }
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

        ['grafico_liquido_desconto', 'grafico_cupons', 'grafico_tickets', 'grafico_ranking'].forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                echarts.dispose(el); // remove instância anterior
                el.innerHTML = "";   // remove mensagem de carregamento
            }
        });

        let chart_liquido_desconto, chart_cupons, chart_tickets, chart_ranking;

        if (theme == 'dark') {
            chart_liquido_desconto = echarts.init(document.getElementById('grafico_liquido_desconto'), 'dark-blue');
            chart_cupons = echarts.init(document.getElementById('grafico_cupons'), 'dark-blue');
            chart_tickets = echarts.init(document.getElementById('grafico_tickets'), 'dark-blue');
            chart_ranking = echarts.init(document.getElementById('grafico_ranking'), 'dark-blue');
        } else {
            chart_liquido_desconto = echarts.init(document.getElementById('grafico_liquido_desconto'));
            chart_cupons = echarts.init(document.getElementById('grafico_cupons'));
            chart_tickets = echarts.init(document.getElementById('grafico_tickets'));
            chart_ranking = echarts.init(document.getElementById('grafico_ranking'));
        }

        // GRAFICO DE LIQUIDO E DESCONTO
        const option_liquido_desconto = {
            backgroundColor: theme === 'dark' ? '#37404a' : '#fff',
            legend: {
                data: [
                    {
                        name: 'Valor Líquido',
                        icon: 'rect'
                    },
                    // {
                    //     name: 'Desconto',
                    //     icon: 'rect'
                    // }
                ]
            },
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'cross'
                },
                backgroundColor: theme === 'dark' ? '#37404a' : '#fff',
                borderColor: theme === 'dark' ? '#555' : '#ccc',
                textStyle: {
                    color: theme === 'dark' ? '#fff' : '#000'
                },
                formatter: function (params) {
                    let txt = `${params[0].axisValue}<br/>`;
                    params.forEach(p => {
                        txt += `${p.marker} ${p.seriesName}: R$ ${p.value.toLocaleString('pt-BR')}<br/>`;
                    });
                    return txt;
                }
            },
            xAxis: {
                type: 'category',
                data: response.grafico_liquido_desconto.meses
            },
            yAxis: [
                {
                    type: 'value',
                    name: 'Valor Líquido',
                    axisLabel: {
                        formatter: val => 'R$ ' + val.toLocaleString('pt-BR')
                    }
                },
                // {
                //     type: 'value',
                //     name: 'Desconto',
                //     axisLabel: {
                //         formatter: val => 'R$ ' + val.toLocaleString('pt-BR')
                //     }
                // }
            ],
            series: [
                {
                    name: 'Valor Líquido',
                    type: 'bar',
                    yAxisIndex: 0,
                    data: response.grafico_liquido_desconto.valor_liquido,
                    barWidth: 30,
                    // itemStyle: { color: '#213574' },
                    label: {
                        show: false,
                        // position: 'top',
                        // formatter: val => 'R$ ' + val.data.toLocaleString('pt-BR'),
                        // color: theme === 'dark' ? '#fff' : '#333'
                    }
                },
                // {
                //     name: 'Desconto',
                //     type: 'pictorialBar',
                //     yAxisIndex: 1,
                //     data: response.grafico_liquido_desconto.valor_desconto,
                //     symbol: 'rect',
                //     symbolSize: [20, 4], // largura, altura do tracinho
                //     symbolPosition: 'end',
                //     itemStyle: {
                //         color: '#ff9500'
                //     },
                //     z: 10 // garante que fique acima das barras
                // }
            ]
        };

        chart_liquido_desconto.setOption(option_liquido_desconto);

        // GRÁFIO DE QUANTIDADE DE CUPONS
        const option_cupons = {
            backgroundColor: theme === 'dark' ? '#37404a' : '#fff',
            // title: {
            //     text: 'Volume de Vendas por Cupom',
            //     left: 'center'
            // },
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'line'
                },
                backgroundColor: theme === 'dark' ? '#37404a' : '#fff',
                borderColor: theme === 'dark' ? '#555' : '#ccc',
                textStyle: {
                    color: theme === 'dark' ? '#fff' : '#000'
                },
                formatter: function (params) {
                    const p = params[0];
                    return `${p.axisValue}: ${p.value.toLocaleString('pt-BR')} cupons`;
                }
            },
            xAxis: {
                type: 'category',
                data: response.grafico_cupons.meses
            },
            yAxis: {
                type: 'value',
                name: 'Cupons',
                axisLabel: {
                    formatter: val => val.toLocaleString('pt-BR')
                }
            },
            series: [
                {
                    name: 'Cupons',
                    type: 'line',
                    data: response.grafico_cupons.valores,
                    symbol: 'none',
                    smooth: true,
                    lineStyle: {
                        width: 3,
                        // color: '#213574'
                    },
                    areaStyle: {
                        opacity: 0.1,
                        // color: '#213574'
                    }
                }
            ]
        };

        chart_cupons.setOption(option_cupons);


        // GRÁFICO DE TICKET MÉDIO
        const option_tickets = {
            backgroundColor: theme === 'dark' ? '#37404a' : '#fff',
            tooltip: {
                trigger: 'axis',
                axisPointer: {
                    type: 'line'
                },
                backgroundColor: theme === 'dark' ? '#37404a' : '#fff',
                borderColor: theme === 'dark' ? '#555' : '#ccc',
                textStyle: {
                    color: theme === 'dark' ? '#fff' : '#000'
                },
                formatter: function (params) {
                    const p = params[0];
                    return `${p.axisValue}: R$ ${p.value.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })}`;
                }
            },
            xAxis: {
                type: 'category',
                data: response.grafico_ticket_medio.meses
            },
            yAxis: {
                type: 'value',
                name: 'Ticket Médio',
                axisLabel: {
                    formatter: val => 'R$ ' + val.toLocaleString('pt-BR', {
                        minimumFractionDigits: 0
                    })
                }
            },
            series: [
                {
                    name: 'Ticket Médio',
                    type: 'line',
                    data: response.grafico_ticket_medio.valores,
                    symbol: 'none',
                    smooth: true,
                    lineStyle: {
                        width: 3,
                        // color: '#213574'
                    },
                    areaStyle: {
                        opacity: 0.1,
                        // color: '#213574'
                    }
                }
            ]
        };

        chart_tickets.setOption(option_tickets);


        // GRÁFICO DE RANKING DE FRANQUIAS
        const option_ranking = {
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
                    const item = params[0];
                    return `${item.name}: R$ ${item.value.toLocaleString('pt-BR', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    })}`;
                }
            },
            grid: {
                left: '5%',
                right: '10%',
                top: 20,
                bottom: 20,
                containLabel: true
            },
            xAxis: {
                type: 'value',
                axisLabel: {
                    formatter: val => 'R$ ' + val.toLocaleString('pt-BR', {
                        minimumFractionDigits: 0
                    })
                }
            },
            yAxis: {
                type: 'category',
                data: response.grafico_ranking.franquias,
                inverse: true,
                axisLabel: {
                    formatter: val => val.length > 25 ? val.slice(0, 25) + '...' : val
                }
            },
            series: [
                {
                    type: 'bar',
                    data: response.grafico_ranking.valores,
                    barWidth: 20,
                    itemStyle: {
                        // color: '#213574'
                    },
                    label: {
                        show: true,
                        position: 'right',
                        formatter: val => val.data.toFixed(2).replace('.', ','),
                        color: theme === 'dark' ? '#ccc' : '#333',
                        fontWeight: 'bold',
                        fontSize: 12
                    }
                }
            ]
        };

        // Adiciona scroll vertical se houver muitas franquias
        if (response.grafico_ranking.franquias.length > 15) {
            option_ranking.dataZoom = [
                {
                    type: 'slider',
                    show: true,
                    yAxisIndex: 0, // ← como o eixo das franquias é Y
                    start: 0,
                    end: 50, // mostra aproximadamente metade por vez
                    right: 10,
                    width: 10,
                    handleSize: 15
                },
                {
                    type: 'inside', // permite rolar com scroll do mouse
                    yAxisIndex: 0
                }
            ];
        }

        chart_ranking.setOption(option_ranking);

        $(window).on('resize', function () {
            chart_liquido_desconto.resize();
            chart_cupons.resize();
            chart_tickets.resize();
            chart_ranking.resize();
        });
    }

    function getResults(data) {

        ['grafico_liquido_desconto', 'grafico_cupons', 'grafico_tickets', 'grafico_ranking'].forEach(id => {
            showLoadingMessage(id, 'Carregando dados...');
        });

        $.ajax({
            type: "post",
            url: url_base + "/admin/vendas/relatorio/visao-geral/gerar",
            data: data ?? null,
            dataType: "json",
            beforeSend: function() {

            },
            success: function (response) {

                cachedChartData = response;
                renderCharts(response);
                $("#btn-send").removeClass('disabled').prop('disabled', false).html('<i class="uil uil-message"></i> Enviar');
            },
            error: function(xhr, status, error) {
                console.error("Erro ao carregar dados da DRE:", error);
            }
        });
    }

    getResults();

    $("#form-relatorio-resutados").submit(function(e) {
        e.preventDefault();
        $("#btn-send").addClass('disabled').prop('disabled', true).html('<i class="fa-solid fa-arrows-rotate fa-spin"></i> Carregando...');
        getResults($(this).serialize());
    });

    function gerarBadge(porcentagem, tipo = 'normal') {

        const valorAbsoluto = Math.abs(porcentagem).toFixed(0) + '%';
        let classe = 'text-success';
        let icone = 'fa-solid fa-square-arrow-up-right';

        if (tipo === 'inverso') {
            if (porcentagem > 0) {
                // Diminuiu a despesa (bom)
                classe = 'text-success';
                icone = 'fa-solid fa-square-arrow-up-right fa-rotate-90'; // seta pra baixo
            } else {
                // Aumentou a despesa (ruim)
                classe = 'text-danger';
                icone = 'fa-solid fa-square-arrow-up-right'; // seta pra cima
            }
        } else {
            if (porcentagem < 0) {
                // Caiu receita ou lucro (ruim)
                classe = 'text-danger';
                icone = 'fa-solid fa-square-arrow-up-right fa-rotate-90'; // seta pra baixo
            } else {
                // Subiu receita ou lucro (bom)
                classe = 'text-success';
                icone = 'fa-solid fa-square-arrow-up-right'; // seta pra cima
            }
        }

        return `<span class="${classe}"><i class="${icone}"></i> ${valorAbsoluto}</span>`;
    }