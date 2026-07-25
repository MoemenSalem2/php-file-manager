<?php
// Configuration
$uploadDir = 'downloads/';
$extractDir = 'extracted/';
$allowedExtensions = ['zip', 'rar', 'tar', 'gz', '7z'];
$maxFileSize = 500 * 1024 * 1024; // 500MB

// Create directories if they don't exist
if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
if (!file_exists($extractDir)) mkdir($extractDir, 0755, true);

// Initialize variables
$message = '';
$success = false;
$downloadedFile = '';
$extractedFiles = [];
$selectedFormats = [];
$currentDir = isset($_GET['dir']) ? $_GET['dir'] : '';
$fileManagerFiles = [];

// Get user-selected formats for extraction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extract_formats'])) {
    $selectedFormats = $_POST['extract_formats'];
}

// Handle file/folder deletion
if (isset($_GET['delete'])) {
    $fileToDelete = $_GET['delete'];
    $fullPath = urldecode($fileToDelete);
    $realPath = realpath($fullPath);
    $allowedBasePaths = [realpath($uploadDir), realpath($extractDir)];
    
    // Security check: prevent directory traversal
    $isAllowed = false;
    foreach ($allowedBasePaths as $basePath) {
        if ($realPath && strpos($realPath, $basePath) === 0) $isAllowed = true;
    }
    if (!$realPath || !$isAllowed) {
        $message = 'Error: Invalid path.';
    } else {
        if (file_exists($realPath)) {
            if (is_dir($realPath)) {
                if (deleteDirectory($realPath)) {
                    $message = 'Directory deleted successfully.';
                    $success = true;
                } else {
                    $message = 'Error: Could not delete directory.';
                }
            } else {
                if (unlink($realPath)) {
                    $message = 'File deleted successfully.';
                    $success = true;
                } else {
                    $message = 'Error: Could not delete file.';
                }
            }
        } else {
            $message = 'Error: File or directory does not exist.';
        }
    }
}

// Handle file extraction from file manager
if (isset($_GET['extract'])) {
    $fileToExtract = urldecode($_GET['extract']);
    $realPath = realpath($fileToExtract);
    $allowedBasePaths = [realpath($uploadDir), realpath($extractDir)];
    
    // Security check: prevent directory traversal
    $isAllowed = false;
    foreach ($allowedBasePaths as $basePath) {
        if ($realPath && strpos($realPath, $basePath) === 0) $isAllowed = true;
    }
    if (!$realPath || !$isAllowed) {
        $message = 'Error: Invalid path.';
    } else {
        if (file_exists($realPath)) {
            $fileExtension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
            
            if (in_array($fileExtension, $allowedExtensions)) {
                $fileName = pathinfo($realPath, PATHINFO_FILENAME);
                $extractPath = $extractDir . $fileName . '/';
                
                // Extract based on file type
                $extractionSuccess = false;
                
                switch ($fileExtension) {
                    case 'zip':
                        $extractionSuccess = extractZip($realPath, $extractPath);
                        break;
                    case 'rar':
                        $extractionSuccess = extractRar($realPath, $extractPath);
                        break;
                    case 'tar':
                    case 'gz':
                        $extractionSuccess = extractTar($realPath, $extractPath);
                        break;
                    case '7z':
                        $extractionSuccess = extract7z($realPath, $extractPath);
                        break;
                    default:
                        $message = 'Archive format not supported for extraction.';
                        break;
                }
                
                if ($extractionSuccess) {
                    $message = 'File extracted successfully to: ' . $extractPath;
                    $success = true;
                    $extractedFiles = scanDirectory($extractPath);
                } else {
                    $message = 'Failed to extract file.';
                }
            } else {
                $message = 'File format not supported for extraction.';
            }
        } else {
            $message = 'Error: File does not exist.';
        }
    }
}

