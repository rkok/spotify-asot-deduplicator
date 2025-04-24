<?php

namespace Foo\SpotifyAsotDeduplicate;

use RuntimeException;

/**
 * Thanks, Claude
 */
class FileCache {
    private string $cacheDir;

    /**
     * @param string $cacheDir Directory to store cache files
     * @throws RuntimeException if directory doesn't exist and can't be created
     */
    public function __construct(string $cacheDir) {
        $this->cacheDir = rtrim($cacheDir, '/\\') . DIRECTORY_SEPARATOR;

        if (!is_dir($this->cacheDir)) {
            if (!mkdir($this->cacheDir, 0777, true)) {
                throw new RuntimeException("Unable to create cache directory: {$this->cacheDir}");
            }
        }

        if (!is_writable($this->cacheDir)) {
            throw new RuntimeException("Cache directory is not writable: {$this->cacheDir}");
        }
    }

    /**
     * Store data in cache
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $ttl Time to live in seconds (0 = never expires)
     * @return bool Success status
     */
    public function set(mixed $key, mixed $data, int $ttl = 0): bool {
        $filename = $this->getFilename($key);

        $cacheData = [
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'data' => $data
        ];

        return file_put_contents($filename, serialize($cacheData)) !== false;
    }

    /**
     * Retrieve data from cache
     *
     * @param string $key Cache key
     * @return mixed|null Cached data or null if not found/expired
     */
    public function get(mixed $key): mixed {
        $filename = $this->getFilename($key);

        if (!file_exists($filename)) {
            return null;
        }

        $cacheData = unserialize(file_get_contents($filename));

        // Check if data has expired
        if ($cacheData['expires'] > 0 && $cacheData['expires'] < time()) {
            $this->delete($key);
            return null;
        }

        return $cacheData['data'];
    }

    /**
     * Check if key exists in cache and hasn't expired
     *
     * @param string $key Cache key
     * @return bool Whether key exists and is valid
     */
    public function has(mixed $key): bool {
        return $this->get($key) !== null;
    }

    /**
     * Delete item from cache
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(mixed $key): bool {
        $filename = $this->getFilename($key);

        if (file_exists($filename)) {
            return unlink($filename);
        }

        return true;
    }

    /**
     * Clear all items from cache
     *
     * @return bool Success status
     */
    public function clear(): bool {
        $files = glob($this->cacheDir . '*');

        if ($files === false) {
            return false;
        }

        $success = true;
        foreach ($files as $file) {
            if (is_file($file)) {
                $success = $success && unlink($file);
            }
        }

        return $success;
    }

    /**
     * Get filename for cache key
     *
     * @param string $key Cache key
     * @return string Full path to cache file
     */
    /**
     * Flatten an array into a kebab-case string
     *
     * @param array|string $input Input to flatten
     * @return string Flattened string in kebab-case
     */
    private function flattenArrayToString(mixed $input): string {
        if (is_string($input) || is_numeric($input)) {
            return (string)$input;
        }

        if (!is_array($input)) {
            return '';
        }

        $result = [];

        foreach ($input as $key => $value) {
            if (is_array($value)) {
                // For nested arrays, recursively flatten
                $flattened = $this->flattenArrayToString($value);
                if (!empty($flattened)) {
                    $result[] = $key;
                    $result[] = $flattened;
                }
            } else {
                // For scalar values, add both key and value if numeric/string
                if (is_string($key) || is_numeric($key)) {
                    $result[] = $key;
                }
                if (is_string($value) || is_numeric($value)) {
                    $result[] = $value;
                }
            }
        }

        return implode('-', $result);
    }

    /**
     * Get filename for cache key
     *
     * @param string|array $key Cache key
     * @return string Full path to cache file
     */
    private function getFilename(mixed $key): string {
        // Convert array to string if necessary
        if (is_array($key)) {
            $key = $this->flattenArrayToString($key);
        }

        // Sanitize key to create safe filename
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->cacheDir . $safeName . '.cache';
    }
}
