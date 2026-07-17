@extends('layouts.app')

@section('title', 'Editar aviso escolar | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Editar aviso escolar')

@section('topbar-actions')
    @if(($notice->status ?? null) !== 'published')
        <form method="POST" action="{{ route('admin.notices.publish', $notice->id) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="ti ti-send me-1"></i>
                Publicar
            </button>
        </form>
    @endif

    @if(($notice->status ?? null) !== 'archived')
        <form method="POST" action="{{ route('admin.notices.archive', $notice->id) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="ti ti-archive me-1"></i>
                Archivar
            </button>
        </form>
    @endif

    <a href="{{ route('admin.notices.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-arrow-left me-1"></i>
        Avisos
    </a>
@endsection

@section('content')
    @include('admin.notices.form', [
        'mode' => 'edit',
        'formTitle' => 'Editar aviso escolar',
        'action' => route('admin.notices.update', $notice->id),
        'method' => 'PUT',
    ])
@endsection