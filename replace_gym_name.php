<?php
$content = file_get_contents('user/home.php');
$content = str_replace('The New Gym tặng bạn', 'FLEXZONE tặng bạn', $content);
file_put_contents('user/home.php', $content);
echo "Đã thay thế xong!";
