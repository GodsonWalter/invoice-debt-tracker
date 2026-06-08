@switch($status)
    @case('pending')
        <span class="badge bg-warning text-dark">
            <i class="fa-solid fa-hourglass-end me-1"></i> Pending
        </span>
        @break
    @case('sent')
        <span class="badge bg-success">
            <i class="fa-solid fa-check me-1"></i> Sent
        </span>
        @break
    @case('failed')
        <span class="badge bg-danger">
            <i class="fa-solid fa-xmark me-1"></i> Failed
        </span>
        @break
    @default
        <span class="badge bg-secondary">{{ ucfirst($status) }}</span>
@endswitch
