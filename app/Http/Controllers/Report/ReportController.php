<?php

namespace App\Http\Controllers\Report;

use App\Exports\ExportSLAReport;
use App\Exports\KpiReportExport;
use App\Exports\SlaReportExport;
use App\Factories\NewWorkFlow\NewWorkFlowFactory;
use App\Factories\Statuses\StatusFactory;
use App\Factories\Workflow\Workflow_type_factory;
use App\Http\Controllers\Controller;
use App\Models\RequesterDepartment;
use App\Models\Status;
use App\Models\Unit;
use App\Services\ReportService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    private $status;

    private $workflow;

    private $workflow_type;

    public function __construct(

        StatusFactory $status,
        NewWorkFlowFactory $workflow,
        Workflow_type_factory $workflow_type,
    ) {

        $this->status = $status::index();
        $this->changerworkflowequeststatus = $workflow::index();
        $this->workflow_type = $workflow_type::index();

        // $this->shareViewData();
    }
    /**
     * Show Actual vs Planned report page
     */
    /* public function actualVsPlanned(Request $request)
     {
         $query = "
                       WITH designprogress_ranked AS (
     SELECT
         cr_id,
         id,
         created_at,
         updated_at,
         user_id,
         ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
     FROM change_request_statuses
     WHERE new_status_id = 15
),
 pend_design_ranked AS (
     SELECT
         cr_id,
         id,
         created_at,
         updated_at,
         ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
     FROM change_request_statuses
     WHERE new_status_id = 7
),
     pend_implementaion_ranked AS (
     SELECT
         cr_id,
         id,
         created_at,
         updated_at,
         user_id,
         group_id,
         ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
     FROM change_request_statuses
     WHERE new_status_id = 8
),
technical_implementation_ranked AS (
     SELECT
         cr_id,
         id,
         created_at,
         updated_at,
         group_id,
         user_id,
         ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
     FROM change_request_statuses
     WHERE new_status_id = 10
),
     pend_testing_ranked AS (
     SELECT
         cr_id,
         id,
         created_at,
         updated_at,
         user_id,
         group_id,
         ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
     FROM change_request_statuses
     WHERE new_status_id = 11
)

    SELECT
         req.id,
         req.cr_no,
         apps.`name` 'Applications',
         req.title,
         flow.`name` 'CR Type',
         req.start_design_time 'Design Estimation Planned Start',
         req.end_design_time 'Design Estimation Planned End',
         designprogress.created_at AS DesignInProgressActualStart,
         designprogress.updated_at AS DesignInProgressActualEnd,
         TIMESTAMPDIFF(MINUTE,ch_cus_fields.created_at , pendig_disgn_start.created_at ) 'Pending Design Assigned Member Duration IN MINUTES',
         design_team_name.`name` as 'Team Member Name',

         req.start_develop_time 'Technical Estimation Planned Start',
         req.end_develop_time 'Technical Estimation Planned End',

         tetch_implt_start.created_at 'TechnicalImplementationActualStart',
         tetch_implt_start.updated_at 'TechnicalImplementationActualEnd',
         TIMESTAMPDIFF(MINUTE,ch_cus_tech_flds.created_at , pend_implement.created_at ) 'Pending Implementation Assigned Member Duration IN MINUTES',
         group_concat(technical_team.title)  'Technical Team',
         pend_imple_assig_usr.`name` 'Pending Implementation Assigned Member',
         req.start_test_time 'Testing Estimation Planned Start',
         req.end_test_time 'Testing Estimation Planned End',
         pend_test.created_at 'Pending Testing ActualStart',
         pend_test.updated_at 'Pending Testing ActualEnd',

         pend_test_assig_usr.`name` 'Testing Team Member',
         IFNULL(req.end_test_time, req.end_develop_time) as 'Expected Delivery date'  ,
         req.requester_name,
         req.division_manager,
         'Not Found' as 'Requseter division',
         'Not Found' as 'Requester Sector'

     FROM  change_request AS req
     LEFT JOIN applications AS apps ON apps.id = req.application_id
     LEFT JOIN workflow_type AS flow ON flow.id = req.workflow_type_id
     LEFT JOIN change_request_statuses AS curr_status  ON curr_status.cr_id = req.id
     LEFT JOIN statuses AS stat ON stat.id = curr_status.new_status_id

    LEFT JOIN designprogress_ranked designprogress ON designprogress.cr_id = req.id AND designprogress.rn = 1
    LEFT JOIN pend_implementaion_ranked pend_implement ON pend_implement.cr_id = req.id AND pend_implement.rn = 1
    LEFT JOIN pend_testing_ranked pend_test ON pend_test.cr_id = req.id AND pend_test.rn = 1
    LEFT JOIN change_request_custom_fields AS ch_cus_fields ON ch_cus_fields.cr_id = req.id and ch_cus_fields.custom_field_id = 48

    LEFT JOIN pend_design_ranked AS pendig_disgn_start ON pendig_disgn_start.cr_id = req.id  AND pendig_disgn_start.rn = 1
    LEFT JOIN technical_implementation_ranked AS tetch_implt_start ON tetch_implt_start.cr_id = req.id  AND tetch_implt_start.rn = 1
     LEFT JOIN user_groups AS usr_grp ON usr_grp.user_id = tetch_implt_start.user_id
    LEFT JOIN `groups` AS technical_team ON technical_team.id = usr_grp.group_id
    LEFT JOIN change_request_custom_fields AS ch_cus_tech_flds ON ch_cus_tech_flds.cr_id = req.id and ch_cus_tech_flds.custom_field_id = 46
    LEFT JOIN users AS design_team_name ON design_team_name.id = ch_cus_fields.custom_field_value
    LEFT JOIN users AS designprogress_assig_usr ON designprogress_assig_usr.id = designprogress.user_id
    LEFT JOIN users AS pend_imple_assig_usr ON pend_imple_assig_usr.id = pend_implement.user_id
    LEFT JOIN `groups` AS grop ON grop.id = pend_implement.group_id
    LEFT JOIN users AS pend_test_assig_usr ON pend_test_assig_usr.id = pend_test.user_id

     GROUP BY req.cr_no;
                 ";

                 $results = \DB::select($query);
                 $results = collect($results);

                 // Pagination setup
                 $perPage = 10;
                 $page = $request->get('page', 1);

                 // Slice collection for current page
                 $currentPageItems = $results->slice(($page - 1) * $perPage, $perPage)->values();

                 // Create LengthAwarePaginator
                 $paginatedResults = new LengthAwarePaginator(
                     $currentPageItems,
                     $results->count(),
                     $perPage,
                     $page,
                     ['path' => $request->url(), 'query' => $request->query()]
                 );

                 return view('reports.actual_vs_planned', [
                     'results' => $paginatedResults // <-- pass paginator to view
                 ]);
     }*/

    public function actualVsPlanned(Request $request)
    {
        $ticket_type = $request->input('ticket_type');
        $top_management = $request->input('top_management');
        $on_hold = $request->input('on_hold');
        $on_behalf = $request->input('on_behalf');

        $query = DB::table('change_request as req')
            ->leftJoin('applications as apps', 'apps.id', '=', 'req.application_id')
            ->leftJoin('workflow_type as flow', 'flow.id', '=', 'req.workflow_type_id')
            ->leftJoin('change_request_statuses as curr_status', 'curr_status.cr_id', '=', 'req.id')
            ->leftJoin('statuses as stat', 'stat.id', '=', 'curr_status.new_status_id')

            // Join CTE-equivalent tables using DB::raw and subqueries
            ->leftJoin(DB::raw('(SELECT * FROM (
                    SELECT *,
                        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
                    FROM change_request_statuses
                    WHERE new_status_id = 15
                ) AS x WHERE rn = 1) AS designprogress'), 'designprogress.cr_id', '=', 'req.id')

            ->leftJoin(DB::raw('(SELECT * FROM (
                    SELECT *,
                        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
                    FROM change_request_statuses
                    WHERE new_status_id = 7
                ) AS x WHERE rn = 1) AS pendig_disgn_start'), 'pendig_disgn_start.cr_id', '=', 'req.id')

            ->leftJoin(DB::raw('(SELECT * FROM (
                    SELECT *,
                        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
                    FROM change_request_statuses
                    WHERE new_status_id = 8
                ) AS x WHERE rn = 1) AS pend_implement'), 'pend_implement.cr_id', '=', 'req.id')

            ->leftJoin(DB::raw('(SELECT * FROM (
                    SELECT *,
                        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
                    FROM change_request_statuses
                    WHERE new_status_id = 10
                ) AS x WHERE rn = 1) AS tetch_implt_start'), 'tetch_implt_start.cr_id', '=', 'req.id')

            ->leftJoin(DB::raw('(SELECT * FROM (
                    SELECT *,
                        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
                    FROM change_request_statuses
                    WHERE new_status_id = 11
                ) AS x WHERE rn = 1) AS pend_test'), 'pend_test.cr_id', '=', 'req.id')

            ->leftJoin('change_request_custom_fields as ch_cus_fields', function ($join) {
                $join->on('ch_cus_fields.cr_id', '=', 'req.id')
                    ->where('ch_cus_fields.custom_field_id', '=', 48);
            })

            ->leftJoin('change_request_custom_fields as ch_cus_tech_flds', function ($join) {
                $join->on('ch_cus_tech_flds.cr_id', '=', 'req.id')
                    ->where('ch_cus_tech_flds.custom_field_id', '=', 46);
            })
            ->leftJoin('change_request_custom_fields as on_behalf', function ($join) {
                $join->on('on_behalf.cr_id', '=', 'req.id')
                    ->where('on_behalf.custom_field_name', '=', 'on_behalf');
            })
            ->leftJoin('users as design_team_name', 'design_team_name.id', '=', 'ch_cus_fields.custom_field_value')
            ->leftJoin('users as pend_imple_assig_usr', 'pend_imple_assig_usr.id', '=', 'pend_implement.user_id')
            ->leftJoin('users as pend_test_assig_usr', 'pend_test_assig_usr.id', '=', 'pend_test.user_id')
            ->leftJoin('user_groups as usr_grp', 'usr_grp.user_id', '=', 'tetch_implt_start.user_id')
            ->leftJoin('groups as technical_team', 'technical_team.id', '=', 'usr_grp.group_id')
            ->leftJoin('change_request_custom_fields as dpnd_on', function ($join) {
                $join->on('dpnd_on.cr_id', '=', 'req.id')
                    ->where('dpnd_on.custom_field_name', '=', 'cr_type');
            })
            ->leftJoin('change_request_custom_fields as rlvvnt', function ($join) {
                $join->on('rlvvnt.cr_id', '=', 'req.id')
                    ->where('rlvvnt.custom_field_name', '=', 'cr_type');
            })

            ->selectRaw("

                req.cr_no,
                apps.name AS `Applications`,
                req.title,
                flow.name AS `Workflow Type`,
                CASE
                    WHEN on_behalf.custom_field_value = '1' THEN 'YES'
                    WHEN on_behalf.custom_field_value = '0' THEN 'N/A'
                END AS 'On Behalf',
                CASE
                    WHEN req.hold = '0' THEN 'N/A'
                    WHEN req.hold = '1' THEN 'YES'
                END AS 'On Hold',
                CASE
                    WHEN req.top_management = '0' THEN 'N/A'
                    WHEN req.top_management = '1' THEN 'YES'
                END AS 'Top Management',
                CASE
                WHEN dpnd_on.custom_field_value = '1' THEN 'Normal'
                WHEN dpnd_on.custom_field_value = '2' THEN 'Depend On'
                WHEN dpnd_on.custom_field_value = '3' THEN 'Relevant'
                ELSE 'N/A'
                END AS 'Ticket Type',
                req.start_design_time AS `Design Estimation Planned Start`,
                req.end_design_time AS `Design Estimation Planned End`,
                designprogress.created_at AS DesignInProgressActualStart,
                designprogress.updated_at AS DesignInProgressActualEnd,
                TIMESTAMPDIFF(MINUTE, ch_cus_fields.created_at, pendig_disgn_start.created_at) AS `Pending Design Assigned Member Duration IN MINUTES`,
                design_team_name.name AS `Team Member Name`,
                req.start_develop_time AS `Technical Estimation Planned Start`,
                req.end_develop_time AS `Technical Estimation Planned End`,
                tetch_implt_start.created_at AS `TechnicalImplementationActualStart`,
                tetch_implt_start.updated_at AS `TechnicalImplementationActualEnd`,
                TIMESTAMPDIFF(MINUTE, ch_cus_tech_flds.created_at, pend_implement.created_at) AS `Pending Implementation Assigned Member Duration IN MINUTES`,
                GROUP_CONCAT(technical_team.title) AS `Technical Team`,
                pend_imple_assig_usr.name AS `Pending Implementation Assigned Member`,
                req.start_test_time AS `Testing Estimation Planned Start`,
                req.end_test_time AS `Testing Estimation Planned End`,
                pend_test.created_at AS `Pending Testing ActualStart`,
                pend_test.updated_at AS `Pending Testing ActualEnd`,
                pend_test_assig_usr.name AS `Testing Team Member`,
                IFNULL(req.end_test_time, req.end_develop_time) AS `Expected Delivery date`,
                req.requester_name,
                req.division_manager,
                'Not Found' AS `Requseter division`,
                'Not Found' AS `Requester Sector`
            ");

        if ($top_management) {
            $query->where('req.top_management', $top_management);
        }

        if ($on_hold) {
            $query->where('req.hold', $on_hold);
        }

        if ($on_behalf) {
            $query->where('on_behalf.custom_field_value', $on_behalf);
        }

        if ($ticket_type) {
            $query->where('dpnd_on.custom_field_value', $ticket_type);
        }

        $query->groupBy('req.cr_no');

        // handle export
        if ($request->has('export')) {
            return Excel::download(new ActualVsPlannedReportExport($query->get()), 'actual_vs_planned.xlsx');
        }

        // paginate
        $results = $query->paginate(10);

        return view('reports.actual_vs_planned', compact('results'));
    }

    /**
     * Show All CRs By Requester report page
     */
    public function allCrsByRequester(Request $request)
    {
        $ticket_type = $request->input('ticket_type');
        $top_management = $request->input('top_management');
        $on_hold = $request->input('on_hold');
        $on_behalf = $request->input('on_behalf');

        $query = "
                       WITH pend_design_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        user_id,

        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 7
),
    pend_implementaion_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        user_id,
        group_id,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 8
),
    pend_testing_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        user_id,
        group_id,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 11
),
--     review_estimate_ranked AS (
--     SELECT
--         cr_id,
--         id,
--         created_at,
--         updated_at,
--         ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
--     FROM change_request_statuses
--     WHERE new_status_id = 70
-- ),
    busins_val_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 18
),
    sanity_check_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 21
),
    delivred_cr_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 27
)


   SELECT

        req.cr_no,
        apps.`name` 'Applications',
        req.title,
        flow.`name` 'Workflow Type',
        CASE
        WHEN on_behalf.custom_field_value = '0' THEN 'N/A'
        WHEN on_behalf.custom_field_value = '1' THEN 'YES'
        ELSE 'N/A'
    END AS 'On Behalf',
        CASE
        WHEN req.hold = '0' THEN 'N/A'
        WHEN req.hold = '1' THEN 'YES'
        END AS 'On Hold',
        CASE
            WHEN req.top_management = '0' THEN 'N/A'
            WHEN req.top_management = '1' THEN 'YES'
        END AS 'Top Management',
        CASE
        WHEN dpnd_on.custom_field_value = '1' THEN 'Normal'
        WHEN dpnd_on.custom_field_value = '2' THEN 'Depend On'
        WHEN dpnd_on.custom_field_value = '3' THEN 'Relevant'
        ELSE 'N/A'
        END AS 'Ticket Type',
        'Not Found' as 'CR Type',
        'NA' as 'Vendor Name',

         GROUP_CONCAT(DISTINCT stat.status_name ORDER BY stat.status_name SEPARATOR ', ') AS 'Current Status',
