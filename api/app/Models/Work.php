<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Work extends Model
{
    protected $fillable = ['title', 'category', 'description', 'image_path', 'sort_order'];

    protected $appends = ['image'];

    public function getImageAttribute(): string
    {
        // シード画像は public/images、アップロード画像は storage/works に保存
        if (str_starts_with($this->image_path, 'images/')) {
            return url($this->image_path);
        }
        return url('storage/' . $this->image_path);
    }
}
