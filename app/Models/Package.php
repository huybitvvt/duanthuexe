<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use \App\Traits\HandlesPostgresDates;
    protected $table = 'packages';
    protected $guarded = [];
    const PACKAGE_GROUP_1 = 1;
    const PACKAGE_GROUP_2 = 2;
    const PACKAGE_GROUP_3 = 3;
}