--        review_estimate.created_at 'Review And Estimation Start',
--        review_estimate.updated_at 'Review And Estimation End',
        busins_val.created_at 'Business Validation Status Start Date',
        busins_val.updated_at 'Business Validation Status End Date',
    --    'Not Found' as 'CAB Review Start',
    --    'Not Found'as 'CAB Review End',
        'Not Found' as 'Design Assigned Member Level',

        req.start_design_time 'Pending Design Planned Start',
        req.end_design_time 'Pending Design Planned End',
        pend_design.created_at AS PendingDesignActualStart,
        pend_design.updated_at AS PendingDesignActualEnd,
        pend_design_assig_usr.`name` 'Pending Design Assigned Member',

        req.start_develop_time 'Technical Estimation Start Date',
        req.end_develop_time 'Technical Estimation End Date',
        pend_implement.created_at 'PendingImplementationActualStart',
        pend_implement.updated_at 'PendingImplementationActualEnd',
        pend_imple_assig_usr.`name` 'Developer Name',
        grop.title 'Technical Team',
        TIMESTAMPDIFF(MINUTE,ch_cus_fields.created_at , pend_implement.created_at ) 'Pending Design Assigned Member Duration IN MINUTES',
        'Not Found' as 'Dev Assigned Member Level',

        req.start_test_time 'Testing Estimation Start Date',
        req.end_test_time 'Testing Estimation End Date',
        pend_test.created_at 'Pending Testing Start',
        pend_test.updated_at 'Pending Testing End',
        TIMESTAMPDIFF(MINUTE,ch_cus_fields_tst.created_at , pend_test.created_at ) 'Pending Design Assigned Member Duration IN MINUTES',
        pend_test_assig_usr.`name` 'Testing Team Member',
        'Not Found' as 'Testing Assigned Member Level',

        chang_stat_pend_prod_deploy.created_at 'Pending Production Deployment Start Date',
        chang_stat_pend_prod_deploy.updated_at 'Pending Production Deployment End Date',
        sanity_check.created_at 'Sanity Check Start',
        sanity_check.updated_at 'Sanity Check End',
        chang_stat_pend_busen_fedbk.created_at 'Pending Business Feedback Start Date',
        chang_stat_pend_busen_fedbk.updated_at 'Pending Business Feedback End Date',
        chang_stat_busen_tst_cas_appval.created_at 'Business Test Case Approval Start Date',
        chang_stat_busen_tst_cas_appval.updated_at 'Business Test Case Approval End Date',
        chang_stat_busen_uat_sign_off.created_at 'Business UAT Sign Off Start Date',
        chang_stat_busen_uat_sign_off.updated_at 'Business UAT Sign Off End Date',
        delivred_cr.created_at 'Delivered CR Start',
        delivred_cr.updated_at 'Delivered CR Start',

  --      'Not Found' as 'Deployment on production',
        IFNULL(req.end_test_time, req.end_develop_time) as 'Expected Delivery date',
        req.requester_name,
        req.division_manager,
        'Not Found' as 'Requseter division',
        'Not Found' as 'Requester Sector',
         rejt_reason.name 'Rejection Reasons'

    FROM  change_request AS req
    LEFT JOIN applications AS apps ON apps.id = req.application_id
    LEFT JOIN workflow_type AS flow ON flow.id = req.workflow_type_id
    -- LEFT JOIN change_request_statuses AS curr_status  ON curr_status.cr_id = req.id
    -- LEFT JOIN statuses AS stat ON stat.id = curr_status.new_status_id

    LEFT JOIN change_request_statuses AS curr_status ON curr_status.cr_id = req.id AND curr_status.`active` = '1'
    LEFT JOIN statuses AS stat ON stat.id = curr_status.new_status_id

    LEFT JOIN pend_design_ranked pend_design ON pend_design.cr_id = req.id AND pend_design.rn = 1
    LEFT JOIN pend_implementaion_ranked pend_implement ON pend_implement.cr_id = req.id AND pend_implement.rn = 1
    LEFT JOIN pend_testing_ranked pend_test ON pend_test.cr_id = req.id AND pend_test.rn = 1
 --   LEFT JOIN review_estimate_ranked review_estimate ON review_estimate.cr_id = req.id AND review_estimate.rn = 1
    LEFT JOIN busins_val_ranked busins_val ON busins_val.cr_id = req.id AND busins_val.rn = 1
    LEFT JOIN sanity_check_ranked sanity_check ON sanity_check.cr_id = req.id AND sanity_check.rn = 1
    LEFT JOIN delivred_cr_ranked delivred_cr ON delivred_cr.cr_id = req.id AND delivred_cr.rn = 1

  --  LEFT JOIN pend_design_ranked AS pendig_disgn_start ON pendig_disgn_start.cr_id = req.id  AND pendig_disgn_start.rn = 1
    LEFT JOIN change_request_custom_fields AS ch_cus_fields ON ch_cus_fields.cr_id = req.id and ch_cus_fields.custom_field_id = 46
    LEFT JOIN change_request_custom_fields AS ch_cus_fields_tst ON ch_cus_fields_tst.cr_id = req.id and ch_cus_fields_tst.custom_field_id = 47

    LEFT JOIN change_request_statuses as chang_stat_pend_prod_deploy ON  chang_stat_pend_prod_deploy.cr_id = req.id and  chang_stat_pend_prod_deploy.new_status_id = 17
    LEFT JOIN change_request_statuses as chang_stat_pend_desin ON  chang_stat_pend_desin.cr_id = req.id and  chang_stat_pend_desin.new_status_id = 7
    LEFT JOIN change_request_statuses as chang_stat_pend_busen_fedbk ON  chang_stat_pend_busen_fedbk.cr_id = req.id and  chang_stat_pend_busen_fedbk.new_status_id = 79
    LEFT JOIN change_request_statuses as chang_stat_busen_tst_cas_appval ON  chang_stat_busen_tst_cas_appval.cr_id = req.id and  chang_stat_busen_tst_cas_appval.new_status_id = 41
    LEFT JOIN change_request_statuses as chang_stat_busen_uat_sign_off ON  chang_stat_busen_uat_sign_off.cr_id = req.id and  chang_stat_busen_uat_sign_off.new_status_id = 44

   LEFT JOIN change_request_custom_fields AS dpnd_on  ON dpnd_on.cr_id = req.id AND dpnd_on.custom_field_name = 'cr_type'
    LEFT JOIN change_request_custom_fields AS rlvvnt  ON rlvvnt.cr_id = req.id AND rlvvnt.custom_field_name = 'cr_type'


    LEFT JOIN users AS pend_design_assig_usr ON pend_design_assig_usr.id = pend_design.user_id
    LEFT JOIN users AS pend_imple_assig_usr ON pend_imple_assig_usr.id = pend_implement.user_id
    LEFT JOIN `groups` AS grop ON grop.id = pend_implement.group_id
    LEFT JOIN users AS pend_test_assig_usr ON pend_test_assig_usr.id = pend_test.user_id
    LEFT JOIN change_request_custom_fields as chang_custm_rejt_reason ON  chang_custm_rejt_reason.cr_id = req.id and  chang_custm_rejt_reason.custom_field_id = 63
    LEFT JOIN rejection_reasons as rejt_reason ON  rejt_reason.id = chang_custm_rejt_reason.custom_field_value
       LEFT JOIN change_request_custom_fields AS on_behalf  ON on_behalf.cr_id = req.id AND on_behalf.custom_field_name = 'on_behalf'

    WHERE 1=1
   ";

        $bindings = [];

        if ($top_management) {
            $query .= ' AND req.top_management = ?';
            $bindings[] = $top_management;
        }

        if ($on_hold) {
            $query .= ' AND req.hold = ?';
            $bindings[] = $on_hold;
        }

        if ($on_behalf) {
            $query .= ' AND on_behalf.custom_field_value = ?';
            $bindings[] = $on_behalf;
        }

        if ($ticket_type) {
            $query .= ' AND dpnd_on.custom_field_value = ?';
            $bindings[] = $ticket_type;
        }

        $query .= ' GROUP BY req.cr_no';

        $results = DB::select($query, $bindings);
        $results = collect($results);

        // Pagination setup
        $perPage = 10;
        $page = $request->get('page', 1);

        // Slice collection for current page
        $currentPageItems = $results->slice(($page - 1) * $perPage, $perPage)->values();

        // Create LengthAwarePaginator
        $paginatedResults = new LengthAwarePaginator(
            $currentPageItems,
            $results->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('reports.all_crs_by_requester', [
            'results' => $paginatedResults, // <-- pass paginator to view
        ]);

    }

    public function exportAllCrsByRequester()
    {
        return Excel::download(new AllCrsByRequesterExport, 'all_crs_by_requester.xlsx');
    }

    /**
     * Show CR Current Status report page
     */
    //   public function crCurrentStatus(Request $request)
    // {
    //      /* ---------------------------------------------------------
    //         1) READ FILTERS FROM FORM
    //     --------------------------------------------------------- */
    //     $cr_type = $request->input('cr_type');                     // single value
    //     $status_ids = $request->input('status_ids');               // array
    //     $cr_nos = $request->input('cr_nos');                       // optional text field: "1001,1002"

    //     //dd(intval($cr_type));
    //     // Convert arrays to comma-separated strings
    //     $status_ids_str = !empty($status_ids) ? implode(",", $status_ids) : null;

    //     /* ---------------------------------------------------------
    //         2) SET MYSQL USER VARIABLES TO PASS INTO THE QUERY
    //     --------------------------------------------------------- */
    //     DB::statement("SET @cr_type := " . ($cr_type ? $cr_type : "NULL"));
    //     DB::statement("SET @status_ids := " . ($status_ids_str ? "'" . $status_ids_str . "'" : "NULL"));
    //     DB::statement("SET @cr_nos := " . ($cr_nos ? "'" . $cr_nos . "'" : "NULL"));

    //     // Main SELECT query (cleaned & corrected)
    //     $query = "
    //         SELECT
    //             req.cr_no,
    //             apps.name AS `Applications`,
    //             req.title,
    //             flow.name AS `Workflow Type`,
    //             'NA' AS `Vendor Name`,
    //             GROUP_CONCAT(DISTINCT stat.status_name ORDER BY stat.status_name SEPARATOR ', ') AS `Current Status`,
    //             CONCAT(sla.unit_sla_time, ' ', sla.sla_type_unit) AS `Assigned SLA`,
    //             req.start_design_time AS `Design Estimation Start`,
    //             req.end_design_time AS `Design Estimation End`,
    //             req.start_develop_time AS `Technical Estimation Start`,
    //             req.end_develop_time AS `Technical Estimation End`,
    //             unt.name AS `Unit Name`,
    //             req.start_test_time AS `Testing Estimation Start`,
    //             req.end_test_time AS `Testing Estimation End`,
    //             grou.title AS `Current Assigned Group`,
    //             usr.user_name AS `Assigned Member`,
    //             'Not Found' AS `Assigned Member Level`,
    //             IFNULL(req.end_test_time, req.end_develop_time) AS `Expected Delivery date`,
    //             req.requester_name,
    //             req.division_manager
    //         FROM change_request AS req
    //         LEFT JOIN applications AS apps
    //             ON apps.id = req.application_id
    //         LEFT JOIN workflow_type AS flow
    //             ON flow.id = req.workflow_type_id
    //         LEFT JOIN change_request_statuses AS curr_status
    //             ON curr_status.cr_id = req.id
    //             AND curr_status.active = '1'
    //         LEFT JOIN statuses AS stat
    //             ON stat.id = curr_status.new_status_id
    //         LEFT JOIN group_statuses AS gro_stat
    //             ON gro_stat.status_id = curr_status.new_status_id
    //         LEFT JOIN `groups` AS grou
    //             ON grou.id = gro_stat.group_id
    //         LEFT JOIN group_applications AS grou_apps
    //             ON grou_apps.application_id = req.application_id
    //         LEFT JOIN `groups` AS grou_unit
    //             ON grou_unit.id = grou_apps.group_id
    //         LEFT JOIN units AS unt
    //             ON unt.id = grou_unit.unit_id
    //         LEFT JOIN sla_calculations AS sla
    //             ON sla.status_id = curr_status.new_status_id
    //         LEFT JOIN change_request_custom_fields AS custom_field_chang
    //             ON custom_field_chang.cr_id = req.id
    //             AND custom_field_chang.custom_field_id = 67
    //         LEFT JOIN users AS usr
    //             ON usr.id = custom_field_chang.custom_field_value
    //         LEFT JOIN roles
    //             ON roles.id = usr.role_id
    //         WHERE
    //             (@cr_type IS NULL OR req.workflow_type_id = @cr_type)
    //             AND (@status_ids IS NULL OR FIND_IN_SET(curr_status.new_status_id, @status_ids))
    //             AND (@cr_nos IS NULL OR FIND_IN_SET(req.cr_no, @cr_nos))
    //         GROUP BY req.cr_no
    //     ";

    //     // Execute query
    //     $results = collect(DB::select($query));

    //     // Pagination
    //     $perPage = 10;
    //     $page = $request->get('page', 1);

    //     $currentPageItems = $results->slice(($page - 1) * $perPage, $perPage)->values();

    //     $paginatedResults = new LengthAwarePaginator(
    //         $currentPageItems,
    //         $results->count(),
    //         $perPage,
    //         $page,
    //         [
    //             'path' => $request->url(),
    //             'query' => $request->query()
    //         ]
    //     );
    //     $workflow_types =$this->workflow_type->get_workflow_all_subtype();
    //     $status =$this->status->getAll();

    //     return view('reports.cr_current_status', [
    //         'results' => $paginatedResults,
    //         'status' => $status,
    //         'workflow_type' => $workflow_types
    //     ]);
    // }

    public function crCurrentStatus(Request $request)
    {
        // 1) Read filters from form (optional)
        $cr_type = $request->input('cr_type');                // single value
        $status_ids = $request->input('status_ids', []);      // array
        $cr_nos = $request->input('cr_nos');                 // optional text field "CR001,CR002"

        $ticket_type = $request->input('ticket_type');
        $top_management = $request->input('top_management');
        $on_hold = $request->input('on_hold');
        $on_behalf = $request->input('on_behalf');

        // 2) Build query dynamically
        $query = DB::table('change_request as req')
            ->leftJoin('applications as apps', 'apps.id', '=', 'req.application_id')
            ->leftJoin('workflow_type as flow', 'flow.id', '=', 'req.workflow_type_id')
            ->leftJoin('change_request_statuses as curr_status', function ($join) {
                $join->on('curr_status.cr_id', '=', 'req.id')
                    ->where('curr_status.active', 1);
            })
            ->leftJoin('statuses as stat', 'stat.id', '=', 'curr_status.new_status_id')
            ->leftJoin('group_statuses as gro_stat', 'gro_stat.status_id', '=', 'curr_status.new_status_id')
            ->leftJoin('groups as grou', 'grou.id', '=', 'gro_stat.group_id')
            ->leftJoin('group_applications as grou_apps', 'grou_apps.application_id', '=', 'req.application_id')
            ->leftJoin('groups as grou_unit', 'grou_unit.id', '=', 'grou_apps.group_id')
            ->leftJoin('units as unt', 'unt.id', '=', 'grou_unit.unit_id')
            ->leftJoin('sla_calculations as sla', 'sla.status_id', '=', 'curr_status.new_status_id')
            ->leftJoin('change_request_custom_fields as custom_field_chang', function ($join) {
                $join->on('custom_field_chang.cr_id', '=', 'req.id')
                    ->where('custom_field_chang.custom_field_id', 67);
            })
            ->leftJoin('change_request_custom_fields as on_behalf', function ($join) {
                $join->on('on_behalf.cr_id', '=', 'req.id')
                    ->where('on_behalf.custom_field_name', '=', 'on_behalf');
            })
            ->leftJoin('users as usr', 'usr.id', '=', 'custom_field_chang.custom_field_value')
            ->leftJoin('roles', 'roles.id', '=', 'usr.role_id')
            ->leftJoin('change_request_custom_fields as dpnd_on', function ($join) {
                $join->on('dpnd_on.cr_id', '=', 'req.id')
                    ->where('dpnd_on.custom_field_name', '=', 'cr_type');
            })
            ->leftJoin('change_request_custom_fields as rlvvnt', function ($join) {
                $join->on('rlvvnt.cr_id', '=', 'req.id')
                    ->where('rlvvnt.custom_field_name', '=', 'cr_type');
            })
            ->select(
                'req.cr_no',
                'apps.name as Applications',
                'req.title',
                'flow.name as Workflow_Type',
                DB::raw("
                    CASE
                        WHEN on_behalf.custom_field_value = '0' THEN 'N/A'
                        WHEN on_behalf.custom_field_value = '1' THEN 'YES'
                        ELSE 'N/A'
                    END AS 'On Behalf'
                "),
                DB::raw("
                    CASE
                        WHEN req.hold = '0' THEN 'N/A'
                        WHEN req.hold = '1' THEN 'YES'
                    END AS 'On Hold'
                "),
                DB::raw("
                    CASE
                        WHEN req.top_management = '0' THEN 'N/A'
                        WHEN req.top_management = '1' THEN 'YES'
                    END AS 'Top Management'
                "),
                DB::raw("CASE
                    WHEN dpnd_on.custom_field_value = '1' THEN 'Normal'
                    WHEN dpnd_on.custom_field_value = '2' THEN 'Depend On'
                    WHEN dpnd_on.custom_field_value = '3' THEN 'Relevant'
                    ELSE 'N/A'
                END AS Ticket_Type"),
                DB::raw("'NA' as Vendor_Name"),
                DB::raw("GROUP_CONCAT(DISTINCT stat.status_name ORDER BY stat.status_name SEPARATOR ', ') as Current_Status"),
                DB::raw("CONCAT(sla.unit_sla_time, ' ', sla.sla_type_unit) as Assigned_SLA"),
                'req.start_design_time as Design_Estimation_Start',
                'req.end_design_time as Design_Estimation_End',
                'req.start_develop_time as Technical_Estimation_Start',
                'req.end_develop_time as Technical_Estimation_End',
                'unt.name as Unit_Name',
                'req.start_test_time as Testing_Estimation_Start',
                'req.end_test_time as Testing_Estimation_End',
                'grou.title as Current_Assigned_Group',
                'usr.user_name as Assigned_Member',
                DB::raw("'Not Found' as Assigned_Member_Level"),
                DB::raw('IFNULL(req.end_test_time, req.end_develop_time) as Expected_Delivery_date'),
                'req.requester_name',
                'req.division_manager'
            )
            ->groupBy('req.cr_no');

        // 3) Apply filters if present
        if ($cr_type) {
            $query->where('req.workflow_type_id', $cr_type);
        }

        if (! empty($status_ids)) {
            $query->whereIn('curr_status.new_status_id', $status_ids);
        }

        if ($on_behalf) {
            $query->where('on_behalf.custom_field_value', $on_behalf);
        }

        if ($cr_nos) {
            $cr_nos_array = array_map('trim', explode(',', $cr_nos));
            $query->whereIn('req.cr_no', $cr_nos_array);
        }

        if ($top_management) {
            $query->where('req.top_management', $top_management);
        }

        if ($on_hold) {
            $query->where('req.hold', $on_hold);
        }
        if ($ticket_type) {
            $query->where('dpnd_on.custom_field_value', $ticket_type);
        }

        $results = collect($query->get());

        // 4) Pagination
        $perPage = 10;
        $page = $request->input('page', 1);
        $currentPageItems = $results->slice(($page - 1) * $perPage, $perPage)->values();
        $paginatedResults = new LengthAwarePaginator(
            $currentPageItems,
            $results->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // 5) Get filter options
        $workflow_types = $this->workflow_type->get_workflow_all_subtype();
        $status = $this->status->getAll();

        return view('reports.cr_current_status', [
            'results' => $paginatedResults,
            'workflow_type' => $workflow_types,
            'status' => $status,
        ]);
    }

    /**
     * Show CR Crossed SLA report page
     */
    public function crCrossedSla(Request $request)
    {
        $ticket_type = $request->input('ticket_type');
        $top_management = $request->input('top_management');
        $on_hold = $request->input('on_hold');
        $on_behalf = $request->input('on_behalf');

        $bindings = [];

        $query = "
                         WITH pend_design_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        user_id,

        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 7
),
    pend_implementaion_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        user_id,
        group_id,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 8
),
    pend_testing_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        user_id,
        group_id,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 11
),
 technical_implem_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 10
),
design_progrs_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 15
),
test_progrs_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 13
)

   SELECT

        req.cr_no,
        apps.`name` 'Applications',
        req.title,
        flow.`name` 'Workflow Type',
        CASE
        WHEN on_behalf.custom_field_value = '0' THEN 'N/A'
        WHEN on_behalf.custom_field_value = '1' THEN 'YES'
        ELSE 'N/A'
            END AS 'On Behalf',
        CASE
        WHEN req.hold = '0' THEN 'N/A'
        WHEN req.hold = '1' THEN 'YES'
        END AS 'On Hold',
        CASE
        WHEN req.top_management = '0' THEN 'N/A'
        WHEN req.top_management = '1' THEN 'YES'
        END AS 'Top Management',
        CASE
        WHEN dpnd_on.custom_field_value = '1' THEN 'Normal'
        WHEN dpnd_on.custom_field_value = '2' THEN 'Depend On'
        WHEN dpnd_on.custom_field_value = '3' THEN 'Relevant'
        ELSE 'N/A'
        END AS 'Ticket Type',
        req.start_design_time 'Pending Design Planned Start',
        req.end_design_time 'Pending Design Planned End',
        pend_design.created_at AS PendingDesignActualStart,
        pend_design.updated_at AS PendingDesignActualEnd,
        pend_design_assig_usr.`name` 'Pending Design Assigned Member',
        req.start_develop_time 'Pending Implementation Planned Start',
        req.end_develop_time 'Pending Implementation Planned End',
        pend_implement.created_at 'PendingImplementationActualStart',
        pend_implement.updated_at 'PendingImplementationActualEnd',
        pend_imple_assig_usr.`name` 'Pending Implementation Assigned Member',
        grop.title 'Assigned Group',
        req.start_test_time 'Pending Testing Planned Start',
        req.end_test_time 'Pending Testing Planned End',
        pend_test.created_at 'Pending Testing ActualStart',
        pend_test.updated_at 'Pending Testing ActualEnd',
        pend_test_assig_usr.`name` 'Pending Testing Assigned Member',
        'Not Found' as 'CR Planned Delivery date'  ,
         IFNULL(req.end_test_time, req.end_develop_time) as 'Expected Delivery date',
        req.requester_name,
        req.division_manager,
        'Not Found' as 'Requseter division',
        'Not Found' as 'Requester Sector'

    FROM  change_request AS req
    LEFT JOIN applications AS apps ON apps.id = req.application_id
    LEFT JOIN workflow_type AS flow ON flow.id = req.workflow_type_id
    LEFT JOIN change_request_statuses AS curr_status  ON curr_status.cr_id = req.id
    LEFT JOIN statuses AS stat ON stat.id = curr_status.new_status_id

   LEFT JOIN pend_design_ranked pend_design ON pend_design.cr_id = req.id AND pend_design.rn = 1
   LEFT JOIN pend_implementaion_ranked pend_implement ON pend_implement.cr_id = req.id AND pend_implement.rn = 1
   LEFT JOIN pend_testing_ranked pend_test ON pend_test.cr_id = req.id AND pend_test.rn = 1
   LEFT JOIN technical_implem_ranked tech_dev ON tech_dev.cr_id = req.id AND tech_dev.rn = 1
   LEFT JOIN design_progrs_ranked des_progs ON des_progs.cr_id = req.id AND des_progs.rn = 1
   LEFT JOIN test_progrs_ranked test_progs ON test_progs.cr_id = req.id AND test_progs.rn = 1



   LEFT JOIN users AS pend_design_assig_usr ON pend_design_assig_usr.id = pend_design.user_id
   LEFT JOIN users AS pend_imple_assig_usr ON pend_imple_assig_usr.id = pend_implement.user_id
   LEFT JOIN `groups` AS grop ON grop.id = pend_implement.group_id
   LEFT JOIN users AS pend_test_assig_usr ON pend_test_assig_usr.id = pend_test.user_id

     LEFT JOIN change_request_custom_fields AS dpnd_on  ON dpnd_on.cr_id = req.id AND dpnd_on.custom_field_name = 'cr_type'
    LEFT JOIN change_request_custom_fields AS rlvvnt  ON rlvvnt.cr_id = req.id AND rlvvnt.custom_field_name = 'cr_type'

    LEFT JOIN change_request_custom_fields AS on_behalf  ON on_behalf.cr_id = req.id AND on_behalf.custom_field_name = 'on_behalf'

   where
	 -- Design mismatch
    (req.start_design_time < des_progs.created_at
    OR req.end_design_time < des_progs.updated_at

    -- Implementation mismatch
    OR req.start_develop_time < tech_dev.created_at
    OR req.end_develop_time < tech_dev.updated_at

    -- Testing mismatch
    OR req.start_test_time < test_progs.created_at
    OR req.end_test_time < test_progs.updated_at)
    ";

        // start new filter
        if ($top_management) {
            $query .= ' AND req.top_management = ?';
            $bindings[] = $top_management;
        }

        if ($on_hold) {
            $query .= ' AND req.hold = ?';
            $bindings[] = $on_hold;
        }

        if ($on_behalf) {
            $query .= ' AND on_behalf.custom_field_value = ?';
            $bindings[] = $on_behalf;
        }

        if ($ticket_type) {
            $query .= ' AND dpnd_on.custom_field_value = ?';
            $bindings[] = $ticket_type;
        }
        // end new filter

        $query .= ' GROUP BY req.cr_no';

        $results = DB::select($query, $bindings);
        $results = collect($results);

        // Pagination setup
        $perPage = 10;
        $page = $request->get('page', 1);

        // Slice collection for current page
        $currentPageItems = $results->slice(($page - 1) * $perPage, $perPage)->values();

        // Create LengthAwarePaginator
        $paginatedResults = new LengthAwarePaginator(
            $currentPageItems,
            $results->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('reports.cr_crossed_sla', [
            'results' => $paginatedResults, // <-- pass paginator to view
        ]);

    }

    public function exportCrsCrossedSla()
    {
        return Excel::download(new CRsCrossedSLAExport, 'CRsCrossedSLA.xlsx');
    }

    /**
     * Show Rejected CRs report page
     */
    public function rejectedCrs(Request $request)
    {
        $ticket_type = $request->input('ticket_type');
        $top_management = $request->input('top_management');
        $on_hold = $request->input('on_hold');
        $on_behalf = $request->input('on_behalf');

        $bindings = [];

        $query = "
            SELECT
        req.cr_no,

        apps.`name` 'Applications',
        req.title,
        flow.`name` 'Workflow Type',
        CASE
        WHEN on_behalf.custom_field_value = '0' THEN 'N/A'
        WHEN on_behalf.custom_field_value = '1' THEN 'YES'
        ELSE 'N/A'
             END AS 'On Behalf',
        CASE
        WHEN req.hold = '0' THEN 'N/A'
        WHEN req.hold = '1' THEN 'YES'
        END AS 'On Hold',
        CASE
        WHEN req.top_management = '0' THEN 'N/A'
        WHEN req.top_management = '1' THEN 'YES'
        END AS 'Top Management',
        CASE
        WHEN dpnd_on.custom_field_value = '1' THEN 'Normal'
        WHEN dpnd_on.custom_field_value = '2' THEN 'Depend On'
        WHEN dpnd_on.custom_field_value = '3' THEN 'Relevant'
        ELSE 'N/A'
        END AS 'Ticket Type',
   --     chang_stat_reject.created_at 'Review and estimation start date' ,
    --    chang_stat_reject.updated_at 'Review and estimation start date' ,
     --   chang_stat_analysis.created_at 'Analysis start date',
    --     chang_stat_analysis.updated_at 'Analysis start date',
        chang_stat_busin_valid.created_at 'Business Validation Status Start Date',
        chang_stat_busin_valid.updated_at 'Business Validation Status End Date',
        chang_stat_pend_cab.created_at 'Pending CAB status Start Date',
        chang_stat_pend_cab.updated_at 'Pending CAB status End Date',
        chang_stat_designin_prog.created_at 'Design in progress Start date',
        chang_stat_designin_prog.updated_at 'Design in progress End date',
        usr_design.user_name 'Assigned Design Team Member',
        'Not Found'  as 'Team Member Level',
  --      chang_stat_pend_desin.created_at 'pending design Actual Start date',
    --    chang_stat_pend_desin.updated_at 'pending design Actual End date',
        chang_stat_pend_implememt.created_at 'Technical Implementation Start Date',
        chang_stat_pend_implememt.updated_at 'Technical Implementation End Date',
        chang_stat_pend_implememt.group_id 'Assigned Group',
        usr_dev.user_name 'Assigned Dev User',
        'Not Found' as  'Assigned  Dev User Level',
        chang_stat_pend_test.created_at 'Pending Testing Start Date',
        chang_stat_pend_test.updated_at 'Pending Testing End Date',
        usr_test.user_name 'Testing Team Member',
        'Not Found' as 'Assigned  Test User Level',
        chang_stat_pend_prod_deploy.created_at 'Pending Production Deployment Start Date',
        chang_stat_pend_prod_deploy.updated_at 'Pending Production Deployment End Date',
        chang_stat_pend_busen_fedbk.created_at 'Pending Business Feedback Start Date',
        chang_stat_pend_busen_fedbk.updated_at 'Pending Business Feedback End Date',
        chang_stat_busen_tst_cas_appval.created_at 'Business Test Case Approval Start Date',
        chang_stat_busen_tst_cas_appval.updated_at 'Business Test Case Approval End Date',
        chang_stat_busen_uat_sign_off.created_at 'Business UAT Sign Off Start Date',
        chang_stat_busen_uat_sign_off.updated_at 'Business UAT Sign Off End Date',

        chang_stat_delivered.created_at 'Delivered Date Start',
        chang_stat_delivered.updated_at 'Delivered Date End',
    --    chang_stat_delivery.created_at 'Release Plan Delivery Date Review',
        IFNULL(req.end_test_time, req.end_develop_time) as 'Expected Delivery date'  ,
         chang_stat_closed.created_at 'Closed Date',
        stat.status_name 'Previous Status',
    --    CONCAT(sla.unit_sla_time, ' ', sla.sla_type_unit) AS `Assigned SLA`,
    --     grou.title AS `Assigned team`,
    --     usr.user_name as 'Assigned Member',
    --    roles.`name` as 'Assigned member level',
        req.requester_name,
        req.division_manager,
        'Not Found' as 'Requseter division',
        'Not Found' as 'Requester Sector',
        rejt_reason.name 'Rejection Reasons'
    FROM  change_request AS req
    LEFT JOIN applications AS apps ON apps.id = req.application_id
    LEFT JOIN workflow_type AS flow ON flow.id = req.workflow_type_id
    LEFT JOIN change_request_statuses AS curr_status
           ON curr_status.cr_id = req.id
    LEFT JOIN statuses AS stat ON stat.id = curr_status.old_status_id
    LEFT JOIN group_statuses AS gro_stat ON gro_stat.status_id = curr_status.new_status_id
 --   LEFT JOIN `groups` AS grou ON grou.id = gro_stat.group_id
    LEFT JOIN sla_calculations as sla ON sla.status_id = curr_status.new_status_id
    LEFT JOIN change_request_custom_fields as custom_field_chang ON custom_field_chang.cr_id = req.id and custom_field_chang.custom_field_id = 67
    LEFT JOIN users as usr ON usr.id = custom_field_chang.custom_field_value
    LEFT JOIN roles  ON roles.id = usr.role_id

 --   LEFT JOIN change_request_statuses as chang_stat_reject ON  chang_stat_reject.cr_id = req.id and  chang_stat_reject.new_status_id = 70
 --   LEFT JOIN change_request_statuses as chang_stat_analysis ON  chang_stat_analysis.cr_id = req.id and  chang_stat_analysis.new_status_id = 63
    LEFT JOIN change_request_statuses as chang_stat_busin_valid ON  chang_stat_busin_valid.cr_id = req.id and  chang_stat_busin_valid.new_status_id = 18
    LEFT JOIN change_request_statuses as chang_stat_pend_cab ON  chang_stat_pend_cab.cr_id = req.id and  chang_stat_pend_cab.new_status_id = 38
    LEFT JOIN change_request_statuses as chang_stat_designin_prog ON  chang_stat_designin_prog.cr_id = req.id and  chang_stat_designin_prog.new_status_id = 15
    LEFT JOIN change_request_statuses as chang_stat_pend_prod_deploy ON  chang_stat_pend_prod_deploy.cr_id = req.id and  chang_stat_pend_prod_deploy.new_status_id = 17
    LEFT JOIN change_request_statuses as chang_stat_pend_desin ON  chang_stat_pend_desin.cr_id = req.id and  chang_stat_pend_desin.new_status_id = 7
    LEFT JOIN change_request_statuses as chang_stat_pend_busen_fedbk ON  chang_stat_pend_busen_fedbk.cr_id = req.id and  chang_stat_pend_busen_fedbk.new_status_id = 79
    LEFT JOIN change_request_statuses as chang_stat_busen_tst_cas_appval ON  chang_stat_busen_tst_cas_appval.cr_id = req.id and  chang_stat_busen_tst_cas_appval.new_status_id = 41
    LEFT JOIN change_request_statuses as chang_stat_busen_uat_sign_off ON  chang_stat_busen_uat_sign_off.cr_id = req.id and  chang_stat_busen_uat_sign_off.new_status_id = 44
    LEFT JOIN users as usr_design ON usr_design.id = chang_stat_pend_desin.user_id
    LEFT JOIN roles as assigned_user_level_design  ON roles.id = usr_design.role_id

    LEFT JOIN change_request_statuses as chang_stat_pend_implememt ON  chang_stat_pend_implememt.cr_id = req.id and  chang_stat_pend_implememt.new_status_id = 8
    LEFT JOIN users as usr_dev ON usr_dev.id = chang_stat_pend_implememt.user_id
    LEFT JOIN roles as assigned_user_level_dev  ON roles.id = usr_dev.role_id

    LEFT JOIN change_request_statuses as chang_stat_pend_test ON  chang_stat_pend_test.cr_id = req.id and  chang_stat_pend_test.new_status_id = 11
    LEFT JOIN users as usr_test ON usr_test.id = chang_stat_pend_test.user_id
    LEFT JOIN roles as assigned_user_level_test  ON roles.id = usr_test.role_id


    LEFT JOIN change_request_custom_fields AS dpnd_on  ON dpnd_on.cr_id = req.id AND dpnd_on.custom_field_name = 'cr_type'
    LEFT JOIN change_request_custom_fields AS rlvvnt  ON rlvvnt.cr_id = req.id AND rlvvnt.custom_field_name = 'cr_type'


     LEFT JOIN change_request_statuses as chang_stat_closed ON  chang_stat_closed.cr_id = req.id and  chang_stat_closed.new_status_id = 49
  LEFT JOIN change_request_statuses as chang_stat_delivered ON  chang_stat_delivered.cr_id = req.id and  chang_stat_delivered.new_status_id = 27
     LEFT JOIN change_request_statuses as chang_stat_delivery ON  chang_stat_delivery.cr_id = req.id and  chang_stat_delivery.new_status_id = 60
     LEFT JOIN change_request_custom_fields as chang_custm_rejt_reason ON  chang_custm_rejt_reason.cr_id = req.id and  chang_custm_rejt_reason.custom_field_id = 63
     LEFT JOIN rejection_reasons as rejt_reason ON  rejt_reason.id = chang_custm_rejt_reason.custom_field_value

            LEFT JOIN change_request_custom_fields AS on_behalf  ON on_behalf.cr_id = req.id AND on_behalf.custom_field_name = 'on_behalf'

    WHERE curr_status.new_status_id = 19
    ";

        // start new filter
        if ($top_management) {
            $query .= ' AND req.top_management = ?';
            $bindings[] = $top_management;
        }

        if ($on_hold) {
            $query .= ' AND req.hold = ?';
            $bindings[] = $on_hold;
        }

        if ($on_behalf) {
            $query .= ' AND on_behalf.custom_field_value = ?';
            $bindings[] = $on_behalf;
        }

        if ($ticket_type) {
            $query .= ' AND dpnd_on.custom_field_value = ?';
            $bindings[] = $ticket_type;
        }
        // end new filter

        $query .= ' GROUP BY req.cr_no';

        $results = DB::select($query, $bindings);
        $results = collect($results);

        // Pagination setup
        $perPage = 10;
        $page = $request->get('page', 1);

        // Slice collection for current page
        $currentPageItems = $results->slice(($page - 1) * $perPage, $perPage)->values();

        // Create LengthAwarePaginator
        $paginatedResults = new LengthAwarePaginator(
            $currentPageItems,
            $results->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('reports.rejected_crs', [
            'results' => $paginatedResults, // <-- pass paginator to view
        ]);
    }

    public function exportRejectedCrs()
    {
        return Excel::download(new RejectedCRsExport, 'RejectedCRs.xlsx');
    }

    public function exportCurrentStatus(Request $request)
    {
        // Get same filters from POST
        $filters = $request->only(['cr_type', 'status_ids', 'cr_nos']);

        return Excel::download(new TableExport($filters), 'current_status.xlsx');
    }

    public function new_sla_report()
    {
        $service = app(ReportService::class);
        $results = $service->getSLAReport();

        $units = Unit::active()->toBase()->get(['id', 'name']);
        $statuses = Status::active()->toBase()->get(['id', 'status_name']);
        $departments = RequesterDepartment::active()->toBase()->get(['id', 'name']);

        return view('reports.new_sla_report',
            compact('results', 'units', 'statuses', 'departments'));
    }

    public function exportNewSlaReport(Request $request): BinaryFileResponse
    {
        return Excel::download(app(ExportSLAReport::class), 'sla_report.xlsx');
    }

    /**
     * Show SLA Report page
     */
    public function slaReport(Request $request)
    {
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $unit_id = $request->input('unit_id');
        $status_name = $request->input('status_name');
        $department_id = $request->input('department_id');

        $ticket_type = $request->input('ticket_type');
        $top_management = $request->input('top_management');
        $on_hold = $request->input('on_hold');
        $on_behalf = $request->input('on_behalf');

        // Dynamic ID fetching for consistency, though seeding implies a fixed name
        $department_field_id = DB::table('custom_fields')->where('name', 'requester_department')->value('id');

        $query = "
WITH designprogress_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        user_id,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 15
),
 pend_design_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 7
),
    pend_implementaion_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        user_id,
        group_id,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 8
),
technical_implementation_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        group_id,
        user_id,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 10
),
    pend_testing_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        user_id,
        group_id,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 11
)
SELECT
    req.cr_no,
    categry.`name` AS 'Category',
    stat.status_name AS 'Current Status',
    req.requester_name,
    CASE
        WHEN on_behalf.custom_field_value = '0' THEN 'N/A'
        WHEN on_behalf.custom_field_value = '1' THEN 'YES'
        ELSE 'N/A'
    END AS 'On Behalf',
    CASE
        WHEN req.hold = '0' THEN 'N/A'
        WHEN req.hold = '1' THEN 'YES'
    END AS 'On Hold',
    CASE
    WHEN req.top_management = '0' THEN 'N/A'
    WHEN req.top_management = '1' THEN 'YES'
    END AS 'Top Management',
    CASE
        WHEN dpnd_on.custom_field_value = '1' THEN 'Normal'
        WHEN dpnd_on.custom_field_value = '2' THEN 'Depend On'
        WHEN dpnd_on.custom_field_value = '3' THEN 'Relevant'
        ELSE 'N/A'
    END AS 'Ticket Type',
    apps.`name` AS 'Targeted System',
    technical_team.title AS 'Technical Team',
    IF(req.start_design_time > 0 AND req.end_design_time > 0, 'Design', 'No Design') AS 'Design Status',
    IF(req.start_test_time > 0 AND req.end_test_time > 0, 'Testing', 'No Testing') AS 'Testing Status',

    -- Business Validation
    IF(
        IF(sla_busns_val.sla_type_unit = 'day',
           (TIMESTAMPDIFF(DAY, busnes_valid_stats.created_at, busnes_valid_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, busnes_valid_stats.created_at, busnes_valid_stats.updated_at) + WEEKDAY(busnes_valid_stats.created_at) + 1) / 7) * 2)),
           (TIMESTAMPDIFF(DAY, busnes_valid_stats.created_at, busnes_valid_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, busnes_valid_stats.created_at, busnes_valid_stats.updated_at) + WEEKDAY(busnes_valid_stats.created_at) + 1) / 7) * 2)) * 8
        ) <= sla_busns_val.unit_sla_time, 'Meet SLA', 'No Meet SLA'
    ) AS 'Business Validation',

   -- Testing Estimation
    IF(
        req.start_test_time > 0 AND req.end_test_time > 0,
        -- If valid timestamps exist, calculate the Business Day SLA
        IF(
            IF(tstig_est_val.sla_type_unit = 'day',
            (TIMESTAMPDIFF(DAY, tstig_est_stats.created_at, tstig_est_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, tstig_est_stats.created_at, tstig_est_stats.updated_at) + WEEKDAY(tstig_est_stats.created_at) + 1) / 7) * 2)),
            (TIMESTAMPDIFF(DAY, tstig_est_stats.created_at, tstig_est_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, tstig_est_stats.created_at, tstig_est_stats.updated_at) + WEEKDAY(tstig_est_stats.created_at) + 1) / 7) * 2)) * 8
            ) <= tstig_est_val.unit_sla_time, 'Meet SLA', 'No Meet SLA'
        ),
        -- If start_test_time or end_test_time are NULL or 0, return N/A
        'N/A'
    ) AS 'Testing Estimation',

   -- 2. Design Estimation Column (with N/A Logic)
