<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * Laravel's password reset message, sent immediately (not queued) so the user receives it while waiting.
 */
class ResetPasswordNotification extends ResetPassword {}
