<?php

namespace App\Notifications;

use App\Models\Testimonial;
use App\Services\PlatformConfigurationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TestimonialStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $testimonialId,
        public string $status,
        public ?string $reason = null,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $testimonial = Testimonial::query()->with('workspace')->find($this->testimonialId);
        $statusLabel = str($this->status)->replace('_', ' ')->title();

        $message = (new MailMessage)
            ->subject('Testimonial update: '.$statusLabel)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A testimonial for '.$testimonial?->workspace?->name.' is now '.$statusLabel.'.');

        if ($this->reason) {
            $message->line('Review note: '.$this->reason);
        }

        if ($testimonial?->workspace && $testimonial->exists) {
            $message->action('View testimonial', route('testimonials.show', [
                'workspace' => $testimonial->workspace,
                'testimonial' => $testimonial,
            ]));
        }

        return $message->line('This notification was sent by '.app(PlatformConfigurationService::class)->settings()->product_name.'.');
    }
}
