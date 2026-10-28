<?php

declare(strict_types=1);

namespace Mzc\Brevo\Tests\Occasion;

use Mzc\Brevo\Occasion\OccasionDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OccasionDetectorTest extends TestCase
{
    public static function names(): array
    {
        return [
            'christening' => ['Plakat na pamiątkę Chrztu Świętego', OccasionDetector::CHRISTENING],
            'christening party' => ['Obrazek na chrzciny z imieniem', OccasionDetector::CHRISTENING],
            'communion' => ['Pamiątka Pierwszej Komunii Świętej', OccasionDetector::COMMUNION],
            'communion adjective' => ['Obrazek komunijny', OccasionDetector::COMMUNION],
            'confirmation' => ['Pamiątka bierzmowania', OccasionDetector::CONFIRMATION],
            'wedding' => ['Plakat na ŚLUB z datą', OccasionDetector::WEDDING],
            'wedding party' => ['Podziękowania weselne', OccasionDetector::WEDDING],
            'wedding vows' => ['Pamiątka zaślubin', OccasionDetector::WEDDING],
            'no occasion' => ['Otulony Bożą miłością — plakat', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('names')]
    public function testDetectsTheOccasionFromTheName(string $name, ?string $expected): void
    {
        self::assertSame($expected, OccasionDetector::fromName($name));
    }

    public function testNormalizesCaseAndPolishLetters(): void
    {
        self::assertSame('zazolc gesla jazn', OccasionDetector::normalize('ZAŻÓŁĆ GĘŚLĄ JAŹŃ'));
    }

    public function testManualChoiceWinsOverCategoryAndName(): void
    {
        $result = OccasionDetector::resolve(OccasionDetector::WEDDING, [10], [10 => OccasionDetector::COMMUNION], 'Pamiątka chrztu');

        self::assertSame(['occasion' => OccasionDetector::WEDDING, 'source' => OccasionDetector::SOURCE_MANUAL], $result);
    }

    public function testManualNoneSuppressesEverything(): void
    {
        self::assertNull(OccasionDetector::resolve(OccasionDetector::NONE, [10], [10 => OccasionDetector::COMMUNION], 'Pamiątka chrztu'));
    }

    public function testCategoryWinsOverName(): void
    {
        $result = OccasionDetector::resolve(null, [3, 10], [10 => OccasionDetector::COMMUNION], 'Pamiątka chrztu');

        self::assertSame(['occasion' => OccasionDetector::COMMUNION, 'source' => OccasionDetector::SOURCE_CATEGORY], $result);
    }

    public function testFirstMappedCategoryInOrderWins(): void
    {
        $map = [3 => OccasionDetector::WEDDING, 10 => OccasionDetector::COMMUNION];

        self::assertSame(OccasionDetector::WEDDING, OccasionDetector::resolve('', [3, 10], $map, '')['occasion']);
        self::assertSame(OccasionDetector::COMMUNION, OccasionDetector::resolve('', [10, 3], $map, '')['occasion']);
    }

    public function testNameIsTheLastResort(): void
    {
        $result = OccasionDetector::resolve('', [3], [10 => OccasionDetector::COMMUNION], 'Plakat ślubny');

        self::assertSame(['occasion' => OccasionDetector::WEDDING, 'source' => OccasionDetector::SOURCE_NAME], $result);
    }

    public function testNoHitIsNoOccasionNotOther(): void
    {
        self::assertNull(OccasionDetector::resolve(null, [3], [], 'Plakat z motylem'));
    }

    public function testOtherExistsOnlyAsManualChoice(): void
    {
        self::assertSame(OccasionDetector::OTHER, OccasionDetector::resolve(OccasionDetector::OTHER, [], [], '')['occasion']);
        self::assertNull(OccasionDetector::resolve(null, [3], [3 => 'unknown'], ''));
    }
}
