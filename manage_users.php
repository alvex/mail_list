<?php
session_start();
include 'config.php';

// Check if user is logged in
// OBS: Admin-sida för hantering av inloggningsanvändare; session-timeout ska gälla.
$timeout = 30 * 60; // 30 minutes

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$_SESSION['last_activity'] = time();

$message = '';
$error = '';

// Hantera Lägg till användare
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        // Affärsregel: Användarnamn måste vara unikt.
        $stmt = $conn->prepare("SELECT id FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Username already exists.";
        } else {
            $stmt->close();
            // OBS: Spara endast lösenordshash (aldrig plaintext-lösenord).
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hashed_password);

            if ($stmt->execute()) {
                $message = "User created successfully.";
            } else {
                $error = "Error creating user: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// Hantera Uppdatera användare
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_user'])) {
    $id = $_POST['user_id'];
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username)) {
        $error = "Username is required.";
    } else {
        // Affärsregel: Användarnamn måste vara unikt bland alla användare.
        $stmt = $conn->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "Username already exists.";
        } else {
            $stmt->close();
            if (!empty($password)) {
                // OBS: Uppdatera bara lösenord om ett nytt lösenord angavs.
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("UPDATE admin_users SET username = ?, password_hash = ? WHERE id = ?");
                $stmt->bind_param("ssi", $username, $hashed_password, $id);
            } else {
                $stmt = $conn->prepare("UPDATE admin_users SET username = ? WHERE id = ?");
                $stmt->bind_param("si", $username, $id);
            }

            if ($stmt->execute()) {
                $message = "User updated successfully.";
                // OBS: Rensa edit-läge via redirect för att undvika resubmission vid refresh.
                header("Location: manage_users.php");
                exit();
            } else {
                $error = "Error updating user: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// Hantera Radera användare
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $user_id_to_delete = $_POST['user_id'];

    // Affärsregel: Förhindra att du raderar ditt eget konto medan du är inloggad.
    if ($user_id_to_delete == $_SESSION['user_id']) {
        $error = "You cannot delete your own account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM admin_users WHERE id = ?");
        $stmt->bind_param("i", $user_id_to_delete);

        if ($stmt->execute()) {
            $message = "User deleted successfully.";
        } else {
            $error = "Error deleting user: " . $stmt->error;
        }
    }
}

// Fetch all users
$result = $conn->query("SELECT id, username, created_at FROM admin_users ORDER BY created_at DESC");

// Fetch user for editing
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result_edit = $stmt->get_result();
    if ($result_edit->num_rows > 0) {
        $edit_user = $result_edit->fetch_assoc();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .user-form { background: #f9f9f9; padding: 20px; border-radius: 5px; margin-bottom: 30px; border: 1px solid #ddd; }
        .user-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .user-table th, .user-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .user-table th { background-color: #f2f2f2; }
        .btn-delete { background-color: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; }
        .btn-delete:hover { background-color: #c82333; }
        .btn-edit { background-color: #007bff; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; text-decoration: none; margin-right: 5px; font-size: 13.33px; display: inline-block;}
        .btn-edit:hover { background-color: #0056b3; }
        .alert { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
        .header-nav { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <?php $activePage = 'manage_users'; require_once 'top_menu.php'; ?>
        <div class="header-nav">
            <h1>Manage Users</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="user-form">
            <h3><?php echo $edit_user ? 'Edit User' : 'Add New User'; ?></h3>
            <form method="POST" action="">
                <?php if ($edit_user): ?>
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="add_user" value="1">
                <?php endif; ?>
                
                <div style="margin-bottom: 15px;">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" value="<?php echo $edit_user ? htmlspecialchars($edit_user['username']) : ''; ?>" required style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label for="password">Password: <?php echo $edit_user ? '<small>(Leave blank to keep current)</small>' : ''; ?></label>
                    <input type="password" id="password" name="password" <?php echo $edit_user ? '' : 'required'; ?> style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
                
                <button type="submit" style="background-color: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                    <?php echo $edit_user ? 'Update User' : 'Add User'; ?>
                </button>
                <?php if ($edit_user): ?>
                    <a href="manage_users.php" style="margin-left: 10px; color: #6c757d; text-decoration: none;">Cancel</a>
                <?php endif; ?>
            </form>
        </div>

        <h3>Existing Users</h3>
        <table class="user-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Created At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo $row['created_at']; ?></td>
                    <td>
                        <a href="manage_users.php?edit=<?php echo $row['id']; ?>" class="btn-edit"><i class="fa fa-pencil"></i> Edit</a>
                        <?php if ($row['id'] != $_SESSION['user_id']): ?>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');" style="display: inline;">
                            <input type="hidden" name="delete_user" value="1">
                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="btn-delete"><i class="fa fa-trash"></i> Delete</button>
                        </form>
                        <?php else: ?>
                            <span style="color: #6c757d; font-style: italic; margin-left: 5px;">Current User</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
<?php $conn->close(); ?>