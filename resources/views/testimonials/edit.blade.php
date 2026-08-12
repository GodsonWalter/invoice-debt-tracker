@extends('layouts.app')

@section('page_title', 'Edit Testimonial')

@section('content')
    <div class="container-fluid px-0">
        <div class="mb-4"><h1 class="h3 fw-bold mb-1">Edit testimonial</h1><p class="text-muted mb-0">Update this draft before submitting it for platform review.</p></div>
        <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
            <form method="POST" action="{{ route('testimonials.update', [$workspace, $testimonial]) }}" enctype="multipart/form-data">
                @include('testimonials._form', ['formMethod' => 'PUT', 'submitLabel' => 'Save changes'])
            </form>
        </div></div>
    </div>
@endsection
