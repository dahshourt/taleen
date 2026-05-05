<?php

namespace App\Http\Repository\MailTemplates;

use App\Contracts\MailTemplates\MailTemplatesRepositoryInterface;
use App\Models\MailTemplate;
use Illuminate\Support\Facades\Validator;

class MailTemplatesRepository implements MailTemplatesRepositoryInterface
{
    public function getAll()
    {
        return MailTemplate::paginate(10);
    } // end method

    public function create($request)
    {

        $validator = Validator::make($request, [
            'template_name' => 'required|unique:mail_templates,name',
            'template_subject' => 'required',
            'template_body' => 'required',

        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        MailTemplate::create(['name' => $request['template_name'], 'subject' => $request['template_subject'], 'body' => $request['template_body'] ?? null]);  //

        // return true;
        return redirect()->back()->with('status', 'Permission Added Successfully');

    }  // end method

    public function find($id)
    {
        return MailTemplate::find($id);
    } // end method

    public function list()
    {
        return MailTemplate::all();
    } // end method

    public function update($request, $id)
    {

        $validator = Validator::make($request, [
            'template_name' => 'required|unique:mail_templates,name,' . $id,
            'template_subject' => 'required',
            'template_body' => 'required',

        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        MailTemplate::where('id', $id)->update(['name' => $request['template_name'], 'subject' => $request['template_subject'], 'body' => $request['template_body'] ?? null]);

    } // end method

    public function delete($id)
    {
        return MailTemplate::find($id)->delete();
    } // end method
}
