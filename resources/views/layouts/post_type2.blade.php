<style>
    .image-container{
        max-width: 90%;

        margin: 0 5%;
    }

    .image1{
        float: right;
    }

    .image2{
        float: left;
        margin-right: 20px;
    }

</style>
<ul class="nav nav-tabs" id="myTab" role="tablist">
    <?php
    $setup = \App\Setup::first();
    ?>
    @if($setup->all_post)
    <li class="nav-item">
        <a class="nav-link active" id="post_type2_all_post-tab" data-toggle="tab" href="#post_type2_all_post" role="tab" aria-controls="post_type2_all_post" aria-selected="true">全部公告</a>
    </li>
    @endif
    <?php
        $p=1;
    ?>
    @foreach($post_types as $post_type)
    <?php
    $active = ($p==1 and $setup->all_post==null)?"active":null;    
    $aria_selected = ($p==1 and $setup->all_post==null)?"true":"false";      
    ?>
        <li class="nav-item">
            <a class="nav-link {{ $active }}" id="post_type2_profile{{ $p }}-tab" data-toggle="tab" href="#post_type2_profile{{ $p }}" role="tab" aria-controls="post_type2_profile{{ $p }}" aria-selected="{{ $aria_selected }}">{{ $post_type->name }}</a>
        </li>
        <?php $p++; ?>
    @endforeach
