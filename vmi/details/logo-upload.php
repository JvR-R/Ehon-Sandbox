<?php
// Enable error reporting for development; remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// 1. Determine your upload path
//    If you want to store outside webroot, adjust $root_dir accordingly
$root_dir   = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
$target_dir = $root_dir . '/vmi/images/';

// 2. Get and sanitize $companyId from POST
//    If you store $companyId in a session, use that (just make sure it's valid).
$companyId = $_POST['companyId'] ?? 'default';
// allow only alphanumeric + underscores for safety
$companyId = preg_replace('/[^a-zA-Z0-9_]/', '', $companyId);

// 3. Build your file name
$target_file = $target_dir . 'company_' . $companyId . '.png';

// 4. Handle "checkExistence" request
if (isset($_POST['checkExistence']) && $_POST['checkExistence'] === 'true') {
    echo json_encode(['exists' => file_exists($target_file)]);
    exit;
}

// 5. Actual upload logic
//    Check if user wants to overwrite
$overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true';

// If the file already exists and overwrite is false, respond accordingly
if (file_exists($target_file) && !$overwrite) {
    echo json_encode([
        'uploaded' => false,
        'message'  => 'File already exists and overwrite not allowed.'
    ]);
    exit;
}

// 6. Validate uploaded file
if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'uploaded' => false,
        'message'  => 'No file uploaded or an error occurred.'
    ]);
    exit;
}

// 7. Check file size (limit to 2 MB for this example)
$maxFileSize = 2 * 1024 * 1024; // 2 MB
if ($_FILES['logo']['size'] > $maxFileSize) {
    echo json_encode([
        'uploaded' => false,
        'message'  => 'File size exceeds the maximum limit of 2 MB.'
    ]);
    exit;
}

// 8. Validate MIME type
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($_FILES['logo']['tmp_name']);

// Only allow JPG, PNG, GIF
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode([
        'uploaded' => false,
        'message'  => 'Unsupported file type. Must be JPG, PNG, or GIF.'
    ]);
    exit;
}

// 9. Convert to PNG if you really need PNG output specifically.
//    Otherwise, you can just move_uploaded_file() to $target_file.
$imageString = file_get_contents($_FILES['logo']['tmp_name']);
if ($img = @imagecreatefromstring($imageString)) {
    // Attempt to save as PNG
    imagesavealpha($img, true);
    if (imagepng($img, $target_file)) {
        imagedestroy($img); // Free resources
        echo json_encode(['uploaded' => true, 'message' => 'Upload successful!']);
    } else {
        imagedestroy($img); // Free resources
        echo json_encode([
            'uploaded' => false,
            'message'  => 'Error processing image as PNG.'
        ]);
    }
} else {
    // Fallback or error
    // If you prefer just to move the file (and keep its original extension), you could do:
    /*
    $finalPath = $target_dir . basename($_FILES['logo']['name']);
    if (move_uploaded_file($_FILES['logo']['tmp_name'], $finalPath)) {
        echo json_encode(['uploaded' => true, 'message' => 'File uploaded!']);
    } else {
        echo json_encode(['uploaded' => false, 'message' => 'Error moving uploaded file.']);
    }
    */
    echo json_encode([
        'uploaded' => false,
        'message'  => 'Error creating image from uploaded file.'
    ]);
}
exit;
