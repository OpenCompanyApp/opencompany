<?php

namespace App\Domain\Web\Enums;

enum WebCapability: string
{
    case Search = 'search';
    case Fetch = 'fetch';
    case Crawl = 'crawl';
}
