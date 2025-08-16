<?php
// Database connection
$servername = "localhost";
$username = "root"; // Your MySQL username
$password = ""; // Your MySQL password
$dbname = "music_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch Artist data
$artistQuery = "SELECT * FROM Artist";
$artistResult = $conn->query($artistQuery);

// Fetch Album data
$albumQuery = "SELECT * FROM Album";
$albumResult = $conn->query($albumQuery);

// Fetch Track data
$trackQuery = "SELECT * FROM Track";
$trackResult = $conn->query($trackQuery);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Music Database</title>
</head>
<body>
    <h1>Music Database Tables</h1>

    <!-- Display Artist Table -->
    <h2>Artist Table</h2>
    <table border="1">
        <tr>
            <th>ArtistID</th>
            <th>ArtistName</th>
        </tr>
        <?php while ($row = $artistResult->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['ArtistID']; ?></td>
                <td><?php echo $row['ArtistName']; ?></td>
            </tr>
        <?php } ?>
    </table>

    <!-- Display Album Table -->
    <h2>Album Table</h2>
    <table border="1">
        <tr>
            <th>AlbumID</th>
            <th>ArtistID</th>
            <th>AlbumName</th>
            <th>ReleaseDate</th>
        </tr>
        <?php while ($row = $albumResult->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['AlbumID']; ?></td>
                <td><?php echo $row['ArtistID']; ?></td>
                <td><?php echo $row['AlbumName']; ?></td>
                <td><?php echo $row['ReleaseDate']; ?></td>
            </tr>
        <?php } ?>
    </table>

    <!-- Display Track Table -->
    <h2>Track Table</h2>
    <table border="1">
        <tr>
            <th>TrackID</th>
            <th>AlbumID</th>
            <th>TrackName</th>
            <th>TrackDuration</th>
        </tr>
        <?php while ($row = $trackResult->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['TrackID']; ?></td>
                <td><?php echo $row['AlbumID']; ?></td>
                <td><?php echo $row['TrackName']; ?></td>
                <td><?php echo $row['TrackDuration']; ?></td>
            </tr>
        <?php } ?>
    </table>

    <!-- Form for SELECT queries -->
    <h2>Select Queries</h2>
    <form method="post">
        <input type="radio" name="query" value="1" /> ORDER BY
        <input type="radio" name="query" value="2" /> LIKE
        <input type="radio" name="query" value="3" /> INNER JOIN
        <input type="radio" name="query" value="4" /> WHERE OR
        <input type="radio" name="query" value="5" /> COUNT
        <input type="submit" value="Execute Query" />
    </form>

    <?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if query is set in the POST request
    if (isset($_POST['query'])) {
        $query = $_POST['query'];

        switch ($query) {
            case 1:
                $result = $conn->query("SELECT * FROM Album ORDER BY ReleaseDate DESC");
                break;
            case 2:
                $result = $conn->query("SELECT * FROM Track WHERE TrackName LIKE '%Rhapsody%'");
                break;
            case 3:
                $result = $conn->query("SELECT Artist.ArtistName, Album.AlbumName FROM Artist INNER JOIN Album ON Artist.ArtistID = Album.ArtistID");
                break;
            case 4:
                $result = $conn->query("SELECT * FROM Track WHERE TrackDuration > '00:04:00' OR TrackName LIKE '%Bohemian%'");
                break;
            case 5:
                $result = $conn->query("SELECT COUNT(*) AS TrackCount FROM Track");
                break;
            default:
                $result = null;
                break;
        }

        // Check if the result was returned successfully
        if ($result && $result->num_rows > 0) {
            echo "<table border='1'><tr>";

            // Use fetch_fields() to get the field names
            $fields = $result->fetch_fields(); // Fetch the field objects
            foreach ($fields as $field) {
                echo "<th>{$field->name}</th>"; // Access the name property correctly
            }

            echo "</tr>";

            // Fetch and display the result rows
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>{$value}</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "No results found for the selected query.";
        }
    } else {
        echo "Please select a query to execute.";
    }
}
?>

</body>
</html>

<?php
// Close connection
$conn->close();
?>