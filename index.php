<?php
ini_set('max_execution_time', 300);

define('CRUNCHYROLL_BASE', 'http://www.crunchyroll.com');
define('CRUNCHYROLL_SUMMER_LIST', '/videos/anime/seasons/summer-2014');
define('EOL', "\r\n");

function dd($var){
  var_dump($var);
  die();
}

require 'vendor/phpQuery/phpQuery-onefile.php';

$xcr_ol = json_decode(file_get_contents('data/cr_ol.json'));
$cr_ol = [];
$doc = phpQuery::newDocument(file_get_contents(CRUNCHYROLL_BASE . CRUNCHYROLL_SUMMER_LIST));
// gather list of items from index
$items = pq('ul.portrait-grid li.group-item');
foreach($items as $item) {
  //   $summary     - text title of the event
  //   $datestart   - the starting date (in seconds since unix epoch)
  //   $dateend     - the ending date (in seconds since unix epoch)
  //   $address     - the event's address
  //   $uri         - the URL of the event (add http://)
  //   $description - text description of the event
  //   $filename    - the name of this file for saving (e.g. my-event-name.ics)
  $title = pq($item)->find('[itemprop="name"]')->html();
  $gid = pq($item)->attr('group_id');

  if(isset($xcr_ol->$gid)) {
    $cr_ol[$gid] = $xcr_ol->$gid;
    continue;
  }

  $data = (object) [
    'summary' => $title,
    'datestart' => null,
    'dateend' => null,
    'address' => null,
    'uri' => CRUNCHYROLL_BASE . pq($item)->find('a')->attr('href'),
    'description' => null
  ];
  $cr_ol[$gid] = $data;
}

/**
 * collect data
 */
foreach($cr_ol as $i => $li) {

  // skip
  if(!is_null($li->datestart)) continue;

  // fetch
  $temp = phpQuery::newDocument(file_get_contents($li->uri));
  phpQuery::selectDocument($temp);

  $li->poster = $temp["#sidebar_elements"]->find('.poster')->attr('src');
  $simulcastText = $temp["#sidebar_elements li:eq(1) .strong"]->html();
  $li->datestart = strtotime(str_replace('Simulcast on ', '', $simulcastText));
  $li->dateend = strtotime("+1 hour", $li->datestart);
  $li->description = trim($temp['#sidebar_elements .more']->html());

  // out
  $cr_ol[$i] = $li;
}

// save for later
file_put_contents('data/cr_ol.json',json_encode($cr_ol));

header('Content-type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename=crunchyroll.ics');

function dateToCal($timestamp) {
  return date('Ymd\THis\Z', $timestamp);
}

$load = "BEGIN:VCALENDAR" . EOL .
"VERSION:2.0" . EOL .
"PRODID:-//project/author//NONSGML v1.0//EN" . EOL .
"CALSCALE:GREGORIAN" . EOL;
foreach($cr_ol as $li) {
$load .= "BEGIN:VEVENT" . EOL .
"DTSTART:" . dateToCal($li->datestart) . EOL .
"DTEND:" . dateToCal($li->dateend) . EOL .
"RRULE:FREQ=WEEKLY;" . EOL .
"DTSTAMP:" . dateToCal(time()) . EOL .
"UID:" . md5(rand()) . EOL .
"URL:" . htmlspecialchars($li->uri) . EOL .
"DESCRIPTION:" . htmlspecialchars(str_replace("\r\n",'',$li->description)) . EOL .
"SUMMARY:" . htmlspecialchars($li->summary) . EOL .
"END:VEVENT" . EOL;
}
$load .= "END:VCALENDAR";

echo $load;