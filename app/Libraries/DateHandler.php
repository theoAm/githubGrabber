<?php

namespace App\Libraries;

class DateHandler {

    public static function iso8601ToUtc($time_8601) {

        $search = array('T', 'Z');
        $replace = array(' ', '');
        $utc = str_replace($search, $replace, $time_8601);
        return $utc;

    }

}