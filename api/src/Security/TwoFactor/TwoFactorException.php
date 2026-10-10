<?php

namespace App\Security\TwoFactor;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class TwoFactorException extends UnprocessableEntityHttpException
{
}
