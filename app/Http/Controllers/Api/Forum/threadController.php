<?php

namespace App\Http\Controllers\Api\Forum;

use File;
use Auth;
use App\Models\Thread;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\Galery;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Repositories\threadRepository;
use App\Repositories\Traits\forumTrait;
use App\Http\Resources\threadResource;
use App\Http\Resources\notificationResource;
use App\Http\Requests\threadStoreRequest;
use App\Http\Requests\notificationStoreRequest;


class threadController extends Controller
{
    use forumTrait;

    protected $thread;

    public function __construct(threadRepository $thread)
    {
        $this->thread = $thread;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data = $this->thread->getAllThread(10);
        $response = threadResource::collection($data);
        return $this->collectionHttpResponse($response,$data);
    }

    public function search(Request $request)
    {
        if($request->has('key'))
        {
            $key = $request->key;
            $data = $this->thread->searchThread($key);
            $response = threadResource::collection($data);
            return $this->collectionHttpResponse($response,$data);
        }
    }

    
    public function popular()
    {
        $data = Thread::orderByUniqueViews()->paginate(10);
        $response = threadResource::collection($data);
        return $this->collectionHttpResponse($response,$data);
    }

    public function notification()
    {
        $data = Auth::user()->unreadNotifications()->get()->where('data.type','notification')->where('read_at',null)->sortBYDesc('created_at');
        $response = notificationResource::collection($data);

        // Auth::user()->unreadNotifications->markAsRead();
        return $this->noPaging($response,$data);
        
    }

    public function countNotification()
    {
        $data = Auth::user()->unreadNotifications()->get()->where('data.type','notification')->where('read_at',null)->count();
        return dtcApiResponse(200, $data);
    }

