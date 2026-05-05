<?php 



namespace App\Http\Controllers\Report;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
      
class AllCrsByRequesterExport implements FromCollection, WithHeadings
{
    public function collection()
    {
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
    apps.`name` AS Applications,
    req.title,
    flow.`name` AS 'Workflow Type',
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
    'Not Found' AS 'CR Type',
    'NA' AS 'Vendor Name',
    GROUP_CONCAT(DISTINCT stat.status_name ORDER BY stat.status_name SEPARATOR ', ') AS `Current Status`,
    busins_val.created_at AS 'Business Validation Status Start Date',
    busins_val.updated_at AS 'Business Validation Status End Date',
    'Not Found' AS 'Design Assigned Member Level',
    req.start_design_time AS 'Pending Design Planned Start',
    req.end_design_time AS 'Pending Design Planned End',
    pend_design.created_at AS 'PendingDesignActualStart',
    pend_design.updated_at AS 'PendingDesignActualEnd',
    pend_design_assig_usr.`name` AS 'Pending Design Assigned Member',
    req.start_develop_time AS 'Technical Estimation Start Date',
    req.end_develop_time AS 'Technical Estimation End Date',
    pend_implement.created_at AS 'PendingImplementationActualStart',
    pend_implement.updated_at AS 'PendingImplementationActualEnd',
    pend_imple_assig_usr.`name` AS 'Developer Name',
    grop.title AS 'Technical Team',
    TIMESTAMPDIFF(MINUTE, ch_cus_fields.created_at, pend_implement.created_at) AS 'Pending Design Assigned Member Duration IN MINUTES',
    'Not Found' AS 'Dev Assigned Member Level',
    req.start_test_time AS 'Testing Estimation Start Date',
    req.end_test_time AS 'Testing Estimation End Date',
    pend_test.created_at AS 'Pending Testing Start',
    pend_test.updated_at AS 'Pending Testing End',
    TIMESTAMPDIFF(MINUTE, ch_cus_fields_tst.created_at, pend_test.created_at) AS 'Pending Design Assigned Member Duration IN MINUTES',
    pend_test_assig_usr.`name` AS 'Testing Team Member',
    'Not Found' AS 'Testing Assigned Member Level',
    chang_stat_pend_prod_deploy.created_at AS 'Pending Production Deployment Start Date',
    chang_stat_pend_prod_deploy.updated_at AS 'Pending Production Deployment End Date',
    sanity_check.created_at AS 'Sanity Check Start',
    sanity_check.updated_at AS 'Sanity Check End',
    chang_stat_pend_busen_fedbk.created_at AS 'Pending Business Feedback Start Date',
    chang_stat_pend_busen_fedbk.updated_at AS 'Pending Business Feedback End Date',
    chang_stat_busen_tst_cas_appval.created_at AS 'Business Test Case Approval Start Date',
    chang_stat_busen_tst_cas_appval.updated_at AS 'Business Test Case Approval End Date',
    chang_stat_busen_uat_sign_off.created_at AS 'Business UAT Sign Off Start Date',
    chang_stat_busen_uat_sign_off.updated_at AS 'Business UAT Sign Off End Date',
    delivred_cr.created_at AS 'Delivered CR Start',
    delivred_cr.updated_at AS 'Delivered CR Start',
    IFNULL(req.end_test_time, req.end_develop_time) AS 'Expected Delivery date',
    req.requester_name,
    req.division_manager,
    'Not Found' AS 'Requseter division',
    'Not Found' AS 'Requester Sector',
    rejt_reason.name AS 'Rejection Reasons'

FROM change_request AS req
LEFT JOIN applications AS apps ON apps.id = req.application_id
LEFT JOIN workflow_type AS flow ON flow.id = req.workflow_type_id
LEFT JOIN change_request_statuses AS curr_status ON curr_status.cr_id = req.id AND curr_status.`active` = '1'
LEFT JOIN statuses AS stat ON stat.id = curr_status.new_status_id
LEFT JOIN pend_design_ranked pend_design ON pend_design.cr_id = req.id AND pend_design.rn = 1
LEFT JOIN pend_implementaion_ranked pend_implement ON pend_implement.cr_id = req.id AND pend_implement.rn = 1
LEFT JOIN pend_testing_ranked pend_test ON pend_test.cr_id = req.id AND pend_test.rn = 1
LEFT JOIN busins_val_ranked busins_val ON busins_val.cr_id = req.id AND busins_val.rn = 1
LEFT JOIN sanity_check_ranked sanity_check ON sanity_check.cr_id = req.id AND sanity_check.rn = 1
LEFT JOIN delivred_cr_ranked delivred_cr ON delivred_cr.cr_id = req.id AND delivred_cr.rn = 1
LEFT JOIN change_request_custom_fields AS ch_cus_fields ON ch_cus_fields.cr_id = req.id AND ch_cus_fields.custom_field_id = 46
LEFT JOIN change_request_custom_fields AS ch_cus_fields_tst ON ch_cus_fields_tst.cr_id = req.id AND ch_cus_fields_tst.custom_field_id = 47
LEFT JOIN change_request_custom_fields AS dpnd_on ON dpnd_on.cr_id = req.id AND dpnd_on.custom_field_name = 'cr_type'
LEFT JOIN change_request_statuses AS chang_stat_pend_prod_deploy ON chang_stat_pend_prod_deploy.cr_id = req.id AND chang_stat_pend_prod_deploy.new_status_id = 17
LEFT JOIN change_request_statuses AS chang_stat_pend_busen_fedbk ON chang_stat_pend_busen_fedbk.cr_id = req.id AND chang_stat_pend_busen_fedbk.new_status_id = 79
LEFT JOIN change_request_statuses AS chang_stat_busen_tst_cas_appval ON chang_stat_busen_tst_cas_appval.cr_id = req.id AND chang_stat_busen_tst_cas_appval.new_status_id = 41
LEFT JOIN change_request_statuses AS chang_stat_busen_uat_sign_off ON chang_stat_busen_uat_sign_off.cr_id = req.id AND chang_stat_busen_uat_sign_off.new_status_id = 44
LEFT JOIN users AS pend_design_assig_usr ON pend_design_assig_usr.id = pend_design.user_id
LEFT JOIN users AS pend_imple_assig_usr ON pend_imple_assig_usr.id = pend_implement.user_id
LEFT JOIN `groups` AS grop ON grop.id = pend_implement.group_id
LEFT JOIN users AS pend_test_assig_usr ON pend_test_assig_usr.id = pend_test.user_id
LEFT JOIN change_request_custom_fields AS chang_custm_rejt_reason ON chang_custm_rejt_reason.cr_id = req.id AND chang_custm_rejt_reason.custom_field_id = 63
LEFT JOIN rejection_reasons AS rejt_reason ON rejt_reason.id = chang_custm_rejt_reason.custom_field_value 

GROUP BY req.cr_no;
";


        $results = DB::select($query);

        return collect($results);
    }

