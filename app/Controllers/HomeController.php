<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;

class HomeController extends Controller
{
    public function index()
    {
        AuthMiddleware::check();
        $this->view('home/index');
    }
}
