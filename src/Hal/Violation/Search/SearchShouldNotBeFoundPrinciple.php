<?php
declare(strict_types=1);

namespace Hal\Violation\Search;

use Hal\Metric\Metric;
use Hal\Violation\Violation;
use function array_unique;
use function implode;

/**
 * This class triggers a violation when a given criterion of research has been detected in some analysed metrics.
 * It manages custom violations.
 */
final class SearchShouldNotBeFoundPrinciple implements Violation
{
    /** @var array<string> */
    private array $concernedSearches = [];

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return implode(', ', $this->concernedSearches);
    }

    /**
     * {@inheritDoc}
     */
    public function apply(Metric $metric): void
    {
        if (true !== $metric->get('was-not-expected')) {
            return;
        }

        $this->concernedSearches = array_unique([...$this->concernedSearches, ...$metric->get('was-not-expected-by')]);
        $metric->get('violations')->add($this);
    }

    /**
     * {@inheritDoc}
     */
    public function getLevel(): int
    {
        return Violation::CRITICAL;
    }

    /**
     * {@inheritDoc}
     */
    public function getDescription(): string
    {
        return 'According configuration, this component is not expected to be found in the code.';
    }
}
