<?php

namespace App\Media;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class InvalidImageException extends UnprocessableEntityHttpException
{
}
