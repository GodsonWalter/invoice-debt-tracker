<?php

namespace Database\Factories;

use App\Models\Testimonial;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'created_by' => User::factory(),
            'display_name' => fake()->name(),
            'job_title' => fake()->jobTitle(),
            'business_name' => fake()->company(),
            'content' => fake()->paragraph(),
            'rating' => fake()->numberBetween(1, 5),
            'status' => Testimonial::STATUS_DRAFT,
            'consent_confirmed' => true,
            'consented_at' => now(),
            'consented_by' => User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state([
            'status' => Testimonial::STATUS_PUBLISHED,
            'submitted_at' => now()->subDays(2),
            'reviewed_at' => now()->subDay(),
            'published_at' => now(),
            'featured' => true,
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state([
            'status' => Testimonial::STATUS_PENDING_REVIEW,
            'submitted_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => Testimonial::STATUS_REJECTED,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now(),
            'rejection_reason' => 'Please provide a clearer customer role.',
        ]);
    }

    public function withoutConsent(): static
    {
        return $this->state([
            'consent_confirmed' => false,
            'consented_at' => null,
            'consented_by' => null,
        ]);
    }
}
