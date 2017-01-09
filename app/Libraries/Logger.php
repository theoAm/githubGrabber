<?php

namespace App\Libraries;

use App\Interfaces\Logging;

abstract class Logger implements Logging{

    abstract function log($message, $filePath);

}