<?php

namespace Tests\Unit;

use App\Support\GoogleDriveFolder;
use App\Support\GoogleSlide;
use App\Support\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GoogleIdParsingTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string}>
     */
    public static function folders(): array
    {
        return [
            'folder url' => ['https://drive.google.com/drive/folders/1AbCdEfGhIjKlMn?usp=sharing', '1AbCdEfGhIjKlMn'],
            'user folder url' => ['https://drive.google.com/drive/u/0/folders/1AbCdEfGhIjKlMn', '1AbCdEfGhIjKlMn'],
            'open id url' => ['https://drive.google.com/open?id=1AbCdEfGhIjKlMn', '1AbCdEfGhIjKlMn'],
            'raw id' => ['1AbCdEfGhIjKlMn', '1AbCdEfGhIjKlMn'],
            'garbage' => ['not a folder', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('folders')]
    public function test_drive_folder_ids_are_parsed(string $input, ?string $expected): void
    {
        $this->assertSame($expected, GoogleDriveFolder::idFrom($input));
    }

    public function test_slides_ids_are_parsed(): void
    {
        $this->assertSame('1AbCdEfGhIjKlMnOp', GoogleSlide::idFrom('https://docs.google.com/presentation/d/1AbCdEfGhIjKlMnOp/edit#slide=id.p'));
        $this->assertSame('local-badge', GoogleSlide::idFrom('local-badge'));
        $this->assertNull(GoogleSlide::idFrom('hello world'));
    }

    public function test_money_uses_exact_cent_arithmetic(): void
    {
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
        $this->assertSame('-5.25', Money::sub('4.75', '10'));
        $this->assertSame('37.50', Money::mul('12.50', 3));
        $this->assertSame(0, Money::compare('10', '10.00'));
        $this->assertSame('1.01', Money::normalize('1.005'));
    }
}