IF(req.start_design_time > 0 AND req.end_design_time > 0,
    -- Inner Logic: Only runs if design status is 'Design'
    IF(
        IF(dsign_est_val.sla_type_unit = 'day',
            (TIMESTAMPDIFF(DAY, dsign_est_stats.created_at, dsign_est_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, dsign_est_stats.created_at, dsign_est_stats.updated_at) + WEEKDAY(dsign_est_stats.created_at) + 1) / 7) * 2)),
            (TIMESTAMPDIFF(DAY, dsign_est_stats.created_at, dsign_est_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, dsign_est_stats.created_at, dsign_est_stats.updated_at) + WEEKDAY(dsign_est_stats.created_at) + 1) / 7) * 2)) * 8
        ) <= dsign_est_val.unit_sla_time, 'Meet SLA', 'No Meet SLA'
    ),
    -- Default value if conditions aren't met
    'N/A'
) AS 'Design Estimation',

    -- Technical Estimation
    IF(
        req.start_develop_time > 0 AND req.end_develop_time > 0
        AND req.start_develop_time IS NOT NULL
        AND req.end_develop_time IS NOT NULL,
        -- If Development times are valid, run the SLA logic
        IF(
            IF(tech_est_val.sla_type_unit = 'day',
            (TIMESTAMPDIFF(DAY, tech_est_stats.created_at, tech_est_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, tech_est_stats.created_at, tech_est_stats.updated_at) + WEEKDAY(tech_est_stats.created_at) + 1) / 7) * 2)),
            (TIMESTAMPDIFF(DAY, tech_est_stats.created_at, tech_est_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, tech_est_stats.created_at, tech_est_stats.updated_at) + WEEKDAY(tech_est_stats.created_at) + 1) / 7) * 2)) * 8
            ) <= tech_est_val.unit_sla_time, 'Meet SLA', 'No Meet SLA'
        ),
        -- If Null or Zero, return N/A
        'N/A'
    ) AS 'Technical Estimation',

    -- Pending Design Document Approval QC
    IF(
        -- GATEKEEPER: Check if Design Status would be 'No Design'
        req.start_design_time <= 0 OR req.end_design_time <= 0 OR req.start_design_time IS NULL,
        'N/A',
        -- CALCULATION: Run the SLA logic only if Design exists
        IF(
            IF(pedig_dishn_doc_appov_val.sla_type_unit = 'day',
            (TIMESTAMPDIFF(DAY, pedig_dishn_doc_appov_stats.created_at, pedig_dishn_doc_appov_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, pedig_dishn_doc_appov_stats.created_at, pedig_dishn_doc_appov_stats.updated_at) + WEEKDAY(pedig_dishn_doc_appov_stats.created_at) + 1) / 7) * 2)),
            (TIMESTAMPDIFF(DAY, pedig_dishn_doc_appov_stats.created_at, pedig_dishn_doc_appov_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, pedig_dishn_doc_appov_stats.created_at, pedig_dishn_doc_appov_stats.updated_at) + WEEKDAY(pedig_dishn_doc_appov_stats.created_at) + 1) / 7) * 2)) * 8
            ) <= pedig_dishn_doc_appov_val.unit_sla_time,
            'Meet SLA',
            'No Meet SLA'
        )
    ) AS 'Pending Design Document Approval QC',

    -- Pending Design Document Approval DEV
    IF(
        -- GATEKEEPER: Check if Design Status would be 'No Design'
        req.start_design_time <= 0 OR req.end_design_time <= 0 OR req.start_design_time IS NULL,
        'N/A',
        -- CALCULATION: Run the DEV Approval SLA logic only if Design exists
        IF(
            IF(pedig_dishn_doc_dev_appov_val.sla_type_unit = 'day',
            (TIMESTAMPDIFF(DAY, pedig_dishn_doc_dev_appov_stats.created_at, pedig_dishn_doc_dev_appov_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, pedig_dishn_doc_dev_appov_stats.created_at, pedig_dishn_doc_dev_appov_stats.updated_at) + WEEKDAY(pedig_dishn_doc_dev_appov_stats.created_at) + 1) / 7) * 2)),
            (TIMESTAMPDIFF(DAY, pedig_dishn_doc_dev_appov_stats.created_at, pedig_dishn_doc_dev_appov_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, pedig_dishn_doc_dev_appov_stats.created_at, pedig_dishn_doc_dev_appov_stats.updated_at) + WEEKDAY(pedig_dishn_doc_dev_appov_stats.created_at) + 1) / 7) * 2)) * 8
            ) <= pedig_dishn_doc_dev_appov_val.unit_sla_time,
            'Meet SLA',
            'No Meet SLA'
        )
    ) AS 'Pending Design Document Approval DEV',

    -- Technical Test Case Approval
    IF(
        -- GATEKEEPER: If no status timestamps exist, return N/A
        tech_tst_apprvl_stats.created_at IS NULL OR tech_tst_apprvl_stats.updated_at IS NULL,
        'N/A',
        -- CALCULATION: Run the Technical SLA logic
        IF(
            IF(tech_tst_apprvl_val.sla_type_unit = 'day',
            (TIMESTAMPDIFF(DAY, tech_tst_apprvl_stats.created_at, tech_tst_apprvl_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, tech_tst_apprvl_stats.created_at, tech_tst_apprvl_stats.updated_at) + WEEKDAY(tech_tst_apprvl_stats.created_at) + 1) / 7) * 2)),
            (TIMESTAMPDIFF(DAY, tech_tst_apprvl_stats.created_at, tech_tst_apprvl_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, tech_tst_apprvl_stats.created_at, tech_tst_apprvl_stats.updated_at) + WEEKDAY(tech_tst_apprvl_stats.created_at) + 1) / 7) * 2)) * 8
            ) <= tech_tst_apprvl_val.unit_sla_time,
            'Meet SLA',
            'No Meet SLA'
        )
    ) AS 'Technical Test Case Approval',

    -- Design Test Case Approval
    IF(
        -- GATEKEEPER: Check if Design Status would be 'No Design'
        req.start_design_time <= 0 OR req.end_design_time <= 0 OR req.start_design_time IS NULL,
        'N/A',
        -- CALCULATION: Run the Design SLA logic only if Design exists
        IF(
            IF(dsgn_tst_apprvl_val.sla_type_unit = 'day',
            (TIMESTAMPDIFF(DAY, dsgn_tst_apprvl_stats.created_at, dsgn_tst_apprvl_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, dsgn_tst_apprvl_stats.created_at, dsgn_tst_apprvl_stats.updated_at) + WEEKDAY(dsgn_tst_apprvl_stats.created_at) + 1) / 7) * 2)),
            (TIMESTAMPDIFF(DAY, dsgn_tst_apprvl_stats.created_at, dsgn_tst_apprvl_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, dsgn_tst_apprvl_stats.created_at, dsgn_tst_apprvl_stats.updated_at) + WEEKDAY(dsgn_tst_apprvl_stats.created_at) + 1) / 7) * 2)) * 8
            ) <= dsgn_tst_apprvl_val.unit_sla_time,
            'Meet SLA',
            'No Meet SLA'
        )
    ) AS 'Design Test Case Approval',

    -- Business Test Case Approval
     IF(
        -- GATEKEEPER: Return N/A if the timestamps are NULL or 0
        bsns_tst_apprvl_stats.created_at IS NULL OR bsns_tst_apprvl_stats.updated_at IS NULL,
        'N/A',
        -- CALCULATION: Run the SLA logic
        IF(
            IF(bsns_tst_apprvl_val.sla_type_unit = 'day',
                (TIMESTAMPDIFF(DAY, bsns_tst_apprvl_stats.created_at, bsns_tst_apprvl_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, bsns_tst_apprvl_stats.created_at, bsns_tst_apprvl_stats.updated_at) + WEEKDAY(bsns_tst_apprvl_stats.created_at) + 1) / 7) * 2)),
                (TIMESTAMPDIFF(DAY, bsns_tst_apprvl_stats.created_at, bsns_tst_apprvl_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, bsns_tst_apprvl_stats.created_at, bsns_tst_apprvl_stats.updated_at) + WEEKDAY(bsns_tst_apprvl_stats.created_at) + 1) / 7) * 2)) * 8
                ) <= bsns_tst_apprvl_val.unit_sla_time,
                'Meet SLA',
                'No Meet SLA'
            )
        ) AS 'Business Test Case Approval',

    -- RollBack
    IF(
        IF(rollback_val.sla_type_unit = 'day',
           (TIMESTAMPDIFF(DAY, rollback_stats.created_at, rollback_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, rollback_stats.created_at, rollback_stats.updated_at) + WEEKDAY(rollback_stats.created_at) + 1) / 7) * 2)),
           (TIMESTAMPDIFF(DAY, rollback_stats.created_at, rollback_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, rollback_stats.created_at, rollback_stats.updated_at) + WEEKDAY(rollback_stats.created_at) + 1) / 7) * 2)) * 8
        ) <= rollback_val.unit_sla_time, 'Meet SLA', 'No Meet SLA'
    ) AS 'RollBack',

    -- Sanity Check
    IF(
        IF(sanity_val.sla_type_unit = 'day',
           (TIMESTAMPDIFF(DAY, sanity_stats.created_at, sanity_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, sanity_stats.created_at, sanity_stats.updated_at) + WEEKDAY(sanity_stats.created_at) + 1) / 7) * 2)),
           (TIMESTAMPDIFF(DAY, sanity_stats.created_at, sanity_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, sanity_stats.created_at, sanity_stats.updated_at) + WEEKDAY(sanity_stats.created_at) + 1) / 7) * 2)) * 8
        ) <= sanity_val.unit_sla_time, 'Meet SLA', 'No Meet SLA'
    ) AS 'Sanity Check',

    -- Health Check
    IF(
        IF(health_val.sla_type_unit = 'day',
           (TIMESTAMPDIFF(DAY, health_stats.created_at, health_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, health_stats.created_at, health_stats.updated_at) + WEEKDAY(health_stats.created_at) + 1) / 7) * 2)),
           (TIMESTAMPDIFF(DAY, health_stats.created_at, health_stats.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, health_stats.created_at, health_stats.updated_at) + WEEKDAY(health_stats.created_at) + 1) / 7) * 2)) * 8
        ) <= health_val.unit_sla_time, 'Meet SLA', 'No Meet SLA'
    ) AS 'Health Check',
        -- Design Estimation Comparison
    IF(
    -- GATEKEEPER: If no design timestamps exist, the comparison is not applicable
    req.start_design_time <= 0 OR req.end_design_time <= 0 OR req.start_design_time IS NULL,
    'N/A',
    -- CALCULATION: Compare Actual vs Planned Duration
    IF(
        -- Actual Duration (8-hour work day, excluding Fri/Sat)
        ((TIMESTAMPDIFF(DAY, designprogress.created_at, designprogress.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, designprogress.created_at, designprogress.updated_at) + WEEKDAY(designprogress.created_at) + 1) / 7) * 2)) * 8)
        <=
        -- Planned Duration (8-hour work day, excluding Fri/Sat)
        ((TIMESTAMPDIFF(DAY, req.start_design_time, req.end_design_time) - (FLOOR((TIMESTAMPDIFF(DAY, req.start_design_time, req.end_design_time) + WEEKDAY(req.start_design_time) + 1) / 7) * 2)) * 8),
        'Meet',
        'Not Meet'
    )
) AS 'Design Estimation Comparison',

    -- Technical Implementation Comparison
    IF(
        -- GATEKEEPER: Check if Development times are missing or invalid
        req.start_develop_time <= 0 OR req.end_develop_time <= 0
        OR req.start_develop_time IS NULL OR req.end_develop_time IS NULL,
        'N/A',
        -- CALCULATION: Run the comparison logic only if Development exists
        IF(
            -- Actual Duration (8-hour work day, excluding Fri/Sat)
            ((TIMESTAMPDIFF(DAY, tetch_implt_start.created_at, tetch_implt_start.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, tetch_implt_start.created_at, tetch_implt_start.updated_at) + WEEKDAY(tetch_implt_start.created_at) + 1) / 7) * 2)) * 8)
            <=
            -- Planned Duration (8-hour work day, excluding Fri/Sat)
            ((TIMESTAMPDIFF(DAY, req.start_develop_time, req.end_develop_time) - (FLOOR((TIMESTAMPDIFF(DAY, req.start_develop_time, req.end_develop_time) + WEEKDAY(req.start_develop_time) + 1) / 7) * 2)) * 8),
            'Meet',
            'Not Meet'
        )
    ) AS 'Technical Estimation Comparison',

    -- Testing Estimation Comparison
    IF(
            -- GATEKEEPER: If timestamps are missing or invalid, return N/A for both columns
            req.start_test_time <= 0 OR req.end_test_time <= 0 OR req.start_test_time IS NULL,
            'N/A',
            -- CALCULATION: Run the duration comparison logic
            IF(
                -- Actual Duration (8-hour work day, excluding Fri/Sat)
                ((TIMESTAMPDIFF(DAY, pend_test.created_at, pend_test.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, pend_test.created_at, pend_test.updated_at) + WEEKDAY(pend_test.created_at) + 1) / 7) * 2)) * 8)
                <=
                -- Planned Duration (8-hour work day, excluding Fri/Sat)
                ((TIMESTAMPDIFF(DAY, req.start_test_time, req.end_test_time) - (FLOOR((TIMESTAMPDIFF(DAY, req.start_test_time, req.end_test_time) + WEEKDAY(req.start_test_time) + 1) / 7) * 2)) * 8),
                'Meet',
                'Not Meet'
            )
        ) AS 'Testing Estimation Comparison'



FROM change_request AS req
LEFT JOIN applications AS apps ON apps.id = req.application_id
LEFT JOIN change_request_statuses AS curr_status ON curr_status.cr_id = req.id AND curr_status.`active` = '1'
LEFT JOIN statuses AS stat ON stat.id = curr_status.new_status_id
LEFT JOIN change_request_custom_fields AS req_csut_feld ON req_csut_feld.cr_id = req.id AND req_csut_feld.custom_field_id = ?

-- Latest Status Lookups
LEFT JOIN change_request_statuses AS busnes_valid_stats ON busnes_valid_stats.id = (SELECT MAX(id) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '18')
LEFT JOIN change_request_statuses AS tstig_est_stats ON tstig_est_stats.id = (SELECT MAX(id) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '6')
LEFT JOIN change_request_statuses AS dsign_est_stats ON dsign_est_stats.id = (SELECT MAX(id) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '3')
LEFT JOIN change_request_statuses AS tech_est_stats ON tech_est_stats.id = (SELECT MAX(id) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '4')
LEFT JOIN change_request_statuses AS pedig_dishn_doc_appov_stats ON pedig_dishn_doc_appov_stats.id = (SELECT MAX(id) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '72')
LEFT JOIN change_request_statuses AS pedig_dishn_doc_dev_appov_stats ON pedig_dishn_doc_dev_appov_stats.id = (SELECT MAX(id) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '71')
LEFT JOIN change_request_statuses AS tech_tst_apprvl_stats ON tech_tst_apprvl_stats.id = (SELECT MAX(id) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '39')
LEFT JOIN change_request_statuses AS dsgn_tst_apprvl_stats ON dsgn_tst_apprvl_stats.id = (SELECT MAX(id) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '40')
LEFT JOIN change_request_statuses AS bsns_tst_apprvl_stats ON bsns_tst_apprvl_stats.id = (SELECT MAX(id) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '41')
LEFT JOIN change_request_statuses AS rollback_stats ON rollback_stats.id = (SELECT MAX(id) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '29')
LEFT JOIN change_request_statuses AS sanity_stats ON sanity_stats.id = (SELECT MAX(id) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '21')
LEFT JOIN change_request_statuses AS health_stats ON health_stats.id = (SELECT MAX(id) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '48')

