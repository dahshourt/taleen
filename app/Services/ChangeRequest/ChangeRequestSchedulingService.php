<?php

namespace App\Services\ChangeRequest;

use App\Http\Repository\ChangeRequest\AttachmentsCRSRepository;
use App\Http\Repository\ChangeRequest\ChangeRequestRepository;
use App\Http\Repository\ChangeRequest\ChangeRequestStatusRepository;
use App\Http\Repository\Logs\LogRepository;
use App\Models\Change_request;
use App\Models\ChangeRequestHold;
use App\Models\Group;
use App\Models\User;
use App\Traits\ChangeRequest\ChangeRequestConstants;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Log;

class ChangeRequestSchedulingService
{
    use ChangeRequestConstants;

    protected $logRepository;

    public function __construct()
    {
        $this->logRepository = new LogRepository();
        // $this->changeRequestStatusRepository = new ChangeRequestStatusRepository();
        // $this->workflowRepository = new NewWorkflowRepository();
    }
    // public function reorderTimes($crId): array
    // {
    //     try {
    //         $cr = Change_request::find($crId);

    //         if (!$cr) {
    //             return [
    //                 'status' => false,
    //                 'message' => "Change Request with ID {$crId} not found."
    //             ];
    //         }

    //         $cr_found_design=Change_request::with('RequestStatuses')
    //         ->where('designer_id', $cr->designer_id)
    //         ->where('id', $crId)

    //         ->whereHas('RequestStatuses', function ($query) {
    //           $query->whereIn('new_status_id', [15, 7]);
    //         })
    //         ->first();

    //         if ($cr_found_design) {
    //             $this->processDesignPhase($cr);
    //         }
    //         if (!$cr_found_design) {
    //             $this->processDevelopmentPhase($cr);
    //             $this->processTestingPhase($cr);
    //             $this->reorderQueuedRequests($cr, $crId);
    //         }
    //      // die("walid");

    //         return [
    //             'status' => true,
    //             'message' => "Successfully reordered times for CR ID {$crId} and related queued CRs."
    //         ];

    //     } catch (\Exception $e) {
    //         return [
    //             'status' => false,
    //             'message' => 'An error occurred while reordering the times: ' . $e->getMessage()
    //         ];
    //     }
    // }
    public function reorderTimes($id): array
    {
        try {

            $crId = Change_request::where('cr_no', $id)->first()->id;
            $cr = Change_request::find($crId);

            if (! $cr) {
                return [
                    'status' => false,
                    'message' => "Change Request with ID {$cr->cr_no} not found.",
                ];
            }

            $priority = request()->has('priority');

            // Check statuses
            $hasDevelopStatus = $cr->RequestStatuses()
                ->whereIn('new_status_id', [10, 8])
                ->exists();

            $hasDesignStatus = $cr->RequestStatuses()
                ->whereIn('new_status_id', [15, 7])
                ->exists();
            $is_done_test = $cr->RequestStatusesDone()
                ->where('new_status_id', 13)
                ->exists();

            // Get the actual records instead of just exists()

            $isdesign = false;

            if ($hasDesignStatus) {
                $isdesign = true;
                $this->processDesignPhase($cr);
            }

            //     if ($hasDevelopStatus) {
            //        // die("dd");
            //         $this->processDevelopPhase($cr, $priority);
            //         $this->reorderAllTesterQueues();

            //     }
            // $hasDesignStatus = $cr->RequestStatuses()
            //     ->whereIn('new_status_id', [15, 7])
            //     ->exists();

            //     $hasTestStatus = $cr->RequestStatuses()
            //     ->whereIn('new_status_id', [74, 11])
            //     ->exists();

            //     if($hasTestStatus){
            //         $this->processtestPhase($cr, $priority);

            //     }

            if (! $isdesign) {
                $isdevelop = false;
                if ($hasDevelopStatus) {
                    $isdevelop = true;
                    $this->processDevelopPhase($cr, $priority);
                    $this->reorderAllTesterQueues();

                }
                if (! $isdevelop && $is_done_test != 1) {
                    // $this->processtestPhase($cr, $priority);
                    $this->processTestingPhase($cr);

                }

            }

            // After reordering develop phase, reorder ALL tester queues to avoid conflicts

            return [
                'status' => true,
                'message' => "Successfully reordered times for CR ID {$cr->cr_no}.",
            ];
        } catch (Exception $e) {

            return [
                'status' => false,
                'message' => 'An error occurred while reordering the times: ' . $e->getMessage(),
            ];
        }
    }
    //     protected function processTestPhase($cr): void
    // {
    //     if (!$cr || $cr->test_duration <= 0) {
    //         throw new \Exception("Invalid CR or no test duration.");
    //     }

    //     // Find the last booked CR for this tester
    //     $activeCr = Change_request::where('tester_id', $cr->tester_id)
    //         ->where('id', '!=', $cr->id)
    //         ->orderBy('end_test_time', 'desc')
    //         ->first();

    //         $Cr_test_in_progress = Change_request::where('tester_id', $cr->tester_id)
    //         ->where('id', '=', $cr->id)
    //         ->whereHas('RequestStatuses', function ($query) {
    //             $query->where('new_status_id', 74);
    //         })

    //         ->first();

    //         if($Cr_test_in_progress){
    //             throw new \Exception("test phase already in progress for CR ID {$cr->id}.");

    //         }

    //     $hasPriority = request()->has('priority');

    //     if ($activeCr && !$hasPriority && $activeCr->end_test_time) {
    //         // Tester is busy → start after their last test finishes
    //         $startTestTime = Carbon::parse($activeCr->end_test_time);
    //     } else {
    //         // Tester is free OR priority CR → start now at working hours
    //         $startTestTime = Carbon::createFromTimestamp(
    //             $this->setToWorkingDate(Carbon::now()->timestamp)
    //         );

    //         // Set current CR status to active testing (13)
    //       //  $cr->RequestStatuses()->update(['new_status_id' => 13]);

    //         if ($hasPriority) {

    //             $repo = new \App\Http\Repository\ChangeRequest\ChangeRequestRepository();

    //             $req = new \Illuminate\Http\Request([
    //                 'old_status_id' => 11,
    //                 'new_status_id' => 139,
    //                 //propagate sender email for repo user resolution logic
    //                 'assign_to'     => null,
    //             ]);

    //         //    $this->logRepository->logCreate($cr->id, $req, $cr, 'create');
    //                 $repo->UpateChangeRequestStatus($cr->id, $req);
    //             // Demote other CRs in testing from 13 to 11 (queued)
    //             Change_request::where('tester_id', $cr->tester_id)
    //                 ->where('id', '!=', $cr->id)
    //                 ->whereHas('RequestStatuses', function ($query) {
    //                     $query->where('new_status_id', 74);
    //                 })
    //                 ->each(function ($otherCr) {

    //                     // $otherCr->RequestStatuses()->update(['new_status_id' => 7]);

    //                     $lastTwoStatuses = $otherCr->AllRequestStatuses()
    //                     ->orderBy('id', 'desc')
    //                     ->take(2)
    //                     ->get();

    //                     if ($lastTwoStatuses->count() == 2) {
    //                         $latest   = $lastTwoStatuses[0]; // newest
    //                         $previous = $lastTwoStatuses[1]; // before newest
    //         //die("ddd");

    //                         // 2️⃣ Make latest inactive
    //                         $latest->timestamps = false;
    //                         $latest->update(['active' => '0']);

    //                         // 3️⃣ Insert a copy of previous with active=1

