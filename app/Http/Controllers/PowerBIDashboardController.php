<?php

namespace App\Http\Controllers;

use App\Models\Change_request;
use App\Models\Change_request_statuse;
use App\Models\Status;
use App\Models\Application;
use App\Models\Group;
use App\Models\Category;
use App\Models\RequesterDepartment;
use App\Models\WorkFlowType;
use App\Services\ChangeRequest\ChangeRequestSearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PowerBIDashboardController extends Controller
{
    // Workflow type IDs
    const INHOUSE_WORKFLOW = 3;
    const VENDOR_WORKFLOW  = 5;
    const PROMO_WORKFLOW   = 9;

    /**
     * Show the main dashboard view.
     */
    public function index()
    {
        $this->authorize('Dashboard');

        return view('powerbi_dashboard');
    }

    /**
     * AJAX: Get all 3 workflow status breakdowns for the left panel.
     * Uses active (active=1) change_request_statuses records joined to statuses table.
     */
    public function allWorkflowStats(Request $request)
    {
        return response()->json($this->computeAllWorkflowStats($request));
    }

    private function computeAllWorkflowStats(Request $request): array
    {
        $workflows = [
            self::INHOUSE_WORKFLOW => 'In-House',
            self::VENDOR_WORKFLOW  => 'Vendor',
            self::PROMO_WORKFLOW   => 'Promo',
        ];

        $has_category_filter = $request->query('chart_key') === 'cCategory';

        $result = [];

        foreach ($workflows as $wfId => $wfName) {
            // Combine In_House and On_Going
            if ($wfId === 3) {
                $wfId = [3, 13];
            }

            $statuses = DB::table('change_request_statuses as crs')
                ->join('change_request as cr', function (JoinClause $join) use ($request, $has_category_filter) {
                    $join->on('cr.id', '=', 'crs.cr_id')
                        ->when($request->get('chart_key') === 'cTargeted', function ($query) use ($request) {
                            $query->join('applications as app', function (JoinClause $join) use ($request) {
                                $join->on('app.id', '=', 'cr.application_id')
                                    ->where('app.name', 'LIKE', '%'.$request->get('label').'%');
                            });
                        })
                        ->when($has_category_filter, function ($query) use ($request) {
                            $query->join('categories as cat', function (JoinClause $join) use ($request) {
                                $join->on('cat.id', '=', 'cr.category_id')
                                    ->where('cat.name', 'LIKE', '%'.$request->get('label').'%');
                            });
                        })
                        ->when($request->query('chart_key') === 'cDepartment', function ($query) {
                            $query->join('change_request_custom_fields as cf', function (JoinClause $join)  {
                                $join->on('cf.cr_id', '=', 'cr.id')
                                    ->where('cf.custom_field_name','requester_department')
                                    ->where('cf.custom_field_name', \request()->query('label'));
                            });
                        })
                    ;
                })

                ->join('statuses as s', 's.id', '=', 'crs.new_status_id')
                ->where('crs.active', '1')
                ->whereIn('cr.workflow_type_id', (array) $wfId)
                ->select('s.status_name', DB::raw('count(*) as total'))
                ->groupBy('s.id', 's.status_name')
                ->orderByDesc('total')
                ->get();

            $items = $statuses->map(fn($s) => [
                'status' => $s->status_name,
                'count'  => $s->total,
            ])->values()->toArray();

            $wfBaseQuery = Change_request::whereIn('workflow_type_id', (array) $wfId)
                ->when($request->query('chart_key') === 'cTargeted', function ($q) {
                    $q->whereRelation('application', 'name', 'LIKE', '%'. \request('label') . '%');
                })
                ->when($request->query('chart_key') === 'cCategory', function ($q) {
                    $q->whereRelation('category', 'name', 'LIKE', '%'. \request('label') . '%');
                })
                ->when($request->query('chart_key') === 'cDepartment', function (Builder $q) {
                    $q->whereHas('changeRequestCustomFields', function($q) {
                        $q->where('custom_field_name', 'requester_department')
                            ->where('custom_field_value', \request('label'));
                    });
                });
            $this->applyActiveStatusLabelFilter($wfBaseQuery, $request);

            $result[] = [
                'name'  => $wfName,
                'total' => $wfBaseQuery->count(),
                'items' => $items,
            ];
        }

        return $result;
    }

    /**
     * AJAX: Get general overview data (Page 1).
     */
    public function generalData(Request $request)
    {
        return response()->json($this->computeGeneralData($request));
    }

    private function computeGeneralData(Request $request): array
    {
        $workflows = [
            self::INHOUSE_WORKFLOW => 'In-House',
            self::VENDOR_WORKFLOW  => 'Vendor',
            self::PROMO_WORKFLOW   => 'Promo',
        ];

        $wffr = null;
        $wffr_com = $request->query('chart_key') !== 'cDonut' ? $request->get('chart_key') : $request->get('label');
        foreach ($workflows as $wfId => $wfName) {
            if ($wffr_com !== null && str_contains((string) $wffr_com, $wfName)) {
                // Combine In_House and On_Going
                if ($wfId === 3) {
                    $wfId = [3, 13];
                }

                $wffr = $wfId;
            }
        }

        $workflowTypeId = $request->get('workflow_type_id', (array) $wffr);

        if ((int) $workflowTypeId === 3) {
            $workflowTypeId = [3, 13];
        }

        $workflowTypeId = (array) $workflowTypeId;

        // Base query on change_request (dashboard filters only — cross-chart “baseline vs filtered” is compared in JS)
        $baseQuery = Change_request::query()
            ->whereIn('workflow_type_id', [3,13,5,9]);
        if ($workflowTypeId) {
            $baseQuery->whereIn('workflow_type_id', $workflowTypeId);
        }
        $baseQuery = $this->applyFilters($baseQuery, $request);
        $this->applyActiveStatusLabelFilter($baseQuery, $request, 'change_request');

        // Total CRs
        $totalCrs = (clone $baseQuery)
            ->when($request->query('chart_key') === 'cTargeted', function ($q) {
                $q->whereRelation('application', 'name', 'LIKE', '%'. \request('label') . '%');
            })
            ->when($request->query('chart_key') === 'cCategory', function ($q) {
                $q->whereRelation('category', 'name', 'LIKE', '%'. \request('label') . '%');
            })
            ->when($request->query('chart_key') === 'cDepartment', function (Builder $q) {
                $q->whereHas('changeRequestCustomFields', function($q) {
                    $q->where('custom_field_name', 'requester_department')
                        ->where('custom_field_value', \request('label'));
                });
            })
            ->count();
        // dd($totalCrs);

        $wfCountRows = (clone $baseQuery)
            ->when($request->query('chart_key') === 'cTargeted', function ($q) {
                $q->whereRelation('application', 'name', 'LIKE', '%'. \request('label') . '%');
            })
            ->when($request->query('chart_key') === 'cCategory', function ($q) {
                $q->whereRelation('category', 'name', 'LIKE', '%'. \request('label') . '%');
            })
            ->when($request->query('chart_key') === 'cDepartment', function (Builder $q) {
                $q->whereHas('changeRequestCustomFields', function($q) {
                    $q->where('custom_field_name', 'requester_department')
                        ->where('custom_field_value', \request('label'));
                });
            })
            ->select('workflow_type_id', DB::raw('count(*) as c'))
            ->groupBy('workflow_type_id')
            ->get();

        $workflowTotals = [
            'in_house' => (int) ($wfCountRows->firstWhere('workflow_type_id', self::INHOUSE_WORKFLOW)->c ?? 0) + (int) $wfCountRows->firstWhere('workflow_type_id', 13)?->c ?? 0,
            'vendor'   => (int) ($wfCountRows->firstWhere('workflow_type_id', self::VENDOR_WORKFLOW)->c ?? 0),
            'promo'    => (int) ($wfCountRows->firstWhere('workflow_type_id', self::PROMO_WORKFLOW)->c ?? 0),
        ];

        $has_category_filter = $request->query('chart_key') === 'cCategory';

        // Status counts via join with active statuses
        $statusRaw = DB::table('change_request_statuses as crs')
            ->join('change_request as cr', function (JoinClause $join) use ($request, $has_category_filter) {
                $join->on('cr.id', '=', 'crs.cr_id')
                    ->when($request->get('chart_key') === 'cTargeted', function ($query) use ($request) {
                        $query->join('applications as app', function (JoinClause $join) use ($request) {
                            $join->on('app.id', '=', 'cr.application_id')
                                ->where('app.name', 'LIKE', '%'.$request->get('label').'%');
                        });
                    })
                    ->when($has_category_filter, function ($query) use ($request) {
                        $query->join('categories as cat', function (JoinClause $join) use ($request) {
                            $join->on('cat.id', '=', 'cr.category_id')
                                ->where('cat.name', 'LIKE', '%'.$request->get('label').'%');
                        });
                    })
                    ->when($request->query('chart_key') === 'cDepartment', function ($query) {
                        $query->join('change_request_custom_fields as cf', function (JoinClause $join)  {
                            $join->on('cf.cr_id', '=', 'cr.id')
                                ->where('cf.custom_field_name','requester_department')
                                ->where('cf.custom_field_name', \request()->query('label'));
                        });
                    })
                ;
            })
            ->join('statuses as s', function (JoinClause $join) use ($wffr, $request) {
                $join->on('s.id', '=', 'crs.new_status_id')
                    ->when($wffr, function ($q) use ($request) {
                        $q->where('s.status_name', 'LIKE', '%'.$request->get('label').'%');
                    });
            })
            ->where('crs.active', '1')
            ->when($workflowTypeId, fn($q) => $q->whereIn('cr.workflow_type_id', $workflowTypeId))
            ->when($request->get('application_id'), fn($q, $v) => $q->whereIn('cr.application_id', (array)$v))
            ->when($request->get('date_from'), fn($q, $v) => $q->whereDate('cr.created_at', '>=', $v))
            ->when($request->get('date_to'), fn($q, $v) => $q->whereDate('cr.created_at', '<=', $v))
            ->select('s.status_name', DB::raw('count(*) as total'))
            ->groupBy('s.id', 's.status_name')
            ->orderByDesc('total')
            ->get();

        $statusBreakdown = [];

        foreach ($statusRaw as $item) {
            $statusBreakdown[] = [
                'status' => $item->status_name,
                'count'  => $item->total,
            ];
        }

        $onHold = DB::table('change_request')
            ->whereIn('workflow_type_id', [3,13,5,9])
            ->when($workflowTypeId, fn($q) => $q->whereIn('workflow_type_id', $workflowTypeId))
            ->where('hold', 1)
            ->count();
        $completed  = $this->countByStatusKeyword($workflowTypeId, $request, ['deliver', 'clos', 'done']);
        $rejected   = $this->countByStatusKeyword($workflowTypeId, $request, ['reject', 'cancel']);
        $inProgress = max(0, $totalCrs - $onHold - $completed - $rejected);

        // CRs per application (targeted system) — narrow by clicked status row so JS baseline vs filtered can compare
        $crsPerAppQuery = clone $baseQuery;
        // Status filter is already applied to baseQuery
        $crsPerApp = $crsPerAppQuery
            ->when($request->query('chart_key') === 'cCategory', function ($q) {
                $q->whereRelation('category', 'name', 'LIKE', '%'. \request('label') . '%');
            })
            ->when($request->query('chart_key') === 'cDepartment', function (Builder $q) {
                $q->whereHas('changeRequestCustomFields', function($q) {
                    $q->where('custom_field_name', 'requester_department')
                        ->where('custom_field_value', \request('label'));
                });
            })
            ->select('application_id', DB::raw('count(*) as total'))
            ->groupBy('application_id')
            ->with('application:id,name')
            ->orderByDesc('total')
            ->get()
            ->map(fn($item) => [
                'application' => $item->application ? $item->application->name : 'Unknown',
                'count'       => $item->total,
            ]);

        // CRs per month (last 12 months) - created
        $crsPerMonth = (clone $baseQuery)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // CRs per requesting department (via custom field)
        $deptFieldId = DB::table('custom_fields')->where('name', 'requester_department')->value('id');
        $crsPerDepartment = [];
        if ($deptFieldId) {
            $deptQuery = DB::table('change_request as cr')
                ->join('change_request_custom_fields as cf', function ($join) use ($deptFieldId) {
                    $join->on('cf.cr_id', '=', 'cr.id')
                        ->where('cf.custom_field_name', '=', 'requester_department');
                })
                ->join('requester_departments as rd', 'rd.id', '=', 'cf.custom_field_value')
                ->when($request->query('chart_key') === 'cTargeted', function ($q) {
                    $q->join('applications', function (JoinClause $join) {
                        $join->on('cr.application_id', '=', 'applications.id')
                            ->where('applications.name', 'LIKE', '%'. \request('label') . '%');
                    });
                })
                ->when($request->query('chart_key') === 'cCategory', function ($q) {
                    $q->join('categories', function (JoinClause $join) {
                        $join->on('cr.category_id', '=', 'categories.id')
                            ->where('categories.name', 'LIKE', '%'. \request('label') . '%');
                    });
                })
                ->when($workflowTypeId, fn($q) => $q->whereIn('cr.workflow_type_id', $workflowTypeId))
                ->when($request->get('application_id'), fn($q, $v) => $q->whereIn('cr.application_id', (array)$v))
                ->when($request->get('date_from'), fn($q, $v) => $q->whereDate('cr.created_at', '>=', $v))
                ->when($request->get('date_to'), fn($q, $v) => $q->whereDate('cr.created_at', '<=', $v));

            $this->applyActiveStatusLabelFilter($deptQuery, $request, 'cr');

            $deptQuery = $deptQuery->select('rd.name as department', DB::raw('count(*) as total'))
                ->groupBy('rd.id', 'rd.name')
                ->orderByDesc('total')
                ->get();

            $crsPerDepartment = $deptQuery->map(fn($item) => [
                'department' => $item->department,
                'count'      => $item->total,
            ])->values()->toArray();
        }

        // CRs per category
        $crsPerCategory = (clone $baseQuery)
            ->when($request->query('chart_key') === 'cTargeted', function ($q) {
                $q->whereRelation('application', 'name', 'LIKE', '%'. \request('label') . '%');
            })
            ->when($request->query('chart_key') === 'cDepartment', function (Builder $q) {
                $q->whereHas('changeRequestCustomFields', function($q) {
                    $q->where('custom_field_name', 'requester_department')
                        ->where('custom_field_value', \request('label'));
                });
            })
            ->select('category_id', DB::raw('count(*) as total'))
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->with('category:id,name')
            ->orderByDesc('total')
            ->get()
            ->map(fn($item) => [
                'category' => $item->category ? $item->category->name : 'Unknown',
                'count'    => $item->total,
            ]);

        return [
            'kpis' => [
                'total'       => $totalCrs,
                'in_progress' => $inProgress,
                'completed'   => $completed,
                'rejected'    => $rejected,
                'on_hold' => $onHold,
            ],
            'workflow_totals'   => $workflowTotals,
            'status_breakdown'  => $statusBreakdown,
            'crs_per_app'       => $crsPerApp,
            'crs_per_month'     => $crsPerMonth,
            'crs_per_department' => $crsPerDepartment,
            'crs_per_category'  => $crsPerCategory,
        ];
    }

    /**
     * When user clicks a bar on a left “*-statuses” chart, restrict queries to CRs on that active status.
     */
    private function applyActiveStatusLabelFilter($baseQuery, Request $request, $crAlias = 'change_request'): void
    {
        if (! $request->filled('chart_key') || ! $request->filled('label')) {
            return;
        }
        $chartKey = (string) $request->get('chart_key');
        if (! str_contains($chartKey, '-statuses')) {
            return;
        }
        $label = (string) $request->get('label');
        $baseQuery->whereExists(function ($q) use ($label, $crAlias) {
            $q->select(DB::raw('1'))
                ->from('change_request_statuses as crs_filter')
                ->join('statuses as s_filter', 's_filter.id', '=', 'crs_filter.new_status_id')
                ->whereColumn('crs_filter.cr_id', $crAlias . '.id')
                ->where('crs_filter.active', '1')
                ->where('s_filter.status_name', 'like', '%'.$label.'%');
        });
    }

    /**
     * AJAX: Get detailed data (Page 2) — CRs per group/unit, monthly trend.
     */
    public function detailedData(Request $request)
    {
        $workflowTypeId = $request->get('workflow_type_id');
        $groupId        = $request->get('group_id');
        $statusFilter   = $request->get('status_filter'); // 'in_progress' | 'delivered' | 'rejected'

        $wfs = [self::INHOUSE_WORKFLOW => 'in_house', self::VENDOR_WORKFLOW => 'vendor', self::PROMO_WORKFLOW => 'promo'];

        // Build base CR query
        $baseQuery = Change_request::query();

        $workflowTypeId = match (true) {
            $request->query('chart_key') === 'cP2_InHouse' => self::INHOUSE_WORKFLOW,
            $request->query('chart_key') === 'cP2_Vendor' => self::VENDOR_WORKFLOW,
            $request->query('chart_key') === 'cP2_Promo' => self::PROMO_WORKFLOW,
            default => null,
        };

        if ($workflowTypeId) {
            $baseQuery->whereIn('workflow_type_id', (array) $workflowTypeId);
        }
        $baseQuery = $this->applyFilters($baseQuery, $request);

        // Total CRs
        $totalCrs = (clone $baseQuery)->count();



        // Created CRs per month (last 12 months)
        $createdPerMonth = (clone $baseQuery)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', now()->subMonths(13))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Delivered CRs per month — from status history where status_name contains 'deliver'
        $deliveredPerMonth = DB::table('change_request_statuses as crs')
            ->join('change_request as cr', 'cr.id', '=', 'crs.cr_id')
            ->join('statuses as s', 's.id', '=', 'crs.new_status_id')
            ->where('s.status_name', 'like', '%deliver%')
            ->when($workflowTypeId, fn($q) => $q->where('cr.workflow_type_id', $workflowTypeId))
            ->when($request->get('application_id'), fn($q, $v) => $q->whereIn('cr.application_id', (array)$v))
            ->when($request->get('date_from'), fn($q, $v) => $q->whereDate('crs.created_at', '>=', $v))
            ->when($request->get('date_to'), fn($q, $v) => $q->whereDate('crs.created_at', '<=', $v))
            ->where('crs.created_at', '>=', now()->subMonths(13))
            ->select(
                DB::raw("DATE_FORMAT(crs.created_at, '%Y-%m') as month"),
                DB::raw('count(DISTINCT crs.cr_id) as total')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $searchService = app(ChangeRequestSearchService::class);

        $groups_by_parents = DB::table('groups as p')
            ->join('groups as c', function ($join) {
                $join->on('p.id', '=', 'c.parent_id')
                    ->whereNull('p.parent_id');
            })
            ->when(in_array($request->query('chart_key'), ['cPerUnit', 'cP2_InHouse', 'cP2_Vendor', 'cP2_Promo']), function (QueryBuilder $query) {
                $query->where('p.title', 'LIKE', '%' . \request('label') . '%');
            })
            ->select(
                'p.title',
                'p.id AS p_id',
                'c.id AS c_id',
            )
            ->get()
            ->groupBy('p_id');

        $appIds = (array) $request->get('application_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $crsPerUnitTemp = [];
        $unitBreakdown = [];

        $total_count = 0;

        foreach ($wfs as $wfId => $wfKey) {
            $wfData = [];

            if ($workflowTypeId && $workflowTypeId != $wfId && $workflowTypeId != 'all') {
                $unitBreakdown[$wfKey] = [];
                continue;
            }

            foreach ($groups_by_parents as $child_groups) {
                $parent_title = $child_groups->first()->title;
                $count = 0;
                foreach ($child_groups as $c_group) {
                    $query = $searchService->getQueryByWorkFlow($wfId, $c_group->c_id);

                    if (!empty($appIds)) {
                        $query->whereIn('application_id', $appIds);
                    }
                    if ($dateFrom) {
                        $query->whereDate('created_at', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $query->whereDate('created_at', '<=', $dateTo);
                    }

                    $count += $query->count();
                    $total_count += $count;
                }

                $p_id = $child_groups->first()->p_id;

                $wfData[] = [
                    'group' => $parent_title,
                    'count' => $count,
                ];

                if (!isset($crsPerUnitTemp[$p_id])) {
                    $crsPerUnitTemp[$p_id] = [
                        'group' => $parent_title,
                        'group_id' => $p_id,
                        'count' => 0
                    ];
                }
                $crsPerUnitTemp[$p_id]['count'] += $count;
            }

            usort($wfData, fn($a, $b) => $b['count'] <=> $a['count']);
            $unitBreakdown[$wfKey] = $wfData;
        }

        $crsPerUnit = array_values($crsPerUnitTemp);
        usort($crsPerUnit, fn($a, $b) => $b['count'] <=> $a['count']);

        $allGroups = collect($crsPerUnit)
            ->map(fn($item) => (object) ['id' => $item['group_id'], 'name' => $item['group']])
            ->sortBy('name')
            ->values()
            ->toArray();

        // Status breakdown helpers
        $onHold = DB::table('change_request')
            ->whereIn('workflow_type_id', [3,13,5,9])
            ->when($workflowTypeId, fn($q) => $q->whereIn('workflow_type_id', $workflowTypeId))
            ->where('hold', 1)
            ->count();
        $total_crs = Change_request::whereIn('workflow_type_id', [3, 13, 5, 9])
            ->count();
        $delivered  = $this->countByStatusKeyword($workflowTypeId, $request, ['deliver', 'clos']);
        $rejected   = $this->countByStatusKeyword($workflowTypeId, $request, ['reject', 'cancel']);
        $inProgress = $total_crs - $onHold - $delivered - $rejected;

        if ($request->query('status_filter') === 'delivered') {
            $rejected = 0;
            $inProgress = 0;
            $onHold = 0;
            $total_crs = $delivered;
        } elseif ($request->query('status_filter') === 'rejected') {
            $inProgress = 0;
            $onHold = 0;
            $delivered = 0;
            $total_crs = $rejected;
        } elseif ($request->query('status_filter') === 'in_progress') {
            $rejected = 0;
            $onHold = 0;
            $delivered = 0;
            $total_crs = $inProgress;
        } elseif ($request->query('status_filter') === 'on_hold') {
            $rejected = 0;
            $inProgress = 0;
            $delivered = 0;
            $total_crs = $onHold;
        }

        return response()->json([
            'kpis' => [
                'total'       => $total_crs,
                'in_progress' => max(0, $inProgress),
                'completed'   => $delivered,
                'rejected'    => $rejected,
                'on_hold'     => $onHold,
            ],
            'created_per_month'   => $createdPerMonth,
            'delivered_per_month' => $deliveredPerMonth,
            'crs_per_unit'        => $crsPerUnit,
            'unit_breakdown'      => $unitBreakdown,
            'all_groups'          => $allGroups,
        ]);
    }

    /**
     * AJAX: Get SLA/Aging data (Page 3) — strictly in-progress CRs.
     */
    public function page3Data(Request $request)
    {
        $workflowTypeId = $request->get('workflow_type_id');
        $groupId        = $request->get('group_id');

        if ($workflowTypeId && (int) $workflowTypeId === 3) {
            $workflowTypeId = [3, 13];
        }

        // Base Query just for in-progress CRs
        $baseQuery = DB::table('change_request_statuses as crs')
            ->join('change_request as cr', 'cr.id', '=', 'crs.cr_id')
            ->join('statuses as s', 's.id', '=', 'crs.new_status_id')
            ->where('crs.active', '1')
            ->where('hold', 0)
            ->where('s.status_name', 'not like', '%deliver%')
            ->where('s.status_name', 'not like', '%clos%')
            ->where('s.status_name', 'not like', '%done%')
            ->where('s.status_name', 'not like', '%reject%')
            ->where('s.status_name', 'not like', '%cancel%')
            ->whereIn('cr.workflow_type_id', [3, 13, 5, 9])
            ->when($workflowTypeId, fn($q) => $q->whereIn('cr.workflow_type_id', (array) $workflowTypeId))
            ->when($groupId, function($q) use ($groupId) {

                $childGroups = Group::where('parent_id', $groupId)->pluck('id')->toArray();

                // Map the exact CRs currently belonging to this group based on List CRs logic
                $searchService = app(ChangeRequestSearchService::class);
                $crIds = [];
                foreach ([3, 5, 9] as $wfId) {
                    $matchedIds = $searchService->getQueryByWorkFlow($wfId, $childGroups)->pluck('id')->toArray();
                    $crIds = array_merge($crIds, $matchedIds);
                }
                $q->whereIn('cr.id', $crIds);
            })
            ->when($request->get('application_id'), fn($q, $v) => $q->whereIn('cr.application_id', (array)$v))
            ->when($request->get('date_from'), fn($q, $v) => $q->whereDate('cr.created_at', '>=', $v))
            ->when($request->get('date_to'), fn($q, $v) => $q->whereDate('cr.created_at', '<=', $v));

        if ($groupId) {
            $totalInProgress = (clone $baseQuery)->distinct('cr.id')->count('cr.id');
            $inHouse = (clone $baseQuery)->whereIn('cr.workflow_type_id', [self::INHOUSE_WORKFLOW, 13])->distinct('cr.id')->count('cr.id');
            $vendor  = (clone $baseQuery)->where('cr.workflow_type_id', self::VENDOR_WORKFLOW)->distinct('cr.id')->count('cr.id');
            $promo   = (clone $baseQuery)->where('cr.workflow_type_id', self::PROMO_WORKFLOW)->distinct('cr.id')->count('cr.id');
        } else {
            // Unfiltered by group: mathematically derive 'In Progress' to perfectly match Page 1 & 2 global totals
            $baseCrQuery = \App\Models\Change_request::query()->where('hold', 0)
                ->whereIn('workflow_type_id', [3, 13, 5, 9]);
            $baseCrQuery = $this->applyFilters($baseCrQuery, $request);

            $totalCrs = (clone $baseCrQuery)->when($workflowTypeId, fn($q) => $q->whereIn('workflow_type_id', (array) $workflowTypeId))->count();
            $completed  = $this->countByStatusKeyword($workflowTypeId, $request, ['deliver', 'clos', 'done']);
            $rejected   = $this->countByStatusKeyword($workflowTypeId, $request, ['reject', 'cancel']);
            $totalInProgress = max(0, $totalCrs - $completed - $rejected);

            $inHouseCrs = (clone $baseCrQuery)->whereIn('workflow_type_id', [self::INHOUSE_WORKFLOW, 13])->count();
            $inHouseComp = $this->countByStatusKeyword([self::INHOUSE_WORKFLOW, 13], $request, ['deliver', 'clos', 'done']);
            $inHouseRej = $this->countByStatusKeyword([self::INHOUSE_WORKFLOW, 13], $request, ['reject', 'cancel']);
            $inHouse = max(0, $inHouseCrs - $inHouseComp - $inHouseRej);

            $vendorCrs = (clone $baseCrQuery)->where('workflow_type_id', self::VENDOR_WORKFLOW)->count();
            $vendorComp = $this->countByStatusKeyword(self::VENDOR_WORKFLOW, $request, ['deliver', 'clos', 'done']);
            $vendorRej = $this->countByStatusKeyword(self::VENDOR_WORKFLOW, $request, ['reject', 'cancel']);
            $vendor  = max(0, $vendorCrs - $vendorComp - $vendorRej);

            $promoCrs = (clone $baseCrQuery)->where('workflow_type_id', self::PROMO_WORKFLOW)->count();
            $promoComp = $this->countByStatusKeyword(self::PROMO_WORKFLOW, $request, ['deliver', 'clos', 'done']);
            $promoRej = $this->countByStatusKeyword(self::PROMO_WORKFLOW, $request, ['reject', 'cancel']);
            $promo   = max(0, $promoCrs - $promoComp - $promoRej);

            if ($workflowTypeId) {
                if ($workflowTypeId === [3, 13]) {
                    $vendor = 0;
                    $promo = 0;
                } elseif ((int) $workflowTypeId === 5) {
                    $promo = 0;
                    $inHouse = 0;
                } elseif ((int) $workflowTypeId === 9) {
                    $vendor = 0;
                    $inHouse = 0;
                }
            }
        }

        // 1. Bar Chart: In-Progress CRs By Status (sorted by count)
        $crsPerStatus = (clone $baseQuery)
            ->select('s.status_name', DB::raw('count(DISTINCT cr.id) as count'))
            ->groupBy('s.id', 's.status_name')
            ->orderByDesc('count')
            ->get();

        // 2. Bar Chart: In-Progress SLA Aging (unsorted from sql, will sort chronologically in PHP)
        $agingBuckets = (clone $baseQuery)
            ->select(DB::raw("
                CASE
                    WHEN DATEDIFF(NOW(), cr.created_at) < 5 THEN 'Less 5 days'
                    WHEN DATEDIFF(NOW(), cr.created_at) >= 5 AND DATEDIFF(NOW(), cr.created_at) <= 15 THEN 'between 5 to 15 day'
                    WHEN DATEDIFF(NOW(), cr.created_at) > 15 AND DATEDIFF(NOW(), cr.created_at) <= 30 THEN 'between 15 to 30 day'
                    WHEN DATEDIFF(NOW(), cr.created_at) > 30 AND DATEDIFF(NOW(), cr.created_at) <= 45 THEN 'between 30 to 45 day'
                    WHEN DATEDIFF(NOW(), cr.created_at) > 45 AND DATEDIFF(NOW(), cr.created_at) <= 60 THEN 'between 45 to 60 day'
                    WHEN DATEDIFF(NOW(), cr.created_at) > 60 AND DATEDIFF(NOW(), cr.created_at) <= 90 THEN 'between 60 to 90 day'
                    WHEN DATEDIFF(NOW(), cr.created_at) > 90 AND DATEDIFF(NOW(), cr.created_at) <= 120 THEN 'between 90 to 120 day'
                    WHEN DATEDIFF(NOW(), cr.created_at) > 120 AND DATEDIFF(NOW(), cr.created_at) <= 150 THEN 'between 120 to 150 day'
                    ELSE 'between 150 to 260 day'
                END as bucket
            "), DB::raw('count(DISTINCT cr.id) as count'))
            ->groupBy('bucket')
            ->get();

        $sortOrder = [
            'Less 5 days' => 1,
            'between 5 to 15 day' => 2,
            'between 15 to 30 day' => 3,
            'between 30 to 45 day' => 4,
            'between 45 to 60 day' => 5,
            'between 60 to 90 day' => 6,
            'between 90 to 120 day' => 7,
            'between 120 to 150 day' => 8,
            'between 150 to 260 day' => 9,
        ];

        $agingArray = $agingBuckets->toArray();
        usort($agingArray, function($a, $b) use ($sortOrder) {
            return $sortOrder[$a->bucket] <=> $sortOrder[$b->bucket];
        });

        $searchService = app(ChangeRequestSearchService::class);
        $groups = DB::table('groups as p')
            ->join('groups as c', function ($join) {
                $join->on('p.id', '=', 'c.parent_id')
                    ->whereNull('p.parent_id');
            })
            ->select(
                'p.id AS p_id',
                'p.title AS p_title',
                'c.id AS c_id',
            )
            ->get()
            ->groupBy('p_id');
        $allGroupsTemp = [];

        $appIds = (array) $request->get('application_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        foreach ($groups as $childGroups) {
            $count = 0;
            $parentGroup = $childGroups->first();
            $workflowsToCheck = $workflowTypeId ? (array) $workflowTypeId : [self::INHOUSE_WORKFLOW, self::VENDOR_WORKFLOW, self::PROMO_WORKFLOW];
            foreach ($workflowsToCheck as $wfId) {
                foreach ($childGroups as $childGroup) {
                    $query = $searchService->getQueryByWorkFlow($wfId, $childGroup->c_id);
                    if (!empty($appIds)) {
                        $query->whereIn('application_id', $appIds);
                    }
                    if ($dateFrom) {
                        $query->whereDate('created_at', '>=', $dateFrom);
                    }
                    if ($dateTo) {
                        $query->whereDate('created_at', '<=', $dateTo);
                    }
                    $count += $query->count();
                }
            }

            $allGroupsTemp[] = (object) [
                'id' => $parentGroup->p_id,
                'name' => $parentGroup->p_title,
                'count' => $count
            ];
        }

        $allGroupsArray = collect($allGroupsTemp)->sortByDesc('count')->values()->toArray();
        $allGroups = collect($allGroupsArray);

        return response()->json([
            'kpis' => [
                'total'    => $totalInProgress,
                'in_house' => $inHouse,
                'vendor'   => $vendor,
                'promo'    => $promo,
            ],
            'crs_per_status' => $crsPerStatus,
            'crs_aging'      => $agingArray,
            'all_groups'     => $allGroups,
        ]);
    }

    /**
     * AJAX: Get filter options (applications, groups, categories, requester departments).
     */
    public function filterOptions()
    {
        $applications = Application::select('id', 'name')->orderBy('name')->get();

        $groups = DB::table('change_request_statuses as crs')
            ->join('groups as g', 'g.id', '=', 'crs.current_group_id')
            ->where('crs.active', '1')
            ->whereNotNull('crs.current_group_id')
            ->select('g.id', 'g.title as name')
            ->groupBy('g.id', 'g.title')
            ->orderBy('g.title')
            ->get();

        return response()->json([
            'applications' => $applications,
            'groups'       => $groups,
        ]);
    }

    /**
     * Helper: Count CRs matching status keywords via active status join.
     */
    private function countByStatusKeyword($workflowTypeId, Request $request, array $keywords): int
    {
        $query = DB::table('change_request_statuses as crs')
            ->join('change_request as cr', 'cr.id', '=', 'crs.cr_id')
            ->when($request->query('chart_key') === 'cTargeted', function ($q) {
                $q->join('applications', function (JoinClause $join) {
                    $join->on('cr.application_id', '=', 'applications.id')
                        ->where('applications.name', 'LIKE', '%'. \request('label') . '%');
                });
            })
            ->when($request->query('chart_key') === 'cCategory', function ($q) {
                $q->join('categories', function (JoinClause $join) {
                    $join->on('cr.category_id', '=', 'categories.id')
                        ->where('categories.name', 'LIKE', '%'. \request('label') . '%');
                });
            })
            ->when($request->query('chart_key') === 'cDepartment', function ($query) {
                $query->join('change_request_custom_fields as cf', function (JoinClause $join)  {
                    $join->on('cf.cr_id', '=', 'cr.id')
                        ->where('cf.custom_field_name','requester_department')
                        ->where('cf.custom_field_name', \request()->query('label'));
                });
            })
            ->join('statuses as s', 's.id', '=', 'crs.new_status_id')
            ->where('crs.active', '1')
            ->when($workflowTypeId, fn($q) => $q->whereIn('cr.workflow_type_id', (array) $workflowTypeId))
            ->whereIn('cr.workflow_type_id', [3,13,5,9])
            ->when($request->get('application_id'), fn($q, $v) => $q->whereIn('cr.application_id', (array)$v))
            ->when($request->get('date_from'), fn($q, $v) => $q->whereDate('cr.created_at', '>=', $v))
            ->when($request->get('date_to'), fn($q, $v) => $q->whereDate('cr.created_at', '<=', $v))
            ->where('cr.hold', 0)
            ->where(function ($q) use ($keywords) {
                foreach ($keywords as $kw) {
                    $q->orWhere('s.status_name', 'like', "%{$kw}%");
                }
            });

        $this->applyActiveStatusLabelFilter($query, $request, 'cr');

        return $query->distinct('crs.cr_id')->count('crs.cr_id');
    }

    /**
     * Apply common filters to an Eloquent CR query.
     */
    private function applyFilters(Builder $query, Request $request)
    {
        return $query
            ->when($request->get('application_id'), fn($q, $value) => $q->whereIn('application_id', (array)$value))
            ->when($request->get('date_from'), fn($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($request->get('date_to'), fn($q, $value) => $q->whereDate('created_at', '<=', $value));
    }
}
