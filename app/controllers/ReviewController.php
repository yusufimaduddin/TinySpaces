<?php
class ReviewController
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

    public function review()
    {
        AuthController::requireLogin();

        $spaceId = $this->f3->get('PARAMS.id');
        $userId = $this->f3->get('SESSION.user_id');

        // Check if space exists and is published
        $db = Database::getInstance();
        $spaceResult = $db->exec("SELECT * FROM spaces WHERE id = ?", [$spaceId]);

        if (empty($spaceResult)) {
            $this->f3->error(404, 'Space not found');
            return;
        }

        $space = $spaceResult[0];

        // Owner always has access
        $isOwner = $space['owner_id'] === $userId;

        if (!$isOwner) {
            // Strict checks for non-owners
            if ($space['status'] !== 'published' || $space['review_mode'] != 1) {
                $this->f3->error(403, 'Review mode is not enabled for this space');
                return;
            }
        }

        // Get owner name
        $owner = $db->exec("SELECT username FROM users WHERE id = ?", [$space['owner_id']]);
        $ownerName = $owner[0]['username'] ?? 'Unknown';

        $this->f3->set('title', 'Review: ' . $space['name']);
        $this->f3->set('description', 'Reviewing space: ' . $space['name']);
        $this->f3->set('author', $ownerName);
        $this->f3->set('username', $this->f3->get('SESSION.username'));
        $this->f3->set('email', $this->f3->get('SESSION.email'));
        $this->f3->set('role', $this->f3->get('SESSION.role'));
        $this->f3->set('space_id', $spaceId);

        echo \Template::instance()->render('user/review.html');
    }
}
