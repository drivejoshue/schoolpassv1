@extends('layouts.app')

@section('title', 'Editar fecha especial | SchoolPass')
@section('section-label', 'Dirección')
@section('page-title', 'Editar fecha especial')

@section('content')
    <div class="row">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('admin.calendar.update', $dayRow->id) }}" class="card">
                @csrf
                @method('PUT')

                <div class="card-header">
                    <h3 class="card-title">Datos de la fecha especial</h3>
                </div>

                <div class="card-body">
                    @include('admin.calendar.partials.form', [
                        'dayRow' => $dayRow,
                        'isEdit' => true,
                    ])
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('admin.calendar.index') }}" class="btn btn-outline-secondary">
                        Cancelar
                    </a>

                    <button class="btn btn-primary">
                        Actualizar fecha
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection