<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationImpact extends Model
{
    use HasFactory;

    protected $table = 'application_impact';

    // Relationships
    public function impact()
    {
        return $this->belongsTo(DeploymentImpact::class, 'impacts_id');
    }

    public function application()
    {
        return $this->belongsTo(Application::class, 'application_id');
    }
}
