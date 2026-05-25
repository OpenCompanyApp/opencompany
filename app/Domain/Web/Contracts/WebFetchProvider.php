<?php

namespace App\Domain\Web\Contracts;

use App\Domain\Web\ValueObjects\WebFetchRequest;
use App\Domain\Web\ValueObjects\WebFetchResponse;

interface WebFetchProvider extends WebProvider
{
    public function fetch(WebFetchRequest $request): WebFetchResponse;
}
