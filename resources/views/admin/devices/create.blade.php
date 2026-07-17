@extends('layouts.app')

@section('title', 'Nuevo dispositivo | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Nuevo dispositivo')

@section('content')
    <div class="row">
        <div class="col-xl-9">
            <form method="POST" action="{{ route('admin.devices.store') }}" class="card">
                @csrf

                <div class="card-header">
                    <h3 class="card-title">Datos del dispositivo</h3>
                </div>

                <div class="card-body">
                    @include('admin.devices.partials.form', ['deviceRow' => null])
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('admin.devices.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                    <button class="btn btn-primary">
                        Guardar dispositivo
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection