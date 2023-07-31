<?php

$sitepath = substr($_SERVER['PHP_SELF'], 0, strrpos($_SERVER['PHP_SELF'], '/'));
$sitepath = htmlspecialchars('http://' . $_SERVER['HTTP_HOST'] . $sitepath);

header('location:'.$sitepath.'/web/index.php');