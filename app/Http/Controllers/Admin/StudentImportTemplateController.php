<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StudentImportTemplateController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $user = $request->user();

        abort_unless(
            $user
            && $user->school_id
            && in_array(
                $user->role,
                ['superadmin', 'school_admin', 'director'],
                true
            ),
            403
        );

        $spreadsheet = new Spreadsheet();

        $this->buildImportSheet($spreadsheet);
        $this->buildInstructionsSheet($spreadsheet);

        $spreadsheet->setActiveSheetIndex(0);

        $schoolId = (int) $user->school_id;

        $directory = storage_path(
            'app/private/import-templates/school_'.$schoolId
        );

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $fileName = 'SchoolPass_Plantilla_Alumnos_'
            .now()->format('Ymd_His')
            .'.xlsx';

        $filePath = $directory.DIRECTORY_SEPARATOR.$fileName;

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        $spreadsheet->disconnectWorksheets();

        return response()
            ->download(
                $filePath,
                'SchoolPass_Plantilla_Importacion_Alumnos.xlsx',
                [
                    'Content-Type' =>
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]
            )
            ->deleteFileAfterSend(true);
    }

    private function buildImportSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Importar alumnos');

        $headers = [
            'student_code',
            'first_name',
            'last_name',
            'campus',
            'level',
            'grade_label',
            'group',
            'guardian_first_name',
            'guardian_last_name',
            'guardian_phone',
            'guardian_email',
            'relationship',
            'is_primary',
            'can_view_attendance',
            'can_receive_notifications',
            'can_authorize_exit',
        ];

        $demo = [
            'A0100',
            'José',
            'Martínez Ruiz',
            'Campus Centro',
            'Primaria',
            '2',
            'Primaria 2B',
            'Ana',
            'Ruiz López',
            '2291112233',
            'ana.ruiz@example.com',
            'madre',
            1,
            1,
            1,
            1,
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray($demo, null, 'A2');

        $sheet->freezePane('A2');

        $sheet->setAutoFilter('A1:P1');

        $sheet->getStyle('A1:P1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => [
                    'rgb' => 'FFFFFF',
                ],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '1D4ED8',
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => [
                        'rgb' => 'BFDBFE',
                    ],
                ],
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(38);

        $widths = [
            'A' => 18,
            'B' => 20,
            'C' => 26,
            'D' => 24,
            'E' => 20,
            'F' => 18,
            'G' => 24,
            'H' => 24,
            'I' => 26,
            'J' => 20,
            'K' => 32,
            'L' => 18,
            'M' => 16,
            'N' => 24,
            'O' => 28,
            'P' => 24,
        ];

        foreach ($widths as $column => $width) {
            $sheet
                ->getColumnDimension($column)
                ->setWidth($width);
        }

        /*
         * Teléfono y matrícula como texto para conservar ceros iniciales.
         */
        $sheet
            ->getStyle('A2:A2000')
            ->getNumberFormat()
            ->setFormatCode('@');

        $sheet
            ->getStyle('J2:J2000')
            ->getNumberFormat()
            ->setFormatCode('@');

        $sheet
            ->getStyle('K2:K2000')
            ->getNumberFormat()
            ->setFormatCode('@');

        /*
         * Lista para parentesco.
         */
        for ($row = 2; $row <= 2000; $row++) {
            $relationshipValidation = new DataValidation();

            $relationshipValidation
                ->setType(DataValidation::TYPE_LIST)
                ->setErrorStyle(DataValidation::STYLE_STOP)
                ->setAllowBlank(false)
                ->setShowInputMessage(true)
                ->setShowErrorMessage(true)
                ->setShowDropDown(true)
                ->setErrorTitle('Parentesco inválido')
                ->setError(
                    'Selecciona madre, padre, tutor, abuela, abuelo u otro.'
                )
                ->setPromptTitle('Parentesco')
                ->setPrompt('Selecciona el parentesco del tutor.')
                ->setFormula1(
                    '"madre,padre,tutor,abuela,abuelo,otro"'
                );

            $sheet
                ->getCell('L'.$row)
                ->setDataValidation(
                    clone $relationshipValidation
                );
        }

        /*
         * Validaciones 0 / 1 para permisos.
         */
        foreach (['M', 'N', 'O', 'P'] as $column) {
            for ($row = 2; $row <= 2000; $row++) {
                $booleanValidation = new DataValidation();

                $booleanValidation
                    ->setType(DataValidation::TYPE_LIST)
                    ->setErrorStyle(DataValidation::STYLE_STOP)
                    ->setAllowBlank(false)
                    ->setShowInputMessage(true)
                    ->setShowErrorMessage(true)
                    ->setShowDropDown(true)
                    ->setErrorTitle('Valor inválido')
                    ->setError('Usa solamente 1 para Sí o 0 para No.')
                    ->setPromptTitle('Permiso')
                    ->setPrompt('Selecciona 1 para Sí o 0 para No.')
                    ->setFormula1('"1,0"');

                $sheet
                    ->getCell($column.$row)
                    ->setDataValidation(
                        clone $booleanValidation
                    );
            }
        }

        $sheet->getStyle('A2:P2000')->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle('A2:P2')->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'EFF6FF',
                ],
            ],
        ]);

        $sheet->getStyle('A1:P2000')->applyFromArray([
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_HAIR,
                    'color' => [
                        'rgb' => 'E2E8F0',
                    ],
                ],
            ],
        ]);
    }

    private function buildInstructionsSheet(
        Spreadsheet $spreadsheet
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Instrucciones');

        $sheet->mergeCells('A1:F1');

        $sheet->setCellValue(
            'A1',
            'SchoolPass · Plantilla de importación de alumnos'
        );

        $sheet->getStyle('A1:F1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => [
                    'rgb' => 'FFFFFF',
                ],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => '0F172A',
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(34);

        $instructions = [
            [
                'Paso',
                'Instrucción',
                'Detalle',
                'Obligatorio',
                'Ejemplo',
                'Notas',
            ],
            [
                1,
                'No cambies los encabezados',
                'El importador reconoce exactamente los nombres de la fila 1.',
                'Sí',
                'student_code',
                'Puedes borrar la fila de ejemplo antes de importar.',
            ],
            [
                2,
                'Una fila por alumno y tutor',
                'Para dos tutores, repite los datos del alumno en otra fila y cambia los datos del tutor.',
                'Sí',
                'A0100',
                'La matrícula evita duplicar al alumno.',
            ],
            [
                3,
                'Usa nombres configurados',
                'Plantel, nivel y grupo deben coincidir con los registrados en SchoolPass.',
                'Sí',
                'Campus Centro',
                'No se crearán grupos automáticamente.',
            ],
            [
                4,
                'Permisos',
                'Usa 1 para Sí y 0 para No.',
                'Sí',
                '1',
                'Aplica a tutor principal, asistencia, avisos y salidas.',
            ],
            [
                5,
                'Tutor',
                'Incluye teléfono, correo o ambos.',
                'Recomendado',
                'ana@example.com',
                'La cuenta para la app se crea después.',
            ],
            [
                6,
                'Alumno demo',
                'La segunda fila contiene un ejemplo.',
                'No',
                'José Martínez Ruiz',
                'Bórralo antes de subir el archivo si no deseas importarlo.',
            ],
        ];

        $sheet->fromArray($instructions, null, 'A3');

        $sheet->getStyle('A3:F3')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => [
                    'rgb' => '1E3A8A',
                ],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'DBEAFE',
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle('A3:F9')->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);

        $widths = [
            'A' => 10,
            'B' => 28,
            'C' => 52,
            'D' => 16,
            'E' => 30,
            'F' => 44,
        ];

        foreach ($widths as $column => $width) {
            $sheet
                ->getColumnDimension($column)
                ->setWidth($width);
        }

        for ($row = 3; $row <= 9; $row++) {
            $sheet->getRowDimension($row)->setRowHeight(48);
        }

        $sheet->freezePane('A4');

        $dictionary = [
            ['Columna', 'Descripción', 'Ejemplo'],
            [
                'student_code',
                'Matrícula única del alumno dentro de la escuela.',
                'A0100',
            ],
            [
                'first_name',
                'Nombre o nombres del alumno.',
                'José',
            ],
            [
                'last_name',
                'Apellidos del alumno.',
                'Martínez Ruiz',
            ],
            [
                'campus',
                'Nombre exacto del plantel.',
                'Campus Centro',
            ],
            [
                'level',
                'Nombre exacto del nivel académico.',
                'Primaria',
            ],
            [
                'grade_label',
                'Grado o etiqueta académica.',
                '2',
            ],
            [
                'group',
                'Nombre exacto del grupo.',
                'Primaria 2B',
            ],
            [
                'guardian_first_name',
                'Nombre del tutor.',
                'Ana',
            ],
            [
                'guardian_last_name',
                'Apellidos del tutor.',
                'Ruiz López',
            ],
            [
                'guardian_phone',
                'Teléfono del tutor.',
                '2291112233',
            ],
            [
                'guardian_email',
                'Correo electrónico del tutor.',
                'ana.ruiz@example.com',
            ],
            [
                'relationship',
                'Parentesco del tutor.',
                'madre',
            ],
            [
                'is_primary',
                '1 si es tutor principal, 0 si no.',
                '1',
            ],
            [
                'can_view_attendance',
                '1 permite consultar asistencia.',
                '1',
            ],
            [
                'can_receive_notifications',
                '1 permite recibir notificaciones.',
                '1',
            ],
            [
                'can_authorize_exit',
                '1 permite autorizar salidas.',
                '1',
            ],
        ];

        $sheet->fromArray($dictionary, null, 'A12');

        $sheet->getStyle('A12:C12')->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'rgb' => 'E2E8F0',
                ],
            ],
        ]);

        $sheet->getStyle('A12:C29')->getAlignment()
            ->setWrapText(true)
            ->setVertical(Alignment::VERTICAL_TOP);
    }
}