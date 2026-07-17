@extends('layouts.app')

@section('title', 'Nuevo aviso escolar | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Nuevo aviso escolar')

@section('topbar-actions')
    <a href="{{ route('admin.notices.index') }}" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-arrow-left me-1"></i>
        Avisos
    </a>
@endsection

@section('content')
    @include('admin.notices.form', [
        'mode' => 'create',
        'formTitle' => 'Nuevo aviso escolar',
        'action' => route('admin.notices.store'),
        'method' => 'POST',
    ])
@endsection