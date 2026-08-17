<?php
/**
 * Excel Class
 *
 * Dependencies:
 *
 * phpoffice/phpspreadsheet
 * composer require phpoffice/phpspreadsheet
 * https://github.com/PHPOffice/PhpSpreadsheet
 * https://phpspreadsheet.readthedocs.io/en/latest/
 *
 */
namespace App\Lib;

use App\Core\File;
use Exception;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class Excel
{

    private $name;
    private $title;
    private $cells;
    private $spreadsheet;

    public function __construct()
    {

        $this->name = "documento.xlsx";

        $this->cells = [];

        $this->spreadsheet = new Spreadsheet();
    }

    private function setInterval($interval = null)
    {

        if ($interval) {

            $Interval = $interval;

        } else {

            $Interval = $this->spreadsheet
                ->getActiveSheet()
                ->calculateWorksheetDimension();
        }

        return $Interval;
    }

    function setTitle($title)
    {
        $this->title = substr($title, 0, 31);
    }

    function setName($name)
    {
        $this->name = $name;
    }

    private function columns($n)
    {

        if ($n > 702) {
            throw new Exception("Excel:: Only 702 columns are allowed");
        }

        $alphabet = ["A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z"];

        $total = count($alphabet);

        if ($n <= $total) {

            for ($i = 0; $i < $n; $i++) {

                $columns[] = $alphabet[$i];
            }

        } else {

            $loop = floor($n / $total);

            $over = $n % $total;

            for ($i = 0; $i < $loop; $i++) {

                for ($j = 0; $j < $total; $j++) {

                    $col  = $i > 0 ? $alphabet[$i - 1] : "";
                    $col .= $alphabet[$j];

                    $columns[] = $col;
                }
            }

            if ($over > 0) {

                for ($s = 0; $s < $over; $s++) {

                    $col_over  = $alphabet[$loop - 1];
                    $col_over .= $alphabet[$s];

                    $columns[] = $col_over;
                }
            }
        }

        return $columns;

    }

    public function addCell($cell, $value)
    {
        $this->cells[$cell] = $value;
    }

    public function addLine($array)
    {

        // ENCONTRAR PRÓXIMA LINHA
        $keys = array_keys($this->cells);


        $func = function ($valor): int {
            return preg_replace('/[^0-9]/', '', $valor);
        };

        $lines = array_map($func, $keys);

        $next = count($lines) > 0 ? max($lines) + 1 : 1;

        $ncolums = count($array);

        // $columns = $this->columns($ncolums);

        for ($i = 0; $i < $ncolums; $i++) {

            $colunaNome = Coordinate::stringFromColumnIndex($i + 1);
            $celula = $colunaNome . ($next);

            $this->cells[$celula] = $array[$i];
            // $this->cells[$columns[$i] . $next] = $array[$i];
        }
    }

    public function addLines($lines, $startLine = 1)
    {
        // Certifica-se de que $lines é um array
        $lines = (array) $lines;

        // Número de linhas a serem adicionadas
        $nlines = count($lines);

        // Número de colunas (pegando a quantidade da primeira linha)
        $ncolumns = count($lines[0]);

        // Obtém os nomes das colunas
        $columns = $this->columns($ncolumns);

        // Adicionar Linhas
        for ($line = 0; $line < $nlines; $line++) { // Iniciamos com $line = 0
            for ($c = 0; $c < $ncolumns; $c++) {
                // Calcula a célula que será preenchida, levando em conta a linha inicial
                $this->cells[$columns[$c] . ($line + $startLine)] = $lines[$line][$c];
            }
        }
    }

    public function setFilter($interval = false)
    {

        $interval = $this->setInterval($interval);

        $this->spreadsheet
            ->getActiveSheet()
            ->setAutoFilter($interval);
    }

    public function setBold($interval = false)
    {

        $interval = $this->setInterval($interval);

        $sheet = $this->spreadsheet
            ->getActiveSheet()
            ->getStyle($interval)
            ->getFont()
            ->setBold(true);
    }

    public function setFontSize($interval, $size = 11)
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        $sheet->getStyle($interval)->getFont()->setSize($size);
    }

    public function setAlignment($interval, $direction, $alignment)
    {

        if ($direction == "vertical") {

            $alignments = [
                "top" => Alignment::VERTICAL_TOP,
                "center" => Alignment::VERTICAL_CENTER,
                "bottom" => Alignment::VERTICAL_BOTTOM,
                "justify" => Alignment::VERTICAL_JUSTIFY,
                "distributed" => Alignment::VERTICAL_DISTRIBUTED,
            ];

            $sheet = $this->spreadsheet->getActiveSheet();
            $sheet
                ->getStyle($interval)
                ->getAlignment()
                ->setVertical($alignments[$alignment]);

        } elseif ($direction == "horizontal") {

            $alignments = [
                "left" => Alignment::HORIZONTAL_LEFT,
                "center" => Alignment::HORIZONTAL_CENTER,
                "right" => Alignment::HORIZONTAL_RIGHT,
                "justify" => Alignment::HORIZONTAL_JUSTIFY,
            ];

            $sheet = $this->spreadsheet->getActiveSheet();
            $sheet
                ->getStyle($interval)
                ->getAlignment()
                ->setHorizontal($alignments[$alignment]);
        }
    }

    public function setCenter($interval)
    {

        if (is_array($interval)) {

            foreach ($interval as $i) {

                $this->setAlignment($i, "vertical", "center");
                $this->setAlignment($i, "horizontal", "center");
            }

        } else {

            $this->setAlignment($interval, "vertical", "center");
            $this->setAlignment($interval, "horizontal", "center");
        }
    }

    public function setAlignmentHorizontal($interval, $alignment)
    {
        $this->setAlignment($interval, "horizontal", $alignment);
    }

    public function setAlignmentVertical($interval, $alignment)
    {
        $this->setAlignment($interval, "vertical", $alignment);
    }

    public function setTextColor($interval, $color)
    {

        $this->spreadsheet
            ->getActiveSheet()
            ->getStyle($interval)
            ->getFont()
            ->getColor()
            ->setARGB("FF" . str_replace("#", "", $color));
    }

    public function setFormatCode($interval, $format)
    {

        $this->spreadsheet
            ->getActiveSheet()
            ->getStyle($interval)
            ->getNumberFormat()
            ->setFormatCode($format);
    }

    /**
     * Definir quais intervalos irão receber o formato de Data
     *
     * @param string|array $interval
     *
     * @param string $format // Valores aceitos: normal, slash
     *
     * @return void
     */
    public function setFormatDate($interval, $format = "br")
    {

        $formats = [
            "br" => NumberFormat::FORMAT_DATE_DDMMYYYY,
            "slash" => NumberFormat::FORMAT_DATE_YYYYMMDDSLASH,
        ];

        if ((array)$interval) {

            foreach ($interval as $i) {

                $this->spreadsheet
                    ->getActiveSheet()
                    ->getStyle($i)
                    ->getNumberFormat()
                    ->setFormatCode($formats[$format]);
            }

        } else {

            $this->spreadsheet
                ->getActiveSheet()
                ->getStyle($interval)
                ->getNumberFormat()
                ->setFormatCode($formats[$format]);
        }
    }

    public function setFormatMoney($interval, $currency = "BRL")
    {

        if ($currency == "BRL") {

            $format = 'R$ #,##0.00_-';

        } else {

            $constants = [
                "USD" => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
                "EUR" => NumberFormat::FORMAT_CURRENCY_EUR,
            ];

            $format = $constants[$currency];
        }

        $this->spreadsheet
            ->getActiveSheet()
            ->getStyle($interval)
            ->getNumberFormat()
            ->setFormatCode($format);
    }

    /**
     * Set Borders of Cells
     * @param string $interval
     * Accept Values: thin (Fino) / thick (Grosso) / dashed (Tracejada) / dotted (Pontilhada) / double (Dupla)
     * @param string $style
     * Accept Values: all (Todos) / left (Esquerda) / right (Direita) / top (Superior) / bottom (Inferior) / diagonal (Diagonal)
     * @param array $side
     * RGB Value without #
     * @param string $color
     * @return void
     */
    public function setBorder(string $interval, string $style = "thin", array $side = ["all"], string $color = "000000")
    {

        (array)$side;

        $sheet = $this->spreadsheet->getActiveSheet()->getStyle($interval);

        $borders = [
            "thin"   => Border::BORDER_THIN,
            "thick"  => Border::BORDER_THICK,
            "dashed" => Border::BORDER_DASHED,
            "dotted" => Border::BORDER_DOTTED,
            "double" => Border::BORDER_DOUBLE,
        ];

        if (in_array("all", $side)) {

            $sheet->getBorders()->getAllBorders()->setBorderStyle($borders[$style]);

        } else {

            if (in_array("top", $side)) {

                $sheet->getBorders()->getTop()->setBorderStyle($borders[$style]);
            }
            if (in_array("right", $side)) {

                $sheet->getBorders()->getRight()->setBorderStyle($borders[$style]);
            }
            if (in_array("bottom", $side)) {

                $sheet->getBorders()->getBottom()->setBorderStyle($borders[$style]);
            }
            if (in_array("left", $side)) {

                $sheet->getBorders()->getLeft()->setBorderStyle($borders[$style]);
            }
        }

    }

    /**
     * Set Background Fill
     * @param string|array $interval
     * Accept RGB or ARGB values
     * @param string $startColor
     * Accept RGB or ARGB values
     * @param string|bool $endColor
     * Accept values: vertical, horizontal or int value for degrees
     * @param string|int $direction
     * @return void
     */
    public function setFill($interval, $startColor, $endColor = false, $direction = "horizontal")
    {

        $interval = $this->setInterval($interval);

        $startColor = strlen($startColor) == 8 ? $startColor : "FF" . $startColor;

        if (!$endColor) {

            $this->spreadsheet
                ->getActiveSheet()
                ->getStyle($interval)
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB($startColor);

        } else {

            $endColor = strlen($endColor) == 8 ? $endColor : "FF" . $endColor;

            $rotations = [
                "horizontal" => 0,
                "vertical" => 90,
                "diagonal" => 45,
            ];

            $rotation = is_int($direction) ? $direction : $rotations[$direction];

            $this->spreadsheet
                ->getActiveSheet()
                ->getStyle($interval)
                ->getFill()
                ->setFillType(Fill::FILL_GRADIENT_LINEAR)
                ->setRotation($rotation);

            $this->spreadsheet
                ->getActiveSheet()
                ->getStyle($interval)
                ->getFill()
                ->getStartColor()
                ->setARGB($startColor);

            $this->spreadsheet
                ->getActiveSheet()
                ->getStyle($interval)
                ->getFill()
                ->getEndColor()
                ->setARGB($endColor);
        }
    }

    /**
     * Set Background Fill
     * @param string|array $interval
     * Accept RGB or ARGB values
     * @param string $startColor
     * Accept RGB or ARGB values
     * @param string|bool $endColor
     * Accept values: vertical, horizontal or int value for rotation
     * @param string|int $direction
     * @return void
     */
    public function setBackground($interval, $startColor, $endColor = false, $direction = "horizontal") {
        $this->setFill($interval, $startColor, $endColor, $direction);
    }

    public function setMerge(string $interval)
    {

        $this->spreadsheet
            ->getActiveSheet()
            ->mergeCells($interval);
    }

    public function setHeader($row = 1, $highestColumn = null, $alignment = "center")
    {

        // PEGAR A COLUNA PREENCHIDA MAIS A ESQUERDA
        if (is_null($highestColumn)) {
            $highestColumn = $this->spreadsheet->getActiveSheet()->getHighestColumn();
        }

        $this->setBold("A{$row}:{$highestColumn}{$row}");
        $this->setAlignment("A{$row}:{$highestColumn}{$row}", "horizontal", "center");
        $this->setAlignment("A{$row}:{$highestColumn}{$row}", "vertical", "center");
    }

    public function sum($cell, $interval)
    {
        $this->cells[$cell] = "=SUM({$interval})";
    }

    public function sumColumn($cell, $column)
    {
        $this->cells[$cell] = "=SUM({$column}:{$column})";
    }

    public function avg($cell, $interval)
    {
        $this->cells[$cell] = "=AVG({$interval})";
    }

    public function setAutoWidthColumn($ncolumns = 1)
    {

        $sheet = $this->spreadsheet->getActiveSheet();

        $columns = $this->columns($ncolumns);

        foreach ($columns as $column) {

            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    public function setAutoHeightRow($nrows = 1)
    {

        $sheet = $this->spreadsheet->getActiveSheet();

        for ($line = 1; $line <= $nrows; $line++) {
            $sheet->getColumnDimension($line)->setAutoSize(true);
        }
    }

    /**
     * Adiciona uma imagem à planilha no local especificado.
     *
     * Esta função permite adicionar uma imagem em uma célula específica com ajustes
     * no posicionamento e tamanho. O deslocamento pode ser configurado para alinhar
     * a imagem à esquerda ou à direita, e o tamanho da imagem também pode ser ajustado.
     *
     * @param string $cell A célula onde a imagem será inserida (ex: 'A1', 'B2').
     * @param string $path Caminho para o arquivo de imagem (ex: '/images/imagem.png').
     * @param int $height Altura da imagem em pixels. O valor padrão é 50 pixels.
     * @param int $offsetX Deslocamento horizontal da imagem. Se o valor for negativo,
     *                     a imagem será posicionada a partir da borda direita da célula.
     *                     O valor padrão é 0 (sem deslocamento).
     * @param int $offsetY Deslocamento vertical da imagem. O valor padrão é 10 pixels.
     * @param string $name Nome da imagem que será exibido na planilha. O valor padrão é 'Imagem'.
     * @param string $description Descrição da imagem que será exibida. O valor padrão é 'Descrição da Imagem'.
     *
     * @return void
     *
     * @throws InvalidArgumentException Caso o caminho da imagem não seja válido ou não exista.
     * @throws PHPExcel_Exception Caso haja algum erro ao adicionar a imagem à planilha.
     */
    public function addImage($cell, $path, $height = 50, $align = 'left', $offsetX = 0, $offsetY = 10, $name = 'Imagem', $description = 'Descrição da Imagem')
    {
        $sheet = $this->spreadsheet->getActiveSheet();
        $drawing = new Drawing();
        $drawing->setName($name);
        $drawing->setDescription($description);
        $drawing->setPath($path); // Caminho para o arquivo de imagem
        $drawing->setHeight($height); // Altura da imagem em pixels

        $totalWidth = 0;

        // Verifica se o parâmetro $cell é um intervalo (ex: "A1:O1")
        if (strpos($cell, ':') !== false) {
            $cells = explode(':', $cell);

            // Certifica-se de que $cells tem dois elementos antes de acessá-los
            if (count($cells) === 2) {
                list($startCell, $endCell) = $cells;

                $startCol = preg_replace('/[0-9]/', '', $startCell); // Ex: 'A'
                $endCol = preg_replace('/[0-9]/', '', $endCell); // Ex: 'O'

                // Converte as letras das colunas em índices numéricos
                $startColIndex = Coordinate::columnIndexFromString($startCol);
                $endColIndex = Coordinate::columnIndexFromString($endCol);

                // Calcula a largura total do intervalo somando as larguras das colunas
                for ($col = $startColIndex; $col <= $endColIndex; $col++) {
                    $colLetter = Coordinate::stringFromColumnIndex($col);
                    $columnWidth = $sheet->getColumnDimension($colLetter)->getWidth();

                    // Se a largura for -1 (auto-ajuste), define um valor padrão
                    if ($columnWidth == -1) {
                        $columnWidth = 8.43; // Largura padrão do Excel
                    }

                    $totalWidth += $columnWidth * 7.2; // Converte para pixels
                }

                // A coordenada da imagem será a primeira célula do intervalo
                $cell = $startCell;
            }
        }

        // Se $cell for uma única célula (ex: "A1") ou o explode não funcionar corretamente
        if ($totalWidth === 0) {
            $columnLetter = preg_replace('/[0-9]/', '', $cell);
            $columnWidth = $sheet->getColumnDimension($columnLetter)->getWidth();

            // Se a largura for -1 (auto-ajuste), define um valor padrão
            if ($columnWidth == -1) {
                $columnWidth = 8.43; // Largura padrão do Excel
            }

            $totalWidth = $columnWidth * 7.2; // Converte para pixels
        }

        // Obtém a largura da imagem
        $imageWidth = $drawing->getWidth();

        // Ajusta o deslocamento baseado no alinhamento
        if ($align === 'right') {
            $offsetX = $totalWidth - $imageWidth + $offsetX;
        }

        // Converte offsetX para inteiro
        $offsetX = (int) $offsetX;

        // Define a coordenada e o deslocamento da imagem
        $drawing->setCoordinates($cell);  // Posição da célula
        $drawing->setOffsetX($offsetX);   // Ajuste horizontal
        $drawing->setOffsetY($offsetY);   // Ajuste vertical

        // Adiciona a imagem à planilha
        $drawing->setWorksheet($sheet);
    }

    /**
     * Aplica formatação condicional para células com valores menores que zero
     * @param string $interval Intervalo de células para verificar e aplicar a formatação
     * @param string $textColor Cor do texto (default é vermelho #FF0000)
     * @param string|null $background Cor de fundo (opcional, caso não fornecido será ignorado)
     */
    public function setClassNegatives($interval, $textColor = "#FF0000", $background = null) {
        // Extrair as células do intervalo fornecido
        list($startCell, $endCell) = explode(":", $interval);

        // Converter as células em coordenadas (linha e coluna)
        list($startCol, $startRow) = $this->cellToCoordinates($startCell);
        list($endCol, $endRow) = $this->cellToCoordinates($endCell);

        // Percorrer o intervalo de células
        for ($row = $startRow; $row <= $endRow; $row++) {
            for ($col = $startCol; $col <= $endCol; $col++) {
                // Obter o valor da célula atual
                $cellValue = $this->getCellValue($col, $row);

                // Verificar se o valor é menor que zero
                if ($cellValue < 0) {
                    $cell = $this->coordinatesToCell($col, $row);

                    // Aplicar cor de texto
                    $this->setTextColor($cell, $textColor);

                    // Aplicar cor de fundo se fornecida
                    if ($background) {
                        $this->setFill($cell, $background);
                    }
                }
            }
        }
    }



    /**
     * Retorna a parte da coluna de uma célula (ex: 'A1' retorna 'A')
     */
    private function getColumnLetter($cell)
    {
        preg_match('/[A-Za-z]+/', $cell, $matches);
        return $matches[0];
    }


    /**
     * Cria a planilha e força o download
     * através da classe File
     *
     * @param string|null $name
     * @param bool $download
     *
     * @throws Exception
     * @return void
     */
    public function create($name = null, $download = true)
    {

        if (count($this->cells) == 0) {
            throw new Exception("Excel:: Cells cannot be empty");
        }

        if ($name) {
            $this->setName($name);
        }

        if ($this->title) {
            $this->spreadsheet->getActiveSheet()->setTitle($this->title);
        }

        $sheet = $this->spreadsheet->getActiveSheet();

        foreach ($this->cells as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $xls = new Xlsx($this->spreadsheet);
        $xls->save("storage/tmp/" . $this->name);

        if ($download) {
            // FORÇAR DOWNLOAD
            $File = new File();
            $File->download("storage/tmp/" . $this->name);

            // REMOVER ARQUIVO
            $File->remove("storage/tmp/" . $this->name);
        }

        $this->spreadsheet->disconnectWorksheets();
        unset($this->spreadsheet);
    }

    public function createAjax($name = null)
    {
        // Defina o nome do arquivo, se não for passado como parâmetro
        if ($name) {
            $this->setName($name);
        }

        // Gerar a planilha com as células que foram configuradas
        $sheet = $this->spreadsheet->getActiveSheet();
        foreach ($this->cells as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Configuração dos cabeçalhos para forçar o download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $this->name . '"');
        header('Cache-Control: max-age=0'); // Certifique-se de que o arquivo não seja armazenado em cache

        // Criar o escritor e enviar para o navegador
        $writer = new Xlsx($this->spreadsheet);
        $writer->save('php://output');  // Envia para a saída para o navegador
    }


    /**
     * Cria comentários na Célula
     *
     * @param string $cell
     * @param string $text
     * @param string $title
     * @param string $author
     * @return void
     */
    public function setComment($cell, $text, $title = null, $author = null, $width = 300, $height = 150)
    {

        if ($author) {
            $this->spreadsheet->getActiveSheet()->getComment($cell)->setAuthor($author);
        }

        if ($title) {

            $this->spreadsheet
                ->getActiveSheet()
                ->getComment($cell)
                ->getText()
                ->createTextRun($title)
                ->getFont()
                ->setBold(true)
                ->setSize(10);

            // ADICIONAR QUEBRA DE LINHA DEPOIS DO TITULO
            $this->spreadsheet->getActiveSheet()
                ->getComment($cell)
                ->getText()->createTextRun("\r\n");
        }

        // ADICIONAR AS QUEBRAS DE LINHA DO TEXTO EM UM ARRAY
        $text = explode("\n", $text);

        $i = 0;
        foreach ($text as $t) {

            $this->spreadsheet->getActiveSheet()
                ->getComment($cell)
                ->getText()
                ->createTextRun($t)
                ->getFont()
                ->setSize(10);

            // SE EXISTIR UM PROXIMO TEXTO, ADICIONA UMA QUEBRA DE LINHA
            if (isset($text[$i + 1])) {
                $this->spreadsheet->getActiveSheet()->getComment($cell)->getText()->createTextRun("\r\n");
            }

            $i++;
        }

        // SETAR TAMANHO
        if ($width) {
            $this->spreadsheet->getActiveSheet()->getComment($cell)->setWidth("{$width}px");
        }
        if ($height) {
            $this->spreadsheet->getActiveSheet()->getComment($cell)->setHeight("{$height}px");
        }
    }

    // Função fictícia para pegar o valor de uma célula (Você deve implementar conforme sua lógica)
    private function getCellValue($col, $row) {
        // Implementar lógica para obter o valor da célula com base nas coordenadas
    }

    // Função para converter a célula (como "A1") em coordenadas (coluna, linha)
    private function cellToCoordinates($cell) {
        preg_match('/([A-Z]+)([0-9]+)/', $cell, $matches);
        $col = 0;
        // Converter a letra da coluna para número (A=1, B=2, etc.)
        for ($i = 0; $i < strlen($matches[1]); $i++) {
            $col = $col * 26 + ord($matches[1][$i]) - 64;
        }
        $row = $matches[2];
        return [$col, $row];
    }

    // Função para converter as coordenadas (coluna, linha) para célula (como "A1")
    private function coordinatesToCell($col, $row) {
        $column = '';
        while ($col > 0) {
            $col--;
            $column = chr($col % 26 + 65) . $column;
            $col = floor($col / 26);
        }
        return $column . $row;
    }

}