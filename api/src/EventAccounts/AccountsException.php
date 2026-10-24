<?php

namespace App\EventAccounts;

use App\Translation\UnprocessableException;

/** Règle du bilan non respectée (bilan clôturé…), message traduit pour le bureau. */
final class AccountsException extends UnprocessableException
{
}
