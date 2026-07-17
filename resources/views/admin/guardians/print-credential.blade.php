<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Credencial de tutor | SchoolPass</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 32px;
            background: #eef1f5;
            color: #182433;
            font-family: Arial, Helvetica, sans-serif;
        }

        .toolbar {
            max-width: 760px;
            margin: 0 auto 18px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .button {
            border: 1px solid #206bc4;
            border-radius: 8px;
            padding: 10px 16px;
            background: #206bc4;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }

        .button.secondary {
            background: #fff;
            color: #182433;
            border-color: #cbd5e1;
        }

        .sheet {
            width: 760px;
            max-width: 100%;
            margin: 0 auto;
            background: #fff;
            border-radius: 18px;
            padding: 34px;
            box-shadow: 0 12px 35px rgba(24, 36, 51, .12);
        }

        .header {
            display: flex;
            justify-content: space-between;
            gap: 24px;
            align-items: flex-start;
            border-bottom: 1px solid #dce1e7;
            padding-bottom: 22px;
        }

        .brand {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #206bc4;
        }

        h1 {
            margin: 6px 0 4px;
            font-size: 28px;
        }

        .school {
            color: #667382;
        }

        .credential {
            display: grid;
            grid-template-columns: 1fr 290px;
            gap: 30px;
            padding-top: 28px;
        }

        .identity {
            display: flex;
            gap: 18px;
            align-items: center;
        }

        .photo {
            width: 130px;
            height: 130px;
            border-radius: 16px;
            object-fit: cover;
            border: 1px solid #dce1e7;
        }

        .name {
            margin: 0;
            font-size: 25px;
        }

        .meta {
            margin-top: 8px;
            color: #667382;
            line-height: 1.55;
        }

        .qr-panel {
            text-align: center;
            border: 1px solid #dce1e7;
            border-radius: 16px;
            padding: 18px;
        }

        .guardian-qr-box {
            min-height: 250px;
            display: grid;
            place-items: center;
        }

        .qr-caption {
            font-size: 12px;
            color: #667382;
            margin-top: 8px;
        }

        .students {
            margin-top: 28px;
            border-top: 1px solid #dce1e7;
            padding-top: 22px;
        }

        .student {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 10px 0;
            border-bottom: 1px solid #edf0f3;
        }

        .permissions {
            font-size: 13px;
            color: #667382;
            text-align: right;
        }

        .notice {
            margin-top: 24px;
            padding: 14px;
            border-radius: 10px;
            background: #f5f8fc;
            color: #4d5966;
            font-size: 13px;
        }

        @media (max-width: 700px) {
            body {
                padding: 12px;
            }

            .sheet {
                padding: 22px;
            }

            .credential {
                grid-template-columns: 1fr;
            }

            .header,
            .identity {
                flex-direction: column;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 12mm;
            }

            body {
                padding: 0;
                background: #fff;
            }

            .toolbar {
                display: none;
            }

            .sheet {
                width: 100%;
                box-shadow: none;
                border: 1px solid #dce1e7;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a
            class="button secondary"
            href="{{ route('admin.guardians.show', $guardianRow->id) }}"
        >
            Volver
        </a>

        <button class="button" type="button" onclick="window.print()">
            Imprimir
        </button>
    </div>

    <main class="sheet">
        <header class="header">
            <div>
                <div class="brand">SchoolPass · Credencial de tutor</div>
                <h1>{{ trim($guardianRow->first_name.' '.$guardianRow->last_name) }}</h1>
                <div class="school">{{ $school->name ?? 'Institución educativa' }}</div>
            </div>

            <div>
                <strong>Emitida:</strong>
                {{ \Illuminate\Support\Carbon::parse($credentialRow->issued_at)->format('d/m/Y H:i') }}
                <br>
                <strong>Vigencia:</strong>
                {{ $credentialRow->expires_at
                    ? \Illuminate\Support\Carbon::parse($credentialRow->expires_at)->format('d/m/Y H:i')
                    : 'Sin vencimiento' }}
            </div>
        </header>

        <section class="credential">
            <div>
                <div class="identity">
                    <img
                        src="{{ $guardianRow->photo_url }}"
                        alt="Fotografía del tutor"
                        class="photo"
                    >

                    <div>
                        <h2 class="name">
                            {{ trim($guardianRow->first_name.' '.$guardianRow->last_name) }}
                        </h2>

                        <div class="meta">
                            {{ $guardianRow->phone ?? 'Sin teléfono' }}<br>
                            {{ $guardianRow->email ?? 'Sin correo' }}<br>
                            Estado: tutor autorizado
                        </div>
                    </div>
                </div>

                <div class="students">
                    <strong>Alumnos vinculados</strong>

                    @forelse($students as $student)
                        <div class="student">
                            <div>
                                <strong>
                                    {{ trim($student->first_name.' '.$student->last_name) }}
                                </strong>
                                <div class="meta">
                                    {{ $student->student_code }}
                                    · {{ $student->group_name ?? 'Sin grupo' }}
                                    · {{ ucfirst($student->relationship) }}
                                </div>
                            </div>

                            <div class="permissions">
                                {{ $student->can_drop_off ? 'Entrega' : '' }}
                                {{ $student->can_drop_off && $student->can_pick_up ? ' / ' : '' }}
                                {{ $student->can_pick_up ? 'Recogida' : '' }}
                            </div>
                        </div>
                    @empty
                        <div class="meta">Sin alumnos vinculados.</div>
                    @endforelse
                </div>
            </div>

            <div class="qr-panel">
                <div
                    class="guardian-qr-box"
                    data-qr-payload="{{ $credentialRow->public_code }}"
                    data-qr-size="250"
                >
                    Generando QR…
                </div>

                <div class="qr-caption">
                    Presenta este código en el acceso escolar.
                </div>
            </div>
        </section>

        <div class="notice">
            Esta credencial es personal. La escuela puede revocarla en cualquier momento.
            La autorización final depende de los permisos y vigencias registrados para cada alumno.
        </div>
    </main>

    @vite('resources/js/guardian-credential.js')
</body>
</html>
