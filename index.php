<?php

require_once(__DIR__.'/vendor/autoload.php');
require_once('util.php');

date_default_timezone_set('UTC');

$game_id = '2'; // Starcraft: Brood War

$events = scrape_tl_events(get_3_months(), $game_id);
$cal = generate_calendar($events);
output_calendar($cal);

exit;