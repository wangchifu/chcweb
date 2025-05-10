@extends('layouts.master')

@section('nav_open_files_active', 'active')
<?php $openfile_name = (empty($setup->openfile_name))?"檔案庫":$setup->openfile_name; ?>
@section('title', $openfile_name.' | ')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-11">
            <h1>
                @if(empty($setup->openfile_name))
                    檔案庫
                @else
                    {{ $setup->openfile_name }}
                @endif
            </h1>
            <?php
            $final = end($folder_path);
            $final_key = key($folder_path);
            $p="";
            $f="app/public/".$school_code."/open_files";
            $last_folder = "";
            ?>
            路徑：
            @auth
                <?php
                    $check_exec = \App\UserGroup::where('user_id',auth()->user()->id)
                    ->where('group_id',1)
                    ->first();                                                          
                ?>
            @endauth            
            @foreach($folder_path as $k=>$v)
                <?php
                if($k=="0"){
                    $k = null;

                }else{
                    $p .= '&'.$k;
                    $f .=  '/'.$v;
                }
                if($k != $final_key and !empty($k)){
                    $last_folder .= '&'.$k;
                }

                ?>
                @if($v == $final)
                    <i class="fa fa-folder-open text-warning"></i> <a href="{{ route('open_files.index',$p) }}">{{$v}}</a>/
                @else
                    <i class="fa fa-folder text-warning"></i> <a href="{{ route('open_files.index',$p) }}">{{$v}}</a>/
                @endif
            @endforeach
            <table class="table table-striped">
                <thead class="thead-light">
                <tr>
                    <th>目錄 / 檔案名稱</th>
                    <th>類型</th>
                    <th>數量</th>
                    <th>建立者</th>
                    <th>建立時間</th>
                </tr>
                </thead>
                <tbody>
                @if($path!=null)
                    <tr>
                        <td><i class="fas fa-arrow-circle-left"></i> <a href="{{ route('open_files.index',$last_folder) }}">上一層</a></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                @endif
                @foreach($folders as $folder)
                    <?php
                    $folder_p = $path.'&'.$folder->id;
                    ?>
                    <tr>
                        <td>
                            <i class="fas fa-folder text-warning"></i> <a href="{{ route('open_files.index',$folder_p) }}">{{ $folder->name }}</a></td>
                        </td>
                        <td>
                            <?php 
                                $n = \App\Upload::where('folder_id',$folder->id)->count();                                                                
                            ?>
                            <strong>目錄</strong>
                            @auth                            
                            @if(($folder->user_id == auth()->user()->id and !empty($check_exec)) or auth()->user()->admin==1)
                                    <a href="javascript:open_window('{{ route('open_files.edit',[$folder->id,$folder_p]) }}','新視窗')" title="編輯"><i class='fas fa-edit'></i></a>
                                @if($n == 0)
                                    <a href="{{ route('open_files.delete',$folder_p) }}" id="delete_folder{{ $folder->id }}" onclick="return confirm('確定刪除目錄嗎？')" title="刪除"><i class="fas fa-minus-square text-danger"></i></a>
                                @endif
                            @endif
                            @endauth
                        </td>
                        <td>
                            {{ $n }} 個項目
                        </td>
                        <td>
                            @if(!empty($folder->job_title))
                                {{ $folder->job_title }}
                            @else
                                @if($folder->user->name == "系統管理員")
                                    系統管理員
                                @else
                                    {{ $folder->user->title }}
                                @endif
                            @endif                            
                        </td>
                        <td>
                            @if(file_exists(storage_path($f.'/'.$folder->name)))
                                {{ date ("Y-m-d H:i:s",filemtime(storage_path($f.'/'.$folder->name))) }}
                            @endif
                        </td>
                    </tr>
                @endforeach
                @foreach($files as $file)
                    <?php
                    $file_p = $path.'&'.$file->id;
                    ?>
                    <tr>
                        <td>
                            @if(file_exists(storage_path($f.'/'.$file->name)))
                                <?php $f2 = str_replace('app/public','',$f); ?>
                                <i class="fas fa-file text-info"></i> <a href="{{ asset('storage'.$f2.'/'.$file->name) }}" target="_blank">{{ $file->name }}</a>
                                <!--
                                <i class="fas fa-file text-info"></i> <a href="{{ route('open_files.download',$file_p) }}">{{ $file->name }}</a>
                                -->
                            @else
                                <span class="text-danger"><i class="fas fa-file"></i> {{ $file->name }}</span>
                            @endif
                        </td>
                        <td>
                            檔案
                            @auth
                                @if(($file->user_id == auth()->user()->id and !empty($check_exec)) or auth()->user()->admin==1)
                                    <a href="javascript:open_window('{{ route('open_files.edit',[$file->id,$file_p]) }}','新視窗')" title="編輯"><i class='fas fa-edit'></i></a>
                                    <a href="{{ route('open_files.delete',$file_p) }}" id="delete_file{{ $file->id }}" onclick="return confirm('確定刪除？')" title="刪除"><i class="fas fa-minus-square text-danger"></i></a>
                                @endif
                            @endauth
                        </td>
                        <td>
                            @if(file_exists(storage_path($f.'/'.$file->name)))
                                {{ filesizekb(storage_path($f.'/'.$file->name)) }} KB
                            @else
                                <small class="text-danger">已遺失</small>
                            @endif
                        </td>
                        <td>
                            @if(!empty($file->job_title))
                                {{ $file->job_title }}
                            @else
                                @if($file->user->name == "系統管理員")
                                    系統管理員
                                @else
                                    {{ $file->user->title }}
                                @endif
                            @endif                            
                        </td>
                        <td>
                            @if(file_exists(storage_path($f.'/'.$file->name)))
                                {{ date ("Y-m-d H:i:s",filemtime(storage_path($f.'/'.$file->name))) }}
                            @else
                                <small class="text-danger">已遺失</small>
                            @endif
                        </td>
                    </tr>
                @endforeach
                @foreach($clouds as $cloud)
                    <?php
                    $file_p = $path.'&'.$cloud->id;
                    ?>
                    <tr>
                        <td>
                            <span class="text-primary"><i class="fas fa-cloud"></i> <a href="{{ $cloud->url }}" target="_blank">{{ $cloud->name }}</a></span>
                        </td>
                        <td>
                            雲端連結
                            @auth
                                @if(($cloud->user_id == auth()->user()->id and !empty($check_exec)) or auth()->user()->admin==1)
                                    <a href="javascript:open_window('{{ route('open_files.edit',[$cloud->id,$file_p]) }}','新視窗')" title="編輯"><i class='fas fa-edit'></i></a>
                                    <a href="{{ route('open_files.delete',$file_p) }}" id="delete_file{{ $cloud->id }}" onclick="return confirm('確定刪除？')" title="刪除"><i class="fas fa-minus-square text-danger"></i></a>
                                @endif
                            @endauth
                        </td>
                        <td>
                            
                        </td>
                        <td>
                            @if(!empty($cloud->job_title))
                                {{ $cloud->job_title }}
                            @else
                                @if($cloud->user->name == "系統管理員")
                                    系統管理員
                                @else
                                    {{ $cloud->user->title }}
                                @endif
                            @endif                            
                        </td>
                        <td>
                            {{ $cloud->created_at }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <hr>
            @can('create',\App\Upload::class)
                <div class="card my-4">
                    <h3 class="card-header">新增</h3>
                    <div class="card-body">
                        @include('layouts.hd')
                        {{ Form::open(['route' => 'open_files.create_folder', 'method' => 'POST','id'=>'this_form','onsubmit'=>"return submitOnce(this)"]) }}
                        <div class="form-group">
                            <label for="name"><strong>1.子目錄</strong></label>
                            {{ Form::text('name',null,['id'=>'name','class' => 'form-control','placeholder'=>'名稱','required'=>'required']) }}
                        </div>
                        <div class="form-group">
                            <input type="hidden" name="folder_id" value="{{ $folder_id }}">
                            <input type="hidden" name="path" value="{{ $path }}">
                            <button class="btn btn-success btn-sm" id="submit_button" onclick="if(confirm('您確定新增子目錄嗎?')){change_button1();return true;}else return false"><i class="fas fa-plus"></i> 新增子目錄</button>
                        </div>
                        {{ Form::close() }}
                        <hr>
                        @include('layouts.errors')
                        @if($per < 100)
                            {{ Form::open(['route' => 'open_files.upload_file', 'method' => 'POST','id'=>'this_form2','files' => true,'onsubmit'=>"return submitOnce(this)"]) }}
                            <div class="form-group">
                                <label for="file"><strong>2.檔案( 不大於10MB，若為文字檔，請改為[ <a href="https://www.ndc.gov.tw/cp.aspx?n=d6d0a9e658098ca2" target="_blank">ODF格式</a> ] [ <a href="{{ asset('ODF.pdf') }}" target="_blank">詳細公文</a> ] [ <a href="{{ asset('office2016_odt_pdf.png') }}" target="_blank">轉檔教學</a> ] )</strong><small class="text-secondary">csv, txt, zip, jpeg, png, pdf, odt, ods, mp3 檔</small></label>
                                {{ Form::file('files[]', ['class' => 'form-control','multiple'=>'multiple','required'=>'required']) }}
                            </div>
                            <div class="form-group">
                                <input type="hidden" name="folder_id" value="{{ $folder_id }}">
                                <input type="hidden" name="path" value="{{ $path }}">
                                <button class="btn btn-success btn-sm" id="submit_button2" onclick="if(confirm('您確定新增檔案嗎?')){change_button2();return true;}else return false"><i class="fas fa-plus"></i> 新增檔案</button>
                            </div>
                            {{ Form::close() }}
                        @endif
                        {{ Form::open(['route' => 'open_files.upload_cloud', 'method' => 'POST','id'=>'this_form3']) }}
                            <div class="form-group">
                                <label for="file"><strong>3.雲端連結</strong></label>
                                {{ Form::text('name',null,['id'=>'name','class' => 'form-control','placeholder'=>'名稱','required'=>'required']) }}
                                {{ Form::text('url',null,['id'=>'url','class' => 'form-control','placeholder'=>'連結','required'=>'required']) }}
                            </div>
                            <div class="form-group">
                                <input type="hidden" name="folder_id" value="{{ $folder_id }}">
                                <input type="hidden" name="path" value="{{ $path }}">
                                <button class="btn btn-success btn-sm" onclick="if(confirm('您確定新增雲端檔案嗎?')){return true;}else return false"><i class="fas fa-plus"></i> 新增雲端連結</button>
                            </div>
                        {{ Form::close() }}
                    </div>
                </div>
            @endcan
        </div>
    </div>
    <script>
        var validator = $("#this_form").validate();
        var validator2 = $("#this_form2").validate();

        var submitcount=0;
        function submitOnce (form){
            if (submitcount == 0){
                submitcount++;
                return true;
            } else{
                alert('正在操作,請不要重複提交,謝謝!');
                return false;
            }
        }
        function change_button1(){
            $("#submit_button").removeAttr('onclick');
            $("#submit_button").attr('disabled','disabled');
            $("#submit_button").addClass('disabled');
            $("#this_form").submit();
        }
        function change_button2(){
            $("#submit_button2").removeAttr('onclick');
            $("#submit_button2").attr('disabled','disabled');
            $("#submit_button2").addClass('disabled');
            $("#this_form2").submit();
        }
        function open_window(url,name)
        {
            window.open(url,name,'statusbar=no,scrollbars=yes,status=yes,resizable=yes,width=900,height=300');
        }
    </script>
@endsection
