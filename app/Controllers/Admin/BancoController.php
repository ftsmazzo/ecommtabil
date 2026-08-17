<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\File;
use App\Core\Redirect;
use App\Core\Request;
use App\Models\Banco;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class BancoController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title" => "Bancos",
            "active_menu" => "cadastros-bancos",
            "page" => [
                "title" => "Bancos",
                "desc" => "Cadastre os bancos utilizados no sistema",
            ],
        ]);
    }

    public function index(): void
    {
        $this->authorize("banco_gerenciar");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cadastros"  => ["url" => false, "current" => false],
                "Bancos"     => ["url" => false, "current" => true],
            ],
        ]);

        $bancos = Banco::orderBy("nome")->get();

        foreach ($bancos as $banco) {
            $banco->hash = md5((string) $banco->id);
        }

        $permissao = [
            "inserir" => $this->auth->allow("banco_inserir"),
            "editar"  => $this->auth->allow("banco_editar"),
            "excluir" => $this->auth->allow("banco_excluir"),
        ];

        echo $this->view->render("admin/banco/index", [
            "dados"    => $bancos,
            "permissao" => $permissao,
        ]);
    }

    public function new(): void
    {
        $this->authorize("banco_inserir");

        echo $this->view->render("admin/banco/form", [
            "csrf"       => $this->csrf->generate(),
            "banco"      => false,
            "url_action" => $this->router->route("admin.banco.insert"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("banco_inserir");

        $data = new Data($request->all());

        if (!$data->has("nome")) {
            $this->message->warning("Informe o nome do banco");
            Redirect::referer();
            return;
        }

        $payload = $data->all();
        unset($payload["csrf"], $payload["id"]);
        $payload["created_by"] = $this->user->uid;

        $insert = Banco::create($payload);

        if (!empty($_FILES["logo"]["name"])) {
            $logo = $this->processarLogo($_FILES["logo"]);
            if ($logo) {
                Banco::updateBy($insert->id, ["logo" => $logo]);
            }
        }

        $this->message->success("Banco cadastrado com sucesso");
        $this->router->redirect("admin.banco.index");
    }

    public function edit(Request $request): void
    {
        $this->authorize("banco_editar");

        $data  = new Data($request->all());
        $banco = Banco::find($data->id);

        if (!$banco) {
            $this->message->warning("Banco não encontrado");
            $this->router->redirect("admin.banco.index");
        }

        echo $this->view->render("admin/banco/form", [
            "csrf"       => $this->csrf->generate(),
            "banco"      => $banco,
            "url_action" => $this->router->route("admin.banco.update"),
        ]);
    }

    public function update(Request $request): void
    {
        $this->authorize("banco_editar");

        $data  = new Data($request->all());
        $banco = Banco::find($data->id) ?: Banco::findByMd5($data->id);

        if (!$banco) {
            $this->message->warning("Banco não encontrado");
            Redirect::referer();
            return;
        }

        if (!$data->has("nome")) {
            $this->message->warning("Informe o nome do banco");
            Redirect::referer();
            return;
        }

        $payload = $data->all();
        unset($payload["csrf"], $payload["id"]);
        $payload["updated_by"] = $this->user->uid;

        Banco::updateBy($banco->id, $payload);

        if (!empty($_FILES["logo"]["name"])) {
            $logo = $this->processarLogo($_FILES["logo"], $banco->logo);
            if ($logo) {
                Banco::updateBy($banco->id, ["logo" => $logo]);
            }
        }

        $this->message->success("Banco atualizado com sucesso");
        $this->router->redirect("admin.banco.index");
    }

    public function deleteLogo(Request $request): void
    {
        $this->authorize("banco_editar");

        header("Content-Type: application/json; charset=utf-8");

        $data  = new Data($request->all());
        $banco = Banco::findByMd5($data->id) ?: Banco::find($data->id);

        if (!$banco) {
            echo json_encode(["error" => true, "message" => "Banco não encontrado."], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($banco->logo) {
            (new File())->remove(Banco::getMediaPath() . $banco->logo);
            Banco::updateBy($banco->id, ["logo" => null, "updated_by" => $this->user->uid]);
        }

        echo json_encode(["error" => false, "message" => "Logo removido com sucesso."], JSON_UNESCAPED_UNICODE);
    }

    public function delete(Request $request): void
    {
        $this->authorize("banco_excluir");

        $data  = new Data($request->all());
        $banco = Banco::find($data->id) ?: Banco::findByMd5($data->id);

        if (!$banco) {
            $this->message->warning("Banco não encontrado");
            Redirect::referer();
            return;
        }

        if ($banco->logo) {
            $file = new File();
            $file->remove(Banco::getMediaPath() . $banco->logo);
        }

        Banco::deleteById($banco->id);

        $this->message->success("Banco removido com sucesso");
        Redirect::referer();
    }

    private function processarLogo(array $file, ?string $logoAtual = null): ?string
    {
        $File = new File();
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
            $File->remove(Banco::getMediaPath() . $logoAtual);
        }

        $dir  = Banco::getMediaPath();
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
