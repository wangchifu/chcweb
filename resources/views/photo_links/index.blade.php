@extends('layouts.master')

@section('nav_setup_active', 'active')

@section('title', '圖片連結 | ')

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-11">
        <h1>圖片連結</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('index') }}">首頁</a></li>
                    <li class="breadcrumb-item active" aria-current="page">圖片連結</li>
                </ol>
            </nav>
        </div>
        <div class="col-md-4">
            <h2>基本設定</h2>
                <table class="table table-striped" style="word-break:break-all;">
                    <thead class="thead-light">
                        <tr>
                            @if(auth()->user()->admin)
                                <th>顯示圖片的數目</th>
                            @endif
                            <th>新建類別</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $setup = \App\Setup::first(); ?>
                        <tr>
                            @if(auth()->user()->admin)
                                <td>                               
                                    <form action="{{ route('setups.photo_link_number') }}" method="post">
                                        @csrf
                                    {{ Form::number('photo_link_number',$setup->photo_link_number,['id'=>'photo_link_number','class' => 'form-control', 'placeholder' => '6的倍數為佳']) }}
                                    <button class="btn btn-primary btn-sm" onclick="return confirm('確定？')">修改</button>    
                                    </form>                                
                                </td>
                            @endif
                            <td>
                                <form action="{{ route('photo_links.type_store') }}" method="post">
                                @csrf
                                排序：
                                {{ Form::number('order_by',$new_order_by,['id'=>'order_by','class' => 'form-control','required'=>'required', 'placeholder' => '排序']) }}
                                名稱：
                                {{ Form::text('name',null,['id'=>'name','class' => 'form-control','required'=>'required', 'placeholder' => '名稱']) }}
                                <button class="btn btn-primary btn-sm" onclick="return confirm('確定？')">新增</button>
                                </form>
                            </td>
                        </tr>
                        </form>
                    </tbody>
                </table>

                <table class="table table-striped" style="word-break:break-all;">
                    <thead class="thead-light">
                    <tr>
                        <th width="100">排序</th>
                        <th nowrap>名稱</th>                        
                        <th nowrap>動作</th>
                    </tr>
                    </thead>
                    <tbody>
                        @foreach($photo_types as $photo_type)
                        {{ Form::open(['route' => ['photo_links.type_update',$photo_type->id], 'method' => 'patch','id'=>'update'.$photo_type->id]) }}
                            @csrf
                            @method('patch')
                        <tr>
                            <?php $readonly=(auth()->user()->admin !=1 and $photo_type->user_id != auth()->user()->id)?"readonly":null; ?>                                                              
                            <td>
                                {{ Form::number('order_by',$photo_type->order_by,['id'=>'order_by','class' => 'form-control', 'placeholder' => '數字','readonly'=>$readonly]) }}
                            </td>
                            <td>
                                {{ Form::text('name',$photo_type->name,['id'=>'name','class' => 'form-control','required'=>'required', 'placeholder' => '名稱','readonly'=>$readonly]) }}
                            </td>                            
                            <td nowrap>
                                @if(auth()->user()->admin or $photo_type->user_id == auth()->user()->id)
                                <button onclick="return confirm('儲存修改？')" class="btn btn-primary btn-sm"><i class="fas fa-save"></i></button>                                
                                <a href="{{ route('photo_links.type_delete',$photo_type->id) }}" onclick="return confirm('確定刪除？底下這個分類的連結，將改為「不分類」喔！')"><i class="fas fa-times-circle text-danger"></i></a>
                                @endif
                            </td>
                        </tr>
                        {{ Form::close() }}
                    @endforeach
                    </tbody>
                </table>
        </div>
        <div class="col-md-7">
            <h2>連結設定</h2>
            <a href="{{ route('photo_links.create',$photo_type_id) }}" class="btn btn-success btn-sm" id="go_create"><i class="fas fa-plus"></i> 新增連結</a><br><br>
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="home-tab" data-toggle="tab" data-target="#home" type="button" role="tab" aria-controls="home" aria-selected="true" onclick="change_create_id()">不分類</button>
                </li>
                <?php $p=1; ?>
                @foreach($photo_types as $photo_type)
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="photo_link{{ $p }}-tab" data-toggle="tab" data-target="#photo_link{{ $p }}" type="button" role="tab" aria-controls="photo_link{{ $p }}" aria-selected="false" onclick="change_create_id({{ $photo_type->id }})">{{  $photo_type->name }}</button>
                    </li>
                <?php $p++; ?>
                @endforeach
              </ul>
              <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                    <table class="table table-striped" style="word-break:break-all;">   
                        <thead class="thead-light">
                            <tr>
                                <th width="120">類別</th>
                                <th width="100">排序</th>
                                <th>代表圖片</th>                                
                                <th>名稱</th>
                                <th>動作</th>
                            </tr>
                            </thead>
                        @if(isset($photo_link_data[0]))
                            @foreach($photo_link_data[0] as $k=>$v)
                                <tr>
                                    <td>
                                        {{ $photo_type_array[0] }}
                                    </td>
                                    <td>
                                        {{ $v['order_by'] }}
                                    </td>
                                    <td>
                                        <?php
                                            $school_code = school_code();
                                            $img = "storage/".$school_code.'/photo_links/'.$v['image'];
                                        ?>
                                        <a href="{{ $v['url'] }}" target="_blank"><img src="{{ asset($img) }}" height="50" alt="連結縮圖"></a>
                                    </td>                                    
                                    <td>
                                        <a href="{{ $v['url'] }}" target="_blank">{{ $v['name'] }}</a>
                                    </td>
                                    <td>
                                        @if(auth()->user()->admin or $v['user_id'] == auth()->user()->id)
                                            <a href="javascript:open_window('{{ route('photo_links.edit',$k) }}','新視窗')" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit"></i> 修改</a>                                        
                                            <a href="#" class="btn btn-danger btn-sm" onclick="if(confirm('確定刪除？不能刪別人建的喔！')) document.getElementById('delete{{ $k}}').submit();else return false;"><i class="fas fa-trash"></i> 刪除</a>                                        
                                        @endif
                                    </td>
                                </tr>
                                {{ Form::open(['route' => ['photo_links.destroy',$k], 'method' => 'DELETE','id'=>'delete'.$k,'onsubmit'=>'return false;']) }}
                                {{ Form::close() }}
                            @endforeach
                        @endif
                        </tbody>
                    </table>
                </div>
                <?php $p=1; ?>
                @foreach($photo_types as $photo_type)
                    <div class="tab-pane fade" id="photo_link{{ $p }}" role="tabpanel" aria-labelledby="photo_link{{ $p }}-tab">
                        <table class="table table-striped" style="word-break:break-all;">   
                            <thead class="thead-light">
                                <tr>
                                    <th width="120">類別</th>
                                    <th width="100">排序</th>
                                    <th>代表圖片</th>                                    
                                    <th>名稱</th>
                                    <th>動作</th>
                                </tr>
                                </thead>
                            <tbody>
                            @if(isset($photo_link_data[$photo_type->id]))
                                @foreach($photo_link_data[$photo_type->id] as $k=>$v)
                                    <tr>
                                        <td>
                                            {{ $photo_type_array[$photo_type->id] }}
                                        </td>
                                        <td>
                                            {{ $v['order_by'] }}
                                        </td>
                                        <td>
                                            <?php
                                                $school_code = school_code();
                                                $img = "storage/".$school_code.'/photo_links/'.$v['image'];
                                            ?>
                                            <a href="{{ $v['url'] }}" target="_blank"><img src="{{ asset($img) }}" height="50" alt="連結縮圖"></a>
                                        </td>                                        
                                        <td>
                                            <a href="{{ $v['url'] }}" target="_blank">{{ $v['name'] }}</a>
                                        </td>
                                        <td>
                                            @if(auth()->user()->admin or $v['user_id'] == auth()->user()->id)
                                                <a href="javascript:open_window('{{ route('photo_links.edit',$k) }}','新視窗')" class="btn btn-outline-primary btn-sm"><i class="fas fa-edit"></i> 修改</a>                                            
                                                <a href="#" class="btn btn-danger btn-sm" onclick="if(confirm('確定刪除？不能刪別人建的喔！')) document.getElementById('delete{{ $k}}').submit();else return false;"><i class="fas fa-trash"></i> 刪除</a>                                            
                                            @endif
                                        </td>
                                    </tr>
                                    {{ Form::open(['route' => ['photo_links.destroy',$k], 'method' => 'DELETE','id'=>'delete'.$k,'onsubmit'=>'return false;']) }}
                                    {{ Form::close() }}
                                @endforeach
                            @endif
                            </tbody>
                        </table>
                    </div>
                <?php $p++; ?>
                @endforeach
                <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">

                </div>
              </div>
        </div>
    </div>
    <script>
        function open_window(url,name)
        {
            window.open(url,name,'statusbar=no,scrollbars=yes,status=yes,resizable=yes,width=900,height=300');
        }

        var validator = $("#this_form1").validate();
        function change_create_id(id){
            $('#go_create').attr('href', '{{ route("photo_links.create") }}'+'/'+id);
        }
    </script>
    @if(!empty($photo_type_id))    
    <?php $p=1; ?>
    @foreach($photo_types as $photo_type)
        @if($photo_type->id == $photo_type_id) 
                <script>                                  
                    $("#home-tab").removeClass("active");
                    $("#home").removeClass("show");
                    $("#home").removeClass("active");
                    $("#photo_link{{ $p }}-tab").addClass("active");
                    $("#photo_link{{ $p }}").addClass("show");
                    $("#photo_link{{ $p }}").addClass("active");
                </script>
        @endif
        <?php $p++; ?>
    @endforeach    
@endif
@endsection
