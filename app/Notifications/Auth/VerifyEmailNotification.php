<?php

namespace App\Notifications\Auth;

use Illuminate\Auth\Notifications\VerifyEmail;

/**
 * Laravel's email verification message, sent immediately (not queued) so the user receives it while waiting on the verify screen.
 */
class VerifyEmailNotification extends VerifyEmail {}
