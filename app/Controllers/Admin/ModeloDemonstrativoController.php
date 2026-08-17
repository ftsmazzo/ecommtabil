<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\DB;
use App\Core\Redirect;
use App\Core\Request;
use App\Models\ModeloDemonstrativo;
use App\Models\ModeloDemonstrativoNo;
use App\Models\DreConta;
use App\Models\TipoDemonstrativo;
use App\Services\Dre\ModeloDemonstrativoService;
use InvalidArgumentException;

class ModeloDemonstrativoController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title"       => "Modelo de Demonstrativo",
            "active_menu" => "configuracoes-dre-estrutura",
            "page"        => [
                "title" => "Modelo de Demonstrativo",
                "desc"  => "Organização das contas do plano de contas no demonstrativo de resultado",
            ],
        ]);
    }

    private function resolverTipo(Data $data): string
    {
        return TipoDemonstrativo::existeSigla($data->tipo ?? "")
            ? $data->tipo
            : TipoDemonstrativo::padrao()?->sigla;
    }

    public function index(Request $request): void
    {
        $this->authorize("modelo_demonstrativo_gerenciar");

        $data = new Data($request->all());
        $tipo = $this->resolverTipo($data);

        $this->view->addData([
            "breadcrumb" => [
                "Configurações"           => ["url" => false, "current" => false],
                "Modelo de Demonstrativo" => ["url" => false, "current" => true],
            ],
        ]);

        echo $this->view->render("admin/modelo_demonstrativo/index", [
            "modelos"   => ModeloDemonstrativo::listarPorTipo($tipo),
            "tipo"      => $tipo,
            "tipos"     => TipoDemonstrativo::options(),
            "permissao" => [
                "inserir" => $this->auth->allow("modelo_demonstrativo_inserir"),
                "editar"  => $this->auth->allow("modelo_demonstrativo_editar"),
                "excluir" => $this->auth->allow("modelo_demonstrativo_excluir"),
            ],
            "csrf" => $this->csrf->generate(),
        ]);
    }

    public function novo(Request $request): void
    {
        $this->authorize("modelo_demonstrativo_inserir");

        $data = new Data($request->all());
        $tipo = $this->resolverTipo($data);

        $idsComModelo = array_filter(array_map(
            fn ($m) => $m->id_empresa,
            ModeloDemonstrativo::listarPorTipo($tipo)
        ));

        $empresas = DB::table("empresa", "e")
            ->where("e.trash", "=", 0)
            ->orderBy("e.razao")
            ->get();

        echo $this->view->render("admin/modelo_demonstrativo/_form_nova", [
            "csrf"          => $this->csrf->generate(),
            "tipo"          => $tipo,
            "empresas"      => $empresas,
            "idsComModelo"  => $idsComModelo,
            "modelos"       => ModeloDemonstrativo::listarPorTipo($tipo),
            "url_action"    => $this->router->route("admin.modelo.demonstrativo.insert"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("modelo_demonstrativo_inserir");

        $data = new Data($request->all());
        $tipo = $this->resolverTipo($data);

        if (!$data->has("nome")) {
            $this->message->warning("Informe o nome do modelo");
            Redirect::referer();
            return;
        }

        $idEmpresa = (int) ($data->id_empresa ?? 0) ?: null;

        if ($idEmpresa && ModeloDemonstrativo::porEmpresaETipo($idEmpresa, $tipo)) {
            $this->message->warning("Esta empresa já possui um modelo para este tipo de demonstrativo");
            Redirect::referer();
            return;
        }

        $novo = ModeloDemonstrativo::create([
            "id_empresa"         => $idEmpresa,
            "tipo_demonstrativo" => $tipo,
            "nome"               => trim((string) $data->nome),
            "is_padrao"          => 0,
            "trash"              => 0,
            "created_by"         => $this->user->uid,
        ]);

        $idClonarDe = (int) ($data->clonar_de_id_configuracao ?? 0) ?: null;
        if ($idClonarDe) {
            $origem = ModeloDemonstrativo::find($idClonarDe);
            if ($origem && $origem->tipo_demonstrativo === $tipo) {
                ModeloDemonstrativoService::clonarArvore($idClonarDe, $novo->id, $this->user->uid);
            }
        }

        $this->message->success("Modelo criado com sucesso");
        $this->router->redirect("admin.modelo.demonstrativo.editar", ["id" => $novo->id]);
    }

    public function editar(Request $request): void
    {
        $this->authorize("modelo_demonstrativo_editar");

        $data   = new Data($request->all());
        $modelo = ModeloDemonstrativo::find($data->id);

        if (!$modelo) {
            $this->message->warning("Modelo não encontrado");
            $this->router->redirect("admin.modelo.demonstrativo.index");
            return;
        }

        $arvore = ModeloDemonstrativoNo::arvorePorConfiguracao((int) $modelo->id);
        $contasDisponiveis = DreConta::todas($modelo->tipo_demonstrativo);

        $this->view->addData([
            "breadcrumb" => [
                "Configurações"           => ["url" => false, "current" => false],
                "Modelo de Demonstrativo" => ["url" => $this->router->route("admin.modelo.demonstrativo.index"), "current" => false],
                $modelo->nome             => ["url" => false, "current" => true],
            ],
        ]);

        echo $this->view->render("admin/modelo_demonstrativo/editar", [
            "modelo"            => $modelo,
            "arvore"            => $arvore,
            "contasDisponiveis" => $contasDisponiveis,
            "csrf"              => $this->csrf->generate(),
            "url_action"        => $this->router->route("admin.modelo.demonstrativo.salvar"),
        ]);
    }

    public function salvar(Request $request): void
    {
        $this->authorize("modelo_demonstrativo_editar");

        $data   = new Data($request->all());
        $modelo = ModeloDemonstrativo::find($data->id);

        if (!$modelo) {
            $this->message->warning("Modelo não encontrado");
            Redirect::referer();
            return;
        }

        $arvoreJson = $request->input("arvore_json", "[]");
        $arvore     = json_decode((string) $arvoreJson, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($arvore)) {
            $this->message->warning("Estrutura inválida — não foi possível salvar");
            Redirect::referer();
            return;
        }

        $idsContasValidas = array_map(
            fn ($c) => (int) $c->id,
            DreConta::todas($modelo->tipo_demonstrativo)
        );

        try {
            $arvoreValidada = ModeloDemonstrativoService::validarArvore($arvore, $idsContasValidas);
            ModeloDemonstrativoService::substituirArvore((int) $modelo->id, $arvoreValidada, $this->user->uid);
        } catch (InvalidArgumentException $e) {
            $this->message->warning($e->getMessage());
            Redirect::referer();
            return;
        }

        $this->message->success("Modelo salvo com sucesso");
        $this->router->redirect("admin.modelo.demonstrativo.editar", ["id" => $modelo->id]);
    }

    public function delete(Request $request): void
    {
        $this->authorize("modelo_demonstrativo_excluir");

        $data   = new Data($request->all());
        $modelo = ModeloDemonstrativo::find($data->id);

        if (!$modelo) {
            $this->message->warning("Modelo não encontrado");
            Redirect::referer();
            return;
        }

        if ((int) $modelo->is_padrao === 1) {
            $this->message->warning("O modelo padrão não pode ser excluído");
            Redirect::referer();
            return;
        }

        ModeloDemonstrativo::updateBy($modelo->id, [
            "trash"      => 1,
            "updated_by" => $this->user->uid,
        ]);

        $this->message->success("Modelo removido com sucesso");
        Redirect::referer();
    }
}
