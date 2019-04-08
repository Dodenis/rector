<?php declare(strict_types=1);

namespace Rector\NodeTypeResolver\Application;

use Nette\Utils\Strings;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Stmt\ClassConst;
use Rector\Exception\ShouldNotHappenException;
use Rector\NodeTypeResolver\Node\Attribute;
use Rector\PhpParser\Node\Resolver\NameResolver;

final class ConstantNodeCollector
{
    /**
     * @var ClassConst[][]
     */
    private $constantsByType = [];

    /**
     * @var NameResolver
     */
    private $nameResolver;

    /**
     * @var ClassConstFetch[][][]
     */
    private $classConstantFetchByClassAndName = [];

    public function __construct(NameResolver $nameResolver)
    {
        $this->nameResolver = $nameResolver;
    }

    public function addClassConstant(ClassConst $classConst): void
    {
        $className = $classConst->getAttribute(Attribute::CLASS_NAME);
        if ($className === null) {
            throw new ShouldNotHappenException();
        }

        $constantName = $this->nameResolver->resolve($classConst);

        $this->constantsByType[$className][$constantName] = $classConst;
    }

    public function addClassConstantFetch(ClassConstFetch $classConstFetch): void
    {
        $className = $this->nameResolver->resolve($classConstFetch->class);
        if (in_array($className, ['static', 'self', 'parent'], true)) {
            // we record only foreign class usage
            return;
        }

        $constantName = $this->nameResolver->resolve($classConstFetch->name);
        if ($constantName === 'class') {
            // not a manual constant
            return;
        }

        $this->classConstantFetchByClassAndName[$className][$constantName][] = $classConstFetch;
    }

    public function findClassConstant(string $className, string $constantName): ?ClassConst
    {
        if (Strings::contains($constantName, '\\')) {
            throw new ShouldNotHappenException(sprintf('Switched arguments in "%s"', __METHOD__));
        }

        return $this->constantsByType[$className][$constantName] ?? null;
    }

    /**
     * @return ClassConstFetch[]|null
     */
    public function findClassConstantFetches(string $className, string $constantName): ?array
    {
        return $this->classConstantFetchByClassAndName[$className][$constantName] ?? null;
    }
}
