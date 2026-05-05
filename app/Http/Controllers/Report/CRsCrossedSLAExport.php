<?php


namespace App\Http\Controllers\Report;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CRsCrossedSLAExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        $query = "
            WITH pend_design_ranked AS (
                SELECT cr_id, id, created_at, updated_at, user_id,
                ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
                FROM change_request_statuses
                WHERE new_status_id = 7
            ),
            pend_implementaion_ranked AS (
                SELECT cr_id, id, created_at, updated_at, user_id, group_id,
                ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
                FROM change_request_statuses
                WHERE new_status_id = 8
            ),
            pend_testing_ranked AS (
                SELECT cr_id, id, created_at, updated_at, user_id, group_id,
                ROW_NUMBER() OVER (PARTITION BY cr_id ORDER BY id DESC) AS rn
                FROM change_request_statuses
                WHERE new_status_id = 11
            )

            SELECT 
               
                req.cr_no,
                apps.`name` AS Applications,
                req.title,
                flow.`name` AS WorkflowType,
                'N\A' as 'On Behalf',
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
                req.start_design_time AS PendingDesignPlannedStart,
                req.end_design_time AS PendingDesignPlannedEnd,
                pend_design.created_at AS PendingDesignActualStart,
                pend_design.updated_at AS PendingDesignActualEnd,
                pend_design_assig_usr.`name` AS PendingDesignAssignedMember,
                req.start_develop_time AS PendingImplementationPlannedStart,
                req.end_develop_time AS PendingImplementationPlannedEnd,
                pend_implement.created_at AS PendingImplementationActualStart,
                pend_implement.updated_at AS PendingImplementationActualEnd,
                pend_imple_assig_usr.`name` AS PendingImplementationAssignedMember,
                grop.title AS AssignedGroup,
                req.start_test_time AS PendingTestingPlannedStart,
                req.end_test_time AS PendingTestingPlannedEnd,
                pend_test.created_at AS PendingTestingActualStart,
                pend_test.updated_at AS PendingTestingActualEnd,
                pend_test_assig_usr.`name` AS PendingTestingAssignedMember,
                'Not Found' AS CRPlannedDeliveryDate,
                IFNULL(req.end_test_time, req.end_develop_time) AS ExpectedDeliveryDate,
                req.requester_name,
                req.division_manager,
                'Not Found' AS RequesterDivision,
                'Not Found' AS RequesterSector
            FROM change_request AS req
            LEFT JOIN applications AS apps ON apps.id = req.application_id
            LEFT JOIN workflow_type AS flow ON flow.id = req.workflow_type_id
            LEFT JOIN change_request_custom_fields AS dpnd_on ON dpnd_on.cr_id = req.id AND dpnd_on.custom_field_name = 'cr_type'
            LEFT JOIN pend_design_ranked pend_design ON pend_design.cr_id = req.id AND pend_design.rn = 1
            LEFT JOIN pend_implementaion_ranked pend_implement ON pend_implement.cr_id = req.id AND pend_implement.rn = 1
            LEFT JOIN pend_testing_ranked pend_test ON pend_test.cr_id = req.id AND pend_test.rn = 1
            LEFT JOIN users AS pend_design_assig_usr ON pend_design_assig_usr.id = pend_design.user_id
            LEFT JOIN users AS pend_imple_assig_usr ON pend_imple_assig_usr.id = pend_implement.user_id
            LEFT JOIN `groups` AS grop ON grop.id = pend_implement.group_id
            LEFT JOIN users AS pend_test_assig_usr ON pend_test_assig_usr.id = pend_test.user_id

            WHERE 
                (req.start_design_time IS NOT NULL AND pend_design.created_at IS NOT NULL AND req.start_design_time < pend_design.created_at)
                OR (req.end_design_time IS NOT NULL AND pend_design.updated_at IS NOT NULL AND req.end_design_time < pend_design.updated_at)
                OR (req.start_develop_time IS NOT NULL AND pend_implement.created_at IS NOT NULL AND req.start_develop_time < pend_implement.created_at)
                OR (req.end_develop_time IS NOT NULL AND pend_implement.updated_at IS NOT NULL AND req.end_develop_time < pend_implement.updated_at)
                OR (req.start_test_time IS NOT NULL AND pend_test.created_at IS NOT NULL AND req.start_test_time < pend_test.created_at)
                OR (req.end_test_time IS NOT NULL AND pend_test.updated_at IS NOT NULL AND req.end_test_time < pend_test.updated_at)

            GROUP BY req.cr_no;
        ";

        return collect(DB::select($query));
    }

    public function headings(): array
    {
        return [
            
            'CR No',
            'Applications',
            'Title',
            'Workflow Type',
            'On Behalf',
            'On Hold',
            'Top Management',
            'Ticket Type',
            'Pending Design Planned Start',
            'Pending Design Planned End',
            'Pending Design Actual Start',
            'Pending Design Actual End',
            'Pending Design Assigned Member',
            'Pending Implementation Planned Start',
            'Pending Implementation Planned End',
            'Pending Implementation Actual Start',
            'Pending Implementation Actual End',
            'Pending Implementation Assigned Member',
            'Assigned Group',
            'Pending Testing Planned Start',
            'Pending Testing Planned End',
            'Pending Testing Actual Start',
            'Pending Testing Actual End',
            'Pending Testing Assigned Member',
            'CR Planned Delivery Date',
            'Expected Delivery Date',
            'Requester Name',
            'Division Manager',
            'Requester Division',
            'Requester Sector'
        ];
    }
}
