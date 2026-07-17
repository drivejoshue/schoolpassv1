<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Imports\StudentRosterImportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StudentImportController extends Controller
{
    private const SESSION_KEY = 'schoolpass.student_import.preview';

    public function __construct(
        private readonly StudentRosterImportService $importService,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless($user && $user->school_id, 403);

        $schoolId = (int) $user->school_id;
        $preview = $request->session()->get(self::SESSION_KEY);

        if (
            is_array($preview)
            && (
                (int) ($preview['school_id'] ?? 0) !== $schoolId
                || (int) ($preview['user_id'] ?? 0) !== (int) $user->id
            )
        ) {
            $this->forgetPreview($request);
            $preview = null;
        }

        return view('admin.imports.students', [
            'preview' => $preview,
            'context' => $this->importService->context($schoolId),
        ]);
    }

    public function preview(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx',
                'max:10240',
            ],
        ], [
            'file.required' => 'Selecciona un archivo Excel o CSV.',
            'file.file' => 'El archivo seleccionado no es válido.',
            'file.mimes' => 'El archivo debe ser Excel .xlsx o CSV.',
            'file.max' => 'El archivo no debe superar 10 MB.',
        ]);

        $user = $request->user();

        abort_unless($user && $user->school_id, 403);

        $schoolId = (int) $user->school_id;
        $userId = (int) $user->id;
        $uploadedFile = $request->file('file');

        $extension = strtolower(
            $uploadedFile->getClientOriginalExtension()
        );

        if (! in_array($extension, ['xlsx', 'csv', 'txt'], true)) {
            return back()->withErrors([
                'file' => 'Formato no permitido. Utiliza .xlsx o .csv.',
            ]);
        }

        $this->forgetPreview($request);

        $directory = sprintf(
            'private/school-imports/school_%d/user_%d',
            $schoolId,
            $userId
        );

        $storedName = Str::uuid()->toString().'.'.$extension;

        $storedPath = $uploadedFile->storeAs(
            $directory,
            $storedName,
            'local'
        );

        try {
            $absolutePath = Storage::disk('local')->path($storedPath);

            $result = $this->importService->preview(
                filePath: $absolutePath,
                schoolId: $schoolId,
            );

            $preview = [
                'token' => Str::random(48),
                'school_id' => $schoolId,
                'user_id' => $userId,
                'stored_path' => $storedPath,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'file_type' => $result['file_type'],
                'file_encoding' => $result['file_encoding'],
                'file_sha256' => hash_file('sha256', $absolutePath),
                'created_at' => now()->toIso8601String(),
                'summary' => $result['summary'],
                'rows' => array_slice($result['rows'], 0, 250),
                'rows_truncated' => count($result['rows']) > 250,
                'headers' => $result['headers'],
                'can_commit' => $result['can_commit'],
            ];

            $request->session()->put(self::SESSION_KEY, $preview);

            if (! $result['can_commit']) {
                return redirect()
                    ->route('admin.imports.students.index')
                    ->with(
                        'warning',
                        'La vista previa contiene errores. Corrige el archivo antes de importar.'
                    );
            }

            return redirect()
                ->route('admin.imports.students.index')
                ->with(
                    'success',
                    'Archivo validado. Revisa la vista previa y confirma la importación.'
                );
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPath);

            report($exception);

            return redirect()
                ->route('admin.imports.students.index')
                ->withErrors([
                    'file' => app()->environment('local')
                        ? $exception->getMessage()
                        : 'No se pudo leer el archivo.',
                ]);
        }
    }

    public function commit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'preview_token' => [
                'required',
                'string',
                'max:100',
            ],
        ]);

        $user = $request->user();

        abort_unless($user && $user->school_id, 403);

        $schoolId = (int) $user->school_id;
        $userId = (int) $user->id;
        $preview = $request->session()->get(self::SESSION_KEY);

        if (! is_array($preview)) {
            return redirect()
                ->route('admin.imports.students.index')
                ->withErrors([
                    'import' => 'La vista previa expiró. Carga nuevamente el archivo.',
                ]);
        }

        $validPreview = hash_equals(
            (string) ($preview['token'] ?? ''),
            (string) $data['preview_token']
        )
            && (int) ($preview['school_id'] ?? 0) === $schoolId
            && (int) ($preview['user_id'] ?? 0) === $userId
            && (bool) ($preview['can_commit'] ?? false);

        if (! $validPreview) {
            $this->forgetPreview($request);

            return redirect()
                ->route('admin.imports.students.index')
                ->withErrors([
                    'import' => 'La vista previa no es válida o no pertenece a esta escuela.',
                ]);
        }

        $storedPath = (string) ($preview['stored_path'] ?? '');

        if (
            $storedPath === ''
            || ! Storage::disk('local')->exists($storedPath)
        ) {
            $this->forgetPreview($request);

            return redirect()
                ->route('admin.imports.students.index')
                ->withErrors([
                    'import' => 'El archivo temporal ya no existe. Cárgalo nuevamente.',
                ]);
        }

        $absolutePath = Storage::disk('local')->path($storedPath);
        $currentHash = hash_file('sha256', $absolutePath);

        if (
            ! is_string($currentHash)
            || ! hash_equals(
                (string) ($preview['file_sha256'] ?? ''),
                $currentHash
            )
        ) {
            $this->forgetPreview($request);

            return redirect()
                ->route('admin.imports.students.index')
                ->withErrors([
                    'import' => 'El archivo temporal cambió y fue descartado por seguridad.',
                ]);
        }

        try {
            $result = $this->importService->commit(
                filePath: $absolutePath,
                schoolId: $schoolId,
                actorUserId: $userId,
            );

            $this->forgetPreview($request);

            return redirect()
                ->route('admin.imports.students.index')
                ->with('import_result', $result)
                ->with(
                    'success',
                    'Importación finalizada correctamente.'
                );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.imports.students.index')
                ->withErrors([
                    'import' => app()->environment('local')
                        ? $exception->getMessage()
                        : 'No se pudo completar la importación.',
                ]);
        }
    }

    public function discard(Request $request): RedirectResponse
    {
        $this->forgetPreview($request);

        return redirect()
            ->route('admin.imports.students.index')
            ->with('success', 'Vista previa descartada.');
    }

    private function forgetPreview(Request $request): void
    {
        $preview = $request->session()->get(self::SESSION_KEY);

        if (
            is_array($preview)
            && ! empty($preview['stored_path'])
        ) {
            Storage::disk('local')->delete(
                (string) $preview['stored_path']
            );
        }

        $request->session()->forget(self::SESSION_KEY);
    }
}