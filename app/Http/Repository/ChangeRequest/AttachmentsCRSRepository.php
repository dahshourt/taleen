<?php

namespace App\Http\Repository\ChangeRequest;

use App\Contracts\ChangeRequest\AttachmentsCRSRepositoryInterface;
use App\Http\Repository\Logs\LogRepository;
use App\Models\Attachements_crs;
use Auth;

// declare Entities
class AttachmentsCRSRepository implements AttachmentsCRSRepositoryInterface
{
    public function add_files($data, $cr_id, $flag)
    {
        foreach ($data as $key => $item) {
            $description = '';
            if (isset(request()->att_description[$key])) {
                $description = request()->att_description[$key];
            }

            // Ensure the file is valid
            if ($item->isValid()) {
                // Get file details
                $filename = time() . '.' . $item->getClientOriginalExtension();
                $original_file_name = $item->getClientOriginalName();

                // Move the file to the uploads directory
                $item->move(public_path() . '/uploads/', $filename);

                // Get the size of the uploaded file after moving it
                $file_path = public_path() . '/uploads/' . $filename;
                $size = filesize($file_path); // Size in bytes

                // Optionally, convert to MB

                // Create a new record for the attachment
                $att = new Attachements_crs();
                $att->user_id = Auth::user()->id;
                $att->file = $original_file_name;
                $att->file_name = $filename;
                $att->cr_id = $cr_id;
                $att->description = $description;

                $att->size = $size; // Save the size in MB
                $att->flag = $flag;
                $att->save();
            } else {
                // Handle file upload failure
                return response()->json(['error' => 'File upload failed.']);
            }
        }

        return true;
    }

    public function update_files($data, $cr_id)
    {
        // dd($data);
        foreach ($data as $key => $file) {
            $att = new Attachements_crs();
            $filename = rand(10, 1000) . '_' . $file->getClientOriginalName();
            $file->move(public_path('uploads'), $filename);
            $att->user_id = Auth::user()->id;
            $att->file = $filename;
            $att->cr_id = $cr_id;
            $att->description = isset(request()->att_description[$key]) ? request()->att_description[$key] : '';
            $att->save();
            // dd($filename);
        }

    }

    public function delete_file($id)
    {
        $file = attachements_crs::findOrFail($id);
        $filePath = public_path('uploads/' . $file['file_name']); // in config
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $log = new LogRepository();
        $log->create([
            'cr_id' => $file->cr_id,
            'user_id' => Auth::user()->id,
            'log_text' => 'File ' . $file->file . ' deleted by ' . Auth::user()->user_name,
        ]);

        return $file->delete();
    }
}
