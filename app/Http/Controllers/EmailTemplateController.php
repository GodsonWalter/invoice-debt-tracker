<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewEmailTemplateRequest;
use App\Http\Requests\StoreEmailTemplateRequest;
use App\Http\Requests\UpdateEmailTemplateRequest;
use App\Models\EmailTemplate;
use App\Models\Workspace;
use App\Services\TemplateRenderer;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function index(Workspace $workspace): View
    {
        $this->authorizeWorkspaceUser($workspace);

        return view('email-templates.index', [
            'workspace' => $workspace,
            'emailTemplates' => $workspace->emailTemplates()
                ->orderBy('type')
                ->paginate(10),
        ]);
    }

    public function create(Workspace $workspace, Request $request): View
    {
        $this->authorizeWorkspaceUser($workspace);

        return view('email-templates.create', [
            'workspace' => $workspace,
            'emailTemplate' => new EmailTemplate([
                'type' => $request->string('type')->toString() ?: EmailTemplate::TYPE_BEFORE_DUE,
                'is_active' => true,
            ]),
            'invoices' => $this->previewInvoices($workspace),
            'placeholders' => EmailTemplate::PLACEHOLDERS,
            'preview' => session('template_preview'),
        ]);
    }

    public function store(StoreEmailTemplateRequest $request, Workspace $workspace): RedirectResponse
    {
        $emailTemplate = $workspace->emailTemplates()->create([
            ...$request->validated(),
            'is_default' => false,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('email-templates.edit', [$workspace, $emailTemplate])
            ->with('success', 'Email template created successfully.');
    }

    public function edit(Workspace $workspace, EmailTemplate $emailTemplate): View
    {
        $this->authorizeWorkspaceUser($workspace);
        $emailTemplate = $this->workspaceTemplate($workspace, $emailTemplate);

        return view('email-templates.edit', [
            'workspace' => $workspace,
            'emailTemplate' => $emailTemplate,
            'invoices' => $this->previewInvoices($workspace),
            'placeholders' => EmailTemplate::PLACEHOLDERS,
            'preview' => session('template_preview'),
        ]);
    }

    public function update(UpdateEmailTemplateRequest $request, Workspace $workspace, EmailTemplate $emailTemplate): RedirectResponse
    {
        $emailTemplate = $this->workspaceTemplate($workspace, $emailTemplate);

        $emailTemplate->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('email-templates.index', $workspace)
            ->with('success', 'Email template updated successfully.');
    }

    public function destroy(Workspace $workspace, EmailTemplate $emailTemplate): RedirectResponse
    {
        $this->authorizeWorkspaceUser($workspace);
        $emailTemplate = $this->workspaceTemplate($workspace, $emailTemplate);

        $emailTemplate->delete();

        return redirect()
            ->route('email-templates.index', $workspace)
            ->with('success', 'Email template deleted successfully.');
    }

    public function preview(PreviewEmailTemplateRequest $request, Workspace $workspace, TemplateRenderer $templateRenderer): RedirectResponse
    {
        $invoice = $workspace->invoices()
            ->with(['client', 'payments', 'currency', 'workspace.businessProfile', 'workspace.currency'])
            ->findOrFail($request->integer('invoice_id'));

        $type = $request->string('type')->toString();
        $reminderType = match ($type) {
            EmailTemplate::TYPE_DUE_TODAY => 'Due Today',
            EmailTemplate::TYPE_OVERDUE => 'Overdue',
            default => 'Before Due',
        };

        return back()
            ->withInput()
            ->with('template_preview', [
                'subject' => $templateRenderer->render($request->string('subject')->toString(), $invoice, [
                    'reminder_type' => $reminderType,
                ]),
                'body' => $templateRenderer->render($request->string('body')->toString(), $invoice, [
                    'reminder_type' => $reminderType,
                ]),
                'invoice_number' => $invoice->invoice_number,
            ]);
    }

    private function authorizeWorkspaceUser(Workspace $workspace): void
    {
        if (! $workspace->users()->where('user_id', Auth::id())->where('workspace_user.is_active', true)->exists()) {
            throw new HttpResponseException(
                redirect()->route('dashboard')->with('error', 'You are not authorized to manage templates for this workspace.')
            );
        }
    }

    private function workspaceTemplate(Workspace $workspace, EmailTemplate $emailTemplate): EmailTemplate
    {
        return $workspace->emailTemplates()->where('id', $emailTemplate->id)->firstOrFail();
    }

    private function previewInvoices(Workspace $workspace)
    {
        return $workspace->invoices()
            ->with('client')
            ->latest()
            ->limit(50)
            ->get();
    }
}
