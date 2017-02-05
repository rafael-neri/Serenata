<?php

namespace PhpIntegrator\Analysis;

use PhpIntegrator\Analysis\ClasslikeInfoBuilder;
use PhpIntegrator\Analysis\ClasslikeInfoBuilderProviderInterface;

use PhpIntegrator\Analysis\Conversion\MethodConverter;
use PhpIntegrator\Analysis\Conversion\ConstantConverter;
use PhpIntegrator\Analysis\Conversion\PropertyConverter;
use PhpIntegrator\Analysis\Conversion\FunctionConverter;
use PhpIntegrator\Analysis\Conversion\ClasslikeConverter;
use PhpIntegrator\Analysis\Conversion\ClasslikeConstantConverter;

use PhpIntegrator\Analysis\Relations\TraitUsageResolver;
use PhpIntegrator\Analysis\Relations\InheritanceResolver;
use PhpIntegrator\Analysis\Relations\InterfaceImplementationResolver;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;
use PhpIntegrator\Analysis\Typing\FileClassListProviderInterface;

use PhpIntegrator\Indexing\IndexDatabase;

use PhpIntegrator\UserInterface\ClasslikeInfoBuilderWhiteHolingProxyProvider;

/**
 * Retrieves a list of available classes.
 */
class ClassListProvider implements FileClassListProviderInterface
{
    /**
     * @var ConstantConverter
     */
    protected $constantConverter;

    /**
     * @var ClasslikeConstantConverter
     */
    protected $classlikeConstantConverter;

    /**
     * @var PropertyConverter
     */
    protected $propertyConverter;

    /**
     * @var FunctionConverter
     */
    protected $functionConverter;

    /**
     * @var MethodConverter
     */
    protected $methodConverter;

    /**
     * @var ClasslikeConverter
     */
    protected $classlikeConverter;

    /**
     * @var InheritanceResolver
     */
    protected $inheritanceResolver;

    /**
     * @var InterfaceImplementationResolver
     */
    protected $interfaceImplementationResolver;

    /**
     * @var TraitUsageResolver
     */
    protected $traitUsageResolver;

    /**
     * @var ClasslikeInfoBuilderProviderInterface
     */
    protected $classlikeInfoBuilderProvider;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * @param ConstantConverter                     $constantConverter
     * @param ClasslikeConstantConverter            $classlikeConstantConverter
     * @param PropertyConverter                     $propertyConverter
     * @param FunctionConverter                     $functionConverter
     * @param MethodConverter                       $methodConverter
     * @param ClasslikeConverter                    $classlikeConverter
     * @param InheritanceResolver                   $inheritanceResolver
     * @param InterfaceImplementationResolver       $interfaceImplementationResolver
     * @param TraitUsageResolver                    $traitUsageResolver
     * @param ClasslikeInfoBuilderProviderInterface $classlikeInfoBuilderProvider
     * @param TypeAnalyzer                          $typeAnalyzer
     * @param IndexDatabase                         $indexDatabase
     */
    public function __construct(
        ConstantConverter $constantConverter,
        ClasslikeConstantConverter $classlikeConstantConverter,
        PropertyConverter $propertyConverter,
        FunctionConverter $functionConverter,
        MethodConverter $methodConverter,
        ClasslikeConverter $classlikeConverter,
        InheritanceResolver $inheritanceResolver,
        InterfaceImplementationResolver $interfaceImplementationResolver,
        TraitUsageResolver $traitUsageResolver,
        ClasslikeInfoBuilderProviderInterface $classlikeInfoBuilderProvider,
        TypeAnalyzer $typeAnalyzer,
        IndexDatabase $indexDatabase
    ) {
        $this->constantConverter = $constantConverter;
        $this->classlikeConstantConverter = $classlikeConstantConverter;
        $this->propertyConverter = $propertyConverter;
        $this->functionConverter = $functionConverter;
        $this->methodConverter = $methodConverter;
        $this->classlikeConverter = $classlikeConverter;
        $this->inheritanceResolver = $inheritanceResolver;
        $this->interfaceImplementationResolver = $interfaceImplementationResolver;
        $this->traitUsageResolver = $traitUsageResolver;
        $this->classlikeInfoBuilderProvider = $classlikeInfoBuilderProvider;
        $this->typeAnalyzer = $typeAnalyzer;
        $this->indexDatabase = $indexDatabase;
    }

    /**
     * @return array
     */
    public function getAll(): array
    {
        return $this->getAllForOptionalFile(null);
    }

    /**
     * @param string $file
     *
     * @return array
     */
    public function getAllForFile(string $file): array
    {
        return $this->getAllForOptionalFile($file);
    }

    /**
     * @param ?string $file
     *
     * @return array
     */
    protected function getAllForOptionalFile(?string $file): array
    {
        $result = [];

        $storageProxy = new ClasslikeInfoBuilderWhiteHolingProxyProvider($this->classlikeInfoBuilderProvider);

        $dataAdapter = new ClasslikeInfoBuilder(
            $this->constantConverter,
            $this->classlikeConstantConverter,
            $this->propertyConverter,
            $this->functionConverter,
            $this->methodConverter,
            $this->classlikeConverter,
            $this->inheritanceResolver,
            $this->interfaceImplementationResolver,
            $this->traitUsageResolver,
            $storageProxy,
            $this->typeAnalyzer
        );

        foreach ($this->indexDatabase->getAllStructuresRawInfo($file) as $element) {
            // Directly load in the raw information we already have, this avoids performing a database query for each
            // record.
            $storageProxy->setStructureRawInfo($element);

            $info = $dataAdapter->getClasslikeInfo($element['name']);

            unset($info['constants'], $info['properties'], $info['methods']);

            $result[$element['fqcn']] = $info;
        }

        return $result;
    }
}
