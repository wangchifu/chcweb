<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\User;
use App\Setup;
use App\StudentClass;
use App\ClubStudent;
use App\Action;
use App\Item;
use App\StudentSign;
use Rap2hpoutre\FastExcel\FastExcel;
use PHPExcel_IOFactory;
use PHPExcel;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use ZipArchive;


class SportMeetingController extends Controller
{
    public function __construct()
    {
        $setup = Setup::first();
        //檢查有無關閉網站
        if (!empty($setup->close_website)) {
            Redirect::to('close')->send();
        }
        $module_setup = get_module_setup();
        if (!isset($module_setup['運動會報名'])) {
            echo "<h1>已停用</h1>";
            die();
        }
    }

    public function index($action_id=null)
    {        
        
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $bdmin = check_power('運動會報名', 'B', auth()->user()->id);

        $actions = Action::where('disable','1')
        ->orderBy('id','DESC')->get();
        $action_array = [];
        foreach($actions as $action){
            $action_array[$action->id] = $action->name;
        }
        $select_action = null;
        if(empty($action_id)){
            $select_action = key($action_array);
        }else{
            $select_action = $action_id;
        }
        $action = [];
        $items = [];
        $student_classes = [];
        if($select_action) {
            $action = Action::find($select_action);

            $items = Item::where('action_id',$action->id)                
                ->where('disable',null)
                ->orderBy('order')
                ->get();

            $student_classes = StudentClass::where('semester',$action->semester)                
                ->orderBy('student_year')
                ->orderBy('student_class')
                ->get();

        }

        $data = [
            "admin"=>$admin,
            "bdmin"=>$bdmin,
            'action_array'=>$action_array,
            'select_action'=>$select_action,
            'action'=>$action,
            'items'=>$items,
            'student_classes'=>$student_classes,
        ];

            return view('sport_meetings.index', $data);
        }

    public function admin()
    {                
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $bdmin = check_power('運動會報名', 'B', auth()->user()->id);
        
        //$actions = Action::orderBy('semester','DESC')->get();
        $class_num = [];
        $club_student_num = [];
        $student_classes = StudentClass::orderBy('semester','DESC')->get()->groupBy('semester');
        foreach($student_classes as $k=>$student_class){
            $class_num[$k] = count($student_class);
        }
        $club_students = ClubStudent::all()->groupBy('semester');
        foreach($club_students as $k=>$club_student){
            $club_student_num[$k] = count($club_student);
        }                    
                
        $data = [            
            'admin'=>$admin,
            'bdmin'=>$bdmin,
            'class_num' => $class_num,
            'club_student_num' => $club_student_num,
        ];
        return view('sport_meetings.admin', $data);
    }

    public function stu_import(Request $request)
    {
        $admin = check_power('運動會報名', 'A', auth()->user()->id);        
        if(!$admin) return back();
        $semester = $request->input('semester');        
        //處理檔案上傳
        if ($request->hasFile('file')) {
            //ClubStudent::where('semester', $semester)->delete();
            //ClubRegister::where('semester', $semester)->delete();

            $file = $request->file('file');
            $collection = (new FastExcel)->import($file);
            //dd($collection);
            foreach ($collection as $line) {

                if (!isset($line['姓名']) or !isset($line['性別']) or !isset($line['年級(數字)']) or !isset($line['班序(數字)']) or !isset($line['班序(數字)']) or !isset($line['生日(西元)']) or !isset($line['學號']) or !isset($line['座號']) or !isset($line['導師姓名'])) {
                    return back()->withErrors(['欄位有錯，請檢查 excel 檔']);
                }

                if (empty($line['姓名']) and empty($line['年級(數字)'])) {
                    break;
                }
                $class_teacher[$line['年級(數字)']][$line['班序(數字)']] = $line['導師姓名'];

                $att['semester'] = $semester;
                $att['no'] = $line['學號'];
                $att['name'] = $line['姓名'];
                $b = explode('/', $line['生日(西元)']->format('Y/m/d'));
                $att['pwd'] = $b[0] . sprintf("%02s", $b[1]) . sprintf("%02s", $b[2]);
                $att['class_num'] = $line['年級(數字)'] . sprintf("%02s", $line['班序(數字)']) . sprintf("%02s", $line['座號']);
                $att['birthday'] = $att['pwd'];
                $att['sex'] = $line['性別'];
                
                $student = ClubStudent::where('semester', $semester)
                    ->where('no', $att['no'])
                    ->first();                    
                if (empty($student->id)) {                    
                    ClubStudent::create($att);                    
                } else {                    
                    $student->update($att);
                }
            }
            foreach ($class_teacher as $k => $v) {
                foreach ($v as $k1 => $v1) {
                    $att2['semester'] = $semester;
                    $att2['student_year'] = $k;
                    $att2['student_class'] = $k1;
                    $att2['user_names'] = $v1;

                    $student_class = StudentClass::where('semester', $att2['semester'])
                        ->where('student_year', $att2['student_year'])
                        ->where('student_class', $att2['student_class'])
                        ->first();
                    if (empty($student_class)) {
                        StudentClass::create($att2);
                    } else {
                        //避免先前拉過API 已經有導師了
                        $att2['user_ids'] = null;
                        $student_class->update($att2);
                    }
                }
            }
        }


        return redirect()->route('sport_meeting.admin', $semester);
    }

    public function stu_adm_more($semester, $student_class_id = null)
    {
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        if(!$admin) return back();

        $student_classes = StudentClass::where('semester', $semester)
            ->orderBy('student_year')
            ->orderBy('student_class')
            ->get();

        $student_class_id = ($student_class_id == null) ? $student_classes->first()->id : $student_class_id;

        $this_class = StudentClass::find($student_class_id);
        $sc = $this_class->student_year . sprintf("%02s", $this_class->student_class);

        $club_students = ClubStudent::where('semester', $semester)
            //->where('disable', null)
            ->where('class_num', 'like', $sc . '%')
            ->orderBy('class_num')
            ->get();

        $data = [
            'club_students' => $club_students,
            'semester' => $semester,
            'student_classes' => $student_classes,
            'this_class' => $this_class,
        ];
        return view('sport_meetings.stu_adm_more', $data);
    }

    public function user(){
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        if(!$admin) return back();

        $users = User::where('disable',null)->orderBy('order_by')->get();
        $data = [
            'admin' => $admin,
            'users' => $users,
        ];
        return view('sport_meetings.user', $data);
    }

    public function action(){
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        if(!$admin) return back();

        $actions = Action::orderBy('id','DESC')
            ->get();
        
        $data = [
            'admin' => $admin,        
            'actions'=>$actions,
        ];
        return view('sport_meetings.action', $data);
    }

    public function action_show(Action $action)
    {
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $items = Item::where('action_id',$action->id)            
            ->where('disable',null)
            ->orderBy('order')
            ->get();

        $student_classes = StudentClass::where('semester',$action->semester)            
            ->orderBy('student_year')
            ->orderBy('student_class')
            ->get();


        $data = [
            'admin' => $admin,  
            'action'=>$action,
            'items'=>$items,
            'student_classes'=>$student_classes,

        ];
        return view('sport_meetings.action_show',$data);

    }

    public function action_create()
    {
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        if(!$admin) return back();
        $data = [
            'admin' => $admin,        
        ];
        return view('sport_meetings.action_create',$data);
    }

    public function action_add(Request $request)
    {
        $att = $request->all();        
        $att['open'] = ($request->input('open'))?1:null;
        Action::create($att);
        return redirect()->route('sport_meeting.action');
    }

    public function action_delete(Action $action)
    {
        $att['disable'] =1;
        $action->update($att);
        return redirect()->route('sport_meeting.action');
    }

    public function action_destroy(Action $action)
    {        
        Item::where('action_id',$action->id)->delete();
        StudentSign::where('action_id',$action->id)->delete();
        $action->delete();
        return redirect()->route('sport_meeting.action');
    }

    public function action_enable(Action $action)
    {

        $att['disable'] =null;
        $action->update($att);
        return redirect()->route('sport_meeting.action');
    }

    public function action_edit(Action $action)
    {
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        if(!$admin) return back();
        $data = [
            'action'=>$action,
            'admin'=>$admin,
        ];
        return view('sport_meetings.action_edit',$data);
    }

    public function action_update(Request $request,Action $action)
    {
        $att = $request->all();
        $att['open'] = ($request->input('open'))?1:null;
        $action->update($att);
        return redirect()->route('sport_meeting.action');
    }

    public function item($action_id=null)
    {
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        if(!$admin) return back();
        $actions = Action::orderBy('id','DESC')->get();
        $action_array = [];
        foreach($actions as $action){
            $action_array[$action->id] = $action->name;
        }
        $select_action = null;
        
        if(empty($action_id)){
            $select_action = key($action_array);
        }else{
            $select_action = $action_id;            
        }
        $s_action = Action::find($select_action);

        $items = [];
        if(!empty($select_action)){
            $items = Item::where('action_id',$select_action)
                ->orderBy('disable')
                ->orderBy('order')->get();
        }
        $data = [
            'actions'=>$actions,            
            'action_array'=>$action_array,
            'select_action'=>$select_action,
            's_action'=>$s_action,
            'items'=>$items,
            'admin'=>$admin,
        ];
        return view('sport_meetings.item',$data);
    }

    public function item_create(Action $action)
    {
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        if(!$admin) return back();
        $data = [
          'action'=>$action,
          'admin'=>$admin,
        ];

        return view('sport_meetings.item_create',$data);
    }

    public function item_add(Request $request)
    {
        $att = $request->all();
        $att['years'] = serialize($att['years']);        
        $att['limit'] = ($request->input('limit'))?1:null;
        if($att['game_type'] == "personal"){
            $att['official'] = null;
            $att['reserve'] = null;
        }
        if($att['game_type'] == "class"){
            $att['official'] = null;
            $att['reserve'] = null;
            $att['group'] = 4;
            $att['people'] = 1;
        }
        $item = Item::create($att);
        if($att['game_type'] == "class"){
            $years = unserialize($item->years);
            $student_classes = StudentClass::where('semester', $item->action->semester)                
                ->whereIn('student_year',$years)
                ->orderBy('student_year')
                ->orderBy('student_class')
                ->get();
            foreach($student_classes as $student_class){                
                $att['item_id'] = $item->id;
                $att['item_name'] = $item->name;
                $att['game_type'] = "class";
                $att['student_id'] = $student_class->id;
                $att['action_id'] = $item->action_id;
                $att['student_year'] = $student_class->student_year;
                $att['student_class'] = $student_class->student_class;
                $att['sex'] = 4;
                StudentSign::create($att);
            }
        }
        return redirect()->route('sport_meeting.item',$item->action_id);
    }

    public function item_destroy(Item $item)
    {
        StudentSign::where('item_id',$item->id)->delete();
        $item->delete();
        return redirect()->route('sport_meeting.item',$item->action_id);
    }

    public function item_edit(Item $item)
    {        
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        if(!$admin) return back();
        $data = [
            'item'=>$item,
            'admin'=>$admin,
        ];
        return view('sport_meetings.item_edit',$data);
    }

    public function item_update(Request $request,Item $item)
    {
        $att = $request->all();
        //如果原來是班際賽，改為團體，要刪掉已報名的班際賽
        if($item->game_type == "class" and $att['game_type'] != "class"){
            StudentSign::where('item_id',$item->id)->delete();
        }
        $att['years'] = serialize($att['years']);
        $att['limit'] = ($request->input('limit'))?1:null;
        if($att['game_type'] == "personal"){
            $att['official'] = null;
            $att['reserve'] = null;
        }
        if($att['game_type'] == "class"){
            $att['official'] = null;
            $att['reserve'] = null;
            $att['group'] = 4;
            $att['people'] = 1;
        }
        $item->update($att);

        if($att['game_type'] == "class"){
            $years = unserialize($item->years);
            $student_classes = StudentClass::where('semester', $item->action->semester)                
                ->whereIn('student_year',$years)
                ->orderBy('student_year')
                ->orderBy('student_class')
                ->get();
            StudentSign::where('item_id',$item->id)->delete();
            foreach($student_classes as $student_class){                
                $att_student_sign['item_id'] = $item->id;
                $att_student_sign['item_name'] = $item->name;
                $att_student_sign['game_type'] = "class";
                $att_student_sign['student_id'] = $student_class->id;
                $att_student_sign['action_id'] = $item->action_id;
                $att_student_sign['student_year'] = $student_class->student_year;
                $att_student_sign['student_class'] = $student_class->student_class;
                $att_student_sign['sex'] = 4;
                StudentSign::create($att_student_sign);
            }
        }

        return redirect()->route('sport_meeting.item');
    }

    public function item_delete(Item $item)
    {        
        $att['disable'] =1;
        $item->update($att);
        return redirect()->route('sport_meeting.item');
    }

    public function item_enable(Item $item)
    {        
        $att['disable'] =null;
        $item->update($att);
        return redirect()->route('sport_meeting.item');
    }

