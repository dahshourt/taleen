<?php

namespace App\Contracts\Defect;

interface DefectRepositoryInterface
{
    public function AddDefect($request);

    public function AddDefectComment($defect_id, $comment);

    public function AddDefectLog($defect_id, $text_log);

    public function AddDefectStatus($defect_id, $previous_status_id, $new_status_id);

    public function Defect_Attach($data, $defect_id);

    public function get_defect_data($defect_id);

    public function get_defect_comments($defect_id);

    public function get_defect_attachments($defect_id);

    public function update_defect($defect_id, $request);
}
