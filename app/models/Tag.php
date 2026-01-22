<?php
class Tag extends DB\SQL\Mapper
{
    public function __construct()
    {
        parent::__construct(Database::getInstance(), 'tags');
    }

    /**
     * Get all tags for a space
     */
    public function getSpaceTags($spaceId)
    {
        $db = Database::getInstance();
        return $db->exec(
            "SELECT t.id, t.name FROM tags t
             JOIN space_tags st ON t.id = st.tag_id
             WHERE st.space_id = ?
             ORDER BY t.name ASC",
            [$spaceId]
        );
    }

    /**
     * Add tags to a space
     */
    public function addTagsToSpace($spaceId, $tags, $userId)
    {
        try {
            $db = Database::getInstance();

            // Check if user is owner of the space
            $space = $db->exec("SELECT owner_id FROM spaces WHERE id = ?", [$spaceId]);
            if (empty($space) || $space[0]['owner_id'] != $userId) {
                return ['success' => false, 'message' => 'Access denied'];
            }

            // Process tags
            if (is_string($tags)) {
                $tags = array_map('trim', explode(',', $tags));
            }

            $addedTags = [];

            foreach ($tags as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName)) {
                    continue;
                }

                // Convert to lowercase for consistency
                $tagName = strtolower($tagName);

                // Check if tag exists
                $existingTag = $db->exec("SELECT id FROM tags WHERE name = ?", [$tagName]);

                if (!empty($existingTag)) {
                    $tagId = $existingTag[0]['id'];
                } else {
                    // Create new tag
                    $tagId = Database::generateId();
                    $db->exec(
                        "INSERT INTO tags (id, name) VALUES (?, ?)",
                        [$tagId, $tagName]
                    );
                }

                // Check if space already has this tag
                $existing = $db->exec(
                    "SELECT 1 FROM space_tags WHERE space_id = ? AND tag_id = ?",
                    [$spaceId, $tagId]
                );

                if (empty($existing)) {
                    // Add tag to space
                    $db->exec(
                        "INSERT INTO space_tags (space_id, tag_id) VALUES (?, ?)",
                        [$spaceId, $tagId]
                    );

                    $addedTags[] = $tagName;
                }
            }

            // Update space's updated_at
            $timestamp = date('Y-m-d H:i:s');
            $db->exec("UPDATE spaces SET updated_at = ? WHERE id = ?", [$timestamp, $spaceId]);

            return [
                'success' => true,
                'message' => count($addedTags) . ' tag(s) added successfully',
                'tags' => $addedTags
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error adding tags: ' . $e->getMessage()];
        }
    }

    /**
     * Remove tag from space
     */
    public function removeTagFromSpace($spaceId, $tagName, $userId)
    {
        try {
            $db = Database::getInstance();

            // Check if user is owner of the space
            $space = $db->exec("SELECT owner_id FROM spaces WHERE id = ?", [$spaceId]);
            if (empty($space) || $space[0]['owner_id'] != $userId) {
                return ['success' => false, 'message' => 'Access denied'];
            }

            // Get tag ID
            $tagName = strtolower(trim($tagName));
            $tagResult = $db->exec("SELECT id FROM tags WHERE name = ?", [$tagName]);

            if (empty($tagResult)) {
                return ['success' => false, 'message' => 'Tag not found'];
            }

            $tagId = $tagResult[0]['id'];

            // Remove tag from space
            $db->exec(
                "DELETE FROM space_tags WHERE space_id = ? AND tag_id = ?",
                [$spaceId, $tagId]
            );

            // Delete tag if it's not used by any other space
            $usage = $db->exec("SELECT COUNT(*) as count FROM space_tags WHERE tag_id = ?", [$tagId]);
            if ($usage[0]['count'] == 0) {
                $db->exec("DELETE FROM tags WHERE id = ?", [$tagId]);
            }

            // Update space's updated_at
            $timestamp = date('Y-m-d H:i:s');
            $db->exec("UPDATE spaces SET updated_at = ? WHERE id = ?", [$timestamp, $spaceId]);

            return ['success' => true, 'message' => 'Tag removed successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error removing tag: ' . $e->getMessage()];
        }
    }

    /**
     * Get all tags
     */
    public function getAllTags()
    {
        $db = Database::getInstance();
        return $db->exec(
            "SELECT t.id, t.name, COUNT(st.space_id) as usage_count 
             FROM tags t
             LEFT JOIN space_tags st ON t.id = st.tag_id
             GROUP BY t.id, t.name
             ORDER BY t.name ASC"
        );
    }

    /**
     * Search spaces by tag
     */
    public function searchByTag($tagName, $userId)
    {
        $db = Database::getInstance();
        return $db->exec(
            "SELECT DISTINCT s.*, 
                    (SELECT COUNT(*) FROM files f WHERE f.space_id = s.id) as file_count,
                    CASE WHEN s.owner_id = ? THEN 1 ELSE 0 END as is_owner
             FROM spaces s
             JOIN space_tags st ON s.id = st.space_id
             JOIN tags t ON st.tag_id = t.id
             LEFT JOIN space_access sa ON s.id = sa.space_id
             WHERE (t.name = ? OR t.name LIKE ?)
             AND (s.owner_id = ? OR (s.status = 'published' AND sa.user_id = ?))
             ORDER BY s.updated_at DESC",
            [$userId, strtolower($tagName), '%' . strtolower($tagName) . '%', $userId, $userId]
        );
    }
}
