@extends('layouts.master')

@section('nav_school_active', 'active')

@section('title', '運動會報名')

@section('content')
    <?php
    $active['admin'] ="active";
    $active['show'] ="";    
    $active['list'] ="";
    $active['score'] ="";
    $active['teacher'] ="";
    ?>
    <div class="row justify-content-center">
        <div class="col-md-11">
            <h1>運動會報名-報名任務</h1>
            @include('sport_meetings.nav')
            <hr>
            <ul class="nav nav-pills">
                <li class="nav-item">
                    <a class="nav-link active" href="{{ route('sport_meeting.action') }}">1.報名任務</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="{{ route('sport_meeting.admin') }}">2.學生資料</a>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="{{ route('sport_meeting.user') }}">3.教師帳號</a>
                  </li>  
              </ul>            
              <div class="card">
                <div class="card-body">
                  @if($admin)
                    <h4>報名任務</h4>
                    <a class="btn btn-success" href="{{ route('sport_meeting.action_create') }}">新增報名任務</a>
                    @include('layouts.errors')
                    <table class="table table-striped">
                        <thead class="table-primary">
                        <tr>
                            <th>
                                報名啟迄
                            </th>
                            <th>
                                名稱
                            </th>
                            <th>
                                動作
                            </th>
                        </tr>
                        </thead>
                        <tbody>
                          <?php $i=1; ?>
                          @foreach($actions as $action)
                              <tr>
                                  <td>
                                      <span @if($action->disable) style="text-decoration:line-through" @endif>
                                          {{ $action->started_at }}<br>
                                          {{ $action->stopped_at }}
                                      </span>
                                  </td>
                                  <td>
                                      <span @if($action->disable) style="text-decoration:line-through" @endif>
                                          {{ $action->name }}
                                      </span>
                                      <a href="{{ route('sport_meeting.action_show',$action->id) }}" class="btn btn-info btn-sm">報名狀況</a>
                                      <!--
                                      <a href="{{ route('sport_meeting.action_set_number',$action->id) }}" class="btn btn-outline-primary btn-sm" onclick="return confirm('確定？')">編入布牌號碼</a>
                                      <a href="{{ route('sport_meeting.action_set_number_null',$action->id) }}" class="btn btn-outline-danger btn-sm" onclick="return confirm('確定清空？')">學生布牌號碼清空</a>
                                      -->
                                      @if($action->disable)
                                          <span class="text-danger">[已停止報名]</span>
                                      @endif
                                      <br>
                                      <small class="text-secondary">每人徑賽最多報{{ $action->track }}項 田賽最多報{{ $action->field }}項  全部最多報{{ $action->frequency }} 項 號碼布為 {{ $action->numbers }} 位數</small>
                                  </td>
                                  <td>
                                      @if($action->disable)
                                          <button class="btn btn-outline-success btn-sm" data-toggle="modal" data-target="#actionModal" data-whatever="{{ route('sport_meeting.action_enable',$action->id) }}" data-name="{{ $action->name }}" data-act="enable">開放報名</button>
                                      @else
                                          <a class="btn btn-success btn-sm" href="{{ route('sport_meeting.item',$action->id) }}">比賽項目</a>
                                          <a class="btn btn-primary btn-sm" href="{{ route('sport_meeting.action_edit',$action->id) }}">修改</a>
                                          <button class="btn btn-warning btn-sm" data-toggle="modal" data-target="#actionModal" data-whatever="{{ route('sport_meeting.action_delete',$action->id) }}" data-name="{{ $action->name }}" data-act="delete">停止報名</button>
                                      @endif
                                          <button class="btn btn-danger btn-sm" data-toggle="modal" data-target="#actionModal" data-whatever="{{ route('sport_meeting.action_destroy',$action->id) }}" data-name="{{ $action->name }}" data-act="destroy">刪除</button>
                                  </td>
                              </tr>
                              <?php $i++; ?>
                          @endforeach                        
                        </tbody>
                    </table>                                 
                  @endif
                </div>
              </div>        
        </div>        
    </div>
    <div class="modal fade" id="actionModal" tabindex="-1" role="dialog" aria-labelledby="actionModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title" id="actionModalLabel">請確認</h5>
                  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                  </button>
              </div>
              <div class="modal-body">
                  <span id="showText"></span>
              </div>
              <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">按錯了</button>
                  <a href="" id="do" class="btn btn-primary">確定</a>
              </div>
          </div>
      </div>
  </div>

  <script>
      $(function () { $('#actionModal').on('show.bs.modal', function (event) {
          var button = $(event.relatedTarget) // Button that triggered the modal
          var recipient = button.data('whatever')
          var act = button.data('act')
          var name = button.data('name')
          $('#do').attr("href", recipient);
          if(act == "delete"){
              $('#showText').text('停止報名 ['+name+'] ？');
          }
          if(act == "enable"){
              $('#showText').text('開放報名 ['+name+'] ？');
          }
          if(act == "destroy"){
              $('#showText').text('完全刪除 ['+name+'] 的相關資料(項目、報名)？');
          }

      })
      });

  </script>
@endsection
