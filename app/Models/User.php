<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['username', 'email', 'password_hash'];
    protected $hidden = ['password_hash'];
    
    public function jobs()
    {
        return $this->hasMany(Job::class);
    }
}
