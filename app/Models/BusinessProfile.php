<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessProfile extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessProfileFactory> */
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'workspace_id',
        'business_name',
        'logo',
        'email',
        'phone',
        'address',
        'tax_id',
        'city',
        'state',
        'postal_code',
        'country',
        'business_description',
    ];
}