    //                         $request=$otherCr->RequestStatuses()->create([
    //  'old_status_id' => $previous->old_status_id,
    //  'new_status_id' => $previous->new_status_id,
    //  'assign_to'     => $previous->assign_to,
    //  'active'        => '1',
    //  'user_id'       => $previous->user_id,
    //  'created_at'    => now(),
    //  'updated_at'    => now(),
    // ]);
    // //$this->logRepository->logCreate($cr->id, $req, $cr, 'create');
    // //$this->logRepository->logCreate($otherCr->id, $request, $otherCr, 'create');

    // $newReq = new \Illuminate\Http\Request($request->toArray());
    // $this->logRepository->logCreate($otherCr->id, $newReq, $otherCr, 'create');
    //                     }

    //                  });
    //         }
    //     }

    //     // Calculate end time for this CR
    //     $endTestTime = Carbon::parse(
    //         $this->generateEndDate(
    //             $startTestTime->timestamp,
    //             $cr->test_duration,
    //             false,
    //             $cr->tester_id,
    //             'test'
    //         )
    //     );

    //     $cr->update([
    //         'start_test_time' => $startTestTime->format('Y-m-d H:i:s'),
    //         'end_test_time'   => $endTestTime->format('Y-m-d H:i:s'),
    //     ]);

    //     // Reorder queued CRs (status 11 only)
    //     $queue = Change_request::where('tester_id', $cr->tester_id)
    //         ->where('id', '!=', $cr->id)
    //         ->whereHas('RequestStatuses', function ($query) {
    //             $query->where('new_status_id', 11); // queued test
    //         })
    //         ->orderBy('id')
    //         ->get();

    //     foreach ($queue as $queuedCr) {
    //         if (!empty($queuedCr->test_duration) && $queuedCr->test_duration > 0) {
    //             // Start after the last CR in the tester's queue
    //             $startTestTime = Carbon::parse($endTestTime);

    //             $endTestTime = Carbon::parse(
    //                 $this->generateEndDate(
    //                     $startTestTime->timestamp,
    //                     $queuedCr->test_duration,
    //                     false,
    //                     $queuedCr->tester_id,
    //                     'test'
    //                 )
    //             );

    //             $queuedCr->update([
    //                 'start_test_time' => $startTestTime->format('Y-m-d H:i:s'),
    //                 'end_test_time'   => $endTestTime->format('Y-m-d H:i:s'),
    //             ]);
    //         }
    //     }
    // }

