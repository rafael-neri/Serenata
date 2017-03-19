<?php

namespace PhpIntegrator\Tests\Integration\UserInterface\Command;

use PhpIntegrator\Linting\LintingSettings;

use PhpIntegrator\Tests\Integration\AbstractIndexedTest;

class LinterTest extends AbstractIndexedTest
{
    /**
     * @param string $file
     * @param bool   $indexingMayFail
     *
     * @return array
     */
    protected function lintFile(string $file, bool $indexingMayFail = false): array
    {
        $path = __DIR__ . '/LinterTest/' . $file;

        $container = $this->createTestContainer();

        $this->indexTestFile($container, $path, $indexingMayFail);

        $linter = $container->get('linter');

        $settings = new LintingSettings(
            true,
            true,
            true,
            true,
            true,
            true
        );

        return $linter->lint($path, file_get_contents($path), $settings);
    }

    /**
     * @return void
     */
    public function testCorrectlyIdentifiesSyntaxErrors(): void
    {
        $output = $this->lintFile('SyntaxError.phpt', true);

        $this->assertEquals(2, count($output['errors']));
    }

    /**
     * @return void
     */
    public function testReportsUnknownClassesWithNoNamespace(): void
    {
        $output = $this->lintFile('UnknownClassesNoNamespace.phpt');

        $this->assertEquals([
            [
                'message' => 'Classlike **A\B** is not defined or imported anywhere.',
                'start'   => 32,
                'end'     => 35
            ]
        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testReportsUnknownClassesWithSingleNamespace(): void
    {
        $output = $this->lintFile('UnknownClassesSingleNamespace.phpt');

        $this->assertEquals([
            [
                'message' => 'Classlike **DateTime** is not defined or imported anywhere.',
                'start'   => 83,
                'end'     => 91
            ],
            [
                'message' => 'Classlike **DateTimeZone** is not defined or imported anywhere.',
                'start'   => 104,
                'end'     => 116
            ],
            [
                'message' => 'Member **#AFRICA** does not exist for type **\A\DateTimeZone**.',
                'start'   => 104,
                'end'     => 124
            ]
        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testReportsUnknownClassesWithMultipleNamespaces(): void
    {
        $output = $this->lintFile('UnknownClassesMultipleNamespaces.phpt');

        $this->assertEquals([
            [
                'message' => 'Classlike **DateTime** is not defined or imported anywhere.',
                'start'   => 97,
                'end'     => 105
            ],

            [
                'message' => 'Classlike **SplFileInfo** is not defined or imported anywhere.',
                'start'   => 153,
                'end'     => 164
            ]
        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testReportsUnknownClassesInDocBlocks(): void
    {
        $output = $this->lintFile('UnknownClassesDocblock.phpt');

        $this->assertEquals([
            [
                'message' => 'Classlike **A\B** is not defined or imported anywhere.',
                'start'   => 75,
                'end'     => 95
            ],

            [
                'message' => 'Classlike **A\C** is not defined or imported anywhere.',
                'start'   => 75,
                'end'     => 95
            ],

            [
                'message' => 'Classlike **MissingAnnotationClass** is not defined or imported anywhere.',
                'start'   => 175,
                'end'     => 197
            ],

            [
                'message' => 'Classlike **A\MissingAnnotationClass** is not defined or imported anywhere.',
                'start'   => 202,
                'end'     => 226
            ],

            [
                'message' => 'Classlike **B\MissingAnnotationClass** is not defined or imported anywhere.',
                'start'   => 231,
                'end'     => 256
            ]
        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testDoesNotComplainAboutUnknownClassesInGroupedUseStatements(): void
    {
        $output = $this->lintFile('GroupedUseStatements.phpt');

        $this->assertEquals([], $output['errors']);
    }

    /**
     * @return void
     */
    public function testReportsInvalidMemberCallsOnAnExpressionWithoutAType(): void
    {
        $output = $this->lintFile('UnknownMemberExpressionWithNoType.phpt');

        $this->assertEquals([
            [
                'message' => 'Member **#foo** could not be found because expression has no type.',
                'start'   => 21,
                'end'     => 32
            ]
        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testReportsInvalidMemberCallsOnAnExpressionThatDoesNotReturnAClasslike(): void
    {
        $output = $this->lintFile('UnknownMemberExpressionWithNoClasslike.phpt');

        $this->assertEquals([
            [
                'message' => 'Cannot invoke **#foo** on non-object type **int**.',
                'start'   => 57,
                'end'     => 68
            ],

            [
                'message' => 'Cannot invoke **#foo** on non-object type **bool**.',
                'start'   => 57,
                'end'     => 68
            ]
        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testReportsInvalidMemberCallsOnAnExpressionThatReturnsAClasslikeWithNoSuchMember(): void
    {
        $output = $this->lintFile('UnknownMemberExpressionWithNoSuchMember.phpt');

        $this->assertEquals([
            [
                'message' => 'Classlike **stdClass** is not defined or imported anywhere.',
                'start'   => 248,
                'end'     => 257
            ],

            [
                'message' => 'Member **#foo** does not exist for type **\A\Foo**.',
                'start'   => 158,
                'end'     => 169
            ],

            [
                'message' => 'Member **#bar** does not exist for type **\A\Foo**.',
                'start'   => 171,
                'end'     => 181
            ],

            [
                'message' => 'Member **#CONSTANT** does not exist for type **\A\Foo**.',
                'start'   => 221,
                'end'     => 234
            ]
        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testReportsInvalidMemberCallsOnAnExpressionThatReturnsAClasslikeWithNoSuchMemberCausingANewMemberToBeCreated(): void
    {
        $output = $this->lintFile('UnknownMemberExpressionWithNoSuchMember.phpt');

        $this->assertEquals([
            [
                'message' => 'Member **#test** was not explicitly defined in **\A\Foo**. It will be created at runtime.',
                'start'   => 114,
                'end'     => 125
            ],

            [
                'message' => 'Member **#fooProp** was not explicitly defined in **\A\Foo**. It will be created at runtime.',
                'start'   => 183,
                'end'     => 196
            ],

            [
                'message' => 'Member **#barProp** was not explicitly defined in **\A\Foo**. It will be created at runtime.',
                'start'   => 202,
                'end'     => 215
            ]
        ], $output['warnings']);
    }

    /**
     * @return void
     */
    public function testReportsUnknownGlobalFunctions(): void
    {
        $output = $this->lintFile('UnknownGlobalFunctions.phpt');

        $this->assertEquals([
            [
                'message' => 'Function **\foo** is not defined or imported anywhere.',
                'start'   => 151,
                'end'     => 156
            ],

            [
                'message' => 'Function **\foo** is not defined or imported anywhere.',
                'start'   => 162,
                'end'     => 168
            ],

            [
                'message' => 'Function **\A\foo** is not defined or imported anywhere.',
                'start'   => 174,
                'end'     => 182
            ]
        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testReportsUnknownGlobalConstants(): void
    {
        $output = $this->lintFile('UnknownGlobalConstants.phpt');

        $this->assertEquals([
            [
                'message' => 'Constant **\MISSING** is not defined or imported anywhere.',
                'start'   => 98,
                'end'     => 105
            ],

            [
                'message' => 'Constant **\MISSING** is not defined or imported anywhere.',
                'start'   => 111,
                'end'     => 119
            ],

            [
                'message' => 'Constant **\A\MISSING** is not defined or imported anywhere.',
                'start'   => 125,
                'end'     => 135
            ]
        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testReportsUnusedUseStatementsWithSingleNamespace(): void
    {
        $output = $this->lintFile('UnusedUseStatementsSingleNamespace.phpt');

        $this->assertEquals([
            [
                'message' => 'Classlike **Traversable** is imported, but not used anywhere.',
                'start'   => 39,
                'end'     => 50
            ]
        ], $output['warnings']);
    }

    /**
     * @return void
     */
    public function testReportsUnusedUseStatementsWithMultipleNamespaces(): void
    {
        $output = $this->lintFile('UnusedUseStatementsMultipleNamespaces.phpt');

        $this->assertEquals([
            [
                'message' => 'Classlike **SplFileInfo** is imported, but not used anywhere.',
                'start'   => 47,
                'end'     => 58
            ],

            [
                'message' => 'Classlike **DateTime** is imported, but not used anywhere.',
                'start'   => 111,
                'end'     => 119
            ]
        ], $output['warnings']);
    }

    /**
     * @return void
     */
    public function testReportsUnusedUseStatementsWithGroupedUseStatements(): void
    {
        $output = $this->lintFile('GroupedUseStatements.phpt');

        $this->assertEquals([
            [
                'message' => 'Classlike **B\Foo** is imported, but not used anywhere.',
                'start'   => 152,
                'end'     => 155
            ],

            [
                'message' => 'Classlike **B\Bar** is imported, but not used anywhere.',
                'start'   => 165,
                'end'     => 168
            ],

            [
                'message' => 'Classlike **B\Missing** is imported, but not used anywhere.',
                'start'   => 178,
                'end'     => 185
            ]
        ], $output['warnings']);
    }

    /**
     * @return void
     */
    public function testReportsUnusedUseStatementsForConstants(): void
    {
        $output = $this->lintFile('UnusedUseStatementsConstant.phpt');

        $this->assertEquals([
            [
                'message' => 'Constant **Some\CONSTANT_UNUSED** is imported, but not used anywhere.',
                'start'   => 56,
                'end'     => 76
            ]
        ], $output['warnings']);
    }

    /**
     * @return void
     */
    public function testReportsUnusedUseStatementsForFunctions(): void
    {
        $output = $this->lintFile('UnusedUseStatementsFunction.phpt');

        $this->assertEquals([
            [
                'message' => 'Function **Some\funcUnused** is imported, but not used anywhere.',
                'start'   => 58,
                'end'     => 73
            ]
        ], $output['warnings']);
    }

    /**
     * @return void
     */
    public function testSeesUseStatementsAsUsedIfTheyAppearInComments(): void
    {
        $output = $this->lintFile('UnusedUseStatementsDocblock.phpt');

        $this->assertEquals([
            [
                'message' => 'Classlike **SplMinHeap** is imported, but not used anywhere.',
                'start'   => 53,
                'end'     => 63
            ],

            [
                'message' => 'Classlike **SplFileInfo** is imported, but not used anywhere.',
                'start'   => 69,
                'end'     => 80
            ]
        ], $output['warnings']);
    }

    /**
     * @return void
     */
    public function testSeesUseStatementsAsUsedIfTheyAppearInAnonymousClasses(): void
    {
        $output = $this->lintFile('UnusedUseStatementsAnonymousClass.phpt');

        $this->assertEquals([], $output['warnings']);
    }

    /**
     * @return void
     */
    public function testCorrectlyIdentifiesMissingDocumentation(): void
    {
        $output = $this->lintFile('DocblockCorrectnessMissingDocumentation.phpt');

        $this->assertEquals([
            [
                'message' => 'Documentation for method **someMethod** is missing.',
                'start'   => 448,
                'end'     => 449
            ],

            [
                'message' => 'Documentation for property **someProperty** is missing.',
                'start'   => 331,
                'end'     => 344
            ],

            [
                'message' => 'Documentation for constant **SOME_CONST** is missing.',
                'start'   => 300,
                'end'     => 310
            ],

            [
                'message' => 'Documentation for classlike **\A\MissingDocumentation** is missing.',
                'start'   => 496,
                'end'     => 497
            ],

            [
                'message' => 'Documentation for function **some_function** is missing.',
                'start'   => 21,
                'end'     => 22
            ]
        ], $output['warnings']);
    }

    /**
     * @return void
     */
    public function testCorrectlyIdentifiesDocblockMissingParameter(): void
    {
        $output = $this->lintFile('DocblockCorrectnessMissingParameter.phpt');

        $this->assertEquals([
            [
                'message' => 'Docblock for function **some_function_missing_parameter** is missing @param tag for **$param2**.',
                'start'   => 182,
                'end'     => 183
            ]
        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testDoesNotComplainAboutMissingParameterWhenItIsAReference(): void
    {
        $output = $this->lintFile('DocblockCorrectnessParamWithReference.phpt');

        $this->assertEquals([

        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testDoesNotComplainAboutMissingParameterWhenItIsVariadic(): void
    {
        $output = $this->lintFile('DocblockCorrectnessVariadicParam.phpt');

        $this->assertEquals([

        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testDoesNotComplainAboutDocblocksHavingFullInheritance(): void
    {
        $output = $this->lintFile('DocblockCorrectnessFullInheritance.phpt');

        $this->assertEquals([

        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testCorrectlyIdentifiesDocblockParameterTypeMismatch(): void
    {
        $output = $this->lintFile('DocblockCorrectnessParameterTypeMismatch.phpt');

        $this->assertEquals([
            [
                'message' => 'Docblock for function **some_function_parameter_incorrect_type** has incorrect @param type for **$param1**.',
                'start'   => 334,
                'end'     => 335
            ],
        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testHighlightsReferenceParameterWithDocblockParameterMismatch(): void
    {
        $output = $this->lintFile('DocblockCorrectnessReferenceParam.phpt');

        $this->assertEquals([
            [
                'message' => 'Docblock for function **some_function_parameter_incorrect_type** has incorrect @param type for **$param1**.',
                'start'   => 65,
                'end'     => 66
            ]
        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testCorrectlyRecognizesDifferentQualificationsOfSameClassName(): void
    {
        $output = $this->lintFile('DocblockCorrectnessParamTypeDifferentQualifications.phpt');

        $this->assertEquals([

        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testCorrectlyIdentifiesDocblockSuperfluousParameters(): void
    {
        $output = $this->lintFile('DocblockCorrectnessSuperfluousParameters.phpt');

        $this->assertEquals([
            [
                'message' => 'Docblock for function **some_function_extra_parameter** contains superfluous @param tags for: **$extra1, $extra2**.',
                'start'   => 256,
                'end'     => 257
            ]
        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testCorrectlyIdentifiesDocblockMissingVarTag(): void
    {
        $output = $this->lintFile('DocblockCorrectnessMissingVarTag.phpt');

        $this->assertEquals([
            [
                'message' => 'Docblock for property **property** is missing @var tag.',
                'start'   => 116,
                'end'     => 125
            ],

            [
                'message' => 'Docblock for constant **CONSTANT** is missing @var tag.',
                'start'   => 64,
                'end'     => 73
            ]
        ], $output['errors']);
    }

    /**
     * @return void
     */
    public function testCorrectlyIdentifiesDeprecatedCategoryTag(): void
    {
        $output = $this->lintFile('DocblockCorrectnessDeprecatedCategoryTag.phpt');

        $this->assertEquals([
            [
                'message' => 'Docblock for classlike **C** contains deprecated @category tag.',
                'start'   => 47,
                'end'     => 48
            ]
        ], $output['warnings']);
    }

    /**
     * @return void
     */
    public function testCorrectlyIdentifiesDeprecatedSubpackageTag(): void
    {
        $output = $this->lintFile('DocblockCorrectnessDeprecatedSubpackageTag.phpt');

        $this->assertEquals([
            [
                'message' => 'Docblock for classlike **C** contains deprecated @subpackage tag.',
                'start'   => 49,
                'end'     => 50
            ]
        ], $output['warnings']);
    }

    /**
     * @return void
     */
    public function testCorrectlyIdentifiesDeprecatedLinkTag(): void
    {
        $output = $this->lintFile('DocblockCorrectnessDeprecatedLinkTag.phpt');

        $this->assertEquals([
            [
                'message' =>  'Docblock for classlike **C** contains deprecated @link tag. See also [https://github.com/phpDocumentor/fig-standards/blob/master/proposed/phpdoc.md#710-link-deprecated](https://github.com/phpDocumentor/fig-standards/blob/master/proposed/phpdoc.md#710-link-deprecated}.',
                'start'   => 63,
                'end'     => 64
            ]
        ], $output['warnings']);
    }
}