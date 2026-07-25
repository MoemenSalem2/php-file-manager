# PHP File Downloader & Manager

A single-file PHP script to download files from a URL, extract archives, and manage files/folders on a web server. It's especially useful for web hosting environments with limited FTP access or file size upload restrictions (like InfinityFree).

 <!-- It's a good idea to add a screenshot of your tool! -->

## Features

- **Download from URL**: Download large files directly to your server.
- **Auto-Extract**: Automatically extract archives after download.
- **Supported Formats**: `zip`, `rar`, `tar`, `gz`, `7z`.
- **File Manager**:
    - Browse server directories.
    - Delete files and folders.
    - Download files to your local machine.
    - Extract existing archives on the server.
    - Compress folders into `.zip` archives.
- **No Database Required**: Runs as a single, self-contained script.

## Installation (for InfinityFree or similar hosting)

This script helps you bypass the 10MB file upload limit on many free hosting services.

1.  **Upload the Script**: Upload the `index.php` file from this repository to your `htdocs` folder using the hosting provider's online File Manager.
2.  **Navigate to the Script**: Open your web browser and go to `your-domain.com/index.php`.
3.  **Directory Creation**: The script will automatically try to create the `downloads/` and `extracted/` directories.
4.  **(If Needed) Set Permissions**: If you encounter permission errors, create the `downloads` and `extracted` folders manually using your hosting's File Manager and ensure they have `0755` permissions.

## Dependencies & Compatibility

The script's functionality depends on the PHP extensions enabled on your server:

- **`.zip`**: Requires the `php-zip` extension (commonly available).
- **`.rar`**: Requires the `php-rar` extension (rarely available on free hosting).
- **`.tar`, `.gz`**: Uses the built-in `PharData` class (commonly available).
- **`.7z`**: Requires `shell_exec` and the `7z` command-line tool to be installed on the server (very rare on shared/free hosting).

If a feature doesn't work, it's likely because the corresponding server extension is not enabled.

---

### ⚠️ Security Warning

This script provides powerful access to your server's file system. Anyone who can access it can download, delete, and manage files.

**It is CRITICAL to protect this script.**

The easiest way to do this is to place it in a password-protected directory. Use your hosting control panel's **"Directory Privacy"** feature to secure the folder where you uploaded `index.php`.

---

## How to Use

### Download & Extract Tab

1.  Paste the direct download link for a file into the URL field.
2.  Check the "Extract after download" box if you are downloading an archive.
3.  Select the archive formats you want the script to handle.
4.  Click "Download File".

### File Manager Tab

- Click the "File Manager" tab to view the contents of the root directory (where `index.php` is located).
- You can navigate into the `downloads/` and `extracted/` folders.
- Action buttons next to each file/folder allow you to **Download**, **Extract**, **Zip**, or **Delete**.