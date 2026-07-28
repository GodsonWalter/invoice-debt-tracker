<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function users()
    {
        //Logic to retrieve and return a list of users
        $users = User::all(); // Assuming you have a User model
        return response()->json(
            [
                'users' => $users
            ]
        );
    }

    public function store(){
       
    }
}
