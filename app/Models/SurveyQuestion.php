<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyQuestion extends Model
{
    protected $table = 'survey_questions';
    protected $fillable = ['question'];

    public function user()
    {
        return $this->belongsToMany('App\Models\User', 'survey_results','question_id','user_id');
    }
}