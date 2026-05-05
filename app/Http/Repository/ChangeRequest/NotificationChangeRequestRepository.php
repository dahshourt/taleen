<?php

namespace App\Http\Repository\ChangeRequest;

use App\Contracts\ChangeRequest\NotificationChangeRequestRepositoryInterface;
// declare Entities
use App\Models\Change_request;
use App\Notifications\ApprovalMail;
use App\Notifications\mail;
use Notification;

class AttachmentsCRSRepository implements NotificationChangeRequestRepositoryInterface
{
    public function send_notification($id)
    {
        $cr = Change_request::with('division_manger', 'requester')->where('id', $id)->first();
        $user = $cr->requester;
        $division = $cr->division_manger->email;

        if ($cr->workflow_type_id == 4) {
            $user = $cr->requester;
            $division = $cr->division_manger->email;
            // dd($cr->division_manger->email);
            $details = [
                'subject' => 'New CR#' . $cr->cr_no,
                'body' => 'New Creation CR' . $cr->title,
                'thanks' => 'Thank you for using App',
                'actionText' => 'View My Site',
                'actionURL' => url('/'),
                'email_cc' => $division,
            ];
            Notification::send($user, new mail($details));
        }
        if ($cr->workflow_type_id == 3) {
            $details = [
                'subject' => 'New CR#' . $cr->cr_no,
                'body' => 'New Creation CR' . $cr->title,
                'thanks' => 'Thank you for using App',
                'actionText' => 'View My Site',
                'actionURL' => url('/'),
            ];
            // dd($cr->division_manger->email);
            $approval = [
                'subject' => ' Request Approval on CR#' . $cr->cr_no,
                'body' => 'kindly approve on CR' . $cr->cr_no,
                'thanks' => 'Thank you for using App',
                'actionText' => 'View My Site',
                'actionURL' => url('/'),
            ];

            Notification::send($cr->division_manger, new ApprovalMail($approval));

            Notification::send($user, new mail($details));
        }
    }
}
