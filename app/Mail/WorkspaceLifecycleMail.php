<?php

namespace App\Mail;

use App\Models\WorkspaceLifecycleNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceLifecycleMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public WorkspaceLifecycleNotification $notification) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->lifecycleSubject());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.workspace-lifecycle',
            with: [
                'notification' => $this->notification,
                'details' => $this->details(),
                'recoveryUrl' => route('workspace.recovery.index'),
            ],
        );
    }

    private function lifecycleSubject(): string
    {
        return match ($this->notification->notification_type) {
            'owner_restored' => 'Workspace restored',
            'platform_restored' => 'Workspace restored through platform support',
            'owner_restore_expired' => 'Workspace self-service recovery has expired',
            'owner_restore_warning' => 'Workspace recovery deadline approaching',
            'permanent_deletion_warning' => 'Workspace permanent deletion approaching',
            'permanently_deleted' => 'Workspace permanently deleted',
            default => 'Workspace moved to recovery',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function details(): array
    {
        return $this->notification->metadata ?? [];
    }
}
