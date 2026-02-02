<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
/**
 * AuthController
 *
 * Hanterar inloggning och utloggning i MVC-lagret.
 */
class AuthController extends Controller
{
    /**
     * Visa login-formulär och hantera inloggningsförsök.
     *
     * GET  /login  → visa formulär
     * POST /login  → försök logga in användaren
     *
     * @return void
     */
    public function login()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (isset($_SESSION['user_id'])) {
            header("Location: " . BASE_PATH . "/");
            exit();
        }

        $error = '';
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $userModel = new User();
            $user = $userModel->findByUsername($username);

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $username;
                $_SESSION['last_activity'] = time();
                header("Location: " . BASE_PATH . "/");
                exit();
            } else {
                $error = "Invalid password or user not found.";
            }
        }
        $this->view('auth/login', ['error' => $error]);
    }

    /**
     * Logga ut aktuell användare och rensa session.
     *
     * GET /logout
     *
     * @return void
     */
    public function logout()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_unset();
        session_destroy();
        header("Location: " . BASE_PATH . "/login");
        exit();
    }
}
