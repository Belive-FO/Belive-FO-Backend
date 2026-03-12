<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @method void authorize(string $ability, mixed $arguments = [])
 */
abstract class Controller
{
    use AuthorizesRequests;
}
