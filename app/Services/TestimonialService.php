<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Testimonial;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\TestimonialStatusNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TestimonialService
{
    private const HOMEPAGE_CACHE_KEY = 'testimonials.homepage';

    public function create(Workspace $workspace, User $actor, array $attributes, ?UploadedFile $image = null): Testimonial
    {
        $this->assertConsent($attributes);
        $imagePath = $this->storeImage($workspace, $image);

        try {
            $testimonial = DB::transaction(function () use ($workspace, $actor, $attributes, $imagePath): Testimonial {
                $testimonial = new Testimonial;
                $testimonial->forceFill([
                    'workspace_id' => $workspace->id,
                    'created_by' => $actor->id,
                    ...$this->contentAttributes($attributes),
                    'image_path' => $imagePath,
                    'status' => Testimonial::STATUS_DRAFT,
                    'consent_confirmed' => true,
                    'consented_at' => now(),
                    'consented_by' => $actor->id,
                ])->save();

                $this->recordActivity($workspace, $actor, $testimonial, 'testimonial.created', 'Testimonial draft created.');

                return $testimonial->refresh();
            });
        } catch (Throwable $exception) {
            $this->deleteImage($imagePath);

            throw $exception;
        }

        return $testimonial;
    }

    public function update(Workspace $workspace, User $actor, Testimonial $testimonial, array $attributes, ?UploadedFile $image = null): Testimonial
    {
        $this->assertConsent($attributes);
        $newImagePath = $this->storeImage($workspace, $image);
        $oldImagePath = null;

        try {
            $updated = DB::transaction(function () use ($workspace, $actor, $testimonial, $attributes, $newImagePath, &$oldImagePath): Testimonial {
                $testimonial = $workspace->testimonials()
                    ->lockForUpdate()
                    ->find($testimonial->id);

                if (! $testimonial) {
                    throw (new ModelNotFoundException)->setModel(Testimonial::class, [$testimonial?->id]);
                }

                $this->assertWorkspaceEditable($testimonial);
                $oldImagePath = $testimonial->image_path;
                $testimonial->forceFill([
                    ...$this->contentAttributes($attributes),
                    'image_path' => $newImagePath ?: $testimonial->image_path,
                    'consent_confirmed' => true,
                    'consented_at' => now(),
                    'consented_by' => $actor->id,
                ])->save();

                $this->recordActivity($workspace, $actor, $testimonial, 'testimonial.updated', 'Testimonial draft details updated.');

                return $testimonial->refresh();
            });
        } catch (Throwable $exception) {
            $this->deleteImage($newImagePath);

            throw $exception;
        }

        if ($newImagePath && $oldImagePath && $newImagePath !== $oldImagePath) {
            $this->deleteImage($oldImagePath);
        }

        $this->forgetHomepageCache();

        return $updated;
    }

    public function delete(Workspace $workspace, User $actor, Testimonial $testimonial): void
    {
        $imagePath = DB::transaction(function () use ($workspace, $actor, $testimonial): string|false|null {
            $testimonial = $workspace->testimonials()
                ->lockForUpdate()
                ->find($testimonial->id);

            if (! $testimonial) {
                throw (new ModelNotFoundException)->setModel(Testimonial::class, [$testimonial?->id]);
            }

            $this->assertWorkspaceEditable($testimonial);
            $imagePath = $testimonial->image_path;
            $testimonial->delete();
            $this->recordActivity($workspace, $actor, $testimonial, 'testimonial.deleted', 'Testimonial draft deleted.');

            return $imagePath;
        });

        $this->deleteImage($imagePath);
        $this->forgetHomepageCache();
    }

    public function submit(Workspace $workspace, User $actor, Testimonial $testimonial): Testimonial
    {
        $submitted = DB::transaction(function () use ($workspace, $actor, $testimonial): Testimonial {
            $testimonial = $workspace->testimonials()
                ->lockForUpdate()
                ->find($testimonial->id);

            if (! $testimonial) {
                throw (new ModelNotFoundException)->setModel(Testimonial::class, [$testimonial?->id]);
            }

            $this->assertWorkspaceEditable($testimonial);
            $this->assertConsent(['consent_confirmed' => $testimonial->consent_confirmed]);
            $testimonial->forceFill([
                'status' => Testimonial::STATUS_PENDING_REVIEW,
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
                'featured' => false,
                'published_at' => null,
                'unpublished_at' => null,
            ])->save();

            $this->recordActivity($workspace, $actor, $testimonial, 'testimonial.submitted', 'Testimonial submitted for platform review.');

            return $testimonial->refresh();
        });

        $this->forgetHomepageCache();

        return $submitted;
    }

    public function approve(Testimonial $testimonial, User $actor): Testimonial
    {
        $approved = DB::transaction(function () use ($testimonial, $actor): Testimonial {
            $testimonial = $this->lockTestimonial($testimonial);
            $this->assertStatus($testimonial, [Testimonial::STATUS_PENDING_REVIEW], 'Only pending testimonials can be approved.');
            $this->assertConsent(['consent_confirmed' => $testimonial->consent_confirmed]);
            $testimonial->forceFill([
                'status' => Testimonial::STATUS_APPROVED,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
                'featured' => false,
            ])->save();

            $this->recordActivity($testimonial->workspace, $actor, $testimonial, 'testimonial.approved', 'Testimonial approved by platform moderation.');

            return $testimonial->refresh();
        });

        $this->forgetHomepageCache();
        $this->notifyWorkspace($approved, Testimonial::STATUS_APPROVED);

        return $approved;
    }

    public function reject(Testimonial $testimonial, User $actor, string $reason): Testimonial
    {
        $rejected = DB::transaction(function () use ($testimonial, $actor, $reason): Testimonial {
            $testimonial = $this->lockTestimonial($testimonial);
            $this->assertStatus($testimonial, [Testimonial::STATUS_PENDING_REVIEW], 'Only pending testimonials can be rejected.');
            $this->assertConsent(['consent_confirmed' => $testimonial->consent_confirmed]);
            $testimonial->forceFill([
                'status' => Testimonial::STATUS_REJECTED,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => trim($reason),
                'featured' => false,
                'published_at' => null,
                'unpublished_at' => null,
            ])->save();

            $this->recordActivity($testimonial->workspace, $actor, $testimonial, 'testimonial.rejected', 'Testimonial rejected by platform moderation.', [
                'reason' => trim($reason),
            ]);

            return $testimonial->refresh();
        });

        $this->forgetHomepageCache();
        $this->notifyWorkspace($rejected, Testimonial::STATUS_REJECTED, $reason);

        return $rejected;
    }

    public function publish(Testimonial $testimonial, User $actor): Testimonial
    {
        $published = DB::transaction(function () use ($testimonial, $actor): Testimonial {
            $testimonial = $this->lockTestimonial($testimonial);
            $this->assertStatus($testimonial, [Testimonial::STATUS_APPROVED, Testimonial::STATUS_UNPUBLISHED], 'Only approved testimonials can be published.');
            $this->assertConsent(['consent_confirmed' => $testimonial->consent_confirmed]);
            $testimonial->forceFill([
                'status' => Testimonial::STATUS_PUBLISHED,
                'published_at' => now(),
                'unpublished_at' => null,
            ])->save();

            $this->recordActivity($testimonial->workspace, $actor, $testimonial, 'testimonial.published', 'Testimonial published on the IDT homepage.');

            return $testimonial->refresh();
        });

        $this->forgetHomepageCache();
        $this->notifyWorkspace($published, Testimonial::STATUS_PUBLISHED);

        return $published;
    }

    public function unpublish(Testimonial $testimonial, User $actor): Testimonial
    {
        $unpublished = DB::transaction(function () use ($testimonial, $actor): Testimonial {
            $testimonial = $this->lockTestimonial($testimonial);
            $this->assertStatus($testimonial, [Testimonial::STATUS_PUBLISHED], 'Only published testimonials can be unpublished.');
            $testimonial->forceFill([
                'status' => Testimonial::STATUS_UNPUBLISHED,
                'featured' => false,
                'unpublished_at' => now(),
            ])->save();

            $this->recordActivity($testimonial->workspace, $actor, $testimonial, 'testimonial.unpublished', 'Testimonial unpublished from the IDT homepage.');

            return $testimonial->refresh();
        });

        $this->forgetHomepageCache();

        return $unpublished;
    }

    public function returnToDraft(Testimonial $testimonial, User $actor, ?string $reason = null): Testimonial
    {
        $draft = DB::transaction(function () use ($testimonial, $actor, $reason): Testimonial {
            $testimonial = $this->lockTestimonial($testimonial);
            $this->assertStatus($testimonial, [
                Testimonial::STATUS_PENDING_REVIEW,
                Testimonial::STATUS_APPROVED,
                Testimonial::STATUS_REJECTED,
                Testimonial::STATUS_UNPUBLISHED,
            ], 'This testimonial cannot be returned to draft from its current status.');
            $testimonial->forceFill([
                'status' => Testimonial::STATUS_DRAFT,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'rejection_reason' => filled($reason) ? trim($reason) : 'Returned for correction by platform review.',
                'featured' => false,
                'published_at' => null,
                'unpublished_at' => null,
            ])->save();

            $this->recordActivity($testimonial->workspace, $actor, $testimonial, 'testimonial.returned_to_draft', 'Testimonial returned to the workspace for correction.', [
                'reason' => $testimonial->rejection_reason,
            ]);

            return $testimonial->refresh();
        });

        $this->forgetHomepageCache();
        $this->notifyWorkspace($draft, 'returned_for_correction', $draft->rejection_reason);

        return $draft;
    }

    public function setPresentation(Testimonial $testimonial, User $actor, bool $featured, int $displayOrder): Testimonial
    {
        $updated = DB::transaction(function () use ($testimonial, $actor, $featured, $displayOrder): Testimonial {
            $testimonial = $this->lockTestimonial($testimonial);
            $this->assertStatus($testimonial, [
                Testimonial::STATUS_APPROVED,
                Testimonial::STATUS_PUBLISHED,
            ], 'Only approved or published testimonials can be featured.');
            $testimonial->forceFill([
                'featured' => $featured,
                'display_order' => $displayOrder,
            ])->save();

            $this->recordActivity($testimonial->workspace, $actor, $testimonial, 'testimonial.presentation_updated', 'Testimonial homepage presentation updated.', [
                'featured' => $featured,
                'display_order' => $displayOrder,
            ]);

            return $testimonial->refresh();
        });

        $this->forgetHomepageCache();

        return $updated;
    }

    public function homepageTestimonials(int $limit = 6): Collection
    {
        $testimonialIds = Cache::remember(
            self::HOMEPAGE_CACHE_KEY,
            now()->addMinutes(10),
            fn (): array => $this->homepageTestimonialIds($limit),
        );

        if (! is_array($testimonialIds)) {
            Cache::forget(self::HOMEPAGE_CACHE_KEY);
            $testimonialIds = $this->homepageTestimonialIds($limit);
            Cache::put(self::HOMEPAGE_CACHE_KEY, $testimonialIds, now()->addMinutes(10));
        }

        if ($testimonialIds === []) {
            return (new Testimonial)->newCollection();
        }

        $displayOrder = collect($testimonialIds)->mapWithKeys(
            fn (int|string $id, int $index): array => [(string) $id => $index],
        );

        $testimonials = Testimonial::query()
            ->with('workspace')
            ->publiclyVisible()
            ->whereKey($testimonialIds)
            ->get()
            ->sortBy(fn (Testimonial $testimonial): int => $displayOrder[(string) $testimonial->getKey()] ?? PHP_INT_MAX)
            ->values();

        return (new Testimonial)->newCollection($testimonials->all());
    }

    public function forgetHomepageCache(): void
    {
        Cache::forget(self::HOMEPAGE_CACHE_KEY);
    }

    /**
     * @return array<int, int>
     */
    private function homepageTestimonialIds(int $limit): array
    {
        return Testimonial::query()
            ->publiclyVisible()
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->all();
    }

    private function lockTestimonial(Testimonial $testimonial): Testimonial
    {
        return Testimonial::query()
            ->with('workspace')
            ->lockForUpdate()
            ->findOrFail($testimonial->id);
    }

    private function assertWorkspaceEditable(Testimonial $testimonial): void
    {
        $this->assertStatus($testimonial, Testimonial::WORKSPACE_EDITABLE_STATUSES, 'Only draft or rejected testimonials can be changed by workspace users.');
    }

    private function assertStatus(Testimonial $testimonial, array $allowedStatuses, string $message): void
    {
        if (! in_array($testimonial->status, $allowedStatuses, true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    private function assertConsent(array $attributes): void
    {
        if (! filter_var($attributes['consent_confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw ValidationException::withMessages([
                'consent_confirmed' => 'Consent is required before this testimonial can be saved or submitted.',
            ]);
        }
    }

    /**
     * @return array{display_name: string, job_title: ?string, business_name: ?string, content: string, rating: int}
     */
    private function contentAttributes(array $attributes): array
    {
        return [
            'display_name' => trim(strip_tags((string) $attributes['display_name'])),
            'job_title' => filled($attributes['job_title'] ?? null) ? trim(strip_tags((string) $attributes['job_title'])) : null,
            'business_name' => filled($attributes['business_name'] ?? null) ? trim(strip_tags((string) $attributes['business_name'])) : null,
            'content' => trim(strip_tags((string) $attributes['content'])),
            'rating' => (int) $attributes['rating'],
        ];
    }

    private function storeImage(Workspace $workspace, ?UploadedFile $image): ?string
    {
        if (! $image) {
            return null;
        }

        $path = $image->store('testimonials/'.$workspace->id, 'public');

        if ($path === false) {
            throw ValidationException::withMessages([
                'image' => 'The testimonial image could not be stored. Please try again.',
            ]);
        }

        return $path;
    }

    private function deleteImage(string|false|null $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function recordActivity(Workspace $workspace, User $actor, Testimonial $testimonial, string $type, string $description, array $properties = []): void
    {
        ActivityLog::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $actor->id,
            'type' => $type,
            'description' => $description,
            'properties' => array_merge([
                'testimonial_id' => $testimonial->id,
                'status' => $testimonial->status,
            ], $properties),
        ]);
    }

    private function notifyWorkspace(Testimonial $testimonial, string $status, ?string $reason = null): void
    {
        try {
            $recipients = $testimonial->workspace
                ->users()
                ->wherePivot('is_active', true)
                ->wherePivotIn('role', ['owner', 'admin'])
                ->get();

            if ($testimonial->created_by) {
                $recipients = $recipients->push($testimonial->creator)->filter();
            }

            Notification::send(
                $recipients->unique('id'),
                new TestimonialStatusNotification($testimonial->id, $status, $reason),
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
