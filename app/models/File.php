<?php
class File extends DB\SQL\Mapper
{
    private static $textExtensions = [
        'md', 'markdown', 'mdown', 'mkd', 'txt', 'text', 'log', 'csv', 'tsv',
        'html', 'htm', 'xhtml', 'css', 'scss', 'sass', 'less',
        'js', 'mjs', 'cjs', 'ts', 'tsx', 'jsx', 'json', 'jsonc', 'xml', 'svg',
        'yml', 'yaml', 'toml', 'ini', 'conf', 'env', 'gitignore', 'htaccess',
        'php', 'phtml', 'py', 'rb', 'java', 'c', 'h', 'cpp', 'hpp', 'cc', 'cs',
        'go', 'rs', 'swift', 'kt', 'kts', 'dart', 'sh', 'bash', 'zsh', 'fish',
        'ps1', 'bat', 'cmd', 'sql', 'graphql', 'gql', 'vue', 'svelte', 'astro',
        'twig', 'blade', 'lock', 'map', 'properties', 'pl', 'lua', 'r', 'scala',
        'clj', 'cljs', 'edn', 'ex', 'exs', 'erl', 'hrl', 'fs', 'fsx', 'vb',
        'asm', 's', 'sol', 'proto', 'tf', 'tfvars', 'hcl', 'nginx', 'cmake',
        'gradle', 'editorconfig'
    ];

    private static $textFilenames = [
        'dockerfile', 'makefile', 'license', 'licence', 'readme', 'changelog',
        'procfile', 'gemfile', 'rakefile', 'vagrantfile', 'jenkinsfile',
        '.env', '.gitignore', '.htaccess', '.editorconfig'
    ];

    public function __construct()
    {
        parent::__construct(Database::getInstance(), 'files');
    }

