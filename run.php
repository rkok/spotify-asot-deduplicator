<?php

use Foo\SpotifyAsotDeduplicate\FileCache;
use Foo\SpotifyAsotDeduplicate\SpotifyWrapper;

require_once __DIR__ . '/config.php';
/** @var $clientId */
/** @var $secret */
/** @var $tokenFile */
/** @var $asotPlaylistId */
/** @var $targetPlaylistId */
/** @var $cacheDir */
/** @var $spotifyRedirectUri */

require 'vendor/autoload.php';

$cache = new FileCache($cacheDir);

$session = new SpotifyWebAPI\Session(
    $clientId,
    $secret,
    $spotifyRedirectUri
);

$api = new SpotifyWebAPI\SpotifyWebAPI(['return_assoc' => true]);

$spotify = new SpotifyWrapper($api, $cache);

$token = null;
if (file_exists($tokenFile)) {
    $token = file_get_contents($tokenFile);
}

if (!$token) {
    $options = [
        'scope' => [
            'user-read-email',
            'playlist-modify-public'
        ],
    ];

    echo "Go to " . $session->getAuthorizeUrl($options) . " to get a token";
    exit(0);
}

$api->setAccessToken($token);

try {
    echo "Testing token...\n";
    $api->me();
} catch (\Exception $e) {
    echo "Error, remove $tokenFile and try again";
    exit(1);
}

$items = $spotify->getFullPlaylist($asotPlaylistId);
$seenTracks = [];
$nSkipped = 0;
$targetTrackUris = [];

foreach ($items as $i => $item) {
    $track = $item['track'];
    if (preg_match("/^A State Of Trance/", $track['name'])) {
        // Talking / repetitive stuff; intros, outros, interviews
        $nSkipped++;
        continue;
    }

    $trackNameClean = $track['name'];
    // "TrackName [ASOT 123] **ASOT Radio Classic** - Original Mix"
    $trackNameClean = preg_replace("/ \[ASOT \d+\]/", "", $trackNameClean);
    $trackNameClean = preg_replace("/ \*\*[^\*]+\*\*/", "", $trackNameClean);
    // "TrackName (ASOT 123) [Tune Of The Week] - Original Mix"
    $trackNameClean = preg_replace("/ \(ASOT \d+\)( \[[^\]]+\])?/", "", $trackNameClean);
    $artistNames = array_map(function ($artist) {
        return $artist['name'];
    }, $track['artists']);
    sort($artistNames);
    $artistNameClean = implode(', ', $artistNames);

    $fullTrackNameClean = "$artistNameClean - $trackNameClean";
    if (isset($seenTracks[$fullTrackNameClean])) {
        $nSkipped++;
        continue; // Skip if we've seen this track before
    }
    $seenTracks[$fullTrackNameClean] = true;

    $targetTrackUris[] = $track['uri'];

    echo "-- " . $track['name'] . "\n";
}

echo "Skipped $nSkipped tracks\n";

// For resuming purposes
$startingTrackCount = $spotify->getPlaylistTrackCount($targetPlaylistId);

for ($i = $startingTrackCount; $i < count($targetTrackUris); $i += 100) {
    echo "Adding tracks to target playlist, i=$i\n";
    $api->addPlaylistTracks($targetPlaylistId, array_slice($targetTrackUris, $i, 100));
}
