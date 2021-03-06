<?php declare(strict_types=1);

namespace Rector\Jms\Rector\Property;

use Nette\Utils\Strings;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use Rector\Application\ErrorAndDiffCollector;
use Rector\Bridge\Contract\AnalyzedApplicationContainerInterface;
use Rector\Exception\ShouldNotHappenException;
use Rector\NodeTypeResolver\Node\Attribute;
use Rector\NodeTypeResolver\PhpDoc\NodeAnalyzer\DocBlockManipulator;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;
use Symplify\PackageBuilder\FileSystem\SmartFileInfo;

/**
 * @see https://jmsyst.com/bundles/JMSDiExtraBundle/master/annotations#inject
 */
final class JmsInjectAnnotationRector extends AbstractRector
{
    /**
     * @var string
     */
    private const INJECT_ANNOTATION = 'JMS\DiExtraBundle\Annotation\Inject';

    /**
     * @var DocBlockManipulator
     */
    private $docBlockManipulator;

    /**
     * @var AnalyzedApplicationContainerInterface
     */
    private $analyzedApplicationContainer;

    /**
     * @var ErrorAndDiffCollector
     */
    private $errorAndDiffCollector;

    public function __construct(
        DocBlockManipulator $docBlockManipulator,
        AnalyzedApplicationContainerInterface $analyzedApplicationContainer,
        ErrorAndDiffCollector $errorAndDiffCollector
    ) {
        $this->docBlockManipulator = $docBlockManipulator;
        $this->analyzedApplicationContainer = $analyzedApplicationContainer;
        $this->errorAndDiffCollector = $errorAndDiffCollector;
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition(
            'Changes properties with `@JMS\DiExtraBundle\Annotation\Inject` to constructor injection',
            [
                new CodeSample(
                    <<<'CODE_SAMPLE'
use JMS\DiExtraBundle\Annotation as DI;

class SomeController
{
    /**
     * @DI\Inject("entity.manager")
     */
    private $entityManager;
}
CODE_SAMPLE
                    ,
                    <<<'CODE_SAMPLE'
use JMS\DiExtraBundle\Annotation as DI;

class SomeController
{
    /**
     * @var EntityManager
     */
    private $entityManager;
    
    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = entityManager;
    }
}
CODE_SAMPLE
                ),
            ]
        );
    }

    /**
     * @return string[]
     */
    public function getNodeTypes(): array
    {
        return [Property::class];
    }

    /**
     * @param Property $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->docBlockManipulator->hasTag($node, self::INJECT_ANNOTATION)) {
            return null;
        }

        $type = $this->resolveType($node);
        if ($type === null) {
            return null;
        }

        $name = $this->getName($node);
        if ($name === null) {
            return null;
        }

        if (! $this->docBlockManipulator->hasTag($node, 'var')) {
            $this->docBlockManipulator->addVarTag($node, $type);
        }

        $this->docBlockManipulator->removeTagFromNode($node, self::INJECT_ANNOTATION);

        // set to private
        $node->flags = Class_::MODIFIER_PRIVATE;

        $classNode = $node->getAttribute(Attribute::CLASS_NODE);
        if (! $classNode instanceof Class_) {
            throw new ShouldNotHappenException();
        }

        $this->addPropertyToClass($classNode, $type, $name);

        return $node;
    }

    private function resolveType(Node $node): ?string
    {
        $injectTagNode = $this->docBlockManipulator->getTagByName($node, self::INJECT_ANNOTATION);

        $serviceName = $this->resolveServiceName($injectTagNode, $node);
        if ($serviceName) {
            if ($this->analyzedApplicationContainer->hasService($serviceName)) {
                return $this->analyzedApplicationContainer->getTypeForName($serviceName);
            }

            // collect error

            /** @var SmartFileInfo $fileInfo */
            $fileInfo = $node->getAttribute(Attribute::FILE_INFO);

            $this->errorAndDiffCollector->addErrorWithRectorClassMessageAndFileInfo(
                self::class,
                sprintf('Service "%s" was not found in DI Container of your Symfony App.', $serviceName),
                $fileInfo
            );
        }

        $varTypeInfo = $this->docBlockManipulator->getVarTypeInfo($node);
        if ($varTypeInfo === null) {
            return null;
        }

        return $varTypeInfo->getFqnType();
    }

    private function resolveServiceName(PhpDocTagNode $phpDocTagNode, Node $node): ?string
    {
        $injectTagContent = (string) $phpDocTagNode->value;
        $match = Strings::match($injectTagContent, '#(\'|")(?<serviceName>.*?)(\'|")#');

        if ($match['serviceName']) {
            return $match['serviceName'];
        }

        $match = Strings::match($injectTagContent, '#(\'|")%(?<parameterName>.*?)%(\'|")#');
        // it's parameter, we don't resolve that here
        if (isset($match['parameterName'])) {
            return null;
        }

        return $this->getName($node);
    }
}
