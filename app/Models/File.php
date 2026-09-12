<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class File extends Model
{
    protected $fillable = [
        'name',
        'key',
        'size',
        'file_hash',
        'provider',
        'provider_id',
        'url',
    ];

	public function vehicles(): BelongsToMany
    {
        return $this->belongsToMany(Vehicle::class, 'vehicle_images', 'file_id', 'vehicle_id');
    }
}
