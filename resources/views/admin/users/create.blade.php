@extends('layouts.app')

@section('title', 'Nuevo usuario | SchoolPass')
@section('section-label', 'Administración')
@section('page-title', 'Crear usuario institucional')

@section('topbar-actions')
    <a
        href="{{ route('admin.users.index') }}"
        class="btn btn-outline-secondary btn-sm"
    >
        <i class="ti ti-arrow-left me-1"></i>
        Usuarios del sistema
    </a>
@endsection

@section('content')
    @if($errors->any())
        <div class="alert alert-danger">
            <i class="ti ti-alert-circle me-2"></i>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="row justify-content-center">
        <div class="col-xl-10">
            <form
                method="POST"
                action="{{ route('admin.users.store') }}"
                autocomplete="off"
            >
                @csrf

                @include('admin.users.partials.form')
            </form>
        </div>
    </div>
@endsection