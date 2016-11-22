<?php

namespace PhpIntegrator\Test\UserInterface\Command;

use ReflectionClass;

use PhpIntegrator\UserInterface\Command\DeduceTypesCommand;

use PhpIntegrator\Test\IndexedTest;

class DeduceTypesCommandTest extends IndexedTest
{
    /**
     * @param string $file
     * @param array  $expressionParts
     *
     * @return string[]
     */
    protected function deduceTypes($file, array $expressionParts)
    {
        $path = __DIR__ . '/DeduceTypesCommandTest/' . $file;

        $markerOffset = $this->getMarkerOffset($path, '<MARKER>');

        $container = $this->createTestContainer();

        $this->indexTestFile($container, $path);

        $command = new DeduceTypesCommand(
            $container->get('typeDeducer'),
            $container->get('partialParser'),
            $container->get('sourceCodeStreamReader')
        );

        $reflectionClass = new ReflectionClass(DeduceTypesCommand::class);
        $reflectionMethod = $reflectionClass->getMethod('deduceTypes');
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invoke($command, $path, file_get_contents($path), $expressionParts, $markerOffset);
    }

    /**
     * @param string $path
     * @param string $marker
     *
     * @return int
     */
    protected function getMarkerOffset($path, $marker)
    {
        $testFileContents = @file_get_contents($path);

        $markerOffset = mb_strpos($testFileContents, $marker);

        return $markerOffset;
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesTypeOverrideAnnotations()
    {
        $output = $this->deduceTypes('TypeOverrideAnnotations.phpt', ['$a']);

        $this->assertEquals(['\Traversable'], $output);

        $output = $this->deduceTypes('TypeOverrideAnnotations.phpt', ['$b']);

        $this->assertEquals(['\Traversable'], $output);

        $output = $this->deduceTypes('TypeOverrideAnnotations.phpt', ['$c']);

        $this->assertEquals(['\A\C', 'null'], $output);

        $output = $this->deduceTypes('TypeOverrideAnnotations.phpt', ['$d']);

        $this->assertEquals(['\A\D'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyResolvesThisInClass()
    {
        $output = $this->deduceTypes('ThisInClass.phpt', ['$this']);

        $this->assertEquals(['\A\B'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyResolvesThisOutsideClass()
    {
        $output = $this->deduceTypes('ThisOutsideClass.phpt', ['$this']);

        $this->assertEquals([], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesFunctionTypeHints()
    {
        $output = $this->deduceTypes('FunctionParameterTypeHint.phpt', ['$b']);

        $this->assertEquals(['\B'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesFunctionDocblocks()
    {
        $output = $this->deduceTypes('FunctionParameterDocblock.phpt', ['$b']);

        $this->assertEquals(['\B'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesMethodTypeHints()
    {
        $output = $this->deduceTypes('MethodParameterTypeHint.phpt', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesMethodDocblocks()
    {
        $output = $this->deduceTypes('MethodParameterDocblock.phpt', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesClosureTypeHints()
    {
        $output = $this->deduceTypes('ClosureParameterTypeHint.phpt', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyMovesBeyondClosureScopeForVariableUses()
    {
        $output = $this->deduceTypes('ClosureVariableUseStatement.phpt', ['$b']);

        $this->assertEquals(['\A\B'], $output);

        $output = $this->deduceTypes('ClosureVariableUseStatement.phpt', ['$c']);

        $this->assertEquals(['\A\C'], $output);

        $output = $this->deduceTypes('ClosureVariableUseStatement.phpt', ['$d']);

        $this->assertEquals(['\A\D'], $output);

        $output = $this->deduceTypes('ClosureVariableUseStatement.phpt', ['$e']);

        $this->assertEquals([], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesCatchBlockTypeHints()
    {
        $output = $this->deduceTypes('CatchBlockTypeHint.phpt', ['$e']);

        $this->assertEquals(['\UnexpectedValueException'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesIfStatementWithInstanceof()
    {
        $output = $this->deduceTypes('InstanceofIf.phpt', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesComplexIfStatementWithInstanceofAndVariableInsideCondition()
    {
        $output = $this->deduceTypes('InstanceofComplexIfVariableInsideCondition.phpt', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesComplexIfStatementWithInstanceofAndAnd()
    {
        $output = $this->deduceTypes('InstanceofComplexIfAnd.phpt', ['$b']);

        $this->assertEquals(['\A\B', '\A\C', '\A\D'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesComplexIfStatementWithInstanceofAndOr()
    {
        $output = $this->deduceTypes('InstanceofComplexIfOr.phpt', ['$b']);

        $this->assertEquals(['\A\B', '\A\C', '\A\D', '\A\E'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesNestedIfStatementWithInstanceof()
    {
        $output = $this->deduceTypes('InstanceofNestedIf.phpt', ['$b']);

        $this->assertEquals(['\A\B', '\A\A'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesNestedIfStatementWithInstanceofAndNegation()
    {
        $output = $this->deduceTypes('InstanceofNestedIfWithNegation.phpt', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesNestedIfStatementWithInstanceofAndReassignment()
    {
        $output = $this->deduceTypes('InstanceofNestedIfReassignment.phpt', ['$b']);

        $this->assertEquals(['\A\A'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesIfStatementWithNotInstanceof()
    {
        $output = $this->deduceTypes('IfNotInstanceof.phpt', ['$b']);

        $this->assertEquals(['\A\A'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesComplexIfStatementWithNotStrictlyEqualsNull()
    {
        $output = $this->deduceTypes('IfNotStrictlyEqualsNull.phpt', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesComplexIfStatementWithNotLooselyEqualsNull()
    {
        $output = $this->deduceTypes('IfNotLooselyEqualsNull.phpt', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesComplexIfStatementWithStrictlyEqualsNull()
    {
        $output = $this->deduceTypes('IfStrictlyEqualsNull.phpt', ['$b']);

        $this->assertEquals(['null'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesComplexIfStatementWithLooselyEqualsNull()
    {
        $output = $this->deduceTypes('IfLooselyEqualsNull.phpt', ['$b']);

        $this->assertEquals(['null'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesIfStatementWithTruthy()
    {
        $output = $this->deduceTypes('IfTruthy.phpt', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesIfStatementWithFalsy()
    {
        $output = $this->deduceTypes('IfFalsy.phpt', ['$b']);

        $this->assertEquals(['null'], $output);
    }

    /**
     * @return void
     */
    public function testTypeOverrideAnnotationsStillTakePrecedenceOverConditionals()
    {
        $output = $this->deduceTypes('IfWithTypeOverride.phpt', ['$b']);

        $this->assertEquals(['string'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesComplexIfStatementWithVariableHandlingFunction()
    {
        $output = $this->deduceTypes('IfVariableHandlingFunction.phpt', ['$b']);

        $this->assertEquals([
            'array',
            'bool',
            'callable',
            'float',
            'int',
            'null',
            'string',
            'object',
            'resource'
        ], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyTreatsIfConditionAsSeparateScope()
    {
        $output = $this->deduceTypes('InstanceofIfSeparateScope.phpt', ['$b']);

        $this->assertEquals([], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesElseIfStatementWithInstanceof()
    {
        $output = $this->deduceTypes('InstanceofElseIf.phpt', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyConfinesTreatsElseIfConditionAsSeparateScope()
    {
        $output = $this->deduceTypes('InstanceofElseIfSeparateScope.phpt', ['$b']);

        $this->assertEquals([], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesTernaryExpressionWithInstanceof()
    {
        $output = $this->deduceTypes('InstanceofTernary.phpt', ['$b']);

        $this->assertEquals(['\A\B'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyConfinesTreatsTernaryExpressionConditionAsSeparateScope()
    {
        $output = $this->deduceTypes('InstanceofTernarySeparateScope.phpt', ['$b']);

        $this->assertEquals([], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesTernaryExpression()
    {
        $output = $this->deduceTypes('TernaryExpression.phpt', ['$a']);

        $this->assertEquals(['\A'], $output);

        $output = $this->deduceTypes('TernaryExpression.phpt', ['$b']);

        $this->assertEquals(['\B'], $output);

        $output = $this->deduceTypes('TernaryExpression.phpt', ['$c']);

        $this->assertEquals(['\C', 'null'], $output);

        $output = $this->deduceTypes('TernaryExpression.phpt', ['$d']);

        $this->assertEquals(['\A', '\C', 'null'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesForeach()
    {
        $output = $this->deduceTypes('Foreach.phpt', ['$a']);

        $this->assertEquals(['\DateTime'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesAssignments()
    {
        $output = $this->deduceTypes('Assignment.phpt', ['$a']);

        $this->assertEquals(['\DateTime'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyIgnoresAssignmentsOutOfScope()
    {
        $output = $this->deduceTypes('AssignmentOutOfScope.phpt', ['$a']);

        $this->assertEquals(['\DateTime'], $output);
    }

    /**
     * @return void
     */
    public function testDocblockTakesPrecedenceOverTypeHint()
    {
        $output = $this->deduceTypes('DocblockPrecedence.phpt', ['$b']);

        $this->assertEquals(['\B'], $output);
    }

    /**
     * @return void
     */
    public function testVariadicTypesForParametersAreCorrectlyAnalyzed()
    {
        $output = $this->deduceTypes('FunctionVariadicParameter.phpt', ['$b']);

        $this->assertEquals(['\A\B[]'], $output);
    }

    /**
     * @return void
     */
    public function testSpecialTypesForParametersResolveCorrectly()
    {
        $output = $this->deduceTypes('FunctionParameterTypeHintSpecial.phpt', ['$a']);

        $this->assertEquals(['\A\C'], $output);

        $output = $this->deduceTypes('FunctionParameterTypeHintSpecial.phpt', ['$b']);

        $this->assertEquals(['\A\C'], $output);

        $output = $this->deduceTypes('FunctionParameterTypeHintSpecial.phpt', ['$c']);

        $this->assertEquals(['\A\C'], $output);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesStaticPropertyAccess()
    {
        $result = $this->deduceTypes(
            'StaticPropertyAccess.phpt',
            ['Bar', '$testProperty']
        );

        $this->assertEquals(['\DateTime'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesSelf()
    {
        $result = $this->deduceTypes(
            'Self.phpt',
            ['self', '$testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesStatic()
    {
        $result = $this->deduceTypes(
            'Static.phpt',
            ['static', '$testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesParent()
    {
        $result = $this->deduceTypes(
            'Parent.phpt',
            ['parent', '$testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesThis()
    {
        $result = $this->deduceTypes(
            'This.phpt',
            ['$this', 'testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesVariables()
    {
        $result = $this->deduceTypes(
            'Variable.phpt',
            ['$var', 'testProperty']
        );

        $this->assertEquals(['\B'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesGlobalFunctions()
    {
        $result = $this->deduceTypes(
            'GlobalFunction.phpt',
            ['\global_function()']
        );

        $this->assertEquals(['\B', 'null'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesGlobalConstants()
    {
        $result = $this->deduceTypes(
            'GlobalConstant.phpt',
            ['\GLOBAL_CONSTANT']
        );

        $this->assertEquals(['string'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesGlobalConstantsAssignedToOtherGlobalConstants()
    {
        $result = $this->deduceTypes(
            'GlobalConstant.phpt',
            ['\ANOTHER_GLOBAL_CONSTANT']
        );

        $this->assertEquals(['string'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesClosures()
    {
        $result = $this->deduceTypes(
            'Closure.phpt',
            ['$var']
        );

        $this->assertEquals(['\Closure'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesNewWithStatic()
    {
        $result = $this->deduceTypes(
            'NewWithStatic.phpt',
            ['new static']
        );

        $this->assertEquals(['\Bar'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesClone()
    {
        $result = $this->deduceTypes(
            'Clone.phpt',
            ['clone $var']
        );

        $this->assertEquals(['\Bar'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesLongerChains()
    {
        $result = $this->deduceTypes(
            'LongerChain.phpt',
            ['$this', 'testProperty', 'aMethod()', 'anotherProperty']
        );

        $this->assertEquals(['\DateTime'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyAnalyzesScalarTypes()
    {
        $file = 'ScalarType.phpt';

        $this->assertEquals(['int'], $this->deduceTypes($file, ['5']));
        $this->assertEquals(['int'], $this->deduceTypes($file, ['05']));
        $this->assertEquals(['int'], $this->deduceTypes($file, ['0x5']));
        $this->assertEquals(['float'], $this->deduceTypes($file, ['5.5']));
        $this->assertEquals(['bool'], $this->deduceTypes($file, ['true']));
        $this->assertEquals(['bool'], $this->deduceTypes($file, ['false']));
        $this->assertEquals(['string'], $this->deduceTypes($file, ['"test"']));
        $this->assertEquals(['string'], $this->deduceTypes($file, ['\'test\'']));
        $this->assertEquals(['array'], $this->deduceTypes($file, ['[$test1, function() {}]']));
        $this->assertEquals(['array'], $this->deduceTypes($file, ['array($test1, function() {})']));

        $this->assertEquals(['string'], $this->deduceTypes($file, ['"
            test
        "']));

        $this->assertEquals(['string'], $this->deduceTypes($file, ['\'
            test
        \'']));
    }

    /**
     * @return void
     */
    public function testCorrectlyProcessesSelfAssign()
    {
        $result = $this->deduceTypes(
            'SelfAssign.phpt',
            ['$foo1']
        );

        $this->assertEquals(['\A\Foo'], $result);

        $result = $this->deduceTypes(
            'SelfAssign.phpt',
            ['$foo2']
        );

        $this->assertEquals(['\A\Foo'], $result);

        $result = $this->deduceTypes(
            'SelfAssign.phpt',
            ['$foo3']
        );

        $this->assertEquals(['\A\Foo'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyProcessesStaticMethodCallAssignedToVariableWithFqcnWithLeadingSlash()
    {
        $result = $this->deduceTypes(
            'StaticMethodCallFqcnLeadingSlash.phpt',
            ['$data']
        );

        $this->assertEquals(['\A\B'], $result);
    }

    /**
     * @return void
     */
    public function testCorrectlyReturnsMultipleTypes()
    {
        $result = $this->deduceTypes(
            'MultipleTypes.phpt',
            ['$this', 'testProperty']
        );

        $this->assertEquals([
            'string',
            'int',
            '\Foo',
            '\Bar'
        ], $result);
    }
}
