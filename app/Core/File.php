<?php
namespace App\Core;

class File
{

    private static $file;

    public function __construct($file = null)
    {

        if ($file)
            self::$file = $file;
    }

    /**
     * Set the value of file
     *
     * @return self
     */
    public static function setFile($file)
    {
        self::$file = $file;

        return new self;
    }

    /**
     * Get the value of file
     */
    public static function getFile()
    {
        return self::$file;
    }

    public static function exists($file = null)
    {
        if ($file) self::$file = $file;
        return file_exists(self::$file);
    }

    public static function valid($file = null): bool
    {
        // Atribui o arquivo, se passado como parâmetro
        if ($file) self::$file = $file;

        // Verifica se o arquivo é uma string válida antes de chamar is_uploaded_file
        return is_string(self::$file) && is_uploaded_file(self::$file);
    }

    public static function size($file=null)
    {
        if ($file) self::$file = $file;
        return filesize(self::$file);
    }

    public static function upload($file, $path): bool
    {
        $dir = dirname($path);

        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        @move_uploaded_file($file, $path);

        // valida pelo resultado real
        return (file_exists($path) && filesize($path) > 0);
    }

    public static function remove($file=null)
    {
        if ($file) self::$file = $file;
        if (self::exists()){

            unlink(self::$file);
        }
    }

    public static function rename($old, $new)
    {
        return rename($old, $new);
    }

    public static function named($path, $file, $timestamp = false)
    {
        $search  = array('(', ')', ',', ' ','ª','á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','º','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ','Á','À','Â','Ã','Ä','É','È','Ê','Ë','Í','Ì','Î','Ï','Ó','Ò','Ô','Õ','Ö','Ú','Ù','Û','Ü','Ç','Ñ');
        $replace = array('', '', '', '-','a','a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','o','u','u','u','u','c','n','A','A','A','A','A','E','E','E','E','I','I','I','I','O','O','O','O','O','U','U','U','U','C','N');

        $file = strtolower(str_replace($search, $replace, $file));

        if (self::exists($path . $file)) {

            $nfiles = self::cont($path);

            for ($i = 1; $i <= $nfiles + 1; $i++) {

                $name = $i . '_' . $file;

                if (!self::exists($path . $name)) {

                    break;

                } else {

                    $name = $name;
                }
            }

        } else {

            $name = $file;
        }

        if ($timestamp) {

            $name = time() . '_' . $name;
        }

        return $name;
    }

    private static function cont($path)
    {
        $handle = opendir($path);
        $files = 0;

        while (false !== ($file = readdir($handle)))
        {
            if ($file != "." && $file != ".." && !(is_dir($path . $file))) {

                $files++;
            }
        }

        return $files;
        closedir($handle);
    }

    /**
     * Pegar somente o nome do arquivo
     * @param mixed $file
     * @return string
     */
    public static function name($file = null): string
    {
        if ($file) self::$file = $file;
        return preg_replace('/^.*\/(.*)\..*$/', '${1}', self::$file);
    }

    public static function extension($file=null): string
    {
        if ($file) self::$file = $file;
        return pathinfo(self::$file, PATHINFO_EXTENSION);
    }

    public static function readable($file=null): bool
    {
        if ($file) self::$file = $file;
        return is_readable(self::$file);
    }

    public static function is_image($file=null): bool
    {
        if ($file) self::$file = $file;

        if (exif_imagetype(self::$file))
            return true;

        return false;
    }

    public static function mime($file=null)
    {
        if ($file) self::$file = $file;

        if (class_exists('finfo')) {

            $finfo    = finfo_open(FILEINFO_MIME_TYPE);
            $mimetype = finfo_file($finfo, self::$file);

            finfo_close($finfo);

        } else if (function_exists('mime_content_type')) {//php5.3 ou inferiror

            $mimetype = mime_content_type(self::$file);
        }

        return $mimetype;
    }

    public static function download($file=null, $force=true)
    {

        if ($file) self::$file = $file;

        $allow_mime_types = [
            "htm" => "text/html",
            "exe" => "application/octet-stream",
            "zip" => "application/zip",
            "doc" => "application/msword",
            "docx" => "application/msword",
            "xls" => "application/vnd.ms-excel",
            "xlsx" => "application/vnd.ms-excel",
            "ppt" => "application/vnd.ms-powerpoint",
            "pptx" => "application/vnd.ms-powerpoint",
            "gif" => "image/gif",
            "pdf" => "application/pdf",
            "txt" => "text/plain",
            "html"=> "text/html",
            "png" => "image/png",
            "jpg" => "image/jpg",
            "jpeg"=> "image/jpg",
            "mp3" => "audio/mpeg",
            "php" => "text/plain",
        ];

        $extension = self::extension();

        if (array_key_exists($extension, $allow_mime_types)) {

            $mime_type = $allow_mime_types[$extension];

        } elseif ($force) {

            $mime_type = "application/force-download";
        }

        if (is_file(self::$file) === true) {

            if (self::readable() === false) {

                exit('File not found or inaccessible!');
            }

            $file = @fopen(self::$file, 'rb');

            if (is_resource($file) === true) {

                set_time_limit(0);
                ignore_user_abort(false);

                while (ob_get_level() > 0)
                {
                    ob_end_clean();
                }

                header('Expires: 0');
                header('Pragma: public');
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                if (isset($mime_type)) {
                    header('Content-Type: '.$mime_type);
                }
                header('Content-Length: ' . sprintf('%u', self::size()));

                if ($force) {
                    header('Content-Disposition: attachment; filename="' . basename(self::$file) . '"');
                } else {
                    header('Content-Disposition: inline; filename="' . basename(self::$file) . '"');
                }

                header('Content-Transfer-Encoding: binary');
                header('Accept-Ranges: bytes');

                while (feof($file) !== true)
                {

                    echo fread($file, 524288);

                    while (ob_get_level() > 0)
                    {
                        ob_end_flush();
                    }

                    flush();
                    sleep(1);
                }

                fclose($file);

            } else {

                exit("File is not resource");
            }

        } else {

            exit("Invalid file");
        }

        return false;
    }

    public static function stream($file=null)
    {
        return self::download($file, false);
    }

    public static function rearrange($files)
    {

        $list = [];

        foreach (array_keys($files) as $key) {

            foreach ($files[$key] as $i => $value) {

                $list[$i][$key] = $value;
            }
        }

        return $list;
    }

    public static function copy($original, $copy)
    {
        if (self::exists($copy) === false) {
            copy($original, $copy);
        }
    }

    public static function getContent($file = null)
    {

        if ($file) {
            self::$file = $file;
        }

        if (self::exists(self::$file)) {

            return file_get_contents(self::$file);

        } else {

            return "error on get contents file";
        }
    }

    public static function putContent($content, $file = null, $entity_decode = false)
    {

        if ($file) {
            self::$file = $file;
        }

        if ($entity_decode) {
            file_put_contents(self::$file, html_entity_decode($content));
        } else {
            file_put_contents(self::$file, $content);
        }
    }

    public static function getContents($file = null)
    {
        self::getContent($file);
    }

    public static function putContents($content, $file = null, $entity_decode = false)
    {
        self::putContent($file);
    }

}
