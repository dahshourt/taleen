<?php

namespace App\Console\Commands;

use App\Mail\DynamicNotification;
use App\Models\ChangeRequestHold;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendCrHoldReminder extends Command
{
    /**
     * The name and signature of the console command.
     * Ahmed Omar
     * @var string
     */
    protected $signature = 'cr:send-hold-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder emails for CRs that are on hold and should resume the next working day';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting CR Hold Reminder check...');

        // Get the resuming dates that should receive reminder today
        $resumingDates = $this->getResumingDatesToNotify();

        if (empty($resumingDates)) {
            $this->info('No reminders to send today.');
            return 0;
        }

        $this->info('Looking for CRs resuming on: ' . implode(', ', array_map(fn($d) => $d->format('Y-m-d'), $resumingDates)));

        // Find all on-hold CRs that resume on these dates and haven't been reminded yet
        // Also check that the CR is still on hold (hold = 1)
        $holds = ChangeRequestHold::with(['changeRequest.resCrMember.user'])
            ->whereHas('changeRequest', function ($query) {
                $query->where('hold', 1);
            })
            ->whereIn(\DB::raw('DATE(resuming_date)'), array_map(fn($d) => $d->format('Y-m-d'), $resumingDates))
            ->where('reminder_sent', false)
            ->get();

        if ($holds->isEmpty()) {
            $this->info('No CRs found that need reminders.');
            return 0;
        }

        $this->info("Found {$holds->count()} CR(s) to send reminders for.");

        $successCount = 0;
        $failCount = 0;

        foreach ($holds as $hold) {
            try {
                $this->sendReminder($hold);
                
                // Mark as sent
                $hold->update(['reminder_sent' => true]);
                
                $this->info("Reminder sent for CR #{$hold->changeRequest->cr_no}");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("Failed to send reminder for CR #{$hold->changeRequest->cr_no}: " . $e->getMessage());
                $failCount++;
                // Continue with next CR
            }
        }

        $this->info("Completed: {$successCount} sent, {$failCount} failed.");

        return 0;
    }

    
    // Get the resuming dates that should receive a reminder today.
    private function getResumingDatesToNotify(): array
    {
        $today = Carbon::now();

        // Skip if today is weekend (Friday or Saturday)
        if ($this->isWeekend($today)) {
            return [];
        }

        $dates = [];

        // Get next working day
        $nextWorkingDay = $today->copy()->addDay();
        while ($this->isWeekend($nextWorkingDay)) {
            $nextWorkingDay->addDay();
        }

        $dates[] = $nextWorkingDay;

        // If today is Wednesday, also include Friday and Saturday
        if ($today->isWednesday()) {
            $dates[] = $today->copy()->addDays(2); // Friday
            $dates[] = $today->copy()->addDays(3); // Saturday
        }

        return $dates;
    }

    
    // Check if the given date is a weekend (Friday or Saturday).
    private function isWeekend(Carbon $date): bool
    {
        return $date->isFriday() || $date->isSaturday();
    }

   
    // Send the reminder email for a hold record.
     
    private function sendReminder(ChangeRequestHold $hold): void
    {
        $cr = $hold->changeRequest;
        $recipients = $this->getRecipients($hold);

        if (empty($recipients['to'])) {
            throw new \Exception('No recipients found');
        }

        $subject = "[TMS] Reminder: CR #{$cr->cr_no} - On-Hold CR Resuming Soon";
        $body = $this->buildEmailBody($hold);

        // Send the email
        Mail::to($recipients['to'])
            ->cc($recipients['cc'] ?? [])
            ->queue(new DynamicNotification($subject, $body));

        // Log to notification_logs table
        $this->logNotification($hold, $subject, $body, $recipients);
    }

    
    // Get recipients.
     
    private function getRecipients(ChangeRequestHold $hold): array
    {
        $to = [];
        $cc = [];

        // cr member mail from cr assignee table
        $crMember = $hold->changeRequest->resCrMember;
        if ($crMember && $crMember->user && $crMember->user->email) {
            $to[] = $crMember->user->email;
        }

        // cr team
        $crTeamEmail = config('constants.mails.cr_team');
        if ($crTeamEmail) {
            // If we have a CR member, add team to CC; otherwise, send directly to team
            if (!empty($to)) {
                $cc[] = $crTeamEmail;
            } else {
                $to[] = $crTeamEmail;
            }
        }

        return ['to' => array_unique($to), 'cc' => array_unique($cc)];
    }

    // the mail body

    private function buildEmailBody(ChangeRequestHold $hold): string
    {
        $cr = $hold->changeRequest;
        $crLink = route('show.cr', $cr->id);
        $resumingDate = Carbon::parse($hold->resuming_date)->format('l, F j, Y');

        return "
            <p>Dears,</p>
            
            <p>Please be informed that <strong>CR #{$cr->cr_no}</strong>, which has been on hold, should be resumed.</p>
            
            <p>Kindly proceed with the required action on the below date:</p>
            
            <table style='border-collapse: collapse; margin: 15px 0;'>
                <tr>
                    <td style='padding: 5px 15px 5px 0; font-weight: bold;'>Resuming Date:</td>
                    <td style='padding: 5px 0;'>{$resumingDate}</td>
                </tr>
                <tr>
                    <td style='padding: 5px 15px 5px 0; font-weight: bold;'>CR Title:</td>
                    <td style='padding: 5px 0;'>{$cr->title}</td>
                </tr>
            </table>
            
            <p>You can review it here: <a href='{$crLink}'><b>View CR #{$cr->cr_no}</b></a></p>
            
            <hr style='border: none; border-top: 1px solid #ccc; margin: 20px 0;'>
            
            <p><i><b>Note:</b> This is an automated message sent by the IT TMS System.</i></p>
            
            <p><b>Best regards,</b><br><b>TMS</b></p>
        ";
    }

    
    // Log the notification to notification_logs table.
    
    private function logNotification(ChangeRequestHold $hold, string $subject, string $body, array $recipients): void
    {
        NotificationLog::create([
            'notification_rule_id' => null,
            'template_id' => null,
            'event_class' => 'App\\Console\\Commands\\SendCrHoldReminder',
            'event_data' => [
                'hold_id' => $hold->id,
                'cr_id' => $hold->change_request_id,
                'resuming_date' => $hold->resuming_date,
            ],
            'subject' => $subject,
            'body' => $body,
            'recipients_to' => $recipients['to'],
            'recipients_cc' => $recipients['cc'] ?? [],
            'recipients_bcc' => [],
            'status' => 'queued',
            'related_model_type' => ChangeRequestHold::class,
            'related_model_id' => $hold->id,
        ]);
    }
}
