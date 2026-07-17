@extends('layouts.app')

@section('title', 'Alumno | SchoolPass')
@section('section-label', 'Alumno')
@section('page-title', 'Mi credencial')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card card-md">
                <div class="card-body text-center">
                    <span class="avatar avatar-xl bg-blue-lt mb-3">
                        JP
                    </span>

                    <h2 class="mb-1">
                        Juan Pérez López
                    </h2>

                    <div class="text-secondary mb-4">
                        Primaria 2B
                    </div>

                    <div class="border rounded p-4 bg-light">
                        <i class="ti ti-qrcode" style="font-size: 10rem;"></i>
                    </div>

                    <div class="alert alert-success mt-4 mb-0">
                        <i class="ti ti-circle-check me-2"></i>
                        Credencial activa
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection