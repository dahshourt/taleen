<?php

namespace App\Services\ChangeRequest;
use App\Models\Change_request;
use App\Models\Change_request_statuse;
use App\Models\Group;
use App\Models\GroupStatuses;
use App\Models\NewWorkFlow;
use App\Models\TechnicalCr;
use App\Models\User;
use Auth;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChangeRequestSearchService
{
    private const ACTIVE_STATUS = '1';

    private const INACTIVE_STATUS = '0';

    private const COMPLETED_STATUS = '2';

    public static array $ACTIVE_STATUS_ARRAY = [self::ACTIVE_STATUS, 1];

    public static array $INACTIVE_STATUS_ARRAY = [self::INACTIVE_STATUS, 0];

    public static array $COMPLETED_STATUS_ARRAY = [self::COMPLETED_STATUS, 2];

    public function getAll($group = null)
    {
        $group = $this->resolveGroup($group);
        $groupData = Group::find($group);
        // dd(auth()->user(),$groupData->unit_id,$groupData);
        $groupApplications = $groupData->group_applications->pluck('application_id')->toArray();
        $viewStatuses = $this->getViewStatuses($group);

        $changeRequests = Change_request::with('RequestStatuses.status');

        if ($groupApplications) {
            $changeRequests = $changeRequests->whereIn('application_id', $groupApplications);
            /* $changeRequests = $changeRequests->whereHas('change_request_custom_fields', function ($q) use ($groupApplications) {
                $q->whereIn('change_request_custom_fields.custom_field_name', ['application_id', 'sub_application_id'])->whereIn('change_request_custom_fields.custom_field_value', $groupApplications);
            }); */

            $changeRequests = $changeRequests->where(function ($query) use ($groupData) {
                // Case 1: Where unit_id matches in custom fields
                $query->whereHas('change_request_custom_fields', function ($q) use ($groupData) {
                    $q->where('custom_field_name', 'tech_group_id')
                        ->where('custom_field_value', $groupData->id);
                })
                    // Case 2: OR unit_id does NOT exist in custom fields
                    ->orWhereDoesntHave('change_request_custom_fields', function ($q) {
                        $q->where('custom_field_name', 'tech_group_id');
                    });
            });
        }

        $changeRequests = $changeRequests->whereHas('RequestStatuses', function ($query) use ($group, $viewStatuses) {
            $query->active()->where(function ($qq) use ($group) {
                $qq->where('group_id', $group)->orWhereNull('group_id');
            })
                ->whereIn('new_status_id', $viewStatuses)
                ->whereHas('status.group_statuses', function ($query) use ($group) {
                    $query->where('group_id', $group)
                        ->where('type', 2);
                });
        })->orderBy('id', 'DESC')->paginate(20);

        return $changeRequests;
    }

    public function getAllByWorkFlow(int $workflow_type_id, $group = null)
    {
        if (is_array($group)) {
            $group = $this->resolveGroup($group);
            $groupData = Group::with('group_applications')->whereIn('id', $group)->get();
            $groupApplications = $groupData->pluck('group_applications.application_id')->filter()->toArray();

            $group_id = $group;

        } else {
            $group = $this->resolveGroup($group);
            $groupData = Group::find($group);
            $groupApplications = $groupData->group_applications->pluck('application_id')->toArray();

            $group_id = [$group];
        }

        $viewStatuses = $this->getViewStatuses($group);

        $work_flow_relations = match ($workflow_type_id) {
            3, 5 => ['member', 'application'],
            9 => ['requester', 'department', 'rejectionReason', 'accumulativeMDs', 'deploymentDate'],
            default => []
        };

        $changeRequests = Change_request::where(static function (Builder $query) use ($workflow_type_id) {
            $query->where('workflow_type_id', $workflow_type_id)
                // Workflow is in [Vendor, In-House] check if it's On-Going CR and parent has the workflow
                ->when(in_array($workflow_type_id, [3, 5], true), function (Builder $query) use ($workflow_type_id) {
                    $query->orWhereRelation('parentCR', 'workflow_type_id', $workflow_type_id);
                });
        })
            ->withAllCRStatusesInfo()
            ->with(
                [
                    'technicalCr.technicalCRTeams',
                    ...$work_flow_relations,
                ]
            );

        if ($groupApplications) {
            $changeRequests = $changeRequests->whereIn('application_id', $groupApplications);
            /* $changeRequests = $changeRequests->whereHas('change_request_custom_fields', function ($q) use ($groupApplications) {
                $q->whereIn('change_request_custom_fields.custom_field_name', ['application_id', 'sub_application_id'])->whereIn('change_request_custom_fields.custom_field_value', $groupApplications);
            }); */

            $changeRequests = $changeRequests->where(function ($query) use ($group_id) {
                // Case 1: Where unit_id matches in custom fields
                $query->whereHas('change_request_custom_fields', function ($q) use ($group_id) {
                    $q->where('custom_field_name', 'tech_group_id')
                        ->whereIn('custom_field_value', $group_id);
                })
                    // Case 2: OR unit_id does NOT exist in custom fields
                    ->orWhereDoesntHave('change_request_custom_fields', function ($q) {
                        $q->where('custom_field_name', 'tech_group_id');
                    });
            });
        }

        // Determine page name for pagination based on workflow and group key
        // If group is an array (the "all" groups case), use "all" as the key
        $groupKeyForPage = is_array($group) ? 'all' : (string) ($group ?? 'all');
        $pageName = "type_{$groupKeyForPage}_{$workflow_type_id}";

        return $changeRequests->whereHas('RequestStatuses', function ($query) use ($group_id, $viewStatuses) {
            $query->active()->where(function ($qq) use ($group_id) {
                $qq->whereIn('group_id', $group_id)->orWhereNull('group_id');
            })
                ->whereIn('new_status_id', $viewStatuses)
                ->whereHas('status.group_statuses', function ($query) use ($group_id) {
                    $query->whereIn('group_id', $group_id)
                        ->where('type', 2);
                });
        })->orderBy('id', 'DESC')
            ->paginate(20, ['*'], $pageName);
    }

    public function getQueryByWorkFlow(int $workflow_type_id, $group = null)
    {
        if (is_array($group)) {
            $group = $this->resolveGroup($group);
            $groupData = Group::with('group_applications')->whereIn('id', $group)->get();
            $groupApplications = $groupData->pluck('group_applications.application_id')->filter()->toArray();

            $group_id = $group;

        } else {
            $group = $this->resolveGroup($group);
            $groupData = Group::find($group);
            $groupApplications = $groupData->group_applications->pluck('application_id')->toArray();

            $group_id = [$group];
        }

        $viewStatuses = $this->getViewStatuses($group);

        $changeRequests = Change_request::where(static function (Builder $query) use ($workflow_type_id) {
            $query->where('workflow_type_id', $workflow_type_id)
                ->when(in_array($workflow_type_id, [3, 5], true), function (Builder $query) use ($workflow_type_id) {
                    $query->orWhereRelation('parentCR', 'workflow_type_id', $workflow_type_id);
                });
        });

        if ($groupApplications) {
            $changeRequests = $changeRequests->whereIn('application_id', $groupApplications);

            $changeRequests = $changeRequests->where(function ($query) use ($group_id) {
                $query->whereHas('change_request_custom_fields', function ($q) use ($group_id) {
                    $q->where('custom_field_name', 'tech_group_id')
                        ->whereIn('custom_field_value', $group_id);
                })
                    ->orWhereDoesntHave('change_request_custom_fields', function ($q) {
                        $q->where('custom_field_name', 'tech_group_id');
                    });
            });
        }

        return $changeRequests
            ->when(request()->filled('status_filter'), function (Builder $query) {
                $query->where('hold', request('status_filter') === 'on_hold' ? 1 : 0);
            })
            ->whereHas('RequestStatuses', function ($query) use ($group_id, $viewStatuses) {
                $query->active()->where(function ($qq) use ($group_id) {
                    $qq->whereIn('group_id', $group_id)->orWhereNull('group_id');
                })
                    ->whereIn('new_status_id', $viewStatuses)
                    ->when(request()->filled('status_filter'), function ($q) {
                        $statusFilter = request('status_filter');

                        $values = match ($statusFilter) {
                            'delivered' => ['clos', 'deliver'],
                            'rejected' => ['reject', 'cancel'],
                            'in_progress' => ['clos', 'deliver', 'reject', 'cancel'],
                            default => [],
                        };

                        if ($statusFilter === 'in_progress') {
                            foreach ($values as $filterValue) {
                                $q->whereRelation('status', 'status_name', 'NOT LIKE', '%' . $filterValue . '%');
                            }
                        } elseif ($statusFilter !== 'on_hold') {
                            $q->where(function ($statusQuery) use ($values) {
                                foreach ($values as $filterValue) {
                                    $statusQuery->orWhereRelation('status', 'status_name', 'LIKE', '%' . $filterValue . '%');
                                }
                            });
                        }

                    })
                    ->whereHas('status.group_statuses', function ($query) use ($group_id) {
                        $query->whereIn('group_id', $group_id)
                            ->where('type', 2);
                    });
            });
    }

    public function getAllForLisCRs(array $workflow_type_ids, $group = null): array
    {
        $data = [];

        foreach ($workflow_type_ids as $workflow_type_id) {
            $crs = $this->getAllByWorkFlow($workflow_type_id, $group);

            if ($crs->isEmpty()) {
                continue;
            }

            $data[$workflow_type_id] = $crs;
        }

        return $data;
    }

    public function getAllWithoutPagination($group = null)
    {
        $group = $this->resolveGroup($group);
        $groupData = Group::find($group);
        $groupApplications = $groupData->group_applications->pluck('application_id')->toArray();
        $viewStatuses = $this->getViewStatuses($group);

        $changeRequests = Change_request::with('RequestStatuses.status');

        if ($groupApplications) {
            /* $changeRequests = $changeRequests->whereHas('change_request_custom_fields', function ($q) use ($groupApplications) {
                $q->whereIn('change_request_custom_fields.custom_field_name', ['application_id', 'sub_application_id'])->whereIn('change_request_custom_fields.custom_field_value', $groupApplications);
            }); */
            $changeRequests = $changeRequests->whereIn('application_id', $groupApplications);
            $changeRequests = $changeRequests->where(function ($query) use ($groupData) {
                // Case 1: Where unit_id matches in custom fields
                $query->whereHas('change_request_custom_fields', function ($q) use ($groupData) {
                    $q->where('custom_field_name', 'tech_group_id')
                        ->where('custom_field_value', $groupData->id);
                })
                    // Case 2: OR unit_id does NOT exist in custom fields
                    ->orWhereDoesntHave('change_request_custom_fields', function ($q) {
                        $q->where('custom_field_name', 'tech_group_id');
                    });
            });
        }

        $changeRequests = $changeRequests->whereHas('RequestStatuses', function ($query) use ($group, $viewStatuses) {
            $query->active()->where(function ($qq) use ($group) {
                $qq->where('group_id', $group)->orWhereNull('group_id');
            })
                ->whereIn('new_status_id', $viewStatuses)
                ->whereHas('status.group_statuses', function ($query) use ($group) {
                    $query->where('group_id', $group)
                        ->where('type', 2);
                });
        })->orderBy('id', 'DESC')->get();

        return $changeRequests;
    }

    public function cr_pending_cap($group = null)
    {

        $userId = auth()->user()->id;
        $userEmail = auth()->user()->email;

        // Get all requests with relationships
        $allRequests = Change_request::with(['RequestStatuses.status'])
            ->whereHas('activeCabCrs', function ($query) use ($userId) {
                $query->whereHas('activeCabCrUsers', function ($subQuery) use ($userId) {
                    $subQuery->where('user_id', $userId);
                });
            })

            ->orderBy('id', 'DESC')
            ->limit(50)
            ->get();
        // die("dd");
        // Filter by status using getCurrentStatusForDivision()
        $filtered = $allRequests->filter(function ($item) {
            $status = $item->getCurrentStatusForDivision();

            return $status
                && $status->status
                && in_array($status->status->id, [
                    \App\Services\StatusConfigService::getStatusId('pending_cab'),
                    \App\Services\StatusConfigService::getStatusId('pending_cab', ' kam'),
                    \App\Services\StatusConfigService::getStatusId('pending_cab_approval'),
                ]);
            // return $status && $status->status && $status->status->id == \App\Services\StatusConfigService::getStatusId('pending_cab');
        });

        // Manual pagination
        $perPage = request()->get('per_page', 10);
        $page = request()->get('page', 1);

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $filtered->forPage($page, $perPage),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return $paginated;

    }

    public function cr_hold_promo($group = null)
    {

        // Get all hold requests with relationships
        $allRequests = Change_request::with([
            'RequestStatuses.status',
            'crHold.holdReason',
        ])
            ->where('hold', 1)

            ->orderBy('id', 'DESC')

            ->get();

        return $allRequests;
    }

    public function divisionManagerCr($group = null)
    {
        $userEmail = auth()->user()->email;
        //dd($userEmail);
        $group = $this->resolveGroup($group);

        // Load up to 50 requests for manual filtering
        $allRequests = Change_request::with(['RequestStatuses.status'])
            ->where('division_manager', $userEmail)
            ->orderBy('id', 'DESC')
            ->limit(50)
            ->get();

        // Use full PHP-based filtering with getCurrentStatus()
        $filtered = $allRequests->filter(function ($item) {
            // $status = $item->getCurrentStatus();

            //   echo  config('change_request.status_ids.Cancel').'<br>';
            //   echo  config('change_request.status_ids_kam.Cancel_kam').'<br>';

            //     echo config('change_request.status_ids.Reject').'<br>';

            //     echo config('change_request.status_ids_kam.Reject_kam').'<br>'; die;

            $status = $item->getCurrentStatusForDivision();

            return $status
                && $status->status
                && in_array($status->status->id, [
                    \App\Services\StatusConfigService::getStatusId('business_approval'),
                    \App\Services\StatusConfigService::getStatusId('business_approval', ' kam'),
                    \App\Services\StatusConfigService::getStatusId('division_manager_approval'),
                    //\App\Services\StatusConfigService::getStatusId('division_manager_approval', ' kam'),
                ]);
            // return $status && $status->status && $status->status->id == \App\Services\StatusConfigService::getStatusId('business_approval');
        });

        // Manual pagination
        $perPage = request()->get('per_page', 10);
        $page = request()->get('page', 1);

        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $filtered->forPage($page, $perPage),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return $paginated;
    }

    public function myAssignmentsCrs()
    {
        $userId = Auth::user()->id;
        $group = $this->resolveGroup();
        $viewStatuses = $this->getViewStatuses();
        $viewStatuses[] = \App\Services\StatusConfigService::getStatusId('cr_manager_review');
        if ($group == config('change_request.group_ids.promo')) {
            $crs = Change_request::with('Req_status.status')
                ->whereHas('Req_status', function ($query) use ($viewStatuses) {
                    $query->whereIn('new_status_id', $viewStatuses);
                    // $query->whereRaw('CAST(active AS CHAR) = ?', ['1']);
                })->paginate(50);
        } else {
            $crs = Change_request::with('Req_status.status')
                ->whereHas('Req_status', function ($query) use ($userId, $viewStatuses) {
                    $query->where('assignment_user_id', $userId);
                    // $query->whereRaw('CAST(active AS CHAR) = ?', ['1']);
                    $query->whereIn('new_status_id', $viewStatuses);
                })
                ->paginate(50);
        }

        return $crs;
    }

    public function myCrs()
    {
        $userId = Auth::user()->id;

        return Change_request::where('requester_id', $userId)->get();
    }

    public function find($id)
    {
        $groupApplications = null;
        $groupData = null;
        $userEmail = strtolower(auth()->user()->email);
        $divisionManager = strtolower(Change_request::where('id', $id)->value('division_manager'));

        $groups = ($userEmail === $divisionManager && request()->has('check_dm'))
            ? Group::pluck('id')->toArray()
            : [$this->resolveGroup()];
        // : auth()->user()->user_groups->pluck('group_id')->toArray();
        if ($userEmail == $divisionManager && (request()->has('check_dm') || request()->has('cab_cr_flag'))) {
            $groupApplications = null;
        } else {
            $groupData = Group::find($groups);
            $groupApplications = $groupData[0]->group_applications->pluck('application_id');
            if ($groupApplications) {

                $groupApplications = $groupApplications->toArray();
            }
        }

        $promoGroups = [50];
        $groups = array_merge($groups, $promoGroups);

        $groupPromo = Group::with('group_statuses')->find(50);
        $statusPromoView = $groupPromo->group_statuses->where('type', \App\Models\GroupStatuses::VIEWBY)->pluck('status.id');
        $viewStatuses = $this->getViewStatuses($groups, $id);
        $viewStatuses = $statusPromoView->merge($viewStatuses)->unique();
        $viewStatuses->push(\App\Services\StatusConfigService::getStatusId('cr_manager_review'));
        $viewStatuses->push(\App\Services\StatusConfigService::getStatusId('pending_business_confirmation'));
        $viewStatuses->push(\App\Services\StatusConfigService::getStatusId('sr_business_fb'));
        if (request()->has('check_business')) {
            $viewStatuses->push(\App\Services\StatusConfigService::getStatusId('business_test_case_approval'));
            $viewStatuses->push(\App\Services\StatusConfigService::getStatusId('business_uat_sign_off'));
            $viewStatuses->push(\App\Services\StatusConfigService::getStatusId('pending_business'));
            $viewStatuses->push(\App\Services\StatusConfigService::getStatusId('pending_business_feedback'));
            $viewStatuses->push(\App\Services\StatusConfigService::getStatusId('prototype_approval_business'));
            // $viewStatuses->push(249); // Pending Agreed Scope Approval-Business
            $viewStatuses->push(\App\Services\StatusConfigService::getStatusId('pending_agreed_business'));


        }

        // Debug logging
        Log::info('SearchService find method debug', [
            'id' => $id,
            'userEmail' => $userEmail,
            'divisionManager' => $divisionManager,
            'groups' => $groups,
            'has_check_business' => request()->has('check_business'),
            'viewStatuses' => $viewStatuses->toArray(),
            'groupApplications' => $groupApplications
        ]);

        $userId = auth()->id();
        $changeRequest = Change_request::with('category')->with('change_request_custom_fields')
            ->with('attachments', function ($q) use ($groups) {
                $q->with('user');
                if (!in_array(8, $groups)) {
                    $q->whereHas('user', function ($q) {
                        if (Auth::user()->flag == '0') {
                            $q->where('flag', Auth::user()->flag);
                        }
                        $q->where('visible', 1);
                    });
                }
            })
            ->where('id', $id)
            ->where(function ($query) use ($groups, $viewStatuses, $groupApplications, $userId) {
                // Option 1: Standard group-based permissions
                $query->where(function ($q) use ($groups, $viewStatuses, $groupApplications) {
                    $q->whereHas('RequestStatuses', function ($query) use ($groups, $viewStatuses) {
                        $query->active()->where(function ($qq) use ($groups) {
                            $qq->whereIn('group_id', $groups)->orWhereNull('group_id');
                        })
                            ->whereIn('new_status_id', $viewStatuses);

                        if (!request()->has('check_business')) {
                            $query->whereHas('status.group_statuses', function ($query) use ($groups) {
                                if (!in_array(19, $groups) && !in_array(8, $groups)) {
                                    $query->whereIn('group_id', $groups);
                                }
                                $query->where('type', 2);
                            });
                        }
                    });

                    if ($groupApplications && !request()->has('check_business')) {
                        $q->whereIn('application_id', $groupApplications);
                    }
                })
                    // Option 2: Requester override for specific statuses
                    ->orWhere(function ($q) use ($userId) {
                    $q->where('requester_id', $userId)
                        ->whereHas('RequestStatuses', function ($sub) {
                            $sub->active()->whereIn('new_status_id', [
                                \App\Services\StatusConfigService::getStatusId('pending_business_confirmation'),
                                \App\Services\StatusConfigService::getStatusId('sr_business_fb'),
                            ]);
                        });
                });
            });

        if ($groupApplications && !request()->has('check_business')) {
            if ($groupData) {
                $changeRequest = $changeRequest->where(function ($query) use ($groupData) {
                    // Case 1: Where unit_id matches in custom fields
                    $query->whereHas('change_request_custom_fields', function ($q) use ($groupData) {
                        $q->where('custom_field_name', 'tech_group_id')
                            ->where('custom_field_value', $groupData[0]->id);
                    })
                        // Case 2: OR unit_id does NOT exist in custom fields
                        ->orWhereDoesntHave('change_request_custom_fields', function ($q) {
                            $q->where('custom_field_name', 'tech_group_id');
                        });
                });
            }
        }

        // Log the SQL query
        $sql = $changeRequest->toSql();
        /*Log::info('SearchService SQL query', [
            'sql' => $sql,
            'bindings' => $changeRequest->getBindings()
        ]);*/

        $changeRequest = $changeRequest->first();

        if ($changeRequest) {
            $currentStatus = $this->getCurrentStatus($changeRequest, $viewStatuses);
            // dd(,$currentStatus);
            $changeRequest->current_status = $currentStatus;
            $changeRequest->set_status = $this->getSetStatus($currentStatus, $changeRequest->workflow_type_id);

            if ($assignedUser = $this->getAssignToUsers()) {
                $changeRequest->assign_to = $assignedUser;
            }
        }

        // dd($changeRequest);
        return $changeRequest;
    }

    public function findCr($id)
    {
        $groups = auth()->user()->user_groups->pluck('group_id')->toArray();
        $viewStatuses = $this->getViewStatuses($groups);

        $changeRequest = Change_request::with(['category', 'defects'])
            ->with('attachments', function ($q) use ($groups) {
                $q->with('user');
                if (!in_array(8, $groups)) {
                    $q->whereHas('user', function ($q) {
                        if (Auth::user()->flag == '0') {
                            $q->where('flag', Auth::user()->flag);
                        }
                        $q->where('visible', 1);
                    });
                }
            })
            ->where('id', $id)
            ->first();

        if ($changeRequest) {
            $changeRequest->current_status = $current_status = $this->getCurrentStatusCab($changeRequest, $viewStatuses);
            $changeRequest->set_status = $this->getSetStatus($current_status, $changeRequest->workflow_type_id);
        }

        $assigned_user = $this->getAssignToUsers();
        if ($assigned_user) {
            $changeRequest->assign_to = $assigned_user;
        }

        return $changeRequest;
    }

    public function advancedSearch($getAll = 0)
    {
        $crs = Change_request::with([
            'RequestStatuses' => function ($q) {
                $q->with('status');

                $selected_statuses = (array) request()->query('new_status_id', []);
                if (count($selected_statuses) > 0) {
                    $q->whereIn('new_status_id', $selected_statuses);
                }
            },
            'changeRequestCustomFields'
        ])->filters();

        return $getAll == 0 ? $crs->paginate(10) : $crs->get();
    }

    public function searchChangeRequest($id)
    {
        $userFlag = Auth::user()->flag;

        return Change_request::with('Release')
            ->where('id', $id)
            ->orWhere('cr_no', $id)
            ->first();
    }

    public function showChangeRequestData($id, $group)
    {
        return Change_request::with([
            'current_status' => function ($q) use ($group) {
                $q->where('group_statuses.group_id', $group)->with('status.to_status_workflow');
            }
        ])->where('id', $id)->get();
    }

    public function findWithReleaseAndStatus($id)
    {
        return Change_request::with('release')->find($id);
    }

    public function getCurrentStatusForDivision($changeRequest)
    {
        $status = Change_request_statuse::where('cr_id', $changeRequest->id)->active()->first();

        return $status;

    }

    protected function resolveGroup($group = null)
    {
        if (!empty($group)) {
            return $group;
        }

        return session('default_group') ?: auth()->user()->default_group;
    }

    protected function getViewStatuses($group = null, $id = null): array
    {
        $userEmail = strtolower(auth()->user()->email);
        $divisionManager = $id ? strtolower(Change_request::where('id', $id)->value('division_manager')) : null;
        $currentStatus = $id ? Change_request_statuse::where('cr_id', $id)->where('active', '1')->value('new_status_id') : null;

        $group = $this->resolveGroup($group);

        // Check if user is division manager and status is business approval
        if ($userEmail === $divisionManager && $currentStatus == \App\Services\StatusConfigService::getStatusId('business_approval')) {
            $group = Group::pluck('id')->toArray();
        }

        $viewStatuses = new GroupStatuses();

        if (is_array($group)) {
            $viewStatuses = $viewStatuses->whereIn('group_id', $group)->where('type', 2);
        } else {
            $viewStatuses = $viewStatuses->where('group_id', $group)->where('type', 2);
        }

        $viewStatuses = $viewStatuses->groupBy('status_id')->get()->pluck('status_id')->toArray();
        // dd($id);
        // Handle technical team status

        /* if ($id) {
            $technicalCrTeamStatus = $this->getTechnicalTeamCurrentStatus($id);
            //dd($technicalCrTeamStatus, $viewStatuses);
            if ($technicalCrTeamStatus && in_array($technicalCrTeamStatus->current_status_id, $viewStatuses)) {
                $viewStatuses = [$technicalCrTeamStatus->current_status_id];
                // dd($viewStatuses);
            }
        } */

        return $viewStatuses;
    }

    protected function getTechnicalTeamCurrentStatus($id)
    {
        $group = $this->resolveGroup();
        $technicalCr = TechnicalCr::where('cr_id', $id)->whereRaw('CAST(status AS CHAR) = ?', ['0'])->first();
        // dd($technicalCr);

        if ($technicalCr) {
            return $technicalCr->technical_cr_team()
                ->where('group_id', $group)
                // ->where('status', '0')
                ->whereRaw('CAST(status AS CHAR) = ?', ['0'])
                ->first();
        }

        return null;
    }

    protected function getCurrentStatus($changeRequest, $viewStatuses)
    {
        if (request()->reference_status) {
            return Change_request_statuse::find(request()->reference_status);
        }

        return Change_request_statuse::where('cr_id', $changeRequest->id)
            ->whereIn('new_status_id', $viewStatuses)
            ->active()
            ->first();

    }

    protected function getCurrentStatusCab($changeRequest, $viewStatuses)
    {
        return Change_request_statuse::where('cr_id', $changeRequest->id)
            ->active()
            ->first();
    }

    protected function getSetStatus($currentStatus, $typeId)
    {
        if (!$currentStatus) {
            return collect();
        }

        $statusId = $currentStatus->new_status_id;
        $previousStatusId = $currentStatus->old_status_id;

        return NewWorkFlow::where('from_status_id', $statusId)
            ->where(function ($query) use ($previousStatusId) {
                $query->whereNull('previous_status_id')
                    ->orWhere('previous_status_id', 0)
                    ->orWhere('previous_status_id', $previousStatusId);
            })
            ->whereHas('workflowstatus', function ($q) {
                $q->whereColumn('to_status_id', '!=', 'new_workflow.from_status_id');
            })
            ->where('type_id', $typeId)
            ->active()
            ->orderBy('id', 'DESC')
            ->get();
    }

    protected function getAssignToUsers()
    {
        $userId = Auth::user()->id;
        $assignTo = User::whereHas('user_report_to', function ($q) use ($userId) {
            $q->where('report_to', $userId)->where('user_id', '!=', $userId);
        })->get();

        return count($assignTo) > 0 ? $assignTo : null;
    }
}
