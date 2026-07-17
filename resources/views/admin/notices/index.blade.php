@extends('layouts.app')

@section('title', 'Avisos escolares | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Avisos escolares')

@section('topbar-actions')
    <a href="{{ route('admin.notices.create') }}" class="btn btn-primary btn-sm">
        <i class="ti ti-plus me-1"></i>
        Nuevo aviso
    </a>
@endsection

@section('content')
    @if(session('success'))
        <div class="alert alert-success">
            <i class="ti ti-check me-1"></i>
            {{ session('success') }}
        </div>
    @endif

    <div class="row row-cards mb-3">
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="text-muted small">Total</div>
                    <div class="h2 m-0">{{ $stats['total'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="text-muted small">Publicados</div>
                    <div class="h2 m-0 text-success">{{ $stats['published'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="text-muted small">Borradores</div>
                    <div class="h2 m-0 text-warning">{{ $stats['draft'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-3">
                    <div class="text-muted small">Histórico</div>
                    <div class="h2 m-0 text-muted">{{ $stats['archived'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.notices.index') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label">Buscar</label>
                    <input
                        type="text"
                        name="q"
                        value="{{ $search }}"
                        class="form-control"
                        placeholder="Buscar aviso..."
                    >
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="published" @selected($status === 'published')>Publicados</option>
                        <option value="draft" @selected($status === 'draft')>Borradores</option>
                        <option value="archived" @selected($status === 'archived')>Histórico</option>
                    </select>
                </div>

                <div class="col-12 col-md-auto">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ti ti-filter me-1"></i>
                        Filtrar
                    </button>
                </div>

                <div class="col-12 col-md-auto">
                    <a href="{{ route('admin.notices.index') }}" class="btn btn-outline-secondary w-100">
                        Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0">
            <div>
                <h3 class="card-title mb-0">Comunicados</h3>
                <div class="text-muted small">
                    Avisos para toda la escuela, grupos, alumnos o tutores específicos.
                </div>
            </div>
        </div>

        <div class="list-group list-group-flush">
            @forelse($notices as $notice)
                <div class="list-group-item {{ $notice->status === 'archived' ? 'opacity-50' : '' }}">
                    <div class="row align-items-start g-3">
                        <div class="col">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <div class="fw-semibold">
                                    {{ $notice->title }}
                                </div>

                                @if($notice->status === 'published')
                                    <span class="badge bg-green-lt text-green">Publicado</span>
                                @elseif($notice->status === 'draft')
                                    <span class="badge bg-yellow-lt text-yellow">Borrador</span>
                                @else
                                    <span class="badge bg-secondary-lt text-secondary">Histórico</span>
                                @endif

                                @if($notice->priority === 'urgent')
                                    <span class="badge bg-red-lt text-red">Urgente</span>
                                @elseif($notice->priority === 'important')
                                    <span class="badge bg-blue-lt text-blue">Importante</span>
                                @else
                                    <span class="badge bg-secondary-lt text-secondary">Normal</span>
                                @endif
                            </div>

                            @if($notice->subtitle)
                                <div class="text-muted small mb-1">
                                    {{ $notice->subtitle }}
                                </div>
                            @endif

                            <div class="text-muted small">
                                {{ \Illuminate\Support\Str::limit($notice->body, 180) }}
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-2">
                                @if($notice->show_as_modal)
                                    <span class="badge bg-blue-lt text-blue">
                                        <i class="ti ti-window me-1"></i>
                                        Modal
                                    </span>
                                @endif

                                @if($notice->requires_ack)
                                    <span class="badge bg-orange-lt text-orange">
                                        <i class="ti ti-checkup-list me-1"></i>
                                        Requiere enterado
                                    </span>
                                @endif

                                @if($notice->publish_at)
                                    <span class="badge bg-secondary-lt text-secondary">
                                        <i class="ti ti-calendar me-1"></i>
                                        {{ \Illuminate\Support\Carbon::parse($notice->publish_at)->format('d/m/Y H:i') }}
                                    </span>
                                @endif

                                @if($notice->expires_at)
                                    <span class="badge bg-secondary-lt text-secondary">
                                        Expira {{ \Illuminate\Support\Carbon::parse($notice->expires_at)->format('d/m/Y H:i') }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="col-12 col-md-auto">
                            <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                                <a href="{{ route('admin.notices.edit', $notice->id) }}" class="btn btn-outline-primary btn-sm">
                                    <i class="ti ti-edit me-1"></i>
                                    Editar
                                </a>

                                @if($notice->status !== 'published')
                                    <form method="POST" action="{{ route('admin.notices.publish', $notice->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="ti ti-send me-1"></i>
                                            Publicar
                                        </button>
                                    </form>
                                @endif

                                @if($notice->status !== 'archived')
                                    <form method="POST" action="{{ route('admin.notices.archive', $notice->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="ti ti-archive me-1"></i>
                                            Archivar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="empty">
                    <div class="empty-icon">
                        <i class="ti ti-speakerphone"></i>
                    </div>
                    <p class="empty-title">Sin avisos escolares</p>
                    <p class="empty-subtitle text-muted">
                        Crea el primer comunicado para las familias.
                    </p>
                    <div class="empty-action">
                        <a href="{{ route('admin.notices.create') }}" class="btn btn-primary">
                            <i class="ti ti-plus me-1"></i>
                            Nuevo aviso
                        </a>
                    </div>
                </div>
            @endforelse
        </div>

        @if($notices->hasPages())
            <div class="card-footer bg-white border-0">
                {{ $notices->links() }}
            </div>
        @endif
    </div>
@endsection