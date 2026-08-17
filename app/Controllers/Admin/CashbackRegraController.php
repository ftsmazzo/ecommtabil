<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\Redirect;
use App\Core\Request;
use App\Models\CashbackRegra;

class CashbackRegraController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title" => "Regras de Cashback",
            "active_menu" => "cashback-regras",
            "page" => [
                "title" => "Regras de Cashback",
                "desc" => "Cadastre e gerencie as regras de cashback dos cartões",
            ],
        ]);
    }

    public function index(): void
    {
        $this->authorize("cashback_regra_gerenciar");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cartões" => ["url" => $this->router->route("admin.cartao.index"), "current" => false],
                "Regras de Cashback" => ["url" => false, "current" => true],
            ],
        ]);

        $dados = CashbackRegra::orderBy("descricao")->get();

        foreach ($dados as $regra) {
            $regra->valor_print = $this->valorLabel((string) ($regra->tipo ?? ""), (float) ($regra->valor ?? 0));
            $regra->minimo_print = (isset($regra->valor_minimo_recarga) && $regra->valor_minimo_recarga !== null && $regra->valor_minimo_recarga !== "")
                ? "R$ " . number_format((float) $regra->valor_minimo_recarga, 2, ",", ".")
                : '<i class="text-black-50">Não definido</i>';
            $regra->periodo_print = $this->periodoLabel($regra->data_inicio ?? null, $regra->data_fim ?? null);
            $regra->ativo_print = (int) ($regra->ativo ?? 0) === 1
                ? '<span class="badge filled-outlined bg-success">Sim</span>'
                : '<span class="badge filled-outlined bg-secondary">Não</span>';
        }

        $permissao = [
            "cartao" => $this->auth->allow("cartao_gerenciar"),
            "gerenciar" => $this->auth->allow("cashback_regra_gerenciar"),
            "inserir" => $this->auth->allow("cashback_regra_inserir"),
            "editar" => $this->auth->allow("cashback_regra_editar"),
            "excluir" => $this->auth->allow("cashback_regra_excluir"),
        ];

        echo $this->view->render("admin/cartao/cashback-regra/index", [
            "dados" => $dados,
            "permissao" => $permissao,
        ]);
    }

    public function new(): void
    {
        $this->authorize("cashback_regra_inserir");

        echo $this->view->render("admin/cartao/cashback-regra/form", [
            "csrf" => $this->csrf->generate(),
            "regra" => false,
            "url_action" => $this->router->route("admin.cashback.regra.insert"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("cashback_regra_inserir");

        $payload = $this->validatedPayload($request);
        $payload["created_by"] = $this->user->uid;

        CashbackRegra::create($payload);

        $this->message->success("Regra de cashback cadastrada com sucesso");
        $this->router->redirect("admin.cashback.regra.index");
    }

    public function edit(Request $request): void
    {
        $this->authorize("cashback_regra_editar");

        $data = new Data($request->all());
        $regra = CashbackRegra::find($data->id) ?: CashbackRegra::findByMd5($data->id);

        if (!$regra) {
            $this->message->warning("Regra de cashback não encontrada");
            $this->router->redirect("admin.cashback.regra.index");
            return;
        }

        echo $this->view->render("admin/cartao/cashback-regra/form", [
            "csrf" => $this->csrf->generate(),
            "regra" => $regra,
            "url_action" => $this->router->route("admin.cashback.regra.update"),
        ]);
    }

    public function update(Request $request): void
    {
        $this->authorize("cashback_regra_editar");

        $data = new Data($request->all());
        $regra = CashbackRegra::find($data->id) ?: CashbackRegra::findByMd5($data->id);

        if (!$regra) {
            $this->message->warning("Regra de cashback não encontrada");
            Redirect::referer();
            return;
        }

        $payload = $this->validatedPayload($request);
        $payload["updated_by"] = $this->user->uid;

        CashbackRegra::updateBy($regra->id, $payload);

        $this->message->success("Regra de cashback atualizada com sucesso");
        $this->router->redirect("admin.cashback.regra.index");
    }

    public function delete(Request $request): void
    {
        $this->authorize("cashback_regra_excluir");

        $data = new Data($request->all());
        $regra = CashbackRegra::find($data->id) ?: CashbackRegra::findByMd5($data->id);

        if (!$regra) {
            $this->message->warning("Regra de cashback não encontrada");
            Redirect::referer();
            return;
        }

        CashbackRegra::updateBy($regra->id, [
            "trash" => 1,
            "deleted_by" => $this->user->uid,
            "deleted_at" => date("Y-m-d H:i:s"),
        ]);

        $this->message->success("Regra de cashback removida com sucesso");
        Redirect::referer();
    }

    private function validatedPayload(Request $request): array
    {
        $data = new Data($request->all());

        $descricao = trim((string) ($data->descricao ?? ""));
        $tipo = strtoupper(trim((string) ($data->tipo ?? "PERCENTUAL")));
        $valor = (float) str_replace(",", ".", str_replace(".", "", (string) ($data->valor ?? "0")));
        $valorMinimo = trim((string) ($data->valor_minimo_recarga ?? ""));

        if ($descricao === "") {
            $this->message->warning("Informe a descrição da regra.");
            Redirect::referer();
            exit;
        }

        if (!in_array($tipo, ["PERCENTUAL", "FIXO"], true)) {
            $this->message->warning("Informe um tipo de cashback válido.");
            Redirect::referer();
            exit;
        }

        if ($valor <= 0) {
            $this->message->warning("Informe um valor de cashback maior que zero.");
            Redirect::referer();
            exit;
        }

        return [
            "descricao" => $descricao,
            "tipo" => $tipo,
            "valor" => $valor,
            "valor_minimo_recarga" => $valorMinimo !== "" ? (float) str_replace(",", ".", str_replace(".", "", $valorMinimo)) : null,
            "data_inicio" => $this->parseDatetimeLocal($data->data_inicio),
            "data_fim" => $this->parseDatetimeLocal($data->data_fim),
            "ativo" => $data->contain("ativo") ? (int) $data->ativo : 0,
            "observacoes" => trim((string) ($data->observacoes ?? "")),
            "trash" => 0,
        ];
    }

    private function parseDatetimeLocal(mixed $value): ?string
    {
        $str = trim((string) ($value ?? ""));
        if ($str === "") {
            return null;
        }
        $ts = strtotime(str_replace("T", " ", $str));
        return $ts !== false ? date("Y-m-d H:i:s", $ts) : null;
    }

    private function valorLabel(string $tipo, float $valor): string
    {
        if ($tipo === "FIXO") {
            return "R$ " . number_format($valor, 2, ",", ".");
        }

        if (fmod($valor, 1.0) === 0.0) {
            return number_format($valor, 0, ",", ".") . "%";
        }

        return number_format($valor, 2, ",", ".") . "%";
    }

    private function periodoLabel(?string $inicio, ?string $fim): string
    {
        if (empty($inicio) && empty($fim)) {
            return '<i class="text-black-50">Não definido</i>';
        }

        $inicioPrint = !empty($inicio) ? datetimebr((string) $inicio) : "Sem início";
        $fimPrint = !empty($fim) ? datetimebr((string) $fim) : "Sem fim";

        return $inicioPrint . " até " . $fimPrint;
    }
}
