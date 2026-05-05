<?php

namespace App\Factories\SrRequesterApprovals;

use App\Http\Repository\SrRequesterApprovals\SrRequesterApprovalRepository;

class SrRequesterApprovalFactory
{
    public static function index()
    {
        return new SrRequesterApprovalRepository();
    }
}
