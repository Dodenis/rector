<?php declare(strict_types=1);

namespace Rector\SOLID\Tests\Rector\ClassConst\PrivatizeLocalClassConstantRector;

use Rector\SOLID\Rector\ClassConst\PrivatizeLocalClassConstantRector;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class PrivatizeLocalClassConstantRectorTest extends AbstractRectorTestCase
{
    public function test(): void
    {

        $this->doTestFiles([
            __DIR__ . '/Fixture/fixture.php.inc',
            __DIR__ . '/Fixture/keep.php.inc',
        ]);
    }

    protected function getRectorClass(): string
    {
        return PrivatizeLocalClassConstantRector::class;
    }
}
