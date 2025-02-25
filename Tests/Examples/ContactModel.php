<?php

namespace DiePHP\Tests\Examples;

use Illuminate\Database\Eloquent\Model;

class ContactModel extends Model
{

    protected $table = 'contacts';
    protected $fillable = ['name', 'email', 'phone'];
}
