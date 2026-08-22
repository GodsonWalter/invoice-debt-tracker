@extends('layouts.app')

@section('page_title', 'Testimonial Preview')

@section('content')
    <div class="container-fluid px-0">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div><p class="text-muted mb-1">Customer story preview</p><h1 class="h3 fw-bold mb-1">{{ $testimonial->display_name }}</h1><div>@include('testimonials._status_badge', ['testimonial' => $testimonial])</div></div>
            <div class="d-flex flex-wrap gap-2">
                @can('update', $testimonial)<a href="{{ route('testimonials.edit', [$workspace, $testimonial]) }}" class="btn btn-outline-primary">Edit</a>@endcan
                @can('submit', $testimonial)
                    <form method="POST" action="{{ route('testimonials.submit', [$workspace, $testimonial]) }}" data-lifecycle-confirm data-lifecycle-title="Submit testimonial?" data-lifecycle-text="You will not be able to edit this testimonial while it is under review." data-lifecycle-confirm-text="Submit for review">@csrf<button type="submit" class="btn btn-primary">Submit for review</button></form>
                @endcan
                @can('delete', $testimonial)
                    <form method="POST" action="{{ route('testimonials.destroy', [$workspace, $testimonial]) }}" data-delete-confirm data-delete-confirm-message="Delete this testimonial draft? This cannot be undone.">@csrf @method('DELETE')<button type="submit" class="btn btn-outline-danger">Delete</button></form>
                @endcan
            </div>
        </div>

        @if ($testimonial->status === 'rejected' && $testimonial->rejection_reason)
            <div class="alert alert-danger" role="alert"><strong>Correction requested:</strong> {{ $testimonial->rejection_reason }}</div>
        @endif

        <div class="row g-4">
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 h-100"><div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        @if ($testimonial->image_url)<img src="{{ $testimonial->image_url }}" alt="{{ $testimonial->display_name }}" class="rounded-circle object-fit-cover" style="width: 80px; height: 80px;">@else<span class="rounded-circle bg-light text-primary d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;"><i class="fa-solid fa-user fs-3"></i></span>@endif
                        <div><h2 class="h5 mb-1">{{ $testimonial->display_name }}</h2><p class="text-muted mb-0">{{ $testimonial->job_title }}{{ $testimonial->job_title && $testimonial->business_name ? ' · ' : '' }}{{ $testimonial->business_name }}</p><div class="text-warning mt-1" aria-label="{{ $testimonial->rating }} out of 5 stars">{{ str_repeat('★', $testimonial->rating) }}</div></div>
                    </div>
                    <blockquote class="blockquote mb-0"><p class="fs-5">{!! nl2br(e($testimonial->content)) !!}</p></blockquote>
                </div></div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm rounded-4"><div class="card-body p-4">
                    <h2 class="h5 fw-bold mb-3">Review details</h2>
                    <dl class="row small mb-0">
                        <dt class="col-6 text-muted">Consent</dt><dd class="col-6 text-end text-success">Confirmed</dd>
                        <dt class="col-6 text-muted">Consented by</dt><dd class="col-6 text-end">{{ $testimonial->consenter?->name ?: 'Current user' }}</dd>
                        <dt class="col-6 text-muted">Created</dt><dd class="col-6 text-end">{{ $testimonial->created_at?->format('M j, Y') }}</dd>
                        <dt class="col-6 text-muted">Submitted</dt><dd class="col-6 text-end">{{ $testimonial->submitted_at?->format('M j, Y') ?: 'Not submitted' }}</dd>
                        <dt class="col-6 text-muted">Reviewed</dt><dd class="col-6 text-end">{{ $testimonial->reviewed_at?->format('M j, Y') ?: 'Not reviewed' }}</dd>
                    </dl>
                    <div class="alert alert-light border small mt-4 mb-0">Platform approval is required before this testimonial can appear on the public {{ $platformSettings['settings']->product_name }} homepage.</div>
                </div></div>
            </div>
        </div>
    </div>
@endsection
