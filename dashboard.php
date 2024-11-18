
<?php
session_start();

// Database configuration
$host = 'image-db.cp0wuao8ky1o.ap-south-1.rds.amazonaws.com';
$db = 'user_db';
$user = 'root';
$pass = 'pass1234';

// Create a new mysqli instance
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html"); // Redirect to login page if not logged in
    exit;
}

$user_id = $_SESSION['user_id'];

// Include AWS SDK and initialize S3 client
require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

$s3Client = new S3Client([
    'region'  => 'ap-south-1', // Your AWS region
    'version' => 'latest'
]);

$bucketName = 'prachi2210'; // Your S3 bucket name

// Handle image deletion
if (isset($_POST['delete_image'])) {
    $image_url = $_POST['image_url'];

    // Delete the image from the database
    $stmt = $conn->prepare("DELETE FROM user_images WHERE user_id = ? AND image_url = ?");
    $stmt->bind_param("is", $user_id, $image_url);
    $stmt->execute();
    $stmt->close();

    // Extract the S3 object key from the image URL
    $parsedUrl = parse_url($image_url);
    $objectKey = ltrim($parsedUrl['path'], '/'); // Remove leading slash from path
    $decodedObjectKey = urldecode($objectKey);
    error_log("Object Key: " . $decodedObjectKey);
    
    // Delete the image from S3
    try {
        $result = $s3Client->deleteObject([
            'Bucket' => $bucketName,
            'Key'    => $decodedObjectKey,
        ]);

        if ($result['@metadata']['statusCode'] === 204) {
            echo "Image deleted successfully from S3.";
        } else {
            echo "Unexpected status code from S3: " . $result['@metadata']['statusCode'];
        }
    } catch (AwsException $e) {
        echo "Error deleting image from S3: " . $e->getMessage();
    }
}

// Query to get user images
$stmt = $conn->prepare("SELECT image_url FROM user_images WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->store_result();

$stmt->bind_result($image_url);

// Fetch images
$images = [];
while ($stmt->fetch()) {
    $images[] = $image_url;
}

// Close the database connection
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        h1 {
            color: #333;
        }
        .welcome-message {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            text-align: center;
        }
.images {
    display: grid; /* Use grid layout */
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); /* Adjust column size */
    gap: 15px; /* Space between items */
    justify-content: center; /* Center the grid horizontally */
}

.images div {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background-color: #fff; /* Add a background for each image block */
    padding: 10px;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); /* Add some shadow */
}

.images img {
    width: 100%; /* Make the image fill its container */
    height: auto;
    border-radius: 5px;
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
    cursor: pointer;
}

.delete-button {
    margin-top: 10px; /* Add space between the image and button */
    width: 100%; /* Make button full width */
    background-color: #dc3545;
    color: white;
    border: none;
    padding: 10px;
    border-radius: 5px;
    cursor: pointer;
    text-align: center;
}

.delete-button:hover {
    background-color: #c82333;
}
        a {
            display: inline-block;
            padding: 10px 15px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }
        a:hover {
            background-color: #0056b3;
        }
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.9);
            padding-top: 60px;
        }
        .modal-content {
            margin: auto;
            display: block;
            width: 80%;
            max-width: 700px;
        }
        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #fff;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
        }
        .close:hover,
        .close:focus {
            color: #bbb;
            text-decoration: none;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="welcome-message">
        <h1>Welcome to Your Dashboard!</h1>
        <p>You are now logged in.</p>
        <a href="upload.html">Upload an Image</a>
        <a href="logout.php">Logout</a>
    </div>

    <h2>Your Uploaded Images:</h2>
    <div class="images">
        <?php if (count($images) > 0): ?>
            <?php foreach ($images as $image_url): ?>
                <div>
                    <img src="<?php echo htmlspecialchars($image_url); ?>" alt="User Image">
                    <form method="post" action="dashboard.php">
                        <input type="hidden" name="image_url" value="<?php echo htmlspecialchars($image_url); ?>">
                        <button type="submit" name="delete_image" class="delete-button">Delete</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No images uploaded yet.</p>
        <?php endif; ?>
    </div>
</body>
</html>