-- SLA Joins
LEFT JOIN sla_calculations AS sla_busns_val ON sla_busns_val.status_id = '18'
LEFT JOIN sla_calculations AS tstig_est_val ON tstig_est_val.status_id = '6'
LEFT JOIN sla_calculations AS dsign_est_val ON dsign_est_val.status_id = '3'
LEFT JOIN sla_calculations AS tech_est_val ON tech_est_val.status_id = '4'
LEFT JOIN sla_calculations AS pedig_dishn_doc_appov_val ON pedig_dishn_doc_appov_val.status_id = '72'
LEFT JOIN sla_calculations AS pedig_dishn_doc_dev_appov_val ON pedig_dishn_doc_dev_appov_val.status_id = '71'
LEFT JOIN sla_calculations AS tech_tst_apprvl_val ON tech_tst_apprvl_val.status_id = '39'
LEFT JOIN sla_calculations AS dsgn_tst_apprvl_val ON dsgn_tst_apprvl_val.status_id = '40'
LEFT JOIN sla_calculations AS bsns_tst_apprvl_val ON bsns_tst_apprvl_val.status_id = '41'
LEFT JOIN sla_calculations AS rollback_val ON rollback_val.status_id = '29'
LEFT JOIN sla_calculations AS sanity_val ON sanity_val.status_id = '21'
LEFT JOIN sla_calculations AS health_val ON health_val.status_id = '48'

