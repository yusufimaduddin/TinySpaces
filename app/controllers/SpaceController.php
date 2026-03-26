<?php
class SpaceController
{
    private $f3;
    private $spaceModel;
    private $fileModel;
    private $tagModel;

    public function __construct()
    {
        $this->f3 = Base::instance();
        $this->spaceModel = new Space();
        $this->fileModel = new File();
        $this->tagModel = new Tag();
    }

    /**
     * GET /api/spaces - List user's spaces
     */
    public function listSpaces()
    {
        AuthController::requireLogin();

        $userId = $this->f3->get('SESSION.user_id');
        $status = $this->f3->get('GET.status');
        $search = $this->f3->get('GET.search');

        $spaces = $this->spaceModel->getUserSpaces($userId, $status, $search);

        $this->jsonResponse(['success' => true, 'spaces' => $spaces]);
    }

    /**
     * GET /api/spaces/recent - Get recently modified spaces
     */
    public function getRecentlyModified()
    {
        AuthController::requireLogin();

        $userId = $this->f3->get('SESSION.user_id');

        $spaces = $this->spaceModel->getRecentlyModifiedSpaces($userId, 3);

        $this->jsonResponse(['success' => true, 'spaces' => $spaces]);
    }

    /**
     * POST /api/spaces - Create new space
     */
    public function createSpace()
    {
        AuthController::requireLogin();

        $data = json_decode($this->f3->get('BODY'), true);
        $userId = $this->f3->get('SESSION.user_id');

        // Validate input
        if (empty($data['name'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Space name is required']);
            return;
        }

        try {
            $spaceId = $this->spaceModel->createSpace($data, $userId);
            $this->jsonResponse(['success' => true, 'message' => 'Space created successfully', 'space_id' => $spaceId]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'message' => 'Failed to create space: ' . $e->getMessage()]);
        }
    }

    /**
     * GET /api/spaces/@id - Get space details
     */
    public function getSpace()
    {
        AuthController::requireLogin();

        $spaceId = $this->f3->get('PARAMS.id');
        $userId = $this->f3->get('SESSION.user_id');

        // Check access
        $space = $this->spaceModel->checkAccess($spaceId, $userId);

        if (!$space) {
            $this->jsonResponse(['success' => false, 'message' => 'Access denied or space not found'], 403);
            return;
        }

        // Get files
        $files = $this->fileModel->getSpaceFiles($spaceId);

        // Get tags
        $tags = $this->tagModel->getSpaceTags($spaceId);

        // Get shared users
        $db = Database::getInstance();
        $sharedUsers = $db->exec(
            "SELECT u.id, u.username, u.email 
             FROM users u
             JOIN space_access sa ON u.id = sa.user_id
             WHERE sa.space_id = ?",
            [$spaceId]
        );

        // Get README content
        $readmeContent = $this->fileModel->getReadme($spaceId);

        $this->jsonResponse([
            'success' => true,
            'space' => $space,
            'files' => $files ?: [],
            'tags' => $tags ?: [],
            'shared_users' => $sharedUsers ?: [],
            'readme' => $readmeContent
        ]);
    }

    /**
     * PUT /api/spaces/@id - Update space settings
     */
    public function updateSpace()
    {
        AuthController::requireLogin();

        $spaceId = $this->f3->get('PARAMS.id');
        $userId = $this->f3->get('SESSION.user_id');
        $data = json_decode($this->f3->get('BODY'), true);

        $result = $this->spaceModel->updateSpace($spaceId, $data, $userId);

        if ($result) {
            $this->jsonResponse(['success' => true, 'message' => 'Space updated successfully']);
        } else {
            $this->jsonResponse(['success' => false, 'message' => 'Failed to update space or access denied'], 403);
        }
    }

    /**
     * DELETE /api/spaces/@id - Delete space with password confirmation
     */
    public function deleteSpace()
    {
        AuthController::requireLogin();

        $spaceId = $this->f3->get('PARAMS.id');
        $userId = $this->f3->get('SESSION.user_id');
        $data = json_decode($this->f3->get('BODY'), true);

        // Password confirmation removed as per request
        // if (empty($data['password'])) ...

        $result = $this->spaceModel->deleteSpace($spaceId, $userId);

        if ($result) {
            $this->jsonResponse(['success' => true, 'message' => 'Space permanently deleted']);
        } else {
            $this->jsonResponse(['success' => false, 'message' => 'Failed to delete space or access denied'], 403);
        }
    }

    /**
     * POST /api/spaces/@id/upload - Upload file
     */
    public function uploadFile()
    {
        AuthController::requireLogin();

        $spaceId = $this->f3->get('PARAMS.id');
        $userId = $this->f3->get('SESSION.user_id');

        // Check access
        $space = $this->spaceModel->checkAccess($spaceId, $userId);

        if (!$space || !($space['is_owner'] || $space['has_shared_access'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
            return;
        }

        // Check if file was uploaded
        if (empty($_FILES['file'])) {
            $this->jsonResponse(['success' => false, 'message' => 'No file uploaded']);
            return;
        }

        $result = $this->fileModel->uploadFile($spaceId, $_FILES['file'], $userId);
        $this->jsonResponse($result);
    }

    /**
     * GET /api/spaces/@space_id/files/@file_id/download - Download file
     */
    public function downloadFile()
    {
        AuthController::requireLogin();

        $spaceId = $this->f3->get('PARAMS.space_id');
        $fileId = $this->f3->get('PARAMS.file_id');
        $userId = $this->f3->get('SESSION.user_id');

        // Check access
        $space = $this->spaceModel->checkAccess($spaceId, $userId);

        if (!$space) {
            header('HTTP/1.1 403 Forbidden');
            echo 'Access denied';
            exit;
        }

        // Get file
        $file = $this->fileModel->getFile($fileId);

        if (!$file || !file_exists($file['file_path'])) {
            header('HTTP/1.1 404 Not Found');
            echo 'File not found';
            exit;
        }

        // Send file
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
        header('Content-Length: ' . $file['file_size']);
        readfile($file['file_path']);
        exit;
    }

    /**
     * GET /api/spaces/@space_id/files/@file_id/view - View file
     */
    public function viewFile()
    {
        AuthController::requireLogin();

        $spaceId = $this->f3->get('PARAMS.space_id');
        $fileId = $this->f3->get('PARAMS.file_id');
        $userId = $this->f3->get('SESSION.user_id');
        $isRaw = $this->f3->get('GET.raw');

        // Check access
        $space = $this->spaceModel->checkAccess($spaceId, $userId);

        if (!$space) {
            header('HTTP/1.1 403 Forbidden');
            echo 'Access denied';
            exit;
        }

        // Get file
        $file = $this->fileModel->getFile($fileId);

        if (!$file || !file_exists($file['file_path'])) {
            header('HTTP/1.1 404 Not Found');
            echo 'File not found';
            exit;
        }

        // Check if it's a markdown file and we are not requesting raw content
        $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['md', 'markdown']) && !$isRaw) {
            $this->f3->reroute('/user/editor/' . $spaceId . '/' . $fileId);
            return;
        }

        // Send file with original mime type
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . $file['file_size']);
        readfile($file['file_path']);
        exit;
    }

