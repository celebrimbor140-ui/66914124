<?php
// Check if the form was submitted and file uploaded
if (isset($_FILES['fileToUpload']) && $_FILES['fileToUpload']['error'] === 0) {
    $fileType = pathinfo($_FILES['fileToUpload']['name'], PATHINFO_EXTENSION);

    // Only allow .txt files
    if (strtolower($fileType) === "txt") {
        $fileContent = file_get_contents($_FILES['fileToUpload']['tmp_name']);
        echo "<h3>File Content:</h3>";
        echo nl2br(htmlspecialchars($fileContent));
    } else {
        echo "<p style='color:red;'>Only .txt files are allowed.</p>";
    }
}
?>

<?php
class Artist {
    private static $idCounter = 0;
    private $id;
    private $name;

    public function __construct($name) {
        self::$idCounter++;
        $this->id = self::$idCounter;
        $this->name = $name;
    }

    public function changeName($newName) {
        $this->name = $newName;
    }

    public function getID() {
        return $this->id;
    }

    public function __toString() {
        return $this->id . ": " . $this->name;
    }
}

// Step 1: Create an array of five Artist objects
$artists = [
    new Artist("Sizwe Moeketsi (Reason)"),
    new Artist("Ndivhudzannyi Ralivhona (Makhadzi)"),
    new Artist("Kgaogelo Moagi (Master KG)"),
    new Artist("Reece Madlisa"),
    new Artist("Focalistic")
];

// Step 2: Write string representation to a file
$fileName = "artists.txt";
$fileHandle = fopen($fileName, "w");
foreach ($artists as $artist) {
    fwrite($fileHandle, $artist . PHP_EOL);
}
fclose($fileHandle);

// Step 3: Read and display the file content
echo "<h3>File Content:</h3>";
echo nl2br(file_get_contents($fileName));

// Step 4: Read file and recreate Artist objects
echo "<h3>Recreated Artist Objects:</h3>";
$lines = file($fileName, FILE_IGNORE_NEW_LINES);
$newArtists = [];
foreach ($lines as $line) {
    // Extract the artist name after the colon
    $parts = explode(": ", $line, 2);
    if (count($parts) == 2) {
        $newArtists[] = new Artist($parts[1]);
    }
}

// Display recreated objects
foreach ($newArtists as $artist) {
    echo $artist . "<br>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload and Display File</title>
</head>
<body>
    <h2>Upload a .txt File</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="fileToUpload" required>
        <br><br>
        <input type="submit" value="Upload & Display">
    </form>
</body>
</html>