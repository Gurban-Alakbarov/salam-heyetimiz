<?php

namespace App\Domain\Site\Enums;

enum SiteLeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Closed = 'closed';
}
