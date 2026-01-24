<?php
class Database
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            $db_path = __DIR__ . '/../storage/database.sqlite';

            // Create database file if not exists
            if (!file_exists($db_path)) {
                touch($db_path);
            }

            self::$instance = new \DB\SQL('sqlite:' . $db_path);
            self::$instance->exec('PRAGMA foreign_keys = ON;');

            // Create tables if not exists
            self::createTables();
        }

        return self::$instance;
    }

    public static function generateId($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $id = '';
        for ($i = 0; $i < $length; $i++) {
            $id .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $id;
    }

    private static function createTables()
    {
        $db = self::getInstance();

        // Users table
        $sql_users = "CREATE TABLE IF NOT EXISTS users (
            id VARCHAR(12) PRIMARY KEY NOT NULL,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'user',
            remember_token VARCHAR(255) DEFAULT NULL,
            token_expires_at TIMESTAMP DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        $db->exec($sql_users);

        // Spaces table
        $sql_spaces = "CREATE TABLE IF NOT EXISTS spaces (
            id VARCHAR(12) PRIMARY KEY NOT NULL,
            name VARCHAR(100) NOT NULL DEFAULT 'untitled',
            description TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'Published',
            class_icon TEXT NOT NULL DEFAULT 'folder',
            owner_id VARCHAR(12) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
        )";

        $db->exec($sql_spaces);

        // Files table
        $sql_files = "CREATE TABLE IF NOT EXISTS files (
            id VARCHAR(12) PRIMARY KEY NOT NULL,
            space_id VARCHAR(12) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path TEXT NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size INTEGER NOT NULL,
            uploaded_by VARCHAR(12) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (space_id) REFERENCES spaces(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
        )";

        $db->exec($sql_files);

        // Tags table
        $sql_tags = "CREATE TABLE IF NOT EXISTS tags (
            id VARCHAR(12) PRIMARY KEY NOT NULL,
            name VARCHAR(50) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";

        $db->exec($sql_tags);

        // Space tags junction table
        $sql_space_tags = "CREATE TABLE IF NOT EXISTS space_tags (
            space_id VARCHAR(12) NOT NULL,
            tag_id VARCHAR(12) NOT NULL,
            PRIMARY KEY (space_id, tag_id),
            FOREIGN KEY (space_id) REFERENCES spaces(id) ON DELETE CASCADE,
            FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
        )";

        $db->exec($sql_space_tags);

        // Space access table
        $sql_space_access = "CREATE TABLE IF NOT EXISTS space_access (
            id VARCHAR(12) PRIMARY KEY NOT NULL,
            space_id VARCHAR(12) NOT NULL,
            user_id VARCHAR(12) NOT NULL,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(space_id, user_id),
            FOREIGN KEY (space_id) REFERENCES spaces(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";

        $db->exec($sql_space_access);

        // Create indexes for better performance
        $db->exec("CREATE INDEX IF NOT EXISTS idx_spaces_owner ON spaces(owner_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_spaces_status ON spaces(status)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_files_space ON files(space_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_space_tags_space ON space_tags(space_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_space_tags_tag ON space_tags(tag_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_space_access_space ON space_access(space_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_space_access_user ON space_access(user_id)");

        // Insert default admin user if not exists
        self::createDefaultAdmin();

        // Migration: Add review_mode column if not exists
        try {
            $db->exec("ALTER TABLE spaces ADD COLUMN review_mode INTEGER DEFAULT 0");
        } catch (\Exception $e) {
            // Column likely already exists, ignore
        }
    }

    private static function createDefaultAdmin()
    {
        $db = self::getInstance();

        // Check if ANY user exists
        $result = $db->exec("SELECT COUNT(*) as count FROM users");

        if ($result[0]['count'] == 0) {
            $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
            $admin_id = self::generateId();

            $db->exec("INSERT INTO users (id, username, email, password_hash, role) 
                      VALUES (?, 'admin', 'admin@tinyspace.com', ?, 'admin')",
                [$admin_id, $password_hash]
            );
        }
    }
}