-- Technical Team
LEFT JOIN change_request_statuses AS tetch_implt_latest ON tetch_implt_latest.id = (SELECT MAX(id) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = 10)
LEFT JOIN user_groups AS usr_grp ON usr_grp.user_id = tetch_implt_latest.user_id
LEFT JOIN `groups` AS technical_team ON technical_team.id = usr_grp.group_id

   LEFT JOIN designprogress_ranked designprogress ON designprogress.cr_id = req.id AND designprogress.rn = 1
   LEFT JOIN technical_implementation_ranked AS tetch_implt_start ON tetch_implt_start.cr_id = req.id  AND tetch_implt_start.rn = 1
   LEFT JOIN pend_testing_ranked pend_test ON pend_test.cr_id = req.id AND pend_test.rn = 1

LEFT JOIN change_request_custom_fields AS dpnd_on  ON dpnd_on.cr_id = req.id AND dpnd_on.custom_field_name = 'cr_type'
LEFT JOIN change_request_custom_fields AS rlvvnt  ON rlvvnt.cr_id = req.id AND rlvvnt.custom_field_name = 'cr_type'

-- Category
LEFT JOIN change_request_custom_fields AS cut_felds_cagoy ON cut_felds_cagoy.cr_id = req.id AND cut_felds_cagoy.custom_field_id = '31'
LEFT JOIN categories AS categry ON categry.id = cut_felds_cagoy.custom_field_value
            LEFT JOIN change_request_custom_fields AS on_behalf  ON on_behalf.cr_id = req.id AND on_behalf.custom_field_name = 'on_behalf'


  WHERE 1=1
  ";

        $bindings = [];
        // Bind ID for custom field join
        $bindings[] = $department_field_id;

        if ($from_date) {
            $query .= ' AND req.created_at >= ?';
            $bindings[] = $from_date . ' 00:00:00';
        }

        if ($top_management) {
            $query .= ' AND req.top_management = ?';
            $bindings[] = $top_management;
        }

        if ($on_hold) {
            $query .= ' AND req.hold = ?';
            $bindings[] = $on_hold;
        }

        if ($on_behalf) {
            $query .= ' AND on_behalf.custom_field_value = ?';
            $bindings[] = $on_behalf;
        }

        if ($ticket_type) {
            $query .= ' AND dpnd_on.custom_field_value = ?';
            $bindings[] = $ticket_type;
        }

        if ($to_date) {
            $query .= ' AND req.created_at <= ?';
            $bindings[] = $to_date . ' 23:59:59';
        }

        if ($unit_id) {
            $query .= ' AND req.unit_id = ?';
            $bindings[] = $unit_id;
        }

        if ($status_name) {
            $query .= ' AND stat.status_name = ?';
            $bindings[] = $status_name;
        }

        if ($department_id) {
            $department = \App\Models\RequesterDepartment::find($department_id);
            if ($department) {
                $query .= ' AND req_csut_feld.custom_field_value = ?';
                $bindings[] = $department->id;
            }
        }

        $query .= ' GROUP BY req.cr_no';

        // Execute query
        $results = collect(DB::select($query, $bindings));

        if ($request->has('export')) {
            return Excel::download(new SlaReportExport($results), 'sla_report.xlsx');
        }

        // Pagination
        $perPage = 10;
        $page = $request->get('page', 1);
        $currentPageItems = $results->slice(($page - 1) * $perPage, $perPage)->values();

        $paginatedResults = new LengthAwarePaginator(
            $currentPageItems,
            $results->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $units = Unit::all();
        $statuses = DB::table('statuses')->select('status_name')->distinct()->orderBy('status_name')->get();
        $departments = \App\Models\RequesterDepartment::where('active', '1')->get();

        return view('reports.sla_report', [
            'results' => $paginatedResults,
            'units' => $units,
            'statuses' => $statuses,
            'departments' => $departments,
        ]);
    }

    /**
     * Show KPI Report page
     */
    public function kpiReport(Request $request)
    {
        $from_date = $request->input('from_date');
        $to_date = $request->input('to_date');
        $unit_id = $request->input('unit_id');
        $status_name = $request->input('status_name');
        $department_id = $request->input('department_id');

        $ticket_type = $request->input('ticket_type');
        $top_management = $request->input('top_management');
        $on_hold = $request->input('on_hold');
        $on_behalf = $request->input('on_behalf');

        // Dynamic ID fetching for consistency
        $department_field_id = DB::table('custom_fields')->where('name', 'requester_department')->value('id');

        $query = "
   WITH designprogress_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        user_id,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 15
),
 pend_implementaion_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        user_id,
        group_id,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 8
),
 pend_testing_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        user_id,
        group_id,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 11
),
technical_implementation_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        group_id,
        user_id,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
    FROM change_request_statuses
    WHERE new_status_id = 10
),
 test_in_progress_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn,
        COUNT(*) OVER (PARTITION BY cr_id) AS total_entries -- New column to check total count
    FROM change_request_statuses
    WHERE new_status_id = 13
),
uat_in_Progress_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn,
        COUNT(*) OVER (PARTITION BY cr_id) AS total_entries -- New column to check total count
    FROM change_request_statuses
    WHERE new_status_id = 42
),
sanity_check_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn,
        COUNT(*) OVER (PARTITION BY cr_id) AS total_entries -- New column to check total count
    FROM change_request_statuses
    WHERE new_status_id = 21
),
health_check_ranked AS (
    SELECT
        cr_id,
        id,
        created_at,
        updated_at,
        ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn,
        COUNT(*) OVER (PARTITION BY cr_id) AS total_entries -- New column to check total count
    FROM change_request_statuses
    WHERE new_status_id = 48
)

