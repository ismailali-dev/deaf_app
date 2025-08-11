<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentApprovalRequest extends Model
{
    
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'parent_name',
        'parent_dob',
        'phone',
        'email',
        'id_type',
        'id_number',
        'ssn_number',
        'parent_id_doc',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