    public function item_import(Request $request)
    {
        $items = Item::where('action_id',$request->input('old_action_id'))->get();
        foreach($items as $item){
            $att = $item->getAttributes();            
            $att['action_id'] = $request->input('new_action_id');
            $att['disable'] = null;
            unset($att['id']);
            unset($att['created_at']);
            unset($att['updated_at']);
            $item = Item::create($att);
            if($att['game_type'] == "class"){
                $years = unserialize($item->years);
                $student_classes = StudentClass::where('semester', $item->action->semester)                
                    ->whereIn('student_year',$years)
                    ->orderBy('student_year')
                    ->orderBy('student_class')
                    ->get();
                foreach($student_classes as $student_class){                
                    $att['item_id'] = $item->id;
                    $att['item_name'] = $item->name;
                    $att['game_type'] = "class";
                    $att['student_id'] = $student_class->id;
                    $att['action_id'] = $item->action_id;
                    $att['scoring'] = $item->scoring;
                    $att['student_year'] = $student_class->student_year;
                    $att['student_class'] = $student_class->student_class;
                    $att['sex'] = 4;
                    StudentSign::create($att);
                }
            }
        }
        return redirect()->route('sport_meeting.item');
    }

    public function list($action_id=null)
    {        
        
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $bdmin = check_power('運動會報名', 'B', auth()->user()->id);

        $actions = Action::orderBy('id','DESC')->get();
        $action_array = [];
        foreach($actions as $action){
            $action_array[$action->id] = $action->name;
        }
        $select_action = null;
        if(empty($action_id)){
            $select_action = key($action_array);
        }else{
            $select_action = $action_id;
        }

        $action = [];
        $student_classes = [];
        if($select_action){
            $action = Action::find($select_action);


            $student_classes = StudentClass::where('semester',$action->semester)                
                ->orderBy('student_year')
		        ->orderBy('student_class')
                ->get();
        }

        $data = [
            "admin"=>$admin,
            "bdmin"=>$bdmin,
            'action_array'=>$action_array,
            'select_action'=>$select_action,
            'student_classes'=>$student_classes,
            'action'=>$action,
        ];
        return view('sport_meetings.list', $data);
    }

    public function score()
    {                
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $bdmin = check_power('運動會報名', 'B', auth()->user()->id);
        
        $data = [
            "admin"=>$admin,
            "bdmin"=>$bdmin,
        ];
        return view('sport_meetings.score', $data);
    }

    public function teacher()
    {                
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $bdmin = check_power('運動會報名', 'B', auth()->user()->id);

        $actions = Action::orderBy('semester','DESC')->get();

        $student_classes = StudentClass::where('user_names','like', "%".auth()->user()->name."%")->get();  
        $teacher_class = [];
        foreach($student_classes as $student_class){
            $teacher_class[$student_class->semester]['year'] = $student_class->student_year;
            $teacher_class[$student_class->semester]['class'] = $student_class->student_class;
        }        

        $data = [
            "admin"=>$admin,
            "bdmin"=>$bdmin,
            'actions'=>$actions,
            'teacher_class'=>$teacher_class,
        ];
        return view('sport_meetings.teacher', $data);
    }

    public function sign_up_do(Action $action)
    {        
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $bdmin = check_power('運動會報名', 'B', auth()->user()->id);
        $student_class = StudentClass::where('semester',$action->semester)->where('user_names','like', "%".auth()->user()->name."%")->first();  
        
        $class_num = $student_class['student_year'].sprintf("%02s",$student_class['student_class']);
        
        $students = ClubStudent::where('semester',$action->semester)
            ->where('class_num','like',$class_num.'%')            
            ->orderBy('class_num')
            ->where('disable',null)
            ->get();            

        $girls = [];
        $boys = [];
        foreach($students as $student){
            if($student->sex == "男") $boys[$student->id] = substr($student->class_num,3,2).'-'.$student->name;
            if($student->sex == "女") $girls[$student->id] = substr($student->class_num,3,2).'-'.$student->name;
            $all_students[$student->id] = substr($student->class_num,3,2).'-'.$student->name;
        }

        $items = Item::where('action_id',$action->id)
            ->where('disable',null)
            ->orderBy('order')
            ->get();
        

        $data = [
            "admin"=>$admin,
            "bdmin"=>$bdmin,
            'boys'=>$boys,
            'girls'=>$girls,
            'all_students'=>$all_students,
            'action'=>$action,
            'items'=>$items,
            'student_year'=>substr($class_num,0,1),
            'student_class'=>(int)substr($class_num,1,2),
        ];
        return view('sport_meetings.sign_up_do',$data);
    }

    public function sign_up_add(Request $request)
    {
        $boy_select = $request->input('boy_select');
        $girl_select = $request->input('girl_select');
        $student_select = $request->input('student_select');

        $boy_group_official_select = $request->input('boy_group_official_select');
        $boy_group_reserve_select = $request->input('boy_group_reserve_select');
        $girl_group_official_select = $request->input('girl_group_official_select');
        $girl_group_reserve_select = $request->input('girl_group_reserve_select');
        $student_group_official_select = $request->input('student_group_official_select');
        $student_group_reserve_select = $request->input('student_group_reserve_select');

        $action = Action::find($request->input('action_id'));

        $class_num = $request->input('student_year').sprintf("%02s",$request->input('student_class'));
        $this_class_students = ClubStudent::where('semester',$action->semester)
            ->where('class_num','like',$class_num."%")
            ->orderBy('class_num')
            ->get();
        foreach($this_class_students as $this_class_student){
            $student_num[$this_class_student->id] = substr($this_class_student->class_num,3,2);
        }

        if(!empty($boy_select)){
            foreach($boy_select as $k=>$v){
                foreach($v as $k1=>$v1){
                    if($v1 <> null){                        
                        $att_boy['item_id'] = $k1;
                        $item = Item::find($k1);
                        $att_boy['item_name'] = $item->name;
                        $att_boy['game_type'] = $item->game_type;
                        $att_boy['student_id'] = $v1;
                        $att_boy['action_id'] = $request->input('action_id');
                        $att_boy['student_year'] = $request->input('student_year');
                        $att_boy['student_class'] = $request->input('student_class');
                        $att_boy['num'] = $student_num[$v1];
                        $att_boy['sex'] = "男";

                        $check = StudentSign::where('action_id',$request->input('action_id'))
                            ->where('item_id',$k1)
                            ->where('student_id',$v1)
                            ->first();
                        if(empty($check)){
                            StudentSign::create($att_boy);
                        }
                    }
                }
            }
        }
        if(!empty($boy_group_official_select)){
            foreach($boy_group_official_select as $k=>$v){
                foreach($v as $k1=>$v1){
                    foreach($v1 as $k2=>$v2){
                        if($v2 <> null){                            
                            $att_boy_official['item_id'] = $k1;
                            $item = Item::find($k1);
                            $att_boy_official['item_name'] = $item->name;
                            $att_boy_official['game_type'] = $item->game_type;
                            $att_boy_official['is_official'] = 1;
                            $att_boy_official['group_num'] = $k;
                            $att_boy_official['student_id'] = $v2;
                            $att_boy_official['action_id'] = $request->input('action_id');
                            $att_boy_official['student_year'] = $request->input('student_year');
                            $att_boy_official['num'] = $student_num[$v2];
                            $att_boy_official['student_class'] = $request->input('student_class');
                            $att_boy_official['sex'] = "男";

                            $check = StudentSign::where('action_id',$request->input('action_id'))
                                ->where('item_id',$k1)
                                ->where('student_id',$v2)
                                ->first();
                            if(empty($check)){
                                StudentSign::create($att_boy_official);
                            }
                        }
                    }
                }
            }
        }

        if(!empty($boy_group_reserve_select)){
            foreach($boy_group_reserve_select as $k=>$v){
                foreach($v as $k1=>$v1){
                    foreach($v1 as $k2=>$v2){
                        if($v2 <> null){                            
                            $att_boy_reserve['item_id'] = $k1;
                            $item = Item::find($k1);
                            $att_boy_reserve['item_name'] = $item->name;
                            $att_boy_reserve['game_type'] = $item->game_type;
                            $att_boy_reserve['is_official'] = null;
                            $att_boy_reserve['group_num'] = $k;
                            $att_boy_reserve['student_id'] = $v2;
                            $att_boy_reserve['action_id'] = $request->input('action_id');
                            $att_boy_reserve['student_year'] = $request->input('student_year');
                            $att_boy_reserve['student_class'] = $request->input('student_class');
                            $att_boy_reserve['num'] = $student_num[$v2];
                            $att_boy_reserve['sex'] = "男";

                            $check = StudentSign::where('action_id',$request->input('action_id'))
                                ->where('item_id',$k1)
                                ->where('student_id',$v2)
                                ->first();
                            if(empty($check)){
                                StudentSign::create($att_boy_reserve);
                            }
                        }
                    }
                }
            }
        }

        if(!empty($girl_select)){
            foreach($girl_select as $k=>$v){
                foreach($v as $k1=>$v1){
                    if($v1 <> null){                        
                        $att_girl['item_id'] = $k1;
                        $item = Item::find($k1);
                        $att_girl['item_name'] = $item->name;
                        $att_girl['game_type'] = $item->game_type;
                        $att_girl['student_id'] = $v1;
                        $att_girl['action_id'] = $request->input('action_id');
                        $att_girl['student_year'] = $request->input('student_year');
                        $att_girl['student_class'] = $request->input('student_class');
                        $att_girl['num'] = $student_num[$v1];
                        $att_girl['sex'] = "女";

                        $check = StudentSign::where('action_id',$request->input('action_id'))
                            ->where('item_id',$k1)
                            ->where('student_id',$v1)
                            ->first();
                        if(empty($check)){
                            StudentSign::create($att_girl);
                        }
                    }
                }
            }
        }
        if(!empty($girl_group_official_select)){
            foreach($girl_group_official_select as $k=>$v){
                foreach($v as $k1=>$v1){
                    foreach($v1 as $k2=>$v2){
                        if($v2 <> null){                            
                            $att_girl_official['item_id'] = $k1;
                            $item = Item::find($k1);
                            $att_girl_official['item_name'] = $item->name;
                            $att_girl_official['game_type'] = $item->game_type;
                            $att_girl_official['is_official'] = 1;
                            $att_girl_official['group_num'] = $k;
                            $att_girl_official['student_id'] = $v2;
                            $att_girl_official['action_id'] = $request->input('action_id');
                            $att_girl_official['student_year'] = $request->input('student_year');
                            $att_girl_official['student_class'] = $request->input('student_class');
                            $att_girl_official['num'] = $student_num[$v2];
                            $att_girl_official['sex'] = "女";

                            $check = StudentSign::where('action_id',$request->input('action_id'))
                                ->where('item_id',$k1)
                                ->where('student_id',$v2)
                                ->first();
                            if(empty($check)){
                                StudentSign::create($att_girl_official);
                            }
                        }
                    }
                }
            }
        }

        if(!empty($girl_group_reserve_select)){
            foreach($girl_group_reserve_select as $k=>$v){
                foreach($v as $k1=>$v1){
                    foreach($v1 as $k2=>$v2){
                        if($v2 <> null){                            
                            $att_girl_reserve['item_id'] = $k1;
                            $item = Item::find($k1);
                            $att_girl_reserve['item_name'] = $item->name;
                            $att_girl_reserve['game_type'] = $item->game_type;
                            $att_girl_reserve['is_official'] = null;
                            $att_girl_reserve['group_num'] = $k;
                            $att_girl_reserve['student_id'] = $v2;
                            $att_girl_reserve['action_id'] = $request->input('action_id');
                            $att_girl_reserve['student_year'] = $request->input('student_year');
                            $att_girl_reserve['student_class'] = $request->input('student_class');
                            $att_girl_reserve['num'] = $student_num[$v2];
                            $att_girl_reserve['sex'] = "女";

                            $check = StudentSign::where('action_id',$request->input('action_id'))
                                ->where('item_id',$k1)
                                ->where('student_id',$v2)
                                ->first();
                            if(empty($check)){
                                StudentSign::create($att_girl_reserve);
                            }
                        }
                    }
                }
            }
        }
        if(!empty($student_select)){
            foreach($student_select as $k=>$v){
                foreach($v as $k1=>$v1){
                    if($v1 <> null){                        
                        $att_student['item_id'] = $k1;
                        $item = Item::find($k1);
                        $att_student['item_name'] = $item->name;
                        $att_student['game_type'] = $item->game_type;
                        $att_student['student_id'] = $v1;
                        $att_student['action_id'] = $request->input('action_id');
                        $att_student['student_year'] = $request->input('student_year');
                        $att_student['student_class'] = $request->input('student_class');
                        $att_student['num'] = $student_num[$v1];
                        $att_student['sex'] = "4";

                        $check = StudentSign::where('action_id',$request->input('action_id'))
                            ->where('item_id',$k1)
                            ->where('student_id',$v1)
                            ->first();
                        if(empty($check)){
                            StudentSign::create($att_student);
                        }
                    }
                }
            }
        }
        if(!empty($student_group_official_select)){
            foreach($student_group_official_select as $k=>$v){
                foreach($v as $k1=>$v1){
                    foreach($v1 as $k2=>$v2){
                        if($v2 <> null){                            
                            $att_student_official['item_id'] = $k1;
                            $item = Item::find($k1);
                            $att_student_official['item_name'] = $item->name;
                            $att_student_official['game_type'] = $item->game_type;
                            $att_student_official['is_official'] = 1;
                            $att_student_official['group_num'] = $k;
                            $att_student_official['student_id'] = $v2;
                            $att_student_official['action_id'] = $request->input('action_id');
                            $att_student_official['student_year'] = $request->input('student_year');
                            $att_student_official['num'] = $student_num[$v2];
                            $att_student_official['student_class'] = $request->input('student_class');
                            $att_student_official['sex'] = "4";

                            $check = StudentSign::where('action_id',$request->input('action_id'))
                                ->where('item_id',$k1)
                                ->where('student_id',$v2)
                                ->first();
                            if(empty($check)){
                                StudentSign::create($att_student_official);
                            }
                        }
                    }
                }
            }
        }

        if(!empty($student_group_reserve_select)){
            foreach($student_group_reserve_select as $k=>$v){
                foreach($v as $k1=>$v1){
                    foreach($v1 as $k2=>$v2){
                        if($v2 <> null){                            
                            $att_student_reserve['item_id'] = $k1;
                            $item = Item::find($k1);
                            $att_student_reserve['item_name'] = $item->name;
                            $att_student_reserve['game_type'] = $item->game_type;
                            $att_student_reserve['is_official'] = null;
                            $att_student_reserve['group_num'] = $k;
                            $att_student_reserve['student_id'] = $v2;
                            $att_student_reserve['action_id'] = $request->input('action_id');
                            $att_student_reserve['student_year'] = $request->input('student_year');
                            $att_student_reserve['student_class'] = $request->input('student_class');
                            $att_student_reserve['num'] = $student_num[$v2];
                            $att_student_reserve['sex'] = "男";

                            $check = StudentSign::where('action_id',$request->input('action_id'))
                                ->where('item_id',$k1)
                                ->where('student_id',$v2)
                                ->first();
                            if(empty($check)){
                                StudentSign::create($att_student_reserve);
                            }
                        }
                    }
                }
            }
        }


        return redirect()->route('sport_meeting.teacher');
    }

