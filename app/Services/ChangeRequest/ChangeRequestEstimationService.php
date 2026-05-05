<?php

namespace App\Services\ChangeRequest;

use App\Models\Change_request;
use App\Models\Group;
use App\Models\User;
use App\Traits\ChangeRequest\ChangeRequestConstants;
use Carbon\Carbon;

class ChangeRequestEstimationService
{
    use ChangeRequestConstants;

    /**
     * Calculate all estimations based on request data
     *
     * @param  int  $id
     * @param  Change_request  $changeRequest
     * @param  mixed  $request
     * @param  User  $user
     */
    public function calculateEstimation($id, $changeRequest, $request, $user): array
    {
        $returnData = [];

        // Handle development estimation
        if (isset($request['dev_estimation'])) {
            $returnData = array_merge($returnData, $this->calculateDevelopmentEstimation(
                $id,
                $changeRequest,
                $request,
                $user
            ));
        }

        // Handle testing estimation
        if (isset($request['testing_estimation'])) {
            $returnData = array_merge($returnData, $this->calculateTestingEstimation(
                $id,
                $changeRequest,
                $request,
                $user
            ));
        }

        // Handle design estimation
        if (isset($request['design_estimation'])) {
            $returnData = array_merge($returnData, $this->calculateDesignEstimation(
                $id,
                $changeRequest,
                $request,
                $user
            ));
        }

        // Handle CR estimation
        if (isset($request['CR_duration'])) {
            $returnData = array_merge($returnData, $this->calculateCREstimation(
                $id,
                $changeRequest,
                $request,
                $user
            ));
        }

        return $returnData;
    }

    /**
     * Set date to working hours and days
     *
     * @param  int  $date
     */
    public function setToWorkingDate($date): int
    {
        if ($date instanceof \Carbon\Carbon) {
            $date = $date->timestamp;
        }

        $workingHours = $this->getWorkingHours();
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

    /**
     * Generate end date based on working hours and man power
     *
     * @param  int  $startDate
     * @param  int  $duration
     * @param  bool  $onGoing
     * @param  int  $userId
     * @param  string  $action
     */
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
            if (
                !in_array($dayOfWeek, $weekendDays) &&
                $hour < $workingHours['end'] &&
                $hour >= $workingHours['start']
            ) {
                $i--;
            }
        }

