<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.html");
    exit;
}
$user_id = $_SESSION['id'];
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file_name = basename($_FILES['file']['name']);
    $description = trim($_POST['description'] ?? '');
    $file_data = file_get_contents($_FILES['file']['tmp_name']);
    $conn = new mysqli('localhost', 'root', '', 'studytogether_db');
    $stmt = $conn->prepare('INSERT INTO uploads (user_id, file_name, description, file_data) VALUES (?, ?, ?, ?)');
    $null = NULL;
    $stmt->bind_param('issb', $user_id, $file_name, $description, $null);
    $stmt->send_long_data(3, $file_data);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    $message = 'File uploaded successfully!';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File - studytogether</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="upload.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="dashboard-page">
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        <main class="dashboard-main">
            <h1>Upload Your File</h1>
            <div class="upload-description">Share your notes or resources with the community. Add a description to help others find your upload!</div>
            <div id="upload-message"></div>
            <div class="upload-main-area">
                <form method="POST" enctype="multipart/form-data" id="uploadForm" autocomplete="off">
                    <div class="upload-drop-area" id="dropArea">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <div class="upload-drop-text">drag and drop your file</div>
                        <div class="upload-drop-or">Or</div>
                        <label class="browse-btn">
                            Browse file
                            <input type="file" name="file" id="fileInput" style="display:none;">
                        </label>
                    </div>
                    <div id="selectedFileName" class="upload-selected-file"></div>
                    <input type="text" name="description" id="descriptionInput" placeholder="Enter a short description..." maxlength="255" class="upload-description-input">
                    <button type="submit" class="upload-form-btn">Upload</button>
                    <div class="upload-progress-bar-ajax" id="progressBarContainer" style="display:none;">
                        <div class="upload-progress-bar-inner-ajax" id="progressBar"></div>
                    </div>
                    <div class="upload-progress-label-ajax" id="progressLabel" style="display:none;">0%</div>
                </form>
            </div>
        </main>
    </div>
    <script>
    // Drag and drop highlight
    const dropArea = document.getElementById('dropArea');
    const fileInput = document.getElementById('fileInput');
    const uploadForm = document.getElementById('uploadForm');
    const progressBar = document.getElementById('progressBar');
    const progressBarContainer = document.getElementById('progressBarContainer');
    const progressLabel = document.getElementById('progressLabel');
    const uploadMessage = document.getElementById('upload-message');
    const selectedFileName = document.getElementById('selectedFileName');
    dropArea.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropArea.style.background = '#e3f2fd';
    });
    dropArea.addEventListener('dragleave', (e) => {
        dropArea.style.background = '#f8fbff';
    });
    function updateFileName() {
        if (fileInput.files && fileInput.files[0]) {
            selectedFileName.textContent = fileInput.files[0].name;
        } else {
            selectedFileName.textContent = '';
        }
    }
    fileInput.addEventListener('change', updateFileName);
    dropArea.addEventListener('drop', (e) => {
        e.preventDefault();
        dropArea.style.background = '#f8fbff';
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            updateFileName();
        }
    });
    // AJAX upload
    uploadForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const file = fileInput.files[0];
        const description = document.getElementById('descriptionInput').value;
        if (!file) {
            uploadMessage.innerHTML = '<div style="color:red;">Please select a file to upload.</div>';
            return;
        }
        const formData = new FormData();
        formData.append('file', file);
        formData.append('description', description);
        progressBarContainer.style.display = 'block';
        progressLabel.style.display = 'inline-block';
        progressBar.style.width = '0%';
        progressLabel.textContent = '0%';
        uploadMessage.innerHTML = '';
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'upload_ajax.php', true);
        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = percent + '%';
                progressLabel.textContent = percent + '%';
            }
        };
        xhr.onload = function() {
            if (xhr.status === 200) {
                uploadMessage.innerHTML = '<div style="color:green;">' + JSON.parse(xhr.responseText).message + '</div>';
                progressBar.style.width = '100%';
                progressLabel.textContent = '100%';
                setTimeout(() => {
                    progressBarContainer.style.display = 'none';
                    progressLabel.style.display = 'none';
                    uploadForm.reset();
                    fileInput.value = '';
                    selectedFileName.textContent = '';
                    location.reload(); // reload to update file list
                }, 1200);
            } else {
                uploadMessage.innerHTML = '<div style="color:red;">Upload failed.</div>';
            }
        };
        xhr.onerror = function() {
            uploadMessage.innerHTML = '<div style="color:red;">Upload failed.</div>';
        };
        xhr.send(formData);
    });
    </script>
</body>
</html> 