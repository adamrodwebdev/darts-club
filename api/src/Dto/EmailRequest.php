<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class EmailRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        #[Assert\Length(max: 180)]
        public readonly string $email = '',
    ) {
    }
}