    public function sign_up_show(Action $action)
    {
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $bdmin = check_power('運動會報名', 'B', auth()->user()->id);

        $student_class = StudentClass::where('semester',$action->semester)->where('user_names','like', "%".auth()->user()->name."%")->first();  
        
        $class_num = $student_class->student_year.sprintf("%02s",$student_class->student_class);
        $students = ClubStudent::where('semester',$action->semester)
            ->where('class_num','like',$class_num."%")
            ->orderBy('class_num')
            ->get();

        foreach($students as $student){
            if($student->sex == "男") $boys[$student->id] = substr($student->class_num,3,2).'-'.$student->name;
            if($student->sex == "女") $girls[$student->id] = substr($student->class_num,3,2).'-'.$student->name;            
            $all_students[$student->id] = substr($student->class_num,3,2).'-'.$student->name;
        }        

        $items = Item::where('action_id',$action->id)
            ->where('disable',null)
            ->orderBy('order')
            ->get();

        $data = [
            'admin'=>$admin,
            'bdmin'=>$bdmin,
            'action'=>$action,
            'items'=>$items,
            'student_year'=>$student_class->student_year,
            'student_class'=>$student_class->student_class,
            'boys'=>$boys,
            'girls'=>$girls,
            'all_students'=>$all_students,
        ];
        return view('sport_meetings.sign_up_show',$data);
    }

    public function sign_up_delete(StudentSign $student_sign)
    {
        $student_class = StudentClass::where('semester',$student_sign->action->semester)->where('user_names','like', "%".auth()->user()->name."%")->first();  

        if($student_class->student_year == $student_sign->student_year and $student_class->student_class == $student_sign->student_class){
            $student_sign->delete();
        }
        return redirect()->route('sport_meeting.sign_up_show',$student_sign->item->action_id);
    }

    public function student_sign_update(Request $request)
    {
        $student_sign = StudentSign::find($request->input('student_sign_id'));

        //檢查此學生報名過了嗎
        $check_has = StudentSign::where('action_id',$request->input('action_id'))
            ->where('item_id',$student_sign->item_id)
            ->where('student_id',$request->input('student_id'))
            ->first();
        if(!empty($check_has)){
            return back()->withErrors(['eroor'=>['***********失敗，此學生報名相同項目***********']])->withInput();
        }        

        $att['student_id'] = $request->input('student_id');
        $student = ClubStudent::find($request->input('student_id'));
        $att['num'] = (int)substr($student->class_num,3,2);
        $student_sign->update($att);
        return redirect()->route('sport_meeting.sign_up_show',$request->input('action_id'));
    }

    public function student_sign_make(Request  $request)
    {
        //檢查此學生報名過了嗎
        $check_has = StudentSign::where('action_id',$request->input('action_id'))
            ->where('item_id',$request->input('item_id'))
            ->where('student_id',$request->input('student_id'))
            ->first();
        if(!empty($check_has)){
            return back()->withErrors(['eroor'=>['***********失敗，此學生報名相同項目***********']])->withInput();
        }    
        
        $att['item_id'] = $request->input('item_id');
        $att['is_official'] = $request->input('is_official');
        $att['group_num'] = $request->input('group_num');
        $item = Item::find($att['item_id']);
        $att['item_name'] = $item->name;
        $att['game_type'] = $item->game_type;
        $att['student_id'] = $request->input('student_id');
        $att['action_id'] = $request->input('action_id');
        $student = ClubStudent::find($att['student_id']);
        $att['student_year'] = substr($student->class_num,0,1);
        $att['student_class'] = (int)substr($student->class_num,1,2);
        $att['num'] = (int)substr($student->class_num,3,2);
        $att['sex'] = ($item->group==4)?4:$student->sex;

        StudentSign::create($att);

        return redirect()->route('sport_meeting.sign_up_show',$request->input('action_id'));
    }

    public function demo_upload(Request $request)
    {        
        $folder = 'public';
        //處理檔案上傳
        if ($request->hasFile('demo')) {
            $demo = $request->file('demo');
            $info = [
                'original_filename' => $demo->getClientOriginalName(),
                'extension' => $demo->getClientOriginalExtension(),
            ];

            $demo->storeAs($folder, 'demo.odt');
        }

        $odt_folder = storage_path('app/public');

        $zip = new ZipArchive;
        $res = $zip->open($odt_folder.'/demo.odt');
        if ($res === TRUE) {
            $zip->extractTo($odt_folder.'/demo');
            $zip->close();
        }


        return redirect()->route('sport_meeting.score');
    }

    public function print_extra(Request $request)
    {
        $odt_folder = storage_path('app/public');

        $zip = new ZipArchive;
        if(file_exists($odt_folder.'/自訂獎狀.odt')){
            unlink($odt_folder.'/自訂獎狀.odt');
        }
        if ($zip->open($odt_folder.'/自訂獎狀.odt', ZipArchive::CREATE) === TRUE) {
            $zip->addFile($odt_folder . '/demo/manifest.rdf', 'manifest.rdf');
            $zip->addFile($odt_folder . '/demo/meta.xml', 'meta.xml');
            $zip->addFile($odt_folder . '/demo/settings.xml', 'settings.xml');

            $content = $odt_folder . '/demo/content.xml';
            if (file_exists($content)) {
                $fp = fopen($content, "r");
                $str = fread($fp, filesize($content));//指定讀取大小，這裡把整個檔案內容讀取出來
                $this_student = $request->input('this_student');
                $action_name = $request->input('action_name');
                $group = $request->input('group');
                $item = $request->input('item');
                $ranking = $request->input('ranking');
                $score = $request->input('score');
                $print_date = $request->input('print_date');

                //取代
                $str = str_replace("{{年班同學}}", $this_student, $str);
                $str = str_replace("{{運動會名稱}}", $action_name, $str);
                $str = str_replace("{{組別}}", $group, $str);
                $str = str_replace("{{項目}}", $item, $str);
                $str = str_replace("{{名次}}", $ranking, $str);
                $str = str_replace("{{成績}}", $score, $str);
                $str = str_replace("{{日期}}", $print_date, $str);

                //寫入 content2.xml
                //先刪除
                if(file_exists($odt_folder . '/demo/content2.xml')){
                    unlink($odt_folder . '/demo/content2.xml');
                }
                $fp2 = fopen($odt_folder . '/demo/content2.xml', "a+"); //開啟檔案
                fwrite($fp2, $str);
                fclose($fp2);

                $zip->addFile($odt_folder . '/demo/content2.xml', 'content.xml');
                $zip->addFile($odt_folder . '/demo/mimetype', 'mimetype');
                $zip->addFile($odt_folder . '/demo/styles.xml', 'styles.xml');
                $zip->addFile($odt_folder . '/demo/META-INF/manifest.xml', 'META-INF/manifest.xml');


                $zip->close();
            }

            return response()->download($odt_folder . '/自訂獎狀.odt');
        }

    }

    public function score_input($action_id=null)
    {
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $bdmin = check_power('運動會報名', 'B', auth()->user()->id);

        $actions = Action::orderBy('id','DESC')->get();
        $action_array = [];
        foreach($actions as $action){
            $action_array[$action->id] = $action->name;
        }
        $select_action = null;
        if(empty($action_id)){
            $select_action = key($action_array);
        }else{
            $select_action = $action_id;
        }

        $items = Item::where('action_id',$select_action)            
            ->where('disable',null)
            ->orderBy('order')
            ->get();


        $data = [
            'admin'=>$admin,
            'bdmin'=>$bdmin,
            'action_array'=>$action_array,
            'select_action'=>$select_action,
            'items'=>$items,
        ];

        return view('sport_meetings.score_input',$data);
    }

    public function score_input_do(Request $request)
    {        
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $bdmin = check_power('運動會報名', 'B', auth()->user()->id);
        
        $action = Action::find($request->input('action_id'));
        $item = Item::find($request->input('item_id'));
        $student_signs = StudentSign::where('item_id',$item->id)
            ->where('sex',$request->input('sex'))
            ->where('student_year',$request->input('student_year'))
            ->orderBy('group_num')
            ->orderBy('order')
            ->orderBy('student_class')
            ->orderBy('is_official','DESC')
            ->get();
        
        $student_array = [];
        foreach($student_signs as $student_sign){
            if($item->game_type=="personal"){
                $student_array[$student_sign->id]['id'] = $student_sign->id;
                $student_array[$student_sign->id]['number'] = $student_sign->student->number;
                $student_array[$student_sign->id]['name'] = $student_sign->student->name;
                $student_array[$student_sign->id]['achievement'] = $student_sign->achievement;
                $student_array[$student_sign->id]['ranking'] = $student_sign->ranking;
                $student_array[$student_sign->id]['order'] = $student_sign->order;
                $student_array[$student_sign->id]['class'] = $student_sign->student_year."年".$student_sign->student_class."班";
            }
            if($item->game_type=="group"){
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['id'] = $student_sign->id;
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['number'] = $student_sign->student->number;
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['name'] = $student_sign->student->name;
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['achievement'] = $student_sign->achievement;
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['ranking'] = $student_sign->ranking;
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['order'] = $student_sign->order;
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['is_official'] = $student_sign->is_official;
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['class'] = $student_sign->student_year."年".$student_sign->student_class."班";
            }
        }        
        if($item->game_type=="class") {

            $cht_num = config('chcschool.cht_num');
            foreach ($student_signs as $student_sign) {
                $class_name = $cht_num[$student_sign->student_year] . "年" . $student_sign->student_class . "班";
                $student_array[$student_sign->id]['name'] = $class_name;
                $student_array[$student_sign->id]['student_year'] = $student_sign->student_year;
                $student_array[$student_sign->id]['student_class'] = $student_sign->student_class;
                $student_array[$student_sign->id]['achievement'] = $student_sign->achievement;
                $student_array[$student_sign->id]['ranking'] = $student_sign->ranking;
                $student_array[$student_sign->id]['order'] = $student_sign->order;
            }
        }
        
        $data = [
            'admin'=>$admin,
            'bdmin'=>$bdmin,
            'year'=>$request->input('student_year'),
            'sex'=>$request->input('sex'),
            'action'=>$action,
            'item'=>$item,
            'student_signs'=>$student_signs,
            'student_array'=>$student_array,
        ];

        return view('sport_meetings.score_input_do',$data);

    }

