<?php
header("HTTP/1.1 404 Not Found");
if (file_exists(__DIR__ . '/../404.html')) {
    include(__DIR__ . '/../404.html');
} else {
    echo "404 Not Found";
}
exit;
?>
