<?php

namespace App\Domain\Site\Models;

use App\Domain\Site\Enums\SiteLeadStatus;
use Illuminate\Database\Eloquent\Model;

/** A request sent through the contact form on the public website. */
class SiteLead extends Model
{
    protected $table = 'site_leads';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => SiteLeadStatus::class,
        ];
    }
}
