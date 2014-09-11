<?php
spl_autoload_register(
    function($FQClassName) {
        if ($FQClassName == 'Breadshop\OutboundEventsMock') {
            require_once __DIR__ . "/test/OutboundEventsMock.php";
        } else {
            $classParts = explode("\\", $FQClassName);
            $className = array_pop($classParts);

            $filename = __DIR__ . "/src/" . $className . ".php";

            if (file_exists($filename)) {
                require_once $filename;
            }
        }
    }
);
