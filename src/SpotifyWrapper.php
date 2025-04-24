<?php

namespace Foo\SpotifyAsotDeduplicate;

use Exception;
use SpotifyWebAPI\SpotifyWebAPI;

class SpotifyWrapper
{
    private $api;
    private $cache;

    public function __construct(SpotifyWebAPI $api, FileCache $cache)
    {
        $this->api = $api;
        $this->cache = $cache;
    }

    public function getPlaylistTrackCount(string $playlistId): int {
        $ret = 0;
        $res = $this->api->getPlaylistTracks($playlistId, [
            'fields' => 'total'
        ]);
        return $res ? $res['total'] : $ret;
    }

    /**
     * Returns all items within a given playlist
     *
     * @param string $playlistId The Spotify playlist ID to fetch
     * @return array<array{
     * track: array{
     * name: string,
     * uri: string,
     * artists: array<array{
     * name: string
     * }>,
     * }
     * }>
     * @throws Exception
     */
    public function getFullPlaylist(string $playlistId): array
    {
        echo "Getting playlist items...\n";

        // Initialize variables
        $offset = 0;
        $limit = 50;
        $allTracks = [];

        // Get first batch to determine total
        $firstBatch = $this->getPlaylistBatch($playlistId, $offset, $limit);
        if (!isset($firstBatch['total'])) {
            throw new Exception("Error: no total in response");
        }
        $total = $firstBatch['total'];

        // Merge first batch results
        if (isset($firstBatch['items'])) {
            $allTracks = array_merge($allTracks, $firstBatch['items']);
        }

        // Get remaining batches
        for ($offset = $limit; $offset < $total; $offset += $limit) {
            $batch = $this->getPlaylistBatch($playlistId, $offset, $limit);
            if (isset($batch['items'])) {
                $allTracks = array_merge($allTracks, $batch['items']);
            }
        }

        return $allTracks;
    }

    private function getPlaylistBatch($playlistId, $offset, $limit)
    {
        $opts = [
            'fields' => 'total,items(track(uri,name,artists(name)))',
            'offset' => $offset,
            'limit' => $limit
        ];
        $cacheKey = ["path" => "playlist-items-$playlistId", ...$opts]; // Will be flattened automatically by FileCache. Thanks Claude =)

        $res = $this->cache->get($cacheKey);
        if ($res) {
            echo "Got offset $offset from cache!\n";
            return $res;
        }

        echo "Retrieving offset $offset\n";
        $res = $this->api->getPlaylistTracks($playlistId, $opts);

        $this->cache->set($cacheKey, $res);
        return $res;
    }
}
