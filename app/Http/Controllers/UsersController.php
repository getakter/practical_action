<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Validator;
use DB;
use Datatables;
use Crypt;
use Auth;
use App\User;
use App\UserDesignationModel;
use App\UserProjectModel;
use App\Designation;
use App\Role;

class UsersController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        return view('users.users_list');
    }

    public function get_all_users(){

        // dd("working on it");

        $query_get_all_users="
        SELECT 
        id,
        users.name,
        users.email,
        users.phone_num,
        users.gender
        FROM users
        WHERE users.valid=1
        ";
        $users=DB::select($query_get_all_users);
        $users_collection= collect($users);
        // return Datatables::of(User::all())->make(true);
    // dd($reservation_collection);
        return Datatables::of($users_collection)
        ->addColumn('action', function ($users_collection) {
            return ' <a href="'. url('/users') . '/' . 
            Crypt::encrypt($users_collection->id) . 
            '/edit' .'"' . 
            'class="btn btn-primary btn-danger"><i class="glyphicon "></i> Edit</a>'.
            ' <a href="'. url('/user') . '/' . 
            Crypt::encrypt($users_collection->id) . 
            '/user_projects' .'"' . 
            'class="btn btn-primary btn-success"><i class="glyphicon "></i>Projects</a>';
        })
        ->editColumn('id', '{{$id}}')
        ->setRowId('id')
        ->make(true);

    }


    public function get_line_managers(Request $request){ 

        $id = $request->id;

        $search_term = $request->input('term');

        $query_users= "
        SELECT users.id , users.name AS text
        FROM users WHERE users.id!= '$id' AND users.name LIKE '%{$search_term}%' AND users.valid=1";

        $users = DB::select($query_users);

        // dd($users);

        return response()->json($users);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $user_id=Crypt::decrypt($id);


        $user = User::where('valid',  1)->where('id',$user_id)->first();

        $date = date_create($user->joining_date);

        $date = date_format($date, "d-m-Y");

        $matrix_manager = User::where('id', $user->matrix_manager_id)->first();

        $line_manager = User::where('id', $user->line_manager_id)->first();

        $user_designation_connection = UserDesignationModel::where('user_id',$user_id)
        ->where('valid',1)->first();

        

        if(is_object($user_designation_connection)){

            $user_designation = Designation::where('id',$user_designation_connection->designation_id)->first();

            return view('users.user_edit')->with('user',$user)->with('date',$date)->with('matrix_manager',$matrix_manager)
            ->with('line_manager',$line_manager)->with('user_designation',$user_designation);
        }else{

            return view('users.user_edit')->with('user',$user)->with('date',$date)->with('matrix_manager',$matrix_manager)
            ->with('line_manager',$line_manager);

        }

    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // dd($request->all());

        if(!empty($request->password) && !empty($request->password_confirm)){

            if($request->password != $request->password_confirm){

                $request->session()->flash('alert-danger', 'password does not match!');

                return redirect()->back();
            }else{

                $user = User::where('id',$id)->update(['password'=>bcrypt($request->password)]);

            }

        }

        /**
         * checking the line manager and the martix manager,
         * both managers cannot be the same person. 
         */

        if(!empty($request->line_manager) && !empty($request->matrix_manager)){

            if($request->line_manager == $request->matrix_manager){

                $request->session()->flash('alert-danger', 'line manager and matrix cannot be the same person!');

                return redirect()->back();

            }else{

                $user = User::where('id',$id)->update(['line_manager_id'=>$request->line_manager,
                    'matrix_manager_id'=>$request->matrix_manager]);
            }

        }

        //updating name
        if(!empty($request->name)){

            $user = User::where('id',$id)->update(['name'=>$request->name]);

        }

        //updating email
        if(!empty($request->email)){

            $user = User::where('id',$id)->update(['email'=>$request->email]);

        }

        //updating phone number

        if(!empty($request->phone_num)){

            $user = User::where('id',$id)->update(['phone_num'=>$request->phone_num]);

        }

        //updating gender
        if(!empty($request->gender)){

            $user = User::where('id',$id)->update(['gender'=>$request->gender]);

        }

        //updating user designation
        if(!empty($request->designation)){


            $user_designation = UserDesignationModel::where('user_id',$id)->where('valid',1)->first();

            //checking if the object is null or not
            if(!is_null($user_designation)){

                //checking if the existing designation id is same as the requested id 
                //if not same then update the existing designation id to valid 0
                //and create a new designation
                if($user_designation->designation_id != $request->designation){

                    $designation = UserDesignationModel::where('user_id',$id)->update(['valid'=>0]);

                    $designation = new UserDesignationModel;
                    $designation->user_id = $id;
                    $designation->designation_id = $request->designation;
                    $designation->valid=1 ;

                    $designation->save();

                }

            }else{

                

                //if old data does not exist, create a designation for this user

                $designation = new UserDesignationModel;
                $designation->user_id = $id;
                $designation->designation_id = $request->designation;
                $designation->valid=1 ;

                $designation->save();


            }

            

        }

        //updating the joining date

        if(!empty($request->joining_date)){

            $user = User::where('id',$id)->update(['joining_date'=>\Carbon\Carbon::createFromFormat('d-m-Y', $request->joining_date)->toDateString()]);

        }

        //creating the roles of an user
        if(!empty($request->role_id)){

            //removing any previous roles
            DB::table('role_user')->where('user_id', '=',$id)->delete();

            $roles = $request->role_id;

            //saving the new roles

            foreach ($roles as $role) {

                $role_object = Role::where('id',$role)->first();

                $user = User::findOrFail($id);

                $user->attachRole($role_object); 

            }



        }


        $request->session()->flash('alert-success', 'data has been updated');

        // return redirect()->back();

        return redirect()->action('UsersController@index');

    }

    /**
     * creating/edting user projects
     */

    public function user_projects(Request $request , $id){

        $user_id=Crypt::decrypt($id);

        $user = User::where('id',$user_id)->first();

        
        
        return view('users.user_projects_list')->with('user',$user);


    }


    public function user_connected_project(Request $request){

        $user_id = $request->id;

        $user_projects = DB::table('users_projects_connection')
        ->join('projects','projects.id', '=' ,'users_projects_connection.project_id')
        ->select('projects.project_name', 'projects.project_code','users_projects_connection.allocated_time',
            'users_projects_connection.project_id','users_projects_connection.allocated_days',
            'users_projects_connection.allocate_days_percent')
        ->where('users_projects_connection.user_id',$user_id)
        ->where('users_projects_connection.valid',1)->get();

        // dd($user_projects);

        return response()->json($user_projects);

    }

    //select the projects for the users where the user the might perticipate

    public  function get_valid_projects(Request $request){

        $search_term = $request->input('term');

        // dd("working on it");
        $query_projects= "
        SELECT id, project_name AS text FROM projects
        WHERE project_name LIKE '%{$search_term}%' AND valid=1 AND completion_status=1";

        $projects = DB::select($query_projects);

        return response()->json($projects);

    }

    /**
     * fetching the project description
     */

    public  function get_project_description(Request $request){

        $project_id = $request->name;

        $project_code = DB::table('projects')->select('project_code')
        ->where('id',$project_id)->first();

        // dd($project_code);
        
        return response()->json($project_code);


    }

    /**
     * store/update the projects for a user
     */

    public function submit_projects(Request $request){

        $user_id = $request->user_id;

        $projects_data = $request->data;

        // dd($projects_data);

        //checking for the previous data
        //if the current user already has some projects but those
        //projects needed to removed due to wrong entry
        $previous_projects = UserProjectModel::where('user_id',$user_id)->where('valid',1)->get();

        $data_found = false;

        foreach ($projects_data as $project) {

            foreach ($previous_projects as $old_project) {

                //if the project exist 
                if($project[5] == $old_project->project_id){

                    //yes the project exist now check if there's any difference in the allocated days
                    if($project[2] != $old_project->allocated_days){


                        //there is a difference 
                        //then update that data only 

                        $new_day = floatval($project[2]);

                        $old_day = floatval($old_project->allocated_days);

                        $days = $new_day;

                        // start by converting to seconds
                        $seconds = ($days * 8 * 3600);

                        //converting seconds into hour

                        $seconds_to_hours = ($seconds / 3600);
                        $hours = floor($seconds_to_hours);    
                        $fraction_hour = $seconds_to_hours - $hours ;

                        //converting fraction hours into minutes

                        $fraction_minutes = ($fraction_hour * 60);

                        $minutes = ceil($fraction_minutes);

                        $allocated_time = $hours. ' hours ' . $minutes . ' mins';


                        UserProjectModel::where('user_id',$user_id)->where('project_id',$old_project->project_id)
                        ->update(['allocated_time'=>$allocated_time,'allocated_days'=>$new_day,'allocate_days_percent'=>$project[3]]);

                        $data_found = true;

                        break;

                    }else{
                        //if there is no difference, do nothing
                        $data_found = true;
                        break;
                    }

                }

                $data_found = false;
            }

            //need to create new entry
            if(!$data_found){

                $days = $project[2];

                $days_percentage = $project[3];

                // start by converting to seconds
                $seconds = ($days * 8 * 3600);

                //converting seconds into hour

                $seconds_to_hours = ($seconds / 3600);
                $hours = floor($seconds_to_hours);    
                $fraction_hour = $seconds_to_hours - $hours ;

                //converting fraction hours into minutes

                $fraction_minutes = ($fraction_hour * 60);

                $minutes = ceil($fraction_minutes);

                // dd($hours . ' '. $minutes);


                $user_projects = new UserProjectModel;

                $user_projects->user_id = $user_id;    
                $user_projects->project_id = $project[5];
                $user_projects->allocated_time = $hours. ' hours ' . $minutes . ' mins';
                $user_projects->allocated_days = $project[2];
                $user_projects->allocate_days_percent = $project[3];
                $user_projects->save();

            }
            
        }

        //soft delete data

        $data_found = false;

        foreach ($previous_projects as $old_project) {

            foreach ($projects_data as $project) {

                if($old_project->project_id == $project[5]){

                    $data_found = true;
                    break;

                }
            }
            if(!$data_found){

                UserProjectModel::where('user_id',$user_id)->where('project_id',$old_project->project_id)
                ->update(['valid'=>0]);
                break;

            }

            $data_found = false;

        }

        dd("done");




    }
















    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
