<?php

namespace App\Services\Menu;

class ClienteMenuDefinition extends AbstractMenuDefinition
{
    public function items(): array
    {
        return [
            $this->link('painel', 'Painel', 'cliente.home', [
                'icon' => 'uil-dashboard',
            ]),
            $this->drop('dre', 'DRE', [
                $this->link('dre-demonstrativos-geral', 'Visão Geral', 'cliente.dre.demonstrativo.geral', [
                    'permission' => 'dre_geral',
                ]),
                $this->link('dre-relatorio', 'Detalhamento', 'cliente.dre.relatorio', [
                    'permission' => 'dre_detalhamento',
                ]),
            ], [
                'icon' => 'uil-chart-line',
            ]),
            $this->drop('vendas', 'Vendas', [
                $this->link('venda-relatorio-geral', 'Visão Geral', 'cliente.venda.relatorio.geral', [
                    'permission' => 'venda_geral',
                ]),
                $this->link('venda-relatorio-faturamento', 'Análise de Faturamento', 'cliente.venda.relatorio.faturamento', [
                    'permission' => 'venda_analise_faturamento',
                ]),
                $this->link('venda-relatorio-escala', 'Análise de Escala', 'cliente.venda.relatorio.escala', [
                    'permission' => 'venda_analise_escala',
                ]),
            ], [
                'icon' => 'uil-bill',
            ]),
        ];
    }
}
