<?php

namespace App\Libraries;

use Mockery\CountValidator\Exception;

class FileLogger extends Logger {


    protected $log_path;


    public function __construct() {

        $this->log_path = base_path() . "/app/Logs/";

    }

    private function write_log($message, $filePath) {

        $filePath = $this->log_path . $filePath;

        if(!$message) {

            throw new Exception('No message to log!');

        }

        if(!$filePath) {

            throw new Exception('No filepath given!');

        }

        $pathinfo = pathinfo($filePath);
        if(!file_exists($pathinfo['dirname'])) {

            mkdir($pathinfo['dirname']);

        }

        if (!$fp = fopen($filePath, "a+")) {

            throw new Exception('Could not open file source!');

        }

        $msg = $message . "\n";

        flock($fp, LOCK_EX);
        fwrite($fp, $msg);
        flock($fp, LOCK_UN);
        fclose($fp);

        return TRUE;

    }

    public function log($message, $filePath = NULL) {


        try {

            $this->write_log($message, $filePath);

        } catch(Exception $ex) {

            echo $ex->getMessage();
            exit;

        }


    }

}