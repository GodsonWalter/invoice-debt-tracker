@props(['title' => 'No results found', 'message' => 'Try adjusting your query.'])

<div class="text-center py-5 px-4">
    <div class="mb-3">
        <i class="fa-solid fa-inbox fs-1 text-muted"></i>
    </div>
    <h5 class="text-dark fw-semibold mb-2">{{ $title }}</h5>
    <p class="text-muted small mb-0">{{ $message }}</p>
</div>
