<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentController extends Controller
{
 public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:students,email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $student = Student::create([
            'name'  => $request->name,
            'email' => $request->email,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Student created successfully.',
            'student' => $student,
        ], 201);
    }

}
