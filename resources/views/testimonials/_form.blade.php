@csrf
@if (($formMethod ?? 'POST') !== 'POST')
    @method($formMethod)
@endif

<div class="row g-3">
    <div class="col-12 col-md-6">
        <label for="display-name" class="form-label">Customer/display name <span class="text-danger">*</span></label>
        <input id="display-name" type="text" name="display_name" class="form-control @error('display_name') is-invalid @enderror"
            value="{{ old('display_name', $testimonial?->display_name) }}" maxlength="255" required>
        @error('display_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label for="job-title" class="form-label">Job title or role</label>
        <input id="job-title" type="text" name="job_title" class="form-control @error('job_title') is-invalid @enderror"
            value="{{ old('job_title', $testimonial?->job_title) }}" maxlength="255">
        @error('job_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label for="business-name" class="form-label">Business name</label>
        <input id="business-name" type="text" name="business_name" class="form-control @error('business_name') is-invalid @enderror"
            value="{{ old('business_name', $testimonial?->business_name) }}" maxlength="255">
        @error('business_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12 col-md-6">
        <label for="rating" class="form-label">Rating <span class="text-danger">*</span></label>
        <select id="rating" name="rating" class="form-select @error('rating') is-invalid @enderror" required>
            <option value="">Select rating</option>
            @for ($rating = 5; $rating >= 1; $rating--)
                <option value="{{ $rating }}" @selected((string) old('rating', $testimonial?->rating) === (string) $rating)>{{ $rating }} / 5</option>
            @endfor
        </select>
        @error('rating')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label for="testimonial-content" class="form-label">Testimonial <span class="text-danger">*</span></label>
        <textarea id="testimonial-content" name="content" rows="6" class="form-control @error('content') is-invalid @enderror"
            maxlength="500" required>{{ old('content', $testimonial?->content) }}</textarea>
        <div class="form-text">Maximum 500 characters.</div>
        <div class="form-text">Use the customer’s own words. HTML is removed before storage.</div>
        @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label for="testimonial-image" class="form-label">Customer photo or business logo</label>
        <input id="testimonial-image" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif"
            class="form-control @error('image') is-invalid @enderror">
        <div class="form-text">JPG, PNG, WEBP, or GIF up to 2 MB. Maximum dimensions: 3000 × 3000 px.</div>
        @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if ($testimonial?->image_url)
            <div class="mt-2 d-flex align-items-center gap-2">
                <img src="{{ $testimonial->image_url }}" alt="Current testimonial image" class="rounded object-fit-cover" style="width: 64px; height: 64px;">
                <span class="small text-muted">Upload a new image to replace the current one.</span>
            </div>
        @endif
    </div>
    <div class="col-12">
        <div class="form-check">
            <input id="consent-confirmed" type="checkbox" name="consent_confirmed" value="1"
                class="form-check-input @error('consent_confirmed') is-invalid @enderror"
                @checked(old('consent_confirmed', $testimonial?->consent_confirmed)) required>
            <label for="consent-confirmed" class="form-check-label">
                I confirm that I have permission to publish this customer’s name, business information, image, and testimonial on IDT.
            </label>
            @error('consent_confirmed')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mt-4">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>{{ $submitLabel ?? 'Save draft' }}</button>
    <a href="{{ route('testimonials.index', $workspace) }}" class="btn btn-outline-secondary">Cancel</a>
</div>
