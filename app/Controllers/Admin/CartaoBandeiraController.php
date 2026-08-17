<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\File;
use App\Core\Redirect;
use App\Core\Request;
use App\Models\CartaoBandeira;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CartaoBandeiraController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();

        $this->view->addData([
            "title"      => "Bandeiras de Cartão",
            "active_menu" => "cadastros-cartao-bandeira",
            "page" => [
                "title" => "Bandeiras de Cartão",
                "desc"  => "Cadastre as bandeiras de cartão de crédito e débito",
            ],
        ]);
    }

    public function index(): void
    {
        $this->authorize("cartao_bandeira_gerenciar");

        $this->view->addData([
            "breadcrumb" => [
                "Dashboard" => ["url" => $this->router->route("admin.home"), "current" => false],
                "Cadastros"  => ["url" => false, "current" => false],
                "Bandeiras"  => ["url" => false, "current" => true],
            ],
        ]);

        $dados = CartaoBandeira::orderBy("nome")->get();

        foreach ($dados as $d) {
            $d->hash = md5((string) $d->id);
        }

        $permissao = [
            "inserir" => $this->auth->allow("cartao_bandeira_inserir"),
            "editar"  => $this->auth->allow("cartao_bandeira_editar"),
            "excluir" => $this->auth->allow("cartao_bandeira_excluir"),
        ];

        echo $this->view->render("admin/cartao_bandeira/index", [
            "dados"     => $dados,
            "permissao" => $permissao,
        ]);
    }

    public function new(): void
    {
        $this->authorize("cartao_bandeira_inserir");

        echo $this->view->render("admin/cartao_bandeira/form", [
            "csrf"       => $this->csrf->generate(),
            "bandeira"   => false,
            "url_action" => $this->router->route("admin.cartao.bandeira.insert"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("cartao_bandeira_inserir");

        $data = new Data($request->all());

        if (!$data->has("nome")) {
            $this->message->warning("Informe o nome da bandeira");
            Redirect::referer();
            return;
        }

        if (!$data->has("bandeira")) {
            $this->message->warning("Informe o nome da bandeira (ex: Visa, Mastercard)");
            Redirect::referer();
            return;
        }

        $payload = $data->all();
        unset($payload["csrf"], $payload["id"]);
        $payload["created_by"] = $this->user->uid;

        $insert = CartaoBandeira::create($payload);

        if (!empty($_FILES["logo"]["name"])) {
            $logo = $this->processarLogo($_FILES["logo"]);
            if ($logo) {
                CartaoBandeira::updateBy($insert->id, ["logo" => $logo]);
            }
        }

        $this->message->success("Bandeira cadastrada com sucesso");
        $this->router->redirect("admin.cartao.bandeira.index");
    }

    public function edit(Request $request): void
    {
        $this->authorize("cartao_bandeira_editar");

        $data     = new Data($request->all());
        $bandeira = CartaoBandeira::findByMd5($data->id) ?: CartaoBandeira::find($data->id);

        if (!$bandeira) {
            $this->message->warning("Bandeira não encontrada");
            $this->router->redirect("admin.cartao.bandeira.index");
        }

        echo $this->view->render("admin/cartao_bandeira/form", [
            "csrf"       => $this->csrf->generate(),
            "bandeira"   => $bandeira,
            "url_action" => $this->router->route("admin.cartao.bandeira.update"),
        ]);
    }

    public function update(Request $request): void
    {
        $this->authorize("cartao_bandeira_editar");

        $data     = new Data($request->all());
        $bandeira = CartaoBandeira::findByMd5($data->id) ?: CartaoBandeira::find($data->id);

        if (!$bandeira) {
            $this->message->warning("Bandeira não encontrada");
            Redirect::referer();
            return;
        }

        if (!$data->has("nome")) {
            $this->message->warning("Informe o nome da bandeira");
            Redirect::referer();
            return;
        }

        $payload = $data->all();
        unset($payload["csrf"], $payload["id"]);
        $payload["updated_by"] = $this->user->uid;

        CartaoBandeira::updateBy($bandeira->id, $payload);

        if (!empty($_FILES["logo"]["name"])) {
            $logo = $this->processarLogo($_FILES["logo"], $bandeira->logo);
            if ($logo) {
                CartaoBandeira::updateBy($bandeira->id, ["logo" => $logo]);
            }
        }

        $this->message->success("Bandeira atualizada com sucesso");
        $this->router->redirect("admin.cartao.bandeira.index");
    }

    public function deleteLogo(Request $request): void
    {
        $this->authorize("cartao_bandeira_editar");

        header("Content-Type: application/json; charset=utf-8");

        $data     = new Data($request->all());
        $bandeira = CartaoBandeira::findByMd5($data->id) ?: CartaoBandeira::find($data->id);

        if (!$bandeira) {
            echo json_encode(["error" => true, "message" => "Bandeira não encontrada."], JSON_UNESCAPED_UNICODE);
            return;
        }

        if ($bandeira->logo) {
            (new File())->remove(CartaoBandeira::getMediaPath() . $bandeira->logo);
            CartaoBandeira::updateBy($bandeira->id, ["logo" => null, "updated_by" => $this->user->uid]);
        }

        echo json_encode(["error" => false, "message" => "Logo removido com sucesso."], JSON_UNESCAPED_UNICODE);
    }

    public function delete(Request $request): void
    {
        $this->authorize("cartao_bandeira_excluir");

        $data     = new Data($request->all());
        $bandeira = CartaoBandeira::findByMd5($data->id) ?: CartaoBandeira::find($data->id);

        if (!$bandeira) {
            $this->message->warning("Bandeira não encontrada");
            Redirect::referer();
            return;
        }

        if ($bandeira->logo) {
            $file = new File();
            $file->remove(CartaoBandeira::getMediaPath() . $bandeira->logo);
        }

        CartaoBandeira::deleteById($bandeira->id);

        $this->message->success("Bandeira removida com sucesso");
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
            $File->remove(CartaoBandeira::getMediaPath() . $logoAtual);
        }

        $dir  = CartaoBandeira::getMediaPath();
        $nome = $File->named($dir, $originalName);

        if ($ext === "svg") {
            File::upload($tmpName, $dir . $nome);
        } else {
            $binary = file_get_contents($tmpName);
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
