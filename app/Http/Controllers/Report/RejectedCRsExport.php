<?php


namespace App\Http\Controllers\Report;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RejectedCRsExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        $query = "
               SELECT 
        req.cr_no,
       
        apps.`name` 'Applications',
        req.title,
        flow.`name` 'CR Type',
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
    LEFT JOIN change_request_custom_fields AS dpnd_on ON dpnd_on.cr_id = req.id AND dpnd_on.custom_field_name = 'cr_type'
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


     LEFT JOIN change_request_statuses as chang_stat_closed ON  chang_stat_closed.cr_id = req.id and  chang_stat_closed.new_status_id = 49
  LEFT JOIN change_request_statuses as chang_stat_delivered ON  chang_stat_delivered.cr_id = req.id and  chang_stat_delivered.new_status_id = 27
     LEFT JOIN change_request_statuses as chang_stat_delivery ON  chang_stat_delivery.cr_id = req.id and  chang_stat_delivery.new_status_id = 60
     LEFT JOIN change_request_custom_fields as chang_custm_rejt_reason ON  chang_custm_rejt_reason.cr_id = req.id and  chang_custm_rejt_reason.custom_field_id = 63
     LEFT JOIN rejection_reasons as rejt_reason ON  rejt_reason.id = chang_custm_rejt_reason.custom_field_value 


    where curr_status.new_status_id = 19
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
            'Business Validation Status Start Date',
            'Business Validation Status End Date',
            'Pending CAB status Start Date',
            'Pending CAB status End Date',
            'Design in Progress Start Date',
            'Design in Progress End Date',
            'Assigned Design Team Member',
            'Team Member Level',
            'Technical Implementation Start Date',
            'Technical Implementation End Date',
            'Assigned Group',
            'Assigned Dev User',
            'Assigned Dev User Level',
            'Pending Testing Start Date',
            'Pending Testing End Date',
            'Testing Team Member',
            'Assigned Test User Level',
            'Pending Production Deployment Start Date',
            'Pending Production Deployment End Date',
            'Pending Business Feedback Start Date',
            'Pending Business Feedback End Date',
            'Business Test Case Approval Start Date',
            'Business Test Case Approval End Date',
            'Business UAT Sign Off Start Date',
            'Business UAT Sign Off End Date',
            'Delivered Date Start',
            'Delivered Date End',
            'Expected Delivery Date',
            'Closed Date',
            'Previous Status',
            'Requester Name',
            'Division Manager',
            'Requester Division',
            'Requester Sector',
            'Rejection Reasons'
        ];
    }
}
