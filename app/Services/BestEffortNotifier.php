<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Sends emails that accompany an action which has already been saved. A mail server problem is logged rather than shown
 * as an error page, and one rejected address does not stop the other recipients from getting their copy.
 */
class BestEffortNotifier
{
    /**
     * @param  User|iterable<User>  $recipients
     * @return bool Whether at least one recipient's copy was handed over for delivery.
     */
    public static function send(User|iterable $recipients, Notification $notification): bool
    {
        $isHandedOver = false;

        foreach ($recipients instanceof User ? [$recipients] : $recipients as $recipient) {
            $isHandedOverToRecipient = rescue(function () use ($recipient, $notification): bool {
                $recipient->notify($notification);

                return true;
            }, false);

            $isHandedOver = $isHandedOver || $isHandedOverToRecipient;
        }

        return $isHandedOver;
    }
}