    public function score_input_update(Request $request)
    {
        $checkbox = $request->input('checkbox');
        $achievement = $request->input('achievement');
        $ranking = $request->input('ranking');
        $order = $request->input('order');
        $action_id = $request->input('action_id');
        $item_id = $request->input('item_id');
        $item = Item::find($item_id);
        //徑賽
        if($item->type==1){
            //小到大
            asort($achievement);
        }
        //田賽
        if($item->type==2){
            //大到小
            arsort($achievement);
        }
        $r=1;
        foreach($achievement as $k=>$v){

            $att['achievement'] = $achievement[$k];
            if($checkbox =="on" and $item->type <> 3){
                $att['ranking'] = $r;
            }else{
                $att['ranking'] = $ranking[$k];
            }

            $att['order'] = (isset($order[$k]))?$order[$k]:null;

            $student_sign = StudentSign::find($k);
            $student_sign->update($att);
            if($item->game_type=="group"){
                $all_student_signs = StudentSign::where('item_id',$item->id)
                    ->where('student_year',$student_sign->student_year)
                    ->where('student_class',$student_sign->student_class)
                    ->where('group_num',$student_sign->group_num)
                    ->where('sex',$student_sign->sex)
                    ->update($att);
            }

            $r++;
        }

        return redirect()->route('sport_meeting.score_input_do',['action_id'=>$action_id,'item_id'=>$item_id,'student_year'=>$student_sign->student_year,'sex'=>$student_sign->sex]);
    }

    public function score_input_print(Action $action,Item $item,$year,$sex)
    {
        $cht_num = config('chcschool.cht_num');
        $student_signs = StudentSign::where('action_id',$action->id)
            ->where('item_id',$item->id)
            ->where('student_year',$year)
            ->where('sex',$sex)
            ->where('ranking','<>',null)
            ->orderBy('ranking')
            ->orderBy('is_official','DESC')
            ->get();

        $odt_folder = storage_path('app/public');

        $zip = new ZipArchive;
        if(file_exists($odt_folder.'/'.$year.'年級'.$sex.'子組'.$item->name.'獎狀.odt')){
            unlink($odt_folder.'/'.$year.'年級'.$sex.'子組'.$item->name.'獎狀.odt');
        }

        if ($zip->open($odt_folder.'/'.$year.'年級'.$sex.'子組'.$item->name.'獎狀.odt', ZipArchive::CREATE) === TRUE) {
            $zip->addFile($odt_folder . '/demo/manifest.rdf', 'manifest.rdf');
            $zip->addFile($odt_folder . '/demo/meta.xml', 'meta.xml');
            $zip->addFile($odt_folder . '/demo/settings.xml', 'settings.xml');

            $content = $odt_folder . '/demo/content.xml';
            if (file_exists($content)) {
                $fp = fopen($content, "r");
                $str = fread($fp, filesize($content));//指定讀取大小，這裡把整個檔案內容讀取出來

                $d = explode('<office:body>',$str);
                $odt_head = $d[0];
                $a = explode('</office:body>',$d[1]);
                $odt_body = $a[0];
                $odt_foot = $a[1];

                $data_body = null;
                $y = date('Y') - 1911;

                $score_data = [];
                $group_student = [];
                $i = 0;
                $item_name = $item->name;
                $print_date = "中華民國".$y.'年'.date('m').'月'.date('d').'日';
                $print_date_c = date('Y').'年'.date('m').'月'.date('d').'日';
                //取代
                $action_name = str_replace('報名','',$action->name);

                $first = $student_signs->first();
                $last_group =$first->student_year.$first->student_class.$first->group_num;
                foreach($student_signs as $student_sign) {
                    //if(!empty($student_sign->achievement)){
                        if($student_sign->student_year=="幼小" or $student_sign->student_year=="幼中" or $student_sign->student_year=="幼大"){
                            $score_data[$i]['year_name'] = $cht_num[$student_sign->student_year];
                            $score_data[$i]['year_class_name'] = $cht_num[$student_sign->student_year].$student_sign->student_class . "班";
                            $score_data[$i]['cht_year_class_name'] = $cht_num[$student_sign->student_year] . $cht_num[$student_sign->student_class] . "班";
                        }else{
                            $score_data[$i]['year_name'] = $cht_num[$student_sign->student_year] . "年級";
                            $score_data[$i]['year_class_name'] = $cht_num[$student_sign->student_year] . "年" . $student_sign->student_class . "班";
                            $score_data[$i]['cht_year_class_name'] = $cht_num[$student_sign->student_year] . "年" . $cht_num[$student_sign->student_class] . "班";
                        }
                        $score_data[$i]['class_name'] = $student_sign->student_class . "班";
                        $score_data[$i]['cht_class_name'] = $cht_num[$student_sign->student_class] . "班";

                        if($item->game_type=="personal"){
                            $score_data[$i]['ranking'] = $student_sign->ranking;
                            $score_data[$i]['achievement'] = $student_sign->achievement;

                            $score_data[$i]['this_student'] = $student_sign->student->name;
                            $score_data[$i]['group'] = ($sex=="4")?"不分性別組":$sex."子組";
                            $i++;
                        }
                        if($item->game_type=="group"){
                            $class_group = $student_sign->student_year.$student_sign->student_class.$student_sign->group_num;
                            if($last_group <> $class_group){
                                $i++;
                            }
                            $score_data[$i]['ranking'] = $student_sign->ranking;
                            $score_data[$i]['achievement'] = $student_sign->achievement;
                            $score_data[$i]['class_name'] = $cht_num[$student_sign->student_year] . "年" . $student_sign->student_class . "班";
                            $score_data[$i]['cht_class_name'] = $cht_num[$student_sign->student_year] . "年" . $cht_num[$student_sign->student_class] . "班";

                            if(!isset($group_student[$i])) $group_student[$i] = "";
                            if($student_sign->is_official == null){
                                $st_name = $student_sign->student->name."(候)";
                            }else{
                                $st_name = $student_sign->student->name;
                            }
                            $group_student[$i] .= $st_name." ";
                            $score_data[$i]['group'] = ($sex=="4")?"不分性別組":$sex."子組";
                            $last_group = $class_group;
                        }
                        if($item->game_type=="class"){
                            $score_data[$i]['ranking'] = $student_sign->ranking;
                            $score_data[$i]['achievement'] = $student_sign->achievement;
                            $score_data[$i]['this_student'] = "";
                            $score_data[$i]['group'] = "班際賽";
                            $i++;
                        }
                    //}
                }

                $i=0;
                foreach($score_data as $k=>$v){
                    if($i<$item->reward){
                        if($item->game_type=="group"){
                            $str2 = str_replace("{{年班同學}}", $v['year_class_name']."</text:p><text:p text:style-name=\"P12\">".$group_student[$k], $odt_body);
                            $str2 = str_replace("{{姓名}}", $group_student[$k], $str2);
                        }else{
                            $str2 = str_replace("{{年班同學}}", $v['year_class_name']." ".$v['this_student'], $odt_body);
                            $str2 = str_replace("{{姓名}}", $v['this_student'], $str2);
                        }
                        $str2 = str_replace("{{年班}}", $v['year_class_name'], $str2);
                        $str2 = str_replace("{{國字年班}}", $v['cht_year_class_name'], $str2);
                        $str2 = str_replace("{{年級}}", $v['year_name'], $str2);
                        $str2 = str_replace("{{班別}}", $v['class_name'], $str2);
                        $str2 = str_replace("{{國字班別}}", $v['cht_class_name'], $str2);
                        $str2 = str_replace("{{運動會名稱}}", $action_name, $str2);
                        $str2 = str_replace("{{組別}}", $v['group'], $str2);
                        $str2 = str_replace("{{項目}}", $item_name, $str2);
                        $str2 = str_replace("{{名次}}", "第".$v['ranking']."名", $str2);
                        if(isset($cht_num[$v['ranking']])){
                            $str2 = str_replace("{{國字名次}}", "第".$cht_num[$v['ranking']]."名", $str2);
                        }
                        $str2 = str_replace("{{成績}}", $v['achievement'], $str2);
                        $str2 = str_replace("{{日期}}", $print_date, $str2);
                        $str2 = str_replace("{{西元日期}}", $print_date_c, $str2);

                        $data_body .= $str2;
                        $i++;
                    }
                }

                $odt = $odt_head."<office:body>".$data_body."</office:body>".$odt_foot;

                //dd($str);

                //寫入 content2.xml
                //先刪除
                if(file_exists($odt_folder . '/demo/content2.xml')){
                    unlink($odt_folder . '/demo/content2.xml');
                }
                $fp2 = fopen($odt_folder . '/demo/content2.xml', "a+"); //開啟檔案
                fwrite($fp2, $odt);
                fclose($fp2);

                $zip->addFile($odt_folder . '/demo/content2.xml', 'content.xml');
                $zip->addFile($odt_folder . '/demo/mimetype', 'mimetype');
                $zip->addFile($odt_folder . '/demo/styles.xml', 'styles.xml');
                $zip->addFile($odt_folder . '/demo/META-INF/manifest.xml', 'META-INF/manifest.xml');


                $zip->close();
            }

            return response()->download($odt_folder.'/'.$year.'年級'.$sex.'子組'.$item->name.'獎狀.odt');
        }


    }

    //團體賽印個人獎狀
    public function score_input_print2(Action $action,Item $item,$year,$sex)
    {
        $cht_num = config('chcschool.cht_num');
        $student_signs = StudentSign::where('action_id',$action->id)
            ->where('item_id',$item->id)
            ->where('student_year',$year)
            ->where('sex',$sex)
            ->orderBy('ranking')
            ->orderBy('is_official','DESC')
            ->get();

        $odt_folder = storage_path('app/public');

        $zip = new ZipArchive;
        if(file_exists($odt_folder.'/'.$year.'年級'.$sex.'子組'.$item->name.'獎狀.odt')){
            unlink($odt_folder.'/'.$year.'年級'.$sex.'子組'.$item->name.'獎狀.odt');
        }

        if ($zip->open($odt_folder.'/'.$year.'年級'.$sex.'子組'.$item->name.'獎狀.odt', ZipArchive::CREATE) === TRUE) {
            $zip->addFile($odt_folder . '/demo/manifest.rdf', 'manifest.rdf');
            $zip->addFile($odt_folder . '/demo/meta.xml', 'meta.xml');
            $zip->addFile($odt_folder . '/demo/settings.xml', 'settings.xml');

            $content = $odt_folder . '/demo/content.xml';
            if (file_exists($content)) {
                $fp = fopen($content, "r");
                $str = fread($fp, filesize($content));//指定讀取大小，這裡把整個檔案內容讀取出來

                $d = explode('<office:body>',$str);
                $odt_head = $d[0];
                $a = explode('</office:body>',$d[1]);
                $odt_body = $a[0];
                $odt_foot = $a[1];

                $data_body = null;
                $y = date('Y') - 1911;

                $score_data = [];
                $group_student = [];
                $i = 0;
                $item_name = $item->name;
                $print_date = "中華民國".$y.'年'.date('m').'月'.date('d').'日';
                $print_date_c = date('Y').'年'.date('m').'月'.date('d').'日';
                //取代
                $action_name = str_replace('報名','',$action->name);

                $first = $student_signs->first();
                $last_group =$first->student_year.$first->student_class.$first->group_num;
                foreach($student_signs as $student_sign) {
                    //if(!empty($student_sign->achievement)){
                        if($student_sign->student_year=="幼小" or $student_sign->student_year=="幼中" or $student_sign->student_year=="幼大"){
                            $score_data[$i]['year_name'] = $cht_num[$student_sign->student_year];
                            $score_data[$i]['year_class_name'] = $cht_num[$student_sign->student_year].$student_sign->student_class . "班";
                            $score_data[$i]['cht_year_class_name'] = $cht_num[$student_sign->student_year] . $cht_num[$student_sign->student_class] . "班";
                        }else{
                            $score_data[$i]['year_name'] = $cht_num[$student_sign->student_year] . "年級";
                            $score_data[$i]['year_class_name'] = $cht_num[$student_sign->student_year] . "年" . $student_sign->student_class . "班";
                            $score_data[$i]['cht_year_class_name'] = $cht_num[$student_sign->student_year] . "年" . $cht_num[$student_sign->student_class] . "班";
                        }
                        $score_data[$i]['class_name'] = $student_sign->student_class . "班";
                        $score_data[$i]['cht_class_name'] = $cht_num[$student_sign->student_class] . "班";

                        $score_data[$i]['ranking'] = $student_sign->ranking;
                        $score_data[$i]['achievement'] = $student_sign->achievement;
                        if($student_sign->is_official){
                            $score_data[$i]['this_student'] = $student_sign->student->name;
                        }else{
                            $score_data[$i]['this_student'] = $student_sign->student->name."(候)";
                        }
                        $score_data[$i]['group'] = ($sex=="4")?"不分性別組":$sex."子組";
                        $i++;

                    //}
                }

                $i=0;
                foreach($score_data as $k=>$v){
                    if($i<$item->reward*($item->official+$item->reserve)){
                        $str2 = str_replace("{{年班同學}}", $v['year_class_name']." ".$v['this_student'], $odt_body);
                        $str2 = str_replace("{{姓名}}", $v['this_student'], $str2);
                        $str2 = str_replace("{{年班}}", $v['year_class_name'], $str2);
                        $str2 = str_replace("{{國字年班}}", $v['cht_year_class_name'], $str2);
                        $str2 = str_replace("{{年級}}", $v['year_name'], $str2);
                        $str2 = str_replace("{{班別}}", $v['class_name'], $str2);
                        $str2 = str_replace("{{國字班別}}", $v['cht_class_name'], $str2);
                        $str2 = str_replace("{{運動會名稱}}", $action_name, $str2);
                        $str2 = str_replace("{{組別}}", $v['group'], $str2);
                        $str2 = str_replace("{{項目}}", $item_name, $str2);
                        $str2 = str_replace("{{名次}}", "第".$v['ranking']."名", $str2);
                        $str2 = str_replace("{{國字名次}}", "第".$cht_num[$v['ranking']]."名", $str2);
                        $str2 = str_replace("{{成績}}", $v['achievement'], $str2);
                        $str2 = str_replace("{{日期}}", $print_date, $str2);
                        $str2 = str_replace("{{西元日期}}", $print_date_c, $str2);

                        $data_body .= $str2;
                        $i++;
                    }
                }

                $odt = $odt_head."<office:body>".$data_body."</office:body>".$odt_foot;

                //dd($str);

                //寫入 content2.xml
                //先刪除
                if(file_exists($odt_folder . '/demo/content2.xml')){
                    unlink($odt_folder . '/demo/content2.xml');
                }
                $fp2 = fopen($odt_folder . '/demo/content2.xml', "a+"); //開啟檔案
                fwrite($fp2, $odt);
                fclose($fp2);

                $zip->addFile($odt_folder . '/demo/content2.xml', 'content.xml');
                $zip->addFile($odt_folder . '/demo/mimetype', 'mimetype');
                $zip->addFile($odt_folder . '/demo/styles.xml', 'styles.xml');
                $zip->addFile($odt_folder . '/demo/META-INF/manifest.xml', 'META-INF/manifest.xml');

                $zip->close();
            }

            return response()->download($odt_folder.'/'.$year.'年級'.$sex.'子組'.$item->name.'獎狀.odt');
        }


    }

