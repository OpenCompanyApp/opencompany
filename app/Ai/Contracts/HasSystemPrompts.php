<?php

namespace App\Ai\Contracts;

interface HasSystemPrompts
{
    /**
     * @return string[]
     */
    public function systemPrompts(): array;
}
