<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

class AdminResetPassword extends ResetPassword
{
    protected function resetUrl($notifiable)
    {
        return route('admin.password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
    }
}
