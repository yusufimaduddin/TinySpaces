<?php
class Space extends DB\SQL\Mapper
{
    public function __construct()
    {
        parent::__construct(Database::getInstance(), 'spaces');
    }

    /**
     * Get all spaces accessible by a user (owned + shared)
     */
    public function getUserSpaces($userId, $status = null, $search = null)
    {
        $db = Database::getInstance();

        $sql = "SELECT s.*, 
                   (SELECT COUNT(*) FROM files f WHERE f.space_id = s.id) as file_count,
                   (SELECT GROUP_CONCAT(t.name, ',') 
                    FROM space_tags st 
                    JOIN tags t ON st.tag_id = t.id 
                    WHERE st.space_id = s.id) as tags,
                   CASE WHEN s.owner_id = ? THEN 1 ELSE 0 END as is_owner
            FROM spaces s
            LEFT JOIN space_access sa ON s.id = sa.space_id
            WHERE (s.owner_id = ? OR (s.status = 'published' AND sa.user_id = ?))";

        $params = [$userId, $userId, $userId];

        if ($status && $status !== 'all') {
            $sql .= " AND s.status = ?";
            $params[] = strtolower($status);
        }

        if ($search) {
            $sql .= " AND (s.name LIKE ? OR s.description LIKE ? OR EXISTS(
                SELECT 1 FROM space_tags st2 
                JOIN tags t2 ON st2.tag_id = t2.id 
                WHERE st2.space_id = s.id AND t2.name LIKE ?
            ))";
            $searchTerm = '%' . $search . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $sql .= " GROUP BY s.id ORDER BY s.created_at DESC";

