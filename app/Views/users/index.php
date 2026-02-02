<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/public/css/styles.css?v=2">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .user-form {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border: 1px solid #ddd;
        }

        .user-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .user-table th,
        .user-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .user-table th {
            background-color: #f2f2f2;
        }

        .btn-delete {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
        }

        .btn-delete:hover {
            background-color: #c82333;
        }

        .btn-edit {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            margin-right: 5px;
            font-size: 13.33px;
            display: inline-block;
        }

        .btn-edit:hover {
            background-color: #0056b3;
        }

        .alert {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .header-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php $activePage = 'manage_users';
        require __DIR__ . '/../partials/top_menu.php'; ?>
        <div class="header-nav">
            <h1>Manage Users</h1>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="user-form">
            <h3>
                <?php echo $edit_user ? 'Edit User' : 'Add New User'; ?>
            </h3>
            <form method="POST" action="<?php echo BASE_PATH; ?>/users">
                <?php if ($edit_user): ?>
                    <input type="hidden" name="update_user" value="1">
                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                <?php else: ?>
                    <input type="hidden" name="add_user" value="1">
                <?php endif; ?>

                <div style="margin-bottom: 15px;">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username"
                        value="<?php echo $edit_user ? htmlspecialchars($edit_user['username']) : ''; ?>" required
                        style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label for="password">Password:
                        <?php echo $edit_user ? '<small>(Leave blank to keep current)</small>' : ''; ?>
                    </label>
                    <input type="password" id="password" name="password" <?php echo $edit_user ? '' : 'required'; ?>
                        style="width: 100%; padding: 8px; margin-top: 5px;">
                </div>

                <button type="submit"
                    style="background-color: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                    <?php echo $edit_user ? 'Update User' : 'Add User'; ?>
                </button>
                <?php if ($edit_user): ?>
                    <a href="<?php echo BASE_PATH; ?>/users"
                        style="margin-left: 10px; color: #6c757d; text-decoration: none;">Cancel</a>
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
                <?php foreach ($users as $row): ?>
                    <tr>
                        <td>
                            <?php echo $row['id']; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row['username']); ?>
                        </td>
                        <td>
                            <?php echo $row['created_at']; ?>
                        </td>
                        <td>
                            <a href="<?php echo BASE_PATH; ?>/users?edit=<?php echo $row['id']; ?>" class="btn-edit"><i
                                    class="fa fa-pencil"></i>
                                Edit</a>
                            <?php if ($row['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this user?');"
                                    style="display: inline;">
                                    <input type="hidden" name="delete_user" value="1">
                                    <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="btn-delete"><i class="fa fa-trash"></i> Delete</button>
                                </form>
                            <?php else: ?>
                                <span style="color: #6c757d; font-style: italic; margin-left: 5px;">Current User</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>

</html>