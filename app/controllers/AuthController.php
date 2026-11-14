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
        if (self::attemptRememberLogin()) {
            $this->redirectBasedOnRole();
            return;
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

        // Prevent session fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        // Set session
        self::setSession($user);

        // Handle remember me
        if ($remember) {
            $token = User::createRememberToken($user->id);
            self::setRememberCookie($token);
        } elseif (isset($_COOKIE['remember_token'])) {
            // User explicitly chose not to be remembered: revoke this device token
            User::deleteRememberToken($_COOKIE['remember_token']);
            $this->userModel->clearRememberToken();
            self::clearRememberCookie();
        }

        $this->redirectBasedOnRole();
    }

    public function logout()
    {
        // Revoke only this device's remember token
        if (isset($_COOKIE['remember_token'])) {
            User::deleteRememberToken($_COOKIE['remember_token']);
            self::clearRememberCookie();
        }

        // Destroy session
        session_destroy();
        $this->f3->clear('SESSION');

        $this->f3->reroute('/login');
    }

    private static function setSession($user)
    {
        $f3 = Base::instance();
        $f3->set('SESSION.user_id', $user->id);
        $f3->set('SESSION.username', $user->username);
        $f3->set('SESSION.email', $user->email);
        $f3->set('SESSION.role', $user->role);
        $f3->set('SESSION.logged_in', true);
    }

    private function isLoggedIn()
    {
        return $this->f3->get('SESSION.logged_in') === true;
    }

    /**
     * Validate the remember-me cookie and restore the session silently.
     */
    private static function attemptRememberLogin()
    {
        $f3 = Base::instance();

        if ($f3->get('SESSION.logged_in')) {
            return true;
        }

        if (empty($_COOKIE['remember_token'])) {
            return false;
        }

        $rawToken = $_COOKIE['remember_token'];
        $userModel = new User();
        $user = $userModel->findByRememberToken($rawToken);
        $isLegacy = false;

        // Backward compatibility with the old single-token implementation
        if (!$user) {
            $user = $userModel->findByToken($rawToken);
            $isLegacy = $user !== null;
        }

        if (!$user) {
            self::clearRememberCookie();
            return false;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_regenerate_id(true);

        self::setSession($user);

        if ($isLegacy) {
            $userModel->clearRememberToken();
            $newToken = User::createRememberToken($user->id);
            self::setRememberCookie($newToken);
        } else {
            User::touchRememberToken($rawToken);
            self::setRememberCookie($rawToken);
        }

        return true;
    }

    /**
     * Cookie options shared by set/clear operations.
     */
    private static function rememberCookieOptions($expires)
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443);

        return [
            'expires' => $expires,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ];
    }

    private static function setRememberCookie($token)
    {
        setcookie('remember_token', $token, self::rememberCookieOptions(time() + (30 * 24 * 60 * 60)));
    }

    public static function clearRememberCookie()
    {
        setcookie('remember_token', '', self::rememberCookieOptions(time() - 3600));
    }

    /**
     * Determine whether the current request expects a JSON/API response.
     */
    public static function isApiRequest()
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        if (strpos($path, '/api/') !== false) {
            return true;
        }

        if (strcasecmp($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest') === 0) {
            return true;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            return true;
        }

        return false;
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

        if ($f3->get('SESSION.logged_in')) {
            return;
        }

        // Try to restore the session from the remember-me cookie
        if (self::attemptRememberLogin()) {
            return;
        }

        if (self::isApiRequest()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Your session has expired. Please sign in again.',
                'code' => 'SESSION_EXPIRED'
            ]);
            exit;
        }

        $f3->reroute('/login');
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