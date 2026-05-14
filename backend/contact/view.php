<?php include("../config/db.php"); ?>

<!DOCTYPE html>
<html>
<head>
    <title>View Data</title>
</head>
<body>

<h2>Saved Data</h2>

<table border="1" cellpadding="10">
<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Email</th>
    <th>Message</th>
</tr>

<?php
while($row = $result->fetch_assoc()) {
    echo "<tr>
        <td>".$row['id']."</td>
        <td>".$row['name']."</td>
        <td>".$row['email']."</td>
        <td>".$row['message']."</td>
    </tr>";
}
?>

</table>

</body>
</html>
