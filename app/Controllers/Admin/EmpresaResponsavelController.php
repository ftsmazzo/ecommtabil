<?php

namespace App\Controllers\Admin;

use App\Core\ControllerAdmin;
use App\Core\Data;
use App\Core\Redirect;
use App\Core\Request;
use App\Models\Empresa;
use App\Models\EmpresaResponsavel;

class EmpresaResponsavelController extends ControllerAdmin
{
    public function __construct()
    {
        parent::__construct();
    }

    public function new(Request $request): void
    {
        $this->authorize("empresa_responsavel_inserir");

        $data    = new Data($request->all());
        $empresa = Empresa::findByMd5($data->id_empresa) ?: Empresa::find($data->id_empresa);

        if (!$empresa) {
            echo "<p class='text-danger p-3'>Empresa não encontrada.</p>";
            return;
        }

        echo $this->view->render("admin/empresa/responsavel_form", [
            "csrf"        => $this->csrf->generate(),
            "responsavel" => false,
            "empresa"     => $empresa,
            "url_action"  => $this->router->route("admin.empresa.responsavel.insert"),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize("empresa_responsavel_inserir");

        $data    = new Data($request->all());
        $empresa = Empresa::findByMd5($data->id_empresa) ?: Empresa::find($data->id_empresa);

        if (!$empresa) {
            $this->message->warning("Empresa não encontrada");
            Redirect::referer();
            return;
        }

        if (!$data->has("nome")) {
            $this->message->warning("Informe o nome do responsável");
            Redirect::referer();
            return;
        }

        $payload = [
            "id_empresa"  => $empresa->id,
            "nome"        => $data->nome,
            "cpf"         => $this->normalizeCpf($data->cpf ?? ""),
            "funcao"      => $data->has("funcao") && $data->funcao !== "" ? $data->funcao : null,
            "celular"     => $this->normalizePhone($data->celular ?? ""),
            "email"       => $data->has("email") && $data->email !== "" ? $data->email : null,
            "endereco"    => $data->has("endereco") && $data->endereco !== "" ? $data->endereco : null,
            "created_by"  => $this->user->uid,
        ];

        EmpresaResponsavel::create($payload);

        $this->message->success("Responsável cadastrado com sucesso");
        $this->router->redirect("admin.empresa.editar", ["id" => $empresa->hash()]);
    }

    public function edit(Request $request): void
    {
        $this->authorize("empresa_responsavel_editar");

        $data        = new Data($request->all());
        $responsavel = EmpresaResponsavel::findByMd5($data->id) ?: EmpresaResponsavel::find($data->id);

        if (!$responsavel) {
            echo "<p class='text-danger p-3'>Responsável não encontrado.</p>";
            return;
        }

        $empresa = Empresa::find($responsavel->id_empresa);

        echo $this->view->render("admin/empresa/responsavel_form", [
            "csrf"        => $this->csrf->generate(),
            "responsavel" => $responsavel,
            "empresa"     => $empresa,
            "url_action"  => $this->router->route("admin.empresa.responsavel.update"),
        ]);
    }

    public function update(Request $request): void
    {
        $this->authorize("empresa_responsavel_editar");

        $data        = new Data($request->all());
        $responsavel = EmpresaResponsavel::findByMd5($data->id) ?: EmpresaResponsavel::find($data->id);

        if (!$responsavel) {
            $this->message->warning("Responsável não encontrado");
            Redirect::referer();
            return;
        }

        if (!$data->has("nome")) {
            $this->message->warning("Informe o nome do responsável");
            Redirect::referer();
            return;
        }

        $payload = [
            "nome"       => $data->nome,
            "cpf"        => $this->normalizeCpf($data->cpf ?? ""),
            "funcao"     => $data->has("funcao") && $data->funcao !== "" ? $data->funcao : null,
            "celular"    => $this->normalizePhone($data->celular ?? ""),
            "email"      => $data->has("email") && $data->email !== "" ? $data->email : null,
            "endereco"   => $data->has("endereco") && $data->endereco !== "" ? $data->endereco : null,
            "updated_by" => $this->user->uid,
        ];

        EmpresaResponsavel::updateBy($responsavel->id, $payload);

        $empresa = Empresa::find($responsavel->id_empresa);

        $this->message->success("Responsável atualizado com sucesso");
        $this->router->redirect("admin.empresa.editar", ["id" => $empresa ? $empresa->hash() : $responsavel->id_empresa]);
    }

    public function delete(Request $request): void
    {
        $this->authorize("empresa_responsavel_excluir");

        header("Content-Type: application/json; charset=utf-8");

        $data        = new Data($request->all());
        $responsavel = EmpresaResponsavel::findByMd5($data->id) ?: EmpresaResponsavel::find($data->id);

        if (!$responsavel) {
            echo json_encode(["error" => true, "message" => "Responsável não encontrado."], JSON_UNESCAPED_UNICODE);
            return;
        }

        EmpresaResponsavel::deleteById($responsavel->id);

        echo json_encode(["error" => false, "message" => "Responsável removido com sucesso."], JSON_UNESCAPED_UNICODE);
    }

    private function normalizeCpf(string $value): ?string
    {
        $value = preg_replace('/[^0-9]/', '', trim($value));
        return $value !== "" ? $value : null;
    }

    private function normalizePhone(string $value): ?string
    {
        $value = preg_replace('/[^0-9]/', '', trim($value));
        return $value !== "" ? $value : null;
    }
}
