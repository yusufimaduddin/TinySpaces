<?php
class User extends \DB\SQL\Mapper
{
    public function __construct()
    {
        parent::__construct(Database::getInstance(), 'users');
    }

    public static function hashPassword($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    public static function generateRememberToken()
    {
        return bin2hex(random_bytes(32));
    }

    public function findByUsername($username)
    {
        $this->load(['username = ?', $username]);
        return $this->loaded() ? $this : null;
    }

    public function findByEmail($email)
    {
        $this->load(['email = ?', $email]);
        return $this->loaded() ? $this : null;
    }

    public function findByToken($token)
    {
        $this->load([
            'remember_token = ? AND token_expires_at > ?',
            $token,
            date('Y-m-d H:i:s')
        ]);
        return $this->loaded() ? $this : null;
    }

    public function updateRememberToken($token, $days = 30)
    {
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        $this->remember_token = $token;
        $this->token_expires_at = $expires_at;
        $this->save();
    }

    public function clearRememberToken()
    {
        $this->remember_token = null;
        $this->token_expires_at = null;
        $this->save();
    }

    /**
     * Create a new remember-me token for a single device.
     * Returns the raw token (stored in the cookie); only its hash is persisted.
     */
    public static function createRememberToken($userId, $days = 30)
    {
        $db = Database::getInstance();

        // Housekeeping: drop expired tokens
        $db->exec("DELETE FROM remember_tokens WHERE expires_at <= ?", [date('Y-m-d H:i:s')]);

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
            ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255)
            : null;

        $db->exec(
            "INSERT INTO remember_tokens (id, user_id, token_hash, expires_at, user_agent)
             VALUES (?, ?, ?, ?, ?)",
            [Database::generateId(), $userId, $tokenHash, $expiresAt, $userAgent]
        );

        return $rawToken;
    }

    /**
     * Find a user by a raw remember-me token (valid and not expired).
     */
    public function findByRememberToken($rawToken)
    {
        if (empty($rawToken)) {
            return null;
        }

        $db = Database::getInstance();
        $result = $db->exec(
            "SELECT u.id FROM users u
             JOIN remember_tokens rt ON rt.user_id = u.id
             WHERE rt.token_hash = ? AND rt.expires_at > ?
             LIMIT 1",
            [hash('sha256', $rawToken), date('Y-m-d H:i:s')]
        );

        if (empty($result)) {
            return null;
        }

        $this->load(['id = ?', $result[0]['id']]);
        return $this->loaded() ? $this : null;
    }

    /**
     * Mark a token as used and optionally extend its lifetime (sliding window).
     */
    public static function touchRememberToken($rawToken, $days = 30)
    {
        if (empty($rawToken)) {
            return false;
        }

        $db = Database::getInstance();
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));

        $db->exec(
            "UPDATE remember_tokens SET last_used_at = ?, expires_at = ? WHERE token_hash = ?",
            [$now, $expiresAt, hash('sha256', $rawToken)]
        );

        return true;
    }

    /**
     * Delete a single device token (used on logout).
     */
    public static function deleteRememberToken($rawToken)
    {
        if (empty($rawToken)) {
            return;
        }

        $db = Database::getInstance();
        $db->exec(
            "DELETE FROM remember_tokens WHERE token_hash = ?",
            [hash('sha256', $rawToken)]
        );
    }

    /**
     * Delete every remember-me token for a user (password change, account deletion).
     */
    public static function deleteAllRememberTokens($userId)
    {
        $db = Database::getInstance();
        $db->exec("DELETE FROM remember_tokens WHERE user_id = ?", [$userId]);
    }
}