<?php
$messageError = null;
$fileError = null;

try {
    // Get the database connection
    $conn = connection();

    // Instantiate the Controller
    $controller = new Controller($conn);

    // Handle message submission (modal form)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['subject'], $_POST['message'])) {
        $name = trim($_POST['name']);
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);

        $controller->addPost($name, $subject, $message);
        header("Location: index.php?page=" . (isset($_GET['page']) ? (int) $_GET['page'] : 1));
        exit;
    }

    // Handle post update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_message'], $_POST['id'])) {
        $id = (int) $_POST['id'];
        $name = trim($_POST['name']);
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);

        $controller->updatePost($id, $name, $subject, $message);
        header("Location: index.php?page=" . (isset($_GET['page']) ? (int) $_GET['page'] : 1));
        exit;
    }

    // Handle post deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_message'], $_POST['id'])) {
        $id = (int) $_POST['id'];

        $controller->deletePost($id);
        header("Location: index.php?page=" . (isset($_GET['page']) ? (int) $_GET['page'] : 1));
        exit;
    }

    // Handle file upload (table form)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message_id'], $_FILES['uploaded_file'])) {
        $messageId = (int) $_POST['message_id'];
        $file = $_FILES['uploaded_file'];

        $controller->uploadFile($messageId, $file);
        header("Location: index.php?page=" . (isset($_GET['page']) ? (int) $_GET['page'] : 1));
        exit;
    }

    // Handle file removal
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_file'], $_POST['message_id'])) {
        $messageId = (int) $_POST['message_id'];
        $controller->removeFile($messageId);
        header("Location: index.php?page=" . (isset($_GET['page']) ? (int) $_GET['page'] : 1));
        exit;
    }

    // Handle bulk deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_selected'], $_POST['selected_messages'])) {
        $selectedMessages = array_map('intval', $_POST['selected_messages']);

        try {
            $controller->deleteMultiplePosts($selectedMessages);
            $_SESSION['success_message'] = count($selectedMessages) . " message(s) deleted successfully";
            header("Location: index.php?page=" . (isset($_GET['page']) ? (int) $_GET['page'] : 1));
            exit;
        } catch (Exception $e) {
            $messageError = "Failed to delete selected messages: " . $e->getMessage();
        }
    }

    // Get pagination parameters
    $perPage = 10;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int) $_GET['page'] : 1;

    // Get the messages for the current page
    $messages = $controller->getPosts($page, $perPage);

    // Get the total number of messages
    $totalMessages = $controller->getTotalPosts();

    // Get the number of messages added today
    $todayMessages = $controller->getTodayPostsCount();

    // Calculate total pages
    $totalPages = ceil($totalMessages / $perPage);

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
    if (str_contains($errorMessage, 'add post') || str_contains($errorMessage, 'Insert failed')) {
        $messageError = "Unable to save message. Please try again.";
    } elseif (str_contains($errorMessage, 'update post') || str_contains($errorMessage, 'Update failed')) {
        $messageError = "Unable to update message. Please try again.";
    } elseif (str_contains($errorMessage, 'delete post') || str_contains($errorMessage, 'Delete failed')) {
        $messageError = "Unable to delete message. Please try again.";
    } elseif (str_contains($errorMessage, 'upload file') || str_contains($errorMessage, 'File')) {
        $fileError = htmlspecialchars($errorMessage);
    } else {
        $messageError = "Unexpected server error. Please try again.";
    }
    // Log the error for debugging
    error_log("[" . date('Y-m-d H:i:s') . "] " . $errorMessage, 3, "errors.log");
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #f2f2f2;
        }

        .pagination {
            margin-top: 20px;
        }

        .pagination a {
            margin: 0 5px;
            text-decoration: none;
            padding: 5px 10px;
            border: 1px solid #ddd;
        }

        .pagination a.active {
            background-color: #007bff;
            color: white;
        }

        .pagination a:hover {
            background-color: #f2f2f2;
        }

        .pagination a.disabled {
            color: #ccc;
            pointer-events: none;
        }

        .stats {
            margin-bottom: 20px;
        }

        .overlay {
            background-color: #00000090;
            position: fixed;
            height: 100vh;
            width: 100%;
            top: 0;
            left: 0;
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 20px;
            background-color: white;
            width: 50%;
            max-width: 500px;
            border-radius: 5px;
        }

        .modal label {
            font-weight: bold;
        }

        .modal input,
        .modal textarea {
            width: 100%;
            padding: 5px;
            margin-top: 5px;
        }

        .modal button {
            padding: 8px 15px;
            margin-right: 10px;
            cursor: pointer;
        }

        .error {
            color: red;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .file-upload-form {
            display: flex;
            gap: 5px;
        }

        .file-upload-form input[type="file"] {
            padding: 3px;
        }

        .file-upload-form input[type="submit"] {
            padding: 5px 10px;
        }

        .view-overlay {
            background-color: #00000090;
            position: fixed;
            height: 100vh;
            width: 100%;
            top: 0;
            left: 0;
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .view-modal {
            display: flex;
            flex-direction: column;
            gap: 20px;
            padding: 30px;
            background-color: white;
            width: 60%;
            max-width: 600px;
            border-radius: 5px;
        }

        .message-content {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .message-content p {
            margin: 0;
        }

        .message-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .message-actions button {
            padding: 8px 15px;
            cursor: pointer;
        }

        .edit-btn {
            background-color: #ffc107;
            border: none;
            color: white;
        }

        .delete-btn {
            background-color: #dc3545;
            border: none;
            color: white;
        }

        .close-view {
            background-color: #6c757d;
            border: none;
            color: white;
        }

        .file-management {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .file-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        .change-file-btn,
        .remove-file-btn,
        .upload-btn,
        .cancel-upload {
            padding: 5px 10px;
            cursor: pointer;
            border: none;
            border-radius: 3px;
        }

        .change-file-btn {
            background-color: #17a2b8;
            color: white;
        }

        .remove-file-btn {
            background-color: #dc3545;
            color: white;
        }

        .upload-btn {
            background-color: #28a745;
            color: white;
        }

        .cancel-upload {
            background-color: #6c757d;
            color: white;
        }

        #file-upload-form {
            margin-top: 10px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
    </style>
</head>

<body>
    <!-- Display post counts -->
    <?php if (isset($totalMessages, $todayMessages)): ?>
        <div class="stats">
            <p>Posts added today: <?php echo htmlspecialchars($todayMessages); ?></p>
            <p>Total posts: <?php echo htmlspecialchars($totalMessages); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="success"><?php echo htmlspecialchars($_SESSION['success_message']); ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- Display message submission errors -->
    <?php if (isset($messageError)): ?>
        <p class="error"><?php echo htmlspecialchars($messageError); ?></p>
    <?php endif; ?>

    <!-- In your file upload form section -->
    <?php if (isset($fileError)): ?>
        <p class="error"><?php echo htmlspecialchars($fileError); ?></p>

    <?php endif; ?>

    <div class="bulk-actions">
        <button class="addpost">Write a post</button>
        <button id="delete-selected" class="delete-selected-btn">Delete Selected</button>
    </div>
    <!-- Display messages table -->
    <?php if (isset($messages)): ?>
        <table>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>Name</th>
                <th>Subject</th>
                <th>Message</th>
                <th>File</th>
                <th>Date</th>
                <th>action</th>
            </tr>
            <?php
            if (empty($messages)) {
                echo "<tr><td colspan='5'>No messages found.</td></tr>";
            } else {
                foreach ($messages as $message) {
                    echo "<tr>";
                    echo "<td><input type='checkbox' class='message-checkbox' name='selected_messages[]' value='" . htmlspecialchars($message['id']) . "'></td>";
                    echo "<td>" . htmlspecialchars($message['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($message['subject']) . "</td>";
                    echo "<td>" . htmlspecialchars(substr($message['message'], 0, 50)) . (strlen($message['message']) > 50 ? '...' : '') . "</td>";
                    echo "<td>";
                    if ($message['uploaded']) {
                        $fileName = basename($message['uploaded']);
                        echo "<a href='" . htmlspecialchars($message['uploaded']) . "' download>" . htmlspecialchars($fileName) . "</a>";
                    } else {
                        echo "<form action='' method='post' enctype='multipart/form-data' class='file-upload-form'>";
                        echo "<input type='hidden' name='message_id' value='" . htmlspecialchars($message['id']) . "'>";
                        echo "<input type='file' name='uploaded_file' required>";
                        echo "<input type='submit' value='Upload'>";
                        if (isset($fileError) && isset($_POST['message_id']) && $_POST['message_id'] == $message['id']) {
                            echo "<p class='error'>" . htmlspecialchars($fileError) . "</p>";
                        }
                        echo "</form>";
                    }
                    echo "</td>";
                    echo "<td>" . htmlspecialchars($message['date']) . "</td>";
                    echo "<td><button class='view-btn' data-id='" . htmlspecialchars($message['id']) . "'>View</button></td>";
                    echo "</tr>";
                }
            }
            ?>
        </table>

        <!-- Pagination Links (only if more than 10 posts) -->
        <?php if ($totalMessages > 10): ?>
            <div class="pagination">
                <?php
                $prevPage = $page - 1;
                echo $page > 1
                    ? "<a href='?page=$prevPage'>Previous</a>"
                    : "<a class='disabled'>Previous</a>";

                for ($i = 1; $i <= $totalPages; $i++) {
                    $activeClass = $i === $page ? "class='active'" : "";
                    echo "<a href='?page=$i' $activeClass>$i</a>";
                }

                $nextPage = $page + 1;
                echo $page < $totalPages
                    ? "<a href='?page=$nextPage'>Next</a>"
                    : "<a class='disabled'>Next</a>";
                ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- overlay for adding and edit a post -->
    <div class="overlay">
        <div class="modal">
            <?php if (isset($messageError) && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['message_id'])): ?>
                <p class="error"><?php echo $messageError; ?></p>
            <?php endif; ?>
            <form action="" method="POST" id="message-form">
                <input type="hidden" name="id" id="form-id">
                <input type="hidden" name="edit_message" id="form-edit-flag" value="0">

                <label for="name">Name</label>
                <input type="text" name="name" id="name" required>

                <label for="subject">Subject</label>
                <input type="text" name="subject" id="subject" required>

                <label for="message">Message</label>
                <textarea name="message" id="message"></textarea>

                <div>
                    <button type="submit" id="form-submit-btn">Submit</button>
                    <button type="button" class="close-modal">Close</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Message Overlay -->
    <div class="view-overlay">
        <div class="view-modal">
            <h2>Message Details</h2>
            <div class="message-content">
                <p><strong>Name:</strong> <span id="view-name"></span></p>
                <p><strong>Subject:</strong> <span id="view-subject"></span></p>
                <p><strong>Message:</strong> <span id="view-message"></span></p>
                <p><strong>Date:</strong> <span id="view-date"></span></p>
                <div class="file-management">
                    <p><strong>File:</strong> <span id="view-file"></span></p>
                    <form id="file-upload-form" method="POST" enctype="multipart/form-data" style="display: none;">
                        <input type="hidden" name="message_id" id="upload-message-id">
                        <input type="file" name="uploaded_file" id="new-uploaded-file" required>
                        <button type="submit" class="upload-btn">Upload</button>
                        <button type="button" class="cancel-upload">Cancel</button>
                    </form>
                    <div class="file-actions" id="file-actions">
                        <button class="change-file-btn">Change File</button>
                        <form method="POST" class="remove-file-form">
                            <input type="hidden" name="message_id" id="remove-message-id">
                            <input type="hidden" name="remove_file" value="1">
                            <button type="submit" class="remove-file-btn">Remove File</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="message-actions">
                <button class="edit-btn">Edit</button>
                <form id="delete-form" method="POST" style="display: inline;">
                    <input type="hidden" name="id" id="delete-id">
                    <input type="hidden" name="delete_message" value="1">
                    <button type="submit" class="delete-btn">Delete</button>
                </form>
                <button class="close-view">Close</button>
            </div>
        </div>
    </div>
    <script>
        const addButton = document.querySelector('.addpost');
        const overlay = document.querySelector('.overlay');
        const editButton = document.querySelector('.edit-btn');
        const closeButton = document.querySelector('.close-modal');
        const changeFileBtn = document.querySelector('.change-file-btn');
        const cancelUploadBtn = document.querySelector('.cancel-upload');
        const fileUploadForm = document.getElementById('file-upload-form');
        const fileActions = document.getElementById('file-actions');
        const removeFileForm = document.querySelector('.remove-file-form');
        const selectAllCheckbox = document.getElementById('select-all');
        const messageCheckboxes = document.querySelectorAll('.message-checkbox');
        const deleteSelectedBtn = document.getElementById('delete-selected');

        // Select all functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                const isChecked = e.target.checked;
                messageCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
            });
        }
        // Delete selected functionality
        if (deleteSelectedBtn) {
            deleteSelectedBtn.addEventListener('click', () => {
                const selectedMessages = Array.from(document.querySelectorAll('.message-checkbox:checked'))
                    .map(checkbox => checkbox.value);

                if (selectedMessages.length === 0) {
                    alert('Please select at least one message to delete');
                    return;
                }

                if (confirm(`Are you sure you want to delete ${selectedMessages.length} selected message(s)?`)) {
                    // Create a form and submit it
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';

                    selectedMessages.forEach(id => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'selected_messages[]';
                        input.value = id;
                        form.appendChild(input);
                    });

                    const deleteInput = document.createElement('input');
                    deleteInput.type = 'hidden';
                    deleteInput.name = 'delete_selected';
                    deleteInput.value = '1';
                    form.appendChild(deleteInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        if (changeFileBtn) {
            changeFileBtn.addEventListener('click', () => {
                fileUploadForm.style.display = 'flex';
                fileActions.style.display = 'none';
            });
        }

        if (cancelUploadBtn) {
            cancelUploadBtn.addEventListener('click', () => {
                fileUploadForm.style.display = 'none';
                fileActions.style.display = 'flex';
                document.getElementById('new-uploaded-file').value = '';
            });
        }
        if (editButton) {
            editButton.addEventListener('click', () => {
                const messageId = viewOverlay.getAttribute('data-current-id');
                const message = messagesData.find(msg => msg.id == messageId);

                if (message) {
                    // Populate the form
                    document.getElementById('form-id').value = message.id;
                    document.getElementById('name').value = message.name;
                    document.getElementById('subject').value = message.subject;
                    document.getElementById('message').value = message.message;
                    document.getElementById('form-edit-flag').value = "1";
                    document.getElementById('form-submit-btn').textContent = "Update";

                    // Show the modal
                    viewOverlay.style.display = 'none';
                    overlay.style.display = 'flex';
                }
            });
        }

        // Reset form when opening for adding
        if (addButton) {
            addButton.addEventListener('click', () => {
                document.getElementById('message-form').reset();
                document.getElementById('form-edit-flag').value = "0";
                document.getElementById('form-submit-btn').textContent = "Submit";
                overlay.style.display = 'flex';
            });
        }

        // Reset form when closing
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                overlay.style.display = 'none';
                document.getElementById('message-form').reset();
                document.getElementById('form-edit-flag').value = "0";
                document.getElementById('form-submit-btn').textContent = "Submit";
            });
        }

        if (overlay) {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.style.display = 'none';
                    document.querySelector('form').reset();
                }
            });
        }

        <?php if (isset($messageError) && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['message_id'])): ?>
            overlay.style.display = 'flex';
        <?php endif; ?>

        const viewButtons = document.querySelectorAll('.view-btn');
        const viewOverlay = document.querySelector('.view-overlay');
        const closeViewButton = document.querySelector('.close-view');
        const deleteButton = document.querySelector('.delete-btn');

        // Store the messages data in a JavaScript variable
        const messagesData = <?php echo json_encode($messages); ?>;

        // View button click handler
        if (viewButtons) {
            viewButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const messageId = button.getAttribute('data-id');
                    const message = messagesData.find(msg => msg.id == messageId);

                    if (message) {
                        document.getElementById('view-name').textContent = message.name;
                        document.getElementById('view-subject').textContent = message.subject;
                        document.getElementById('view-message').textContent = message.message;
                        document.getElementById('view-date').textContent = message.date;
                        document.getElementById('delete-id').value = messageId;
                        document.getElementById('upload-message-id').value = messageId;
                        document.getElementById('remove-message-id').value = messageId;
                        const fileElement = document.getElementById('view-file');
                        if (message.uploaded) {
                            const fileName = message.uploaded.split('/').pop();
                            fileElement.innerHTML = `<a href="${message.uploaded}" download>${fileName}</a>`;
                            fileActions.style.display = 'flex';
                            fileUploadForm.style.display = 'none';
                        } else {
                            fileElement.textContent = 'No file uploaded';
                            fileActions.style.display = 'none';
                            fileUploadForm.style.display = 'flex';
                        }

                        // Store the current message ID for edit/delete
                        viewOverlay.setAttribute('data-current-id', messageId);
                        viewOverlay.style.display = 'flex';
                    }
                });
            });
        }

        // Close view overlay
        if (closeViewButton) {
            closeViewButton.addEventListener('click', () => {
                viewOverlay.style.display = 'none';
            });
        }

        // Edit button functionality

        // Delete button functionality
        if (deleteButton) {
            deleteButton.addEventListener('click', () => {
                const messageId = viewOverlay.getAttribute('data-current-id');
                if (confirm('Are you sure you want to delete this message?')) {
                    // Send delete request (you'll need to implement this)
                    // For now, we'll just show an alert
                    alert(`Delete message with ID: ${messageId}`);
                    viewOverlay.style.display = 'none';
                    // Reload the page to see changes
                    window.location.reload();
                }
            });
        }

        // Close view overlay when clicking outside
        if (viewOverlay) {
            viewOverlay.addEventListener('click', (e) => {
                if (e.target === viewOverlay) {
                    viewOverlay.style.display = 'none';
                }
            });
        }
    </script>
</body>

</html>