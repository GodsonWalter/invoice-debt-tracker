<?php

namespace App\Http\Controllers;

use App\Models\BusinessProfile;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessProfileController extends Controller
{
    private function authorizeWorkspaceUser()
    {      // deny access if the user is not the workspace owner or admin

        $currentWorkspace = request()->currentWorkspace;
        if (! $currentWorkspace->users()->where('user_id', Auth::id())->whereIn('workspace_user.role', ['owner', 'admin'])->exists()) {
            throw new HttpResponseException(
                redirect()->route('dashboard')->with('error', 'You are not authorized to manage this workspace.')
            );
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorizeWorkspaceUser();
        // if business profile for this workspace does not exist, create a default one
        $businessProfile = BusinessProfile::firstOrCreate(
            ['workspace_id' => $request->currentWorkspace->id],
            ['business_name' => $request->currentWorkspace->name]
        );
        $data['businessProfile'] = $businessProfile;

        return view('business-profile.index', $data);
    }

    public function update(Request $request, BusinessProfile $businessProfile)
    {
        $this->authorizeWorkspaceUser();

        abort_unless($businessProfile->workspace_id === $request->currentWorkspace->id, 404);

        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:2048'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:100'],
            'business_description' => ['nullable', 'string'],
        ]);

        $logoPath = $businessProfile->logo;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('business-profiles', 'public');
        }
        $businessProfile->update([
            ...$validated,
            'logo' => $logoPath,
        ]);

        return back()->with('success', 'Business profile updated successfully.');
    }
}
