<?php

namespace App\Notification;

/**
 * Canaux optionnels (email, push) activés par catégorie.
 * Par défaut tout est activé ; le centre de notifications l'est toujours.
 */
final readonly class NotificationPreferences
{
    /** @param array<string, list<Channel>> $channels catégorie => canaux optionnels */
    private function __construct(
        private array $channels,
    ) {
    }

    public static function defaults(): self
    {
        $channels = [];
        foreach (Category::cases() as $category) {
            $channels[$category->value] = Channel::optional();
        }

        return new self($channels);
    }

    /**
     * Hydratation tolérante : valeurs inconnues ignorées, catégories absentes = défaut.
     *
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $preferences = self::defaults()->channels;

        foreach (Category::cases() as $category) {
            $stored = $data[$category->value] ?? null;
            if (!\is_array($stored)) {
                continue;
            }

            $preferences[$category->value] = array_values(array_filter(
                Channel::optional(),
                static fn (Channel $channel): bool => \in_array($channel->value, $stored, true),
            ));
        }

        return new self($preferences);
    }

    public function allows(Category $category, Channel $channel): bool
    {
        return Channel::InApp === $channel || \in_array($channel, $this->channels[$category->value] ?? [], true);
    }

    public function with(Category $category, Channel ...$channels): self
    {
        $preferences = $this->channels;
        $preferences[$category->value] = array_values(array_filter(
            Channel::optional(),
            static fn (Channel $channel): bool => \in_array($channel, $channels, true),
        ));

        return new self($preferences);
    }

    /** @return array<string, list<string>> */
    public function toArray(): array
    {
        return array_map(
            static fn (array $channels): array => array_map(static fn (Channel $c): string => $c->value, $channels),
            $this->channels,
        );
    }
}
