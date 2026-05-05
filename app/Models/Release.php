<?php

namespace App\Models;

use App\Traits\DynamicColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    use DynamicColumns;

    protected $fillable = [
        'name',
        'vendor_id',
        'priority_id',
        'target_system_id',
        'responsible_rtm_id',
        'creator_rtm_name',
        'rtm_email',
        'release_description',
        'release_start_date',
        'go_live_planned_date',
        'atp_review_start_date',
        'atp_review_end_date',
        'vendor_internal_test_start_date',
        'vendor_internal_test_end_date',
        'iot_start_date',
        'iot_end_date',
        'e2e_start_date',
        'e2e_end_date',
        'uat_start_date',
        'uat_end_date',
        'smoke_test_start_date',
        'smoke_test_end_date',
        'release_status',
        'release_status_id',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function priority()
    {
        return $this->belongsTo(Priority::class);
    }

    public function targetSystem()
    {
        return $this->belongsTo(Application::class, 'target_system_id');
    }

    public function responsibleRtm()
    {
        return $this->belongsTo(User::class, 'responsible_rtm_id');
    }

    public function status()
    {
        return $this->belongsTo(ReleaseStatus::class, 'release_status_id');
    }

    public function releaseStatusObj()
    {
        return $this->belongsTo(ReleaseStatus::class, 'release_status_id');
    }

    public function attachments()
    {
        return $this->hasMany(ReleaseAttachment::class);
    }

    public function feedbacks()
    {
        return $this->hasMany(ReleaseFeedback::class);
    }

    public function stakeholders()
    {
        return $this->hasMany(ReleaseStakeholder::class);
    }

    public function changeRequests()
    {
        return $this->hasMany(Change_request::class, 'release_name');
    }

    public function risks()
    {
        return $this->hasMany(ReleaseRisk::class);
    }

    public function teamMembers()
    {
        return $this->hasMany(ReleaseTeamMember::class);
    }

    public function crAttachments()
    {
        return $this->hasMany(ReleaseCrAttachment::class);
    }
}
