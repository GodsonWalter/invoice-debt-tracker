<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTestimonialRequest;
use App\Http\Requests\UpdateTestimonialRequest;
use App\Models\Testimonial;
use App\Models\Workspace;
use App\Services\TestimonialService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TestimonialController extends Controller
{
    public function index(Request $request, Workspace $workspace): View
    {
        Gate::authorize('view-testimonials', $workspace);

        $filters = array_merge([
            'search' => null,
            'status' => null,
            'rating' => null,
            'per_page' => 10,
        ], $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(Testimonial::STATUSES)],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50])],
        ]));

        $testimonials = $workspace->testimonials()
            ->with(['creator', 'reviewer'])
            ->when($filters['search'], function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('display_name', 'like', '%'.$search.'%')
                        ->orWhere('business_name', 'like', '%'.$search.'%')
                        ->orWhere('content', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['status'], fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['rating'], fn (Builder $query, int $rating): Builder => $query->where('rating', $rating))
            ->latest()
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('testimonials.index', [
            'workspace' => $workspace,
            'testimonials' => $testimonials,
            'filters' => $filters,
            'statuses' => Testimonial::STATUSES,
        ]);
    }

    public function create(Workspace $workspace): View
    {
        Gate::authorize('manage-testimonials', $workspace);

        return view('testimonials.create', [
            'workspace' => $workspace,
        ]);
    }

    public function store(StoreTestimonialRequest $request, Workspace $workspace, TestimonialService $testimonialService): RedirectResponse
    {
        $testimonial = $testimonialService->create(
            $workspace,
            Auth::user(),
            $request->validated(),
            $request->file('image'),
        );

        return redirect()
            ->route('testimonials.show', [$workspace, $testimonial])
            ->with('success', 'Testimonial draft created successfully.');
    }

    public function show(Workspace $workspace, Testimonial $testimonial): View
    {
        $testimonial = $this->scopedTestimonial($workspace, $testimonial);
        Gate::authorize('view', $testimonial);

        return view('testimonials.show', [
            'workspace' => $workspace,
            'testimonial' => $testimonial,
        ]);
    }

    public function edit(Workspace $workspace, Testimonial $testimonial): View
    {
        $testimonial = $this->scopedTestimonial($workspace, $testimonial);
        Gate::authorize('update', $testimonial);

        return view('testimonials.edit', [
            'workspace' => $workspace,
            'testimonial' => $testimonial,
        ]);
    }

    public function update(UpdateTestimonialRequest $request, Workspace $workspace, Testimonial $testimonial, TestimonialService $testimonialService): RedirectResponse
    {
        $testimonial = $this->scopedTestimonial($workspace, $testimonial);
        Gate::authorize('update', $testimonial);

        try {
            $testimonialService->update(
                $workspace,
                Auth::user(),
                $testimonial,
                $request->validated(),
                $request->file('image'),
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', $this->firstValidationError($exception));
        }

        return redirect()
            ->route('testimonials.show', [$workspace, $testimonial])
            ->with('success', 'Testimonial updated successfully.');
    }

    public function submit(Workspace $workspace, Testimonial $testimonial, TestimonialService $testimonialService): RedirectResponse
    {
        $testimonial = $this->scopedTestimonial($workspace, $testimonial);
        Gate::authorize('submit', $testimonial);

        try {
            $testimonialService->submit($workspace, Auth::user(), $testimonial);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', $this->firstValidationError($exception));
        }

        return redirect()
            ->route('testimonials.show', [$workspace, $testimonial])
            ->with('success', 'Testimonial submitted for platform review.');
    }

    public function destroy(Workspace $workspace, Testimonial $testimonial, TestimonialService $testimonialService): RedirectResponse
    {
        $testimonial = $this->scopedTestimonial($workspace, $testimonial);
        Gate::authorize('delete', $testimonial);

        try {
            $testimonialService->delete($workspace, Auth::user(), $testimonial);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', $this->firstValidationError($exception));
        }

        return redirect()
            ->route('testimonials.index', $workspace)
            ->with('success', 'Testimonial draft deleted successfully.');
    }

    private function scopedTestimonial(Workspace $workspace, Testimonial $testimonial): Testimonial
    {
        return $workspace->testimonials()
            ->with(['workspace', 'creator', 'reviewer'])
            ->whereKey($testimonial->getKey())
            ->firstOrFail();
    }

    private function firstValidationError(ValidationException $exception): string
    {
        return (string) collect($exception->errors())->flatten()->first();
    }
}
