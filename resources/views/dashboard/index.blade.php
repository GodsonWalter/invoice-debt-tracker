@extends('layouts.app')

@section('content')
<div class="container-fluid py-2">
    <div class="mb-4">
        <h2 class="fs-4 fw-bold text-dark mb-1">Dashboard Overview</h2>
        <p class="text-muted small mb-0">Welcome back, {{ Auth::user()->name }}!</p>
    </div>


    {{-- <div class="row g-4 mb-4">
        
        <div class="col-12 col-md-6">
            <div class="card border border-light shadow-sm rounded-4 h-100 p-4">
                <div class="d-flex align-items-center">
                    <div class="p-3 rounded-3 bg-primary bg-opacity-10 text-primary me-4 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-users fs-3"></i>
                    </div>
                    <div>
                        <p class="text-muted small fw-bold text-uppercase tracking-wide mb-1">Total Users</p>
                        <h3 class="fs-3 fw-bold text-dark mb-0">{{ number_format(5) }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <div class="card border border-light shadow-sm rounded-4 h-100 p-4">
                <div class="d-flex align-items-center">
                    <div class="p-3 rounded-3 bg-success bg-opacity-10 text-success me-4 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                        <i class="fa-solid fa-box-open fs-3"></i>
                    </div>
                    <div>
                        <p class="text-muted small fw-bold text-uppercase tracking-wide mb-1">Total Products</p>
                        <h3 class="fs-3 fw-bold text-dark mb-0">{{ number_format(5) }}</h3>
                    </div>
                </div>
            </div>
        </div>

    </div> --}}

   

{{-- <div class="card border border-light shadow-sm rounded-4 overflow-hidden mt-2">
    <div class="card-header bg-white p-4 border-bottom d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <h5 class="fw-bold text-dark mb-0 fs-6">Recent System Activity</h5>
        <button class="btn btn-link text-decoration-none fw-semibold p-0" style="color: #4f46e5; font-size: 0.875rem;">View All</button>
    </div>

</div> --}}
</div>
@endsection