<?php

namespace App\Http\Controllers\MailTemplates;

use App\Factories\MailTemplates\MailTemplatesFactory;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MailTemplatesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    private $MailTemplates;

    public function __construct(MailTemplatesFactory $MailTemplates)
    {

        $this->MailTemplates = $MailTemplates::index();
        $this->view = 'mail_templates';
        $view = 'mail_templates';
        $route = 'mail_templates';
        $OtherRoute = 'mail_templates';

        $title = 'Mail Templates';
        $form_title = 'Mail Templates';
        view()->share(compact('view', 'route', 'title', 'form_title', 'OtherRoute'));

    }

    public function index()
    {
        $collection = $this->MailTemplates->getAll();

        return view("$this->view.index", compact('collection'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view("$this->view.create");
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $store = $this->MailTemplates->create($request->all());

        return redirect()->back()->with('status', 'Template Added Successfully');

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $row = $this->MailTemplates->find($id);

        return view("$this->view.show", compact('row'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $row = $this->MailTemplates->find($id);

        return view("$this->view.edit", compact('row'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->MailTemplates->update($request->all(), $id);

        return redirect()->back()->with('status', 'Template Updated Successfully');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $this->MailTemplates->delete($id);

        return redirect()->back()->with('success', 'Template deleted successfully!');
    }
}
