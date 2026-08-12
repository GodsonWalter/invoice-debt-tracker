@php
    $statusClasses = [
        'draft' => 'secondary',
        'pending_review' => 'warning',
        'approved' => 'info',
        'rejected' => 'danger',
        'published' => 'success',
        'unpublished' => 'dark',
    ];
@endphp

<span class="badge text-bg-{{ $statusClasses[$testimonial->status] ?? 'secondary' }}">
    {{ $testimonial->status_label }}
</span>
