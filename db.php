<?php
$conn = mysqli_connect("localhost", "root", "", "mtau_bank");
if (!$conn) {
    die("<div style='color:white; background:#9d174d; padding:15px; font-weight:bold; font-family:sans-serif;'>Database Connection Failure: " . mysqli_connect_error() . "</div>");
}
?>