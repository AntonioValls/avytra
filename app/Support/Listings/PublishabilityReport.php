<?php

namespace App\Support\Listings;

/**
 * Result of the "ready to publish" validation (domain invariant 5).
 */
final readonly class PublishabilityReport
{
    /**
     * @param  list<PublishabilityIssue>  $issues
     */
    public function __construct(public array $issues = []) {}

    public function passes(): bool
    {
        return $this->issues === [];
    }

    public function fails(): bool
    {
        return ! $this->passes();
    }

    /**
     * @return list<PublishabilityIssue>
     */
    public function forStep(int $step): array
    {
        return array_values(array_filter($this->issues, fn (PublishabilityIssue $issue): bool => $issue->step === $step));
    }

    /**
     * @return list<string>
     */
    public function messages(): array
    {
        return array_map(fn (PublishabilityIssue $issue): string => $issue->message, $this->issues);
    }
}
