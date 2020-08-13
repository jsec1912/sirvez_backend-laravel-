<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Project;
use App\Project_site;
use App\Site;
use App\Task;
use App\Notification;
use App\User;
use App\Room;
use App\Company;
use App\Company_customer;
use App\Project_user;
class ProjectController extends Controller
{
    public function updateProject(Request $request){
        $v = Validator::make($request->all(), [
            //company info
            'customer_id' => 'required',
            'project_name' => 'required',
            'manager_id' => 'required',
            'contact_number' => 'required',
            'survey_start_date' => 'required',
            'project_summary' => 'required'
        ]);
        if ($v->fails())
        {
            return response()->json([
                'status' => 'error',
                'msg' => 'You must input data in the field!'
            ]);
        }
        $project = array();
        $id = $request->id;
        if($request->hasFile('upload_doc')){

            $fileName = time().'.'.$request->upload_doc->extension();  
            $request->upload_doc->move(public_path('upload\file'), $fileName);
            $project['upload_doc']  = $fileName;
        }
        $project['company_id'] = $request->customer_id;
        $project['project_name']  = $request->project_name;
        //$project['user_id']  = $request->user_id;
        $project['manager_id']  = $request->manager_id;
        $project['contact_number']  = $request->contact_number;
        $project['survey_start_date']  = $request->survey_start_date;
        $project['created_by']  = $request->user->id;
        $project['project_summary']  = $request->project_summary;   
        $action = "updated";
        if(!isset($id) || $id=="" || $id=="null" || $id=="undefined"){
            $project = Project::create($project);
            $action = "created";
            $id = $project->id;
            if($request->has('assign_to'))
            {
                $array_res = array();
                $array_res =json_decode($request->assign_to,true);
                foreach($array_res as $row)
                {
                    Project_user::create(['project_id'=>$id,'user_id'=>$row]);

                }
            }
        }
        else{
            Project::whereId($id)->update($project);
            if($request->has('assign_to'))
            {
                Project_user::where(['project_id'=>$id,'type'=>'1'])->delete();
                $array_res = array();
                $array_res =json_decode($request->assign_to,true);
                foreach($array_res as $row)
                {
                    Project_user::insert(['project_id'=>$id,'user_id'=>$row]);

                }
            }
        }

        //$notice_type ={1:pending_user,2:createcustomer 3:project}  
        $insertnotificationndata = array(
            'notice_type'		=> '3',
            'notice_id'			=> $id,
            'notification'		=> $project['project_name'].' have been '.$action.' by  '.$request->user->first_name.' ('.$request->user->company_name.').',
            'created_by'		=> $request->user->id,
            'company_id'		=> $request->company_id,
            'created_date'		=> date("Y-m-d H:i:s"),
            'is_read'	    	=> 0,
        );
        Notification::create($insertnotificationndata);

        $response = ['status'=>'success', 'msg'=>'Project Saved Successfully!'];  
        return response()->json($response);
    }
    public function deleteProject(Request $request)
    {
        //$request = {'project_id':{}}
       
        Project::where(['id'=>$request->project_id])->delete();
        $site_id = Project_site::where('project_id',$request->project_id)->pluck('id');
        Project_site::whereIn('id',$site_id)->delete();
        Room::whereIn('site_id',$site_id)->delete();
        $res["status"] = "success";
        return response()->json($res);
    }
    public function projectList(Request $request){
        $res = array();
        if($request->user->user_type==1){
            $id = Company_customer::where('company_id',$request->user->company_id)->pluck('customer_id');
            $project_array = Project::whereIn('projects.company_id',$id)
            ->join('companies','companies.id','=','projects.company_id')
            ->join('users','users.id','=','projects.manager_id')
            ->select('projects.*', 'companies.name AS customer','users.first_name AS account_manager')->get();
        }
        else{
            $id = $request->user->company_id;
            $project_array = Project::where('projects.company_id',$id)
            ->join('companies','companies.id','=','projects.company_id')
            ->join('users','users.id','=','projects.manager_id')
            ->select('projects.*', 'companies.name AS customer','users.first_name AS account_manager')->get();
        }
        foreach($project_array as $key => $row){
            $project_array[$key]['site_count'] = Project_site::where('project_id',$row['id'])->count();
            $project_array[$key]['room_count'] = Room::where('project_id',$row['id'])->count();
            $project_array[$key]['messages'] = Notification::where('notice_type','3')->where('notice_id',$row['id'])->count();
        }
        $res["projects"] = $project_array;
        $res['status'] = "success";
        return response()->json($res);
    }
    public function projectDetail(Request $request){
        $res = array();
        $project = Project::where('projects.id',$request->id)
        ->join('companies','projects.company_id','=','companies.id')->select('projects.*','companies.logo_img','companies.name AS company_name')->first();
        if(User::where('company_id',$project->company_id)->count() > 0)
            $project['customer_user'] = User::where('company_id',$project->company_id)->first()->first_name;
        else
            $project['customer_user'] = '';
        $project['site_count'] = Project_site::where('project_id',$project['id'])->count();
        $project['room_count'] = Room::where('project_id',$project['id'])->count();
        $project['user_notifications'] = Notification::where('notice_type','3')->where('notice_id',$request->id)->count();
        $res['sites'] = Project_site::where('project_id',$project['id'])
            ->leftjoin('sites','project_sites.site_id','=','sites.id')->select('sites.*','project_sites.survey_date')->withCount('rooms')->get();
        $res['rooms'] = Room::where('rooms.project_id',$project['id'])
            ->join('sites','rooms.site_id','=','sites.id')->select('rooms.*','sites.site_name')->get();
        $res['tasks'] = Task::where('project_id',$project['id'])->get();
       
        $res["project"] = $project;
        $res['status'] = "success";
        return response()->json($res);
    }
    public function getProjectInfo(Request $request){
        //return response()->json($request);
        if ($request->has('id')) {
            $id = $request->id;
        
            $res = array();
            $res['status'] = 'success';
            $res['project'] = Project::whereId($id)->first();
            $res['project']['assign_to'] = Project_user::where(['project_id'=>$id,'type'=>'1'])->pluck('user_id');
        }
        $company_id = Company_customer::where('company_id',$request->user->company_id)->pluck('customer_id');
        $res['customer'] = Company::whereIn('id',$company_id)->get();
        $res['account_manager'] = User::whereIn('user_type',[1,3])->where('status',1)->where('company_id',$request->user->company_id)->select('id','first_name','last_name')->get();
        $res['assign_to'] = User::whereIn('user_type',[1,5])->where('status',1)->where('company_id',$request->user->company_id)->get();
        //$res['users'] = User::whereIn('user_type',2)->where('status',1)->where('company_id',$request->user->company_id)->get();
        
        return response()->json($res);
    }
}