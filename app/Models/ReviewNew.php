<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewNew extends Model
{
    use HasFactory;
    protected $fillable = [
        "booking_id",
        'description',
        'garage_id',
        'rate',
        'user_id',
        'comment',
        'status',
        'total_review'
        // "question_id",
        // 'tag_id' ,
        // 'star_id',

    ];


    // public function question() {
    //     return $this->hasOne(Question::class,'id','question_id');
    // }
    // public function tag() {
    //     return $this->hasOne(Question::class,'id','tag_id');
    // }
    public function value() {
        return $this->hasMany(ReviewValueNew::class,'review_id','id');
    }
    public function garage() {
        return $this->hasOne(Garage::class,'id','garage_id')->withTrashed();
    }
    public function user() {
        return $this->hasOne(User::class,'id','user_id');
    }

    public function booking() {
        return $this->hasOne(Booking::class,'id','booking_id');
    }
    protected $hidden = [
        'created_at',
        'updated_at',
    ];
}