    public function holdPromo($id, array $holdData = []): array
    {
        try {
            $cr = Change_request::where('cr_no', $id)
                ->first();

            // If not found
            if (! $cr) {
                return [
                    'status' => false,
                    'message' => "Change Request with number {$id} not found.",
                ];
            }

            // Check if on hold
            if ($cr->hold == 1) {
                return [
                    'status' => false,
                    'message' => "Change Request #{$cr->cr_no} is currently on hold.",
                ];
            }

            DB::transaction(function () use ($cr, $holdData) {
                // Create the hold record if hold data is provided
                if (! empty($holdData)) {
                    ChangeRequestHold::create([
                        'change_request_id' => $cr->id,
                        'hold_reason_id' => $holdData['hold_reason_id'],
                        'resuming_date' => $holdData['resuming_date'],
                        'justification' => $holdData['justification'] ?? null,
                    ]);

                    if (request()->hasFile('attachments')) {
                        $attachments_repo = app(AttachmentsCRSRepository::class);

                        $attachments = request()->file('attachments');
                        $attachments_repo->add_files($attachments, $cr->id, 2);
                    }
                }

                $cr->RequestStatuses()
                    ->where('active', '1')
                    ->update(['active' => '3']);

                // $cr->update(['hold' => 1]);
                $cr_repo = app(ChangeRequestRepository::class);
                $data = new Request(['hold' => 1]);
                $cr_repo->update($cr->id, $data);
                //dd('2');
            });

            return [
                'status' => true,
                'message' => "Successfully hold promo CR #{$cr->cr_no}.",
            ];
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'An error occurred while hold : ' . $e->getMessage(),
            ];
        }
    }

    public function reorderChangeRequests($id)
    {

        $crId = Change_request::where('cr_no', $id)->first()->id;

        $changeRequest = Change_request::find($crId);

        if (! $changeRequest) {
            return 'Change Request not found';
        }

        // Adjust times for this change request
        $this->adjustTimesForChangeRequest($crId, $changeRequest);

        // Get developer, tester, and designer for future requests
        $developerId = $changeRequest->developer_id;
        $testerId = $changeRequest->tester_id;
        $designerId = $changeRequest->designer_id;

        // Fetch all other CRs for the same developer, tester, and designer
        $otherChangeRequests = Change_request::where(function ($query) use ($developerId, $testerId, $designerId) {
            $query->whereIn('developer_id', [$developerId])
                ->orWhereIn('tester_id', [$testerId])
                ->orWhereIn('designer_id', [$designerId]);
        })
            ->where('id', '!=', $crId)
            ->orderBy('start_design_time', 'asc')
            ->get();

        // Reorder other CRs
        foreach ($otherChangeRequests as $otherRequest) {
            $this->adjustTimesForChangeRequest($crId, $otherRequest);
        }

        return 'Change requests reordered successfully';
    }

    public function reorderCRQueues(string $crNumber): array
    {
        $targetCR = Change_request::where('id', $crNumber)->first();

        if (! $targetCR) {
            return [
                'status' => false,
                'message' => 'Change Request not found.',
            ];
        }

        $this->shiftQueue($targetCR->developer_id, 'developer_id', $targetCR->id);
        $this->shiftQueue($targetCR->tester_id, 'tester_id', $targetCR->id);
        $this->shiftQueue($targetCR->designer_id, 'designer_id', $targetCR->id);

        return [
            'status' => true,
            'message' => 'Change Request reordered successfully.',
        ];
    }

    // Conflict detection methods
    public function isDeveloperBusy($developerId, $startDevelopTime, $developDuration, $endDevelopTime, $shiftingCrId = null)
    {
        return Change_request::where('developer_id', $developerId)
            ->where('id', '!=', $shiftingCrId)
            ->where(function ($query) use ($startDevelopTime, $endDevelopTime) {
                $query->whereBetween('start_develop_time', [$startDevelopTime, $endDevelopTime])
                    ->orWhereBetween('end_develop_time', [$startDevelopTime, $endDevelopTime])
                    ->orWhere(function ($query) use ($startDevelopTime, $endDevelopTime) {
                        $query->where('start_develop_time', '<=', $startDevelopTime)
                            ->where('end_develop_time', '>=', $endDevelopTime);
                    });
            })
            ->first();
    }

    public function isDesignerBusy($designerId, $startDesignerTime, $designerDuration, $endDesignerTime, $shiftingCrId = null)
    {
        return Change_request::where('designer_id', $designerId)
            ->where('id', '!=', $shiftingCrId)
            ->where(function ($query) use ($startDesignerTime, $endDesignerTime) {
                $query->whereBetween('start_design_time', [$startDesignerTime, $endDesignerTime])
                    ->orWhereBetween('end_design_time', [$startDesignerTime, $endDesignerTime])
                    ->orWhere(function ($query) use ($startDesignerTime, $endDesignerTime) {
                        $query->where('start_design_time', '<=', $startDesignerTime)
                            ->where('end_design_time', '>=', $endDesignerTime);
                    });
            })
            ->first();
    }

    public function isTesterBusy($testerId, $startTestTime, $testDuration, $endTestTime, $shiftingCrId = null)
    {
        return Change_request::where('tester_id', $testerId)
            ->where('id', '!=', $shiftingCrId)
            ->where(function ($query) use ($startTestTime, $endTestTime) {
                $query->whereBetween('start_test_time', [$startTestTime, $endTestTime])
                    ->orWhereBetween('end_test_time', [$startTestTime, $endTestTime])
                    ->orWhere(function ($query) use ($startTestTime, $endTestTime) {
                        $query->where('start_test_time', '<=', $startTestTime)
                            ->where('end_test_time', '>=', $endTestTime);
                    });
            })
            ->first();
    }

    // Helper methods for working time calculations
    public function setToWorkingDate($date): int
    {
        if ($date instanceof \Carbon\Carbon) {
            $date = $date->timestamp;
        }

        $workingHours = config('change_request.working_hours');
        $weekendDays = $workingHours['weekend_days'];

        // Weekend handling
        if (in_array((int) date('w', $date), $weekendDays)) {
            $daysToAdd = $weekendDays[0] == 5 ? 2 : 1; // If Friday is weekend, add 2 days, otherwise 1
            $date = strtotime(date('Y-m-d 08:00:00', $date) . " +{$daysToAdd} days");
        }

        // Working hours handling
        $hour = (int) date('G', $date);
        $startHour = $workingHours['start'];
        $endHour = $workingHours['end'];

        if ($hour >= $endHour) {
            $date = strtotime(date("Y-m-d {$startHour}:00:00", $date) . ' +1 days');
        } elseif ($hour < $startHour) {
            $date = strtotime(date("Y-m-d {$startHour}:00:00", $date));
        }

        // Re-check weekend after adjustment
        if (in_array((int) date('w', $date), $weekendDays)) {
            $daysToAdd = $weekendDays[0] == 5 ? 2 : 1;
            $date = strtotime(date("Y-m-d {$startHour}:00:00", $date) . " +{$daysToAdd} days");
        }

        return $date;
    }

    public function generateEndDate($startDate, $duration, $onGoing, $userId = 0, $action = 'dev'): string
    {
        $defaultValues = $this->getDefaultValues();
        $manPower = $defaultValues['man_power'];
        $manPowerOngoing = $defaultValues['man_power_ongoing'];

        // Get user-specific or group-specific man power
        if ($userId > 0) {
            $assignUser = User::find($userId);
            if ($assignUser && $assignUser->defualt_group) {
                $groupPower = $assignUser->defualt_group->man_power;
                $userManPower = $assignUser->man_power;

                if ($userManPower) {
                    $manPower = $userManPower;
                    $manPowerOngoing = $userManPower == 8 ? 1 : 8 - $userManPower;
                } else {
                    $manPower = $groupPower;
                    $manPowerOngoing = $groupPower == 8 ? 1 : 8 - $groupPower;
                }
            }
        }

        // Prevent division by zero
        if ($manPowerOngoing == 0) {
            $manPowerOngoing = 1;
        }
        if ($manPower == 0) {
            $manPower = 1;
        }

        // Calculate working hours needed
        $estimationMultiplier = $this->getDefaultValues()['estimation_multiplier'];
        $multiplier = $estimationMultiplier[$action] ?? 1;

        $i = ($action == 'dev')
            ? ($duration * (int) (($onGoing) ? (8 / $manPowerOngoing) : (8 / $manPower)))
            : $duration * $multiplier;

        $time = $startDate;
        $workingHours = $this->getWorkingHours();
        $weekendDays = $workingHours['weekend_days'];

        while ($i != 0) {
            $time = strtotime('+1 hour', $time);
            $dayOfWeek = (int) date('w', $time);
            $hour = (int) date('G', $time);

            // Only count working hours
            if (! in_array($dayOfWeek, $weekendDays) &&
                $hour < $workingHours['end'] &&
                $hour >= $workingHours['start']) {
                $i--;
            }
        }

        return date('Y-m-d H:i:s', $time);
    }

    public function isStartTimeInFuture($startTime): bool
    {
        $start = Carbon::parse($startTime);
        $now = Carbon::now();

        return $now->lessThan($start);
    }

    protected function processTestPhase($cr): void
    {
        if (! $cr || $cr->test_duration <= 0) {
            throw new Exception('Invalid CR or no test duration.');
        }

        // Find if there is already an active CR (status 74) for this tester
        $activeCr = Change_request::where('tester_id', $cr->tester_id)
            ->where('id', '!=', $cr->id)
            ->whereHas('RequestStatuses', function ($query) {
                $query->where('new_status_id', 74);
            })
            ->orderBy('end_test_time', 'desc')
            ->first();

        // Check if this CR is already in progress
        $Cr_test_in_progress = Change_request::where('tester_id', $cr->tester_id)
            ->where('id', '=', $cr->id)
            ->whereHas('RequestStatuses', function ($query) {
                $query->where('new_status_id', 74);
            })
            ->first();

        if ($Cr_test_in_progress) {
            throw new Exception("Test phase already in progress for CR ID {$cr->cr_no}.");
        }

        $hasPriority = request()->has('priority');

        if ($activeCr && ! $hasPriority) {
            // Normal CR → start after current active CR finishes
            $startTestTime = Carbon::parse($activeCr->end_test_time);
        } else {
            // Has priority OR no active CR → start now
            $startTestTime = Carbon::createFromTimestamp(
                $this->setToWorkingDate(Carbon::now()->timestamp)
            );

            $repo = new \App\Http\Repository\ChangeRequest\ChangeRequestRepository();

            $req = new \Illuminate\Http\Request([
                'old_status_id' => 11,  // queued test
                'new_status_id' => 139, // shifting
                'assign_to' => null,
            ]);

            $this->logRepository->logCreate($cr->id, $req, $cr, 'shifting');
            $repo->UpateChangeRequestStatus($cr->id, $req);

            if ($hasPriority) {
                // Demote other CRs from active test (74) → queued test (11)
                Change_request::where('tester_id', $cr->tester_id)
                    ->where('id', '!=', $cr->id)
                    ->whereHas('RequestStatuses', function ($query) {
                        $query->where('new_status_id', 74);
                    })
                    ->each(function ($otherCr) {
                        $lastTwoStatuses = $otherCr->AllRequestStatuses()
                            ->orderBy('id', 'desc')
                            ->take(2)
                            ->get();

                        if ($lastTwoStatuses->count() == 2) {
                            $latest = $lastTwoStatuses[0];
                            $previous = $lastTwoStatuses[1];

                            // Make latest inactive
                            $latest->timestamps = false;
                            $latest->update(['active' => '0']);

                            // Insert a copy of previous with active=1
                            $otherCr->RequestStatuses()->create([
                                'old_status_id' => $previous->old_status_id,
                                'new_status_id' => $previous->new_status_id, // queued test
                                'assign_to' => $previous->assign_to,
                                'active' => '1',
                                'user_id' => $previous->user_id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            // Log the status change
                            $logRequest = new \Illuminate\Http\Request([
                                'old_status_id' => $previous->old_status_id,
                                'new_status_id' => 139, // shifting
                                'assign_to' => $previous->assign_to,
                            ]);

                            $this->logRepository->logCreate($otherCr->id, $logRequest, $otherCr, 'shifting');
                        }
                    });
            }
        }

        // Calculate end time for this CR
        $endTestTime = Carbon::parse(
            $this->generateEndDate(
                $startTestTime->timestamp,
                $cr->test_duration,
                false,
                $cr->tester_id,
                'test'
            )
        );

        $cr->update([
            'start_test_time' => $startTestTime->format('Y-m-d H:i:s'),
            'end_test_time' => $endTestTime->format('Y-m-d H:i:s'),
        ]);

        // Reorder queued CRs (status 11 only)
        $queue = Change_request::where('tester_id', $cr->tester_id)
            ->where('id', '!=', $cr->id)
            ->whereHas('RequestStatuses', function ($query) {
                $query->where('new_status_id', 11); // queued test
            })
            ->orderBy('start_test_time')
            ->get();

        foreach ($queue as $queuedCr) {
            if (! empty($queuedCr->test_duration) && $queuedCr->test_duration > 0) {
                $startTestTime = Carbon::parse($endTestTime);

                $endTestTime = Carbon::parse(
                    $this->generateEndDate(
                        $startTestTime->timestamp,
                        $queuedCr->test_duration,
                        false,
                        $queuedCr->tester_id,
                        'test'
                    )
                );

                $queuedCr->update([
                    'start_test_time' => $startTestTime->format('Y-m-d H:i:s'),
                    'end_test_time' => $endTestTime->format('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    protected function processDevelopPhase($cr): void
    {
        if (! $cr || $cr->develop_duration <= 0) {
            throw new Exception('Invalid CR or no develop duration.');
        }

        // Find if there is already an active CR (status 10) for this developer
        $activeCr = Change_request::where('developer_id', $cr->developer_id)
            ->where('id', '!=', $cr->id)
            ->whereHas('RequestStatuses', function ($query) {
                $query->where('new_status_id', 10);
            })
            ->orderBy('end_develop_time', 'desc')
            ->first();

        // Check if develop already in progress for this CR
        $Cr_develop_in_progress = Change_request::where('developer_id', $cr->developer_id)
            ->where('id', '=', $cr->id)
            ->whereHas('RequestStatuses', function ($query) {
                $query->where('new_status_id', 10);
            })
            ->first();

        if ($Cr_develop_in_progress) {
            throw new Exception("Develop phase already in progress for CR ID {$cr->cr_no}.");
        }

        $hasPriority = request()->has('priority');

        if ($activeCr && ! $hasPriority) {
            // Normal CR → start after current active CR finishes (+1 hour)
            $startDevelopTime = Carbon::parse($activeCr->end_develop_time)->addHour();
        } else {
            // Has priority OR no active CR → start now
            $startDevelopTime = Carbon::createFromTimestamp(
                $this->setToWorkingDate(Carbon::now()->addHours(3)->timestamp)
            );

            // Handle status shifting for priority CR
            if ($hasPriority) {
                $repo = new \App\Http\Repository\ChangeRequest\ChangeRequestRepository();
                $req = new \Illuminate\Http\Request([
                    'old_status_id' => 8,
                    'new_status_id' => 48,
                    'assign_to' => null,
                ]);
                $this->logRepository->logCreate($cr->id, $req, $cr, 'shifting');
                $repo->UpateChangeRequestStatus($cr->id, $req);

                // Demote other CRs from 10 → 8
                Change_request::where('developer_id', $cr->developer_id)
                    ->where('id', '!=', $cr->id)
                    ->whereHas('RequestStatuses', function ($query) {
                        $query->where('new_status_id', 10);
                    })
                    ->each(function ($otherCr) {
                        $lastTwoStatuses = $otherCr->AllRequestStatuses()
                            ->orderBy('id', 'desc')
                            ->take(2)
                            ->get();

                        if ($lastTwoStatuses->count() == 2) {
                            $latest = $lastTwoStatuses[0]; // newest
                            $previous = $lastTwoStatuses[1]; // before newest

                            // Make latest inactive
                            $latest->timestamps = false;
                            $latest->update(['active' => '0']);

                            // Insert copy of previous with active=1
                            $otherCr->RequestStatuses()->create([
                                'old_status_id' => $previous->old_status_id,
                                'new_status_id' => $previous->new_status_id,
                                'assign_to' => $previous->assign_to,
                                'active' => '1',
                                'user_id' => $previous->user_id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            // Log status change
                            $logRequest = new \Illuminate\Http\Request([
                                'old_status_id' => $previous->old_status_id,
                                'new_status_id' => 48,
                                'assign_to' => $previous->assign_to,
                            ]);
                            $this->logRepository->logCreate($otherCr->id, $logRequest, $otherCr, 'shifting');
                        }
                    });
            }
        }

        // Calculate end time for this CR
        $endDevelopTime = Carbon::parse(
            $this->generateEndDate(
                $startDevelopTime->timestamp,
                $cr->develop_duration,
                false,
                $cr->developer_id,
                'dev'
            )
        );

        $cr->update([
            'start_develop_time' => $startDevelopTime->format('Y-m-d H:i:s'),
            'end_develop_time' => $endDevelopTime->format('Y-m-d H:i:s'),
        ]);

        // === REORDER QUEUE ===
        $queue = Change_request::where('developer_id', $cr->developer_id)
            ->where('id', '!=', $cr->id)
            ->whereHas('RequestStatuses', function ($query) {
                $query->where('new_status_id', 8);
            })
            ->orderBy('start_develop_time')
            ->get();

        if ($queue->count() > 0) {
            $currentEnd = $endDevelopTime;

            foreach ($queue as $queuedCr) {
                if (! empty($queuedCr->develop_duration) && $queuedCr->develop_duration > 0) {
                    $startDevelopTime = Carbon::parse($currentEnd)->addHour();

                    $endDevelopTime = Carbon::parse(
                        $this->generateEndDate(
                            $startDevelopTime->timestamp,
                            $queuedCr->develop_duration,
                            false,
                            $queuedCr->developer_id,
                            'dev'
                        )
                    );

                    $queuedCr->update([
                        'start_develop_time' => $startDevelopTime->format('Y-m-d H:i:s'),
                        'end_develop_time' => $endDevelopTime->format('Y-m-d H:i:s'),
                    ]);

                    $currentEnd = $endDevelopTime;
                }
            }
        }
    }

    protected function reorderAllTesterQueues(): void
    {
        $testerIds = Change_request::whereNotNull('tester_id')
            ->distinct()
            ->pluck('tester_id');

        foreach ($testerIds as $testerId) {
            $this->reorderSingleTesterQueue($testerId);
        }
    }

    /**
     * Reorder a single tester's queue sequentially
     */
    /**
     * Reorder a single tester's queue sequentially:
     * - Each CR starts testing only after its develop phase is finished.
     * - No overlap: tester handles one CR at a time.
     */
    /**
     * Reorder a tester's queue:
     * - Test starts strictly after develop finishes (+1h buffer).
     * - No overlaps: tester works sequentially.
     * - Uses setToWorkingDate() for valid working start times.
     */
    protected function reorderSingleTesterQueue(int $testerId): void
    {
        $crList = Change_request::where('tester_id', $testerId)
            ->whereNotNull('test_duration')
            ->where('test_duration', '>', 0)
            ->orderBy('end_develop_time', 'asc') // process in order of dev completion
            ->get();

        $testerAvailableAt = null; // when tester is next free

        foreach ($crList as $cr) {
            if (empty($cr->end_develop_time)) {
                continue; // skip if dev not finished yet
            }

            $devEnd = Carbon::parse($cr->end_develop_time);

            // Base earliest time = dev end + 1 hour
            $earliestPossible = $devEnd->copy()->addHour();

            // If tester is still busy, shift start after last test
            $proposedStart = $testerAvailableAt && $testerAvailableAt->gt($earliestPossible)
                ? $testerAvailableAt->copy()
                : $earliestPossible->copy();

            // Adjust to working hours
            $adjustedStart = Carbon::createFromTimestamp(
                $this->setToWorkingDate($proposedStart->timestamp)
            );

            // Compute test end with working-hours aware function
            $actualEnd = Carbon::parse(
                $this->generateEndDate(
                    $adjustedStart->timestamp,
                    $cr->test_duration,
                    false,
                    $testerId,
                    'test'
                )
            );

            // Save corrected times
            $cr->update([
                'start_test_time' => $adjustedStart->format('Y-m-d H:i:s'),
                'end_test_time' => $actualEnd->format('Y-m-d H:i:s'),
            ]);

            // Tester will be free only after this test ends
            $testerAvailableAt = $actualEnd;
        }
    }

    protected function processDesignPhase($cr): void
    {
        if (! $cr || $cr->design_duration <= 0) {
            throw new Exception('Invalid CR or no design duration.');
        }

        // Find if there is already an active CR (status 15) for this designer
        $activeCr = Change_request::where('designer_id', $cr->designer_id)
            ->where('id', '!=', $cr->id)
            ->whereHas('RequestStatuses', function ($query) {
                $query->where('new_status_id', 15);
            })
            ->orderBy('end_design_time', 'desc')
            ->first();

        // Check if design already in progress for this CR
        $Cr_design_in_progress = Change_request::where('designer_id', $cr->designer_id)
            ->where('id', '=', $cr->id)
            ->whereHas('RequestStatuses', function ($query) {
                $query->where('new_status_id', 15);
            })
            ->first();

        if ($Cr_design_in_progress) {
            throw new Exception("Design phase already in progress for CR ID {$cr->cr_no}.");
        }

        $hasPriority = request()->has('priority');

        if ($activeCr && ! $hasPriority) {
            // Normal CR → start after current active CR finishes (+1 hour)
            $startDesignTime = Carbon::parse($activeCr->end_design_time)->addHour();
        } else {
            // Has priority OR no active CR → start now
            $startDesignTime = Carbon::createFromTimestamp(
                $this->setToWorkingDate(Carbon::now()->addHours(3)->timestamp)
            );

            // Handle status shifting for priority CR
            if ($hasPriority) {
                $repo = new \App\Http\Repository\ChangeRequest\ChangeRequestRepository();
                $req = new \Illuminate\Http\Request([
                    'old_status_id' => 7,
                    'new_status_id' => 46,
                    'assign_to' => null,
                ]);
                $this->logRepository->logCreate($cr->id, $req, $cr, 'shifting');
                $repo->UpateChangeRequestStatus($cr->id, $req);

                // Demote other CRs from 15 → 7
                Change_request::where('designer_id', $cr->designer_id)
                    ->where('id', '!=', $cr->id)
                    ->whereHas('RequestStatuses', function ($query) {
                        $query->where('new_status_id', 15);
                    })
                    ->each(function ($otherCr) {
                        $lastTwoStatuses = $otherCr->AllRequestStatuses()
                            ->orderBy('id', 'desc')
                            ->take(2)
                            ->get();

                        if ($lastTwoStatuses->count() == 2) {
                            $latest = $lastTwoStatuses[0]; // newest
                            $previous = $lastTwoStatuses[1]; // before newest

                            // Make latest inactive
                            $latest->timestamps = false;
                            $latest->update(['active' => '0']);

                            // Insert copy of previous with active=1
                            $otherCr->RequestStatuses()->create([
                                'old_status_id' => $previous->old_status_id,
                                'new_status_id' => $previous->new_status_id,
                                'assign_to' => $previous->assign_to,
                                'active' => '1',
                                'user_id' => $previous->user_id,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            // Log status change
                            $logRequest = new \Illuminate\Http\Request([
                                'old_status_id' => $previous->old_status_id,
                                'new_status_id' => 46,
                                'assign_to' => $previous->assign_to,
                            ]);
                            $this->logRepository->logCreate($otherCr->id, $logRequest, $otherCr, 'shifting');
                        }
                    });
            }
        }

        // Calculate end time for this CR
        $endDesignTime = Carbon::parse(
            $this->generateEndDate(
                $startDesignTime->timestamp,
                $cr->design_duration,
                false,
                $cr->designer_id,
                'design'
            )
        );

        $cr->update([
            'start_design_time' => $startDesignTime->format('Y-m-d H:i:s'),
            'end_design_time' => $endDesignTime->format('Y-m-d H:i:s'),
        ]);

        // === REORDER QUEUE ===
        $queue = Change_request::where('designer_id', $cr->designer_id)
            ->where('id', '!=', $cr->id)
            ->whereHas('RequestStatuses', function ($query) {
                $query->where('new_status_id', 7);
            })
            ->orderBy('start_design_time')
            ->get();

        if ($queue->count() > 0) {
            // Put new CR at top, shift others down
            $currentEnd = $endDesignTime;

            foreach ($queue as $queuedCr) {
                if (! empty($queuedCr->design_duration) && $queuedCr->design_duration > 0) {
                    $startDesignTime = Carbon::parse($currentEnd)->addHour();

                    $endDesignTime = Carbon::parse(
                        $this->generateEndDate(
                            $startDesignTime->timestamp,
                            $queuedCr->design_duration,
                            false,
                            $queuedCr->designer_id,
                            'design'
                        )
                    );

                    $queuedCr->update([
                        'start_design_time' => $startDesignTime->format('Y-m-d H:i:s'),
                        'end_design_time' => $endDesignTime->format('Y-m-d H:i:s'),
                    ]);

                    $currentEnd = $endDesignTime;
                }
            }
        }
    }

    // protected function processDesignPhase($cr): void
    // {
    //     if ($cr->design_duration <= 0) {
    //         return;
    //     }

    //     if (!$cr) {
    //         return;
    //         // return [
    //         //     'status' => 'error',
    //         //     'message' => "Change Request with ID {$crId} not found."
    //         // ];
    //     }

    //         if (!request()->has('priority')) {

    //             $q = Change_request::with('RequestStatuses')
    //             ->where('designer_id', $cr->designer_id)
    //             ->where('id', $crId)

    //             ->whereHas('RequestStatuses', function ($query) {
    //                 $query->where('new_status_id', 15); // Only those with status = 15
    //             })
    //             ->first();

    //            // dd($q);

    // if($q){
    //    return ;

    //     // return [
    //     //     'status' => 'error',
    //     //     'message' => "Change Request with ID  is aleady working on it."
    //     // ];
    // }
    //         } else {

    //                 $cr->RequestStatuses()->update(['new_status_id' => 15]);

    //         }

    //         // Fetch all CRs of the same designer, ordered by end time
    //         $designerCrs = Change_request::with('RequestStatuses')
    //             ->where('designer_id', $cr->designer_id)
    //             ->where('id', '!=', $cr->id)
    //             ->get();
    //             $startDesignTime = Carbon::createFromTimestamp($this->setToWorkingDate(Carbon::now()->timestamp));
    //             if (!request()->has('priority')) {
    //             // Find the latest CR with status 15
    //         $crWithStatus15 = $designerCrs
    //             ->filter(function ($item) {
    //                 return $item->RequestStatuses->contains(function ($status) {
    //                     return $status->new_status_id == 15;
    //                 });
    //             })
    //             ->sortByDesc('end_design_time')
    //             ->first();

    //         // Default start time is now

    //         if ($crWithStatus15) {
    //             $startDesignTime = Carbon::parse($crWithStatus15->end_design_time);
    //         }
    //     }
    //         // Calculate end time for current CR
    //         $endDesignTime = Carbon::parse(
    //             $this->generateEndDate(
    //                 $startDesignTime->timestamp,
    //                 $cr->design_duration,
    //                 false,
    //                 $cr->designer_id,
    //                 'design'
    //             )
    //         );

    //         // Update current CR
    //         $cr->update([
    //             'start_design_time' => $startDesignTime->format('Y-m-d H:i:s'),
    //             'end_design_time'   => $endDesignTime->format('Y-m-d H:i:s'),
    //         ]);
    //         if (!request()->has('priority')) {
    //         // Now reorder remaining CRs (excluding the current one)
    //         $queue = Change_request::where('designer_id', $cr->designer_id)
    //         ->where('id', '!=', $cr->id)
    //         // ->whereDoesntHave('RequestStatuses', function ($query) {
    //         //     $query->where('new_status_id', 15); // Exclude CRs with status = 15
    //         // })
    //         ->whereHas('RequestStatuses', function ($query) {
    //             $query->where('new_status_id', 7); // Exclude CRs with status = 15
    //         })
    //         ->orderBy('id')
    //         ->get();
    //         } else{
    //             $queue = Change_request::where('designer_id', $cr->designer_id)
    //             ->where('id', '!=', $cr->id)
    //             ->whereHas('RequestStatuses', function ($query) {
    //                 $query->whereIn('new_status_id', [15, 7]);
    //             })
    //             ->orderBy('id')
    //             ->get();

    //         }

    //         foreach ($queue as $queuedCr) {
    //             if (!empty($queuedCr->design_duration) && $queuedCr->design_duration > 0) {
    //                 if (request()->has('priority')) {
    //                 $queuedCr->RequestStatuses()->where('new_status_id', 15)->update(['new_status_id' => 7]);
    //                 }
    //                 $startDesignTime = Carbon::parse($endDesignTime);

    //                 $endDesignTime = Carbon::parse(
    //                     $this->generateEndDate(
    //                         $startDesignTime->timestamp,
    //                         $queuedCr->design_duration,
    //                         false,
    //                         $queuedCr->designer_id,
    //                         'design'
    //                     )
    //                 );

    //                 $queuedCr->update([
    //                     'start_design_time' => $startDesignTime->format('Y-m-d H:i:s'),
    //                     'end_design_time'   => $endDesignTime->format('Y-m-d H:i:s'),
    //                 ]);
    //             }
    //         }

    //     // $currentTime = Carbon::now()->timestamp;

    //     // if (isset($cr->start_design_time) && Carbon::parse($cr->start_design_time)->isFuture()) {
    //     //     $startDesignTime = Carbon::createFromTimestamp($this->setToWorkingDate($currentTime));
    //     //     $endDesignTime = Carbon::parse(
    //     //         $this->generateEndDate(
    //     //             $startDesignTime->timestamp,
    //     //             $cr->design_duration,
    //     //             false,
    //     //             $cr->designer_id,
    //     //             'design'
    //     //         )
    //     //     );

    //     //     $conflictingCR = $this->isDesignerBusy(
    //     //         $cr->designer_id,
    //     //         $startDesignTime,
    //     //         $cr->design_duration,
    //     //         $endDesignTime,
    //     //         $cr->id
    //     //     );

    //     //     if ($conflictingCR) {
    //     //         $startDesignTime = $this->resolveDesignConflict($conflictingCR, $cr);
    //     //         $endDesignTime = Carbon::parse(
    //     //             $this->generateEndDate(
    //     //                 $startDesignTime->timestamp,
    //     //                 $cr->design_duration,
    //     //                 false,
    //     //                 $cr->designer_id,
    //     //                 'design'
    //     //             )
    //     //         );
    //     //     }

    //     //     $cr->update([
    //     //         'start_design_time' => $startDesignTime->format('Y-m-d H:i:s'),
    //     //         'end_design_time' => $endDesignTime->format('Y-m-d H:i:s'),
    //     //     ]);
    //     // }

    // }

    protected function processDevelopmentPhase($cr): void
    {
        if ($cr->develop_duration <= 0) {
            return;
        }

        if (isset($cr->start_develop_time)) {
            $startDevelopTime = Carbon::createFromTimestamp(
                Carbon::parse($cr->end_design_time)->timestamp
            );

            $endDevelopTime = Carbon::parse(
                $this->generateEndDate(
                    $startDevelopTime->timestamp,
                    $cr->develop_duration,
                    false,
                    $cr->developer_id,
                    'dev'
                )
            );

            $conflictingCR = $this->isDeveloperBusy(
                $cr->developer_id,
                $startDevelopTime,
                $cr->develop_duration,
                $endDevelopTime,
                $cr->id
            );

            if ($conflictingCR) {
                $startDevelopTime = $this->resolveDevelopmentConflict($conflictingCR, $cr);
                $endDevelopTime = Carbon::parse(
                    $this->generateEndDate(
                        $startDevelopTime->timestamp,
                        $cr->develop_duration,
                        false,
                        $cr->developer_id,
                        'dev'
                    )
                );
            }

            $cr->update([
                'start_develop_time' => $startDevelopTime->format('Y-m-d H:i:s'),
                'end_develop_time' => $endDevelopTime->format('Y-m-d H:i:s'),
            ]);
        }
    }

    // protected function processTestingPhase($cr): void
    // {
    //     if (!$cr || $cr->test_duration <= 0) {
    //         return;
    //     }

    //     // Start time = after development ends, aligned to working hours
    //     $startTestTime = Carbon::createFromTimestamp(
    //         $this->setToWorkingDate(Carbon::parse($cr->end_develop_time)->timestamp)
    //     );

    //     // If start time is already past → push it forward
    //     if ($startTestTime->isPast()) {
    //         $startTestTime = Carbon::createFromTimestamp(
    //             $this->setToWorkingDate(Carbon::now()->addHour()->timestamp)
    //         );
    //     }

    //     // End time
    //     $endTestTime = Carbon::parse(
    //         $this->generateEndDate(
    //             $startTestTime->timestamp,
    //             $cr->test_duration,
    //             false,
    //             $cr->tester_id,
    //             'test'
    //         )
    //     );

    //     // === STEP 1: Place current CR FIRST (always anchor) ===
    //     $cr->update([
    //         'start_test_time' => $startTestTime->format('Y-m-d H:i:s'),
    //         'end_test_time'   => $endTestTime->format('Y-m-d H:i:s'),
    //     ]);

    //     // === STEP 2: Get ALL other CRs for this tester (both scheduled and unscheduled) ===
    //     $allOtherCRs = Change_request::where('tester_id', $cr->tester_id)
    //         ->where('id', '!=', $cr->id)
    //         ->orderBy('end_develop_time', 'asc') // Order by when dev finishes
    //         ->orderBy('id', 'asc') // Then by ID for same dev end time
    //         ->get();

    //     // === STEP 3: Reorder ALL CRs after the current one ===
    //     $currentEnd = $endTestTime;

    //     foreach ($allOtherCRs as $nextCR) {
    //         // Check if this CR's dev has finished
    //         $devEndTime = Carbon::parse($nextCR->end_develop_time);

    //         // Next CR starts at the LATER of: current queue end OR its own dev end time
    //         $nextStartCandidate = Carbon::createFromTimestamp(
    //             $this->setToWorkingDate(
    //                 max($currentEnd->timestamp, $devEndTime->timestamp)
    //             )
    //         );

    //         // If the start time is in the past, push it to now
    //         if ($nextStartCandidate->isPast()) {
    //             $nextStartCandidate = Carbon::createFromTimestamp(
    //                 $this->setToWorkingDate(Carbon::now()->addHour()->timestamp)
    //             );
    //         }

    //         $nextEnd = Carbon::parse(
    //             $this->generateEndDate(
    //                 $nextStartCandidate->timestamp,
    //                 $nextCR->test_duration,
    //                 false,
    //                 $nextCR->tester_id,
    //                 'test'
    //             )
    //         );

    //         $nextCR->update([
    //             'start_test_time' => $nextStartCandidate->format('Y-m-d H:i:s'),
    //             'end_test_time'   => $nextEnd->format('Y-m-d H:i:s'),
    //         ]);

    //         $currentEnd = $nextEnd; // Move queue forward
    //     }
    // }

    protected function processTestingPhase($cr): void
    {
        if (! $cr || $cr->test_duration <= 0) {
            return;
        }

        $isPriority = request()->has('priority') && request('priority');

        // developer end timestamp (base candidate)
        $devEndTimestamp = Carbon::parse($cr->end_develop_time)->timestamp;

        if (! $isPriority) {
            // check for any CR that is actually running right now for this tester
            $nowStr = Carbon::now()->toDateTimeString();

            $currentWorkingCR = Change_request::where('tester_id', $cr->tester_id)
                ->where('id', '!=', $cr->id)
                ->whereNotNull('start_test_time')
                ->whereNotNull('end_test_time')
                ->where('start_test_time', '<=', $nowStr)
                ->where('end_test_time', '>', $nowStr)
                ->orderBy('end_test_time', 'desc') // pick the one that finishes last right now
                ->first();

            if ($currentWorkingCR) {
                $runningEndTs = Carbon::parse($currentWorkingCR->end_test_time)->timestamp;
                // start after the running CR AND after our dev end
                $startTimestamp = max($runningEndTs, $devEndTimestamp) + 3600; // add 1 hour

            } else {
                // no one is working right now -> start as soon as possible (aligned to working hours)
                $startTimestamp = Carbon::now()->addHour()->timestamp;

            }

            $startTestTime = Carbon::createFromTimestamp(
                $this->setToWorkingDate($startTimestamp)
            );
        } else {
            // non-priority: start after dev end, aligned to working hours; if that is in the past push to next working hour
            $startTestTime = Carbon::createFromTimestamp(
                $this->setToWorkingDate($devEndTimestamp)
            );

            if ($startTestTime->isPast()) {
                $startTestTime = Carbon::createFromTimestamp(
                    $this->setToWorkingDate(Carbon::now()->addHour()->timestamp)
                );
            }
        }

        // compute end time
        $endTestTime = Carbon::parse(
            $this->generateEndDate(
                $startTestTime->timestamp,
                $cr->test_duration,
                false,
                $cr->tester_id,
                'test'
            )
        );

        // anchor current CR
        $cr->update([
            'start_test_time' => $startTestTime->format('Y-m-d H:i:s'),
            'end_test_time' => $endTestTime->format('Y-m-d H:i:s'),
        ]);

        // Reorder only future/unscheduled CRs (do NOT touch CRs that are currently running)
        $allOtherCRs = Change_request::where('tester_id', $cr->tester_id)
            ->where('id', '!=', $cr->id)
            ->where(function ($q) {
                $q->whereNull('start_test_time') // unscheduled
                    ->orWhere('start_test_time', '>', Carbon::now()->toDateTimeString()); // scheduled for the future
            })
            ->orderBy('end_develop_time', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $currentEnd = $endTestTime;

        foreach ($allOtherCRs as $nextCR) {
            $devEndTime = Carbon::parse($nextCR->end_develop_time);

            // Next CR starts at the LATER of: current queue end OR its own dev end time
            $nextStartCandidate = Carbon::createFromTimestamp(
                $this->setToWorkingDate(
                    max($currentEnd->timestamp, $devEndTime->timestamp)
                )
            );

            // If start candidate somehow ended up in the past -> push to next working hour
            if ($nextStartCandidate->isPast()) {
                $nextStartCandidate = Carbon::createFromTimestamp(
                    $this->setToWorkingDate(Carbon::now()->addHour()->timestamp)
                );
            }

            $nextEnd = Carbon::parse(
                $this->generateEndDate(
                    $nextStartCandidate->timestamp,
                    $nextCR->test_duration,
                    false,
                    $nextCR->tester_id,
                    'test'
                )
            );

            $nextCR->update([
                'start_test_time' => $nextStartCandidate->format('Y-m-d H:i:s'),
                'end_test_time' => $nextEnd->format('Y-m-d H:i:s'),
            ]);

            $currentEnd = $nextEnd;
        }
    }

    protected function reorderQueuedRequests($cr, $crId): void
    {
        $queue = Change_request::where(function ($query) use ($cr) {
            $query->Where('developer_id', $cr->developer_id)
                ->orWhere('tester_id', $cr->tester_id);
        })
            ->where('id', '!=', $crId)
            ->orderBy('id')
            ->get();

        foreach ($queue as $queuedCr) {
            $this->adjustQueuedRequest($queuedCr, $cr);
        }
    }

    protected function adjustQueuedRequest($queuedCr, $cr): void
    {
        // // Design Phase
        // if ($queuedCr->designer_id == $cr->designer_id &&
        //     !empty($queuedCr->design_duration) &&
        //     $queuedCr->design_duration > 0) {

        //     $startDesignTime = Carbon::parse($cr->end_design_time);
        //     $endDesignTime = Carbon::parse(
        //         $this->generateEndDate(
        //             $startDesignTime->timestamp,
        //             $queuedCr->design_duration,
        //             false,
        //             $queuedCr->designer_id,
        //             'design'
        //         )
        //     );

        //     $queuedCr->update([
        //         'start_design_time' => $startDesignTime->format('Y-m-d H:i:s'),
        //         'end_design_time' => $endDesignTime->format('Y-m-d H:i:s'),
        //     ]);
        // }

        // Development Phase
        if ($queuedCr->developer_id == $cr->developer_id &&
            ! empty($queuedCr->develop_duration) &&
            $queuedCr->develop_duration > 0) {

            $startDevelopTime = Carbon::parse($cr->end_develop_time);
            $endDevelopTime = Carbon::parse(
                $this->generateEndDate(
                    $startDevelopTime->timestamp,
                    $queuedCr->develop_duration,
                    false,
                    $queuedCr->developer_id,
                    'dev'
                )
            );

            if ($this->isDeveloperUnavailable($queuedCr->developer_id, $startDevelopTime, $endDevelopTime, $queuedCr->id)) {
                $startDevelopTime = $this->getFirstAvailableTime($queuedCr->developer_id, $startDevelopTime, $endDevelopTime, $queuedCr->id);
                $endDevelopTime = Carbon::parse(
                    $this->generateEndDate(
                        $startDevelopTime->timestamp,
                        $queuedCr->develop_duration,
                        false,
                        $queuedCr->developer_id,
                        'dev'
                    )
                );
            }

            $queuedCr->update([
                'start_develop_time' => $startDevelopTime->format('Y-m-d H:i:s'),
                'end_develop_time' => $endDevelopTime->format('Y-m-d H:i:s'),
            ]);
        }

        // Test Phase
        if ($queuedCr->tester_id == $cr->tester_id &&
            ! empty($queuedCr->test_duration) &&
            $queuedCr->test_duration > 0) {

            $startTestTime = Carbon::parse($cr->end_test_time ?? $cr->end_develop_time);
            $endTestTime = Carbon::parse(
                $this->generateEndDate(
                    $startTestTime->timestamp,
                    $queuedCr->test_duration,
                    false,
                    $queuedCr->tester_id,
                    'test'
                )
            );

            if ($this->isTesterUnavailable($queuedCr->tester_id, $startTestTime, $endTestTime, $queuedCr->id)) {
                $startTestTime = $this->getFirstAvailableTimeForTest($queuedCr->tester_id, $startTestTime, $endTestTime, $queuedCr->id);
                $endTestTime = Carbon::parse(
                    $this->generateEndDate(
                        $startTestTime->timestamp,
                        $queuedCr->test_duration,
                        false,
                        $queuedCr->tester_id,
                        'test'
                    )
                );
            }

            $queuedCr->update([
                'start_test_time' => $startTestTime->format('Y-m-d H:i:s'),
                'end_test_time' => $endTestTime->format('Y-m-d H:i:s'),
            ]);
        }
    }

    // Conflict resolution methods
    protected function resolveDesignConflict($conflictingCR, $cr)
    {
        $date1 = $conflictingCR->end_design_time;
        $date2 = Carbon::now();
        $date3 = $conflictingCR->start_design_time;
        $date4 = $cr->start_design_time;

        $date2 = Carbon::createFromTimestamp($this->setToWorkingDate($date2->timestamp));
        $date3 = Carbon::createFromTimestamp(Carbon::parse($date3)->timestamp);

        if ($date2->greaterThan($date3)) {
            return Carbon::createFromTimestamp(Carbon::parse($date1)->timestamp);
        }

        return Carbon::createFromTimestamp($this->setToWorkingDate($date2->timestamp));

    }

    protected function resolveDevelopmentConflict($conflictingCR, $cr)
    {
        $date1 = $conflictingCR->start_develop_time;
        $date2 = $cr->end_design_time;

        if (Carbon::parse($date1)->greaterThan(Carbon::parse($date2))) {
            return Carbon::createFromTimestamp($this->setToWorkingDate(Carbon::parse($date1)->timestamp));
        }

        return Carbon::createFromTimestamp($this->setToWorkingDate(Carbon::parse($date2)->timestamp));

    }

    protected function resolveTestingConflict($conflictingCR, $cr)
    {
        $date1 = $conflictingCR->start_test_time;
        $date2 = $cr->end_develop_time;

        if (Carbon::parse($date1)->greaterThan(Carbon::parse($date2))) {
            return Carbon::createFromTimestamp($this->setToWorkingDate(Carbon::parse($date1)->timestamp));
        }

        return Carbon::createFromTimestamp($this->setToWorkingDate(Carbon::parse($date2)->timestamp));

    }

    protected function adjustTimesForChangeRequest($id, $changeRequest): void
    {

        $crId = Change_request::where('cr_no', $id)->first()->id;
        $cr = Change_request::find($crId);
        $cr_found_design = Change_request::with('RequestStatuses')
            ->where('designer_id', $cr->designer_id)
            ->where('id', $crId)
            ->whereHas('RequestStatuses', function ($query) {
                $query->whereIn('new_status_id', [15, 7]);
            })
            ->first();

        if ($cr_found_design) {
            $this->processDesignPhase($changeRequest);
        }
        // Implementation for adjusting times for a specific change request
        // This would include the complex logic from the original method
        if (! $cr_found_design) {
            $this->processDevelopmentPhase($changeRequest);
            $this->processTestingPhase($changeRequest);
        }

    }

    protected function shiftQueue($userId, $roleColumn, $targetCrId): void
    {
        // Implementation for shifting the queue for a specific role
        $affectedRequests = Change_request::where($roleColumn, $userId)
            ->where('id', '!=', $targetCrId)
            ->orderBy('created_at')
            ->get();

        foreach ($affectedRequests as $request) {
            $this->adjustTimesForChangeRequest($request->id, $request);
        }
    }

    /**
     * Demote a CR from testing status back to queued
     */
    private function demoteCrFromTesting($otherCr): void
    {
        try {
            $lastTwoStatuses = $otherCr->AllRequestStatuses()
                ->orderBy('id', 'desc')
                ->take(2)
                ->get();

            if ($lastTwoStatuses->count() == 2) {
                $latest = $lastTwoStatuses[0]; // newest
                $previous = $lastTwoStatuses[1]; // before newest

                // Make latest inactive
                $latest->timestamps = false;
                $latest->update(['active' => '0']);

                // Insert a copy of previous status with active=1
                $newStatusRecord = $otherCr->RequestStatuses()->create([
                    'old_status_id' => $previous->old_status_id,
                    'new_status_id' => $previous->new_status_id,
                    'assign_to' => $previous->assign_to,
                    'active' => '1',
                    'user_id' => $previous->user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Create a proper request object for logging
                $logRequest = new \Illuminate\Http\Request([
                    'old_status_id' => $previous->old_status_id,
                    'new_status_id' => 139,
                    'assign_to' => $previous->assign_to,
                ]);

                // Log the status change
                $this->logRepository->logCreate($otherCr->id, $logRequest, $otherCr, 'shifting');
            }
        } catch (Exception $e) {
            Log::error('Error demoting CR from testing', [
                'cr_id' => $otherCr->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw here to avoid breaking the main process
        }
    }

    /**
     * Reorder queued CRs after current CR
     */
    private function reorderQueuedCrs($cr, $endTestTime): void
    {
        $queue = Change_request::where('tester_id', $cr->tester_id)
            ->where('id', '!=', $cr->id)
            ->whereHas('RequestStatuses', function ($query) {
                $query->where('new_status_id', 11); // queued test
            })
            ->orderBy('id')
            ->get();

        $currentEndTime = $endTestTime;

        foreach ($queue as $queuedCr) {
            if (! empty($queuedCr->test_duration) && $queuedCr->test_duration > 0) {
                // Start after the last CR in the tester's queue
                $startTestTime = Carbon::parse($currentEndTime);
                $newEndTestTime = Carbon::parse(
                    $this->generateEndDate(
                        $startTestTime->timestamp,
                        $queuedCr->test_duration,
                        false,
                        $queuedCr->tester_id,
                        'test'
                    )
                );

                $queuedCr->update([
                    'start_test_time' => $startTestTime->format('Y-m-d H:i:s'),
                    'end_test_time' => $newEndTestTime->format('Y-m-d H:i:s'),
                ]);

                // Update for next iteration
                $currentEndTime = $newEndTestTime;
            }
        }
    }

    // Availability checking methods
    private function isDeveloperUnavailable($developerId, $startTime, $endTime, $excludeCrId): bool
    {
        return Change_request::where('developer_id', $developerId)
            ->where('id', '!=', $excludeCrId)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_develop_time', [$startTime, $endTime])
                    ->orWhereBetween('end_develop_time', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_develop_time', '<', $startTime)
                            ->where('end_develop_time', '>', $endTime);
                    });
            })
            ->exists();
    }

    private function isTesterUnavailable($testerId, $startTime, $endTime, $excludeCrId): bool
    {
        return Change_request::where('tester_id', $testerId)
            ->where('id', '!=', $excludeCrId)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_test_time', [$startTime, $endTime])
                    ->orWhereBetween('end_test_time', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_test_time', '<', $startTime)
                            ->where('end_test_time', '>', $endTime);
                    });
            })
            ->exists();
    }

    private function getFirstAvailableTime($developerId, $startTime, $endTime, $excludeCrId)
    {
        $unavailablePeriods = Change_request::where('developer_id', $developerId)
            ->where('id', '!=', $excludeCrId)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_develop_time', [$startTime, $endTime])
                    ->orWhereBetween('end_develop_time', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_develop_time', '<', $startTime)
                            ->where('end_develop_time', '>', $endTime);
                    });
            })
            ->orderBy('end_develop_time', 'asc')
            ->get(['start_develop_time', 'end_develop_time']);

        $availabilityStart = Carbon::parse($startTime);
        foreach ($unavailablePeriods as $period) {
            $periodEnd = Carbon::parse($period->end_develop_time);
            if ($periodEnd->greaterThan($availabilityStart)) {
                $availabilityStart = $periodEnd;
            }
        }

        return $availabilityStart;
    }

    private function getFirstAvailableTimeForTest($testerId, $startTime, $endTime, $excludeCrId)
    {
        $unavailablePeriods = Change_request::where('tester_id', $testerId)
            ->where('id', '!=', $excludeCrId)
            ->where(function ($query) use ($startTime, $endTime) {
                $query->whereBetween('start_test_time', [$startTime, $endTime])
                    ->orWhereBetween('end_test_time', [$startTime, $endTime])
                    ->orWhere(function ($q) use ($startTime, $endTime) {
                        $q->where('start_test_time', '<', $startTime)
                            ->where('end_test_time', '>', $endTime);
                    });
            })
            ->orderBy('end_test_time', 'asc')
            ->get(['start_test_time', 'end_test_time']);

        $availabilityStart = Carbon::parse($startTime);
        foreach ($unavailablePeriods as $period) {
            $periodEnd = Carbon::parse($period->end_test_time);
            if ($periodEnd->greaterThan($availabilityStart)) {
                $availabilityStart = $periodEnd;
            }
        }

        return $availabilityStart;
    }
}
