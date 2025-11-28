<?php
/**
 * File Handler Class for Prime Cargo Limited
 * Handles secure file uploads, storage, and management
 */

class FileHandler {
    private $uploadPath;
    private $tempPath;
    private $maxSize;
    private $allowedTypes;
    private $errors = [];
    
    public function __construct() {
        $this->uploadPath = UPLOAD_PATH;
        $this->tempPath = UPLOAD_TEMP_PATH;
        $this->maxSize = UPLOAD_MAX_SIZE;
        $this->allowedTypes = UPLOAD_ALLOWED_TYPES;
        
        // Create upload directories if they don't exist
        $this->createDirectories();
    }
    
    /**
     * Create necessary directories
     */
    private function createDirectories() {
        $directories = [
            $this->uploadPath,
            $this->tempPath,
            $this->uploadPath . 'documents/',
            $this->uploadPath . 'documents/shipments/',
            $this->uploadPath . 'documents/verification/',
            $this->uploadPath . 'documents/payments/'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    $this->errors[] = "Failed to create directory: $dir";
                }
            }
        }
    }
    
    /**
     * Upload a file
     * @param array $file $_FILES array element
     * @param string $category Document category (shipments, verification, payments)
     * @param int $recordId Related record ID
     * @return array|false Upload result or false on failure
     */
    public function uploadFile($file, $category = 'general', $recordId = null) {
        // Reset errors
        $this->errors = [];
        
        // Validate file
        if (!$this->validateFile($file)) {
            return false;
        }
        
        // Generate unique filename
        $filename = $this->generateUniqueFilename($file['name']);
        
        // Determine upload path
        $uploadDir = $this->uploadPath . 'documents/' . $category . '/';
        if ($recordId) {
            $uploadDir .= $recordId . '/';
        }
        
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                $this->errors[] = "Failed to create upload directory";
                return false;
            }
        }
        
        $filepath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Set proper permissions
            chmod($filepath, 0644);
            
            return [
                'original_name' => $file['name'],
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => $file['size'],
                'type' => $file['type'],
                'extension' => pathinfo($file['name'], PATHINFO_EXTENSION),
                'upload_time' => date('Y-m-d H:i:s')
            ];
        } else {
            $this->errors[] = "Failed to move uploaded file";
            return false;
        }
    }
    
    /**
     * Validate uploaded file
     * @param array $file $_FILES array element
     * @return bool
     */
    private function validateFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[] = $this->getUploadErrorMessage($file['error']);
            return false;
        }
        
        // Check file size
        if ($file['size'] > $this->maxSize) {
            $this->errors[] = "File size exceeds maximum limit of " . $this->formatFileSize($this->maxSize);
            return false;
        }
        
        // Check file type
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes)) {
            $this->errors[] = "File type not allowed. Allowed types: " . implode(', ', $this->allowedTypes);
            return false;
        }
        
        // Additional security checks
        if (!$this->isFileSafe($file)) {
            $this->errors[] = "File appears to be unsafe";
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if file is safe
     * @param array $file $_FILES array element
     * @return bool
     */
    private function isFileSafe($file) {
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // Define safe MIME types
        $safeMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];
        
        if (!in_array($mimeType, $safeMimeTypes)) {
            return false;
        }
        
        // Check file content for suspicious patterns
        $content = file_get_contents($file['tmp_name']);
        $suspiciousPatterns = [
            '<?php',
            '<script',
            'javascript:',
            'vbscript:',
            'onload=',
            'onerror='
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (stripos($content, $pattern) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate unique filename
     * @param string $originalName Original filename
     * @return string
     */
    private function generateUniqueFilename($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Clean basename
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        $basename = substr($basename, 0, 50); // Limit length
        
        $filename = $basename . '_' . uniqid() . '.' . $extension;
        
        return $filename;
    }
    
    /**
     * Delete file
     * @param string $filepath File path to delete
     * @return bool
     */
    public function deleteFile($filepath) {
        if (file_exists($filepath) && is_file($filepath)) {
            if (unlink($filepath)) {
                // Remove empty directories
                $this->removeEmptyDirectories(dirname($filepath));
                return true;
            }
        }
        return false;
    }
    
    /**
     * Remove empty directories
     * @param string $dir Directory path
     */
    private function removeEmptyDirectories($dir) {
        while ($dir !== $this->uploadPath && is_dir($dir)) {
            if (count(scandir($dir)) <= 2) { // Only . and ..
                rmdir($dir);
                $dir = dirname($dir);
            } else {
                break;
            }
        }
    }
    
    /**
     * Get file information
     * @param string $filepath File path
     * @return array|false File info or false if not found
     */
    public function getFileInfo($filepath) {
        if (file_exists($filepath) && is_file($filepath)) {
            $stat = stat($filepath);
            return [
                'size' => $stat['size'],
                'modified' => date('Y-m-d H:i:s', $stat['mtime']),
                'permissions' => substr(sprintf('%o', $stat['mode']), -4),
                'readable' => is_readable($filepath),
                'writable' => is_writable($filepath)
            ];
        }
        return false;
    }
    
    /**
     * Download file securely
     * @param string $filepath File path
     * @param string $originalName Original filename for download
     * @return bool
     */
    public function downloadFile($filepath, $originalName = null) {
        if (!file_exists($filepath) || !is_file($filepath)) {
            return false;
        }
        
        // Security check - ensure file is within upload directory
        $realPath = realpath($filepath);
        $uploadRealPath = realpath($this->uploadPath);
        
        if (strpos($realPath, $uploadRealPath) !== 0) {
            return false;
        }
        
        // Set headers for download
        $filename = $originalName ?: basename($filepath);
        $filesize = filesize($filepath);
        $mimeType = mime_content_type($filepath);
        
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $filesize);
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // Output file
        readfile($filepath);
        return true;
    }
    
    /**
     * Get upload error message
     * @param int $errorCode Upload error code
     * @return string
     */
    private function getUploadErrorMessage($errorCode) {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return "File exceeds upload_max_filesize directive";
            case UPLOAD_ERR_FORM_SIZE:
                return "File exceeds MAX_FILE_SIZE directive";
            case UPLOAD_ERR_PARTIAL:
                return "File was only partially uploaded";
            case UPLOAD_ERR_NO_FILE:
                return "No file was uploaded";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Missing temporary folder";
            case UPLOAD_ERR_CANT_WRITE:
                return "Failed to write file to disk";
            case UPLOAD_ERR_EXTENSION:
                return "File upload stopped by extension";
            default:
                return "Unknown upload error";
        }
    }
    
    /**
     * Format file size
     * @param int $bytes File size in bytes
     * @return string
     */
    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Get errors
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get last error
     * @return string|null
     */
    public function getLastError() {
        return end($this->errors) ?: null;
    }
    
    /**
     * Clear errors
     */
    public function clearErrors() {
        $this->errors = [];
    }
    
    /**
     * Check if file exists
     * @param string $filepath File path
     * @return bool
     */
    public function fileExists($filepath) {
        return file_exists($filepath) && is_file($filepath);
    }
    
    /**
     * Get file size
     * @param string $filepath File path
     * @return int|false File size or false if not found
     */
    public function getFileSize($filepath) {
        if ($this->fileExists($filepath)) {
            return filesize($filepath);
        }
        return false;
    }
    
    /**
     * Get file extension
     * @param string $filename Filename
     * @return string
     */
    public function getFileExtension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    /**
     * Is file type allowed
     * @param string $filename Filename
     * @return bool
     */
    public function isFileTypeAllowed($filename) {
        $extension = $this->getFileExtension($filename);
        return in_array($extension, $this->allowedTypes);
    }
}
?>
