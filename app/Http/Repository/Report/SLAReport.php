<?php

declare(strict_types=1);

namespace App\Http\Repository\Report;

use App\Traits\SqlTrait;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SLAReport
{
    use SqlTrait;

    public function SLAQuery(): string
    {
        return "
WITH cr_current_status AS (
    SELECT
        crs.cr_id,
        GROUP_CONCAT(s.status_name SEPARATOR ', ') AS status
    FROM change_request cr
             JOIN change_request_statuses crs ON crs.cr_id = cr.id AND crs.active = '1'
             JOIN statuses s ON s.id = crs.new_status_id
    GROUP BY crs.cr_id
), cr_category AS (
    SELECT DISTINCT (cr_cf.cr_id), categories.name
    FROM change_request_custom_fields cr_cf
             JOIN categories ON categories.id = cr_cf.custom_field_value AND cr_cf.custom_field_name = 'category_id'
), cr_unit AS (
    SELECT DISTINCT (cr_cf.cr_id), units.id
    FROM change_request_custom_fields cr_cf
             JOIN units ON units.id = cr_cf.custom_field_value AND cr_cf.custom_field_name = 'unit_id'
), cr_department AS (
    SELECT DISTINCT (cr_cf.cr_id), department.id
    FROM change_request_custom_fields cr_cf
             JOIN requester_departments AS department ON department.id = cr_cf.custom_field_value AND cr_cf.custom_field_name = 'department_id'
), cr_type AS (
    SELECT DISTINCT (cr_cf.cr_id), cr_cf.custom_field_value AS type
    FROM change_request_custom_fields cr_cf
             WHERE cr_cf.custom_field_name = 'cr_type'
), cr_on_behalf AS (
    SELECT DISTINCT (cr_cf.cr_id), cr_cf.custom_field_value AS on_behaf
    FROM change_request_custom_fields cr_cf
    WHERE cr_cf.custom_field_name = 'on_behalf'
), cr_requester AS (
    SELECT DISTINCT (cr_cf.cr_id), cr_cf.custom_field_value AS requester
    FROM change_request_custom_fields cr_cf
             WHERE cr_cf.custom_field_name = 'requester_name'
), cr_target_system AS (
    SELECT DISTINCT (cr_cf.cr_id), applications.name
    FROM change_request_custom_fields cr_cf
             JOIN applications ON applications.id = cr_cf.custom_field_value AND cr_cf.custom_field_name = 'application_id'
), cr_technical_group AS (
    SELECT DISTINCT (cr_cf.cr_id), `groups`.title
    FROM change_request_custom_fields cr_cf
    JOIN `groups`  ON `groups`.id = cr_cf.custom_field_value AND cr_cf.custom_field_name = 'tech_group_id'
), business_estimation AS (
    SELECT
        cr_id,
#         SUM(
#                 DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
#                     - IF(DAYOFWEEK(created_at) = 6, 1, 0)
#                     - IF(DAYOFWEEK(created_at) = 7, 1, 0)
#         ) AS estimation,
        IF(SUM(
                   DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
                       - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
                       - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
                       - IF(DAYOFWEEK(created_at) = 6, 1, 0)
                       - IF(DAYOFWEEK(created_at) = 7, 1, 0)
           )<= 1, 'Meet SLA', 'No Meet SLA') AS label
    FROM change_request_statuses
    WHERE new_status_id = 18 -- Business Validation Status
    GROUP BY cr_id
), testing_estimation AS (
    SELECT cr_cf_start_test_time.cr_id,
#            cr_cf_start_test_time.custom_field_value                                                             AS start,
#            cr_cf_end_test_time.custom_field_value                                                               AS end,
#            DATEDIFF(IFNULL(cr_cf_end_test_time.custom_field_value, NOW()), cr_cf_start_test_time.custom_field_value) + 1
#                - FLOOR((DATEDIFF(IFNULL(cr_cf_end_test_time.custom_field_value, NOW()), cr_cf_start_test_time.custom_field_value) + DAYOFWEEK(cr_cf_start_test_time.custom_field_value) + 1) / 7)
#                - FLOOR((DATEDIFF(IFNULL(cr_cf_end_test_time.custom_field_value, NOW()), cr_cf_start_test_time.custom_field_value) + DAYOFWEEK(cr_cf_start_test_time.custom_field_value)) / 7)
#                - IF(DAYOFWEEK(cr_cf_start_test_time.custom_field_value) = 6, 1, 0)
#                - IF(DAYOFWEEK(cr_cf_start_test_time.custom_field_value) = 7, 1, 0) AS estimation,
           IF(DATEDIFF(IFNULL(cr_cf_end_test_time.custom_field_value, NOW()), cr_cf_start_test_time.custom_field_value) + 1
                  - FLOOR((DATEDIFF(IFNULL(cr_cf_end_test_time.custom_field_value, NOW()), cr_cf_start_test_time.custom_field_value) + DAYOFWEEK(cr_cf_start_test_time.custom_field_value) + 1) / 7)
                  - FLOOR((DATEDIFF(IFNULL(cr_cf_end_test_time.custom_field_value, NOW()), cr_cf_start_test_time.custom_field_value) + DAYOFWEEK(cr_cf_start_test_time.custom_field_value)) / 7)
                  - IF(DAYOFWEEK(cr_cf_start_test_time.custom_field_value) = 6, 1, 0)
                  - IF(DAYOFWEEK(cr_cf_start_test_time.custom_field_value) = 7, 1, 0) <= 2, 'Meet Test estimation SLA', 'No Meet Testing estimation SLA') AS label
    FROM change_request_custom_fields cr_cf_start_test_time
             JOIN change_request_custom_fields cr_cf_end_test_time
                  ON cr_cf_end_test_time.cr_id = cr_cf_start_test_time.cr_id
                      AND cr_cf_start_test_time.custom_field_name = 'start_test_time'
                      AND cr_cf_end_test_time.custom_field_name = 'end_test_time'
), design_estimation AS (
    SELECT cr_cf_start_design_time.cr_id,
#            cr_cf_start_design_time.custom_field_value                                                             AS start,
#            cr_cf_end_design_time.custom_field_value                                                               AS end,
#            DATEDIFF(IFNULL(cr_cf_end_design_time.custom_field_value, NOW()), cr_cf_start_design_time.custom_field_value) + 1
#                - FLOOR((DATEDIFF(IFNULL(cr_cf_end_design_time.custom_field_value, NOW()), cr_cf_start_design_time.custom_field_value) + DAYOFWEEK(cr_cf_start_design_time.custom_field_value) + 1) / 7)
#                - FLOOR((DATEDIFF(IFNULL(cr_cf_end_design_time.custom_field_value, NOW()), cr_cf_start_design_time.custom_field_value) + DAYOFWEEK(cr_cf_start_design_time.custom_field_value)) / 7)
#                - IF(DAYOFWEEK(cr_cf_start_design_time.custom_field_value) = 6, 1, 0)
#                - IF(DAYOFWEEK(cr_cf_start_design_time.custom_field_value) = 7, 1, 0) AS estimation,
           IF(DATEDIFF(IFNULL(cr_cf_end_design_time.custom_field_value, NOW()), cr_cf_start_design_time.custom_field_value) + 1
                  - FLOOR((DATEDIFF(IFNULL(cr_cf_end_design_time.custom_field_value, NOW()), cr_cf_start_design_time.custom_field_value) + DAYOFWEEK(cr_cf_start_design_time.custom_field_value) + 1) / 7)
                  - FLOOR((DATEDIFF(IFNULL(cr_cf_end_design_time.custom_field_value, NOW()), cr_cf_start_design_time.custom_field_value) + DAYOFWEEK(cr_cf_start_design_time.custom_field_value)) / 7)
                  - IF(DAYOFWEEK(cr_cf_start_design_time.custom_field_value) = 6, 1, 0)
                  - IF(DAYOFWEEK(cr_cf_start_design_time.custom_field_value) = 7, 1, 0) <= 2, 'Meet Design estimation SLA', 'No Meet Design estimation SLA') AS label
    FROM change_request_custom_fields cr_cf_start_design_time
             JOIN change_request_custom_fields cr_cf_end_design_time
                  ON cr_cf_end_design_time.cr_id = cr_cf_start_design_time.cr_id
                      AND cr_cf_start_design_time.custom_field_name = 'start_design_time'
                      AND cr_cf_end_design_time.custom_field_name = 'end_design_time'
), technical_estimation AS (
    SELECT cr_cf_start_develop_time.cr_id,
#            cr_cf_start_develop_time.custom_field_value                                                             AS start,
#            cr_cf_end_develop_time.custom_field_value                                                               AS end,
#            DATEDIFF(IFNULL(cr_cf_end_develop_time.custom_field_value, NOW()), cr_cf_start_develop_time.custom_field_value) + 1
#                - FLOOR((DATEDIFF(IFNULL(cr_cf_end_develop_time.custom_field_value, NOW()), cr_cf_start_develop_time.custom_field_value) + DAYOFWEEK(cr_cf_start_develop_time.custom_field_value) + 1) / 7)
#                - FLOOR((DATEDIFF(IFNULL(cr_cf_end_develop_time.custom_field_value, NOW()), cr_cf_start_develop_time.custom_field_value) + DAYOFWEEK(cr_cf_start_develop_time.custom_field_value)) / 7)
#                - IF(DAYOFWEEK(cr_cf_start_develop_time.custom_field_value) = 6, 1, 0)
#                - IF(DAYOFWEEK(cr_cf_start_develop_time.custom_field_value) = 7, 1, 0) AS estimation,
           IF(DATEDIFF(IFNULL(cr_cf_end_develop_time.custom_field_value, NOW()), cr_cf_start_develop_time.custom_field_value) + 1
                  - FLOOR((DATEDIFF(IFNULL(cr_cf_end_develop_time.custom_field_value, NOW()), cr_cf_start_develop_time.custom_field_value) + DAYOFWEEK(cr_cf_start_develop_time.custom_field_value) + 1) / 7)
                  - FLOOR((DATEDIFF(IFNULL(cr_cf_end_develop_time.custom_field_value, NOW()), cr_cf_start_develop_time.custom_field_value) + DAYOFWEEK(cr_cf_start_develop_time.custom_field_value)) / 7)
                  - IF(DAYOFWEEK(cr_cf_start_develop_time.custom_field_value) = 6, 1, 0)
                  - IF(DAYOFWEEK(cr_cf_start_develop_time.custom_field_value) = 7, 1, 0) <= 2, 'Meet Develop estimation SLA', 'No Meet Develop estimation SLA') AS label
    FROM change_request_custom_fields cr_cf_start_develop_time
             JOIN change_request_custom_fields cr_cf_end_develop_time
                  ON cr_cf_end_develop_time.cr_id = cr_cf_start_develop_time.cr_id
                      AND cr_cf_start_develop_time.custom_field_name = 'start_develop_time'
                      AND cr_cf_end_develop_time.custom_field_name = 'end_develop_time'
), pending_design_document_approval AS (
    SELECT
        cr_id,
#         SUM(
#                 DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
#                     - IF(DAYOFWEEK(created_at) = 6, 1, 0)
#                     - IF(DAYOFWEEK(created_at) = 7, 1, 0)
#         ) AS estimation,
        IF(SUM(
                DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
                    - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
                    - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
                    - IF(DAYOFWEEK(created_at) = 6, 1, 0)
                    - IF(DAYOFWEEK(created_at) = 7, 1, 0)
        )<= 2, 'Meet', 'No Meet') AS label
    FROM change_request_statuses
    WHERE new_status_id = 72 -- Pending QC Design Document Approval
    GROUP BY cr_id
), technical_test_case_approval AS (
    SELECT
        cr_id,
#         SUM(
#                 DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
#                     - IF(DAYOFWEEK(created_at) = 6, 1, 0)
#                     - IF(DAYOFWEEK(created_at) = 7, 1, 0)
#         ) AS estimation,
        IF(SUM(
                DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
                    - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
                    - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
                    - IF(DAYOFWEEK(created_at) = 6, 1, 0)
                    - IF(DAYOFWEEK(created_at) = 7, 1, 0)
        )<= 1, 'Meet', 'No Meet') AS label
    FROM change_request_statuses
    WHERE new_status_id = 39 -- Technical Test Case Approval
    GROUP BY cr_id
), design_test_case_approval AS (
    SELECT
        cr_id,
#         SUM(
#                 DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
#                     - IF(DAYOFWEEK(created_at) = 6, 1, 0)
#                     - IF(DAYOFWEEK(created_at) = 7, 1, 0)
#         ) AS estimation,
        IF(SUM(
                DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
                    - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
                    - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
                    - IF(DAYOFWEEK(created_at) = 6, 1, 0)
                    - IF(DAYOFWEEK(created_at) = 7, 1, 0)
        )<= 1, 'Meet', 'No Meet') AS label
    FROM change_request_statuses
    WHERE new_status_id = 40 -- Design Test Case Approval
    GROUP BY cr_id
), business_test_case_approval AS (
    SELECT
        cr_id,
#         SUM(
#                 DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
#                     - IF(DAYOFWEEK(created_at) = 6, 1, 0)
#                     - IF(DAYOFWEEK(created_at) = 7, 1, 0)
#         ) AS estimation,
        IF(SUM(
                DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
                    - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
                    - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
                    - IF(DAYOFWEEK(created_at) = 6, 1, 0)
                    - IF(DAYOFWEEK(created_at) = 7, 1, 0)
        )<= 3, 'Meet', 'No Meet') AS label
    FROM change_request_statuses
    WHERE new_status_id = 41 -- Business Test Case Approval
    GROUP BY cr_id
), rollback AS (
    SELECT
        cr_id,
#         SUM(
#                 DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
#                     - IF(DAYOFWEEK(created_at) = 6, 1, 0)
#                     - IF(DAYOFWEEK(created_at) = 7, 1, 0)
#         ) AS estimation,
        IF(SUM(
                DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
                    - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
                    - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
                    - IF(DAYOFWEEK(created_at) = 6, 1, 0)
                    - IF(DAYOFWEEK(created_at) = 7, 1, 0)
        )<= 1, 'Meet', 'No Meet') AS label
    FROM change_request_statuses
    WHERE new_status_id = 134 -- rollback
    GROUP BY cr_id
), sanity_check AS (
    SELECT
        cr_id,
#         SUM(
#                 DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
#                     - IF(DAYOFWEEK(created_at) = 6, 1, 0)
#                     - IF(DAYOFWEEK(created_at) = 7, 1, 0)
#         ) AS estimation,
        IF(SUM(
                DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
                    - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
                    - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
                    - IF(DAYOFWEEK(created_at) = 6, 1, 0)
                    - IF(DAYOFWEEK(created_at) = 7, 1, 0)
        )<= 5, 'Meet', 'No Meet') AS label
    FROM change_request_statuses
    WHERE new_status_id = 21 -- Sanity Check
    GROUP BY cr_id
), health_check AS (
    SELECT
        cr_id,
#         SUM(
#                 DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
#                     - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
#                     - IF(DAYOFWEEK(created_at) = 6, 1, 0)
#                     - IF(DAYOFWEEK(created_at) = 7, 1, 0)
#         ) AS estimation,
        IF(SUM(
                DATEDIFF(IFNULL(updated_at, NOW()), created_at) + 1
                    - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at) + 1) / 7)
                    - FLOOR((DATEDIFF(IFNULL(updated_at, NOW()), created_at) + DAYOFWEEK(created_at)) / 7)
                    - IF(DAYOFWEEK(created_at) = 6, 1, 0)
                    - IF(DAYOFWEEK(created_at) = 7, 1, 0)
        )<= 1, 'Meet', 'No Meet') AS label
    FROM change_request_statuses
    WHERE new_status_id = 48 -- Health Check
    GROUP BY cr_id
)

