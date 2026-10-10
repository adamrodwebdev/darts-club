<?php

namespace App\Tests\Double;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Client HTTP du hub Mercure en test : enregistre les publications. */
final class MercureRecorder extends MockHttpClient
{
    /** @var list<array{topics: list<string>, data: string, private: bool}> */
    public array $published = [];

    public function __construct()
    {
        parent::__construct(function (string $method, string $url, array $options): MockResponse {
            $body = $options['body'] ?? '';
            $this->published[] = self::parse(\is_string($body) ? $body : '');

            return new MockResponse('urn:uuid:'.bin2hex(random_bytes(8)));
        });
    }

    /** @return array{topics: list<string>, data: string, private: bool} */
    private static function parse(string $body): array
    {
        $topics = [];
        $data = '';
        $private = false;
        foreach (explode('&', $body) as $pair) {
            [$key, $value] = array_map('urldecode', explode('=', $pair, 2) + [1 => '']);
            match ($key) {
                'topic' => $topics[] = $value,
                'data' => $data = $value,
                'private' => $private = 'on' === $value,
                default => null,
            };
        }

        return ['topics' => $topics, 'data' => $data, 'private' => $private];
    }
}
