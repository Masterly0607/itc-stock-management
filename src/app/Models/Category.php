<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'is_active'];
    protected $casts = ['is_active' => 'bool'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
