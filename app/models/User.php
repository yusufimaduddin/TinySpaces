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
}