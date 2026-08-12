<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlatformTestimonialIndexRequest;
use App\Http\Requests\PresentationTestimonialRequest;
use App\Http\Requests\RejectTestimonialRequest;
use App\Http\Requests\ReturnTestimonialToDraftRequest;
use App\Models\Testimonial;
use App\Models\Workspace;
use App\Services\TestimonialService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PlatformTestimonialController extends Controller
{
    public function index(PlatformTestimonialIndexRequest $request): View
    {
        $filters = array_merge([
            'search' => null,
            'workspace_id' => null,
            'status' => null,
            'rating' => null,
            'featured' => null,
            'submitted_from' => null,
            'submitted_to' => null,
            'per_page' => 25,
        ], $request->validated());

        $testimonials = Testimonial::query()
            ->with(['workspace', 'creator', 'reviewer'])
            ->when($filters['search'], function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('display_name', 'like', '%'.$search.'%')
                        ->orWhere('business_name', 'like', '%'.$search.'%')
                        ->orWhere('content', 'like', '%'.$search.'%')
                        ->orWhereHas('workspace', fn (Builder $workspaceQuery): Builder => $workspaceQuery->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->when($filters['workspace_id'], fn (Builder $query, int $workspaceId): Builder => $query->where('workspace_id', $workspaceId))
            ->when($filters['status'], fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['rating'], fn (Builder $query, int $rating): Builder => $query->where('rating', $rating))
            ->when($filters['featured'] !== null, fn (Builder $query): Builder => $query->where('featured', (bool) $filters['featured']))
            ->when($filters['submitted_from'], fn (Builder $query, string $date): Builder => $query->whereDate('submitted_at', '>=', $date))
            ->when($filters['submitted_to'], fn (Builder $query, string $date): Builder => $query->whereDate('submitted_at', '<=', $date))
            ->latest('submitted_at')
            ->latest('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        return view('platform.testimonials.index', [
            'testimonials' => $testimonials,
            'filters' => $filters,
            'statuses' => Testimonial::STATUSES,
            'workspaces' => Workspace::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Testimonial $testimonial): View
    {
        Gate::authorize('moderate', $testimonial);
        $testimonial->load(['workspace', 'creator', 'reviewer', 'consenter']);

        return view('platform.testimonials.show', [
            'testimonial' => $testimonial,
        ]);
    }

    public function approve(Testimonial $testimonial, TestimonialService $testimonialService): RedirectResponse
    {
        Gate::authorize('moderate', $testimonial);

        try {
            $testimonialService->approve($testimonial, Auth::user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', $this->firstValidationError($exception));
        }

        return back()->with('success', 'Testimonial approved successfully.');
    }

    public function reject(RejectTestimonialRequest $request, Testimonial $testimonial, TestimonialService $testimonialService): RedirectResponse
    {
        Gate::authorize('moderate', $testimonial);

        try {
            $testimonialService->reject($testimonial, Auth::user(), $request->validated('rejection_reason'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', $this->firstValidationError($exception));
        }

        return back()->with('success', 'Testimonial rejected and the workspace has been notified.');
    }

    public function publish(Testimonial $testimonial, TestimonialService $testimonialService): RedirectResponse
    {
        Gate::authorize('moderate', $testimonial);

        try {
            $testimonialService->publish($testimonial, Auth::user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', $this->firstValidationError($exception));
        }

        return back()->with('success', 'Testimonial published on the IDT homepage.');
    }

    public function unpublish(Testimonial $testimonial, TestimonialService $testimonialService): RedirectResponse
    {
        Gate::authorize('moderate', $testimonial);

        try {
            $testimonialService->unpublish($testimonial, Auth::user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', $this->firstValidationError($exception));
        }

        return back()->with('success', 'Testimonial unpublished from the IDT homepage.');
    }

    public function returnToDraft(ReturnTestimonialToDraftRequest $request, Testimonial $testimonial, TestimonialService $testimonialService): RedirectResponse
    {
        Gate::authorize('moderate', $testimonial);

        try {
            $testimonialService->returnToDraft($testimonial, Auth::user(), $request->validated('reason'));
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', $this->firstValidationError($exception));
        }

        return back()->with('success', 'Testimonial returned to the workspace for correction.');
    }

    public function presentation(PresentationTestimonialRequest $request, Testimonial $testimonial, TestimonialService $testimonialService): RedirectResponse
    {
        Gate::authorize('moderate', $testimonial);
        $validated = $request->validated();

        try {
            $testimonialService->setPresentation(
                $testimonial,
                Auth::user(),
                $request->boolean('featured'),
                (int) $validated['display_order'],
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->with('error', $this->firstValidationError($exception));
        }

        return back()->with('success', 'Testimonial homepage presentation updated.');
    }

    private function firstValidationError(ValidationException $exception): string
    {
        return (string) collect($exception->errors())->flatten()->first();
    }
}
