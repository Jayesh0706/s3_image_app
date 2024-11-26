<?php
require 'vendor/autoload.php'; // Make sure to have the AWS SDK installed via Composer
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Start the session
session_start();

// Ensure the user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("User is not logged in. Please log in to upload images.");
}

// AWS S3 Configuration
$s3 = new S3Client([
    'region' => 'ap-south-1', // Replace with your region
    'version' => 'latest'
]);

// Define CloudFront base URL
$cloudFrontUrl = 'http://d1ykmz6hv57j6a.cloudfront.net'; // Replace with your CloudFront URL

// Database connection
$conn = new mysqli('database-1.cp0wuao8ky1o.ap-south-1.rds.amazonaws.com', 'admin', 'prachi11', 'user_db');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if files were uploaded
if (isset($_FILES['images'])) {
    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
            echo "Error uploading file: " . $_FILES['images']['error'][$key];
            continue; // Skip to the next file
        }

        $fileName = basename($_FILES['images']['name'][$key]);
        $filePath = "uploads/" . $fileName;

        try {
            // Upload the image to S3
            $result = $s3->putObject([
                'Bucket' => 'prachi2210', // Your S3 bucket name
                'Key' => $filePath,
                'SourceFile' => $tmp_name,
                'ACL' => 'public-read',
            ]);

            // Generate the CloudFront URL
            $cloudFrontImageUrl = $cloudFrontUrl . '/' . $filePath;

            // Debugging output
            echo "Uploaded to CloudFront: $cloudFrontImageUrl<br>";

            // Store the CloudFront URL in RDS
            $stmt = $conn->prepare("INSERT INTO user_images (user_id, image_url) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $cloudFrontImageUrl);
            if ($stmt->execute()) {
                // Redirect to the dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                echo "Error storing image URL: " . $stmt->error . "<br>";
            }
        } catch (AwsException $e) {
            echo "Error uploading image: " . $e->getMessage() . "<br>";
        }
    }
} else {
    echo "No images uploaded.";
}

// Close the database connection
$conn->close();
?>
