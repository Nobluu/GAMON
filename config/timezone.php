<?php
// Timezone Configuration
// Set default timezone untuk Indonesia (WIB - Western Indonesia Time)
date_default_timezone_set('Asia/Jakarta');

// Alternative timezone options for Indonesia:
// 'Asia/Jakarta'  - WIB (UTC+7) - Java, Sumatra
// 'Asia/Makassar' - WITA (UTC+8) - Sulawesi, Bali, Nusa Tenggara  
// 'Asia/Jayapura' - WIT (UTC+9) - Papua, Maluku

// Function to get current timezone info
function getTimezoneInfo() {
    return [
        'timezone' => date_default_timezone_get(),
        'offset' => date('P'),
        'current_time' => date('Y-m-d H:i:s'),
        'formatted_time' => date('l, d F Y - H:i:s')
    ];
}

// Function to convert UTC to local time
function utcToLocal($utc_datetime) {
    $utc = new DateTime($utc_datetime, new DateTimeZone('UTC'));
    $utc->setTimezone(new DateTimeZone(date_default_timezone_get()));
    return $utc->format('Y-m-d H:i:s');
}

// Function to convert local time to UTC
function localToUtc($local_datetime) {
    $local = new DateTime($local_datetime, new DateTimeZone(date_default_timezone_get()));
    $local->setTimezone(new DateTimeZone('UTC'));
    return $local->format('Y-m-d H:i:s');
}
?>