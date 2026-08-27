<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\Request;
use App\Models\CaixaMovimento;
use App\Models\CaixaSessao;
use App\Models\Empresa;
use App\Models\Projeto;
use App\Services\Caixa\CaixaSessaoService;

class ProjetoCaixaController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title"       => "Projetos",
            "active_menu" => "projetos",
        ]);
    }

    public function index(Request $request): void
    {
        $this->authorize("projeto_gerenciar");

        $projeto = $this->carregarProjeto($request);
        if (!$projeto) {
            return;
        }

        $sessoes = CaixaSessao::ativasPorProjeto((int) $projeto->id);
        $sessaoId = (int) (($request->all()["sessao"] ?? 0));
        $sessao = null;
        $movimentos = [];
        $resumo = null;

        if ($sessaoId > 0) {
            $sessao = CaixaSessao::findAtiva($sessaoId, (int) $projeto->id);
        }
        if (!$sessao && $sessoes !== []) {
            $sessao = $sessoes[0];
        }
        if ($sessao) {
            $filtro = trim((string) (($request->all()["status"] ?? "")));
            $movimentos = CaixaMovimento::porSessao((int) $sessao->id, $filtro !== "" ? $filtro : null);
            $resumo = CaixaMovimento::resumoPorSessao((int) $sessao->id);
        }

        $label = trim((string) ($projeto->nome ?? "")) ?: $projeto->empresa_print;

        $this->view->addData([
            "breadcrumb" => [
                "Projetos"    => ["url" => $this->router->route("admin.projeto.index"), "current" => false],
                $label        => ["url" => $this->router->route("admin.projeto.abrir", ["id" => $projeto->id]), "current" => false],
                "Montar DFC"  => ["url" => false, "current" => true],
            ],
            "page" => [
                "title" => $label,
                "desc"  => $projeto->empresa_print,
            ],
            "title" => $label,
        ]);

        echo $this->view->render("admin/projeto/caixa", [
            "projeto"    => $projeto,
            "aba"        => "montar-dfc",
            "sessoes"    => $sessoes,
            "sessao"     => $sessao,
            "movimentos" => $movimentos,
            "resumo"     => $resumo,
            "filtro"     => trim((string) (($request->all()["status"] ?? ""))),
            "csrf"       => $this->csrf->generate(),
            "empresas"   => Empresa::orderBy("razao")->get(),
            "permissao"  => [
                "editar"  => $this->auth->allow("projeto_editar"),
                "excluir" => $this->auth->allow("projeto_excluir"),
            ],
        ]);
    }

    public function uploadExtrato(Request $request): void
    {
        $this->authorize("projeto_gerenciar");

        $projeto = $this->carregarProjeto($request, true);
        if (!$projeto) {
            return;
        }

        $file = $_FILES["arquivo"] ?? null;
        if (!$file || ($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->message->warning("Envie um arquivo OFX do extrato bancário.");
            $this->router->redirect("admin.projeto.caixa", ["id" => $projeto->id]);
            return;
        }

        $ext = strtolower(pathinfo((string) $file["name"], PATHINFO_EXTENSION));
        if ($ext !== "ofx") {
            $this->message->warning("Por enquanto só aceitamos extrato em OFX.");
            $this->router->redirect("admin.projeto.caixa", ["id" => $projeto->id]);
            return;
        }

        $dir = PATH_ROOT . "/storage/tmp/caixa/";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $nomeSalvo = "proj_{$projeto->id}_" . time() . "_" . uniqid() . ".ofx";
        $destino   = $dir . $nomeSalvo;

        if (!move_uploaded_file((string) $file["tmp_name"], $destino)) {
            $this->message->error("Falha ao salvar o extrato. Tente novamente.");
            $this->router->redirect("admin.projeto.caixa", ["id" => $projeto->id]);
            return;
        }

        try {
            $svc = new CaixaSessaoService();
            $out = $svc->criarDeOfx(
                (int) $projeto->id,
                $destino,
                (string) $file["name"],
                $nomeSalvo
            );
            $this->message->success(
                "Extrato lido: {$out["total"]} movimentos. Próximo passo: conferir e classificar para montar o DFC."
            );
            $this->router->redirect("admin.projeto.caixa", [
                "id"     => $projeto->id,
                "sessao" => $out["sessao"]->id,
            ]);
        } catch (\Throwable $e) {
            @unlink($destino);
            $this->message->error("Não foi possível ler o OFX: " . $e->getMessage());
            $this->router->redirect("admin.projeto.caixa", ["id" => $projeto->id]);
        }
    }

    public function arquivarSessao(Request $request): void
    {
        $this->authorize("projeto_gerenciar");

        $projeto = $this->carregarProjeto($request, true);
        if (!$projeto) {
            return;
        }

        $data = new Data($request->all());
        $idSessao = (int) ($data->sessao_id ?? 0);
        if ($idSessao < 1) {
            $this->message->warning("Sessão inválida.");
            $this->router->redirect("admin.projeto.caixa", ["id" => $projeto->id]);
            return;
        }

        (new CaixaSessaoService())->arquivar($idSessao, (int) $projeto->id);
        $this->message->success("Sessão arquivada.");
        $this->router->redirect("admin.projeto.caixa", ["id" => $projeto->id]);
    }

    private function carregarProjeto(Request $request, bool $redirectOnFail = true): ?Projeto
    {
        $data = new Data($request->all());
        $projeto = Projeto::leftJoin("empresa as e", "p.id_empresa", "=", "e.id")
            ->select("p.*", "e.razao as empresa_razao", "e.nome as empresa_nome")
            ->where("p.id", "=", (int) ($data->id ?? 0))
            ->first();

        if (!$projeto) {
            if ($redirectOnFail) {
                $this->message->warning("Projeto não encontrado");
                $this->router->redirect("admin.projeto.index");
            }
            return null;
        }

        $projeto->empresa_print = trim((string) ($projeto->empresa_razao ?: $projeto->empresa_nome)) ?: "-";
        return $projeto;
    }
}
