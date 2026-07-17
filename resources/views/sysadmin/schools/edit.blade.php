@extends('layouts.sysadmin')

@section('title', 'Editar '.$school->name)
@section('page_title', 'Editar escuela')

@section('content')
<div class="page-header d-print-none">
    <div class="row g-2 align-items-center">
        <div class="col">
            <div class="page-pretitle">
                <a
                    href="{{ route('sysadmin.schools.show', $school) }}"
                    class="text-secondary text-decoration-none"
                >
                    <i class="ti ti-arrow-left me-1"></i>
                    {{ $school->name }}
                </a>
            </div>

            <h2 class="page-title">Editar escuela</h2>
        </div>
    </div>
</div>

<form
    method="POST"
    action="{{ route('sysadmin.schools.update', $school) }}"
>
    @csrf
    @method('PUT')

    @include('sysadmin.schools.partials.form', [
        'school' => $school,
    ])
</form>
@endsection