    public function all_scores($action_id=null)
    {
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $bdmin = check_power('運動會報名', 'B', auth()->user()->id);

        $actions = Action::orderBy('id','DESC')->get();
        $action_array = [];
        foreach($actions as $action){
            $action_array[$action->id] = $action->name;
        }
        $select_action = null;
        if(empty($action_id)){
            $select_action = key($action_array);
        }else{
            $select_action = $action_id;
        }

        $action = [];
        if($select_action) {
            $action = Action::find($select_action);            
        }
        $student_score = [];
        foreach($action->items as $item){
            $item_name[$item->id] = $item->name;
            $years = unserialize($item->years);           
            //$item_reward[$item->id] = $item->reward;             
            foreach($years as $k=>$v){
                if($item->group==1){
                    if(!isset($year_item[$v]['男'])) $year_item[$v]['男'] = [];                
                    array_push($year_item[$v]['男'],$item->id);
                }
                if($item->group==2){
                    if(!isset($year_item[$v]['女'])) $year_item[$v]['女'] = [];                
                    array_push($year_item[$v]['女'],$item->id);
                }
                if($item->group==3){
                    if(!isset($year_item[$v]['男'])) $year_item[$v]['男'] = [];                
                    array_push($year_item[$v]['男'],$item->id);
                    if(!isset($year_item[$v]['女'])) $year_item[$v]['女'] = [];                
                    array_push($year_item[$v]['女'],$item->id);
                } 
                if($item->group==4){
                    if(!isset($year_item[$v]['全班'])) $year_item[$v]['全班'] = [];                
                    array_push($year_item[$v]['全班'],$item->id);
                }          

            }            
            $student_signs = StudentSign::where('item_id',$item->id)
                ->where('ranking',"<>",null)
                ->where('ranking',"<=",$item->reward)
                ->orderBy('ranking')
                ->get();
            if($item->game_type != "class"){                            
                foreach($student_signs as $student_sign){
                    $student_score[$student_sign->student_year][$student_sign->sex][$student_sign->item_id][$student_sign->ranking]['class'] = $student_sign->student_year.sprintf("%02s",$student_sign->student_class);
                    $student_score[$student_sign->student_year][$student_sign->sex][$student_sign->item_id][$student_sign->ranking]['name'] = $student_sign->student->name;
                    $student_score[$student_sign->student_year][$student_sign->sex][$student_sign->item_id][$student_sign->ranking]['achievement'] = $student_sign->achievement;
                }
            }else{
                foreach($student_signs as $student_sign){
                    $student_score[$student_sign->student_year]['全班'][$student_sign->item_id][$student_sign->ranking]['class'] = $student_sign->student_year.sprintf("%02s",$student_sign->student_class);
                    $student_score[$student_sign->student_year]['全班'][$student_sign->item_id][$student_sign->ranking]['name'] = null;
                    $student_score[$student_sign->student_year]['全班'][$student_sign->item_id][$student_sign->ranking]['achievement'] = $student_sign->achievement;
                }
            }
            

        }        
        ksort($year_item);
        
        //dd($year_item);
        //dd($student_score);
        $data = [
            'admin'=>$admin,
            'bdmin'=>$bdmin,            
            'action_array'=>$action_array,
            'select_action'=>$select_action,
            'action'=>$action,
            //'item_reward'=>$item_reward,
            'student_score'=>$student_score,
            'year_item'=>$year_item,
            'cht_num' =>config('chcschool.cht_num'),
            'item_name'=>$item_name,
        ];

        return view('sport_meetings.all_scores',$data);
    }

    public function all_scores_print(Action $action)
    {                                
        $student_score = [];
        $cht_num = config('chcschool.cht_num');
        foreach($action->items as $item){
            $item_name[$item->id] = $item->name;
            $years = unserialize($item->years);           
            //$item_reward[$item->id] = $item->reward;             
            foreach($years as $k=>$v){
                if($item->group==1){
                    if(!isset($year_item[$v]['男'])) $year_item[$v]['男'] = [];                
                    array_push($year_item[$v]['男'],$item->id);
                }
                if($item->group==2){
                    if(!isset($year_item[$v]['女'])) $year_item[$v]['女'] = [];                
                    array_push($year_item[$v]['女'],$item->id);
                }
                if($item->group==3){
                    if(!isset($year_item[$v]['男'])) $year_item[$v]['男'] = [];                
                    array_push($year_item[$v]['男'],$item->id);
                    if(!isset($year_item[$v]['女'])) $year_item[$v]['女'] = [];                
                    array_push($year_item[$v]['女'],$item->id);
                }   
                if($item->group==4){
                    if(!isset($year_item[$v]['全班'])) $year_item[$v]['全班'] = [];                
                    array_push($year_item[$v]['全班'],$item->id);
                }                
            }

            $student_signs = StudentSign::where('item_id',$item->id)
                ->where('ranking',"<>",null)
                ->where('ranking',"<=",$item->reward)
                ->orderBy('ranking')
                ->get();            
            if($item->game_type != "class"){                            
                foreach($student_signs as $student_sign){
                    $student_score[$student_sign->student_year][$student_sign->sex][$student_sign->item_id][$student_sign->ranking]['class'] = $student_sign->student_year.sprintf("%02s",$student_sign->student_class);
                    $student_score[$student_sign->student_year][$student_sign->sex][$student_sign->item_id][$student_sign->ranking]['name'] = $student_sign->student->name;
                    $student_score[$student_sign->student_year][$student_sign->sex][$student_sign->item_id][$student_sign->ranking]['achievement'] = $student_sign->achievement;
                }
            }else{
                foreach($student_signs as $student_sign){
                    $student_score[$student_sign->student_year]['全班'][$student_sign->item_id][$student_sign->ranking]['class'] = $student_sign->student_year.sprintf("%02s",$student_sign->student_class);
                    $student_score[$student_sign->student_year]['全班'][$student_sign->item_id][$student_sign->ranking]['name'] = null;
                    $student_score[$student_sign->student_year]['全班'][$student_sign->item_id][$student_sign->ranking]['achievement'] = $student_sign->achievement;
                }
            }

        }
        ksort($year_item);
        
        // 建立 PhpWord 實例
        $phpWord = new PhpWord();             
        // 設置 A4 橫向紙張
        $sectionStyle = [
            'marginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // 上邊界 2 厘米
            'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // 下邊界 2 厘米
            'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // 左邊界 2.5 厘米
            'marginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // 右邊界 2.5 厘米
            'orientation' => 'landscape', // 橫向
        ];
        // 添加一個章節
        $section = $phpWord->addSection($sectionStyle);

        $phpWord->addTitleStyle(
            1, // 標題層級（1 表示最高級標題）
            ['size' => 24, 'bold' => true, 'color' => '000000'], // 字體樣式
            ['alignment' => 'center'] // 對齊方式
        );
        
        $tableStyle = [     
            'cellMarginTop' => 100,    // 單元格上內邊距
            'cellMarginBottom' => 100, // 單元格下內邊距
            'cellMarginLeft' => 100,   // 單元格左內邊距
            'cellMarginRight' => 100,  // 單元格右內邊距       
            'borderSize' => 6,        // 邊框粗細
            'borderColor' => '000000' // 邊框顏色
        ];
        $phpWord->addTableStyle('myTableStyle', $tableStyle);
        // 添加文字內容        

        foreach($year_item as $k=>$v){
            $section->addTitle($action->name.' '.$cht_num[$k].'年級 田徑賽成績一覽表',1);
            
            $table = $section->addTable('myTableStyle');
            $table->addRow();
            $table->addCell(3000,['vMerge' => 'restart','gridSpan' => 2]);   
            for($i=1;$i<6;$i++){
                $table->addCell(2550,['gridSpan' => 3])->addText('第'.$cht_num[$i].'名',['size' => 14],['alignment' => 'center']);   
            }
            $table->addRow();
            $table->addCell(3000,['vMerge' => 'continue','gridSpan' => 2]);   
            for($i=1;$i<6;$i++){
                $table->addCell(850)->addText('班級',['size' => 14],['alignment' => 'center']);   
                $table->addCell(850)->addText('姓名',['size' => 14],['alignment' => 'center']);   
                $table->addCell(850)->addText('紀錄',['size' => 14],['alignment' => 'center']);   
            }

            $last_sex="";            
            foreach($v as $k1=>$v1){                
                foreach($v1 as $k2=>$v2){
                    $table->addRow();     
                    if($last_sex != $k1){                        
                        $table->addCell(1000,['vMerge' => 'restart','valign' => 'center'])->addText($k1."生",['size' => 14],['alignment' => 'center']);                       
                    }else{                    
                        $table->addCell(1000,['vMerge' => 'continue']);   
                    }                    
                    $table->addCell(2000)->addText($item_name[$v2],['size' => 14],['alignment' => 'center']);   
                    for($i=1;$i<=5;$i++){
                        $class=(isset($student_score[$k][$k1][$v2][$i]['class']))?$student_score[$k][$k1][$v2][$i]['class']:null;                                          
                        $name=(isset($student_score[$k][$k1][$v2][$i]['name']))?$student_score[$k][$k1][$v2][$i]['name']:null;
                        $achievement=(isset($student_score[$k][$k1][$v2][$i]['achievement']))?$student_score[$k][$k1][$v2][$i]['achievement']:null;
                        $table->addCell(850)->addText($class,['size' => 14],['alignment' => 'center']);   
                        $table->addCell(850)->addText($name,['size' => 10],['alignment' => 'center']);   
                        $table->addCell(850)->addText($achievement,['size' => 10],['alignment' => 'center']);   
                    }
                    $last_sex=$k1;                    
                }                    
            }
            

            
            // 插入換頁符
            $section->addPageBreak();
        }



        // 定義文件名稱
        $fileName = $action->name.'田徑賽成績一覽表.docx';
        
        // 將文件存為臨時檔案
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);

