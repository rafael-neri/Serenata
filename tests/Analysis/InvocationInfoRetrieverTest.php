<?php

namespace PhpIntegrator\Tests\Analysis;

use PhpIntegrator\Analysis\InvocationInfoRetriever;

use PhpIntegrator\Parsing\PartialParser;
use PhpIntegrator\Parsing\PrettyPrinter;
use PhpIntegrator\Parsing\ParserTokenHelper;
use PhpIntegrator\Parsing\LastExpressionParser;

use PhpParser\Lexer;
use PhpParser\ParserFactory;

class InvocationInfoRetrieverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @return ParserFactory
     */
    protected function createParserFactoryStub(): ParserFactory
    {
        return new ParserFactory();
    }

    /**
     * @return ParserFactory
     */
    protected function createPrettyPrinterStub(): PrettyPrinter
    {
        return new PrettyPrinter();
    }

    /**
     * @return ParserFactory
     */
    protected function createPartialParserStub(): PartialParser
    {
        return new PartialParser($this->createParserFactoryStub(), new Lexer());
    }

    /**
     * @return ParserTokenHelper
     */
    protected function createParserTokenHelperStub(): ParserTokenHelper
    {
        return new ParserTokenHelper();
    }

    /**
     * @return LastExpressionParser
     */
    protected function createLastExpressionParserStub(): LastExpressionParser
    {
        return new LastExpressionParser(
            $this->createPartialParserStub(),
            $this->createParserTokenHelperStub()
        );
    }

    /**
     * @return InvocationInfoRetriever
     */
    protected function createInvocationInfoRetriever(): InvocationInfoRetriever
    {
        return new InvocationInfoRetriever(
            $this->createLastExpressionParserStub(),
            $this->createParserTokenHelperStub(),
            $this->createPrettyPrinterStub()
        );
    }

    /**
     * @return void
     */
    public function testSingleLineInvocation(): void
    {
        $source = <<<'SOURCE'
            <?php

            $this->test(1, 2, 3
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertEquals(42, $result['offset']);
        $this->assertEquals('test', $result['name']);
        $this->assertEquals('$this->test', $result['expression']);
        $this->assertEquals('method', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    /**
     * @return void
     */
    public function testMultiLineInvocation(): void
    {
        $source = <<<'SOURCE'
        <?php

        $this->test(
            1,
            2,
            3
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertEquals(34, $result['offset']);
        $this->assertEquals('test', $result['name']);
        $this->assertEquals('$this->test', $result['expression']);
        $this->assertEquals('method', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    /**
     * @return void
     */
    public function testMoreComplexNestedArguments1(): void
    {
        $source = <<<'SOURCE'
        <?php

        builtin_func(
            ['test', $this->foo()],
            function ($a) {
                // Something here.
                $this->something();
            },
            3
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals('builtin_func', $result['name']);
        $this->assertEquals('builtin_func', $result['expression']);
        $this->assertEquals('function', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    /**
     * @return void
     */
    public function testMoreComplexNestedArguments2(): void
    {
        $source = <<<'SOURCE'
        <?php

        builtin_func(/* test */
            "]",// a comment
            "}",/*}*/
            ['test'
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals('builtin_func', $result['name']);
        $this->assertEquals('builtin_func', $result['expression']);
        $this->assertEquals('function', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    /**
     * @return void
     */
    public function testMoreComplexNestedArguments3(): void
    {
        $source = <<<'SOURCE'
        <?php

        builtin_func(
            $this->foo(),
            $array['key'],
            $array['ke
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals('builtin_func', $result['name']);
        $this->assertEquals('builtin_func', $result['expression']);
        $this->assertEquals('function', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    /**
     * @return void
     */
    public function testTrailingCommas(): void
    {
        $source = <<<'SOURCE'
        <?php

        builtin_func(
            foo(),
            [
                'Trailing comma',
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertEquals(1, $result['argumentIndex']);
    }

    /**
     * @return void
     */
    public function testNestedParantheses(): void
    {
        $source = <<<'SOURCE'
        <?php

        builtin_func(
            foo(),
            ($a + $b
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals('builtin_func', $result['name']);
        $this->assertEquals('builtin_func', $result['expression']);
        $this->assertEquals('function', $result['type']);
        $this->assertEquals(1, $result['argumentIndex']);
    }

    /**
     * @return void
     */
    public function testSqlStringArguments(): void
    {
        $source = <<<'SOURCE'
        <?php

        foo("SELECT a.one, a.two, a.three FROM test", second
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertEquals(1, $result['argumentIndex']);
    }

    /**
     * @return void
     */
    public function testSqlStringArgumentsContainingParantheses(): void
    {
        $source = <<<'SOURCE'
        <?php

        foo('IF(
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertEquals('foo', $result['name']);
        $this->assertEquals('foo', $result['expression']);
        $this->assertEquals('function', $result['type']);
        $this->assertEquals(0, $result['argumentIndex']);
    }

    /**
     * @return void
     */
    public function testConstructorCallsWithNormalClassName(): void
    {
        $source = <<<'SOURCE'
        <?php

        new MyObject(
            1,
            2,
            3
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertEquals(35, $result['offset']);
        $this->assertEquals('MyObject', $result['name']);
        $this->assertEquals('MyObject', $result['expression']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    /**
     * @return void
     */
    public function testConstructorCallsWithNormalClassNamePrecededByLeadingSlash(): void
    {
        $source = <<<'SOURCE'
        <?php

        new \MyObject(
            1,
            2,
            3
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertEquals(36, $result['offset']);
        $this->assertEquals('\MyObject', $result['name']);
        $this->assertEquals('\MyObject', $result['expression']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    /**
     * @return void
     */
    public function testConstructorCallsWithNormalClassNamePrecededByLeadingSlashAndMultipleParts(): void
    {
        $source = <<<'SOURCE'
        <?php

        new \MyNamespace\MyObject(
            1,
            2,
            3
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertEquals(48, $result['offset']);
        $this->assertEquals('\MyNamespace\MyObject', $result['name']);
        $this->assertEquals('\MyNamespace\MyObject', $result['expression']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    /**
     * @return void
     */
    public function testConstructorCalls2(): void
    {
        $source = <<<'SOURCE'
        <?php

        new static(
            1,
            2,
            3
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertEquals(33, $result['offset']);
        $this->assertEquals('static', $result['name']);
        $this->assertEquals('static', $result['expression']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    /**
     * @return void
     */
    public function testConstructorCalls3(): void
    {
        $source = <<<'SOURCE'
        <?php

        new self(
            1,
            2,
            3
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertEquals(31, $result['offset']);
        $this->assertEquals('self', $result['name']);
        $this->assertEquals('self', $result['expression']);
        $this->assertEquals('instantiation', $result['type']);
        $this->assertEquals(2, $result['argumentIndex']);
    }

    /**
     * @return void
     */
    public function testReturnsNullWhenNotInInvocation1(): void
    {
        $source = <<<'SOURCE'
        <?php

        if ($this->test() as $test) {
            if (true) {

            }
        }
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testReturnsNullWhenNotInInvocation2(): void
    {
        $source = <<<'SOURCE'
        <?php

        $this->test();
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testReturnsNullWhenNotInInvocation3(): void
    {
        $source = <<<'SOURCE'
        <?php

        function test($a, $b)
        {

SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertNull($result);
    }

    /**
     * @return void
     */
    public function testReturnsNullWhenNotInInvocation4(): void
    {
        $source = <<<'SOURCE'
        <?php

        if (preg_match('/^array\s*\(/', $firstElement) === 1) {
            $className = 'array';
        } elseif (
SOURCE;

        $result = $this->createInvocationInfoRetriever()->get($source);

        $this->assertNull($result);
    }
}
