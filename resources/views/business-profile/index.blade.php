@extends('layouts.app')

@section('page_title', 'Edit Business Profile')

@section('content')
<div class="container-fluid py-2">
    <div class="mb-4">
        <h4 class="fs-4 fw-bold text-muted mb-1">Business Profile</h4>
        <p class="text-muted small mb-0">Update business profile information.</p>
    </div>

    <div class="card border border-light shadow-sm rounded-4 overflow-hidden">
        <div class="card-body p-4">
            <form action="{{ route('business-profile.update', $businessProfile) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label">Business Name</label>
                    <input type="text" name="business_name" class="form-control" value="{{ old('business_name', $businessProfile->business_name) }}" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Logo</label>
                    <input type="file" name="logo" class="form-control">
                    @if($businessProfile->logo)
                        <div class="mt-2"><img src="{{ asset('storage/' . $businessProfile->logo) }}" alt="logo" style="height:48px"></div>
                    @endif
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="{{ old('email', $businessProfile->email) }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $businessProfile->phone) }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address', $businessProfile->address) }}">
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">City</label>
                        <input type="text" name="city" class="form-control" value="{{ old('city', $businessProfile->city) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">State</label>
                        <input type="text" name="state" class="form-control" value="{{ old('state', $businessProfile->state) }}">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Postal Code</label>
                        <input type="text" name="postal_code" class="form-control" value="{{ old('postal_code', $businessProfile->postal_code) }}">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Country</label>
                    <input type="text" name="country" class="form-control" value="{{ old('country', $businessProfile->country) }}">
                </div>

                <div class="mb-3">
                    <label class="form-label">Tax ID</label>
                    <input type="text" name="tax_id" class="form-control" value="{{ old('tax_id', $businessProfile->tax_id) }}">
                </div>

                <div class="mb-3">
                    <label class="form-label">Business Description</label>
                    <textarea name="business_description" class="form-control" rows="4">{{ old('business_description', $businessProfile->business_description) }}</textarea>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Save</button>                    
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
