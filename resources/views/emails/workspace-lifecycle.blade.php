<h1>{{ $notification->workspace_name }}</h1>

@php
    $type = $notification->notification_type;
    $days = $details['days'] ?? null;
@endphp

@if ($type === 'deletion_requested')
    <p>Your workspace has been moved to recovery status. Your workspace data has not been deleted.</p>
@elseif ($type === 'owner_restored')
    <p>Your workspace and its data have been restored and are available again.</p>
@elseif ($type === 'platform_restored')
    <p>Your workspace was restored through platform support.</p>
@elseif ($type === 'owner_restore_expired')
    <p>Your self-service recovery period has expired. Please contact platform support before permanent deletion.</p>
@elseif ($type === 'owner_restore_warning')
    <p>Your self-service recovery period expires in {{ $days }} day(s).</p>
@elseif ($type === 'permanent_deletion_warning')
    <p>This workspace is scheduled for permanent deletion in {{ $days }} day(s).</p>
@else
    <p>This workspace has been permanently deleted.</p>
@endif

<p>Workspace: {{ $notification->workspace_name }}</p>
<p>Deletion date: {{ $details['deleted_at'] ?? 'Unavailable' }}</p>
<p>Owner recovery deadline: {{ $details['restore_deadline'] ?? 'Unavailable' }}</p>
<p>Permanent deletion date: {{ $details['permanent_deletion_deadline'] ?? 'Unavailable' }}</p>

@if (in_array($type, ['deletion_requested', 'owner_restore_warning'], true))
    <p><a href="{{ $recoveryUrl }}">Open the Recovery Center</a></p>
@endif