    /**
     * PUT /api/spaces/@space_id/files/@file_id - Update file content
     */
    public function updateFile()
    {
        AuthController::requireLogin();

        $spaceId = $this->f3->get('PARAMS.space_id');
        $fileId = $this->f3->get('PARAMS.file_id');
        $userId = $this->f3->get('SESSION.user_id');
        $data = json_decode($this->f3->get('BODY'), true);

        // Check access (Owner or Collaborator)
        // We use checkAccess which returns space info if the user has access.
        // We then check if they have write permission.
        $space = $this->spaceModel->checkAccess($spaceId, $userId);

        if (!$space) {
            $this->jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
            return;
        }

        // Check permissions: Owner ALWAYS can. Collaborator: can if NOT in review mode (or if implementation details vary).
        // For now, let's assume checkAccess grants read/review capability.
        // We need to verify if the user is owner OR has shared access.
        // The implementation plan says: "Only users with appropriate permissions (owner or collaborator) will be able to save changes."

        $canEdit = $space['is_owner'] || $space['has_shared_access'];

        if (!$canEdit) {
            $this->jsonResponse(['success' => false, 'message' => 'You do not have permission to edit this file'], 403);
            return;
        }

        if (!isset($data['content'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Content is required']);
            return;
        }

        $result = $this->fileModel->updateFileContent($fileId, $data['content'], $userId, $spaceId);
        $this->jsonResponse($result);
    }

    /**
     * DELETE /api/spaces/@space_id/files/@file_id - Delete file
     */
    public function deleteFile()
    {
        AuthController::requireLogin();

        $spaceId = $this->f3->get('PARAMS.space_id');
        $fileId = $this->f3->get('PARAMS.file_id');
        $userId = $this->f3->get('SESSION.user_id');

        $result = $this->fileModel->deleteFile($fileId, $userId, $spaceId);
        $this->jsonResponse($result);
    }

    /**
     * POST /api/spaces/@id/tags - Add/remove tags
     */
    public function manageTags()
    {
        AuthController::requireLogin();

        $spaceId = $this->f3->get('PARAMS.id');
        $userId = $this->f3->get('SESSION.user_id');
        $data = json_decode($this->f3->get('BODY'), true);

        $action = $data['action'] ?? 'add';

        if ($action === 'add' && !empty($data['tags'])) {
            $result = $this->tagModel->addTagsToSpace($spaceId, $data['tags'], $userId);
            $this->jsonResponse($result);
        } elseif ($action === 'remove' && !empty($data['tag'])) {
            $result = $this->tagModel->removeTagFromSpace($spaceId, $data['tag'], $userId);
            $this->jsonResponse($result);
        } else {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid action or missing data']);
        }
    }

    /**
     * POST /api/spaces/@id/share - Share space
     */
    public function shareSpace()
    {
        AuthController::requireLogin();

        $spaceId = $this->f3->get('PARAMS.id');
        $userId = $this->f3->get('SESSION.user_id');
        $data = json_decode($this->f3->get('BODY'), true);

        if (empty($data['email'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Email is required']);
            return;
        }

        $result = $this->spaceModel->shareSpace($spaceId, $data['email'], $userId);
        $this->jsonResponse($result);
    }

    /**
     * POST /api/spaces/@id/review-mode - Toggle review mode
     */
    public function toggleReviewMode()
    {
        AuthController::requireLogin();

        $spaceId = $this->f3->get('PARAMS.id');
        $userId = $this->f3->get('SESSION.user_id');
        $data = json_decode($this->f3->get('BODY'), true);

        $enabled = isset($data['enabled']) ? (bool) $data['enabled'] : false;

        $result = $this->spaceModel->toggleReviewMode($spaceId, $enabled, $userId);

        if ($result) {
            $this->jsonResponse(['success' => true, 'message' => 'Review mode ' . ($enabled ? 'enabled' : 'disabled')]);
        } else {
            $this->jsonResponse(['success' => false, 'message' => 'Failed to update review mode'], 403);
        }
    }

    /**
     * PUT /api/spaces/@id/readme - Update README
     */
    public function updateReadme()
    {
        AuthController::requireLogin();

        $spaceId = $this->f3->get('PARAMS.id');
        $userId = $this->f3->get('SESSION.user_id');
        $data = json_decode($this->f3->get('BODY'), true);

        if (!isset($data['content'])) {
            $this->jsonResponse(['success' => false, 'message' => 'Content is required']);
            return;
        }

        $result = $this->fileModel->updateReadme($spaceId, $data['content'], $userId);
        $this->jsonResponse($result);
    }

    /**
     * Helper function to send JSON response
     */
    private function jsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
