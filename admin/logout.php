<?php
// admin/logout.php
session_start();
session_destroy();
header('Location: /panacea/admin/login.php');
exit;;
