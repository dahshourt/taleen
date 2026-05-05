<?php

namespace App\Http\Repository\Groups;
use App\Contracts\Groups\GroupRepositoryInterface;
use Illuminate\Support\Arr;// declare Entities
use App\Models\Group;
use App\Models\GroupApplications;
use App\Models\GroupStatuses;




class GroupRepository implements GroupRepositoryInterface
{


    public function StoreApplicationGroup($group_id,$request)
    {
        GroupApplications::where('group_id',$group_id)->delete();
        if(isset($group_id) && !empty($request))
        {
            
            foreach($request as $key=>$value)
            {
                $group_applications = new GroupApplications;
                $group_applications->group_id = $group_id;
                $group_applications->application_id = $value;
                $group_applications->save();
            }
        }
        return true;
    }
    
    public function getAll()
    {
        return Group::with('children','parent')->get();
    }

    public function getAllActive()
    {
        return Group::with('children','parent')->where('active', '1')->get();
    }

    public function getAllWithFilter($parent_id)
    {
        if($parent_id)
        {
            return Group::with('parent')->whereNotNull('parent_id')->get();
        }
        else
        {
            return Group::with('children')->whereNull('parent_id')->get();
        }
        
    }

    public function create($request)
    {
        $group = Group::create(Arr::except($request, ['application_id', 'roles']));
        
        if (isset($request['roles'])) {
            $group->syncRoles($request['roles']);
        }

        if(isset($request['application_id']) && !empty($request['application_id']))
        {
            $this->StoreApplicationGroup($group->id,$request['application_id']);
        }
        //$this->StoreApplicationGroup($group->id,$request);
        return $group;
    }

    public function delete($id)
    {
        return Group::destroy($id);
    }

    public function update($request, $id)
    {
        $group = Group::where('id', $id)->update($request->except(['application_id','_method','_token', 'roles']));
        
        $groupModel = Group::find($id);
        if ($request->has('title')) {
            $groupModel->syncRoles($request->input('roles', []));
        }

        if(isset($request['application_id']) && !empty($request['application_id']))
        {
            $this->StoreApplicationGroup($id, $request['application_id']);
        }
        else
        {
            GroupApplications::where('group_id', $id)->delete();
        }
        return $group;
    }

    public function find($id)
    {
        return Group::with('group_applications')->find($id);
    }
	public function updateactive($active,$id)
    {
		if($active)
        {
		    return 	Group::where('id', $id)->update(['active'=>'0']);
		} 
        else
        {
			
			return 	Group::where('id', $id)->update(['active'=>'1']);

		}
		
	}

    public function findByName($name)
    {
        return Group::where('title',$name)->first();
    }
	
	
	
	
	public function get_tech_groups_by_application($app_id)
	{
		return Group::whereHas('group_applications', function($q) use($app_id){
            $q->where('application_id', $app_id);
        })->get();
	}


    public function StoreGroupStatuses($request,$group_id)
    {
        
        GroupStatuses::where('group_id',$group_id)->delete();
        if(isset($group_id) && !empty($request->view_statuses_id))
        {
            foreach($request->view_statuses_id as $key=>$value)
            {
                $group_statuses = new GroupStatuses;
                $group_statuses->group_id = $group_id;
                $group_statuses->status_id = $value;
                $group_statuses->type = 2;                $group_statuses->save();
            }
        }

        if(isset($group_id) && !empty($request->set_statuses_id))
        {
            foreach($request->set_statuses_id as $key=>$value)
            {
                $group_statuses = new GroupStatuses;
                $group_statuses->group_id = $group_id;
                $group_statuses->status_id = $value;                $group_statuses->type = 1;
                $group_statuses->save();
            }
        }
        return true;
    }


    
}