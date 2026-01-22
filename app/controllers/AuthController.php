<?php
class AuthController
{
    private $f3;
    private $userModel;

    public function __construct()
    {
        $this->f3 = Base::instance();
        $this->userModel = new User();
    }

    public function loginPage()
    {
        // Redirect if already logged in
        if ($this->isLoggedIn()) {
            $this->redirectBasedOnRole();
            return;
        }

        // Check remember me cookie
        if (isset($_COOKIE['remember_token'])) {
            $user = $this->userModel->findByToken($_COOKIE['remember_token']);
            if ($user) {
                $this->setSession($user);
                $this->redirectBasedOnRole();
                return;
            }
        }

        // Flash message handling
        $error = $this->f3->get('SESSION.error');
        $this->f3->clear('SESSION.error');

        $this->f3->set('title', 'Login');
        $this->f3->set('error', $error);
        echo \Template::instance()->render('auth/login.html');
    }

    public function loginProcess()
    {
        $username = $this->f3->get('POST.username');
        $password = $this->f3->get('POST.password');
        $remember = $this->f3->get('POST.remember') === 'on';

        // Find user by username or email
        $user = $this->userModel->findByUsername($username);
        if (!$user) {
            $user = $this->userModel->findByEmail($username);
        }

        if (!$user || !User::verifyPassword($password, $user->password_hash)) {
            $this->f3->set('SESSION.error', 'Username/email atau password salah');
            $this->f3->reroute('/login');
            return;
        }

        // Set session
        $this->setSession($user);

        // Handle remember me
        if ($remember) {
            $token = User::generateRememberToken();
            $user->updateRememberToken($token);

            // Set cookie for 30 days
            setcookie('remember_token', $token, [
                'expires' => time() + (30 * 24 * 60 * 60),
                'path' => '/',
                'secure' => false, // set true for HTTPS
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }

        $this->redirectBasedOnRole();
    }

    public function logout()
    {
        // Clear remember token from database
        if (isset($_COOKIE['remember_token'])) {
            $user = $this->userModel->findByToken($_COOKIE['remember_token']);
            if ($user) {
                $user->clearRememberToken();
            }
            // Clear cookie
            setcookie('remember_token', '', [
                'expires' => time() - 3600,
                'path' => '/'
            ]);
        }

        // Destroy session
        session_destroy();
        $this->f3->clear('SESSION');

        $this->f3->reroute('/login');
    }

    private function setSession($user)
    {
        $this->f3->set('SESSION.user_id', $user->id);
        $this->f3->set('SESSION.username', $user->username);
        $this->f3->set('SESSION.email', $user->email);
        $this->f3->set('SESSION.role', $user->role);
        $this->f3->set('SESSION.logged_in', true);
    }

    private function isLoggedIn()
    {
        return $this->f3->get('SESSION.logged_in') === true;
    }

    private function redirectBasedOnRole()
    {
        $role = $this->f3->get('SESSION.role');

        if ($role === 'admin') {
            $this->f3->reroute('/admin/dashboard');
        } else {
            $this->f3->reroute('/user/dashboard');
        }
    }

    // Middleware untuk check login
    public static function requireLogin()
    {
        $f3 = Base::instance();

        if (!$f3->get('SESSION.logged_in')) {
            $f3->reroute('/login');
        }
    }

    // Middleware untuk check role
    public static function requireRole($role)
    {
        self::requireLogin();

        $f3 = Base::instance();
        $userRole = $f3->get('SESSION.role');

        if ($userRole !== $role) {
            if ($userRole === 'admin') {
                $f3->reroute('/admin/dashboard');
            } else {
                $f3->reroute('/user/dashboard');
            }
        }
    }
}