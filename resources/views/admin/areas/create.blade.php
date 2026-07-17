@extends('layouts.app')

@section('title', 'Nueva área | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Nueva área')

@section('content')
    <div class="row">
        <div class="col-lg-8 col-xl-7">
            <form method="POST" action="{{ route('admin.areas.store') }}" class="card">
                @csrf

                <div class="card-header">
                    <h3 class="card-title">Datos del área</h3>
                </div>

                <div class="card-body">
                    @include('admin.areas.partials.form', ['areaRow' => null])
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('admin.areas.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                    <button class="btn btn-primary">
                        Guardar área
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection