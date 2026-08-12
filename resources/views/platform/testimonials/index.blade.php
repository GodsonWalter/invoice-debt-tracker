@extends('layouts.app')

@section('page_title', 'Platform Testimonials')

@section('content')
    <div class="container-fluid px-0">
        <div class="mb-4"><p class="text-muted mb-1">Platform moderation</p><h1 class="h3 fw-bold mb-1">Testimonials</h1><p class="text-muted mb-0">Review customer stories across all IDT workspaces before publication.</p></div>

        <div class="card border-0 shadow-sm rounded-4 mb-4"><div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-lg-3"><label for="platform-testimonial-search" class="form-label small text-muted">Search</label><input id="platform-testimonial-search" type="search" name="search" value="{{ $filters['search'] }}" class="form-control" placeholder="Customer, business, workspace"></div>
                <div class="col-12 col-md-6 col-lg-2"><label for="platform-testimonial-workspace" class="form-label small text-muted">Workspace</label><select id="platform-testimonial-workspace" name="workspace_id" class="form-select"><option value="">All workspaces</option>@foreach ($workspaces as $workspace)<option value="{{ $workspace->id }}" @selected((string) $filters['workspace_id'] === (string) $workspace->id)>{{ $workspace->name }}</option>@endforeach</select></div>
                <div class="col-12 col-md-6 col-lg-2"><label for="platform-testimonial-status" class="form-label small text-muted">Status</label><select id="platform-testimonial-status" name="status" class="form-select"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status }}" @selected($filters['status'] === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
                <div class="col-6 col-md-3 col-lg-1"><label for="platform-testimonial-rating" class="form-label small text-muted">Rating</label><select id="platform-testimonial-rating" name="rating" class="form-select"><option value="">All</option>@for ($rating = 5; $rating >= 1; $rating--)<option value="{{ $rating }}" @selected((string) $filters['rating'] === (string) $rating)>{{ $rating }}</option>@endfor</select></div>
                <div class="col-6 col-md-3 col-lg-2"><label for="platform-testimonial-featured" class="form-label small text-muted">Featured</label><select id="platform-testimonial-featured" name="featured" class="form-select"><option value="">All</option><option value="1" @selected((string) $filters['featured'] === '1')>Featured</option><option value="0" @selected((string) $filters['featured'] === '0')>Not featured</option></select></div>
                <div class="col-12 col-md-6 col-lg-2"><label for="platform-testimonial-submitted-from" class="form-label small text-muted">Submitted from</label><input id="platform-testimonial-submitted-from" type="date" name="submitted_from" value="{{ $filters['submitted_from'] }}" class="form-control"></div>
                <div class="col-12 col-md-6 col-lg-2"><label for="platform-testimonial-submitted-to" class="form-label small text-muted">Submitted to</label><input id="platform-testimonial-submitted-to" type="date" name="submitted_to" value="{{ $filters['submitted_to'] }}" class="form-control"></div>
                <div class="col-12 col-lg-1"><button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter"></i><span class="visually-hidden">Apply filters</span></button></div>
            </form>
        </div></div>

        <div class="card border-0 shadow-sm rounded-4"><div class="table-responsive">
            <table class="table table-hover align-middle mb-0"><caption class="visually-hidden">Platform testimonial moderation queue</caption><thead><tr><th>Customer</th><th>Workspace</th><th>Rating</th><th>Status</th><th>Submitted</th><th>Presentation</th><th class="text-end">Action</th></tr></thead><tbody>
                @forelse ($testimonials as $testimonial)
                    <tr>
                        <td><div class="fw-semibold">{{ $testimonial->display_name }}</div><small class="text-muted">{{ $testimonial->business_name ?: $testimonial->job_title ?: 'Customer story' }}</small></td>
                        <td>{{ $testimonial->workspace?->name ?: 'Deleted workspace' }}</td>
                        <td><span class="text-warning" aria-label="{{ $testimonial->rating }} out of 5 stars">{{ str_repeat('★', $testimonial->rating) }}</span></td>
                        <td>@include('testimonials._status_badge', ['testimonial' => $testimonial])</td>
                        <td class="text-muted">{{ $testimonial->submitted_at?->format('M j, Y') ?: 'Not submitted' }}</td>
                        <td>@if ($testimonial->featured)<span class="badge text-bg-primary"><i class="fa-solid fa-star me-1"></i>#{{ $testimonial->display_order }}</span>@else<span class="text-muted small">Not featured</span>@endif</td>
                        <td class="text-end"><a href="{{ route('platform.testimonials.show', $testimonial) }}" class="btn btn-sm btn-outline-primary">Review</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-5"><i class="fa-solid fa-inbox text-muted fs-2 mb-2"></i><p class="mb-1 fw-semibold">No testimonials match these filters</p><p class="text-muted mb-0">Pending workspace submissions will appear here.</p></td></tr>
                @endforelse
            </tbody></table>
        </div>@if ($testimonials->hasPages())<div class="card-footer bg-white border-0">{{ $testimonials->links() }}</div>@endif</div>
    </div>
@endsection
