<?php

namespace App\Dto;

use App\EventAccounts\EntryAmounts;
use App\EventAccounts\EntryDraft;
use App\EventAccounts\EntryKind;
use App\Membership\Money;
use Symfony\Component\Validator\Constraints as Assert;

/** Dépense ou recette hors buvette : montant prévu, puis réel une fois connu. */
final class AccountsEntryInput
{
    private const MONEY = '/^\d{1,6}([.,]\d{1,2})?$/';

    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Choice(callback: [self::class, 'kinds'])]
        public readonly string $kind = '',

        #[Assert\NotBlank]
        #[Assert\Length(max: EntryDraft::LABEL_MAX)]
        public readonly string $label = '',

        #[Assert\NotBlank]
        #[Assert\Regex(self::MONEY, message: 'Montant invalide.')]
        public readonly string $planned = '',

        #[Assert\Regex(self::MONEY, message: 'Montant invalide.')]
        public readonly ?string $actual = null,
    ) {
    }

    /** @return list<string> */
    public static function kinds(): array
    {
        return array_map(static fn (EntryKind $kind): string => $kind->value, EntryKind::cases());
    }

    public function toDraft(): EntryDraft
    {
        $actual = null === $this->actual || '' === $this->actual ? null : Money::fromDecimal($this->actual);

        return new EntryDraft(EntryKind::from($this->kind), $this->label, new EntryAmounts(Money::fromDecimal($this->planned), $actual));
    }
}
