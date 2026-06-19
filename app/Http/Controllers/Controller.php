<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0", 
    title: "Grades API", 
    description: "API Documentation for Student Grade and Curriculum Management"
)]
#[OA\Server(url: "http://localhost:8003", description: "Local API Server")]
abstract class Controller
{
    //
}