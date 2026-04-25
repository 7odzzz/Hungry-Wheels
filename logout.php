<?php
session_start();
session_destroy();
header("Location: /HungryWheels/login.php");
exit();
?>