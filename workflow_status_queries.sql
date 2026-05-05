-- Check current counts by workflow status
SELECT 
    workflow_type,
    COUNT(*) as count,
    GROUP_CONCAT(DISTINCT from_status_id) as from_statuses,
    GROUP_CONCAT(DISTINCT to_status_label) as to_status_labels,
    GROUP_CONCAT(DISTINCT type_id) as type_ids
FROM new_workflow
WHERE active = '1'
GROUP BY workflow_type
ORDER BY count DESC;

-- Check workflow types and their distribution
SELECT 
    workflow_type,
    CASE 
        WHEN workflow_type = '0' THEN '✅ Approved'
        WHEN workflow_type = '1' THEN '❌ Need to review'
        ELSE CONCAT('Type ', workflow_type)
    END as workflow_description,
    COUNT(*) as count
FROM new_workflow
WHERE active = '1'
GROUP BY workflow_type
ORDER BY workflow_type;

-- Identify records that need status changes or have NULL values
SELECT 
    id,
    workflow_type,
    from_status_id,
    to_status_label,
    type_id,
    previous_status_id,
    active,
    CASE 
        WHEN workflow_type = '0' THEN '✅ Approved'
        WHEN workflow_type = '1' THEN '❌ Need to review'
        ELSE CONCAT('Type ', workflow_type)
    END as workflow_description
FROM new_workflow
WHERE workflow_type NOT IN ('0', '1')
   OR workflow_type IS NULL
   OR active != '1'
ORDER BY workflow_type, id;

-- Check for workflows that might be missing for cr_pending_cap status
SELECT 
    cr.id as change_request_id,
    cr.title,
    crs.new_status_id,
    crs.old_status_id,
    COUNT(nw.id) as available_workflows,
    GROUP_CONCAT(DISTINCT nw.workflow_type) as workflow_types
FROM change_requests cr
JOIN change_request_statuses crs ON cr.id = crs.cr_id
LEFT JOIN new_workflow nw ON nw.from_status_id = crs.new_status_id 
    AND nw.active = '1' 
    AND nw.type_id = cr.workflow_type_id
WHERE crs.active = '1'
GROUP BY cr.id, cr.title, crs.new_status_id, crs.old_status_id
HAVING available_workflows = 0
ORDER BY cr.id DESC;

-- Update queries (use with caution - backup first!)

-- Update NULL workflow_type to appropriate defaults
-- UPDATE new_workflow 
-- SET workflow_type = CASE 
--     WHEN workflow_type IS NULL THEN '0'
--     ELSE workflow_type
-- END
-- WHERE workflow_type IS NULL;

-- Update inactive workflows to active
-- UPDATE new_workflow 
-- SET active = '1'
-- WHERE active != '1' AND workflow_type IN ('0', '1');

-- Insert missing workflows for common status transitions
-- INSERT INTO new_workflow (from_status_id, to_status_label, workflow_type, type_id, active, created_at, updated_at)
-- SELECT 
--     crs.new_status_id,
--     CASE 
--         WHEN s.status_name LIKE '%Approve%' THEN 'Approved'
--         WHEN s.status_name LIKE '%Reject%' THEN 'Rejected'
--         WHEN s.status_name LIKE '%Review%' THEN 'Need Review'
--         ELSE 'Next Status'
--     END as to_status_label,
--     '0', -- Approved workflow
--     cr.workflow_type_id,
--     '1',
--     NOW(),
--     NOW()
-- FROM change_requests cr
-- JOIN change_request_statuses crs ON cr.id = crs.cr_id
-- JOIN statuses s ON s.id = crs.new_status_id
-- WHERE crs.active = '1'
-- AND NOT EXISTS (
--     SELECT 1 FROM new_workflow nw 
--     WHERE nw.from_status_id = crs.new_status_id 
--     AND nw.workflow_type = '0'
--     AND nw.type_id = cr.workflow_type_id
--     AND nw.active = '1'
-- );

-- Insert rejection workflows
-- INSERT INTO new_workflow (from_status_id, to_status_label, workflow_type, type_id, active, created_at, updated_at)
-- SELECT 
--     crs.new_status_id,
--     'Need to Review',
--     '1', -- Need to review workflow
--     cr.workflow_type_id,
--     '1',
--     NOW(),
--     NOW()
-- FROM change_requests cr
-- JOIN change_request_statuses crs ON cr.id = crs.cr_id
-- WHERE crs.active = '1'
-- AND NOT EXISTS (
--     SELECT 1 FROM new_workflow nw 
--     WHERE nw.from_status_id = crs.new_status_id 
--     AND nw.workflow_type = '1'
--     AND nw.type_id = cr.workflow_type_id
--     AND nw.active = '1'
-- );