SELECT
    req.cr_no,
    categry.`name` AS 'Category',
    stat.status_name AS 'Current Status',
    req.requester_name,

    CASE
        WHEN on_behalf.custom_field_value = '0' THEN 'N/A'
        WHEN on_behalf.custom_field_value = '1' THEN 'YES'
        ELSE 'N/A'
    END AS 'On Behalf',
    CASE
        WHEN req.hold = '0' THEN 'N/A'
        WHEN req.hold = '1' THEN 'YES'
    END AS 'On Hold',
    CASE
    WHEN req.top_management = '0' THEN 'N/A'
    WHEN req.top_management = '1' THEN 'YES'
    END AS 'Top Management',
    CASE
        WHEN dpnd_on.custom_field_value = '1' THEN 'Normal'
        WHEN dpnd_on.custom_field_value = '2' THEN 'Depend On'
        WHEN dpnd_on.custom_field_value = '3' THEN 'Relevant'
        ELSE 'N/A'
    END AS 'Ticket Type',
    apps.`name` AS 'Targeted System',
    technical_team.title AS 'Technical Team',
    IF(req.start_design_time > 0 AND req.end_design_time > 0, 'Design', 'No Design') AS 'Design Status',
    IF(req.start_test_time > 0 AND req.end_test_time > 0, 'Testing', 'No Testing') AS 'Testing Status',

    -- Design Estimation Comparison
    IF(
        req.start_design_time > 0 AND req.end_design_time > 0,
        -- If Design exists, perform the comparison math
        IF(
            -- Actual Duration (8-hour work day, excluding Fri/Sat)
            ((TIMESTAMPDIFF(DAY, designprogress.created_at, designprogress.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, designprogress.created_at, designprogress.updated_at) + WEEKDAY(designprogress.created_at) + 1) / 7) * 2)) * 8)
            <=
            -- Planned Duration (8-hour work day, excluding Fri/Sat)
            ((TIMESTAMPDIFF(DAY, req.start_design_time, req.end_design_time) - (FLOOR((TIMESTAMPDIFF(DAY, req.start_design_time, req.end_design_time) + WEEKDAY(req.start_design_time) + 1) / 7) * 2)) * 8),
            'Meet',
            'Not Meet'
        ),
        -- If No Design, return N/A
        'N/A'
    ) AS 'Design Estimation ',

   -- Technical Implementation Comparison
        IF(
            req.start_develop_time > 0 AND req.end_develop_time > 0,
            -- If valid development plan exists, run the comparison
            IF(
            -- Actual Duration (8-hour work day, excluding Fri/Sat)
            ((TIMESTAMPDIFF(DAY, tetch_implt_start.created_at, tetch_implt_start.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, tetch_implt_start.created_at, tetch_implt_start.updated_at) + WEEKDAY(tetch_implt_start.created_at) + 1) / 7) * 2)) * 8)
            <=
            -- Planned Duration (8-hour work day, excluding Fri/Sat)
            ((TIMESTAMPDIFF(DAY, req.start_develop_time, req.end_develop_time) - (FLOOR((TIMESTAMPDIFF(DAY, req.start_develop_time, req.end_develop_time) + WEEKDAY(req.start_develop_time) + 1) / 7) * 2)) * 8),
            'Meet',
            'Not Meet'
        ),
        -- If start_develop_time or end_develop_time are NULL or 0, return N/A
        'N/A'
        ) AS 'Technical Estimation',

   -- Testing Estimation Comparison
    IF(
        req.start_test_time > 0 AND req.end_test_time > 0,
        -- If valid test plan exists, run the comparison
        IF(
            -- Actual Duration (8-hour work day, excluding Fri/Sat)
            ((TIMESTAMPDIFF(DAY, pend_test.created_at, pend_test.updated_at) - (FLOOR((TIMESTAMPDIFF(DAY, pend_test.created_at, pend_test.updated_at) + WEEKDAY(pend_test.created_at) + 1) / 7) * 2)) * 8)
            <=
            -- Planned Duration (8-hour work day, excluding Fri/Sat)
            ((TIMESTAMPDIFF(DAY, req.start_test_time, req.end_test_time) - (FLOOR((TIMESTAMPDIFF(DAY, req.start_test_time, req.end_test_time) + WEEKDAY(req.start_test_time) + 1) / 7) * 2)) * 8),
            'Meet',
            'Not Meet'
        ),
        -- If start_test_time or end_test_time are NULL or 0, return N/A
        'N/A'
    ) AS 'Testing Estimation ',

   -- Test InProgress vs Rework Logic
    CASE
        WHEN pendig_rework_after_test_inprogress.created_at IS NULL THEN 'NA'
        WHEN pendig_rework_after_test_inprogress.created_at > test_in_progress_rnk.created_at THEN 'Not Meet'
        ELSE 'Meet'
    END AS 'Test in Progress',
    -- UAT In Progress vs Rework
    CASE
        WHEN pendig_rework_after_uat_inprogress.created_at IS NULL THEN 'NA'
        WHEN pendig_rework_after_uat_inprogress.created_at > uat_in_progress_rnk.created_at THEN 'Not Meet'
        ELSE 'Meet'
    END AS 'UAT In Progress',
    -- Sanity check VS rework
    CASE
        WHEN pendig_rework_after_sanity.created_at IS NULL THEN 'NA'
        WHEN pendig_rework_after_sanity.created_at > sanity_check_rnk.created_at THEN 'Not Meet'
        ELSE 'Meet'
    END AS 'Sanity Check',
    -- Health Check
    CASE
        WHEN pendig_rework_after_health.created_at IS NULL THEN 'NA'
        WHEN pendig_rework_after_health.created_at > health_check_rnk.created_at THEN 'Not Meet'
        ELSE 'Meet'
    END AS 'Healthy Check',
    (SELECT COUNT(*)
 FROM change_request_statuses
 WHERE cr_id = req.id AND new_status_id = 31) AS 'Count-Required Info',
       IF(
            -- Condition that determines 'Count-Required Info'
            (SELECT COUNT(*) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = 31) = 0,
            'N/A',
            -- Your existing Logic
            IF(
                COALESCE(
                    (SELECT SUM(
                        (TIMESTAMPDIFF(DAY, created_at, updated_at) -
                        (FLOOR((TIMESTAMPDIFF(DAY, created_at, updated_at) + WEEKDAY(created_at) + 1) / 7) * 2)) * 8
                        + (TIMESTAMPDIFF(HOUR, created_at, updated_at) % 24)
                    )
                    FROM change_request_statuses
                    WHERE cr_id = req.id AND new_status_id = 31),
                0) <= 2, 'Meet', 'Not Meet'
            )
        ) AS 'Time -Required Info',

            (SELECT COUNT(*)
        FROM change_request_statuses
        WHERE cr_id = req.id AND new_status_id = 7) AS 'Count-Design Rework',

    IF(
        -- GATEKEEPER: Check if design times exist
        req.start_design_time <= 0 OR req.end_design_time <= 0 OR req.start_design_time IS NULL,
        'N/A',
        -- CALCULATION: Run the rework logic if times exist
        IF(
            -- ACTUAL REWORK DURATION (Status 7)
            COALESCE((
                SELECT SUM((TIMESTAMPDIFF(DAY, created_at, updated_at) -
                    (FLOOR((TIMESTAMPDIFF(DAY, created_at, updated_at) + WEEKDAY(created_at) + 1) / 7) * 2)) * 8)
                FROM change_request_statuses
                WHERE cr_id = req.id AND new_status_id = 7
            ), 0)
            <=
            -- 25% OF PLANNED DURATION
            (((TIMESTAMPDIFF(DAY, req.start_design_time, req.end_design_time) -
                (FLOOR((TIMESTAMPDIFF(DAY, req.start_design_time, req.end_design_time) + WEEKDAY(req.start_design_time) + 1) / 7) * 2)) * 8) * 0.25),

            'Meet',
            'Not Meet'
        )
    ) AS 'Rework Time-Design Rework',

   -- Rework Count
    IF(
        req.start_develop_time > 0 AND req.end_develop_time > 0,
        -- If development times exist, perform the count
        (SELECT COUNT(*)
        FROM change_request_statuses
        WHERE cr_id = req.id AND new_status_id = 28),
        -- If start_develop_time or end_develop_time are NULL or 0, return N/A
        'N/A'
    ) AS 'Rework',
    -- Time Rework
    IF(
        req.start_develop_time > 0 AND req.end_develop_time > 0,
        -- If Development times exist, calculate the Rework vs Threshold
        IF(
            -- ACTUAL: Total Working Hours spent in 'Pending Rework' (Status 28)
            COALESCE(
                (SELECT SUM(
                    ((TIMESTAMPDIFF(DAY, created_at, updated_at) -
                    (FLOOR((TIMESTAMPDIFF(DAY, created_at, updated_at) + WEEKDAY(created_at) + 1) / 7) * 2)) * 8)
                    + (TIMESTAMPDIFF(HOUR, created_at, updated_at) % 24)
                )
                FROM change_request_statuses
                WHERE cr_id = req.id AND new_status_id = 28),
            0)
            <=
            -- THRESHOLD: 25% of the Planned Technical Estimation Duration
            (
                ((TIMESTAMPDIFF(DAY, req.start_develop_time, req.end_develop_time) -
                (FLOOR((TIMESTAMPDIFF(DAY, req.start_develop_time, req.end_develop_time) + WEEKDAY(req.start_develop_time) + 1) / 7) * 2)) * 8)
                * 0.25
            ),
            'Meet', 'Not Meet'
        ),
        -- If no Development Plan, return N/A
        'N/A'
    ) AS 'Time Rework',

    -- Count TC-Rework
    IF(
        req.start_develop_time > 0 AND req.end_develop_time > 0,
        -- If Development times exist, perform the count
        (SELECT COUNT(*)
        FROM change_request_statuses
        WHERE cr_id = req.id AND new_status_id = 53),
        -- If start_develop_time or end_develop_time are NULL or 0, return N/A
        'N/A'
    ) AS 'Count TC-Rwork',

    -- Time TC-Rwork
    IF(
        req.start_test_time > 0 AND req.end_test_time > 0,
        -- If Testing timestamps exist, run the comparison logic
        IF(
            -- ACTUAL: Sum of all working hours in Status 53
            COALESCE(
                (SELECT SUM(
                    ((TIMESTAMPDIFF(DAY, created_at, updated_at) -
                    (FLOOR((TIMESTAMPDIFF(DAY, created_at, updated_at) + WEEKDAY(created_at) + 1) / 7) * 2)) * 8)
                    + (TIMESTAMPDIFF(HOUR, created_at, updated_at) % 24)
                )
                FROM change_request_statuses
                WHERE cr_id = req.id AND new_status_id = 53),
            0)
            <=
            -- THRESHOLD: 25% of Testing Planned Duration
            (
                ((TIMESTAMPDIFF(DAY, req.start_test_time, req.end_test_time) -
                (FLOOR((TIMESTAMPDIFF(DAY, req.start_test_time, req.end_test_time) + WEEKDAY(req.start_test_time) + 1) / 7) * 2)) * 8)
                * 0.25
            ),
            'Meet', 'Not Meet'
        ),
        -- If no Testing Plan, return N/A
        'N/A'
    ) AS 'Time TC-Rwork',

