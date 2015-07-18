<?php
class Estimate_Reading_Time extends Plugin {
  private $host;
  private $lang;

  function about() {
    return array(1.0,
      "Assign estimated reading time labels based on article length",
      "arthaey");
  }

  function init($host) {
    $this->host = $host;
    $host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
  }

  function hook_article_filter($article) {
    // TODO: replace echo with _debug // XXX

    $wpm = 180; // arbitrary value, based on adult averages
    $word_count = str_word_count($article["content"]);
    $minutes = round($word_count / $wpm);

    $minimum_time_bucket = 5; // minutes
    $time_label = $this->minutes_to_time_label($word_count, $minimum_time_bucket);
    echo("estimated reading time: $minutes min ($time_label), based on $word_count words at $wpm WPM");

    $owner_uid = $article["owner_uid"];
    if (!label_find_id($time_label, $owner_uid)) {
      label_create($time_label);
    }
    label_add_article($article["ref_id"], $time_label, $owner_uid);

    return $article;
  }

  function api_version() {
    return 2;
  }

  function minutes_to_time_label($minutes, $minimum_time_bucket) {
    // Buckets: <5, 5-10, 10-15, 15-30, 30-60, then 1-hour increments,
    // for the default 5-minute minimum bucket size.
    $buckets = array(0, 1, 2, 3, 6, 12);

    for ($i = 0; $i < count($buckets) - 1; $i++) {
      $lowerBound = $buckets[$i] * $minimum_time_bucket;
      $upperBound = $buckets[$i+1] * $minimum_time_bucket;

      if ($lowerBound <= $minutes && $minutes < $upperBound) {
        $lowerOptions = array();
        // If the amounts are both in minutes or both in hours, don't display
        // the units for the first amount (ie, show "5-10 min", not "5 min-10 min".
        if (($lowerBound <= 60 && $upperBound <= 60) || ($lowerBound > 60 && $upperBound > 60)) {
          $lowerOptions["no_units"] = true;
        }
        return $this->minutes_to_time_str($lowerBound, $lowerOptions) + "-" + $this->minutes_to_time_str($upperBound);
      }
    }

    // If we get to here, then the time is longer than our largest explicit bucket.
    // Divide everything beyond into hour-long buckets.
    return $this->minutes_to_time_str($minutes, array("treat_60_min_as_an_hour" => true));
  }

  function minutes_to_time_str($minutes, $options = array()) {
    $amount = $minutes;
    $units = "min";

    if ($minutes > 60 || ($minutes === 60 && $options["treat_60_min_as_an_hour"])) {
      $amount = round($minutes / 60);
      $units = "hr";
    }

    return $amount + ($options["no_units"] ? "" : " $units");
  }
}
?>
