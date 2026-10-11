<?php

namespace App\Admin\Resources;

use App\Admin\Field;
use App\Admin\Resource;
use App\Models\NewsletterSubscriber;

/**
 * Homepage "Hear about new sailings first" sign-ups. Listed and removable; added only by guests.
 */
class NewsletterSubscriberResource extends Resource
{
    public static string $model = NewsletterSubscriber::class;

    public static string $slug = 'newsletter';

    public static string $label = 'Newsletter';

    public static string $singular = 'Subscriber';

    public static string $title = 'email';

    public static array $search = ['email'];

    public static function fields(): array
    {
        return [
            Field::email('email', 'Email')->rules(['required', 'email:rfc,strict'])->index(),
            Field::date('subscribed_at', 'Subscribed')->indexOnly(),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