        return date('Y-m-d H:i:s', $time);
    }

    /**
     * Calculate total estimation for a change request
     */
    public function calculateTotalEstimation(array $estimations): array
    {
        $total = [
            'design_hours' => $estimations['design_duration'] ?? 0,
            'development_hours' => $estimations['develop_duration'] ?? 0,
            'testing_hours' => $estimations['test_duration'] ?? 0,
            'cr_hours' => $estimations['CR_duration'] ?? 0,
        ];

        $total['total_hours'] = array_sum($total);
        $total['total_days'] = ceil($total['total_hours'] / $this->getWorkingHours()['hours_per_day']);

        // Calculate total working days (considering weekends)
        $total['total_working_days'] = $this->calculateWorkingDays($total['total_days']);

        return $total;
    }

    /**
     * Get estimation breakdown by phase
     */
    public function getEstimationBreakdown(Change_request $changeRequest): array
    {
        $breakdown = [
            'phases' => [
                'design' => [
                    'duration' => $changeRequest->design_duration ?? 0,
                    'start_time' => $changeRequest->start_design_time,
                    'end_time' => $changeRequest->end_design_time,
                    'assigned_to' => $changeRequest->designer_id,
                    'status' => $this->getPhaseStatus($changeRequest, 'design'),
                ],
                'development' => [
                    'duration' => $changeRequest->develop_duration ?? 0,
                    'start_time' => $changeRequest->start_develop_time,
                    'end_time' => $changeRequest->end_develop_time,
                    'assigned_to' => $changeRequest->developer_id,
                    'status' => $this->getPhaseStatus($changeRequest, 'development'),
                ],
                'testing' => [
                    'duration' => $changeRequest->test_duration ?? 0,
                    'start_time' => $changeRequest->start_test_time,
                    'end_time' => $changeRequest->end_test_time,
                    'assigned_to' => $changeRequest->tester_id,
                    'status' => $this->getPhaseStatus($changeRequest, 'testing'),
                ],
                'cr_implementation' => [
                    'duration' => $changeRequest->CR_duration ?? 0,
                    'start_time' => $changeRequest->start_CR_time,
                    'end_time' => $changeRequest->end_CR_time,
                    'assigned_to' => $changeRequest->chnage_requester_id,
                    'status' => $this->getPhaseStatus($changeRequest, 'cr'),
                ],
            ],
            'totals' => $this->calculateTotalEstimation([
                'design_duration' => $changeRequest->design_duration,
                'develop_duration' => $changeRequest->develop_duration,
                'test_duration' => $changeRequest->test_duration,
                'CR_duration' => $changeRequest->CR_duration,
            ]),
        ];

        return $breakdown;
    }

    /**
     * Validate estimation values
     */
    public function validateEstimations(array $estimations): array
    {
        $errors = [];
        $maxHours = 2000; // Maximum allowed hours per phase

        $phases = ['design_duration', 'develop_duration', 'test_duration', 'CR_duration'];

        foreach ($phases as $phase) {
            if (isset($estimations[$phase])) {
                $value = $estimations[$phase];

                if (!is_numeric($value) || $value < 0) {
                    $errors[$phase] = "The {$phase} must be a positive number.";
                } elseif ($value > $maxHours) {
                    $errors[$phase] = "The {$phase} cannot exceed {$maxHours} hours.";
                }
            }
        }

        // Validate total estimation
        $totalHours = array_sum(array_intersect_key($estimations, array_flip($phases)));
        if ($totalHours > ($maxHours * 2)) {
            $errors['total'] = 'Total estimation cannot exceed ' . ($maxHours * 2) . ' hours.';
        }

        return $errors;
    }

    /**
     * Get resource availability for a time period
     */
    public function getResourceAvailability(int $userId, string $startDate, string $endDate, string $role = 'developer'): array
    {
        $roleColumns = $this->getRoleColumnMappings();
        $roleConfig = $roleColumns[$role . '_id'] ?? $roleColumns['developer_id'];

        $conflicts = Change_request::where($role . '_id', $userId)
            ->where(function ($query) use ($startDate, $endDate, $roleConfig) {
                $query->whereBetween($roleConfig['start_column'], [$startDate, $endDate])
                    ->orWhereBetween($roleConfig['end_column'], [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate, $roleConfig) {
                        $q->where($roleConfig['start_column'], '<=', $startDate)
                            ->where($roleConfig['end_column'], '>=', $endDate);
                    });
            })
            ->get();

        return [
            'available' => $conflicts->isEmpty(),
            'conflicts' => $conflicts,
            'next_available' => $this->getNextAvailableTime($userId, $endDate, $role),
        ];
    }

    /**
     * Calculate development estimation with time slots
     *
     * @param  int  $id
     * @param  Change_request  $changeRequest
     * @param  mixed  $request
     * @param  User  $user
     */
    protected function calculateDevelopmentEstimation($id, $changeRequest, $request, $user): array
    {
        $isTestable = $changeRequest
            ->change_request_custom_fields
            ->where('custom_field_name', 'testable')
            ->pluck('custom_field_value')
            ->first(); // check if cr is testable or not
        $data = [
            'develop_duration' => $request['dev_estimation'],
            'developer_id' => $request['developer_id'] ?? $user->id,
        ];
        if ($isTestable) {
            $dates = $this->getLastCRDate(
                $id,
                $data['developer_id'],
                'developer_id',
                'end_develop_time',
                $request['dev_estimation'],
                'dev'
            );



            // Calculate subsequent phase (testing) if it exists
            if (!empty($changeRequest['test_duration'])) {

                $data['start_develop_time'] = $dates[0] ?? '';
                $data['end_develop_time'] = $dates[1] ?? '';
                $testEndTime = $data['end_develop_time'];
                $testDates = $this->getLastEndDate(
                    $id,
                    $changeRequest['tester_id'],
                    'tester_id',
                    $testEndTime,
                    $changeRequest['test_duration'],
                    'test'
                );

                $data['start_test_time'] = $testDates[0] ?? '';
                $data['end_test_time'] = $testDates[1] ?? '';
            }
        } else {
            $dates = $this->getLastCRDate(
                $id,
                $data['developer_id'],
                'developer_id',
                'end_develop_time',
                $request['dev_estimation'],
                'dev'
            );

            $data['start_develop_time'] = $dates[0] ?? '';
            $data['end_develop_time'] = $dates[1] ?? '';
        }

        return $data;
    }

    /**
     * Calculate testing estimation with time slots
     *
     * @param  int  $id
     * @param  Change_request  $changeRequest
     * @param  mixed  $request
     * @param  User  $user
     */
    protected function calculateTestingEstimation($id, $changeRequest, $request, $user): array
    {
        $data = [
            'test_duration' => $request['testing_estimation'],
            'tester_id' => $request['tester_id'] ?? $user->id,
        ];
        if (!empty($changeRequest['develop_duration'])) {
            // If design phase exists, calculate testing after development

            // $developDates = $this->getLastEndDate(
            //     $id,
            //     $changeRequest['developer_id'],
            //     'developer_id',
            //     $changeRequest['end_design_time'],
            //     $changeRequest['develop_duration'],
            //     'dev'
            // );

            // $data['start_develop_time'] = $developDates[0] ?? '';
            // $data['end_develop_time'] = $developDates[1] ?? '';
            // die("dddtt");
            $dates = $this->getLastCRDate(
                $id,
                $changeRequest['developer_id'],
                'developer_id',
                'end_develop_time',
                $changeRequest['develop_duration'],
                'dev'
            );

            $data['start_develop_time'] = $dates[0] ?? '';
            $data['end_develop_time'] = $dates[1] ?? '';
            $testDates = $this->getLastEndDate(
                $id,
                $data['tester_id'],
                'tester_id',
                $data['end_develop_time'],
                $request['testing_estimation'],
                'test'
            );

            $data['start_test_time'] = $testDates[0] ?? '';
            $data['end_test_time'] = $testDates[1] ?? '';
        }

        return $data;
    }

    /**
     * Calculate design estimation with time slots
     *
     * @param  int  $id
     * @param  Change_request  $changeRequest
     * @param  mixed  $request
     * @param  User  $user
     */
    protected function calculateDesignEstimation($id, $changeRequest, $request, $user): array
    {
        $data = [
            'design_duration' => $request['design_estimation'],
            'designer_id' => $request['designer_id'] ?? $user->id,
        ];

        $dates = $this->getLastCRDate(
            $id,
            $data['designer_id'],
            'designer_id',
            'end_design_time',
            $request['design_estimation'],
            'design'
        );

        $data['start_design_time'] = $dates[0] ?? '';
        $data['end_design_time'] = $dates[1] ?? '';

        // Calculate subsequent phases if they exist
        if (!empty($changeRequest['develop_duration'])) {
            $developDates = $this->getLastEndDate(
                $id,
                $changeRequest['developer_id'],
                'developer_id',
                $data['end_design_time'],
                $changeRequest['develop_duration'],
                'dev'
            );

            $data['start_develop_time'] = $developDates[0] ?? '';
            $data['end_develop_time'] = $developDates[1] ?? '';
        }

        if (!empty($changeRequest['test_duration'])) {
            $testEndTime = $data['end_develop_time'] ?? $data['end_design_time'];
            $testDates = $this->getLastEndDate(
                $id,
                $changeRequest['tester_id'],
                'tester_id',
                $testEndTime,
                $changeRequest['test_duration'],
                'test'
            );

            $data['start_test_time'] = $testDates[0] ?? '';
            $data['end_test_time'] = $testDates[1] ?? '';
        }

        return $data;
    }

    /**
     * Calculate Change Request estimation with time slots
     *
     * @param  int  $id
     * @param  Change_request  $changeRequest
     * @param  mixed  $request
     * @param  User  $user
     */
    protected function calculateCREstimation($id, $changeRequest, $request, $user): array
    {
        $data = [
            'CR_duration' => $request['CR_duration'],
            'chnage_requester_id' => $request['chnage_requester_id'] ?? $user->id,
        ];

        $dates = $this->getLastCRDate(
            $id,
            $data['chnage_requester_id'],
            'chnage_requester_id',
            'end_CR_time',
            $request['CR_duration'],
            'CR'
        );

        $data['start_CR_time'] = $dates[0] ?? '';
        $data['end_CR_time'] = $dates[1] ?? '';

        return $data;
    }

    /**
     * Get the last CR date for scheduling
     *
     * @param  int  $id
     * @param  int  $userId
     * @param  string  $column
     * @param  string  $endDateColumn
     * @param  int  $duration
     * @param  string  $action
     */
    protected function getLastCRDate($id, $userId, $column, $endDateColumn, $duration, $action): array
    {
        $lastEndDate = Change_request::where($column, $userId)
            ->where('id', '!=', $id)
            ->max($endDateColumn);

        if ($lastEndDate == '' || $lastEndDate < date('Y-m-d H:i:s')) {
            $newStartDate = date('Y-m-d H:i:s', strtotime('+3 hours'));
        } else {
            $newStartDate = date('Y-m-d H:i:s', strtotime('+1 hours', strtotime($lastEndDate)));
        }

        $newStartDate = date('Y-m-d H:i:s', $this->setToWorkingDate(strtotime($newStartDate)));
        $now = Carbon::now();

        if (!Carbon::parse($newStartDate)->gt(Carbon::now())) {
            $newStartDate = date('Y-m-d H:i:s');
        }

        $newEndDate = $this->generateEndDate(
            $this->setToWorkingDate(strtotime($newStartDate)),
            $duration,
            0,
            $userId,
            $action
        );

        return [$newStartDate, $newEndDate];
    }

    /**
     * Get the last end date for dependent scheduling
     *
     * @param  int  $id
     * @param  int  $userId
     * @param  string  $column
     * @param  string  $lastEndDate
     * @param  int  $duration
     * @param  string  $action
     */
    protected function getLastEndDate($id, $userId, $column, $lastEndDate, $duration, $action): array
    {
        // Map actions to their respective start/end dependencies
        $actionConfig = [
            'dev' => ['prevField' => 'end_design_time', 'maxField' => 'end_develop_time'],
            'test' => ['prevField' => 'end_develop_time', 'maxField' => 'end_test_time'],
        ];

        // If action not recognized, return lastEndDate as is
        if (!isset($actionConfig[$action])) {
            return [$lastEndDate, $lastEndDate];
        }

        $prevField = $actionConfig[$action]['prevField']; // Dependency from same CR
        $maxField = $actionConfig[$action]['maxField'];  // Max from other CRs for same user

        // 1️⃣ Get dependency end time for the same CR
        $dependencyEnd = Change_request::where('id', $id)->value($prevField);

        // 2️⃣ Get last occupied time for this user in other CRs
        $lastOccupied = Change_request::where($column, $userId)
            ->where('id', '!=', $id)
            ->max($maxField);

        // 3️⃣ Determine latest blocking time
        $latestBlockingTime = max(
            strtotime($dependencyEnd ?? '1970-01-01'),
            strtotime($lastOccupied ?? '1970-01-01'),
            strtotime($lastEndDate ?? '1970-01-01')
        );

        // 4️⃣ New start date = +1 hour after blocking time
        $newStartDate = date('Y-m-d H:i:s', strtotime('+1 hour', $latestBlockingTime));

        // 5️⃣ Align to working date
        $newStartDate = date('Y-m-d H:i:s', $this->setToWorkingDate(strtotime($newStartDate)));

        // 6️⃣ If start is in the past, use now
        if (!Carbon::parse($newStartDate)->gt(Carbon::now())) {
            $newStartDate = Carbon::createFromTimestamp(
                $this->setToWorkingDate(strtotime(Carbon::now()))
            )->format('Y-m-d H:i:s');
        }

        // 7️⃣ Generate end date
        $newEndDate = $this->generateEndDate(
            $this->setToWorkingDate(strtotime($newStartDate)),
            $duration,
            0,
            $userId,
            $action
        );

        return [$newStartDate, $newEndDate];
    }

    /**
     * Calculate working days excluding weekends
     */
    protected function calculateWorkingDays(int $totalDays): int
    {
        $workingHours = $this->getWorkingHours();
        $weekendDays = count($workingHours['weekend_days']);
        $workingDaysPerWeek = 7 - $weekendDays;

        $weeks = floor($totalDays / 7);
        $remainingDays = $totalDays % 7;

        $workingDays = $weeks * $workingDaysPerWeek;

        // Add remaining working days
        for ($i = 0; $i < $remainingDays; $i++) {
            $dayOfWeek = $i % 7;
            if (!in_array($dayOfWeek, $workingHours['weekend_days'])) {
                $workingDays++;
            }
        }

        return $workingDays;
    }

    /**
     * Get the status of a specific phase
     */
    protected function getPhaseStatus(Change_request $changeRequest, string $phase): string
    {
        $now = Carbon::now();

        $startTimeField = match ($phase) {
            'design' => 'start_design_time',
            'development' => 'start_develop_time',
            'testing' => 'start_test_time',
            'cr' => 'start_CR_time',
            default => null,
        };

        $endTimeField = match ($phase) {
            'design' => 'end_design_time',
            'development' => 'end_develop_time',
            'testing' => 'end_test_time',
            'cr' => 'end_CR_time',
            default => null,
        };

        if (!$startTimeField || !$endTimeField) {
            return 'not_scheduled';
        }

        $startTime = $changeRequest->$startTimeField;
        $endTime = $changeRequest->$endTimeField;

        if (!$startTime || !$endTime) {
            return 'not_scheduled';
        }

        $startTime = Carbon::parse($startTime);
        $endTime = Carbon::parse($endTime);

        if ($now->isBefore($startTime)) {
            return 'scheduled';
        } elseif ($now->isBetween($startTime, $endTime)) {
            return 'in_progress';
        } elseif ($now->isAfter($endTime)) {
            return 'completed';
        }

        return 'unknown';
    }

    /**
     * Get next available time for a resource
     */
    protected function getNextAvailableTime(int $userId, string $afterDate, string $role): ?string
    {
        $roleColumns = $this->getRoleColumnMappings();
        $roleConfig = $roleColumns[$role . '_id'] ?? $roleColumns['developer_id'];

        $lastEndTime = Change_request::where($role . '_id', $userId)
            ->where($roleConfig['end_column'], '>=', $afterDate)
            ->max($roleConfig['end_column']);

        if (!$lastEndTime) {
            return $afterDate;
        }

        $nextAvailable = Carbon::parse($lastEndTime)->addHours(1);

        return date('Y-m-d H:i:s', $this->setToWorkingDate($nextAvailable->timestamp));
    }
}
