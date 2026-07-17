<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Credenciales SchoolPass</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        :root {
            --sp-border: #d8dee9;
            --sp-text: #101828;
            --sp-muted: #667085;
            --sp-soft: #e7f0ff;
            --sp-primary: #0d6efd;
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: #f3f4f6;
            margin: 0;
        }

        .print-toolbar {
            padding: 16px 18px;
            background: white;
            border-bottom: 1px solid #ddd;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .no-print {
            display: block;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(360px, 1fr));
            gap: 14px;
            padding: 18px;
        }

        .print-single .cards-grid {
            grid-template-columns: 430px;
            justify-content: center;
        }

        .credential-card {
            width: 100%;
            min-height: 260px;
            background: white;
            border: 1px solid var(--sp-border);
            border-radius: 14px;
            padding: 16px;
            display: grid;
            grid-template-columns: 1fr 150px;
            gap: 16px;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .credential-title {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: var(--sp-muted);
            font-weight: 700;
            line-height: 1.35;
        }

        .student-name {
            font-size: 22px;
            line-height: 1.1;
            font-weight: 800;
            color: var(--sp-text);
            margin-top: 10px;
        }

        .student-meta {
            color: #475467;
            margin-top: 8px;
            font-size: 14px;
        }

        .student-photo {
            width: 72px;
            height: 72px;
            border-radius: 14px;
            background-color: var(--sp-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--sp-primary);
            font-size: 28px;
            font-weight: 800;
            margin-top: 16px;
            overflow: hidden;
            border: 1px solid var(--sp-border);
        }

        .student-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .qr-box {
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .qr-canvas {
            width: 145px;
            height: 145px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .qr-canvas img {
            width: 145px;
            height: 145px;
            display: block;
        }

        .qr-code-text {
            margin-top: 8px;
            font-size: 11px;
            color: var(--sp-muted);
            word-break: break-all;
            max-width: 145px;
        }

        .credential-footer {
            margin-top: 20px;
            font-size: 11px;
            color: var(--sp-muted);
            max-width: 190px;
        }

        .empty-state {
            margin: 32px;
            padding: 32px;
            background: white;
            border: 1px solid var(--sp-border);
            border-radius: 14px;
            text-align: center;
            color: var(--sp-muted);
        }

        @page {
            size: letter;
            margin: 10mm;
        }

        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body {
                background: white !important;
                margin: 0 !important;
            }

            .no-print,
            .print-toolbar,
            button,
            .btn,
            a.btn {
                display: none !important;
                visibility: hidden !important;
            }

            .cards-grid {
                padding: 0 !important;
                gap: 10px !important;
                grid-template-columns: repeat(2, 1fr) !important;
            }

            .print-single .cards-grid {
                grid-template-columns: 430px !important;
                justify-content: center !important;
            }

            .credential-card {
                border: 1px solid #999 !important;
                border-radius: 8px !important;
                box-shadow: none !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            .empty-state {
                display: none !important;
            }
        }
    </style>
</head>

<body class="{{ ($isIndividual ?? false) ? 'print-single' : '' }}">
    <div class="print-toolbar no-print d-flex justify-content-between align-items-center">
        <div>
            <div class="fw-bold">Credenciales SchoolPass</div>

            <div class="text-secondary">
                @if($isIndividual ?? false)
                    Impresión individual
                @else
                    {{ $groupRow ? $groupRow->name : 'Todos los grupos' }} · {{ $students->count() }} credencial(es)
                @endif
            </div>
        </div>

        <div class="btn-list">
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="ti ti-printer me-1"></i>
                Imprimir
            </button>

            <a href="{{ route('admin.credentials.index') }}" class="btn btn-outline-secondary">
                Volver
            </a>
        </div>
    </div>

    @if($students->isEmpty())
        <div class="empty-state">
            No hay credenciales activas para imprimir.
        </div>
    @else
        <div class="cards-grid">
            @foreach($students as $student)
                <div class="credential-card">
                    <div>
                        <div class="credential-title">
                            SchoolPass ·<br>
                            Credencial escolar
                        </div>

                        <div class="student-name">
                            {{ $student->first_name }} {{ $student->last_name }}
                        </div>

                        <div class="student-meta">
                            Matrícula: <strong>{{ $student->student_code }}</strong>
                        </div>

                        <div class="student-meta">
                            Grupo: <strong>{{ $student->group_name }}</strong>
                        </div>

                        <div class="student-meta">
                            Nivel: <strong>{{ $student->level_name ?? '—' }}</strong>
                        </div>

                        @if($student->photo_url)
                            <div class="student-photo">
                                <img
                                    src="{{ asset($student->photo_url) }}"
                                    alt="Foto de {{ $student->first_name }} {{ $student->last_name }}"
                                >
                            </div>
                        @else
                            <div class="student-photo">
                                {{ strtoupper(substr($student->first_name, 0, 1)) }}
                            </div>
                        @endif

                        <div class="credential-footer">
                            Uso interno para control de acceso escolar.
                        </div>
                    </div>

                    <div class="qr-box">
                        <div
                            class="qr-canvas"
                            data-token="{{ $student->public_code }}"
                        ></div>

                        <div class="qr-code-text">
                            {{ $student->public_code }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', async function () {
            const elements = document.querySelectorAll('.qr-canvas');

            for (const element of elements) {
                const token = element.dataset.token;

                if (!token || !window.QRCode) {
                    element.innerHTML = '<div class="text-danger small">QR no disponible</div>';
                    continue;
                }

                try {
                    const url = await window.QRCode.toDataURL(token, {
                        width: 180,
                        margin: 1,
                        errorCorrectionLevel: 'M'
                    });

                    element.innerHTML = `<img src="${url}" alt="QR ${token}">`;
                } catch (error) {
                    element.innerHTML = '<div class="text-danger small">Error QR</div>';
                }
            }
        });
    </script>
</body>
</html>