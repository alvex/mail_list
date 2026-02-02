<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Models\User;

class UserController extends Controller
{

    public function __construct()
    {
        AuthMiddleware::check();
    }

    public function index()
    {
        $userModel = new User();
        $message = '';
        $error = '';

        // Handle Form Submissions
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (isset($_POST['add_user'])) {
                $username = trim($_POST['username']);
                $password = $_POST['password'];

                if (empty($username) || empty($password)) {
                    $error = "Username and password are required.";
                } elseif ($userModel->checkUsernameExists($username)) {
                    $error = "Username already exists.";
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    if ($userModel->create($username, $hashed)) {
                        $message = "User created successfully.";
                    } else {
                        $error = "Error creating user.";
                    }
                }
            } elseif (isset($_POST['update_user'])) {
                $id = $_POST['user_id'];
                $username = trim($_POST['username']);
                $password = $_POST['password'];

                if (empty($username)) {
                    $error = "Username is required.";
                } elseif ($userModel->checkUsernameExists($username, $id)) {
                    $error = "Username already exists.";
                } else {
                    $hashed = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : null;
                    if ($userModel->update($id, $username, $hashed)) {
                        $message = "User updated successfully.";
                        // Redirect to clear edit state
                        header("Location: /users");
                        exit();
                    } else {
                        $error = "Error updating user.";
                    }
                }
            } elseif (isset($_POST['delete_user'])) {
                $id = $_POST['user_id'];
                if ($id == $_SESSION['user_id']) {
                    $error = "You cannot delete your own account.";
                } else {
                    if ($userModel->delete($id)) {
                        $message = "User deleted successfully.";
                    } else {
                        $error = "Error deleting user.";
                    }
                }
            }
        }

        $edit_user = null;
        if (isset($_GET['edit'])) {
            $edit_user = $userModel->findById($_GET['edit']);
        }

        $users = $userModel->getAll();

        $this->view('users/index', [
            'users' => $users,
            'edit_user' => $edit_user,
            'message' => $message,
            'error' => $error
        ]);
    }
}
