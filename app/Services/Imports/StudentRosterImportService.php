<?php

namespace App\Services\Imports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class StudentRosterImportService
{
    private const MAX_ROWS = 5000;

    public function expectedHeaders(): array
    {
        return [
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
    }

    public function context(int $schoolId): array
    {
        $activeCycle = DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where('status', '!=', 'closed')
            ->orderByDesc('starts_on')
            ->first();

        return [
            'active_cycle' => $activeCycle,

            'campuses' => DB::table('campuses')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),

            'levels' => DB::table('academic_levels')
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),

            'groups' => DB::table('school_groups as g')
                ->join('campuses as c', function ($join) use ($schoolId): void {
                    $join->on('c.id', '=', 'g.campus_id')
                        ->where('c.school_id', '=', $schoolId);
                })
                ->join('academic_levels as l', function ($join) use ($schoolId): void {
                    $join->on('l.id', '=', 'g.academic_level_id')
                        ->where('l.school_id', '=', $schoolId);
                })
                ->where('g.school_id', $schoolId)
                ->where('g.status', 'active')
                ->when(
                    $activeCycle,
                    fn ($query) => $query->where(
                        'g.academic_cycle_id',
                        $activeCycle->id
                    )
                )
                ->orderBy('c.name')
                ->orderBy('l.sort_order')
                ->orderBy('g.name')
                ->get([
                    'g.id',
                    'g.name',
                    'g.grade_label',
                    'c.name as campus_name',
                    'l.name as level_name',
                ]),
        ];
    }

    public function preview(
        string $filePath,
        int $schoolId
    ): array {
        $parsed = $this->parseFile($filePath);
        $context = $this->buildLookupContext($schoolId);

        $rows = [];
        $valid = 0;
        $invalid = 0;
        $studentCodesSeen = [];

        foreach ($parsed['rows'] as $index => $rawRow) {
            $line = $index + 2;

            $validation = $this->validateRow(
                row: $rawRow,
                line: $line,
                schoolId: $schoolId,
                context: $context,
                studentCodesSeen: $studentCodesSeen,
            );

            if ($validation['valid']) {
                $valid++;
            } else {
                $invalid++;
            }

            $rows[] = $validation;
        }

        return [
            'file_type' => $parsed['file_type'],
            'file_encoding' => $parsed['file_encoding'],
            'headers' => $parsed['headers'],
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'valid' => $valid,
                'invalid' => $invalid,
            ],
            'can_commit' => count($rows) > 0 && $invalid === 0,
        ];
    }

    public function commit(
        string $filePath,
        int $schoolId,
        int $actorUserId
    ): array {
        $preview = $this->preview($filePath, $schoolId);

        if (! $preview['can_commit']) {
            throw new RuntimeException(
                'El archivo contiene errores y no puede importarse.'
            );
        }

        return DB::transaction(
            function () use (
                $preview,
                $schoolId,
                $actorUserId
            ): array {
                $stats = [
                    'rows_processed' => 0,
                    'students_created' => 0,
                    'students_updated' => 0,
                    'guardians_created' => 0,
                    'guardians_updated' => 0,
                    'links_created' => 0,
                    'links_updated' => 0,
                ];

                foreach ($preview['rows'] as $validatedRow) {
                    $row = $validatedRow['normalized'];
                    $resolved = $validatedRow['resolved'];

                    $student = DB::table('students')
                        ->where('school_id', $schoolId)
                        ->where(
                            'student_code',
                            $row['student_code']
                        )
                        ->lockForUpdate()
                        ->first();

                    $studentPayload = [
                        'campus_id' => $resolved['campus_id'],
                        'current_group_id' => $resolved['group_id'],
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'status' => 'active',
                        'updated_at' => now(),
                    ];

                    if ($student) {
                        DB::table('students')
                            ->where('id', $student->id)
                            ->where('school_id', $schoolId)
                            ->update($studentPayload);

                        $studentId = (int) $student->id;
                        $stats['students_updated']++;
                    } else {
                        $studentId = (int) DB::table('students')
                            ->insertGetId([
                                'school_id' => $schoolId,
                                'campus_id' => $resolved['campus_id'],
                                'current_group_id' => $resolved['group_id'],
                                'user_id' => null,
                                'student_code' => $row['student_code'],
                                'first_name' => $row['first_name'],
                                'last_name' => $row['last_name'],
                                'photo_url' => null,
                                'status' => 'active',
                                'notes' => sprintf(
                                    'Importado desde archivo. Usuario administrador #%d',
                                    $actorUserId
                                ),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                        $stats['students_created']++;
                    }

                    if ($resolved['has_guardian']) {
                        $guardian = $this->findGuardian(
                            schoolId: $schoolId,
                            email: $row['guardian_email'],
                            phone: $row['guardian_phone'],
                            lock: true,
                        );

                        $guardianPayload = [
                            'first_name' => $row['guardian_first_name'],
                            'last_name' => $row['guardian_last_name'],
                            'phone' => $row['guardian_phone'] ?: null,
                            'email' => $row['guardian_email'] ?: null,
                            'status' => 'active',
                            'updated_at' => now(),
                        ];

                        if ($guardian) {
                            DB::table('guardians')
                                ->where('id', $guardian->id)
                                ->where('school_id', $schoolId)
                                ->update($guardianPayload);

                            $guardianId = (int) $guardian->id;
                            $stats['guardians_updated']++;
                        } else {
                            $guardianId = (int) DB::table('guardians')
                                ->insertGetId([
                                    'school_id' => $schoolId,
                                    'user_id' => null,
                                    ...$guardianPayload,
                                    'created_at' => now(),
                                ]);

                            $stats['guardians_created']++;
                        }

                        if ($row['is_primary']) {
                            DB::table('student_guardians')
                                ->where('student_id', $studentId)
                                ->where(
                                    'guardian_id',
                                    '!=',
                                    $guardianId
                                )
                                ->where('status', 'active')
                                ->update([
                                    'is_primary' => false,
                                    'updated_at' => now(),
                                ]);
                        }

                        $existingLink = DB::table(
                            'student_guardians'
                        )
                            ->where('student_id', $studentId)
                            ->where('guardian_id', $guardianId)
                            ->lockForUpdate()
                            ->first();

                        $linkPayload = [
                            'relationship' => $row['relationship'],
                            'can_view_attendance' =>
                                $row['can_view_attendance'],
                            'can_receive_notifications' =>
                                $row['can_receive_notifications'],
                            'can_authorize_exit' =>
                                $row['can_authorize_exit'],
                            'is_primary' => $row['is_primary'],
                            'status' => 'active',
                            'updated_at' => now(),
                        ];

                        if ($existingLink) {
                            DB::table('student_guardians')
                                ->where('id', $existingLink->id)
                                ->update($linkPayload);

                            $stats['links_updated']++;
                        } else {
                            DB::table('student_guardians')->insert([
                                'student_id' => $studentId,
                                'guardian_id' => $guardianId,
                                ...$linkPayload,
                                'created_at' => now(),
                            ]);

                            $stats['links_created']++;
                        }
                    }

                    $stats['rows_processed']++;
                }

                return $stats;
            },
            3
        );
    }

    private function parseFile(string $filePath): array
    {
        if (
            ! is_file($filePath)
            || ! is_readable($filePath)
        ) {
            throw new RuntimeException(
                'El archivo no existe o no puede leerse.'
            );
        }

        $extension = strtolower(
            pathinfo($filePath, PATHINFO_EXTENSION)
        );

        return match ($extension) {
            'xlsx' => $this->parseExcel($filePath),
            'csv', 'txt' => $this->parseCsv($filePath),
            default => throw new RuntimeException(
                'Formato no soportado. Utiliza .xlsx o .csv.'
            ),
        };
    }

    private function parseExcel(string $filePath): array
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No se pudo abrir el archivo Excel: '
                .$exception->getMessage(),
                previous: $exception
            );
        }

        try {
            $sheet = $spreadsheet->getSheet(0);

            $data = $sheet->toArray(
                null,
                true,
                true,
                false
            );

            if ($data === []) {
                throw new RuntimeException(
                    'El archivo Excel está vacío.'
                );
            }

            $rawHeaders = array_shift($data);

            if (! is_array($rawHeaders)) {
                throw new RuntimeException(
                    'No se pudo leer la fila de encabezados.'
                );
            }

            $headers = array_map(
                fn ($header): string => $this->normalizeHeader(
                    $this->normalizeUtf8((string) $header)
                ),
                $rawHeaders
            );

            $this->validateHeaders($headers);

            $rows = [];

            foreach ($data as $values) {
                if (count($rows) >= self::MAX_ROWS) {
                    throw new RuntimeException(
                        'El archivo supera el máximo de '
                        .self::MAX_ROWS
                        .' filas.'
                    );
                }

                $values = is_array($values) ? $values : [];

                $values = array_map(
                    fn ($value): string => $this->normalizeUtf8(
                        $this->scalarToString($value)
                    ),
                    $values
                );

                $values = array_pad(
                    $values,
                    count($headers),
                    ''
                );

                $values = array_slice(
                    $values,
                    0,
                    count($headers)
                );

                $row = array_combine($headers, $values);

                if (! is_array($row)) {
                    continue;
                }

                if ($this->rowHasContent($row)) {
                    $rows[] = $row;
                }
            }

            if ($rows === []) {
                throw new RuntimeException(
                    'El archivo Excel no contiene filas de datos.'
                );
            }

            return [
                'file_type' => 'xlsx',
                'file_encoding' => 'UTF-8',
                'headers' => $headers,
                'rows' => $rows,
            ];
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    private function parseCsv(string $filePath): array
    {
        $contents = file_get_contents($filePath);

        if ($contents === false || $contents === '') {
            throw new RuntimeException(
                'El archivo CSV está vacío o no puede leerse.'
            );
        }

        $encoding = $this->detectEncoding($contents);
        $utf8Contents = $this->convertToUtf8(
            $contents,
            $encoding
        );

        $utf8Contents = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $utf8Contents
        ) ?? $utf8Contents;

        $handle = fopen('php://temp', 'w+b');

        if ($handle === false) {
            throw new RuntimeException(
                'No se pudo preparar el archivo CSV.'
            );
        }

        fwrite($handle, $utf8Contents);
        rewind($handle);

        $firstLine = fgets($handle);

        if ($firstLine === false) {
            fclose($handle);

            throw new RuntimeException(
                'El archivo CSV está vacío.'
            );
        }

        $delimiter = $this->detectDelimiter($firstLine);

        rewind($handle);

        $rawHeaders = fgetcsv(
            $handle,
            null,
            $delimiter,
            '"',
            '\\'
        );

        if (! is_array($rawHeaders)) {
            fclose($handle);

            throw new RuntimeException(
                'No se pudo leer la fila de encabezados.'
            );
        }

        $headers = array_map(
            fn ($header): string => $this->normalizeHeader(
                $this->normalizeUtf8((string) $header)
            ),
            $rawHeaders
        );

        $this->validateHeaders($headers);

        $rows = [];

        while (
            ($values = fgetcsv(
                $handle,
                null,
                $delimiter,
                '"',
                '\\'
            )) !== false
        ) {
            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);

                throw new RuntimeException(
                    'El archivo supera el máximo de '
                    .self::MAX_ROWS
                    .' filas.'
                );
            }

            $values = array_map(
                fn ($value): string => $this->normalizeUtf8(
                    (string) $value
                ),
                $values
            );

            $values = array_pad(
                $values,
                count($headers),
                ''
            );

            $values = array_slice(
                $values,
                0,
                count($headers)
            );

            $row = array_combine($headers, $values);

            if (! is_array($row)) {
                continue;
            }

            if ($this->rowHasContent($row)) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        if ($rows === []) {
            throw new RuntimeException(
                'El archivo CSV no contiene filas de datos.'
            );
        }

        return [
            'file_type' => 'csv',
            'file_encoding' => $encoding,
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private function validateHeaders(array $headers): void
    {
        $expected = $this->expectedHeaders();

        $missing = array_values(
            array_diff($expected, $headers)
        );

        $duplicates = array_keys(
            array_filter(
                array_count_values($headers),
                fn (int $count): bool => $count > 1
            )
        );

        if ($missing !== []) {
            throw new RuntimeException(
                'Faltan columnas obligatorias: '
                .implode(', ', $missing)
                .'.'
            );
        }

        if ($duplicates !== []) {
            throw new RuntimeException(
                'Hay encabezados duplicados: '
                .implode(', ', $duplicates)
                .'.'
            );
        }
    }

    private function validateRow(
        array $row,
        int $line,
        int $schoolId,
        array $context,
        array &$studentCodesSeen,
    ): array {
        $normalized = $this->normalizeRow($row);

        $errors = [];
        $warnings = [];

        foreach (
            [
                'student_code',
                'first_name',
                'last_name',
                'campus',
                'level',
                'group',
            ] as $required
        ) {
            if ($normalized[$required] === '') {
                $errors[] = sprintf(
                    'La columna %s es obligatoria.',
                    $required
                );
            }
        }

        if (
            mb_strlen(
                $normalized['student_code'],
                'UTF-8'
            ) > 50
        ) {
            $errors[] =
                'La matrícula no puede superar 50 caracteres.';
        }

        if (
            mb_strlen(
                $normalized['first_name'],
                'UTF-8'
            ) > 100
            || mb_strlen(
                $normalized['last_name'],
                'UTF-8'
            ) > 150
        ) {
            $errors[] =
                'El nombre o apellidos superan la longitud permitida.';
        }

        $codeKey = Str::lower(
            $normalized['student_code']
        );

        if (isset($studentCodesSeen[$codeKey])) {
            $warnings[] =
                'La matrícula aparece en más de una fila. Se procesará para permitir varios tutores.';
        }

        $studentCodesSeen[$codeKey] = true;

        $campus = $context['campuses'][
            $this->lookupKey($normalized['campus'])
        ] ?? null;

        $level = $context['levels'][
            $this->lookupKey($normalized['level'])
        ] ?? null;

        if (! $campus) {
            $errors[] =
                'El plantel no existe o no pertenece a esta escuela.';
        }

        if (! $level) {
            $errors[] =
                'El nivel académico no existe o no pertenece a esta escuela.';
        }

        if (! $context['active_cycle']) {
            $errors[] =
                'La escuela no tiene un ciclo académico activo.';
        }

        $group = null;

        if (
            $campus
            && $level
            && $context['active_cycle']
        ) {
            $groupKey = implode('|', [
                $campus->id,
                $level->id,
                $context['active_cycle']->id,
                $this->lookupKey(
                    $normalized['group']
                ),
            ]);

            $group = $context['groups'][$groupKey] ?? null;

            if (! $group) {
                $errors[] =
                    'El grupo no existe para el plantel, nivel y ciclo activo indicados.';
            } elseif (
                $normalized['grade_label'] !== ''
                && $this->lookupKey(
                    (string) $group->grade_label
                ) !== $this->lookupKey(
                    $normalized['grade_label']
                )
            ) {
                $errors[] =
                    'El grado no coincide con el grupo seleccionado.';
            }
        }

        $guardianFields = [
            $normalized['guardian_first_name'],
            $normalized['guardian_last_name'],
            $normalized['guardian_phone'],
            $normalized['guardian_email'],
            $normalized['relationship'],
        ];

        $hasGuardian = collect($guardianFields)
            ->contains(
                fn ($value): bool => $value !== ''
            );

        if ($hasGuardian) {
            if (
                $normalized['guardian_first_name'] === ''
                || $normalized['guardian_last_name'] === ''
            ) {
                $errors[] =
                    'El tutor requiere nombre y apellidos.';
            }

            if (
                $normalized['guardian_email'] === ''
                && $normalized['guardian_phone'] === ''
            ) {
                $errors[] =
                    'El tutor requiere correo o teléfono para evitar duplicados.';
            }

            if (
                $normalized['guardian_email'] !== ''
                && ! filter_var(
                    $normalized['guardian_email'],
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                $errors[] =
                    'El correo del tutor no es válido.';
            }

            if ($normalized['relationship'] === '') {
                $errors[] =
                    'La relación del tutor con el alumno es obligatoria.';
            }

            $guardianByEmail =
                $normalized['guardian_email'] !== ''
                    ? $this->findGuardian(
                        $schoolId,
                        $normalized['guardian_email'],
                        '',
                        false
                    )
                    : null;

            $guardianByPhone =
                $normalized['guardian_phone'] !== ''
                    ? $this->findGuardian(
                        $schoolId,
                        '',
                        $normalized['guardian_phone'],
                        false
                    )
                    : null;

            if (
                $guardianByEmail
                && $guardianByPhone
                && (int) $guardianByEmail->id
                    !== (int) $guardianByPhone->id
            ) {
                $errors[] =
                    'El correo y teléfono pertenecen a tutores distintos dentro de esta escuela.';
            }
        }

        $existingStudent = null;

        if ($normalized['student_code'] !== '') {
            $existingStudent = DB::table('students')
                ->where('school_id', $schoolId)
                ->where(
                    'student_code',
                    $normalized['student_code']
                )
                ->first();
        }

        return [
            'line' => $line,
            'valid' => $errors === [],
            'action' => $existingStudent
                ? 'update'
                : 'create',
            'normalized' => $normalized,
            'resolved' => [
                'campus_id' => $campus?->id,
                'level_id' => $level?->id,
                'cycle_id' =>
                    $context['active_cycle']?->id,
                'group_id' => $group?->id,
                'has_guardian' => $hasGuardian,
            ],
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($this->expectedHeaders() as $header) {
            $normalized[$header] = trim(
                $this->normalizeUtf8(
                    (string) ($row[$header] ?? '')
                )
            );
        }

        $normalized['student_code'] = mb_strtoupper(
            $normalized['student_code'],
            'UTF-8'
        );

        $normalized['guardian_email'] = mb_strtolower(
            $normalized['guardian_email'],
            'UTF-8'
        );

        $normalized['guardian_phone'] =
            preg_replace(
                '/[^0-9+]/',
                '',
                $normalized['guardian_phone']
            ) ?? '';

        $normalized['is_primary'] = $this->toBool(
            $normalized['is_primary'],
            false
        );

        $normalized['can_view_attendance'] = $this->toBool(
            $normalized['can_view_attendance'],
            true
        );

        $normalized['can_receive_notifications'] =
            $this->toBool(
                $normalized['can_receive_notifications'],
                true
            );

        $normalized['can_authorize_exit'] = $this->toBool(
            $normalized['can_authorize_exit'],
            false
        );

        return $normalized;
    }

    private function buildLookupContext(
        int $schoolId
    ): array {
        $activeCycle = DB::table('academic_cycles')
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where('status', '!=', 'closed')
            ->orderByDesc('starts_on')
            ->first();

        $campuses = DB::table('campuses')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->get()
            ->keyBy(
                fn (object $row): string =>
                    $this->lookupKey($row->name)
            )
            ->all();

        $levels = DB::table('academic_levels')
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->get()
            ->keyBy(
                fn (object $row): string =>
                    $this->lookupKey($row->name)
            )
            ->all();

        $groups = [];

        if ($activeCycle) {
            $groupRows = DB::table('school_groups')
                ->where('school_id', $schoolId)
                ->where(
                    'academic_cycle_id',
                    $activeCycle->id
                )
                ->where('status', 'active')
                ->get();

            foreach ($groupRows as $group) {
                $key = implode('|', [
                    $group->campus_id,
                    $group->academic_level_id,
                    $group->academic_cycle_id,
                    $this->lookupKey($group->name),
                ]);

                $groups[$key] = $group;
            }
        }

        return [
            'active_cycle' => $activeCycle,
            'campuses' => $campuses,
            'levels' => $levels,
            'groups' => $groups,
        ];
    }

    private function findGuardian(
        int $schoolId,
        string $email,
        string $phone,
        bool $lock,
    ): ?object {
        if ($email === '' && $phone === '') {
            return null;
        }

        $query = DB::table('guardians')
            ->where('school_id', $schoolId)
            ->where(
                function ($subquery) use (
                    $email,
                    $phone
                ): void {
                    if ($email !== '') {
                        $subquery->whereRaw(
                            'LOWER(email) = ?',
                            [mb_strtolower($email, 'UTF-8')]
                        );
                    }

                    if ($phone !== '') {
                        if ($email !== '') {
                            $subquery->orWhere(
                                'phone',
                                $phone
                            );
                        } else {
                            $subquery->where(
                                'phone',
                                $phone
                            );
                        }
                    }
                }
            );

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function normalizeHeader(
        string $header
    ): string {
        $header = $this->normalizeUtf8($header);

        $header = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $header
        ) ?? $header;

        return Str::of($header)
            ->trim()
            ->lower()
            ->replace([' ', '-'], '_')
            ->toString();
    }

    private function normalizeUtf8(
        string $value
    ): string {
        $value = str_replace("\0", '', $value);

        if ($value === '') {
            return '';
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $encoding = mb_detect_encoding(
            $value,
            [
                'Windows-1252',
                'ISO-8859-1',
                'UTF-8',
            ],
            true
        );

        if ($encoding === false) {
            $encoding = 'Windows-1252';
        }

        return mb_convert_encoding(
            $value,
            'UTF-8',
            $encoding
        );
    }

    private function detectEncoding(
        string $contents
    ): string {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            return 'UTF-8-BOM';
        }

        if (mb_check_encoding($contents, 'UTF-8')) {
            return 'UTF-8';
        }

        $detected = mb_detect_encoding(
            $contents,
            [
                'Windows-1252',
                'ISO-8859-1',
            ],
            true
        );

        return $detected ?: 'Windows-1252';
    }

    private function convertToUtf8(
        string $contents,
        string $encoding
    ): string {
        if ($encoding === 'UTF-8-BOM') {
            return preg_replace(
                '/^\xEF\xBB\xBF/',
                '',
                $contents
            ) ?? $contents;
        }

        if ($encoding === 'UTF-8') {
            return $contents;
        }

        return mb_convert_encoding(
            $contents,
            'UTF-8',
            $encoding
        );
    }

    private function scalarToString(
        mixed $value
    ): string {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (
            is_string($value)
            || is_int($value)
            || is_float($value)
        ) {
            return (string) $value;
        }

        return '';
    }

    private function rowHasContent(
        array $row
    ): bool {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function lookupKey(
        string $value
    ): string {
        return Str::of(
            $this->normalizeUtf8($value)
        )
            ->lower()
            ->ascii()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function detectDelimiter(
        string $line
    ): string {
        $delimiters = [
            ',',
            ';',
            "\t",
            '|',
        ];

        $counts = [];

        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = substr_count(
                $line,
                $delimiter
            );
        }

        arsort($counts);

        $delimiter = array_key_first($counts);

        return ($counts[$delimiter] ?? 0) > 0
            ? $delimiter
            : ',';
    }

    private function toBool(
        string $value,
        bool $default
    ): bool {
        if ($value === '') {
            return $default;
        }

        $value = mb_strtolower(
            trim($value),
            'UTF-8'
        );

        return match ($value) {
            '1',
            'true',
            'si',
            'sí',
            'yes',
            'y',
            'x' => true,

            '0',
            'false',
            'no',
            'n' => false,

            default => $default,
        };
    }
}