        return $db->exec($sql, $params);
    }

    /**
     * Get recently modified spaces for a user
     */
    public function getRecentlyModifiedSpaces($userId, $limit = 3)
    {
        $db = Database::getInstance();

        return $db->exec(
            "SELECT s.*, 
                   (SELECT COUNT(*) FROM files f WHERE f.space_id = s.id) as file_count
            FROM spaces s
            WHERE s.owner_id = ? OR EXISTS(
                SELECT 1 FROM space_access sa 
                WHERE sa.space_id = s.id AND sa.user_id = ?
            )
            ORDER BY s.updated_at DESC
            LIMIT ?",
            [$userId, $userId, $limit]
        );
    }

    /**
     * Check if user has access to a space
     */
    public function checkAccess($spaceId, $userId)
    {
        $db = Database::getInstance();

        $result = $db->exec(
            "SELECT s.*, u.username as owner_name,
                    CASE WHEN s.owner_id = ? THEN 1 ELSE 0 END as is_owner,
                    EXISTS(SELECT 1 FROM space_access WHERE space_id = s.id AND user_id = ?) as has_shared_access
             FROM spaces s
             JOIN users u ON s.owner_id = u.id
             WHERE s.id = ?",
            [$userId, $userId, $spaceId]
        );

        if (empty($result)) {
            return null;
        }

        $space = $result[0];

        // Owner always has access
        if ($space['is_owner']) {
            return $space;
        }

        // For non-owners, check if published and has shared access
        if ($space['status'] === 'published' && $space['has_shared_access']) {
            return $space;
        }

        // For review mode (must be published)
        if ($space['status'] === 'published' && !empty($space['review_mode'])) {
            return $space;
        }

        return null;
    }

    /**
     * Create a new space with folder and README
     */
    public function createSpace($data, $userId)
    {
        $db = Database::getInstance();
        $db->begin();

        try {
            // Generate unique 12-character space ID
            $spaceId = Database::generateId();
            while ($db->exec("SELECT count(*) as count FROM spaces WHERE id = ?", [$spaceId])[0]['count'] > 0) {
                $spaceId = Database::generateId();
            }

            // Insert space using named parameters
            $timestamp = date('Y-m-d H:i:s');
            $db->exec(
                "INSERT INTO spaces (id, owner_id, name, description, status, class_icon, created_at, updated_at) 
                 VALUES (:id, :owner_id, :name, :desc, :status, :icon, :created, :updated)",
                [
                    ':id' => $spaceId,
                    ':owner_id' => $userId,
                    ':name' => $data['name'],
                    ':desc' => $data['description'] ?? '',
                    ':status' => strtolower($data['status'] ?? 'published'),
                    ':icon' => $data['class_icon'] ?? 'bx-folder',
                    ':created' => $timestamp,
                    ':updated' => $timestamp
                ]
            );

            // Create folder
            $uploadDir = __DIR__ . '/../../public/uploads/spaces/' . $spaceId;
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Create README.md
            $readmePath = $uploadDir . '/README.md';
            $readmeContent = "# {$data['name']}\n\n{$data['description']}\n\n## Files\n\nThis space contains files related to {$data['name']}.";
            file_put_contents($readmePath, $readmeContent);

            // Insert README into files table
            $db->exec(
                "INSERT INTO files (id, space_id, original_name, file_path, file_size, mime_type, uploaded_by) 
                 VALUES (:id, :space_id, :orig_name, :path, :size, :mime, :uploader)",
                [
                    ':id' => Database::generateId(),
                    ':space_id' => $spaceId,
                    ':orig_name' => 'README.md',
                    ':path' => '/public/uploads/spaces/' . $spaceId . '/README.md',
                    ':size' => (int) filesize($readmePath),
                    ':mime' => 'text/markdown',
                    ':uploader' => $userId
                ]
            );

            $db->commit();
            return $spaceId;
        } catch (\Exception $e) {
            $db->rollback();
            return false;
        }
    }

    /**
     * Toggle review mode
     */
    public function toggleReviewMode($spaceId, $enabled, $userId)
    {
        $this->load(['id = ? AND owner_id = ?', $spaceId, $userId]);

        if ($this->dry()) {
            return false;
        }

        $this->review_mode = $enabled ? 1 : 0;
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();

        return true;
    }

    /**
     * Update space settings
     */
    public function updateSpace($spaceId, $data, $userId)
    {
        $this->load(['id = ? AND owner_id = ?', $spaceId, $userId]);

        if ($this->dry()) {
            return false;
        }

        if (isset($data['name']))
            $this->name = $data['name'];
        if (isset($data['description']))
            $this->description = $data['description'];
        if (isset($data['status']))
            $this->status = strtolower($data['status']);
        if (isset($data['class_icon']))
            $this->class_icon = $data['class_icon'];

        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();

        return true;
    }

    /**
     * Delete space and all associated files
     */
    public function deleteSpace($spaceId, $userId)
    {
        $this->load(['id = ? AND owner_id = ?', $spaceId, $userId]);

        if ($this->dry()) {
            return false;
        }

        // Delete physical files
        $uploadDir = __DIR__ . '/../../public/uploads/spaces/' . $spaceId;
        if (file_exists($uploadDir)) {
            $this->deleteDirectory($uploadDir);
        }

        // Database cascade will handle files, tags, and access records
        $this->erase();

        return true;
    }

    /**
     * Share space with a user
     */
    public function shareSpace($spaceId, $email, $userId)
    {
        $db = Database::getInstance();

        // Check if user is owner
        $this->load(['id = ? AND owner_id = ?', $spaceId, $userId]);
        if ($this->dry()) {
            return ['success' => false, 'message' => 'You do not have permission to share this space'];
        }

        // Find user by email
        $userResult = $db->exec("SELECT id FROM users WHERE email = ?", [$email]);
        if (empty($userResult)) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $targetUserId = $userResult[0]['id'];

        // Don't share with owner
        if ($targetUserId == $userId) {
            return ['success' => false, 'message' => 'Cannot share with yourself'];
        }

        // Check if already shared
        $existing = $db->exec(
            "SELECT 1 FROM space_access WHERE space_id = ? AND user_id = ?",
            [$spaceId, $targetUserId]
        );

        if (!empty($existing)) {
            return ['success' => false, 'message' => 'Space already shared with this user'];
        }

        // Add to space_access
        $db->exec(
            "INSERT INTO space_access (id, space_id, user_id) VALUES (?, ?, ?)",
            [Database::generateId(), $spaceId, $targetUserId]
        );

        return ['success' => true, 'message' => 'Space shared successfully'];
    }

    /**
     * Helper function to delete directory recursively
     */
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
