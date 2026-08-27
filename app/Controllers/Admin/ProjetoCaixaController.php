<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\DB;
use App\Core\Request;
use App\Models\CaixaMovimento;
use App\Models\CaixaRecibo;
use App\Models\CaixaSessao;
use App\Models\DreConta;
use App\Models\Empresa;
use App\Models\Projeto;
use App\Services\Caixa\CaixaClassificadorService;
use App\Services\Caixa\CaixaConferenciaService;
use App\Services\Caixa\CaixaReciboService;
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

        DreConta::garantirPlanoSagaPadrao("dfc");

        $sessoes = CaixaSessao::ativasPorProjeto((int) $projeto->id);
        $sessaoId = (int) (($request->all()["sessao"] ?? 0));
        $sessao = null;
        $movimentos = [];
        $resumo = null;
        $recibos = [];
        $vinculosPorMov = [];

        if ($sessaoId > 0) {
            $sessao = CaixaSessao::findAtiva($sessaoId, (int) $projeto->id);
        }
        if (!$sessao && $sessoes !== []) {
            $sessao = $sessoes[0];
        }
        if ($sessao) {
            $filtro = trim((string) (($request->all()["status"] ?? "")));
            $faixa = trim((string) (($request->all()["faixa"] ?? "")));
            $movimentos = CaixaMovimento::porSessao((int) $sessao->id, $filtro !== "" ? $filtro : null);
            if ($faixa !== "") {
                $movimentos = array_values(array_filter($movimentos, static function ($m) use ($faixa) {
                    $c = (int) ($m->confianca_conta ?? 0);
                    return match ($faixa) {
                        "alta"  => $c >= 85,
                        "media" => $c >= 50 && $c < 85,
                        "baixa" => $c > 0 && $c < 50,
                        "sem"   => $c <= 0,
                        default => true,
                    };
                }));
            }
            $resumo = CaixaMovimento::resumoPorSessao((int) $sessao->id);
            $recibos = CaixaRecibo::porSessao((int) $sessao->id);
            $vinculosPorMov = $this->vinculosPorMovimento((int) $sessao->id);
        }

        $contas = DreConta::analiticasLista("dfc");
        $contasMap = [];
        foreach ($contas as $c) {
            $contasMap[(int) $c->id] = $c;
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
            "projeto"         => $projeto,
            "aba"             => "montar-dfc",
            "sessoes"         => $sessoes,
            "sessao"          => $sessao,
            "movimentos"      => $movimentos,
            "resumo"          => $resumo,
            "recibos"         => $recibos,
            "vinculosPorMov"  => $vinculosPorMov,
            "contas"          => $contas,
            "contasMap"       => $contasMap,
            "filtro"          => trim((string) (($request->all()["status"] ?? ""))),
            "faixa"           => trim((string) (($request->all()["faixa"] ?? ""))),
            "csrf"            => $this->csrf->generate(),
            "empresas"        => Empresa::orderBy("razao")->get(),
            "permissao"       => [
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

            $cls = (new CaixaClassificadorService())->classificarSessao(
                (int) $out["sessao"]->id,
                (int) $projeto->id,
                true,
                true
            );

            $msg = "Extrato lido: {$out["total"]} movimentos. Classificados: {$cls["atualizados"]}";
            $msg .= " (memória {$cls["por_memoria"]}, regras {$cls["por_regra"]}, IA {$cls["por_ia"]}).";
            $this->message->success($msg);

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

    public function classificar(Request $request): void
    {
        $this->authorize("projeto_gerenciar");
        $projeto = $this->carregarProjeto($request, true);
        if (!$projeto) {
            return;
        }

        $data = new Data($request->all());
        $sessao = CaixaSessao::findAtiva((int) ($data->sessao_id ?? 0), (int) $projeto->id);
        if (!$sessao) {
            $this->message->warning("Sessão inválida.");
            $this->router->redirect("admin.projeto.caixa", ["id" => $projeto->id]);
            return;
        }

        $usarIa = !empty($data->usar_ia);
        $cls = (new CaixaClassificadorService())->classificarSessao(
            (int) $sessao->id,
            (int) $projeto->id,
            $usarIa,
            true
        );

        $msg = "Classificação: {$cls["atualizados"]} atualizados";
        $msg .= " (memória {$cls["por_memoria"]}, regras {$cls["por_regra"]}, IA {$cls["por_ia"]}).";
        if ($cls["avisos"] !== []) {
            $this->message->warning($msg . " " . $cls["avisos"][0]);
        } else {
            $this->message->success($msg);
        }

        $this->router->redirect("admin.projeto.caixa", [
            "id"     => $projeto->id,
            "sessao" => $sessao->id,
        ]);
    }

    public function uploadRecibos(Request $request): void
    {
        $this->authorize("projeto_gerenciar");
        $projeto = $this->carregarProjeto($request, true);
        if (!$projeto) {
            return;
        }

        $data = new Data($request->all());
        $sessao = CaixaSessao::findAtiva((int) ($data->sessao_id ?? 0), (int) $projeto->id);
        if (!$sessao) {
            $this->message->warning("Abra uma sessão de extrato antes de enviar recibos.");
            $this->router->redirect("admin.projeto.caixa", ["id" => $projeto->id]);
            return;
        }

        $files = $this->normalizarFiles($_FILES["recibos"] ?? null);
        if ($files === []) {
            $this->message->warning("Selecione um ou mais PDFs/imagens de recibo.");
            $this->router->redirect("admin.projeto.caixa", ["id" => $projeto->id, "sessao" => $sessao->id]);
            return;
        }

        $out = (new CaixaReciboService())->uploadLote((int) $sessao->id, $files);
        $msg = "Recibos: {$out["criados"]} salvos, {$out["vinculos"]} vínculos sugeridos.";
        if ($out["avisos"] !== []) {
            $this->message->warning($msg . " " . $out["avisos"][0]);
        } else {
            $this->message->success($msg);
        }

        $this->router->redirect("admin.projeto.caixa", [
            "id"     => $projeto->id,
            "sessao" => $sessao->id,
        ]);
    }

    public function aprovar(Request $request): void
    {
        $this->authorize("projeto_gerenciar");
        $this->acaoMovimento($request, "aprovar");
    }

    public function ignorar(Request $request): void
    {
        $this->authorize("projeto_gerenciar");
        $this->acaoMovimento($request, "ignorar");
    }

    public function editar(Request $request): void
    {
        $this->authorize("projeto_gerenciar");
        $projeto = $this->carregarProjeto($request, true);
        if (!$projeto) {
            return;
        }

        $data = new Data($request->all());
        $sessao = CaixaSessao::findAtiva((int) ($data->sessao_id ?? 0), (int) $projeto->id);
        $idMov = (int) ($data->movimento_id ?? 0);
        $idConta = (int) ($data->id_dre_conta ?? 0);
        if (!$sessao || $idMov < 1 || $idConta < 1) {
            $this->message->warning("Dados incompletos para editar.");
            $this->router->redirect("admin.projeto.caixa", ["id" => $projeto->id]);
            return;
        }

        $ok = (new CaixaConferenciaService())->editar($idMov, (int) $sessao->id, $idConta, true);
        if ($ok) {
            $this->message->success("Movimento atualizado e aprovado.");
        } else {
            $this->message->warning("Não foi possível salvar a conta.");
        }
        $this->router->redirect("admin.projeto.caixa", ["id" => $projeto->id, "sessao" => $sessao->id]);
    }

    public function aprovarAltas(Request $request): void
    {
        $this->authorize("projeto_gerenciar");
        $projeto = $this->carregarProjeto($request, true);
        if (!$projeto) {
            return;
        }

        $data = new Data($request->all());
        $sessao = CaixaSessao::findAtiva((int) ($data->sessao_id ?? 0), (int) $projeto->id);
        if (!$sessao) {
            $this->message->warning("Sessão inválida.");
            $this->router->redirect("admin.projeto.caixa", ["id" => $projeto->id]);
            return;
        }

        $n = (new CaixaConferenciaService())->aprovarAltas((int) $sessao->id, 85);
        $this->message->success("Aprovados {$n} movimentos com confiança ≥ 85%.");
        $this->router->redirect("admin.projeto.caixa", ["id" => $projeto->id, "sessao" => $sessao->id]);
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

    private function acaoMovimento(Request $request, string $acao): void
    {
        $projeto = $this->carregarProjeto($request, true);
        if (!$projeto) {
            return;
        }

        $data = new Data($request->all());
        $sessao = CaixaSessao::findAtiva((int) ($data->sessao_id ?? 0), (int) $projeto->id);
        $idMov = (int) ($data->movimento_id ?? 0);
        if (!$sessao || $idMov < 1) {
            $this->message->warning("Movimento inválido.");
            $this->router->redirect("admin.projeto.caixa", ["id" => $projeto->id]);
            return;
        }

        $svc = new CaixaConferenciaService();
        $ok = $acao === "ignorar"
            ? $svc->ignorar($idMov, (int) $sessao->id)
            : $svc->aprovar($idMov, (int) $sessao->id);

        if ($acao === "aprovar" && !$ok) {
            $this->message->warning("Defina a conta DFC antes de aprovar.");
        } else {
            $this->message->success($acao === "ignorar" ? "Movimento ignorado." : "Movimento aprovado.");
        }

        $this->router->redirect("admin.projeto.caixa", [
            "id"     => $projeto->id,
            "sessao" => $sessao->id,
            "status" => (string) ($data->status ?? ""),
            "faixa"  => (string) ($data->faixa ?? ""),
        ]);
    }

    /**
     * @return array<int,array<int,object>>
     */
    private function vinculosPorMovimento(int $idSessao): array
    {
        $rows = DB::execute(
            "SELECT v.id, v.id_movimento, v.id_recibo, v.confianca_match, v.status, v.motivo,
                    r.nome_original, r.valor AS recibo_valor
             FROM caixa_vinculo v
             INNER JOIN caixa_movimento m ON m.id = v.id_movimento AND m.id_sessao = ? AND m.trash = 0
             INNER JOIN caixa_recibo r ON r.id = v.id_recibo AND r.trash = 0
             WHERE v.trash = 0
             ORDER BY v.confianca_match DESC",
            [$idSessao]
        );

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->id_movimento][] = $r;
        }
        return $out;
    }

    /**
     * @param mixed $filesField
     * @return array<int,array{name:string,tmp_name:string,error:int,size:int,type?:string}>
     */
    private function normalizarFiles(mixed $filesField): array
    {
        if (!is_array($filesField) || !isset($filesField["name"])) {
            return [];
        }
        if (!is_array($filesField["name"])) {
            return [[
                "name"     => (string) $filesField["name"],
                "tmp_name" => (string) $filesField["tmp_name"],
                "error"    => (int) $filesField["error"],
                "size"     => (int) $filesField["size"],
                "type"     => (string) ($filesField["type"] ?? ""),
            ]];
        }
        $out = [];
        foreach ($filesField["name"] as $i => $name) {
            $out[] = [
                "name"     => (string) $name,
                "tmp_name" => (string) $filesField["tmp_name"][$i],
                "error"    => (int) $filesField["error"][$i],
                "size"     => (int) $filesField["size"][$i],
                "type"     => (string) ($filesField["type"][$i] ?? ""),
            ];
        }
        return $out;
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
