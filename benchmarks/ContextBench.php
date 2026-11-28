<?php

declare(strict_types=1);

namespace Componenta\Policy\Benchmarks;

use Componenta\Policy\Context\Context;
use Componenta\Policy\Exception\ContextCapacityException;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods(['setUp'])]
#[Bench\Revs(1000)]
#[Bench\Iterations(5)]
final class ContextBench
{
    private array $plain;
    private array $referenced;
    private array $sharedTree;
    private Context $context;
    private Context $referenceContext;
    private bool $flag = false;

    public function setUp(): void
    {
        $this->plain = ['resource' => new \stdClass(), 'metadata' => ['tags' => ['one', 'two'], 'revision' => 1]];
        $this->referenced = ['metadata' => ['allowed' => &$this->flag, 'alias' => &$this->flag]];
        $this->context = new Context($this->plain);
        $this->referenceContext = new Context($this->referenced);
        $tree = [1];
        for ($i = 0; $i < 16; $i++) {
            $tree = [$tree, $tree];
        }
        $this->sharedTree = ['payload' => $tree];
    }

    public function benchCreateEmpty(): void
    {
        new Context();
    }

    public function benchCreateNested(): void
    {
        new Context($this->plain);
    }

    public function benchCreateReferenced(): void
    {
        new Context($this->referenced);
    }

    public function benchReadPlain(): void
    {
        $this->context->getAttribute('metadata');
    }

    public function benchReadReferenced(): void
    {
        $this->referenceContext->getAttribute('metadata');
    }

    public function benchExportReferenced(): void
    {
        $this->referenceContext->toArray();
    }

    public function benchDerive(): void
    {
        $this->context->withAttribute('extra', true);
    }

    #[Bench\Revs(1)]
    public function benchRejectOversizedSharedTree(): void
    {
        try {
            new Context($this->sharedTree);
        } catch (ContextCapacityException) {
            return;
        }

        throw new \LogicException('Expected capacity rejection.');
    }
}
