<?php
namespace App\Models;

use App\Core\Model;

class User extends Model
{
    public function findByUsername($username)
    {
        $stmt = $this->db->prepare("SELECT id, password_hash FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }

    public function findById($id)
    {
        $stmt = $this->db->prepare("SELECT id, username, created_at FROM admin_users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    }

    public function checkUsernameExists($username, $excludeId = null)
    {
        if ($excludeId) {
            $stmt = $this->db->prepare("SELECT id FROM admin_users WHERE username = ? AND id != ?");
            $stmt->bind_param("si", $username, $excludeId);
        } else {
            $stmt = $this->db->prepare("SELECT id FROM admin_users WHERE username = ?");
            $stmt->bind_param("s", $username);
        }
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function create($username, $hashed_password)
    {
        $stmt = $this->db->prepare("INSERT INTO admin_users (username, password_hash) VALUES (?, ?)");
        $stmt->bind_param("ss", $username, $hashed_password);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function update($id, $username, $hashed_password = null)
    {
        if ($hashed_password) {
            $stmt = $this->db->prepare("UPDATE admin_users SET username = ?, password_hash = ? WHERE id = ?");
            $stmt->bind_param("ssi", $username, $hashed_password, $id);
        } else {
            $stmt = $this->db->prepare("UPDATE admin_users SET username = ? WHERE id = ?");
            $stmt->bind_param("si", $username, $id);
        }
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM admin_users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function getAll()
    {
        $result = $this->db->query("SELECT id, username, created_at FROM admin_users ORDER BY created_at DESC");
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        return $users;
    }
}
