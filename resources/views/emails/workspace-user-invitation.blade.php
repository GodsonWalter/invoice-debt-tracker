<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f8f9fa; padding: 20px; border-radius: 8px 8px 0 0; }
        .content { background-color: #ffffff; padding: 20px; border: 1px solid #e0e0e0; }
        .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; border: 1px solid #e0e0e0; border-top: none; }
        .button { display: inline-block; background-color: #d6dbe4; color: rgb(17, 17, 17); padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        h2 { color: #0d6efd; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Workspace Invitation</h2>
        </div>
        <div class="content">
            <p>Hello {{ $user->name }},</p>
            
            <p>You have been invited to join the <strong>{{ $workspace->name }}</strong> workspace as a <strong>{{ ucfirst($role) }}</strong>.</p>
            
            <p>
                <a href="{{ route('dashboard') }}" class="button">View Your Workspaces</a>
            </p>
            
            <p>If you don't have an account yet, you can create one using this email address. Once logged in, you'll have access to this workspace.</p>
            
            <p>
                Best regards,<br>
                {{ config('app.name') }}
            </p>
        </div>
        <div class="footer">
            <p>This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
