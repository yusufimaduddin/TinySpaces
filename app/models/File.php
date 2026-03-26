<?php
class File extends DB\SQL\Mapper
{
    public function __construct()
    {
        parent::__construct(Database::getInstance(), 'files');
    }

    /**
     * Get all files for a space
     */
    public function getSpaceFiles($spaceId)
    {
        $db = Database::getInstance();
        $result = $db->exec(
            "SELECT id, space_id, original_name, file_path, mime_type, file_size, uploaded_by, uploaded_at
            FROM files
            WHERE space_id = ?
            ORDER BY uploaded_at DESC",
            [$spaceId]
        );
        return $result ?: [];
    }


    /**
     * Upload a file to a space
     */
    public function uploadFile($spaceId, $uploadedFile, $userId)
    {
        // Validate upload
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload error: ' . $this->getUploadErrorMessage($uploadedFile['error'])];
        }

        // Validate file size (100MB max)
        $maxSize = 100 * 1024 * 1024; // 100MB in bytes
        if ($uploadedFile['size'] > $maxSize) {
            return ['success' => false, 'message' => 'File size exceeds 100MB limit'];
        }

        // Validate space exists
        $db = Database::getInstance();
        $spaceCheck = $db->exec(
            "SELECT s.owner_id, 
                    CASE WHEN s.owner_id = ? THEN 1 ELSE 0 END as is_owner,
                    EXISTS(SELECT 1 FROM space_access WHERE space_id = s.id AND user_id = ?) as has_shared_access
             FROM spaces s WHERE s.id = ?",
            [$userId, $userId, $spaceId]
        );

        if (empty($spaceCheck)) {
            return ['success' => false, 'message' => 'Space not found'];
        }

        $space = $spaceCheck[0];

        // Check if user has permission (owner or shared access)
        if (!$space['is_owner'] && (!$space['has_shared_access'])) {
            return ['success' => false, 'message' => 'You do not have permission to upload to this space'];
        }

        // Generate unique filename
        $originalName = $uploadedFile['name'];
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $uniqueCode = Database::generateId();
        $filename = $uniqueCode . '.' . $extension;