// Handle folder zipping
if (isset($_GET['zip'])) {
    $folderToZip = urldecode($_GET['zip']);
    $realPath = realpath($folderToZip);
    $allowedBasePaths = [realpath($uploadDir), realpath($extractDir)];
    
    // Security check: prevent directory traversal
    $isAllowed = false;
    foreach ($allowedBasePaths as $basePath) {
        if ($realPath && strpos($realPath, $basePath) === 0) $isAllowed = true;
    }
    if (!$realPath || !$isAllowed) {
        $message = 'Error: Invalid path.';
    } else {
        if (file_exists($realPath) && is_dir($realPath)) {
            $folderName = basename($realPath);
            $zipFileName = $folderName . '.zip';
            $zipFilePath = $uploadDir . $zipFileName;
            
            // Check if a zip file with the same name already exists and create a unique name
            $counter = 1;
            while (file_exists($zipFilePath)) {
                $zipFileName = $folderName . '_' . $counter . '.zip';
                $zipFilePath = $uploadDir . $zipFileName;
                $counter++;
            }
            
            if (zipFolder($realPath, $zipFilePath)) {
                $message = 'Folder zipped successfully: ' . $zipFileName;
                $success = true;
            } else {
                $message = 'Failed to zip folder.';
            }
        } else {
            $message = 'Error: Folder does not exist.';
        }
    }
}

// Handle file download and extraction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    // Validate URL
    $url = filter_var($_POST['url'], FILTER_SANITIZE_URL);
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $message = 'Invalid URL provided.';
    } else {
        // Get file name from URL
        $fileName = basename(parse_url($url, PHP_URL_PATH));
        if (empty($fileName)) {
            $fileName = 'downloaded_file_' . time();
        }
        
        $targetPath = $uploadDir . $fileName;
        
        try {
            // Download the file
            set_time_limit(0);
            $fileContent = file_get_contents($url);
            
            if ($fileContent === false) {
                throw new Exception('Failed to download file from URL.');
            }
            
            // Check file size
            if (strlen($fileContent) > $maxFileSize) {
                throw new Exception('File size exceeds maximum limit of 500MB.');
            }
            
            // Save file
            if (file_put_contents($targetPath, $fileContent) === false) {
                throw new Exception('Failed to save file to server.');
            }
            
            $downloadedFile = $targetPath;
            $success = true;
            $message = 'File downloaded successfully: ' . $fileName;
            
            // Check if extraction is requested and file is an archive
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if (isset($_POST['extract']) && in_array($fileExtension, $allowedExtensions)) {
                // Check if this format is selected for extraction
                if (in_array($fileExtension, $selectedFormats)) {
                    $message .= ' Extracting file...';
                    
                    // Extract based on file type
                    $extractionSuccess = false;
                    
                    switch ($fileExtension) {
                        case 'zip':
                            $extractionSuccess = extractZip($targetPath, $extractDir . pathinfo($fileName, PATHINFO_FILENAME) . '/');
                            break;
                        case 'rar':
                            $extractionSuccess = extractRar($targetPath, $extractDir . pathinfo($fileName, PATHINFO_FILENAME) . '/');
                            break;
                        case 'tar':
                        case 'gz':
                            $extractionSuccess = extractTar($targetPath, $extractDir . pathinfo($fileName, PATHINFO_FILENAME) . '/');
                            break;
                        case '7z':
                            $extractionSuccess = extract7z($targetPath, $extractDir . pathinfo($fileName, PATHINFO_FILENAME) . '/');
                            break;
                        default:
                            $message .= ' Archive format not supported for extraction.';
                            break;
                    }
                    
                    if ($extractionSuccess) {
                        $message .= ' File extracted successfully.';
                        $extractedFiles = scanDirectory($extractDir . pathinfo($fileName, PATHINFO_FILENAME) . '/');
                    } else {
                        $message .= ' Failed to extract file.';
                    }
                } else {
                    $message .= ' Extraction skipped for this format (not selected).';
                }
            }
            
        } catch (Exception $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

// Get files for file manager
$fileManagerFiles = scanDirectoryWithInfo($currentDir ?: '.');

// Utility functions
function extractZip($file, $extractPath) {
    $zip = new ZipArchive();
    if ($zip->open($file) === TRUE) {
        if (!file_exists($extractPath)) mkdir($extractPath, 0755, true);
        $zip->extractTo($extractPath);
        $zip->close();
        return true;
    }
    return false;
}

function extractRar($file, $extractPath) {
    if (!class_exists('RarArchive')) {
        return false; // Rar extension not available
    }
    
    $rar = RarArchive::open($file);
    if ($rar !== FALSE) {
        if (!file_exists($extractPath)) mkdir($extractPath, 0755, true);
        
        $entries = $rar->getEntries();
        foreach ($entries as $entry) {
            $entry->extract($extractPath);
        }
        $rar->close();
        return true;
    }
    return false;
}

function extractTar($file, $extractPath) {
    try {
        $phar = new PharData($file);
        if (!file_exists($extractPath)) mkdir($extractPath, 0755, true);
        $phar->extractTo($extractPath);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function extract7z($file, $extractPath) {
    // Requires 7z command line tool installed on server
    if (!function_exists('shell_exec')) {
        return false;
    }
    
    if (!file_exists($extractPath)) mkdir($extractPath, 0755, true);
    
    $command = "7z x " . escapeshellarg($file) . " -o" . escapeshellarg($extractPath) . " -y";
    $output = shell_exec($command);
    
    return !empty($output);
}

function zipFolder($source, $destination) {
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }

    $source = realpath($source);
    
    if (is_dir($source)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            // Skip directories (they would be added automatically)
            if (!$file->isDir()) {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($source) + 1);

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }
    } else if (is_file($source)) {
        $zip->addFile($source, basename($source));
    }

    return $zip->close();
}

function scanDirectory($dir) {
    $files = [];
    if (is_dir($dir)) {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item != '.' && $item != '..') {
                $files[] = $item;
            }
        }
    }
    return $files;
}