    /**
     * Check whether a file can be edited as plain text
     */
    public static function isTextFile($filename, $mimeType = null)
    {
        $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        $basename = strtolower(basename((string) $filename));

        if ($extension !== '' && in_array($extension, self::$textExtensions, true)) {
            return true;
        }

        if (in_array($basename, self::$textFilenames, true)) {
            return true;
        }

        $mimeType = strtolower((string) $mimeType);

        if ($mimeType !== '') {
            if (str_starts_with($mimeType, 'text/')) {
                return true;
            }

            $textMimeTypes = [
                'application/json',
                'application/xml',
                'application/javascript',
                'application/x-javascript',
                'application/ecmascript',
                'application/x-httpd-php',
                'application/x-sh',
                'application/sql',
                'application/graphql',
                'application/x-yaml',
                'application/yaml',
                'application/toml',
                'image/svg+xml'
            ];

            if (in_array($mimeType, $textMimeTypes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a file should be edited as markdown
     */
    public static function isMarkdownFile($filename)
    {
        $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        return in_array($extension, ['md', 'markdown', 'mdown', 'mkd'], true);
    }

    /**
     * Check whether a browser can display this file inline.
     * Everything else should be sent as a download.
     */
    public static function isInlineViewable($filename, $mimeType = null)
    {
        $mimeType = strtolower((string) $mimeType);
        $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));

        if ($mimeType !== '') {
            if (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/svg+xml') {
                return true;
            }

            if (str_starts_with($mimeType, 'audio/') || str_starts_with($mimeType, 'video/')) {
                return true;
            }

            if ($mimeType === 'application/pdf') {
                return true;
            }
        }

        return in_array($extension, [
            'pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'avif', 'ico',
            'mp3', 'wav', 'ogg', 'oga', 'm4a', 'flac',
            'mp4', 'webm', 'ogv', 'mov', 'm4v'
        ], true);
    }

    /**
     * Get all files for a space
     */
    public function getSpaceFiles($spaceId)
    {
        $db = Database::getInstance();
        $result = $db->exec(
            "SELECT id, space_id, original_name, file_path, mime_type, file_size, uploaded_by, uploaded_at, group_name
            FROM files
            WHERE space_id = ?
            ORDER BY uploaded_at DESC",
            [$spaceId]
        );
        return $result ?: [];
    }

    /**
     * Merge one or more files into a group (or remove them from their group when no name is given)
     */
    public function mergeFilesToGroup($spaceId, $fileIds, $groupName, $userId)
    {
        $db = Database::getInstance();
        $access = $this->getSpaceAccess($spaceId, $userId);

        if (!$access['found']) {
            return ['success' => false, 'message' => 'Space not found', 'status' => 404];
        }

        if (!$access['allowed']) {
            return ['success' => false, 'message' => 'You do not have permission to manage groups', 'status' => 403];
        }

        $ids = [];
        foreach ((array) $fileIds as $id) {
            if (is_string($id) || is_int($id)) {
                $id = trim((string) $id);
                if ($id !== '') {
                    $ids[$id] = $id;
                }
            }
        }
        $ids = array_values($ids);

        if (empty($ids)) {
            return ['success' => false, 'message' => 'No files selected', 'status' => 400];
        }

        $name = $this->normalizeGroupName($groupName);
        $name = $name === '' ? null : $this->resolveGroupName($spaceId, $name);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $matchingFiles = $db->exec(
            "SELECT id FROM files
             WHERE space_id = ? AND original_name <> ? AND id IN ($placeholders)",
            array_merge([$spaceId, 'README.md'], $ids)
        );

        if (empty($matchingFiles)) {
            return ['success' => false, 'message' => 'No matching files found', 'status' => 404];
        }

        $now = date('Y-m-d H:i:s');
        $db->exec(
            "UPDATE files SET group_name = ?, updated_at = ?
             WHERE space_id = ? AND original_name <> ? AND id IN ($placeholders)",
            array_merge([$name, $now, $spaceId, 'README.md'], $ids)
        );
        $db->exec("UPDATE spaces SET updated_at = ? WHERE id = ?", [$now, $spaceId]);

        return [
            'success' => true,
            'message' => $name === null ? 'Files removed from group' : 'Files merged into group successfully',
            'group_name' => $name,
            'count' => count($matchingFiles)
        ];
    }

    /**
     * Remove every file from a group without deleting the files
     */
    public function releaseGroup($spaceId, $groupName, $userId)
    {
        $db = Database::getInstance();
        $access = $this->getSpaceAccess($spaceId, $userId);

        if (!$access['found']) {
            return ['success' => false, 'message' => 'Space not found', 'status' => 404];
        }

        if (!$access['allowed']) {
            return ['success' => false, 'message' => 'You do not have permission to manage groups', 'status' => 403];
        }

        $name = $this->normalizeGroupName($groupName);

        if ($name === '') {
            return ['success' => false, 'message' => 'Group name is required', 'status' => 400];
        }

        $files = $db->exec(
            "SELECT id FROM files
             WHERE space_id = ? AND group_name IS NOT NULL
               AND LOWER(group_name) = LOWER(?) AND original_name <> ?",
            [$spaceId, $name, 'README.md']
        );

        if (empty($files)) {
            return ['success' => false, 'message' => 'Group not found', 'status' => 404];
        }

        $now = date('Y-m-d H:i:s');
        $db->exec(
            "UPDATE files SET group_name = NULL, updated_at = ?
             WHERE space_id = ? AND group_name IS NOT NULL
               AND LOWER(group_name) = LOWER(?) AND original_name <> ?",
            [$now, $spaceId, $name, 'README.md']
        );
        $db->exec("UPDATE spaces SET updated_at = ? WHERE id = ?", [$now, $spaceId]);

        return [
            'success' => true,
            'message' => 'All files released from group',
            'group_name' => $name,
            'count' => count($files)
        ];
    }

    /**
     * Delete every file inside a group
     */
    public function deleteGroup($spaceId, $groupName, $userId)
    {
        $db = Database::getInstance();
        $access = $this->getSpaceAccess($spaceId, $userId);

        if (!$access['found']) {
            return ['success' => false, 'message' => 'Space not found', 'status' => 404];
        }

        if (!$access['allowed']) {
            return ['success' => false, 'message' => 'You do not have permission to manage groups', 'status' => 403];
        }

        $name = $this->normalizeGroupName($groupName);

        if ($name === '') {
            return ['success' => false, 'message' => 'Group name is required', 'status' => 400];
        }

        $files = $db->exec(
            "SELECT id, file_path FROM files
             WHERE space_id = ? AND group_name IS NOT NULL
               AND LOWER(group_name) = LOWER(?) AND original_name <> ?",
            [$spaceId, $name, 'README.md']
        );

        if (empty($files)) {
            return ['success' => false, 'message' => 'Group not found', 'status' => 404];
        }

        $ids = [];
        foreach ($files as $file) {
            $physicalPath = __DIR__ . '/../../' . $file['file_path'];
            if (file_exists($physicalPath)) {
                unlink($physicalPath);
            }
            $ids[] = $file['id'];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->exec(
            "DELETE FROM files WHERE space_id = ? AND id IN ($placeholders)",
            array_merge([$spaceId], $ids)
        );

        $now = date('Y-m-d H:i:s');
        $db->exec("UPDATE spaces SET updated_at = ? WHERE id = ?", [$now, $spaceId]);

        return [
            'success' => true,
            'message' => 'All files in the group were deleted',
            'group_name' => $name,
            'count' => count($ids)
        ];
    }

    /**
     * Get all downloadable files in a group
     */
    public function getGroupFiles($spaceId, $groupName)
    {
        $name = $this->normalizeGroupName($groupName);

        if ($name === '') {
            return [];
        }

        $db = Database::getInstance();
        $result = $db->exec(
            "SELECT id, space_id, original_name, file_path, mime_type, file_size, uploaded_by, uploaded_at, group_name
             FROM files
             WHERE space_id = ? AND group_name IS NOT NULL
               AND LOWER(group_name) = LOWER(?) AND original_name <> ?
             ORDER BY original_name ASC",
            [$spaceId, $name, 'README.md']
        );

        return $result ?: [];
    }


    /**
     * Upload a file to a space
     */
    public function uploadFile($spaceId, $uploadedFile, $userId, $groupName = '')
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

        // Generate unique filename. Store it with a neutral extension so an
        // uploaded .php/.phtml file can never be executed by the web server.
        // The original name and detected MIME type are kept in the database.
        $originalName = $uploadedFile['name'];
        $uniqueCode = Database::generateId();
        $filename = $uniqueCode . '.dat';

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

        // Normalize the optional group name. Reuse the existing spelling when
        // the name only differs by case so groups stay consistent.
        $group = $this->normalizeGroupName($groupName);
        if ($group !== '' && $originalName !== 'README.md') {
            $group = $this->resolveGroupName($spaceId, $group);
        } else {
            $group = null;
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
        $this->group_name = $group;

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
        // The file must belong to the space in the route. Without this check a
        // user with access to one space could delete a file from another space.
        $this->load(['id = ? AND space_id = ?', $fileId, $spaceId]);

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
            'space_id' => $this->space_id,
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

        // Check if user is owner or has shared access
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

        if (!$spaceCheck[0]['is_owner'] && !$spaceCheck[0]['has_shared_access']) {
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

        if (!self::isTextFile($this->original_name, $this->mime_type)) {
            return ['success' => false, 'message' => 'This file cannot be edited as text'];
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
     * Check whether a user can manage files in a space
     */
    private function getSpaceAccess($spaceId, $userId)
    {
        $db = Database::getInstance();
        $result = $db->exec(
            "SELECT s.owner_id,
                    CASE WHEN s.owner_id = ? THEN 1 ELSE 0 END as is_owner,
                    EXISTS(SELECT 1 FROM space_access WHERE space_id = s.id AND user_id = ?) as has_shared_access
             FROM spaces s WHERE s.id = ?",
            [$userId, $userId, $spaceId]
        );

        if (empty($result)) {
            return ['found' => false, 'allowed' => false];
        }

        $space = $result[0];

        return [
            'found' => true,
            'allowed' => (bool) ($space['is_owner'] || $space['has_shared_access'])
        ];
    }

    /**
     * Normalize a group name
     */
    private function normalizeGroupName($name)
    {
        if (!is_string($name) && !is_numeric($name)) {
            return '';
        }

        $name = trim(preg_replace('/\s+/', ' ', (string) $name));

        if ($name === '') {
            return '';
        }

        return function_exists('mb_substr') ? mb_substr($name, 0, 100) : substr($name, 0, 100);
    }

    /**
     * Reuse the existing group spelling when the name only differs by case
     */
    private function resolveGroupName($spaceId, $name)
    {
        $db = Database::getInstance();
        $result = $db->exec(
            "SELECT group_name FROM files
             WHERE space_id = ? AND group_name IS NOT NULL AND group_name <> ''
               AND LOWER(group_name) = LOWER(?) LIMIT 1",
            [$spaceId, $name]
        );

        return !empty($result) ? $result[0]['group_name'] : $name;
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