</ul>
<div class="tab-content" id="myTabContent2">
    @if($setup->all_post==1)
    <div class="tab-pane fade show active" id="post_type2_all_post" role="tabpanel" aria-labelledby="post_type2_all_post-tab" style="margin: 10px;">
        @auth
            @can('create',\App\Post::class)
                <a href="{{ route('posts.create') }}" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> 新增公告</a>
            @endauth
        @endauth
        <table class="table table-striped" style="word-break: break-all;">
            <tbody>
            @foreach($posts as $post)
                <tr>
                    <td>
                        <span style="font-size: 20px">
                            @if($post->top)
                                <p class="badge badge-danger">置頂</p>
                            @endif
                            @if($post->inbox)
                                <p class="badge badge-warning">常駐</p>
                            @endif
                            <?php
                            if($post->insite==1){
                                if(auth()->check() or check_ip()){
                                    $can_see = 1;
                                }else{
                                    $can_see = 0;
                                }
                            }else{
                                $can_see = 1;
                            };
                            $school_code = school_code();
                            $title = str_limit($post->title,80);
                            //有無附件
                            $files = get_files(storage_path('app/public/'.$school_code.'/posts/'.$post->id.'/files'));
                            $photos = get_files(storage_path('app/public/'.$school_code.'/posts/'.$post->id.'/photos'));
                            ?>
                            @if($can_see)
                                @if($post->insite==1)
                                    <span class="text-danger">[ 內部公告 ]</span>
                                @endif
                                <a href="{{ route('posts.show',$post->id) }}">{{ $title }}</a>
                            @else
                                <span class='text-danger'>[ 內部公告 ]</span>
                                {{ $title }}
                            @endif
                        </span><br>
                        <?php
                        $content = str_limit(strip_tags($post->content),'150');
                        $content = str_replace('&nbsp;','',$content);
                        ?>
                        @if($can_see)
                            @if($post->title_image)
                                <a href="{{ route('posts.show',$post->id) }}">
                                    <img src="{{ asset('storage/'.$school_code.'/posts/'.$post->id.'/title_image.png') }}" class="image2 img-fluid rounded" width="100px" alt="{{ $post->id }}公告的示意圖片">
                                </a>
                            @endif
                        @endif
                        <p class="pp1">
                            @if($can_see)
                                {{ $content }}
                            @else
                                <span>請登入後再查看</span>
                            @endif
                            <br>
                            <small class="text-secondary">
                                <?php
                                    $insite = ($post->insite != null)?$post->insite:0;
                                ?>

                                {{ $post_type_array[$insite] }} / <a href="{{ route('posts.job_title',$post->job_title) }}">{{ $post->job_title }}</a> / {{ $post->created_at }} / 點閱：{{ $post->views }}
                                @if(!empty($photos))
                                    <span class="text-success"><i class="fas fa-image"></i></span>
                                @endif
                                @if(!empty($files))
                                    <span class="text-info"><i class="fas fa-download"></i></span>
                                @endif
                            </small>
                        </p>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <a href="{{ route('posts.index') }}"><small><i class="far fa-hand-point-up"></i> 更多 公告...</small></a>
    </div>
    @endif
    <?php
        $p=1;
    ?>
    @foreach($post_types as $post_type)
    <?php 
        $active = ($p==1 and $setup->all_post==null)?"show active":null;
    ?>
        <div class="tab-pane fade {{ $active }}" id="post_type2_profile{{ $p }}" role="tabpanel" aria-labelledby="post_type2_profile{{ $p }}-tab" style="margin: 10px;">
            <?php
            $p++;
            $insite = ($post_type->id == 0)?null:$post_type->id;
            $posts = \App\Post::where('insite',$insite)
                ->where(function ($query) {
                    $query->where('die_date',null)->orWhere('die_date','>=',date('Y-m-d'));
                })->where('created_at','<',date('Y-m-d H:i:s'))->orderBy('top','DESC')
                ->orderBy('created_at','DESC')
                ->paginate($post_show_number);
            //檢查置頂日期
            foreach($posts as $post){
                if($post->top ==1){
                    if($post->top_date < date('Y-m-d')){
                        $att['top'] = null;
                        $att['top_date'] = null;
                        $post->update($att);
                    }    
                }
            }
            ?>
            @auth
                @can('create',\App\Post::class)
                    <a href="{{ route('posts.create') }}" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> 新增公告</a>
                @endauth
            @endauth
            <table class="table table-striped" style="word-break: break-all;">
                <tbody>
                @foreach($posts as $post)
                    <tr>
                        <td>
                            <span style="font-size: 20px">
                                @if($post->top)
                                    <p class="badge badge-danger">置頂</p>
                                @endif
                                @if($post->inbox)
                                    <p class="badge badge-warning">常駐</p>
                                @endif
                                <?php
                                if($post->insite==1){
                                    if(auth()->check() or check_ip()){
                                        $can_see = 1;
                                    }else{
                                        $can_see = 0;
                                    }
                                }else{
                                    $can_see = 1;
                                };
                                $school_code = school_code();
                                $title = str_limit($post->title,80);
                                //有無附件
                                $files = get_files(storage_path('app/public/'.$school_code.'/posts/'.$post->id.'/files'));
                                $photos = get_files(storage_path('app/public/'.$school_code.'/posts/'.$post->id.'/photos'));
                                ?>
                                @if($can_see)
                                    @if($post->insite==1)
                                        <span class="text-danger">[ 內部公告 ]</span>
                                    @endif
                                    <a href="{{ route('posts.show',$post->id) }}">{{ $title }}</a>
                                @else
                                    <span class='text-danger'>[ 內部公告 ]</span>
                                    {{ $title }}
                                @endif
                            </span><br>
                            <?php
                            $content = str_limit(strip_tags($post->content),'150');
                            $content = str_replace('&nbsp;','',$content);
                            ?>
                            @if($can_see)
                                @if($post->title_image)
                                    <a href="{{ route('posts.show',$post->id) }}">
                                        <img src="{{ asset('storage/'.$school_code.'/posts/'.$post->id.'/title_image.png') }}" class="image2 img-fluid rounded" width="100px" alt="{{ $post->id }}公告的示意圖片">
                                    </a>
                                @endif
                            @endif
                            <p class="pp1">
                                @if($can_see)
                                    {{ $content }}
                                @else
                                    <span>請登入後再查看</span>
                                @endif
                                <br>
                                <small class="text-secondary">
                                    {{ $post_type->name }}/ <a href="{{ route('posts.job_title',$post->job_title) }}">{{ $post->job_title }}</a> / {{ $post->created_at }} / 點閱：{{ $post->views }}
                                    @if(!empty($photos))
                                        <span class="text-success"><i class="fas fa-image"></i></span>
                                    @endif
                                    @if(!empty($files))
                                        <span class="text-info"><i class="fas fa-download"></i></span>
                                    @endif
                                </small>
                            </p>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <a href="{{ route('posts.type',$post_type->id) }}"><small><i class="far fa-hand-point-up"></i> 更多 {{ $post_type->name }}...</small></a>
        </div>
    @endforeach
</div>
