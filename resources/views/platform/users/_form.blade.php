<div class="col-md-6">
    <label for="platform-user-name" class="form-label">Name</label>
    <input id="platform-user-name" name="name" value="{{ old('name', $user->name ?? '') }}"
        class="form-control @error('name') is-invalid @enderror" required maxlength="255">
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="col-md-6">
    <label for="platform-user-email" class="form-label">Email</label>
    <input id="platform-user-email" type="email" name="email" value="{{ old('email', $user->email ?? '') }}"
        class="form-control @error('email') is-invalid @enderror" required maxlength="255">
    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="col-md-6">
    <label for="platform-user-role" class="form-label">Platform role</label>
    <select id="platform-user-role" name="role" class="form-select @error('role') is-invalid @enderror" required>
        @foreach ($roles as $role)
            <option value="{{ $role }}" @selected(old('role', $user->role ?? 'user') === $role)>{{ ucfirst($role) }}</option>
        @endforeach
    </select>
    @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="col-md-6">
    <label for="platform-user-password" class="form-label">{{ $passwordRequired ? 'Password' : 'New password (optional)' }}</label>
    <input id="platform-user-password" type="password" name="password"
        class="form-control @error('password') is-invalid @enderror" @required($passwordRequired) autocomplete="new-password">
    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="col-md-6">
    <label for="platform-user-password-confirmation" class="form-label">Confirm password</label>
    <input id="platform-user-password-confirmation" type="password" name="password_confirmation"
        class="form-control" @required($passwordRequired) autocomplete="new-password">
</div>

<div class="col-12">
    <input type="hidden" name="is_active" value="0">
    <div class="form-check">
        <input id="platform-user-is-active" type="checkbox" name="is_active" value="1" class="form-check-input"
            @checked((bool) old('is_active', $user->is_active ?? true))>
        <label for="platform-user-is-active" class="form-check-label">Account is active</label>
    </div>
    @error('is_active')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>

<div class="col-12">
    <button class="btn btn-primary" type="submit">{{ $submitLabel }}</button>
</div>