        // Create upload directory if not exists
        $uploadDir = __DIR__ . '/../../public/uploads/spaces/' . $spaceId;
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filePath = $uploadDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
            return ['success' => false, 'message' => 'Failed to upload file'];
        }

        // Get MIME type
        $mimeType = mime_content_type($filePath);
        if (!$mimeType) {
            $mimeType = 'application/octet-stream';
        }

        // Save to database
        $this->id = Database::generateId();
        $this->space_id = $spaceId;
        $this->original_name = $originalName;
        $this->file_path = '/public/uploads/spaces/' . $spaceId . '/' . $filename;
        $this->file_size = $uploadedFile['size'];
        $this->mime_type = $mimeType;
        $this->uploaded_by = $userId;
        $this->uploaded_at = date('Y-m-d H:i:s');
        $this->updated_at = date('Y-m-d H:i:s');

        try {
            $this->save();

            // Update space updated_at
            $db->exec("UPDATE spaces SET updated_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $spaceId]);

            return ['success' => true, 'message' => 'File uploaded successfully', 'file_id' => $this->id];
        } catch (Exception $e) {
            // Delete physical file if save fails
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            return ['success' => false, 'message' => 'Failed to save file: ' . $e->getMessage()];
        }
    }

    /**
     * Delete a file
     */
    public function deleteFile($fileId, $userId, $spaceId)
    {
        $this->load(['id = ?', $fileId]);

        if ($this->dry()) {
            return ['success' => false, 'message' => 'File not found'];
        }

        // Check if README
        if ($this->original_name === 'README.md') {
            return ['success' => false, 'message' => 'Cannot delete README file'];
        }

        // Check if user has permission (owner or shared access)
        $db = Database::getInstance();
        $spaceCheck = $db->exec(
            "SELECT s.owner_id, 
                    CASE WHEN s.owner_id = ? THEN 1 ELSE 0 END as is_owner,
                    EXISTS(SELECT 1 FROM space_access WHERE space_id = s.id AND user_id = ?) as has_shared_access
             FROM spaces s WHERE s.id = ?",
            [$userId, $userId, $spaceId]
        );

        if (empty($spaceCheck)) {
            return ['success' => false, 'message' => 'Space not found'];
        }

        $space = $spaceCheck[0];

        if (!$space['is_owner'] && !$space['has_shared_access']) {
            return ['success' => false, 'message' => 'You do not have permission to delete this file'];
        }

        // Delete physical file
        $physicalPath = __DIR__ . '/../../' . $this->file_path;
        if (file_exists($physicalPath)) {
            unlink($physicalPath);
        }

        // Delete from database
        $this->erase();

        // Update space updated_at
        $db->exec("UPDATE spaces SET updated_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $spaceId]);

        return ['success' => true, 'message' => 'File deleted successfully'];
    }

    /**
     * Get file for download/view
     */
    public function getFile($fileId)
    {
        $this->load(['id = ?', $fileId]);

        if ($this->dry()) {
            return null;
        }

        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'file_path' => __DIR__ . '/../../' . $this->file_path,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size
        ];
    }

    /**
     * Update README content
     */
    public function updateReadme($spaceId, $content, $userId)
    {
        $db = Database::getInstance();

        // Check if user is owner
        $space = $db->exec("SELECT owner_id FROM spaces WHERE id = ?", [$spaceId]);
        if (empty($space) || $space[0]['owner_id'] != $userId) {
            return ['success' => false, 'message' => 'You do not have permission to edit README'];
        }

        // Get README file
        $this->load(['space_id = ? AND original_name = ?', $spaceId, 'README.md']);

        if ($this->dry()) {
            return ['success' => false, 'message' => 'README not found'];
        }

        // Update file content
        $physicalPath = __DIR__ . '/../../' . $this->file_path;
        if (!file_put_contents($physicalPath, $content)) {
            return ['success' => false, 'message' => 'Failed to update README'];
        }

        // Update file size and timestamp
        $this->file_size = filesize($physicalPath);
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();

        // Update space updated_at
        $db->exec("UPDATE spaces SET updated_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $spaceId]);

        return ['success' => true, 'message' => 'README updated successfully'];
    }

    /**
     * Update file content
     */
    public function updateFileContent($fileId, $content, $userId, $spaceId)
    {
        $db = Database::getInstance();

        // Check if user is owner/has permission (checked in controller, but good to be safe)
        $space = $db->exec("SELECT owner_id FROM spaces WHERE id = ?", [$spaceId]);

        // This method assumes permission is already checked or user is owner. 
        // For shared access, we rely on the controller's checkAccess logic.
        // However, we should ensure the file belongs to the space.

        $this->load(['id = ? AND space_id = ?', $fileId, $spaceId]);

        if ($this->dry()) {
            return ['success' => false, 'message' => 'File not found'];
        }

        // Update physical file
        $physicalPath = __DIR__ . '/../../' . $this->file_path;
        if (!file_exists($physicalPath)) {
            return ['success' => false, 'message' => 'Physical file not found'];
        }

        if (file_put_contents($physicalPath, $content) === false) {
            return ['success' => false, 'message' => 'Failed to write to file'];
        }

        // Update file size and timestamp
        $this->file_size = filesize($physicalPath);
        $this->updated_at = date('Y-m-d H:i:s');
        $this->save();

        // Update space updated_at
        $db->exec("UPDATE spaces SET updated_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), $spaceId]);

        return ['success' => true, 'message' => 'File saved successfully'];
    }

    /**
     * Get README content
     */
    public function getReadme($spaceId)
    {
        $db = Database::getInstance();
        $result = $db->exec(
            "SELECT file_path, updated_at FROM files WHERE space_id = ? AND original_name = 'README.md' LIMIT 1",
            [$spaceId]
        );

        if (empty($result)) {
            return ['content' => '', 'updated_at' => null];
        }

        $physicalPath = __DIR__ . '/../../' . $result[0]['file_path'];
        if (!file_exists($physicalPath)) {
            return ['content' => '', 'updated_at' => null];
        }

        $content = file_get_contents($physicalPath);
        return [
            'content' => $content,
            'updated_at' => $result[0]['updated_at']
        ];
    }

    /**
     * Cast all results to array
     */
    private function castAll()
    {
        $results = [];
        while (!$this->dry()) {
            $results[] = $this->cast();
            $this->skip();
        }
        return $results;
    }

    /**
     * Helper to get upload error message
     */
    private function getUploadErrorMessage($code)
    {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds max file size',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
        ];
        return $errors[$code] ?? 'Unknown upload error';
    }
}
