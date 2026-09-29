<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateCounter extends Model
{
    protected $fillable = ['counter_id', 'badge_id', 'year', 'last_number'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'last_number' => 'integer'];
    }
}