    public function headings(): array
    {
        return [
            
            'cr_no',
            'Applications',
            'title',
            'Workflow Type',
            'On Behalf',
            'On Hold',
            'Top Management',
            'Ticket Type',
            'CR Type',
            'CR Type',
            'Vendor Name',
            'Current Status',
            'Business Validation Status Start Date',
            'Business Validation Status End Date',
            'Design Assigned Member Level',
            'Pending Design Planned Start',
            'Pending Design Planned End',
            'PendingDesignActualStart',
            'PendingDesignActualEnd',
            'Pending Design Assigned Member',
            'Technical Estimation Start Date',
            'Technical Estimation End Date',
            'PendingImplementationActualStart',
            'PendingImplementationActualEnd',
            'Developer Name',
            'Technical Team',
            'Pending Design Assigned Member Duration IN MINUTES',
            'Dev Assigned Member Level',
            'Testing Estimation Start Date',
            'Testing Estimation End Date',
            'Pending Testing Start',
            'Pending Testing End',
            'Pending Design Assigned Member Duration IN MINUTES',
            'Testing Team Member',
            'Testing Assigned Member Level',
            'Pending Production Deployment Start Date',
            'Pending Production Deployment End Date',
            'Sanity Check Start',
            'Sanity Check End',
            'Pending Business Feedback Start Date',
            'Pending Business Feedback End Date',
            'Business Test Case Approval Start Date',
            'Business Test Case Approval End Date',
            'Business UAT Sign Off Start Date',
            'Business UAT Sign Off End Date',
            'Delivered CR Start',
            'Delivered CR Start',
            'Expected Delivery date',
            'requester_name',
            'division_manager',
            'Requseter division',
            'Requester Sector',
            'Rejection Reasons'
        ];
    }
}

?>