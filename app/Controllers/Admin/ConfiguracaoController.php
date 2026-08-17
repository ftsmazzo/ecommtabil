<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\DB;
use App\Core\Request;
use App\Models\Igpm;
use App\Models\Ipca;
use App\Models\ModeloDemonstrativo;
use App\Models\PlanilhaModeloColuna;
use App\Models\Selic;
use App\Models\TipoDemonstrativo;
use App\Services\IgpmService;
use App\Services\IpcaService;
use App\Services\SelicService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ConfiguracaoController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title"       => "Configurações",
            "active_menu" => "configuracoes",
        ]);
    }

    private function resolverTipo(Data|Request $input): string
    {
        $tipo = $input instanceof Data ? ($input->tipo ?? "") : $input->input("tipo", "");
        return TipoDemonstrativo::existeSigla($tipo)
            ? $tipo
            : TipoDemonstrativo::padrao()?->sigla;
    }

    private function modeloDoTipo(string $tipo): object
    {
        $modelo = ModeloDemonstrativo::padraoPorTipo($tipo);
        if (!$modelo) {
            $this->message->warning("Nenhum modelo de demonstrativo encontrado para o tipo " . strtoupper($tipo));
            $this->router->redirect("admin.configuracao.planilha");
        }
        return $modelo;
    }

    public function planilha(Request $request): void
    {
        $this->authorize("config_planilha_gerenciar");

        $tipo  = $this->resolverTipo($request);
        $modelo = $this->modeloDoTipo($tipo);

        $this->view->addData([
            "breadcrumb" => [
                "Configurações"   => ["url" => false, "current" => false],
                "Planilha Modelo" => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "Planilha Modelo — " . strtoupper($tipo),
                "desc"  => "Configure os cabeçalhos da planilha de importação para " . strtoupper($tipo),
            ],
        ]);

        $colunas = PlanilhaModeloColuna::porModelo((int) $modelo->id);

        echo $this->view->render("admin/configuracao/planilha", [
            "colunas" => $colunas,
            "tipo"    => $tipo,
            "tipos"   => TipoDemonstrativo::options(),
            "modelo"  => $modelo,
            "csrf"    => $this->csrf->generate(),
        ]);
    }

    public function planilhaSave(Request $request): void
    {
        $this->authorize("config_planilha_gerenciar");

        $data   = new Data($request->all());
        $tipo   = $this->resolverTipo($data);
        $modelo = $this->modeloDoTipo($tipo);

        // Remove as colunas atuais deste modelo
        DB::table("planilha_modelo_coluna")
            ->where("id_modelo_demonstrativo", "=", (int) $modelo->id)
            ->update(["trash" => 1]);

        $descricoes  = (array) ($data->descricao ?? []);
        $helpers     = (array) ($data->helper ?? []);
        $campos_dre  = (array) ($data->campo_dre ?? []);

        $letras = range("A", "Z");

        foreach ($descricoes as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === "") continue;

            PlanilhaModeloColuna::create([
                "id_modelo_demonstrativo" => (int) $modelo->id,
                "ordem"                   => (int) $i,
                "coluna"                  => $letras[$i] ?? ("Col" . ($i + 1)),
                "descricao"               => $desc,
                "helper"                  => trim((string) ($helpers[$i] ?? "")) ?: null,
                "campo_dre"               => trim((string) ($campos_dre[$i] ?? "")) ?: null,
                "trash"                   => 0,
            ]);
        }

        $this->message->success("Planilha modelo salva com sucesso");
        $this->router->redirect("admin.configuracao.planilha", ["tipo" => $tipo]);
    }

    public function planilhaDownload(Request $request): void
    {
        $this->authorize("config_planilha_gerenciar");

        $tipo   = $this->resolverTipo($request);
        $modelo = $this->modeloDoTipo($tipo);

        $colunas = PlanilhaModeloColuna::porModelo((int) $modelo->id);

        if (empty($colunas)) {
            $this->message->warning("Nenhuma coluna configurada para " . strtoupper($tipo));
            $this->router->redirect("admin.configuracao.planilha", ["tipo" => $tipo]);
            return;
        }

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Importação");

        $headerStyle = [
            "font"      => ["bold" => true, "color" => ["argb" => "FFFFFFFF"]],
            "fill"      => ["fillType" => Fill::FILL_SOLID, "startColor" => ["argb" => "FF1E3A5F"]],
            "alignment" => ["horizontal" => Alignment::HORIZONTAL_CENTER, "vertical" => Alignment::VERTICAL_CENTER],
        ];

        foreach ($colunas as $idx => $col) {
            $cellCol = $col->coluna;
            $cell    = $cellCol . "1";

            $sheet->setCellValue($cell, $col->descricao);
            $sheet->getStyle($cell)->applyFromArray($headerStyle);
            $sheet->getColumnDimension($cellCol)->setWidth(22);

            if ($col->helper) {
                $sheet->getComment($cell)->getText()->createTextRun($col->helper);
            }
        }

        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->freezePane("A2");

        $writer   = new Xlsx($spreadsheet);
        $filename = "planilha_modelo_{$tipo}_" . date("Ymd") . ".xlsx";

        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header("Cache-Control: max-age=0");

        $writer->save("php://output");
        exit;
    }

    public function ipca(): void
    {
        $this->authorize("configuracao_ipca");

        $this->view->addData([
            "breadcrumb" => [
                "Configurações" => ["url" => false, "current" => false],
                "IPCA"          => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "IPCA",
                "desc"  => "Sincronize o IPCA acumulado no ano, usado como comparativo em relatórios",
            ],
        ]);

        echo $this->view->render("admin/configuracao/ipca", [
            "registros" => Ipca::listaOrdenada(),
            "csrf"      => $this->csrf->generate(),
        ]);
    }

    public function ipcaSincronizar(Request $request): void
    {
        $this->authorize("configuracao_ipca");

        try {
            $qtd = (new IpcaService())->sincronizar();
            $this->message->success("IPCA sincronizado com sucesso! {$qtd} meses atualizados.");
            $this->log->app("update", ["user" => $this->user->uid, "tabela" => "ipca_mensal"]);
        } catch (\Throwable $e) {
            $this->message->warning("Falha ao sincronizar com a API do IBGE: {$e->getMessage()}");
        }

        $this->router->redirect("admin.configuracao.ipca");
    }

    public function selic(): void
    {
        $this->authorize("configuracao_selic");

        $this->view->addData([
            "breadcrumb" => [
                "Configurações" => ["url" => false, "current" => false],
                "SELIC"         => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "SELIC",
                "desc"  => "Sincronize a SELIC acumulada no ano, usada como comparativo em relatórios",
            ],
        ]);

        echo $this->view->render("admin/configuracao/selic", [
            "registros" => Selic::listaOrdenada(),
            "csrf"      => $this->csrf->generate(),
        ]);
    }

    public function selicSincronizar(Request $request): void
    {
        $this->authorize("configuracao_selic");

        try {
            $qtd = (new SelicService())->sincronizar();
            $this->message->success("SELIC sincronizada com sucesso! {$qtd} meses atualizados.");
            $this->log->app("update", ["user" => $this->user->uid, "tabela" => "selic_mensal"]);
        } catch (\Throwable $e) {
            $this->message->warning("Falha ao sincronizar com a API do Banco Central: {$e->getMessage()}");
        }

        $this->router->redirect("admin.configuracao.selic");
    }

    public function igpm(): void
    {
        $this->authorize("configuracao_igpm");

        $this->view->addData([
            "breadcrumb" => [
                "Configurações" => ["url" => false, "current" => false],
                "IGP-M"         => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => "IGP-M",
                "desc"  => "Sincronize o IGP-M acumulado no ano, usado como comparativo em relatórios",
            ],
        ]);

        echo $this->view->render("admin/configuracao/igpm", [
            "registros" => Igpm::listaOrdenada(),
            "csrf"      => $this->csrf->generate(),
        ]);
    }

    public function igpmSincronizar(Request $request): void
    {
        $this->authorize("configuracao_igpm");

        try {
            $qtd = (new IgpmService())->sincronizar();
            $this->message->success("IGP-M sincronizado com sucesso! {$qtd} meses atualizados.");
            $this->log->app("update", ["user" => $this->user->uid, "tabela" => "igpm_mensal"]);
        } catch (\Throwable $e) {
            $this->message->warning("Falha ao sincronizar com a API do Banco Central: {$e->getMessage()}");
        }

        $this->router->redirect("admin.configuracao.igpm");
    }
}
