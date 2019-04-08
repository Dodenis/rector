<?php declare(strict_types=1);

namespace Rector\SOLID\Rector\ClassConst;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use Rector\NodeTypeResolver\Application\ConstantNodeCollector;
use Rector\NodeTypeResolver\Node\Attribute;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

final class PrivatizeLocalClassConstantRector extends AbstractRector
{
    /**
     * @var ConstantNodeCollector
     */
    private $constantNodeCollector;

    public function __construct(ConstantNodeCollector $constantNodeCollector)
    {
        $this->constantNodeCollector = $constantNodeCollector;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Finalize every class constant that is used only locally', [
            new CodeSample(
                <<<'CODE_SAMPLE'
class ClassWithConstantUsedOnlyHere
{
    const LOCAL_ONLY = true;

    public function isLocalOnly()
    {
        return self::LOCAL_ONLY;
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
class ClassWithConstantUsedOnlyHere
{
    private const LOCAL_ONLY = true;

    public function isLocalOnly()
    {
        return self::LOCAL_ONLY;
    }
}
CODE_SAMPLE
            ),
        ]);
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [ClassConst::class];
    }

    /**
     * @param ClassConst $node
     */
    public function refactor(Node $node): ?Node
    {
        // change the node
        $class = $node->getAttribute(Attribute::CLASS_NAME);
        $constant = $this->getName($node);

        $classConstantFetches = $this->constantNodeCollector->findClassConstantFetches($class, $constant);

        if ($classConstantFetches === null) {
            // never used, make private
            $node->flags |= Node\Stmt\Class_::MODIFIER_PRIVATE;
        } else {
            // @todo
//            dump($classConstantFetches);
//            die;
        }

        return $node;
    }
}
