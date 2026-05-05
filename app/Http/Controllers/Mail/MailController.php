<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Mail\TestMail;
use App\Models\change_request;
use App\Models\Group;
use App\Models\MailTemplate;
use App\Models\Status;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Mail;

class MailController extends Controller
{
    public function index()
    {
        $templates = MailTemplate::get();
        $users = User::get();
        $groups = Group::get();

        return view('manual_email.create', compact('users', 'templates', 'groups'));
    } // end method

    public function sendMailByTemplate($templateName, $to, $cc = [])
    {

        $template = MailTemplate::where('name', $templateName)->first();

        if (! $template) {
            return response()->json(['error' => 'Template not found.'], 404);
        }

        $templateContent = [
            'subject' => $template->subject,
            'body' => $template->body,
        ];

        // Send mail
        Mail::to($to)
            ->cc($cc)
            ->send(new TestMail($templateContent));

        return response()->json(['message' => 'Mail sent successfully']);
    } // end method

    public function notifyRequesterCrCreated($requester_email, $cr, $cr_no)
    {
        $email_parts = explode('.', explode('@', $requester_email)[0]);
        $first_name = ucfirst($email_parts[0]);

        $cr_link = route('show.cr', $cr);

        $templateContent = [
            'subject' => "CR #$cr_no has been created",
            'body' => "Dear $first_name, <br><br>"
            . "CR #$cr_no has been created successfully."
            . '<br><br>'
            . "You can review it here: <a href='$cr_link'>CR: #$cr_no</a>"
            . '<br><br>'
            . 'Thank you',
        ];

        try {
            // Send the email
            Mail::to($requester_email)->send(new TestMail($templateContent));

            return response()->json(['success' => 'Email sent successfully.']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to send email.', 'details' => $e->getMessage()], 500);
        }

    } // end method

    // Notify dev team when status changes.
    public function notifyDevTeam($cr_id, $old_status_id, $new_status_id, $assigned_user_id)
    {
        $recipients = [];
        $cr_link = route('show.cr', $cr_id);

        $cr = Change_request::find($cr_id);
        $cr_no = $cr->cr_no;
        $cr_title = $cr->title;
        $app_id = $cr->application_id;
        $group_id = $cr->application->group_applications->first()->group_id;
        $group_name = Group::where('id', $group_id)->value('title');
        $mail = Group::where('id', $group_id)->value('head_group_email');
        $old_status = Status::where('id', $old_status_id)->value('status_name');
        $new_status = Status::where('id', $new_status_id)->value('status_name');
        /*if($assigned_user_id){
            $assigned_user = User::where('id', $assigned_user_id)->value('email');
            $recipients[] = $assigned_user;
        }*/
        if (isset($cr->developer_id)) {
            $developer = User::where('id', $cr->developer_id)->value('email');
            $recipients[] = $developer;

        }
        $recipients[] = $mail;
        // dd($recipients);
        $templateContent = [
            'subject' => "CR #$cr_no status has been changed",
            'body' => "Dear $group_name, <br><br>"
            . "CR #$cr_no status has been changed from <strong> $old_status </strong> to <strong> $new_status </strong>."
            . '<br><br>'
            . "CR Title: $cr_title"
            . '<br><br>'
            . "You can review it here: <a href='$cr_link'>CR: #$cr_no</a>"
            . '<br><br>'
            . '<strong>Note:</strong> This is an automated message sent by the <strong>IT TMS System</strong>.<br>'
            . '<strong>Best regards,</strong><br>'
            . '<strong>TMS</strong>',
        ];

        try {
            // Send the email
            Mail::to($recipients)->send(new TestMail($templateContent));

            return response()->json(['success' => 'Email sent successfully.']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to send email.', 'details' => $e->getMessage()], 500);
        }
    } // end method

    // Notify group when status changes.
    public function notifyGroup($cr_id, $old_status_id, $new_status_id, $group_id, $userToNotify)
    {
        $recipients = [];
        if (! empty($userToNotify)) {
            $emails = User::whereIn('id', $userToNotify)->pluck('email')->filter()->toArray();
            $recipients = array_merge($recipients, $emails);
        }

        $cr_link = route('show.cr', $cr_id);

        $cr = Change_request::find($cr_id);
        $cr_no = $cr->cr_no;
        $cr_title = $cr->title;
        // $app_id = $cr->application_id;
        // $group_id = $cr->application->group_applications->first()->group_id;
        $group_name = Group::where('id', $group_id)->value('title');
        $mail = Group::where('id', $group_id)->value('head_group_email');
        $old_status = Status::where('id', $old_status_id)->value('status_name');
        $new_status = Status::where('id', $new_status_id)->value('status_name');
        /*if($assigned_user_id){
            $assigned_user = User::where('id', $assigned_user_id)->value('email');
            $recipients[] = $assigned_user;
        }
        if(isset($cr->developer_id)){
            $developer = User::where('id', $cr->developer_id)->value('email');
            $recipients[] = $developer;

        } */
        $recipients[] = $mail;
        $templateContent = [
            'subject' => "CR #$cr_no status has been changed",
            'body' => "Dear $group_name, <br><br>"
            . "CR #$cr_no status has been changed from <strong> $old_status </strong> to <strong> $new_status </strong>."
            . '<br><br>'
            . "CR Title: $cr_title"
            . '<br><br>'
            . "You can review it here: <a href='$cr_link'>CR: #$cr_no</a>"
            . '<br><br>'
            . '<strong>Note:</strong> This is an automated message sent by the <strong>IT TMS System</strong>.<br>'
            . '<strong>Best regards,</strong><br>'
            . '<strong>TMS</strong>',
        ];

        try {
            // Send the email
            Mail::to($recipients)->send(new TestMail($templateContent));

            return response()->json(['success' => 'Email sent successfully.']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to send email.', 'details' => $e->getMessage()], 500);
        }
    } // end method

    public function send_mail_to_cap_users($users_mail, $cr_id, $cr_no)
    {
        $cr = Change_request::find($cr_id);
        $cr_title = $cr ? $cr->title : 'N/A';
        $cr_description = $cr ? $cr->description : 'N/A';
        $requester_name = ($cr && $cr->requester) ? $cr->requester->user_name : 'N/A';
        $ticketingDev = config('constants.mails.ticketing_dev_mail', '');
        $general_sa = config('constants.service_accounts_emails.general_sa', '');
        $subject = "Re: CR #{$cr->cr_no} - Awaiting Your Approval - CAB";

        $approveLink = "mailto:{$general_sa}?subject=" . rawurlencode($subject) . "&cc={$ticketingDev}&body=approved";
        $rejectLink = "mailto:{$general_sa}?subject=" . rawurlencode($subject) . "&cc={$ticketingDev}&body=rejected";

        foreach ($users_mail as $key => $user) {
            $email_parts = explode('.', explode('@', $user)[0]);
            $first_name = ucfirst($email_parts[0]);
            
            $cr_link = route('edit_cab.cr', $cr_id);
            
            $htmlBody = <<<HTML
            <p>Dear {$first_name},</p>
            <p>Change Request (CR) #$cr_no has been transfered To Pending Cab Status and Waiting Your Approval Or Rejection.</p>
            <p>Please use one of the buttons below to respond:</p>

            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin: 20px 0;">
            <tbody><tr>
                <td style="padding: 10px;">
                <a href="{$approveLink}" style="background-color: #28a745; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">✓ Approve</a>
                </td>
                <td style="padding: 10px;">
                <a href="{$rejectLink}" style="background-color: #dc3545; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">✗ Reject</a>
                </td>
                <td style="padding: 10px;">
                <a href="{$cr_link}" style="background-color: #007bff; color: #ffffff; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">View in TMS</a>
                </td>
            </tr>
            </tbody></table>

            <p><i>To review this request directly in the TMS system, click "View in TMS" and select "CR Pending Cab".</i></p>

            <hr>
            <p><strong>Please Note:</strong> If no action is taken within <strong>2 Working Days,</strong> the system will treat this as a <strong>passive confirmation</strong> and proceed accordingly.</p>
            <hr>
            <p><b>Change Request Information:</b></p>
            <ul>
            <li><p><b>CR Subject:</b>&nbsp;{$cr_title}</p></li>
            <li><p><b>CR Description:</b>&nbsp;{$cr_description}</p></li>
            <li><p><b>Requester:</b> {$requester_name}</p></li>
            </ul>
            <p>Thank you in advance for your prompt action.</p><hr><p><i><span style="font-weight: 600;">Note:</span>&nbsp;This is an automated message sent by the IT TMS System.</i></p><p><span style="font-weight: 600;">Best regards,</span><br><span style="font-weight: 600;">TMS</span></p>
            HTML;

            $templateContent = [
                'subject' => "[TMS] CR #{$cr_no} - Awaiting Your Approval - CAB",
                'body' => $htmlBody,
            ];

            try {
                // Send the email
                Mail::to($user)->send(new TestMail($templateContent));

                // return response()->json(['success' => 'Email sent successfully.']);
            } catch (Exception $e) {

                // return response()->json(['error' => 'Failed to send email.', 'details' => $e->getMessage()], 500);
            }
        }

        return true;
    }

    public function notifyDivisionManager($division_manager_email, $requester_email, $cr, $title, $description, $requester_name, $cr_no)
    {

        $template = MailTemplate::where('name', 'Notify Division Manager')->first();
        if (! $template) {
            return response()->json(['error' => 'Template not found.'], 404);
        }

        $email_parts = explode('.', explode('@', $division_manager_email)[0]);
        $first_name = ucfirst($email_parts[0]);

        // $cr_link = route('edit.cr', $cr);
        $cr_link = route('edit.cr', ['id' => $cr, 'check_dm' => 1]);

        $reply_to_email = config('mail.from.address');
        $qc_email = config('constants.mails.qc_mail');
        $ticketing_dev = config('constants.mails.ticketing_dev_mail');
        $subject = "Re: CR #$cr_no - Awaiting Your Approval";

        $approve_link = "mailto:$qc_email?subject=" . rawurlencode($subject) . "&cc=$reply_to_email;$ticketing_dev&body=approved";
        $reject_link = "mailto:$qc_email?subject=" . rawurlencode($subject) . "&cc=$reply_to_email;$ticketing_dev&body=rejected";

        /*
        $cr_model = Change_request::find($cr);
        $token = md5($cr_model->id . $cr_model->created_at . env('APP_KEY'));
        $approveLink = route('cr.division_manager.action', [
            'crId' => $cr,
            'action' => 'approve',
            'token' => $token
        ]);
        $rejectLink = route('cr.division_manager.action', [
            'crId' => $cr,
            'action' => 'reject',
            'token' => $token
        ]);
        . "<div style='margin: 25px 0;'>"
        . "<a href='$approveLink' style='background-color: #4CAF50; color: white; padding: 10px 20px; margin-right: 10px; text-decoration: none; border-radius: 4px;'>Approve</a>"
        . " <a href='$rejectLink' style='background-color: #f44336; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>Reject</a>"
        . "</div>"
        */
        $cr = Change_request::find($cr);
        if ($cr->workflow_type_id == '9') {

            $templateContent = [
                'subject' => "Promo #$cr_no",
                'body' => "Dear $first_name,<br><br>"

                    . "Promo with ID <strong>#<a href='$cr_link'>$cr_no</a></strong> has been created and is currently "

                    . '<ul>'
                    . "<li><strong>Promo Subject:</strong> $title</li>"
                    . "<li><strong>Promo Description:</strong> $description</li>"
                    . "<li><strong>Requester:</strong> $requester_name</li>"
                    . "<li><strong>Reference:</strong> Promo ID #$cr_no</li>"
                    . '</ul><br>'

                    . '<strong>Note:</strong> This is an automated message sent by the <strong>IT TMS System</strong>.<br>'
                    . '<strong>Best regards,</strong><br>'
                    . '<strong>TMS</strong>',
            ];
        } else {

            $templateContent = [
                'subject' => "CR #$cr_no - Awaiting Your Approval",
                'body' => "
                Dear Division,<br><br>
                Please take action with below Buttons to 
                <a href='$approve_link' style='background-color:#00cc00; color:white; padding:4px 10px; border-radius:3px; text-decoration:none;'>Approve</a>
                or 
                <a href='$reject_link' style='background-color:#ff0000; color:white; padding:4px 10px; border-radius:3px; text-decoration:none;'>Reject</a>
                the change or through the <strong>TMS system only</strong> via link 
                <a href='$cr_link'>TMS URL</a> and select <strong>CR Waiting Approval</strong>.<br><br>
            
                <strong>Disclaimer:</strong> -<br>
                Kindly <strong>avoid using \"Reply All\" or forwarding this email</strong>, as doing so may result in the 
                <strong><u>loss of your action or approval</u></strong>.
                "
            ];
        }

        try {
            // Send the email <a href='$cr_link'>$cr</a>
            Mail::to($division_manager_email)->cc(config('constants.division_managers_mails'))->send(new TestMail($templateContent));
            // Mail::to($division_manager_email)->send(new TestMail($templateContent));
            // Mail::to($division_manager_email)->send(new TestMail($templateContent));

            return response()->json(['success' => 'Email sent successfully.']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to send email.', 'details' => $e->getMessage()], 500);
        }
    }

    // Mail to Anan

    public function notifyCrManager($cr)
    {
        $cr_manager_email = config('constants.mails.cr_manager');
        $email_parts = explode('.', explode('@', $cr_manager_email)[0]);
        $first_name = ucfirst($email_parts[0]);

        $cr_link = route('show.cr', $cr);

        $templateContent = [
            'subject' => "CR #$cr status has been changed",
            'body' => "Dear $first_name, <br><br>"
            . "CR #$cr status has been changed from Review CD_CR To Promo Nature Validation."
            . '<br><br>'
            . "You can review it here: <a href='$cr_link'>CR: #$cr</a>"
            . '<br><br>'
            . 'Thank you',
        ];

        try {
            // Send the email
            Mail::to($cr_manager_email)->send(new TestMail($templateContent));

            return response()->json(['success' => 'Email sent successfully.']);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to send email.', 'details' => $e->getMessage()], 500);
        }

    } // end method

    // to send the mail
    // sendMailByTemplate('Template Name', 'recipient@example.com', ['cc@example.com']);
}
