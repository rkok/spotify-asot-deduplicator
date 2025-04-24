# Spotify ASOT Deduplicator

A quick tool that takes an A State Of Trance playlist,
strips out all the duplicate tracks, repetitive intros and other
'talk radio', and places the result in a new playlist.

No offense, Armin.

# Usage

Note: not tested in a while, could differ slightly.

1. Reverse-proxy https://yourdomain.com/spotify-redirect-uri/ to 
   a local server hosting [/receiver/](./receiver/),  
   _or_  
   Put this project in a web-hosted directory
   and execute all the following steps on that same server.
2. `composer install`
3. `cp config.example.php config.php`
4. Fill in [config.php](./config.php)
5. Run `php run.php`
