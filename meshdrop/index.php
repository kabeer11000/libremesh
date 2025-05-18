<!DOCTYPE html>
<html>
<head>
    <title>LibreMesh File Upload Client</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; }
        form { margin-top: 20px; padding: 20px; border: 1px solid #ccc; max-width: 500px; }
        input[type="file"] { margin-bottom: 10px; }
        .message { margin-top: 15px; padding: 10px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .info { background-color: #ffeeba; color: #856404; border-color: #ffc107; }
    </style>
</head>
<body>

    <h1>Upload File to LibreMesh</h1>

    <p>Select a file below to upload it to the decentralized LibreMesh network via a single node.</p>

    <form action="process_upload.php" method="post" enctype="multipart/form-data">
        <label for="fileToUpload">Select file to upload:</label>
        <br>
        <input type="file" name="file_upload" id="fileToUpload">
        <br>
        <input type="submit" value="Upload File" name="submit">
    </form>

</body>
</html>