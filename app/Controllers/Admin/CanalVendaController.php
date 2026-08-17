<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\File;
use App\Core\Redirect;
use App\Core\Request;
use App\Enums\CanalVendaTipo;
use App\Models\CanalVenda;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CanalVendaController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title"       => "Canais de Venda",
            "active_menu" => "cadastros-canal-venda",
            "page" => [
                "title" => "Canais de Venda",
                "desc"  => "Cadastre os canais de venda utilizados (iFood, Mercado Livre, Shopee...)",
            ],
        ]);
    }

    public function index(): void
    {
        $this->authorize("canal_venda_gerenciar");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard"      => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cadastros"      => ["url" => false, "current" => false],
                "Canais de Venda" => ["url" => false, "current" => true],
            ],
        ]);

        $dados = CanalVenda::orderBy("nome")->get();

        foreach ($dados as $d) {
            $d->hash = md5((string) $d->id);
        }

        $permissao = [
            "inserir" => $this->auth->allow("canal_venda_inserir"),
            "editar"  => $this->auth->allow("canal_venda_editar"),
            "excluir" => $this->auth->allow("canal_venda_excluir"),
        ];

        echo $this->view->render("admin/canal_venda/index", [
            "dados"     => $dados,
            "permissao" => $permissao,
        ]);
    }

    public function new(): void
    {
        $this->authorize("canal_venda_inserir");

        echo $this->view->render("admin/canal_venda/form", [
            "csrf"       => $this->csrf->generate(),
            "canal"      => false,
            "tipos"      => CanalVendaTipo::options(),
            "url_action" => $this->router->route("admin.canal.venda.insert"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("canal_venda_inserir");

        $data = new Data($request->all());

        if (!$data->has("nome")) {
            $this->message->warning("Informe o nome do canal de venda");
            Redirect::referer();
            return;
        }

        $payload = $data->all();
        unset($payload["csrf"], $payload["id"]);
        $payload["created_by"] = $this->user->uid;

        if (empty($payload["tipo"])) {
            $payload["tipo"] = null;
        }
        $payload["url"] = $this->normalizeUrl($payload["url"] ?? "");

        $insert = CanalVenda::create($payload);

        if (!empty($_FILES["logo"]["name"])) {
            $logo = $this->processarLogo($_FILES["logo"]);
            if ($logo) {
                CanalVenda::updateBy($insert->id, ["logo" => $logo]);
            }
        }

        $this->message->success("Canal de venda cadastrado com sucesso");
        $this->router->redirect("admin.canal.venda.index");
    }

    public function edit(Request $request): void
    {
        $this->authorize("canal_venda_editar");

        $data  = new Data($request->all());
        $canal = CanalVenda::findByMd5($data->id) ?: CanalVenda::find($data->id);

        if (!$canal) {
            $this->message->warning("Canal de venda não encontrado");
            $this->router->redirect("admin.canal.venda.index");
        }

        echo $this->view->render("admin/canal_venda/form", [
            "csrf"       => $this->csrf->generate(),
            "canal"      => $canal,
            "tipos"      => CanalVendaTipo::options(),
            "url_action" => $this->router->route("admin.canal.venda.update"),
        ]);
    }

    public function update(Request $request): void
    {
        $this->authorize("canal_venda_editar");

        $data  = new Data($request->all());
        $canal = CanalVenda::findByMd5($data->id) ?: CanalVenda::find($data->id);

        if (!$canal) {
            $this->message->warning("Canal de venda não encontrado");
            Redirect::referer();
            return;
        }

        if (!$data->has("nome")) {
            $this->message->warning("Informe o nome do canal de venda");
            Redirect::referer();
            return;
        }

        $payload = $data->all();
        unset($payload["csrf"], $payload["id"]);
        $payload["updated_by"] = $this->user->uid;

        if (empty($payload["tipo"])) {
            $payload["tipo"] = null;
        }
        $payload["url"] = $this->normalizeUrl($payload["url"] ?? "");

        CanalVenda::updateBy($canal->id, $payload);

        if (!empty($_FILES["logo"]["name"])) {
            $logo = $this->processarLogo($_FILES["logo"], $canal->logo);
            if ($logo) {
                CanalVenda::updateBy($canal->id, ["logo" => $logo]);
            }
        }

        $this->message->success("Canal de venda atualizado com sucesso");
        $this->router->redirect("admin.canal.venda.index");
    }

    public function deleteLogo(Request $request): void
    {
        $this->authorize("canal_venda_editar");

        header("Content-Type: application/json; charset=utf-8");

        $data  = new Data($request->all());
        $canal = CanalVenda::findByMd5($data->id) ?: CanalVenda::find($data->id);

        if (!$canal) {
            echo json_encode(["error" => true, "message" => "Canal de venda não encontrado."], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($canal->logo) {
            (new File())->remove(CanalVenda::getMediaPath() . $canal->logo);
            CanalVenda::updateBy($canal->id, ["logo" => null, "updated_by" => $this->user->uid]);
        }

        echo json_encode(["error" => false, "message" => "Logo removido com sucesso."], JSON_UNESCAPED_UNICODE);
    }

    public function delete(Request $request): void
    {
        $this->authorize("canal_venda_excluir");

        $data  = new Data($request->all());
        $canal = CanalVenda::findByMd5($data->id) ?: CanalVenda::find($data->id);

        if (!$canal) {
            $this->message->warning("Canal de venda não encontrado");
            Redirect::referer();
            return;
        }

        if ($canal->logo) {
            (new File())->remove(CanalVenda::getMediaPath() . $canal->logo);
        }

        CanalVenda::deleteById($canal->id);

        $this->message->success("Canal de venda removido com sucesso");
        Redirect::referer();
    }

    private function normalizeUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === "") {
            return null;
        }

        // Remove protocolo para salvar só o domínio/path
        $url = preg_replace('#^https?://#i', '', $url);
        $url = rtrim($url, "/");

        return $url ?: null;
    }

    private function processarLogo(array $file, ?string $logoAtual = null): ?string
    {
        $File         = new File();
        $tmpName      = $file["tmp_name"] ?? null;
        $originalName = $file["name"] ?? null;

        if (!$tmpName || !$originalName || !$File->valid($tmpName)) {
            return null;
        }

        $ext = strtolower($File->extension($originalName));

        if (!in_array($ext, ["jpg", "jpeg", "png", "gif", "webp", "svg"], true)) {
            throw new \Exception("Formato de imagem não permitido");
        }

        if ($ext !== "svg" && !$File->is_image($tmpName)) {
            throw new \Exception("Arquivo enviado não é uma imagem válida");
        }

        if ($logoAtual) {
            $File->remove(CanalVenda::getMediaPath() . $logoAtual);
        }

        $dir  = CanalVenda::getMediaPath();
        $nome = $File->named($dir, $originalName);

        if ($ext === "svg") {
            File::upload($tmpName, $dir . $nome);
        } else {
            $binary  = file_get_contents($tmpName);
            $manager = new ImageManager(new Driver());
            $image   = $manager->decodeBinary($binary)->orient();

            if ($image->width() > 400 || $image->height() > 400) {
                $image->scale(width: 400);
            }

            $image->save($dir . $nome);
        }

        return $nome;
    }
}
