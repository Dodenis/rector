<?php declare(strict_types=1);

namespace Rector\Symfony\Rector\MethodCall;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use Rector\NodeTypeResolver\Node\Attribute;
use Rector\Rector\AbstractRector;
use Rector\RectorDefinition\CodeSample;
use Rector\RectorDefinition\RectorDefinition;

/**
 * @see https://symfony.com/blog/new-in-symfony-4-3-better-test-assertions
 * @see https://github.com/symfony/symfony/pull/30813/files
 */
final class SimplifyWebTestCaseAssertionsRector extends AbstractRector
{
    /**
     * @var string
     */
    private $webTestCaseClass;

    /**
     * @var MethodCall
     */
    private $getStatusCodeMethodCall;

    public function __construct(string $webTestCaseClass = 'Symfony\Bundle\FrameworkBundle\Test\WebTestCase')
    {
        $this->webTestCaseClass = $webTestCaseClass;

        $clientGetResponse = new MethodCall(new Node\Expr\Variable('client'), 'getResponse');
        $this->getStatusCodeMethodCall = new MethodCall($clientGetResponse, 'getStatusCode');
    }

    public function getDefinition(): RectorDefinition
    {
        return new RectorDefinition('Simplify use of assertions in WebTestCase', [
            new CodeSample(
                <<<'CODE_SAMPLE'
use PHPUnit\Framework\TestCase;

class SomeClass extends TestCase
{
    public function test()
    {
        $this->assertSame(200, $client->getResponse()->getStatusCode());
    }

    public function testUrl()
    {
        $this->assertSame(301, $client->getResponse()->getStatusCode());
        $this->assertSame('https://example.com', $client->getResponse()->headers->get('Location'));
    }

    public function testContains()
    {
        $this->assertContains('Hello World', $crawler->filter('h1')->text());
    }
}
CODE_SAMPLE
                ,
                <<<'CODE_SAMPLE'
use PHPUnit\Framework\TestCase;

class SomeClass extends TestCase
{
    public function test()
    {
         $this->assertResponseIsSuccessful();
    }

    public function testUrl()
    {
        $this->assertResponseRedirects('https://example.com', 301);
    }

    public function testContains()
    {
        $this->assertSelectorTextContains('h1', 'Hello World');
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
        return [MethodCall::class];
    }

    /**
     * @param MethodCall $node
     */
    public function refactor(Node $node): ?Node
    {
        if (! $this->isInWebTestCase($node)) {
            return null;
        }

        // assertResponseIsSuccessful
        $args = [];
        $args[] = new Node\Arg(new Node\Scalar\LNumber(200));
        $args[] = new Node\Arg($this->getStatusCodeMethodCall);
        $match = new MethodCall(new Node\Expr\Variable('this'), 'assertSame', $args);
        if ($this->areNodesEqual($node, $match)) {
            return new MethodCall(new Node\Expr\Variable('this'), 'assertResponseIsSuccessful');
        }

        // assertResponseStatusCodeSame
        $newNode = $this->processAssertResponseStatusCodeSame($node);
        if ($newNode) {
            return $newNode;
        }

        // assertSelectorTextContains
        $args = $this->matchAssertContainsCrawlerArg($node);
        if ($args !== null) {
            return new MethodCall(new Node\Expr\Variable('this'), 'assertSelectorTextContains', $args);
        }

        // 3. assertResponseRedirects
        return $this->processAssertResponseRedirects($node);
    }

    private function isInWebTestCase(Node $node): bool
    {
        $class = $node->getAttribute(Attribute::CLASS_NODE);
        if ($class === null) {
            return false;
        }

        return $this->isType($class, $this->webTestCaseClass);
    }

    /**
     * @return Node\Arg[]|null
     */
    private function matchAssertContainsCrawlerArg(MethodCall $methodCall): ?array
    {
        if (! $this->isName($methodCall, 'assertContains')) {
            return null;
        }

        $comparedNode = $methodCall->args[1]->value;
        if (! $comparedNode instanceof MethodCall) {
            return null;
        }

        if (! $comparedNode->var instanceof MethodCall) {
            return null;
        }

        if (! $comparedNode->var->var instanceof Node\Expr\Variable) {
            return null;
        }

        if (! $this->isName($comparedNode->var->var, 'crawler')) {
            return null;
        }

        if (! $this->isName($comparedNode, 'text')) {
            return null;
        }

        $args = [];
        $args[] = $comparedNode->var->args[0];
        $args[] = $methodCall->args[0];

        return $args;
    }

    private function processAssertResponseRedirects(MethodCall $methodCall): ?Node
    {
        /** @var Node\Stmt\Expression|null $previousNode */
        $previousExpression = $methodCall->getAttribute(Attribute::PREVIOUS_EXPRESSION);
        if (! $previousExpression instanceof Node\Stmt\Expression) {
            return null;
        }

        $previousNode = $previousExpression->expr;
        if (! $previousNode instanceof MethodCall) {
            return null;
        }

        $args = [];
        $args[] = new Node\Arg(new Node\Scalar\LNumber(301));
        $args[] = new Node\Arg($this->getStatusCodeMethodCall);

        $match = new MethodCall(new Node\Expr\Variable('this'), 'assertSame', $args);

        if ($this->areNodesEqual($previousNode, $match)) {
            $clientGetLocation = new MethodCall(new Node\Expr\PropertyFetch(new MethodCall(
                new Node\Expr\Variable('client'),
                'getResponse'
            ), 'headers'), 'get', [new Node\Arg(new Node\Scalar\String_('Location'))]);

            if (! isset($methodCall->args[1])) {
                return null;
            }

            if ($this->areNodesEqual($methodCall->args[1]->value, $clientGetLocation)) {
                $args = [];
                $args[] = $methodCall->args[0];
                $args[] = $previousNode->args[0];

                $this->removeNode($previousNode);

                return new MethodCall(new Node\Expr\Variable('this'), 'assertResponseRedirects', $args);
            }
        }

        return null;
    }

    private function processAssertResponseStatusCodeSame(Node $node): ?MethodCall
    {
        if (! $node instanceof MethodCall) {
            return null;
        }

        if (! $this->isName($node, 'assertSame')) {
            return null;
        }

        if (! $this->areNodesEqual($node->args[1]->value, $this->getStatusCodeMethodCall)) {
            return null;
        }

        $statusCode = $this->getValue($node->args[0]->value);

        // handled by another methods
        if (in_array($statusCode, [200, 301], true)) {
            return null;
        }

        return new MethodCall(new Node\Expr\Variable('this'), 'assertResponseStatusCodeSame', [$node->args[0]]);
    }
}
