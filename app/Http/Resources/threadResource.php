<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Auth;
class threadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // if(Auth::user()->id == $this->created_by)
        // {
        //     $edit_links = route('thread_edit',$this->id);
        // } else {
        //     $edit_links = null;
        // }

        if ($this->liked)
        {
            $likeStatus = True;
        } else {
            $likeStatus = False;
        }

        

        return [
            'id' => $this->id,
            'owner' => $this->user->nama_lengkap,
            'email' => $this->user->email,
            'avatar' => userAvatar($this->user->avatar),
            'jabatan' => $this->user->jenisAkun,
            'category' => $this->subCategory->name,
            'idCategory' => $this->subCategory->id,
            'title'=> $this->subject,
            'slug'=> $this->slug,
            'desc' => $this->description,
            'images' => galeryResource::collection($this->galery->where('type','image')),
            'videos' => galeryResource::collection($this->galery->where('type','video')),
            // 'edit_link' => $edit_links,
            'created_at' => $this->created_at->format('d,M-Y'),
            'comments' => commentResource::collection($this->comments),
            'commentsCount' => $this->comments->count(),
            'likesCount' => $this->likesCount,
            'likes' => $this->collectLikers(),
            'likeStatus' => $likeStatus,
        ]; 
    }
}
