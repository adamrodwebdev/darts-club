<?php

namespace App\Profile;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class InvalidAvatarException extends UnprocessableEntityHttpException
{
}
