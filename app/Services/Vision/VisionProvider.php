<?php

namespace App\Services\Vision;

use App\Models\Screenshot;

interface VisionProvider
{
    /**
     * Whether this provider is configured (API key present).
     */
    public function enabled(): bool;

    /**
     * Finding source tag persisted with each finding (e.g. Finding::SOURCE_OPENAI).
     */
    public function source(): string;

    /**
     * Critique a screenshot with the given prompt.
     *
     * @return list<array{severity: string, body: string, area: array<string, float>|null}>
     */
    public function findings(Screenshot $screenshot, string $prompt): array;
}
