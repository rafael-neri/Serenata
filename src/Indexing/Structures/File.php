<?php

namespace PhpIntegrator\Indexing\Structures;

use DateTime;
use OutOfRangeException;

use Doctrine\Common\Collections\ArrayCollection;

use Ramsey\Uuid\Uuid;

/**
 * Represents a file.
 */
class File
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $path;

    /**
     * @var DateTime
     */
    private $indexedOn;

    /**
     * @var ArrayCollection
     */
    private $constants;

    /**
     * @var ArrayCollection
     */
    private $functions;

    /**
     * @var ArrayCollection
     */
    private $namespaces;

    /**
     * @param string          $path
     * @param DateTime        $indexedOn
     * @param FileNamespace[] $namespaces
     */
    public function __construct(string $path, DateTime $indexedOn, array $namespaces)
    {
        $this->id = (string) Uuid::uuid4();
        $this->path = $path;
        $this->indexedOn = $indexedOn;
        $this->namespaces = new ArrayCollection($namespaces);

        $this->constants = new ArrayCollection();
        $this->functions = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return DateTime
     */
    public function getIndexedOn(): DateTime
    {
        return $this->indexedOn;
    }

    /**
     * @param DateTime $indexedOn
     *
     * @return static
     */
    public function setIndexedOn(DateTime $indexedOn)
    {
        $this->indexedOn = $indexedOn;
        return $this;
    }

    /**
     * @return Constant[]
     */
    public function getConstants(): array
    {
        return $this->constants->toArray();
    }

    /**
     * @param Constant $constant
     */
    public function addConstant(Constant $constant): void
    {
        $this->constants->add($constant);
    }

    /**
     * @param Constant $constant
     */
    public function removeConstant(Constant $constant): void
    {
        if (!$this->constants->contains($constant)) {
            throw new OutOfRangeException('Can not remove function from file that isn\'t even part of file');
        }

        $this->constants->removeElement($constant);
    }

    /**
     * @return Function_[]
     */
    public function getFunctions(): array
    {
        return $this->functions->toArray();
    }

    /**
     * @param Function_ $function
     */
    public function addFunction(Function_ $function): void
    {
        $this->functions->add($function);
    }

    /**
     * @param Function_ $function
     */
    public function removeFunction(Function_ $function): void
    {
        if (!$this->functions->contains($function)) {
            throw new OutOfRangeException('Can not remove function from file that isn\'t even part of file');
        }

        $this->functions->removeElement($function);
    }

    /**
     * @return FileNamespace[]
     */
    public function getNamespaces(): array
    {
        return $this->namespaces->toArray();
    }

    /**
     * @param FileNamespace $namespace
     *
     * @return void
     */
    public function addNamespace(FileNamespace $namespace): void
    {
        $this->namespaces->add($namespace);
    }
}
