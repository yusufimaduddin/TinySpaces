<?php
class AdminController
{
    private $f3;

    public function __construct()
    {
        $this->f3 = Base::instance();
    }

    public function dashboard()
    {
        AuthController::requireRole('admin');

        $userModel = new User();
        $users = $userModel->find();

        // Flash message handling
        $success = $this->f3->get('SESSION.success');
        $error = $this->f3->get('SESSION.error');
        $this->f3->clear('SESSION.success');
        $this->f3->clear('SESSION.error');

        $this->f3->set('title', 'TinySpaces Admin');
        $this->f3->set('username', $this->f3->get('SESSION.username'));
        $this->f3->set('role', $this->f3->get('SESSION.role'));
        $this->f3->set('users', $users);
        $this->f3->set('success', $success);
        $this->f3->set('error', $error);

        echo \Template::instance()->render('admin/dashboard.html');
    }

    public function addUser()
    {
        AuthController::requireRole('admin');

        $username = $this->f3->get('POST.username');
        $email = $this->f3->get('POST.email');
        $password = $this->f3->get('POST.password');

        // Validate input
        if (!$username || !$email || !$password) {
            $this->f3->set('SESSION.error', 'All fields are required');
            $this->f3->reroute('/admin/dashboard');
            return;
        }

        if (strlen($password) < 8) {
            $this->f3->set('SESSION.error', 'Password must be at least 8 characters');
            $this->f3->reroute('/admin/dashboard');
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->f3->set('SESSION.error', 'Invalid email format');
            $this->f3->reroute('/admin/dashboard');
            return;
        }

        $userModel = new User();

        // Check if username or email exists
        if ($userModel->findByUsername($username)) {
            $this->f3->set('SESSION.error', 'Username already exists');
            $this->f3->reroute('/admin/dashboard');
            return;
        }

        if ($userModel->findByEmail($email)) {
            $this->f3->set('SESSION.error', 'Email already exists');
            $this->f3->reroute('/admin/dashboard');
            return;
        }

        try {
            $db = Database::getInstance();

            // Generate unique ID
            $userId = Database::generateId();
            while ($db->exec("SELECT COUNT(*) as count FROM users WHERE id = ?", [$userId])[0]['count'] > 0) {
                $userId = Database::generateId();
            }

            $timestamp = date('Y-m-d H:i:s');

            // Insert user directly using database
            $db->exec(
                "INSERT INTO users (id, username, email, password_hash, role, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$userId, $username, $email, User::hashPassword($password), 'user', $timestamp, $timestamp]
            );

            $this->f3->set('SESSION.success', 'User added successfully');
        } catch (Exception $e) {
            $this->f3->set('SESSION.error', 'Failed to add user: ' . $e->getMessage());
        }

        $this->f3->reroute('/admin/dashboard');
    }
}
