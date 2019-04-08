<?php declare(strict_types=1);

namespace Rector\SOLID\Tests\Rector\ClassConst\PrivatizeLocalClassConstantRector;

use Rector\Testing\PHPUnit\AbstractRectorTestCase;

final class PrivatizeLocalClassConstantRectorTest extends AbstractRectorTestCase
{
    public function test(): void
    {
        $this->doTestFiles([
            __DIR__ . '/Fixture/fixture.php.inc'
        ]);
    }

    protected function getRectorClass(): string
    {
        return \Rector\SOLID\Rector\ClassConst\PrivatizeLocalClassConstantRector::class;
    }
}
