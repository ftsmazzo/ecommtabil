<?php

require __DIR__ . '/../../vendor/autoload.php';

ob_start();
App\Core\Bootstrap::init(__DIR__ . '/../..');
ob_end_clean();

use App\Models\ClienteRamo;
use App\Models\ClienteSituacao;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function buildClienteTemplate(bool $includeRamo): Spreadsheet
{
    $spreadsheet = new Spreadsheet();

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Clientes');

    $headers = [
        'Tipo de Pessoa',
        'CPF / CNPJ',
        'Razão Social / Nome Completo',
        'Nome Fantasia / Apelido',
        'IE / RG',
        'Data de Abertura / Nascimento',
        'Contato',
        'Telefone',
        'WhatsApp',
        'E-mail',
        'Site',
        'Situação',
    ];

    if ($includeRamo) {
        $headers[] = 'Ramo';
    }

    $headers = array_merge($headers, [
        'CEP',
        'Logradouro',
        'Número',
        'Complemento',
        'Bairro',
        'Cidade',
        'Estado',
        'País',
        'Observações',
    ]);

    $sheet->fromArray($headers, null, 'A1');
    $sheet->freezePane('A2');
    $sheet->getRowDimension(1)->setRowHeight(24);

    $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
    $sheetRange = 'A1:' . $lastColumn . '1';
    $sheet->getStyle($sheetRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle($sheetRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1F6F43');
    $sheet->getStyle($sheetRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle($sheetRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

    $widths = [
        'A' => 12,
        'B' => 18,
        'C' => 32,
        'D' => 28,
        'E' => 18,
        'F' => 22,
        'G' => 20,
        'H' => 16,
        'I' => 16,
        'J' => 26,
        'K' => 24,
        'L' => 20,
    ];

    if ($includeRamo) {
        $widths['M'] = 24;
        $widths['N'] = 14;
        $widths['O'] = 28;
        $widths['P'] = 12;
        $widths['Q'] = 18;
        $widths['R'] = 18;
        $widths['S'] = 20;
        $widths['T'] = 10;
        $widths['U'] = 14;
        $widths['V'] = 30;
    } else {
        $widths['M'] = 14;
        $widths['N'] = 28;
        $widths['O'] = 12;
        $widths['P'] = 18;
        $widths['Q'] = 18;
        $widths['R'] = 20;
        $widths['S'] = 10;
        $widths['T'] = 14;
        $widths['U'] = 30;
    }

    foreach ($widths as $column => $width) {
        $sheet->getColumnDimension($column)->setWidth($width);
    }

    $situacaoColumn = $includeRamo ? 'L' : 'L';
    $sheet->getComment($situacaoColumn . '1')->getText()->createTextRun('Preencha com a descricao da situacao cadastrada no sistema.');

    if ($includeRamo) {
        $sheet->getComment('M1')->getText()->createTextRun('Preencha com a descricao do ramo cadastrado no sistema.');
    }

    $notes = $spreadsheet->createSheet();
    $notes->setTitle('Apoio');
    $notes->setCellValue('A1', 'Instruções');
    $notes->setCellValue('A2', '1. Preencha apenas as linhas abaixo do cabeçalho.');
    $notes->setCellValue('A3', $includeRamo ? '2. Situação e Ramo devem ser informados pela descrição.' : '2. Situação deve ser informada pela descrição.');
    $notes->setCellValue('A4', '3. Se o campo não existir no cadastro ainda, ele poderá ser criado na importação.');
    $notes->setCellValue('A5', '4. Pessoa aceita F, J, PF, PJ, física e jurídica.');
    $notes->getStyle('A1')->getFont()->setBold(true);
    $notes->getColumnDimension('A')->setWidth(90);

    $notes->setCellValue('C1', 'Situações cadastradas');
    $notes->getStyle('C1')->getFont()->setBold(true);
    $notes->getColumnDimension('C')->setWidth(30);

    $situacoes = ClienteSituacao::orderBy('descricao')->get();
    $row = 2;
    foreach ($situacoes as $situacao) {
        $notes->setCellValue('C' . $row, $situacao->descricao);
        $row++;
    }

    if ($includeRamo) {
        $notes->setCellValue('E1', 'Ramos cadastrados');
        $notes->getStyle('E1')->getFont()->setBold(true);
        $notes->getColumnDimension('E')->setWidth(30);

        $ramos = ClienteRamo::orderBy('descricao')->get();
        $row = 2;
        foreach ($ramos as $ramo) {
            $notes->setCellValue('E' . $row, $ramo->descricao);
            $row++;
        }
    }

    $notes->getStyle('A1:E12')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    $notes->getStyle('A1:E12')->getAlignment()->setWrapText(true);

    return $spreadsheet;
}

function saveClienteTemplate(string $output, bool $includeRamo): void
{
    $dir = dirname($output);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $spreadsheet = buildClienteTemplate($includeRamo);
    $writer = new Xlsx($spreadsheet);
    $writer->save($output);
    $spreadsheet->disconnectWorksheets();
}

$baseDir = __DIR__ . '/../../public/assets/docs/modelos';

saveClienteTemplate($baseDir . '/modelo-importacao-clientes-a.xlsx', false);
saveClienteTemplate($baseDir . '/modelo-importacao-clientes-b.xlsx', true);

echo "Modelos gerados em: {$baseDir}\n";
