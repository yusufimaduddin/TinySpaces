<?php
class UserController
{
    private $f3;

    private $userModel;
    private $spaceModel;

    public function __construct()
    {
        $this->f3 = Base::instance();
        $this->userModel = new User();
        $this->spaceModel = new Space();
    }

    public function dashboard()
    {
        AuthController::requireLogin();

        $this->f3->set('title', 'User Dashboard');
        $this->f3->set('description', 'Manage your file spaces and share them easily.');
        $this->f3->set('author', $this->f3->get('SESSION.username'));
        $this->f3->set('username', $this->f3->get('SESSION.username'));
        $this->f3->set('email', $this->f3->get('SESSION.email'));
        $this->f3->set('role', $this->f3->get('SESSION.role'));

        echo \Template::instance()->render('user/dashboard.html');
    }

    public function space()
    {
        AuthController::requireLogin();

        $spaceId = $this->f3->get('PARAMS.id');
        $userId = $this->f3->get('SESSION.user_id');

        // Check access
        $space = $this->spaceModel->checkAccess($spaceId, $userId);
        if (!$space) {
            $this->f3->error(404, 'Space not found or access denied');
            return;
        }

        $this->f3->set('title', $space['name']);
        $this->f3->set('description', $space['description'] ?: 'A shared space on TinySpaces');
        $this->f3->set('author', $space['owner_name']);
        $this->f3->set('username', $this->f3->get('SESSION.username'));
        $this->f3->set('email', $this->f3->get('SESSION.email'));
        $this->f3->set('role', $this->f3->get('SESSION.role'));
        $this->f3->set('space_id', $spaceId);

        echo \Template::instance()->render('user/space.html');
    }

    public function profile()
    {
        AuthController::requireLogin();

        $this->f3->set('title', 'User Profile');
        $this->f3->set('description', 'Manage your profile settings.');
        $this->f3->set('author', $this->f3->get('SESSION.username'));
        $this->f3->set('username', $this->f3->get('SESSION.username'));
        $this->f3->set('email', $this->f3->get('SESSION.email'));
        $this->f3->set('role', $this->f3->get('SESSION.role'));

        echo \Template::instance()->render('user/profile.html');
    }

    public function updateProfile()
    {
        AuthController::requireLogin();
        $data = json_decode($this->f3->get('BODY'), true);

        $userId = $this->f3->get('SESSION.user_id');
        $username = trim($data['username'] ?? '');
        $currentPassword = $data['currentPassword'] ?? '';

        if (empty($username) || empty($currentPassword)) {
            $this->jsonResponse(['success' => false, 'message' => 'All fields are required']);
            return;
        }

        // Get user from database
        $db = Database::getInstance();
        $userResult = $db->exec("SELECT * FROM users WHERE id = ?", [$userId]);

        if (empty($userResult)) {
            $this->jsonResponse(['success' => false, 'message' => 'User not found']);
            return;
        }

        $user = $userResult[0];

        // Verify password
        if (!User::verifyPassword($currentPassword, $user['password_hash'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Incorrect password']);
            return;
        }

        // Check if username already exists (and is not current username)
        if ($username !== $user['username']) {
            $existing = $db->exec("SELECT id FROM users WHERE username = ?", [$username]);
            if (!empty($existing)) {
                $this->jsonResponse(['success' => false, 'message' => 'Username already exists']);
                return;
            }
        }

        // Update user
        $timestamp = date('Y-m-d H:i:s');
        $db->exec(
            "UPDATE users SET username = ?, updated_at = ? WHERE id = ?",
            [$username, $timestamp, $userId]
        );

        // Update session
        $this->f3->set('SESSION.username', $username);

        $this->jsonResponse(['success' => true, 'message' => 'Profile updated successfully']);
    }

    public function updatePassword()
    {
        AuthController::requireLogin();
        $data = json_decode($this->f3->get('BODY'), true);

        $userId = $this->f3->get('SESSION.user_id');
        $currentPassword = $data['currentPassword'] ?? '';
        $newPassword = $data['newPassword'] ?? '';

        if (empty($currentPassword) || empty($newPassword)) {
            $this->jsonResponse(['success' => false, 'message' => 'All fields are required']);
            return;
        }

        if (strlen($newPassword) < 8) {
            $this->jsonResponse(['success' => false, 'message' => 'New password must be at least 8 characters']);
            return;
        }

        // Get user from database
        $db = Database::getInstance();
        $userResult = $db->exec("SELECT * FROM users WHERE id = ?", [$userId]);

        if (empty($userResult)) {
            $this->jsonResponse(['success' => false, 'message' => 'User not found']);
            return;
        }

        $user = $userResult[0];

        if (!User::verifyPassword($currentPassword, $user['password_hash'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Incorrect current password']);
            return;
        }

        // Update password
        $timestamp = date('Y-m-d H:i:s');
        $newPasswordHash = User::hashPassword($newPassword);
        $db->exec(
            "UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?",
            [$newPasswordHash, $timestamp, $userId]
        );

        $this->jsonResponse(['success' => true, 'message' => 'Password changed successfully']);
    }

    public function deleteAccount()
    {
        AuthController::requireLogin();
        $data = json_decode($this->f3->get('BODY'), true);

        $userId = $this->f3->get('SESSION.user_id');
        $password = $data['password'] ?? '';

        if (empty($password)) {
            $this->jsonResponse(['success' => false, 'message' => 'Password is required']);
            return;
        }

        // Get user from database
        $db = Database::getInstance();
        $userResult = $db->exec("SELECT * FROM users WHERE id = ?", [$userId]);

        if (empty($userResult)) {
            $this->jsonResponse(['success' => false, 'message' => 'User not found']);
            return;
        }

        $user = $userResult[0];

        if (!User::verifyPassword($password, $user['password_hash'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Incorrect password']);
            return;
        }

        try {
            // First, get all spaces owned by user to delete their directories
            $spacesResult = $db->exec("SELECT id FROM spaces WHERE owner_id = ?", [$userId]);

            foreach ($spacesResult as $space) {
                $spaceDir = __DIR__ . '/../../public/uploads/spaces/' . $space['id'];
                if (file_exists($spaceDir)) {
                    $this->deleteDirectory($spaceDir);
                }
            }

            // Delete user (CASCADE will handle spaces, files, tags, and access)
            $db->exec("DELETE FROM users WHERE id = ?", [$userId]);

            // Clear remember token cookie
            if (isset($_COOKIE['remember_token'])) {
                setcookie('remember_token', '', [
                    'expires' => time() - 3600,
                    'path' => '/'
                ]);
            }

            // Destroy session
            session_destroy();
            $this->f3->clear('SESSION');

            $this->jsonResponse(['success' => true, 'message' => 'Account deleted successfully']);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Failed to delete account: ' . $e->getMessage()]);
        }
    }

    private function jsonResponse($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }
}
