<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class notificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        if($this->data['label'] == "Comment")
        {

            return [
                'id' => $this->id,
                'type' => $this->data['type'],
                'label' => $this->data['label'],
                'title' => $this->data['title'],
                'path' => $this->data['path'],
                'body' => $this->data['commenter']." Memberi Komentar Pada Thread ". $this->data['judulThread'],  
            ];
        }

        if($this->data['label'] == "Reply")
        {
            return [
                'id' => $this->id,
                'type' => $this->data['type'],
                'label' => $this->data['label'],
                'title' => $this->data['title'],
                'path' => $this->data['path'],
                'body' => $this->data['replier']." Memberi Balasan Pada Komentar Anda Pada Thread ".$this->data['judulThread'], 
            ];
        }

        if($this->data['label'] == "pengujian" || $this->data['label'] == "Pengajuan")
        {
            return [
                'id' => $this->id,
                'type' => $this->data['type'],
                'label' => $this->data['label'],
                'title' => $this->data['title'],
                'body' => $this->data['body'], 
            ];
        }
    }
}
