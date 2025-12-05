<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

class SuperadminController extends Controller
{
    public function index()
    {
        $this->requireLogin('superadmin');
        $user = $this->getCurrentUser();

        $this->render('superadmin/dashboard', [
            'user' => $user,
            'userCount' => Auth::userCount(),
            'environment' => $GLOBALS['config']['environment'] ?? 'development',
        ]);
    }
}
