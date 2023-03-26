<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $primaryKey = 'path';
    public $incrementing = false;

    protected $fillable = [
        'path',
        'fie_type',
        'file_size',
        'file_name',
        'creator',
        'look_groups',
        'look_users',
        'edit_groups',
        'edit_users',
        'move_groups',
        'move_users',
    ];

    protected $casts = [
        'look_groups' => 'array',
        'look_users' => 'array',
        'move_groups' => 'array',
        'move_users' => 'array',
        'edit_groups' => 'array',
        'edit_users' => 'array',
        'file_extensions' => 'array',
    ];
}
