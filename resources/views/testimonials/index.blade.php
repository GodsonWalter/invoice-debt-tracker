@extends('layouts.app')

@section('page_title', 'Testimonials')

@section('content')
    <div class="container-fluid px-0">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <p class="text-muted mb-1">Workspace reputation</p>
                <h1 class="h3 fw-bold mb-1">Testimonials</h1>
                <p class="text-muted mb-0">Collect customer feedback and submit approved consented stories for {{ $platformSettings['settings']->product_name }} review.</p>
            </div>
            @can('manage-testimonials', $workspace)
                <a href="{{ route('testimonials.create', $workspace) }}" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>New testimonial</a>
            @endcan
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-12 col-md-5">
                        <label for="testimonial-search" class="form-label small text-muted">Search</label>
                        <input id="testimonial-search" type="search" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Name, business, or content">
                    </div>
                    <div class="col-12 col-md-3">
                        <label for="testimonial-status" class="form-label small text-muted">Status</label>
                        <select id="testimonial-status" name="status" class="form-select">
                            <option value="">All statuses</option>
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label for="testimonial-rating" class="form-label small text-muted">Rating</label>
                        <select id="testimonial-rating" name="rating" class="form-select">
                            <option value="">All ratings</option>
                            @for ($rating = 5; $rating >= 1; $rating--)
                                <option value="{{ $rating }}" @selected((string) $filters['rating'] === (string) $rating)>{{ $rating }} / 5</option>
                            @endfor
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <button type="submit" class="btn btn-outline-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <caption class="visually-hidden">Workspace testimonials</caption>
                    <thead>
                        <tr><th>Customer</th><th>Rating</th><th>Status</th><th>Submitted</th><th class="text-end">Actions</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($testimonials as $testimonial)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($testimonial->image_url)
                                            <img src="{{ $testimonial->image_url }}" alt="" class="rounded-circle object-fit-cover" style="width: 40px; height: 40px;">
                                        @else
                                            <span class="rounded-circle bg-light text-primary d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px;"><i class="fa-solid fa-quote-left"></i></span>
                                        @endif
                                        <div><div class="fw-semibold">{{ $testimonial->display_name }}</div><small class="text-muted">{{ $testimonial->business_name ?: $testimonial->job_title ?: 'Customer story' }}</small></div>
                                    </div>
                                </td>
                                <td><span class="text-warning" aria-label="{{ $testimonial->rating }} out of 5 stars">{{ str_repeat('★', $testimonial->rating) }}</span><small class="text-muted ms-1">{{ $testimonial->rating }}/5</small></td>
                                <td>@include('testimonials._status_badge', ['testimonial' => $testimonial])</td>
                                <td class="text-muted">{{ $testimonial->submitted_at?->format('M j, Y') ?: 'Not submitted' }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('testimonials.show', [$workspace, $testimonial]) }}" class="btn btn-sm btn-outline-primary" aria-label="Preview {{ $testimonial->display_name }}"><i class="fa-solid fa-eye me-1"></i>Preview</a>
                                    @can('update', $testimonial)
                                        <a href="{{ route('testimonials.edit', [$workspace, $testimonial]) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-5"><i class="fa-solid fa-quote-left text-muted fs-2 mb-2"></i><p class="mb-1 fw-semibold">No testimonials yet</p><p class="text-muted mb-0">Create a customer story when you have permission to share it.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($testimonials->hasPages())
                <div class="card-footer bg-white border-0">{{ $testimonials->links() }}</div>
            @endif
        </div>
    </div>
@endsection
