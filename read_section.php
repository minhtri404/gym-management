<?php
$lines = file('user/home.php');
for ($i = 433; $i <= 455; $i++) {
    echo ($i+1) . ': ' . $lines[$i];
}
