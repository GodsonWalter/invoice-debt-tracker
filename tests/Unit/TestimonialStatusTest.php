<?php

use App\Models\Testimonial;

test('testimonial statuses and workspace editability are explicit', function () {
    expect(Testimonial::STATUSES)->toBe([
        'draft',
        'pending_review',
        'approved',
        'rejected',
        'published',
        'unpublished',
    ])
        ->and(Testimonial::WORKSPACE_EDITABLE_STATUSES)->toBe(['draft', 'rejected']);
});

test('testimonial status labels are human readable', function () {
    $testimonial = new Testimonial(['display_name' => 'Customer']);
    $testimonial->status = Testimonial::STATUS_PENDING_REVIEW;

    expect($testimonial->status_label)->toBe('Pending Review');
});
