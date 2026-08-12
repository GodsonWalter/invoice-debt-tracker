@extends('layouts.app')

@section('page_title', 'New Testimonial')

@section('content')
    <div class="container-fluid px-0">
        <div class="mb-4"><h1 class="h3 fw-bold mb-1">New testimonial</h1><p class="text-muted mb-0">Capture a customer story with explicit permission to publish it.</p></div>
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <form method="POST" action="{{ route('testimonials.store', $workspace) }}" enctype="multipart/form-data">
                @include('testimonials._form', ['formMethod' => 'POST', 'submitLabel' => 'Save draft', 'testimonial' => null])
            </form>
        </div></div>
    </div>
@endsection
