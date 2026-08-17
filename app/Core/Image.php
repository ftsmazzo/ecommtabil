<?php
namespace App\Core;

class Image
{

    protected $file;
    protected $image;
    protected $ext;

    public function setImage(string $file)
    {
        $this->file = $file;
        return $this;
    }

    private function create()
    {
        $this->ext = $this->extension($this->file);

        if ($this->ext=="png") {

            $this->imgcreate = imagecreatefrompng($this->file);
        }
        elseif ($this->ext=="jpg" || $this->ext=="jpeg") {

            $this->imgcreate = imagecreatefromjpeg($this->file);
        }
        return $this;
    }


    public function crop($x, $y, $width, $height)
    {

        $this->create($this->file);

        $dimensions = ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height];

        $this->image = imagecrop($this->imgcreate, $dimensions);

        return $this;
    }

    // direction = HORIZONTAL ou H, VERTICAL ou V, BOTH ou B
    public function flip($direction)
    {

        $d = strtoupper(substr($direction, 0, 1));

        $this->create($this->file);

        if ($direction=="H") {

            $this->image = imageflip($this->imgcreate, IMG_FLIP_HORIZONTAL);

        } elseif ($direction=="V") {

            $this->image = imageflip($this->imgcreate, IMG_FLIP_VERTICAL);

        } elseif ($direction=="B") {

            $this->image = imageflip($this->imgcreate, IMG_FLIP_BOTH);
        }

        return $this;
    }

    public function flipH()
    {
        return $this->flip("HORIZONTAL");
    }

    public function flipV()
    {
        return $this->flip("VERTICAL");
    }

    public function flipB()
    {
        return $this->flip("BOTH");
    }

    public function saveAs($name)
    {
        if ($this->ext=="png") {

            $save = imagepng($this->image, $name);

        } elseif ($this->ext=="jpg" || $this->ext=="jpeg") {

            $save = imagejpeg($this->image, $name);
        }

        imagedestroy($this->image);

        return $save ? true : false;
    }

    public function save()
    {
        if ($this->ext=="png") {

            $save = imagepng($this->image);

        } elseif ($this->ext=="jpg" || $this->ext=="jpeg") {

            $save = imagejpeg($this->image);
        }

        imagedestroy($this->image_final);

        return $save ? true : false;
    }

    private function extension($file)
    {
        return pathinfo($file, PATHINFO_EXTENSION);
    }

}
