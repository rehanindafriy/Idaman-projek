<?php
echo 'Imagick loaded? ';
var_dump(extension_loaded('imagick'));

echo "<br>Imagick version: ";
if (class_exists('Imagick')) {
    $obj = new Imagick();
    echo $obj->getVersion()['versionString'] ?? 'unknown';
} else {
    echo 'Imagick class not found';
}

echo "<br><br>phpinfo (ringkas):<br>";
phpinfo(INFO_MODULES);
