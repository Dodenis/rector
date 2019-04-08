<?php declare(strict_types=1);

namespace Rector\PhpParser\Node\Value;

use PhpParser\ConstExprEvaluator;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Scalar\MagicConst\File;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\ConstantScalarType;
use Rector\Exception\ShouldNotHappenException;
use Rector\NodeTypeResolver\Application\ConstantNodeCollector;
use Rector\NodeTypeResolver\Node\Attribute;
use Rector\NodeTypeResolver\NodeTypeResolver;
use Rector\PhpParser\Node\Resolver\NameResolver;
use Symplify\PackageBuilder\FileSystem\SmartFileInfo;

final class ValueResolver
{
    /**
     * @var NameResolver
     */
    private $nameResolver;

    /**
     * @var ConstExprEvaluator
     */
    private $constExprEvaluator;

    /**
     * @var ConstantNodeCollector
     */
    private $constantNodeCollector;

    /**
     * @var NodeTypeResolver
     */
    private $nodeTypeResolver;

    public function __construct(
        NameResolver $nameResolver,
        NodeTypeResolver $nodeTypeResolver,
        ConstantNodeCollector $constantNodeCollector
    ) {
        $this->nameResolver = $nameResolver;
        $this->constantNodeCollector = $constantNodeCollector;
        $this->nodeTypeResolver = $nodeTypeResolver;
    }

    /**
     * @return mixed|null
     */
    public function resolve(Expr $expr)
    {
        $value = $this->getConstExprEvaluator()->evaluateDirectly($expr);
        if ($value !== null) {
            return $value;
        }

        $nodeStaticType = $this->nodeTypeResolver->getNodeStaticType($expr);

        if ($nodeStaticType instanceof ConstantArrayType) {
            return $this->extractConstantArrayTypeValue($nodeStaticType);
        }

        if ($nodeStaticType instanceof ConstantScalarType) {
            return $nodeStaticType->getValue();
        }

        return null;
    }

    private function getConstExprEvaluator(): ConstExprEvaluator
    {
        if ($this->constExprEvaluator !== null) {
            return $this->constExprEvaluator;
        }

        $this->constExprEvaluator = new ConstExprEvaluator(function (Expr $expr) {
            if ($expr instanceof Dir) {
                // __DIR__
                return $this->resolveDirConstant($expr);
            }

            if ($expr instanceof File) {
                // __FILE__
                return $this->resolveFileConstant($expr);
            }

            // resolve "SomeClass::SOME_CONST"
            if ($expr instanceof ClassConstFetch) {
                return $this->resolveClassConstFetch($expr);
            }

            return null;
        });

        return $this->constExprEvaluator;
    }

    private function resolveDirConstant(Dir $dir): string
    {
        $fileInfo = $dir->getAttribute(Attribute::FILE_INFO);
        if (! $fileInfo instanceof SmartFileInfo) {
            throw new ShouldNotHappenException();
        }

        return $fileInfo->getPath();
    }

    private function resolveFileConstant(File $file): string
    {
        $fileInfo = $file->getAttribute(Attribute::FILE_INFO);
        if (! $fileInfo instanceof SmartFileInfo) {
            throw new ShouldNotHappenException();
        }

        return $fileInfo->getPathname();
    }

    /**
     * @return mixed
     */
    private function resolveClassConstFetch(ClassConstFetch $classConstFetch)
    {
        $class = $this->nameResolver->resolve($classConstFetch->class);
        $constant = $this->nameResolver->resolve($classConstFetch->name);

        if ($class === null) {
            throw new ShouldNotHappenException();
        }

        if ($constant === null) {
            throw new ShouldNotHappenException();
        }

        if ($class === 'self') {
            $class = (string) $classConstFetch->class->getAttribute(Attribute::CLASS_NAME);
        }

        if ($constant === 'class') {
            return $class;
        }

        $classConstNode = $this->constantNodeCollector->findClassConstant($class, $constant);

        if ($classConstNode === null) {
            // fallback to the name
            return $class . '::' . $constant;
        }

        return $this->constExprEvaluator->evaluateDirectly($classConstNode->consts[0]->value);
    }

    /**
     * @return mixed[]
     */
    private function extractConstantArrayTypeValue(ConstantArrayType $constantArrayType): array
    {
        $keys = [];
        foreach ($constantArrayType->getKeyTypes() as $i => $keyType) {
            /** @var ConstantScalarType $keyType */
            $keys[$i] = $keyType->getValue();
        }

        $values = [];
        foreach ($constantArrayType->getValueTypes() as $i => $valueType) {
            /** @var ConstantScalarType $valueType */
            $value = $valueType->getValue();
            $values[$keys[$i]] = $value;
        }

        return $values;
    }
}
