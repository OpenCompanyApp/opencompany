<?php

namespace App\Services\Memory;

use Illuminate\Support\Collection;

class CompactionPlan
{
    /**
     * @param  Collection<int, \App\Models\Message>  $messagesToSummarize
     * @param  Collection<int, \App\Models\Message>  $messagesToKeep
     */
    public function __construct(
        public readonly Collection $messagesToSummarize,
        public readonly Collection $messagesToKeep,
        public readonly int $splitIndex,
        public readonly int $tokensToSummarize,
        public readonly int $tokensToKeep,
    ) {}

    public function lastSummarizedMessageId(): ?string
    {
        return $this->messagesToSummarize->last()?->id;
    }
}
