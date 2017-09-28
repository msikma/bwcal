<?php

function get_cal_url($year, $month, $game_id, $json=true) {
  $params = array(
    'view' => 'month',
    'year' => $year,
    'month' => $month,
  );
  if (isset($game_id)) {
    $params['game'] = $game_id;
  }
  if ($json) {
    $params['fmt'] = 'json';
  }
  
  $req = http_build_query($params);
  return "http://www.teamliquid.net/calendar/?{$req}";
}

function get_3_months() {
  $prev_month = strtotime('-1 month');
  $curr_month = strtotime('+0');
  $next_month = strtotime('+1 month');
  return array(
    'prev' => array(date('Y', $prev_month), date('n', $prev_month)),
    'curr' => array(date('Y', $curr_month), date('n', $curr_month)),
    'next' => array(date('Y', $next_month), date('n', $next_month)),
  );
}

function get_tl_event_cache($year, $month, $game_id) {
  $fn = "cache/cache-{$year}-{$month}-id{$game_id}.json";
  // Check if the cache is older than 24 hours.
  if (time() - @filemtime($fn) > (24 * 3600)) {
    return false;
  }
  // Otherwise, return the contents.
  return json_decode(@file_get_contents($fn), true);
}

function save_tl_event_cache($events, $year, $month, $game_id) {
  $fn = "cache/cache-{$year}-{$month}-id{$game_id}.json";
  file_put_contents($fn, json_encode($events));
}

function scrape_tl_events($months, $game_id) {
  // Generate a plain array with event data.
  $events = array();
  
  foreach ($months as $m) {
    $year = $m[0];
    $month = $m[1];
    
    // Check if we have these events in cache.
    $month_events = get_tl_event_cache($year, $month, $game_id);
    if (!empty($month_events)) {
      $events = array_merge($events, $month_events);
      continue;
    }
    
    $month_events = array();
    $url = get_cal_url($year, $month, $game_id);
    $res = json_decode(file_get_contents($url), true);
    $doc = phpQuery::newDocumentHTML($res['html']);
    // Note: ensure we only select items from the current month. Ignore .mo_out.
    $days = $doc['#evcal .evc-l:not(.mo_out)'];
    foreach ($days as $day) {
      // Determine the exact date.
      $day_node = pq($day);
      $day_number_raw = $day_node['& > div > div:first-child']->text();
      preg_match_all('!\d+!', $day_number_raw, $day_number_matches);
      $day_number = $day_number_matches[0][0];
      $date_str = "{$year}-{$month}-{$day_number}";
      $link = get_cal_url($year, $month, $game_id);
      
      // Extract events.
      $day_events = $day_node['.ev-block'];
      foreach ($day_events as $event) {
        $event_node = pq($event);
        $time = trim($event_node['.ev-timer']->text());
        $name = trim($event_node['.ev-ctrl']->text());
        $id = trim($event_node['.ev-ctrl > span']->attr('data-event-id'));
        $timestamp = strtotime("{$date_str} {$time}:00 GMT+0100");
        $month_events[] = array(
          'timestamp' => date('U', $timestamp),
          'name' => $name,
          'id' => $id,
          'url' => $link,
        );
      }
    }
    
    save_tl_event_cache($month_events, $year, $month, $game_id);
    $events = array_merge($events, $month_events);
  }
  
  return $events;
}

function generate_calendar($events) {
  $cal = new \Eluceo\iCal\Component\Calendar('www.teamliquid.net');
  $cal->setName('TL Brood War Events');
  $cal->setDescription('Events taken from Team Liquid\'s Brood War calendar.');
  
  foreach ($events as $event) {
    $ev = new \Eluceo\iCal\Component\Event();
    $start = date('c', $event['timestamp']);
    $end = date('c', strtotime('+2 hours', $event['timestamp']));
    $ev->setDtStart(new \DateTime($start));
    $ev->setDtEnd(new \DateTime($end));
    $ev->setSummary($event['name']);
    $ev->setDescription("Link: {$event['url']}");
    $ev->setUseTimezone(true);
    $cal->addComponent($ev);
  }
  
  return $cal;
}

function output_calendar($cal) {
  header('Content-Type: text/calendar; charset=utf-8');
  header('Content-Disposition: attachment; filename="cal.ics"');
  print($cal->render());
}