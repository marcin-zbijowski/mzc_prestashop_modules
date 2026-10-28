<?php

declare(strict_types=1);

namespace Mzc\Brevo\Tests\Mapper;

use Mzc\Brevo\Mapper\PhoneNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNormalizerTest extends TestCase
{
    public static function phones(): array
    {
        return [
            'polish, nine digits' => ['601 234 567', 'PL', '+48601234567'],
            'polish, dashes' => ['601-234-567', 'PL', '+48601234567'],
            'polish with prefix, no plus' => ['48601234567', 'PL', '+48601234567'],
            'polish with plus' => ['+48 601 234 567', 'PL', '+48601234567'],
            'double zero' => ['0048601234567', 'PL', '+48601234567'],
            'german with plus, polish address' => ['+49 151 23456789', 'PL', '+4915123456789'],
            'german national' => ['0151 23456789', 'DE', '+4915123456789'],
            'polish too short' => ['60123456', 'PL', null],
            'polish too long' => ['+48 601 234 5678', 'PL', null],
            'unknown country without plus' => ['601234567', 'XX', null],
            'letters' => ['brak', 'PL', null],
            'empty' => ['', 'PL', null],
        ];
    }

    #[DataProvider('phones')]
    public function testNormalizes(string $phone, string $country, ?string $expected): void
    {
        self::assertSame($expected, PhoneNormalizer::toE164($phone, $country));
    }
}
