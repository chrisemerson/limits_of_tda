<?php
spl_autoload_register(
    function($FQClassName) {
        $classParts = explode("\\", $FQClassName);
        $className = array_pop($classParts);

        $filename = __DIR__ . "/src/" . $className . ".php";

        if (file_exists($filename)) {
            require_once $filename;
        }
    }
);