SELECT cr.cr_no,
       IFNULL(c.name, 'N/A') AS category,
       DATE(cr.created_at) AS cr_date,
       cr_requester.requester AS requester,
       ap.name AS targeted_system,
       IFNULL(tech_team.title, 'N/A')  AS technical_team,
       IFNULL(cr_cs.status, 'N/A')  AS current_status,
       CASE
           WHEN cr_design_estimation.custom_field_value = 0 THEN 'No Design'
           WHEN cr_design_estimation.custom_field_value > 0 THEN 'Design'
           ELSE 'N/A'
           END AS design_status,
       CASE
           WHEN cr_cf_testable.custom_field_value = 0 THEN 'Not Testable'
           WHEN cr_cf_testable.custom_field_value = 1 THEN 'Testable'
           ELSE 'N/A'
           END AS testable_status,
       IFNULL(business_estimation.label, 'N/A') AS business_estimation,
       IFNULL(testing_estimation.label, 'N/A') AS testing_estimation,
       IFNULL(design_estimation.label, 'N/A') AS design_estimation,
       IFNULL(technical_estimation.label, 'N/A') AS technical_estimation,
       IFNULL(pdda.label, 'N/A') AS design_document_approval,
       IFNULL(ttca.label, 'N/A') AS technical_test_case_approval,
       IFNULL(dtca.label, 'N/A') AS design_test_case_approval,
       IFNULL(btca.label, 'N/A') AS business_test_case_approval,
       IFNULL(rollback.label, 'N/A') AS rollback,
       IFNULL(sanity_check.label, 'N/A') AS sanity_check,
       IFNULL(health_check.label, 'N/A') AS health_check