        // 寫入文件
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        // 回傳文件下載回應
        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
                    
    }

    public function total_scores($action_id=null)
    {
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $bdmin = check_power('運動會報名', 'B', auth()->user()->id);

        $actions = Action::orderBy('id','DESC')->get();
        $action_array = [];
        foreach($actions as $action){
            $action_array[$action->id] = $action->name;
        }
        $select_action = null;
        if(empty($action_id)){
            $select_action = key($action_array);
        }else{
            $select_action = $action_id;
        }

        $action = [];
        $student_classes = [];
        $class_data = [];
        $sex_item['男'] = [];
        $sex_item['女'] = [];
        $sex_item['不'] = [];
        $item_ranking = [];
        if($select_action) {
            $action = Action::find($select_action);            
            $student_classes = StudentClass::where('semester',$action->semester)
            ->orderBy('student_year','DESC')->orderBy('student_class')->get();

            foreach($student_classes as $student_class){
                array_push($class_data,$student_class->student_year.sprintf("%02s",$student_class->student_class));
            }

            foreach($action->items as $item){
                if($item->group == 1 or $item->group == 3){
                    $sex_item['男'][$item->id] = $item->name;
                }
                if($item->group == 2 or $item->group == 3){
                    $sex_item['女'][$item->id] = $item->name;
                }
                if($item->group == 4){
                    $sex_item['不'][$item->id] = $item->name;
                }
                if(!empty($item->scoring)){
                    $scoring_array[$item->id] = explode(",",$item->scoring);                    
                }else{
                    $scoring_array[$item->id] = [];
                    $scoring[$item->id] = [];
                }                
                foreach($scoring_array[$item->id] as $k=>$v){
                    $scoring[$item->id][$k+1] = $v;
                }
             }

            $student_signs = StudentSign::where('action_id',$action->id)->get();
            foreach($student_signs as $student_sign){
                if($student_sign->game_type=="personal"){
                    $num = $student_sign->student_id;
                }
                if($student_sign->game_type=="group"){
                    $num = $student_sign->group_num;
                }
                if($student_sign->game_type=="class"){
                    $num = 1;
                }
                if($student_sign->sex==4){
                    $item_ranking[$student_sign->student_year.sprintf("%02s",$student_sign->student_class)][$student_sign->item_id]['不'][$num] = $student_sign->ranking;
                }else{
                    $item_ranking[$student_sign->student_year.sprintf("%02s",$student_sign->student_class)][$student_sign->item_id][$student_sign->sex][$num] = $student_sign->ranking;
                }            
                
            }
        }

        $row = [];
        foreach($class_data as $k=>$v){
            foreach($sex_item['男'] as $k1=>$v1){
                $one = null;
                if(isset($item_ranking[$v][$k1]['男'])){
                    foreach($item_ranking[$v][$k1]['男'] as $k2=>$v2){
                        if(isset($scoring[$k1][$v2])){
                            $one = (int)$one+(int)$scoring[$k1][$v2];
                        } 
                    }
                }
                $row[$v] = (isset($row[$v]))?$row[$v]:null;
                $row[$v] = (int)$row[$v]+(int)$one;
            }
            foreach($sex_item['女'] as $k1=>$v1){
                $one = null;
                if(isset($item_ranking[$v][$k1]['女'])){
                    foreach($item_ranking[$v][$k1]['女'] as $k2=>$v2){
                        if(isset($scoring[$k1][$v2])){
                            $one = (int)$one+(int)$scoring[$k1][$v2];
                        } 
                    }
                }
                $row[$v] = (isset($row[$v]))?$row[$v]:null;
                $row[$v] = (int)$row[$v]+(int)$one;
            }
            foreach($sex_item['不'] as $k1=>$v1){
                $one = null;
                if(isset($item_ranking[$v][$k1]['不'])){
                    foreach($item_ranking[$v][$k1]['不'] as $k2=>$v2){
                        if(isset($scoring[$k1][$v2])){
                            $one = (int)$one+(int)$scoring[$k1][$v2];
                        } 
                    }
                }
                $row[$v] = (isset($row[$v]))?$row[$v]:null;
                $row[$v] = (int)$row[$v]+(int)$one;
            }
        }

        $last_y = null;
       foreach($row as $k=>$v){
        if(strlen($k) == 4){
            $y = substr($k,0,2);
        }else{
            $y = substr($k,0,1);
        }
        
        $year[$y][$k] = $v;        
       }
       foreach($year as $k=>$v){
        arsort($year[$k]);
       }
       
       foreach($year as $k=>$v){
        $n=1;
        foreach($v as $k1=>$v1){
            if($n<4){
                $year_award[$k1] = $n;
            }else{
                $year_award[$k1] = null;
            }
            
            $n++;
        }                
       }
       //dd($year_award);
        $data = [
            'admin'=>$admin,
            'bdmin'=>$bdmin,
            'action_array'=>$action_array,
            'select_action'=>$select_action,
            'action'=>$action,
            'sex_item'=>$sex_item,
            'class_data'=>$class_data,
            'item_ranking'=>$item_ranking,
            'scoring'=>$scoring,
            'year_award'=>$year_award,
        ];

        return view('sport_meetings.total_scores',$data);
    }

    public function total_scores_print(Action $action)
    {
        $student_classes = [];
        $class_data = [];
        $sex_item['男'] = [];
        $sex_item['女'] = [];
        $sex_item['不'] = [];
        $item_ranking = [];

        $student_classes = StudentClass::where('semester',$action->semester)
            ->orderBy('student_year','DESC')->orderBy('student_class')->get();

            foreach($student_classes as $student_class){
                array_push($class_data,$student_class->student_year.sprintf("%02s",$student_class->student_class));
            }

            foreach($action->items as $item){
                if($item->group == 1 or $item->group == 3){
                    $sex_item['男'][$item->id] = $item->name;
                }
                if($item->group == 2 or $item->group == 3){
                    $sex_item['女'][$item->id] = $item->name;
                }
                if($item->group == 4){
                    $sex_item['不'][$item->id] = $item->name;
                }
                if(!empty($item->scoring)){
                    $scoring_array[$item->id] = explode(",",$item->scoring);                    
                }else{
                    $scoring_array[$item->id] = [];
                    $scoring[$item->id] = [];
                }                
                foreach($scoring_array[$item->id] as $k=>$v){
                    $scoring[$item->id][$k+1] = $v;
                }
             }

            $student_signs = StudentSign::where('action_id',$action->id)->get();
            foreach($student_signs as $student_sign){
                if($student_sign->game_type=="personal"){
                    $num = $student_sign->student_id;
                }
                if($student_sign->game_type=="group"){
                    $num = $student_sign->group_num;
                }
                if($student_sign->game_type=="class"){
                    $num = 1;
                }
                if($student_sign->sex==4){
                    $item_ranking[$student_sign->student_year.sprintf("%02s",$student_sign->student_class)][$student_sign->item_id]['不'][$num] = $student_sign->ranking;
                }else{
                    $item_ranking[$student_sign->student_year.sprintf("%02s",$student_sign->student_class)][$student_sign->item_id][$student_sign->sex][$num] = $student_sign->ranking;
                }            
                
            }

        $row = [];
        foreach($class_data as $k=>$v){
            foreach($sex_item['男'] as $k1=>$v1){
                $one = null;
                if(isset($item_ranking[$v][$k1]['男'])){
                    foreach($item_ranking[$v][$k1]['男'] as $k2=>$v2){
                        if(isset($scoring[$k1][$v2])){
                            $one = (int)$one+(int)$scoring[$k1][$v2];
                        } 
                    }
                }
                $row[$v] = (isset($row[$v]))?$row[$v]:null;
                $row[$v] = (int)$row[$v]+(int)$one;
            }
            foreach($sex_item['女'] as $k1=>$v1){
                $one = null;
                if(isset($item_ranking[$v][$k1]['女'])){
                    foreach($item_ranking[$v][$k1]['女'] as $k2=>$v2){
                        if(isset($scoring[$k1][$v2])){
                            $one = (int)$one+(int)$scoring[$k1][$v2];
                        } 
                    }
                }
                $row[$v] = (isset($row[$v]))?$row[$v]:null;
                $row[$v] = (int)$row[$v]+(int)$one;
            }
            foreach($sex_item['不'] as $k1=>$v1){
                $one = null;
                if(isset($item_ranking[$v][$k1]['不'])){
                    foreach($item_ranking[$v][$k1]['不'] as $k2=>$v2){
                        if(isset($scoring[$k1][$v2])){
                            $one = (int)$one+(int)$scoring[$k1][$v2];
                        } 
                    }
                }
                $row[$v] = (isset($row[$v]))?$row[$v]:null;
                $row[$v] = (int)$row[$v]+(int)$one;
            }
        }

        $last_y = null;
       foreach($row as $k=>$v){
        if(strlen($k) == 4){
            $y = substr($k,0,2);
        }else{
            $y = substr($k,0,1);
        }
        
        $year[$y][$k] = $v;        
       }
       foreach($year as $k=>$v){
        arsort($year[$k]);
       }
       
       foreach($year as $k=>$v){
        $n=1;
        foreach($v as $k1=>$v1){
            if($n<4){
                $year_award[$k1] = $n;
            }else{
                $year_award[$k1] = null;
            }
            
            $n++;
        }                
       }


        // 建立 PhpWord 實例
        $phpWord = new PhpWord();             
        // 設置 A4 橫向紙張
        $sectionStyle = [
            'marginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // 上邊界 2 厘米
            'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // 下邊界 2 厘米
            'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // 左邊界 2.5 厘米
            'marginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // 右邊界 2.5 厘米
            'orientation' => 'landscape', // 橫向
        ];
        // 添加一個章節
        $section = $phpWord->addSection($sectionStyle);

        $phpWord->addTitleStyle(
            1, // 標題層級（1 表示最高級標題）
            ['size' => 24, 'bold' => true, 'color' => '000000'], // 字體樣式
            ['alignment' => 'center'] // 對齊方式
        );
        
        $tableStyle = [     
            'cellMarginTop' => 100,    // 單元格上內邊距
            'cellMarginBottom' => 100, // 單元格下內邊距
            'cellMarginLeft' => 100,   // 單元格左內邊距
            'cellMarginRight' => 100,  // 單元格右內邊距       
            'borderSize' => 6,        // 邊框粗細
            'borderColor' => '000000' // 邊框顏色
        ];
        $phpWord->addTableStyle('myTableStyle', $tableStyle);
        // 添加文字內容  
        $section->addTitle($action->name.' 田徑賽計分總表',1);
            
        $table = $section->addTable('myTableStyle');
        $table->addRow();
        $table->addCell(800,['vMerge' => 'restart'])->addText('項目',['size' => 14],['alignment' => 'center']);             
        $b = (int)round(6000/count($sex_item['男']));    
        $all_b = $b*count($sex_item['男']);
        
        $table->addCell($all_b,['gridSpan' => count($sex_item['男'])])->addText('男生',['size' => 14],['alignment' => 'center']);   
        
        $g = (int)round(6000/count($sex_item['女']));    
        $all_g = $g*count($sex_item['女']);
        $table->addCell($all_g,['gridSpan' => count($sex_item['男'])])->addText('女生',['size' => 14],['alignment' => 'center']);   
        foreach($sex_item['不'] as $k=>$v){
            $table->addCell(1200,['vMerge' => 'restart'])->addText($v,['size' => 14],['alignment' => 'center']);   
        }
        $table->addCell(800,['vMerge' => 'restart'])->addText('總計',['size' => 14],['alignment' => 'center']);   
        $table->addCell(800,['vMerge' => 'restart'])->addText('名次',['size' => 14],['alignment' => 'center']);   
        
        $table->addRow();        
        $table->addCell(800,['vMerge' => 'continue']);
        foreach($sex_item['男'] as $k=>$v){
            $table->addCell($b)->addText($v,['size' => 14],['alignment' => 'center']);         
        }
        foreach($sex_item['女'] as $k=>$v){
            $table->addCell($g)->addText($v,['size' => 14],['alignment' => 'center']);         
        }
        foreach($sex_item['不'] as $k=>$v){
            $table->addCell(1200,['vMerge' => 'continue']);   
        }
        $table->addCell(800,['vMerge' => 'continue']);  
        $table->addCell(800,['vMerge' => 'continue']);  

        foreach($class_data as $k=>$v){
            if(!isset($show_class[substr($v,0,1)])) $show_class[substr($v,0,1)] = "";
            $show_class[substr($v,0,1)] = $show_class[substr($v,0,1)]."\"".$v."\",";
            $table->addRow();        
            $table->addCell(800)->addText($v,['size' => 14],['alignment' => 'center']);  
            foreach($sex_item['男'] as $k1=>$v1){
                $one = null;
                if(isset($item_ranking[$v][$k1]['男'])){
                    foreach($item_ranking[$v][$k1]['男'] as $k2=>$v2){
                        if(isset($scoring[$k1][$v2])){
                            $one = (int)$one+(int)$scoring[$k1][$v2];
                        } 
                    }                    
                }
                $table->addCell($b)->addText($one,['size' => 14],['alignment' => 'center']); 
            }
            foreach($sex_item['女'] as $k1=>$v1){
                $one = null;
                if(isset($item_ranking[$v][$k1]['女'])){
                    foreach($item_ranking[$v][$k1]['女'] as $k2=>$v2){
                        if(isset($scoring[$k1][$v2])){
                            $one = (int)$one+(int)$scoring[$k1][$v2];
                        } 
                    }                    
                }
                $table->addCell($g)->addText($one,['size' => 14],['alignment' => 'center']); 
            }
            foreach($sex_item['不'] as $k1=>$v1){
                $one = null;
                if(isset($item_ranking[$v][$k1]['不'])){
                    foreach($item_ranking[$v][$k1]['不'] as $k2=>$v2){
                        if(isset($scoring[$k1][$v2])){
                            $one = (int)$one+(int)$scoring[$k1][$v2];
                        } 
                    }                    
                }
                $table->addCell(1200)->addText($one,['size' => 14],['alignment' => 'center']); 
            }
            $s = ($row[$v] <> 0)?$row[$v]:null;                        
            $table->addCell(800)->addText($s,['size' => 14],['alignment' => 'center']);                               
            $table->addCell(800)->addText($year_award[$v],['size' => 14],['alignment' => 'center']);                                           
        }

                    
                    
        

        






        // 定義文件名稱
        $fileName = $action->name.'田徑賽成績一覽表.docx';
                
        // 將文件存為臨時檔案
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);

        // 寫入文件
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        // 回傳文件下載回應
        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);

    }

    public function action_set_number_null(Action $action)
    {
        $att_set_null['number'] = null;
        ClubStudent::where('semester',$action->semester)
            ->update($att_set_null);
        return redirect()->back()->withErrors(['error'=>['編入號碼清空完成！']]);
    }

    public function action_set_number(Action $action)
    {
        $att_set_null['number'] = null;
        ClubStudent::where('semester',$action->semester)
            ->update($att_set_null);
        $student_signs = StudentSign::where('action_id',$action->id)
            ->orderBy('student_year')
            ->orderBy('student_class')
            ->orderBy('num')
            ->get();
        
        $students = [];
        if(!empty($student_signs)){
            foreach($student_signs as $student_sign){
                if($student_sign->item->game_type <> "class"){
                    $students[$student_sign->student_id]['name'] = $student_sign->student->name;
                    $students[$student_sign->student_id]['year'] = $student_sign->student_year;
                    $students[$student_sign->student_id]['class'] = $student_sign->student_class;
                    $students[$student_sign->student_id]['num'] = (int)substr($student_sign->student->class_num,3,2);
                }
            }
        }
        $s = 1;        
	$last_class = "";    
	foreach($students as $k => $v){
	    if($last_class <> $v['year'].$v['class']) $s =1;
            if($action->numbers == 4){
                $number = $v['year'].$v['class'].sprintf("%02s",$s);
            }
            if($action->numbers == 5){
                $number = $v['year'].sprintf("%02s",$v['class']).sprintf("%02s",$s);
            }
            $student = ClubStudent::where('id',$k)->first();
            $att['number'] = $number;                  
            $student->update($att);
	    $s++;
	    $last_class = $v['year'].$v['class'];
        }

        return redirect()->back()->withErrors(['error'=>['編入號碼完成！']]);

    }

    public function records($action_id=null)
    {
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $bdmin = check_power('運動會報名', 'B', auth()->user()->id);

        $actions = Action::orderBy('id','DESC')->get();
        $action_array = [];
        foreach($actions as $action){
            $action_array[$action->id] = $action->name;
        }
        $select_action = null;
        if(empty($action_id)){
            $select_action = key($action_array);
        }else{
            $select_action = $action_id;
        }

        $years = [];
        $year_students = [];

        $items = [];
        $student_classes = [];
        $student_signs = [];
        $action = [];
        $st_number = [];
        if($select_action){
            $action = Action::find($select_action);
            
            $items = Item::where('action_id',$select_action)
                ->get();

            $student_classes = StudentClass::where('semester',$action->semester)            
                ->orderBy('student_year')
                ->orderBy('student_class')
                ->get();

            $student_signs = StudentSign::where('action_id',$action->id)                
                ->orderBy('student_year')
		        ->orderBy('student_class')
		        ->orderBy('num')
                ->get();

            foreach($student_signs as $student_sign){
                $years[$student_sign->student_year] = 1;
                if($student_sign->item->game_type == "class"){
                    $year_students[$student_sign->student_year][$student_sign->item_id][$student_sign->sex][$student_sign->student_id] = $student_sign->get_student_class->student_year."年".$student_sign->get_student_class->student_class."班";
                }
                if($student_sign->item->game_type == "personal" or $student_sign->item->game_type == "group"){
                    $year_students[$student_sign->student_year][$student_sign->item_id][$student_sign->sex][$student_sign->student_id] = $student_sign->student->name;
                    $st_number[$student_sign->student_id] = $student_sign->student->number;
                }
            }
        }

        $data = [
            'admin'=>$admin,
            'bdmin'=>$bdmin,
            'items'=>$items,
            'student_signs'=>$student_signs,
            'action_array'=>$action_array,
            'select_action'=>$select_action,
            'student_classes'=>$student_classes,
            'action'=>$action,
            'years'=>$years,
            'year_students'=>$year_students,
            'st_number'=>$st_number,
        ];

        return view('sport_meetings.records',$data);
    }

    public function download_records(Action $action)
    {
        $items = Item::where('action_id',$action->id)
            ->orderBy('order')
            ->get();

        $student_signs = StudentSign::where('action_id',$action->id)
            ->orderBy('student_year')
            ->orderBy('student_class')
            ->orderBy('is_official','DESC')
            ->orderBy('num')
            ->get();

        $cht_num = config('chcschool.cht_num');

        foreach($student_signs as $student_sign){
            if($student_sign->game_type == "personal"){
                $sign_data[$student_sign->item_id][$student_sign->id]['item_name'] = $student_sign->item->name;
                $sign_data[$student_sign->item_id][$student_sign->id]['year_class'] = $cht_num[$student_sign->student_year]."年".$student_sign->student_class."班";
                $sign_data[$student_sign->item_id][$student_sign->id]['num'] = $student_sign->num;
                $sign_data[$student_sign->item_id][$student_sign->id]['number'] = $student_sign->student->number;
                $sign_data[$student_sign->item_id][$student_sign->id]['name'] = $student_sign->student->name;
                $sign_data[$student_sign->item_id][$student_sign->id]['sex'] = $student_sign->sex."子組";
            }
            if($student_sign->game_type == "group"){
                if($student_sign->sex=="男"){
                    $k = 'b'.$student_sign->student_year.$student_sign->student_class.$student_sign->group_num;
                }
                if($student_sign->sex=="女"){
                    $k = 'g'.$student_sign->student_year.$student_sign->student_class.$student_sign->group_num;
                }
                $sign_data[$student_sign->item_id][$k]['item_name'] = $student_sign->item->name;
                $sign_data[$student_sign->item_id][$k]['year_class'] = $cht_num[$student_sign->student_year]."年".$student_sign->student_class."班";
                $sign_data[$student_sign->item_id][$k]['num'] = "";
                $sign_data[$student_sign->item_id][$k]['number'] = "";
                if(!isset($sign_data[$student_sign->item_id][$k]['name'])){
                    $sign_data[$student_sign->item_id][$k]['name'] = "";
                }
                if(empty($student_sign->is_official)){
                    $sign_data[$student_sign->item_id][$k]['name'] .= $student_sign->student->name."(候) ";
                }else{
                    $sign_data[$student_sign->item_id][$k]['name'] .= $student_sign->student->name." ";
                }

                $sign_data[$student_sign->item_id][$k]['sex'] = $student_sign->sex."子組";
            }
            if($student_sign->game_type == "class"){
                $sign_data[$student_sign->item_id][$student_sign->id]['item_name'] = $student_sign->item->name;
                $sign_data[$student_sign->item_id][$student_sign->id]['year_class'] = $cht_num[$student_sign->student_year]."年".$student_sign->student_class."班";
                $sign_data[$student_sign->item_id][$student_sign->id]['num'] = "";
                $sign_data[$student_sign->item_id][$student_sign->id]['number'] = "";
                $sign_data[$student_sign->item_id][$student_sign->id]['name'] = $cht_num[$student_sign->student_year]."年".$student_sign->student_class."班";
                $sign_data[$student_sign->item_id][$student_sign->id]['sex'] = "班際賽";
            }
        }
        $i = 0;
        foreach($items as $item){
            if(isset($sign_data[$item->id])){
                foreach($sign_data[$item->id] as $k=>$v){
                    $data[$i]['項目名稱'] = $v['item_name'];
                    $data[$i]['班級'] = $v['year_class'];
                    $data[$i]['座號'] = $v['num'];
                    $data[$i]['布牌號碼'] = $v['number'];
                    $data[$i]['姓名'] = $v['name'];
                    $data[$i]['組別'] = $v['sex'];
                    $i++;
                }
            }
        }

        $list = collect($data);

        return (new FastExcel($list))->download($action->semester.'運動會學生報名資料.xlsx');
    }

    public function scores($action_id=null)
    {
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $bdmin = check_power('運動會報名', 'B', auth()->user()->id);

        $actions = Action::orderBy('id','DESC')->get();
        $action_array = [];
        foreach($actions as $action){
            $action_array[$action->id] = $action->name;
        }
        $select_action = null;
        if(empty($action_id)){
            $select_action = key($action_array);
        }else{
            $select_action = $action_id;
        }

        $items = Item::where('action_id',$select_action)            
            ->where('disable',null)
            ->orderBy('order')
            ->get();


        $data = [
            'admin'=>$admin,
            'bdmin'=>$bdmin,
            'action_array'=>$action_array,
            'select_action'=>$select_action,
            'items'=>$items,
        ];

        return view('sport_meetings.scores',$data);
    }

    public function scores_do(Request $request)
    {        
        $admin = check_power('運動會報名', 'A', auth()->user()->id);
        $bdmin = check_power('運動會報名', 'B', auth()->user()->id);
        
        $action = Action::find($request->input('action_id'));
        $item = Item::find($request->input('item_id'));
        $student_signs = StudentSign::where('item_id',$item->id)
            ->where('sex',$request->input('sex'))
            ->where('student_year',$request->input('student_year'))
            ->orderBy('section_num')
            ->orderBy('run_num')
            ->orderBy('student_class')
            ->orderBy('is_official','DESC')
            ->orderBy('student_id')
            ->get();
        
        $student_array = [];
        foreach($student_signs as $student_sign){
            if($item->game_type=="personal"){
                $student_array[$student_sign->id]['id'] = $student_sign->id;
                $student_array[$student_sign->id]['number'] = $student_sign->student->number;
                $student_array[$student_sign->id]['name'] = $student_sign->student->name;                                
                $student_array[$student_sign->id]['section_num'] = $student_sign->section_num;
                $student_array[$student_sign->id]['run_num'] = $student_sign->run_num;
                $student_array[$student_sign->id]['class'] = $student_sign->student_year."年".$student_sign->student_class."班";
            }
            if($item->game_type=="group"){
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['id'] = $student_sign->id;
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['number'] = $student_sign->student->number;
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['name'] = $student_sign->student->name;                                        
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['section_num'] = $student_sign->section_num;
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['run_num'] = $student_sign->run_num;
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['is_official'] = $student_sign->is_official;
                    $student_array[$student_sign->student_year.$student_sign->student_class.$student_sign->group_num][$student_sign->id]['class'] = $student_sign->student_year."年".$student_sign->student_class."班";

            }
        }        
        if($item->game_type=="class") {

            $cht_num = config('chcschool.cht_num');
            foreach ($student_signs as $student_sign) {
                $class_name = $cht_num[$student_sign->student_year] . "年" . $student_sign->student_class . "班";
                $student_array[$student_sign->id]['name'] = $class_name;
                $student_array[$student_sign->id]['student_year'] = $student_sign->student_year;
                $student_array[$student_sign->id]['student_class'] = $student_sign->student_class;                                
                $student_array[$student_sign->id]['section_num'] = $student_sign->section_num;
                $student_array[$student_sign->id]['run_num'] = $student_sign->run_num;
            }
        }
        
        $data = [
            'admin'=>$admin,
            'bdmin'=>$bdmin,
            'year'=>$request->input('student_year'),
            'sex'=>$request->input('sex'),
            'action'=>$action,
            'item'=>$item,
            'student_signs'=>$student_signs,
            'student_array'=>$student_array,
        ];

        return view('sport_meetings.scores_do',$data);

    }

    public function scores_update(Request $request)
    {                        
        $section_num = $request->input('section_num');
        $run_num = $request->input('run_num');
        $action_id = $request->input('action_id');
        $item_id = $request->input('item_id');
        $item = Item::find($item_id);
        
        foreach($section_num as $k=>$v){            
            $att['section_num'] = $v;
            $att['run_num'] = (isset($run_num[$k]))?$run_num[$k]:null;            
            
            $student_sign = StudentSign::find($k);
            
            if($item->game_type=="group"){                
                $all_student_signs = StudentSign::where('item_id',$item->id)
                    ->where('student_year',$student_sign->student_year)
                    ->where('student_class',$student_sign->student_class)                    
                    ->where('sex',$student_sign->sex)                    
                    ->where('group_num',$student_sign->group_num)                        
                    ->update($att);                                                            
            }else{
                $student_sign->update($att);                
            }
            
        }        
        return redirect()->route('sport_meeting.scores_do',['action_id'=>$action_id,'item_id'=>$item_id,'student_year'=>$student_sign->student_year,'sex'=>$student_sign->sex]);
    }

    public function scores_print(Action $action,Item $item,$year,$sex){                
        
        $cht_num = config('chcschool.cht_num');

        $student_signs = StudentSign::where('item_id',$item->id)
            ->where('sex',$sex)
            ->where('student_year',$year)
            ->orderBy('section_num')
            ->orderBy('run_num')
            ->orderBy('student_class')
            ->orderBy('is_official','DESC')
            ->orderBy('student_id')
            ->get();

        $section_num = 0;
        foreach($student_signs as $student_sign){
            //取組數
            $section_num = ($student_sign->section_num > $section_num)?$student_sign->section_num:$section_num;
        }
        $student_array = [];
        $s = 0;
        $r = 0;
        foreach($student_signs as $student_sign){
            if($item->game_type=="personal"){                                     
                $student_array[$student_sign->section_num][$student_sign->run_num]['class'] = $student_sign->student_year.sprintf("%02s",$student_sign->student_class);
                $student_array[$student_sign->section_num][$student_sign->run_num]['number'] = $student_sign->student->number;
                $student_array[$student_sign->section_num][$student_sign->run_num]['name'] = $student_sign->student->name;                                                                                                
            }
            if($item->game_type=="group"){
                $student_array[$student_sign->section_num][$student_sign->run_num]['class'] = $student_sign->student_year."年".$student_sign->student_class."班";
                $student_array[$student_sign->section_num][$student_sign->run_num]['number'] = null;
                if(!isset($student_array[$student_sign->section_num][$student_sign->run_num]['name'])) $student_array[$student_sign->section_num][$student_sign->run_num]['name']= null;
                if($student_sign->is_official == null){
                    $st_name = $student_sign->student->name."(候)";
                }else{
                    $st_name = $student_sign->student->name;
                }
                $student_array[$student_sign->section_num][$student_sign->run_num]['name'] .= $st_name." ";                     
            }                
        }      
        
        if($item->game_type=="class") {   
            foreach ($student_signs as $student_sign) {
                $student_array[$student_sign->section_num][$student_sign->run_num]['class'] = $cht_num[$student_sign->student_year] . "年" . $student_sign->student_class . "班";
                $student_array[$student_sign->section_num][$student_sign->run_num]['number'] = null;
                $student_array[$student_sign->section_num][$student_sign->run_num]['name'] = null;                 
            }
        }
                
        //徑賽
        // 建立 PhpWord 實例
        $phpWord = new PhpWord();
        if($item->type==1){            
            // 設置 A4 橫向紙張
            $sectionStyle = [
                'marginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2), // 上邊界 2 厘米
                'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2), // 下邊界 2 厘米
                //'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5), // 左邊界 2.5 厘米
                //'marginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2.5), // 右邊界 2.5 厘米
                'orientation' => 'landscape', // 橫向
            ];
            // 添加一個章節
            $section = $phpWord->addSection($sectionStyle);

            $phpWord->addTitleStyle(
                1, // 標題層級（1 表示最高級標題）
                ['size' => 24, 'bold' => true, 'color' => '000000'], // 字體樣式
                ['alignment' => 'center'] // 對齊方式
            );
            
            $tableStyle = [     
                'cellMarginTop' => 100,    // 單元格上內邊距
                'cellMarginBottom' => 100, // 單元格下內邊距
                'cellMarginLeft' => 100,   // 單元格左內邊距
                'cellMarginRight' => 100,  // 單元格右內邊距       
                'borderSize' => 6,        // 邊框粗細
                'borderColor' => '000000' // 邊框顏色
            ];
            $phpWord->addTableStyle('myTableStyle', $tableStyle);
            // 添加文字內容
            $section->addTitle($item->name.'檢錄表',1);

            foreach($student_array as $k=>$v){   
                if($item->game_type != "class"){
                    $s = $sex.'生組';
                }else{
                    $s = null;
                }
                $section->addText($cht_num[$year].'年級            '.$s.'          ▢預賽          共 '.$section_num.' 組      第 '.$k.' 組    每組取 2 名決賽',['size' => 14]);                                                                    

                $table = $section->addTable('myTableStyle');

                // 添加表格標題行
                $table->addRow();
                $table->addCell(1200)->addText('道次',['size' => 14],['alignment' => 'center']);               
                foreach($v as $k1=>$v1){
                    if(!isset($cht_num[$k1])) $cht_num[$k1]=null;
                    $table->addCell(1200)->addText($cht_num[$k1],['size' => 14],['alignment' => 'center']);                         
                }
                // 添加表格標題行
                $table->addRow();
                $table->addCell(1200)->addText('班級',['size' => 14],['alignment' => 'center']);               
                foreach($v as $k1=>$v1){
                    $table->addCell(1200)->addText($v1['class'],['size' => 14],['alignment' => 'center']);                         
                }
                if($item->game_type != "class"){
                    // 添加表格標題行
                    if($item->game_type !="group"){
                        $table->addRow();
                        $table->addCell(1200)->addText('號碼',['size' => 14],['alignment' => 'center']);               
                        foreach($v as $k1=>$v1){
                            $table->addCell(1200)->addText($v1['number'],['size' => 14],['alignment' => 'center']);                         
                        }    
                    }        
                    // 添加表格標題行
                    $table->addRow();
                    $table->addCell(1200)->addText('姓名',['size' => 14],['alignment' => 'center']);               
                    foreach($v as $k1=>$v1){
                        $table->addCell(1200)->addText($v1['name'],['size' => 14],['alignment' => 'center']);                         
                    }
                }
                // 添加表格標題行
                $table->addRow();
                $table->addCell(1200)->addText('成績',['size' => 14],['alignment' => 'center']);               
                foreach($v as $k1=>$v1){
                    $table->addCell(1200)->addText(null,['size' => 14],['alignment' => 'center']);                         
                }
                // 添加表格標題行
                $table->addRow();
                $table->addCell(1200)->addText('名次',['size' => 14],['alignment' => 'center']);               
                foreach($v as $k1=>$v1){
                    $table->addCell(1200)->addText(null,['size' => 14],['alignment' => 'center']);                         
                }      
                $section->addTextBreak(1);          
        }
        

            $section->addTextBreak(2);            
        }
        if($item->type==2){
            $sectionStyle = [
                'marginTop' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2), // 上邊界 2 厘米
                'marginBottom' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(2), // 下邊界 2 厘米
                'marginLeft' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // 左邊界 2.5 厘米
                'marginRight' => \PhpOffice\PhpWord\Shared\Converter::cmToTwip(1), // 右邊界 2.5 厘米
                //'orientation' => 'landscape', // 橫向
            ];
            // 添加一個章節
            $section = $phpWord->addSection($sectionStyle);

            $phpWord->addTitleStyle(
                1, // 標題層級（1 表示最高級標題）
                ['size' => 24, 'bold' => true, 'color' => '000000'], // 字體樣式
                ['alignment' => 'center'] // 對齊方式
            );
            
            $tableStyle = [     
                'cellMarginTop' => 100,    // 單元格上內邊距
                'cellMarginBottom' => 100, // 單元格下內邊距
                'cellMarginLeft' => 100,   // 單元格左內邊距
                'cellMarginRight' => 100,  // 單元格右內邊距       
                'borderSize' => 6,        // 邊框粗細
                'borderColor' => '000000' // 邊框顏色
            ];
            $phpWord->addTableStyle('myTableStyle', $tableStyle);
            // 添加文字內容
            $section->addTitle($item->name.'記錄表',1);
            $section->addText($cht_num[$year].'年級            '.$sex.'生組',['size' => 14]);
            $section->addTextBreak(1);  

            if(strpos($item->name,"跳高") !== false){                  
                $table = $section->addTable('myTableStyle');
                $table->addRow();
                $table->addCell(2000,['vMerge' => 'restart','valign' => 'center'])->addText('號碼姓名',['size' => 14],['alignment' => 'center']);   
                $table->addCell(6600,['gridSpan' => 30])->addText('高度',['size' => 14],['alignment' => 'center']);   
                $table->addCell(1200,['vMerge' => 'restart','valign' => 'center'])->addText('成績',['size' => 14],['alignment' => 'center']);   
                $table->addCell(1200,['vMerge' => 'restart','valign' => 'center'])->addText('名次',['size' => 14],['alignment' => 'center']);   
                $table->addRow();
                $table->addCell(2000,['vMerge' => 'continue']);
                $table->addCell(660,['gridSpan' => 3])->addText('80',['size' => 10],['alignment' => 'center']);   
                $table->addCell(660,['gridSpan' => 3])->addText('85',['size' => 10],['alignment' => 'center']);
                $table->addCell(660,['gridSpan' => 3])->addText('90',['size' => 10],['alignment' => 'center']);
                $table->addCell(660,['gridSpan' => 3])->addText('95',['size' => 10],['alignment' => 'center']);
                $table->addCell(660,['gridSpan' => 3])->addText('100',['size' => 10],['alignment' => 'center']);
                $table->addCell(660,['gridSpan' => 3])->addText('105',['size' => 10],['alignment' => 'center']);
                $table->addCell(660,['gridSpan' => 3])->addText('108',['size' => 10],['alignment' => 'center']);
                $table->addCell(660,['gridSpan' => 3])->addText('111',['size' => 10],['alignment' => 'center']);
                $table->addCell(660,['gridSpan' => 3])->addText('120',['size' => 10],['alignment' => 'center']);
                $table->addCell(660,['gridSpan' => 3])->addText('130',['size' => 10],['alignment' => 'center']);         
                $table->addCell(1200,['vMerge' => 'continue']);   
                $table->addCell(1200,['vMerge' => 'continue']);     
                foreach($student_array as $k=>$v){  
                    foreach($v as $k1=>$v1){
                        $table->addRow();
                        $table->addCell(2000)->addText($v1['number'].' '.$v1['name'],['size' => 14],['alignment' => 'center']);                         
                        for($i=0;$i<30;$i++){
                            $table->addCell(220);
                        }
                        $table->addCell(1200);
                        $table->addCell(1200);                        
                    }
                }
            }
            if(strpos($item->name,"跳遠") !== false){
                $table = $section->addTable('myTableStyle');
                $table->addRow();
                $table->addCell(2000,['vMerge' => 'restart','valign' => 'center'])->addText('號碼姓名',['size' => 14],['alignment' => 'center']);   
                $table->addCell(2100,['valign' => 'center','gridSpan' => 3])->addText('前三次成績',['size' => 14],['alignment' => 'center']);   
                $cell = $table->addCell(1500,['vMerge' => 'restart','valign' => 'center']);
                $cell->addText('前三次',['size' => 14],['alignment' => 'center']);   
                $cell->addText('最佳成績',['size' => 14],['alignment' => 'center']);   
                $table->addCell(2100,['valign' => 'center','gridSpan' => 3])->addText('後三次成績',['size' => 14],['alignment' => 'center']);                   
                $table->addCell(1500,['vMerge' => 'restart','valign' => 'center'])->addText('最佳成績',['size' => 14],['alignment' => 'center']);   
                $table->addCell(1200,['vMerge' => 'restart','valign' => 'center'])->addText('名次',['size' => 14],['alignment' => 'center']);   
                $table->addRow();
                $table->addCell(2000,['vMerge' => 'continue']);
                $table->addCell(700)->addText('一',['size' => 14],['alignment' => 'center']);   
                $table->addCell(700)->addText('二',['size' => 14],['alignment' => 'center']);   
                $table->addCell(700)->addText('三',['size' => 14],['alignment' => 'center']);   
                $table->addCell(1500,['vMerge' => 'continue']);
                $table->addCell(700)->addText('一',['size' => 14],['alignment' => 'center']);   
                $table->addCell(700)->addText('二',['size' => 14],['alignment' => 'center']);   
                $table->addCell(700)->addText('三',['size' => 14],['alignment' => 'center']);   
                $table->addCell(1500,['vMerge' => 'continue']);
                $table->addCell(1200,['vMerge' => 'continue']);
                foreach($student_array as $k=>$v){  
                    foreach($v as $k1=>$v1){
                        $table->addRow();
                        $table->addCell(2000)->addText($v1['number'].' '.$v1['name'],['size' => 14],['alignment' => 'center']);                         
                        for($i=0;$i<3;$i++){
                            $table->addCell(700);
                        }
                        $table->addCell(1500);
                        for($i=0;$i<3;$i++){
                            $table->addCell(700);
                        }
                        $table->addCell(1500);                        
                        $table->addCell(1200);
                    }
                }
            }
            $section->addTextBreak(4);    
            $section->addText('裁判員：_______________________      記錄員：_______________________',['size' => 14]);
        }

        // 定義文件名稱
        $fileName = $item->name.'檢錄表.docx';
        
        // 將文件存為臨時檔案
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);

        // 寫入文件
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        // 回傳文件下載回應
        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
                 

    }

}
