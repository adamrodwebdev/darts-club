<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/** Format de PushSubscription.toJSON() côté navigateur. */
final class PushSubscriptionInput
{
    /** @param array{p256dh: string, auth: string} $keys */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Url(protocols: ['https'], requireTld: true)]
        #[Assert\Length(max: 2048)]
        public readonly string $endpoint = '',

        #[Assert\Collection(
            fields: [
                'p256dh' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
                'auth' => [new Assert\NotBlank(), new Assert\Length(max: 255)],
            ],
            allowExtraFields: false,
        )]
        public readonly array $keys = ['p256dh' => '', 'auth' => ''],
    ) {
    }
}