    public function readNotification(Request $request)
    {
        $data = Auth::user()->unreadNotifications()->get()->where('data.path',$request->slug)->first();
        if(isset($data))
        {
            $data->markAsRead();
            return dtcApiResponse(200,'Success','Notifikasi Sudah Di Baca');            
        } else {
            return dtcApiResponse(404, null,'Notifikasi Tidak Di temukan');        
        }

        return $this->success();
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
    public function store(threadStoreRequest $request)
    {
        $thread = new Thread;
        $thread->subject = $request->subject;
        $thread->slug = $this->makeSlugFromTitle($request->subject);
        $thread->description = $request->description;
        $thread->category_id = $request->category_id;
        $thread->created_by = Auth::user()->id;
        $thread->save();

        if($request->hasFile('images'))
        {
            foreach ($request->images as $image) {
                $galery = new Galery;
                $uploadFile = $image;
                $filename = time()."images".".".$uploadFile->getClientOriginalExtension();
                $destination = 'upload/images';
                $uploadFile->move(public_path($destination),$filename);
                $galery->file = $filename;
                $galery->user_id = Auth::user()->id;
                $galery->thread_id = $thread->id;
                $galery->type = "image";
                $galery->save();
            }
        }

        if($request->hasFile('videos'))
        {
            foreach ($request->videos as $video) {
                $galery = new Galery;
                $uploadFile = $video;
                $filename = time()."video".".".$uploadFile->getClientOriginalExtension();
                $destination = 'upload/videos';
                $uploadFile->move(public_path($destination),$filename);
                $galery->file = $filename;
                $galery->user_id = Auth::user()->id;
                $galery->thread_id = $thread->id;
                $galery->type = "video";
                $galery->save();
            }
        }

        if(isset($thread->id))
        {
            return $this->success();
        } else {
            return $this->serverError();
        }
    }
    
    // Make Unique Slug
    public function makeSlugFromTitle($title)
    {
        $slug = Str::slug($title);
        $count = Thread::whereRaw("slug RLIKE '^{$slug}(-[0-9]+)?$'")->count();
        return $count ? "{$slug}-{$count}" : $slug;
    }



    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($slug)
    {
           
        $data = Thread::where('slug',$slug)->first();
        if(isset($data))
        {
            views($data)->record();
            $response = new threadResource($data);
            return $this->singleHttpResponse($data,$response);
        } else {
            return $this->notFound();
        }
         
    }

    public function relatedThread($id)

    {
            $thread = Thread::find($id); 
            if(isset($thread))
            {
                $data = Thread::where('category_id',$thread->category_id)
                                ->where('id','!=',$thread->id)->take(5)->get();
                $response = threadResource::collection($data);
                return $this->noPaging($response,$data);
            } else {
                return $this->notFound();
            }  
    }

    // thread like function
    public function like($id)
    {
        // find the thread
        $data = $this->thread->findThread($id);

        /*
        * checking if thread exist if not return 404
        * if it exist give like to thread based by current user 
        */ 
        if(isset($data))
        {
            $data->like();
            return $this->success();
        } else {
            return $this->notFound();
        }

    }

     // thread dislike function
     public function dislike($id)
     {
         // find the thread
         $data = $this->thread->findThread($id);
 
         /*
         * checking if thread exist if not return 404
         * if it exist give dislike to thread based by current user 
         */ 
         if(isset($data))
         {
             $data->dislike();
             return $this->success();
         } else {
             return $this->notFound();
         }
 
     }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        $thread = $this->thread->findThread($id);
        $role = Auth::user()->roles->where('name','admin')->first();

        if(isset($thread))
        {
            if(Auth::user()->id == $thread->created_by || isset($role))
            {
                $thread->subject = $request->subject;
                $thread->slug = str_slug($thread->subject);
                $thread->description = $request->description;
                $thread->category_id = $request->category_id;
                $thread->created_by = Auth::user()->id;
                $thread->save();

                if($request->hasFile('image'))
                {
                    $galery = Galery::where('thread_id',$id)->first();                    
                    $uploadFile = $request->image;
                    $filename = time()."images".".".$uploadFile->getClientOriginalExtension();
                    $destination = 'upload/images';
                    $uploadFile->move(public_path($destination),$filename);
                    $galery->file = $filename;
                    $galery->user_id = Auth::user()->id;
                    $galery->thread_id = $thread->id;
                    $galery->type = "image";
                    $galery->save();
                }

                if($request->hasFile('video'))
                {
                    $galery = Galery::where('thread_id',$id)->first();
                    $uploadFile = $request->video;
                    $filename = time()."video".".".$uploadFile->getClientOriginalExtension();
                    $destination = 'upload/videos';
                    $uploadFile->move(public_path($destination),$filename);
                    $galery->file = $filename;
                    $galery->user_id = Auth::user()->id;
                    $galery->thread_id = $thread->id;
                    $galery->type = "video";
                    $galery->save();
                }

                if(isset($thread->id))
                {
                    return $this->success();
                } else {
                    return $this->serverError();
                }   
            } else {
                return $this->unprocessable();
            }
        } else {
            return $this->notFound();
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $thread = $this->thread->findThread($id);

        $role = Auth::user()->roles->where('name','admin')->first();
        if(isset($thread))
        {
            if(Auth::user()->id == $thread->created_by || isset($role))
            {
                $gallery = Galery::where('thread_id',$id)->first();
                if(isset($gallery)) {
                    $imagePath = public_path('upload/images/'.$gallery->file);
                    if(File::exists($imagePath)){
                        File::delete($imagePath);
                    }
                    $videoPath = public_path('upload/videos/'.$gallery->file);
                    if(File::exists($videoPath)){
                        File::delete($videoPath);
                    }
                    $gallery->delete();
                }
                $thread->delete();
                return $this->success();
            } else {
                return $this->unprocessable();
            }
        } else {
            return $this->notFound();
        }
    }

    public function fixDataThread()
    {
        $datas = Thread::orderBy('id','desc')->get();
        foreach ($datas as $data) {
            $user = User::where('id',$data->created_by)->first();
            if(!isset($user))
            {
                $data->delete();
            }
        }

        $comments = Comment::orderBy('id','desc')->get();
        foreach ($comments as $comment) {
            $user = User::where('id',$comment->user_id)->first();
            if(!isset($user))
            {
                $data->delete();
            }
        }
    }
}
