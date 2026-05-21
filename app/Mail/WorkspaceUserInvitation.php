<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceUserInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Workspace $workspace,
        public User $user,
        public string $role,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to {$this->workspace->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.workspace-user-invitation',
        );
    }
}
