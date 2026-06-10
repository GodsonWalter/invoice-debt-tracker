@props(['message' => 'We could not understand that query. Please try again.', 'title' => 'Unable to Process Query'])

<div class="alert alert-danger border-0 rounded-4 mb-0" role="alert">
    <div class="d-flex align-items-center">
        <div class="me-3">
            <i class="fa-solid fa-circle-exclamation fs-5"></i>
        </div>
        <div>
            <h6 class="alert-heading mb-1">{{ $title }}</h6>
            <p class="mb-0 small">{{ $message }}</p>
        </div>
    </div>
</div>
