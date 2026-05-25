<?php

namespace App\Domain\Web\Contracts;

use App\Domain\Web\ValueObjects\WebSearchRequest;
use App\Domain\Web\ValueObjects\WebSearchResponse;

interface WebSearchProvider extends WebProvider
{
    public function search(WebSearchRequest $request): WebSearchResponse;
}
