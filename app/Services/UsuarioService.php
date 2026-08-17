<?php
namespace App\Services;

use App\Models\Usuario;
use App\Core\Password;
use App\Core\File;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class UsuarioService
{
    /* -----------------------------
     | CRIAR USUARIO
     |----------------------------- */
    public static function criar(array $dados, int $criadorId): int
    {
        if (!empty($dados['senha'])) {
            $dados['senha'] = Password::hash($dados['senha']);
        }

        $dados['created_by'] = $criadorId;

        $insert = Usuario::create($dados);

        return $insert->id;
    }

    /* -----------------------------
     | ATUALIZAR USUARIO
     |----------------------------- */
    public static function atualizar(int $id, array $dados): bool
    {
        if (isset($dados['senha']) && $dados['senha']) {
            $dados['senha'] = Password::hash($dados['senha']);
        } else {
            unset($dados['senha']);
        }

        return Usuario::updateBy($id, $dados);
    }

    /* -----------------------------
     | PROCESSAR FOTO
     |----------------------------- */
    public static function processarFoto(array $file, ?string $fotoAtual = null): ?string
    {
        $File = new File();
        $permitidos = ["jpg", "jpeg", "png", "gif", "bmp"];
        $tmpName = $file["tmp_name"] ?? null;
        $originalName = $file["name"] ?? null;

        if (!$tmpName || !$originalName || !$File->valid($tmpName)) {
            return null;
        }

        $ext = strtolower($File->extension($originalName));

        if (!in_array($ext, $permitidos, true)) {
            throw new \Exception("Formato de imagem não permitido");
        }

        if (!$File->is_image($tmpName)) {
            throw new \Exception("Arquivo enviado não e uma imagem válida");
        }

        if ($fotoAtual) {
            $File->remove(Usuario::getMediaPath() . $fotoAtual);
        }

        $dir = Usuario::getMediaPath();
        $nome = $File->named($dir, $originalName);

        $binary = file_get_contents($tmpName);

        if ($binary === false) {
            throw new \Exception("Não foi possível ler a imagem enviada");
        }

        $manager = new ImageManager(new Driver());
        $image = $manager->decodeBinary($binary)->orient();

        if ($image->width() > 200 || $image->height() > 200) {
            $image->cover(200, 200);
        }

        $image->save($dir . $nome);

        return $nome;
    }

    public static function removerFotoUsuario(int $userId): void
    {
        $usuario = Usuario::find($userId);

        if (!$usuario) {
            return;
        }

        $foto = $usuario->foto ?? null;

        if (!$foto) {
            return;
        }

        self::removerFoto($usuario->foto);

        Usuario::updateBy($usuario->id, ['foto' => null]);
    }

    private static function removerFoto(?string $foto): void
    {
        if (!$foto) {
            return;
        }

        $dir = Usuario::getMediaPath();

        if (!$dir) {
            return;
        }

        $file = new File();
        $fullPath = $dir . $foto;

        if ($file->exists($fullPath)) {
            $file->remove($fullPath);
        }
    }
}
