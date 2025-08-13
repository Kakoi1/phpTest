<?php
class Controller
{
    private $postModel;

    public function __construct($conn)
    {
        $this->postModel = new Messages($conn);
    }

    public function addPost($name, $subject, $message)
    {
        try {
            // Validate input
            if (empty($name) || empty($subject) || empty($message)) {
                throw new Exception("All fields are required");
            }

            $this->postModel->addMessage($name, $subject, $message);
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to add post: " . $e->getMessage());
        }
    }

    public function updatePost($id, $name, $subject, $message)
    {
        try {
            // Validate input
            if (empty($id) || empty($name) || empty($subject) || empty($message)) {
                throw new Exception("All fields are required");
            }

            $this->postModel->updateMessage($id, $name, $subject, $message);
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to update post: " . $e->getMessage());
        }
    }

    public function deletePost($id)
    {
        try {
            if (empty($id)) {
                throw new Exception("Invalid post ID");
            }

            $this->postModel->deleteMessage($id);
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to delete post: " . $e->getMessage());
        }
    }

    public function uploadFile($messageId, $file)
    {
        try {
            if (empty($messageId) || empty($file)) {
                throw new Exception("Invalid parameters for file upload");
            }

            $this->postModel->updateMessageFile($messageId, $file);
            return true;
        } catch (Exception $e) {
            throw new Exception("Failed to upload file: " . $e->getMessage());
        }
    }

    public function getPosts($page = 1, $perPage = 10)
    {
        try {
            return $this->postModel->getMessages($page, $perPage);
        } catch (Exception $e) {
            throw new Exception("Failed to get posts: " . $e->getMessage());
        }
    }

    public function getPostById($id)
    {
        try {
            return $this->postModel->getMessageById($id);
        } catch (Exception $e) {
            throw new Exception("Failed to get post: " . $e->getMessage());
        }
    }

    public function getTotalPosts()
    {
        try {
            return $this->postModel->getTotalMessages();
        } catch (Exception $e) {
            throw new Exception("Failed to get total posts: " . $e->getMessage());
        }
    }

    public function getTodayPostsCount()
    {
        try {
            return $this->postModel->getTodayMessagesCount();
        } catch (Exception $e) {
            throw new Exception("Failed to get today's posts count: " . $e->getMessage());
        }
    }
}