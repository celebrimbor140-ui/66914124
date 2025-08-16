<!DOCTYPE html>
<html>
<head>
  <title>Task 1</title>
</head>
<body>

<?php include 'menu.inc'; ?>

<?php

echo "<h3>Part (a): Table Descriptions and Keys</h3>";

echo "<p><strong>Artist</strong> – Represents a music artist or band. 
Primary Key: ArtistID. No foreign keys.</p>";

echo "<p><strong>Album</strong> – Represents an album released by an artist. 
Primary Key: AlbumID. Foreign Key: ArtistID (references Artist table).</p>";

echo "<p><strong>Track</strong> – Represents a single track on an album. 
Primary Key: TrackID. Foreign Key: AlbumID (references Album table).</p>";

echo "<h3>Part (b): Proposed ERD for Lecturers, Modules, and Roles</h3>";
echo "<p>The database will include the entities: Lecturer, Module, Role, and Lecturer_Module (junction table). 
Lecturer_Module contains composite primary keys (LecturerID, ModuleID, RoleID) and foreign keys linking to the other tables.</p>";

echo "<img src='task1erd.png' alt='ERD Diagram for Lecturers, Modules, and Roles' style='max-width:600px;'>";
?>

</body>
</html>