-- Meet Delivery Date
IF(
    req.end_test_time > 0 AND req.end_test_time IS NOT NULL,
    -- If a planned end time exists, compare it to the actual update time
    IF(req.end_test_time >= pend_test.updated_at, 'Meet', 'Not Meet'),
    -- If end_test_time is NULL or 0, return N/A
    'N/A'
) AS 'Meet Delivery Date'


FROM change_request AS req
LEFT JOIN applications AS apps ON apps.id = req.application_id
LEFT JOIN change_request_statuses AS curr_status ON curr_status.cr_id = req.id AND curr_status.`active` = '1'
LEFT JOIN statuses AS stat ON stat.id = curr_status.new_status_id
LEFT JOIN change_request_custom_fields AS req_csut_feld ON req_csut_feld.cr_id = req.id AND req_csut_feld.custom_field_id = ?

-- Design Estimation
LEFT JOIN designprogress_ranked designprogress ON designprogress.cr_id = req.id AND designprogress.rn = 1
LEFT JOIN pend_implementaion_ranked pend_implement ON pend_implement.cr_id = req.id AND pend_implement.rn = 1
LEFT JOIN pend_testing_ranked pend_test ON pend_test.cr_id = req.id AND pend_test.rn = 1
LEFT JOIN change_request_custom_fields AS ch_cus_fields ON ch_cus_fields.cr_id = req.id and ch_cus_fields.custom_field_id = 48
LEFT JOIN technical_implementation_ranked AS tetch_implt_start ON tetch_implt_start.cr_id = req.id  AND tetch_implt_start.rn = 1
-- Test inprogress
LEFT JOIN test_in_progress_ranked AS test_in_progress_rnk
    ON test_in_progress_rnk.cr_id = req.id
    AND test_in_progress_rnk.rn = IF(test_in_progress_rnk.total_entries > 1, 2, 1)
    -- Pending Rework after Test InProgress
  LEFT JOIN change_request_statuses AS pendig_rework_after_test_inprogress ON pendig_rework_after_test_inprogress.id = (SELECT id FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '14' and id > test_in_progress_rnk.id limit 1)
-- UAT In Progress
    LEFT JOIN uat_in_Progress_ranked AS uat_in_progress_rnk
        ON uat_in_progress_rnk.cr_id = req.id
        AND uat_in_progress_rnk.rn = IF(uat_in_progress_rnk.total_entries > 1, 2, 1)
-- Pending Rework after UAT InProgress
  LEFT JOIN change_request_statuses AS pendig_rework_after_uat_inprogress ON pendig_rework_after_uat_inprogress.id = (SELECT id FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '14' and id > uat_in_progress_rnk.id limit 1)
-- Sanity check
LEFT JOIN sanity_check_ranked AS sanity_check_rnk
        ON sanity_check_rnk.cr_id = req.id
        AND sanity_check_rnk.rn = IF(sanity_check_rnk.total_entries > 1, 2, 1)
-- Pending Rework after Sanity check
  LEFT JOIN change_request_statuses AS pendig_rework_after_sanity ON pendig_rework_after_sanity.id = (SELECT id FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '14' and id > sanity_check_rnk.id limit 1)
-- Health Check
LEFT JOIN health_check_ranked AS health_check_rnk
        ON health_check_rnk.cr_id = req.id
        AND health_check_rnk.rn = IF(health_check_rnk.total_entries > 1, 2, 1)
-- Health Check VS Rework
  LEFT JOIN change_request_statuses AS pendig_rework_after_health ON pendig_rework_after_health.id = (SELECT id FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = '14' and id > health_check_rnk.id limit 1)

LEFT JOIN change_request_custom_fields AS dpnd_on  ON dpnd_on.cr_id = req.id AND dpnd_on.custom_field_name = 'cr_type'
LEFT JOIN change_request_custom_fields AS rlvvnt  ON rlvvnt.cr_id = req.id AND rlvvnt.custom_field_name = 'cr_type'


-- Technical Team
LEFT JOIN change_request_statuses AS tetch_implt_latest ON tetch_implt_latest.id = (SELECT MAX(id) FROM change_request_statuses WHERE cr_id = req.id AND new_status_id = 10)
LEFT JOIN user_groups AS usr_grp ON usr_grp.user_id = tetch_implt_latest.user_id
LEFT JOIN `groups` AS technical_team ON technical_team.id = usr_grp.group_id

-- Category
LEFT JOIN change_request_custom_fields AS cut_felds_cagoy ON cut_felds_cagoy.cr_id = req.id AND cut_felds_cagoy.custom_field_id = '31'
LEFT JOIN categories AS categry ON categry.id = cut_felds_cagoy.custom_field_value

            LEFT JOIN change_request_custom_fields AS on_behalf  ON on_behalf.cr_id = req.id AND on_behalf.custom_field_name = 'on_behalf'

  WHERE 1=1
  ";

        $bindings = [];
        $bindings[] = $department_field_id;

        if ($from_date) {
            $query .= ' AND req.created_at >= ?';
            $bindings[] = $from_date . ' 00:00:00';
        }

        if ($top_management) {
            $query .= ' AND req.top_management = ?';
            $bindings[] = $top_management;
        }

        if ($on_hold) {
            $query .= ' AND req.hold = ?';
            $bindings[] = $on_hold;
        }

        if ($ticket_type) {
            $query .= ' AND dpnd_on.custom_field_value = ?';
            $bindings[] = $ticket_type;
        }

        if ($on_behalf) {
            $query .= ' AND on_behalf.custom_field_value = ?';
            $bindings[] = $on_behalf;
        }

        if ($to_date) {
            $query .= ' AND req.created_at <= ?';
            $bindings[] = $to_date . ' 23:59:59';
        }

        if ($unit_id) {
            $query .= ' AND req.unit_id = ?';
            $bindings[] = $unit_id;
        }

        if ($status_name) {
            $query .= ' AND stat.status_name = ?';
            $bindings[] = $status_name;
        }

        if ($department_id) {
            $department = \App\Models\RequesterDepartment::find($department_id);
            if ($department) {
                $query .= ' AND req_csut_feld.custom_field_value = ?';
                $bindings[] = $department->name;
            }
        }

        $query .= ' GROUP BY req.cr_no';

        // Execute query
        $results = collect(DB::select($query, $bindings));

        if ($request->has('export')) {
            return Excel::download(new KpiReportExport($results), 'kpi_report.xlsx');
        }

        // --- KPI Statistics Calculation ---
        $kpiColumns = [
            'Design Estimation',
            'Technical Estimation',
            'Testing Estimation',
            'Test in Progress',
            'UAT In Progress',
            'Sanity Check',
            'Healthy Check',
            'Time -Required Info',
            'Rework Time-Design Rework',
            'Time Rework',
            'Time TC-Rwork',
            'Meet Delivery Date',
        ];

        $kpiStats = [];

        foreach ($kpiColumns as $column) {
            // Count rows that have a valid value (not 'NA', if applicable, though SQL returns 'NA' for some)
            // SQL returns 'Meet', 'Not Meet', or 'NA'

            $totalApplicable = $results->filter(function ($row) use ($column) {
                return isset($row->{$column}) && $row->{$column} !== 'NA';
            })->count();

            $meetCount = $results->filter(function ($row) use ($column) {
                return isset($row->{$column}) && $row->{$column} === 'Meet';
            })->count();

            $percentage = $totalApplicable > 0 ? round(($meetCount / $totalApplicable) * 100, 2) : 0;

            $kpiStats[] = [
                'name' => $column,
                'total' => $totalApplicable,
                'meet' => $meetCount,
                'percentage' => $percentage,
            ];
        }
        // ----------------------------------

        // Pagination
        $perPage = 10;
        $page = $request->get('page', 1);
        $currentPageItems = $results->slice(($page - 1) * $perPage, $perPage)->values();

        $paginatedResults = new LengthAwarePaginator(
            $currentPageItems,
            $results->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $units = Unit::all();
        $statuses = DB::table('statuses')->select('status_name')->distinct()->orderBy('status_name')->get();
        $departments = \App\Models\RequesterDepartment::where('active', '1')->get();

        return view('reports.kpi_report', [
            'results' => $paginatedResults,
            'units' => $units,
            'statuses' => $statuses,
            'departments' => $departments,
            'kpiStats' => $kpiStats, // Pass stats to view
        ]);
    }
}
