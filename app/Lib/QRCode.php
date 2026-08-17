<?php
/**
 * QR Code Generator Class
 *
 * Dependencies:
 *
 * endroid/qr-code
 * $ composer require endroid/qr-code
 * https://github.com/endroid/qr-code
 *
 */

namespace App\Lib;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\Label\Alignment\LabelAlignmentCenter;
use Endroid\QrCode\Label\Alignment\LabelAlignmentLeft;
use Endroid\QrCode\Label\Alignment\LabelAlignmentRight;
use Endroid\QrCode\Label\Font\NotoSans;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use Endroid\QrCode\Writer\EpsWriter;
use Endroid\QrCode\Writer\PdfWriter;
use Endroid\QrCode\QrCode as QR;
use Exception;

class QRCode
{

    private $size;
    private $margin;
    private $path;
    private $data;
    private $ext;
    private $foregroundColor;
    private $backgroundColor;
    private $encoding;
    private $logo;
    private $logoWidth;
    private $labelText;
    private $labelColor;
    private $labelAlignment;
    private $labelFont;
    private $output;
    private $errorCorrectionLevel;


    public function __construct()
    {

        $this->size = 400;

        $this->margin = 10;

        $this->ext = "png";

        $this->encoding = new Encoding('UTF-8');

        $this->foregroundColor = new Color(0, 0, 0);
        $this->backgroundColor = new Color(255, 255, 255);

        /* Logo */
        $this->logoWidth = 50;

        /* Label */
        $this->labelColor = new Color(0, 0, 0);
        $this->labelFont = new NotoSans(20);
        $this->labelAlignment = new LabelAlignmentCenter();
    }

    public function setEncoding($enc)
    {
        $this->encoding = new Encoding($enc);
    }

    public function setPath($path)
    {
        $this->path = $path;
    }

    public function setSize($size)
    {
        $this->size = $size;
    }

    public function setMargin($margin)
    {
        $this->margin = $margin;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function setExt($ext)
    {

        $allow = ["png", "svg", "eps", "pdf"];

        if (!in_array($ext, $allow))
            throw new Exception("QRCode:: Extension not allowed");

        $this->ext = $ext;
    }

    public function setErrorCorrectionLevel($level)
    {

        $allow = ['low', 'medium', 'quartile', 'high'];

        if (!in_array($ext, $allow))
            throw new Exception("QRCode:: Extension not allowed");

        $this->ext = $ext;
    }

    public function setLogo($logo)
    {
        $this->logo = $logo;
    }

    public function setLabel($text, $color=null, $alignment=null)
    {
        $this->labelText = $text;

        if ($color) {
            list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
            $this->setLabelColor($r, $g, $b);
        }

        if ($alignment) {
            $this->setLabelAlignment($alignment);
        }
    }

    public function setLabelText($text)
    {
        $this->labelText = $text;
    }

    public function setLabelColor($color)
    {
        list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
        $this->labelColor = new Color($r, $g, $b);
    }

    public function setLabelAlignment($alignment)
    {

        $alignments = [
            "center" => new LabelAlignmentCenter(),
            "right" => new LabelAlignmentRight(),
            "left" => new LabelAlignmentLeft(),
        ];

        $this->labelAlignment = $alignment;
    }

    public function setForegroundColor($color)
    {

        list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
        $this->foregroundColor = new Color($r, $g, $b);
    }

    public function setBackgroundColor($color)
    {
        list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
        $this->foregroundColor = new Color($r, $g, $b);
    }

    public function setOutput($output)
    {
        $this->output = $output;
    }

    public function generate($data=null, $output="save")
    {

        if ($data)
            $this->setData($data);

        if (!$this->data)
            throw new Exception("QRCode:: This QR Code Data is required!");

        if ($output=="save" && !$this->path)
            throw new Exception("QRCode:: This QR Code Path is required!");

        $imageClass = [
            "png" => new PngWriter(),
            "svg" => new SvgWriter(),
            "eps" => new EpsWriter(),
            "pdf" => new PdfWriter(),
        ];

        $qrcode = QR::create($this->data)
            ->setEncoding($this->encoding)
            ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->setSize($this->size)
            ->setMargin($this->margin)
            ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->setForegroundColor($this->foregroundColor)
            ->setBackgroundColor($this->backgroundColor);

        $writer = $imageClass[$this->ext];

        $logo = null;
        $label = null;
        $options = [];

        if ($this->logo) {

            $logo = Logo::create($this->logo)
                ->setResizeToWidth($this->logoWidth);
        }

        if ($this->labelText) {

            $label = Label::create($this->labelText)
                ->setTextColor($this->labelColor)
                ->setAlignment($this->labelAlignment)
                ->setFont($this->labelFont);
        }

        $result = $writer->write($qrcode, $logo, $label, $options);

        if ($this->output == "stream") {

            // Directly output the QR code
            header('Content-Type: '.$result->getMimeType());
            echo $result->getString();

        } elseif ($this->output == "uri") {

            // Generate a data URI to include image data inline (i.e. inside an <img> tag)
            return $result->getDataUri();

        } else {

            // Save it to a file
            return $result->saveToFile($this->path. "." . $this->ext);
        }

    }


}
