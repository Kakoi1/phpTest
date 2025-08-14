<?php
class Messages
{
    private $conn;

    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    public function getMessages($page = 1, $perPage = 10)
    {
        // Calculate the offset
        $offset = ($page - 1) * $perPage;

        // Prepare the SQL query with LIMIT and OFFSET
        $sql = "SELECT * FROM messages ORDER BY date DESC LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $perPage, $offset);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        // Check if query was successful
        if (!$result) {
            die("Query Failed: " . mysqli_error($this->conn));
        }

        // Fetch all rows into an array
        $messages = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }

        mysqli_stmt_close($stmt);

        return $messages;
    }

    public function getTotalMessages()
    {
        $sql = "SELECT COUNT(*) as total FROM messages";
        $result = mysqli_query($this->conn, $sql);

        if (!$result) {
            die("Query Failed: " . mysqli_error($this->conn));
        }

        $row = mysqli_fetch_assoc($result);
        mysqli_free_result($result);
        return $row['total'];
    }

    public function getTodayMessagesCount()
    {
        // Use today's date (2025-08-13) based on system-provided date
        $today = '2025-08-13';
        $sql = "SELECT COUNT(*) as today_count FROM messages WHERE DATE(date) = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $today);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (!$result) {
            die("Query Failed: " . mysqli_error($this->conn));
        }

        $row = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);
        return $row['today_count'];
    }
    public function addMessage($name, $subject, $message)
    {
        $sql = "INSERT INTO messages (name, subject, message, date) VALUES (?, ?, ?, NOW())";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($this->conn));
        }
        mysqli_stmt_bind_param($stmt, "sss", $name, $subject, $message);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Insert failed: " . mysqli_error($this->conn));
        }

        mysqli_stmt_close($stmt);
        return true;
    }
    public function updateMessageFile($messageId, $file)
    {
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . $file['error']);
        }

        // Check file size (e.g., 5MB max)
        if ($file['size'] > 2 * 1024 * 1024) {
            throw new Exception("File size exceeds 5MB limit");
        }

        // Validate file type (example: allow only certain extensions)
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowedExtensions));
        }

        // Create uploads directory if it doesn't exist
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename to prevent overwrites
        $filename = uniqid() . '_' . basename($file['name']);
        $targetPath = $uploadDir . $filename;

        // Move the uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Failed to move uploaded file");
        }

        // Update the database
        $sql = "UPDATE messages SET uploaded = ? WHERE id = ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        if (!$stmt) {
            // Clean up the uploaded file if database update fails
            unlink($targetPath);
            throw new Exception("Prepare failed: " . mysqli_error($this->conn));
        }
        mysqli_stmt_bind_param($stmt, "si", $targetPath, $messageId);
        if (!mysqli_stmt_execute($stmt)) {
            // Clean up the uploaded file if database update fails
            unlink($targetPath);
            throw new Exception("Update failed: " . mysqli_error($this->conn));
        }

        mysqli_stmt_close($stmt);
        return true;
    }
    public function updateMessage($id, $name, $subject, $message)
    {
        try {
            $sql = "UPDATE messages SET name = ?, subject = ?, message = ? WHERE id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);

            if (!$stmt) {
                throw new Exception("Database query preparation failed");
            }

            mysqli_stmt_bind_param($stmt, "sssi", $name, $subject, $message, $id);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Database query execution failed");
            }

            mysqli_stmt_close($stmt);
            return true;

        } catch (Exception $e) {
            error_log("Error in updateMessage: " . $e->getMessage());
            throw new Exception("Failed to update message");
        }
    }
    public function getMessageById($id)
    {
        try {
            $sql = "SELECT * FROM messages WHERE id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);

            if (!$stmt) {
                throw new Exception("Database query preparation failed");
            }

            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if (!$result) {
                throw new Exception("Database query execution failed");
            }

            $message = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            if (!$message) {
                throw new Exception("Message not found");
            }

            return $message;

        } catch (Exception $e) {
            error_log("Error in getMessageById: " . $e->getMessage());
            return null;
        }
    }

    public function deleteMessage($id)
    {
        try {
            // First get the message to check for file
            $message = $this->getMessageById($id);

            if ($message && $message['uploaded']) {
                // Delete the file if it exists
                if (file_exists($message['uploaded'])) {
                    unlink($message['uploaded']);
                }
            }

            $sql = "DELETE FROM messages WHERE id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);

            if (!$stmt) {
                throw new Exception("Database query preparation failed");
            }

            mysqli_stmt_bind_param($stmt, "i", $id);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Database query execution failed");
            }

            mysqli_stmt_close($stmt);
            return true;

        } catch (Exception $e) {
            error_log("Error in deleteMessage: " . $e->getMessage());
            throw new Exception("Failed to delete message");
        }
    }
    public function removeFile($messageId)
    {
        try {
            // First get the message to check for file
            $message = $this->getMessageById($messageId);

            if ($message && $message['uploaded']) {
                // Delete the file if it exists
                if (file_exists($message['uploaded'])) {
                    unlink($message['uploaded']);
                }
            }

            // Update the database to remove file reference
            $sql = "UPDATE messages SET uploaded = NULL WHERE id = ?";
            $stmt = mysqli_prepare($this->conn, $sql);

            if (!$stmt) {
                throw new Exception("Database query preparation failed");
            }

            mysqli_stmt_bind_param($stmt, "i", $messageId);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Database query execution failed");
            }

            mysqli_stmt_close($stmt);
            return true;

        } catch (Exception $e) {
            error_log("Error in removeFile: " . $e->getMessage());
            throw new Exception("Failed to remove file");
        }
    }
}
