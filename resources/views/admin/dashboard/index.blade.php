@extends('layouts.admin.index')

@section('title')
    {{ $title = 'Dashboard' }}
@endsection

@section('content')
<div class="container">

    <div class="row">

        <div class="col-xxl-6 col-md-6">
            <div class="card info-card sales-card">
                <div class="card-body">
                    <h5 class="card-title">CARD DE EJEMPLO 1</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <div class="text-center theme-icon-circle theme-icon-circle--amber">
                                <i class="fa fa-building"></i>
                            </div>
                            <div class="px-3 mt-1">
                                <h3 data-purecounter-start="0" data-purecounter-end="1000" data-purecounter-duration="1" class="purecounter"></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xxl-6 col-md-6">
            <div class="card info-card sales-card">
                <div class="card-body">
                    <h5 class="card-title">CARD DE EJEMPLO 2</h5>
                    <div class="d-flex align-items-center">
                        <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                            <div class="text-center theme-icon-circle theme-icon-circle--cyan">
                                <i class="fa fa-users"></i>
                            </div>
                            <div class="px-3 mt-1">
                                <h3 data-purecounter-start="0" data-purecounter-end="2000" data-purecounter-duration="1" class="purecounter"></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>
@endsection
