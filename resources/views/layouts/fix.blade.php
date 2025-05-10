<?php
$fix_classes = \App\FixClass::where('disable',null)->orderBy('order_by')->get();
$n=1;
?>
<ul class="nav nav-tabs" id="fix-Tab" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="fix-home-tab" data-toggle="tab" data-target="#fix-home" type="button" role="tab" aria-controls="fix-home" aria-selected="true">全部</button>
  </li>
  @foreach($fix_classes as $fix_class)
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="fix{{ $n }}-tab" data-toggle="tab" data-target="#fix{{ $n }}" type="button" role="tab" aria-controls="fix{{ $n }}" aria-selected="false">{{ $fix_class->name }}</button>
    </li>  
  <?php $n++; ?>
@endforeach
</ul>
<?php $n=1; ?>
<div class="tab-content" id="myTabContent">
  <?php
  $fixes = \App\Fix::where('situation','!=',1)->orderBy('situation','DESC')->orderBy('created_at','DESC')->paginate(10);
  ?>
<div class="tab-pane fade show active" id="fix-home" role="tabpanel" aria-labelledby="fix-home-tab">
  <ul>
    @foreach($fixes as $fix)
      <?php               
        if($fix->situation==3) $color = "danger";
        if($fix->situation==2) $color = "warning";
        if($fix->situation==1) $color = "success";
      ?>
      <li>
        {{ substr($fix->created_at,0,10) }} <span class="badge badge-{{ $color }}">{{ substr_cut_name($fix->user->name) }}</span> <a href="javascript:open_window('{{ route('fixes.show_clean',$fix->id) }}','新視窗')">{{ $fix->title }}</a>
      </li>
    @endforeach
  </ul>
  <a href="{{ route('fixes.index') }}"><span class="badge badge-secondary">更多報修</span></a>
</div>
  @foreach($fix_classes as $fix_class)
  <?php
  $fixes = \App\Fix::where('situation','!=',1)->where('type',$fix_class->id)->orderBy('situation','DESC')->orderBy('created_at','DESC')->paginate(10);
  ?>    
      <div class="tab-pane fade" id="fix{{ $n }}" role="tabpanel" aria-labelledby="fix{{ $n }}-tab">
        <ul>
          @foreach($fixes as $fix)
            <?php               
              if($fix->situation==3) $color = "danger";
              if($fix->situation==2) $color = "warning";
              if($fix->situation==1) $color = "success";
            ?>
            <li>
              {{ substr($fix->created_at,0,10) }} <span class="badge badge-{{ $color }}">{{ substr_cut_name($fix->user->name) }}</span> <a href="javascript:open_window('{{ route('fixes.show_clean',$fix->id) }}','新視窗')">{{ $fix->title }}</a>
            </li>
          @endforeach
        </ul>
        <a href="{{ route('fixes.index') }}"><span class="badge badge-secondary">更多報修</span></a>
      </div>    
    <?php $n++; ?>
  @endforeach
</div>