FROM change_request cr
         LEFT JOIN cr_category c ON c.cr_id = cr.id
         LEFT JOIN cr_requester ON cr_requester.cr_id = cr.id
         LEFT JOIN cr_target_system ap ON ap.cr_id = cr.id
         LEFT JOIN cr_technical_group tech_team ON tech_team.cr_id = cr.id
         LEFT JOIN change_request_custom_fields cr_design_estimation ON cr_design_estimation.cr_id = cr.id AND cr_design_estimation.custom_field_name = 'design_estimation'
         LEFT JOIN change_request_custom_fields cr_cf_testable ON cr_cf_testable.cr_id = cr.id AND cr_cf_testable.custom_field_name = 'testable'
         LEFT JOIN cr_current_status cr_cs ON cr_cs.cr_id = cr.id
         LEFT JOIN business_estimation ON business_estimation.cr_id = cr.id
         LEFT JOIN testing_estimation ON testing_estimation.cr_id = cr.id
         LEFT JOIN design_estimation ON design_estimation.cr_id =cr.id
         LEFT JOIN technical_estimation ON technical_estimation.cr_id = cr.id
         LEFT JOIN pending_design_document_approval AS pdda ON pdda.cr_id = cr.id
         LEFT JOIN technical_test_case_approval AS ttca ON ttca.cr_id = cr.id
         LEFT JOIN design_test_case_approval AS dtca ON dtca.cr_id = cr.id
         LEFT JOIN business_test_case_approval AS btca ON btca.cr_id = cr.id
         LEFT JOIN rollback ON rollback.cr_id = cr.id
         LEFT JOIN sanity_check ON sanity_check.cr_id = cr.id
         LEFT JOIN health_check ON health_check.cr_id = cr.id
         LEFT JOIN cr_unit ON cr_unit.cr_id = cr.id
         LEFT JOIN cr_department ON cr_department.cr_id = cr.id
         LEFT JOIN cr_type ON cr_type.cr_id = cr.id
         LEFT JOIN cr_on_behalf ON cr_on_behalf.cr_id = cr.id

                ";
    }

    public function SLAPReportPaginated(): LengthAwarePaginator
    {
        [$query, $bindings] = $this->addFiltersToQuery($this->SLAQuery());

        return $this->sqlPaginated($query, $bindings);
    }

    /**
     * @return array<int, object>
     */
    public function SLAPReportAll(): array
    {
        [$query, $bindings] = $this->addFiltersToQuery($this->SLAQuery());

        return DB::select($query, $bindings);
    }

    public function addFiltersToQuery(string $statement): array
    {
        $filter_statement = [];
        $bindings = [];

        // filters
        $unit_id = request()->query('unit_id');
        $department_id = request()->query('department_id');
        $from_date = request()->query('from_date');
        $to_date = request()->query('to_date');
        $status = request()->query('status_name');
        $cr_type = request()->query('ticket_type') ?: request()->query('cr_type');
        $on_hold = request()->query('on_hold');
        $top_management = request()->query('top_management');
        $on_behalf = request()->query('on_behalf');

        if ($unit_id) {
            $filter_statement[] = ' cr_unit.id = ?';
            $bindings[] = $unit_id;
        }

        if ($department_id) {
            $filter_statement[] = ' cr_department.id = ?';
            $bindings[] = $department_id;
        }

        if ($from_date) {
            $filter_statement[] = ' DATE(cr.created_at) >= ?';
            $bindings[] = Carbon::parse($from_date)->format('Y-m-d');
        }

        if ($to_date) {
            $filter_statement[] = ' DATE(cr.created_at) <= ?';
            $bindings[] = Carbon::parse($to_date)->format('Y-m-d');
        }

        if ($status) {
            $filter_statement[] = " cr_cs.status LIKE CONCAT('%', ?, '%')";
            $bindings[] = $status;
        }

        if ($cr_type) {
            $filter_statement[] = ' cr_type.type = ?';
            $bindings[] = $cr_type;
        }

        if ($on_hold === '1') {
            $filter_statement[] = ' cr.hold = 1';
        }

        if ($top_management === '1') {
            $filter_statement[] = " cr.top_management = '1'";
        }

        if ($on_behalf === '1') {
            $filter_statement[] = ' cr_on_behalf.on_behaf = 1';
        }

        if (count($filter_statement)) {
            $statement .= ' WHERE ' . implode(' AND ', $filter_statement);
        }

        $statement .= ' ORDER BY cr.id';

        return [$statement, $bindings];
    }
}