function scanDirectoryWithInfo($dir) {
    $files = [];
    if (is_dir($dir)) {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item != '.' && $item != '..') {
                $fullPath = $dir . '/' . $item;
                $files[] = [
                    'name' => $item,
                    'path' => $fullPath,
                    'is_dir' => is_dir($fullPath),
                    'size' => is_dir($fullPath) ? '' : formatSize(filesize($fullPath)),
                    'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
                    'extension' => strtolower(pathinfo($fullPath, PATHINFO_EXTENSION))
                ];
            }
        }
    }
    return $files;
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Downloader, Extractor and Manager</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        .container {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1, h2, h3 {
            color: #2c3e50;
        }
        h1 {
            text-align: center;
            margin-bottom: 25px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #34495e;
        }
        input[type="url"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .checkbox-group {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border: 1px solid #e9ecef;
        }
        .format-options {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 10px;
        }
        .format-option {
            display: flex;
            align-items: center;
            background: #e8f4fc;
            padding: 8px 15px;
            border-radius: 20px;
            border: 1px solid #b8daff;
        }
        .checkbox {
            margin-right: 8px;
            transform: scale(1.2);
        }
        button {
            background-color: #3498db;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        button:hover {
            background-color: #2980b9;
        }
        .btn-extract {
            background-color: #2ecc71;
        }
        .btn-extract:hover {
            background-color: #27ae60;
        }
        .btn-delete {
            background-color: #e74c3c;
        }
        .btn-delete:hover {
            background-color: #c0392b;
        }
        .btn-zip {
            background-color: #9b59b6;
        }
        .btn-zip:hover {
            background-color: #8e44ad;
        }
        .message {
            padding: 15px;
            margin: 20px 0;
            border-radius: 6px;
            font-weight: bold;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .file-list {
            margin-top: 25px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        .file-list h3 {
            margin-top: 0;
            color: #2c3e50;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
        }
        .file-list ul {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }
        .file-list li {
            padding: 10px;
            background-color: white;
            margin-bottom: 8px;
            border-radius: 4px;
            border-left: 4px solid #3498db;
            display: flex;
            align-items: center;
        }
        .file-list li:before {
            content: "📄";
            margin-right: 10px;
            font-size: 18px;
        }
        .info-text {
            font-size: 14px;
            color: #6c757d;
            margin-top: 5px;
        }
        
        /* File Manager Styles */
        .file-manager {
            margin-top: 30px;
        }
        .file-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .file-table th, .file-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .file-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .file-table tr:hover {
            background-color: #f9f9f9;
        }
        .file-icon {
            margin-right: 8px;
        }
        .breadcrumb {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .breadcrumb a {
            color: #3498db;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .tab-container {
            margin-bottom: 20px;
        }
        .tab-buttons {
            display: flex;
            border-bottom: 1px solid #ddd;
        }
        .tab-button {
            padding: 10px 20px;
            background: #f1f1f1;
            border: none;
            cursor: pointer;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
        }
        .tab-button.active {
            background: #3498db;
            color: white;
        }
        .tab-content {
            display: none;
            padding: 20px;
            border: 1px solid #ddd;
            border-top: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <h1>File Downloader, Extractor and Manager</h1>
    
    <div class="tab-container">
        <div class="tab-buttons">
            <button class="tab-button active" onclick="openTab('download-tab')">Download & Extract</button>
            <button class="tab-button" onclick="openTab('filemanager-tab')">File Manager</button>
        </div>
        
        <div id="download-tab" class="tab-content active">
            <div class="container">
                <form method="POST">
                    <div class="form-group">
                        <label for="url">File URL:</label>
                        <input type="url" id="url" name="url" placeholder="https://example.com/file.zip" required>
                    </div>
                    
                    <div class="checkbox-group">
                        <div>
                            <input type="checkbox" id="extract" name="extract" class="checkbox" checked>
                            <label for="extract" style="display: inline;">Extract after download</label>
                        </div>
                        
                        <div class="info-text">Select which archive formats to extract:</div>
                        
                        <div class="format-options">
                            <div class="format-option">
                                <input type="checkbox" id="format-zip" name="extract_formats[]" value="zip" class="checkbox" <?php echo in_array('zip', $selectedFormats) || empty($selectedFormats) ? 'checked' : ''; ?>>
                                <label for="format-zip" style="display: inline;">ZIP</label>
                            </div>
                            
                            <div class="format-option">
                                <input type="checkbox" id="format-rar" name="extract_formats[]" value="rar" class="checkbox" <?php echo in_array('rar', $selectedFormats) || empty($selectedFormats) ? 'checked' : ''; ?>>
                                <label for="format-rar" style="display: inline;">RAR</label>
                            </div>
                            
                            <div class="format-option">
                                <input type="checkbox" id="format-7z" name="extract_formats[]" value="7z" class="checkbox" <?php echo in_array('7z', $selectedFormats) || empty($selectedFormats) ? 'checked' : ''; ?>>
                                <label for="format-7z" style="display: inline;">7Z</label>
                            </div>
                            
                            <div class="format-option">
                                <input type="checkbox" id="format-tar" name="extract_formats[]" value="tar" class="checkbox" <?php echo in_array('tar', $selectedFormats) || empty($selectedFormats) ? 'checked' : ''; ?>>
                                <label for="format-tar" style="display: inline;">TAR</label>
                            </div>
                            
                            <div class="format-option">
                                <input type="checkbox" id="format-gz" name="extract_formats[]" value="gz" class="checkbox" <?php echo in_array('gz', $selectedFormats) || empty($selectedFormats) ? 'checked' : ''; ?>>
                                <label for="format-gz" style="display: inline;">GZ</label>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit">Download File</button>
                </form>
                
                <?php if (!empty($message)): ?>
                    <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($extractedFiles)): ?>
                    <div class="file-list">
                        <h3>Extracted Files:</h3>
                        <ul>
                            <?php foreach ($extractedFiles as $file): ?>
                                <li><?php echo htmlspecialchars($file); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div id="filemanager-tab" class="tab-content">
            <div class="container">
                <h2>File Manager</h2>
                
                <div class="breadcrumb">
                    <a href="?">Root</a> 
                    <?php 
                    if ($currentDir) {
                        $parts = explode('/', $currentDir);
                        $currentPath = '';
                        foreach ($parts as $part) {
                            if ($part) {
                                $currentPath .= '/' . $part;
                                echo ' / <a href="?dir=' . urlencode($currentPath) . '">' . htmlspecialchars($part) . '</a>';
                            }
                        }
                    }
                    ?>
                </div>
                
                <table class="file-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Size</th>
                            <th>Modified</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($currentDir)): ?>
                        <tr>
                            <td>
                                <span class="file-icon">📁</span>
                                <a href="?dir=<?php echo urlencode(dirname($currentDir)); ?>">..</a>
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php foreach ($fileManagerFiles as $file): ?>
                        <tr>
                            <td>
                                <span class="file-icon"><?php echo $file['is_dir'] ? '📁' : '📄'; ?></span>
                                <?php if ($file['is_dir']): ?>
                                    <a href="?dir=<?php echo urlencode($file['path']); ?>"><?php echo htmlspecialchars($file['name']); ?></a>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($file['name']); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $file['size']; ?></td>
                            <td><?php echo $file['modified']; ?></td>
                            <td class="action-buttons">
                                <?php if (!$file['is_dir']): ?>
                                    <a href="<?php echo $file['path']; ?>" download>
                                        <button>Download</button>
                                    </a>
                                    <?php 
                                    $allowed_extensions = ['zip', 'rar', 'tar', 'gz', '7z'];
                                    if (in_array($file['extension'], $allowed_extensions)): 
                                    ?>
                                        <button class="btn-extract" onclick="confirmExtract('<?php echo urlencode($file['path']); ?>', '<?php echo htmlspecialchars($file['name']); ?>')">Extract</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="btn-zip" onclick="confirmZip('<?php echo urlencode($file['path']); ?>', '<?php echo htmlspecialchars($file['name']); ?>')">Zip</button>
                                <?php endif; ?>
                                <button class="btn-delete" onclick="confirmDelete('<?php echo urlencode($file['path']); ?>', '<?php echo htmlspecialchars($file['name']); ?>')">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Show/hide format options based on extract checkbox
        document.getElementById('extract').addEventListener('change', function() {
            const formatOptions = document.querySelector('.format-options');
            const formatCheckboxes = document.querySelectorAll('input[name="extract_formats[]"]');
            
            if (this.checked) {
                formatOptions.style.opacity = '1';
                formatCheckboxes.forEach(checkbox => {
                    checkbox.disabled = false;
                });
            } else {
                formatOptions.style.opacity = '0.6';
                formatCheckboxes.forEach(checkbox => {
                    checkbox.disabled = true;
                });
            }
        });

        // Initial check on page load
        document.addEventListener('DOMContentLoaded', function() {
            const extractCheckbox = document.getElementById('extract');
            const formatOptions = document.querySelector('.format-options');
            const formatCheckboxes = document.querySelectorAll('input[name="extract_formats[]"]');
            
            if (!extractCheckbox.checked) {
                formatOptions.style.opacity = '0.6';
                formatCheckboxes.forEach(checkbox => {
                    checkbox.disabled = true;
                });
            }
        });

        // Tab navigation
        function openTab(tabId) {
            // Hide all tabs
            const tabs = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            
            // Update tab buttons
            const tabButtons = document.getElementsByClassName('tab-button');
            for (let i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }
            
            event.currentTarget.classList.add('active');
        }

        // Delete confirmation
        function confirmDelete(filePath, fileName) {
            if (confirm('Are you sure you want to delete "' + fileName + '"?')) {
                window.location.href = '?delete=' + filePath + '&dir=<?php echo urlencode($currentDir); ?>';
            }
        }

        // Extract confirmation
        function confirmExtract(filePath, fileName) {
            if (confirm('Are you sure you want to extract "' + fileName + '"?')) {
                window.location.href = '?extract=' + filePath + '&dir=<?php echo urlencode($currentDir); ?>';
            }
        }

        // Zip confirmation
        function confirmZip(folderPath, folderName) {
            if (confirm('Are you sure you want to zip the folder "' + folderName + '"?')) {
                window.location.href = '?zip=' + folderPath + '&dir=<?php echo urlencode($currentDir); ?>';
            }
        }
    </script>
</body>
</html>