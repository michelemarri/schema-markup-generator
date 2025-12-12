<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Integration;

use Metodo\SchemaMarkupGenerator\Security\Encryption;

/**
 * YouTube Data API Integration
 *
 * Provides video duration extraction using YouTube Data API v3.
 * API key is stored encrypted for security.
 *
 * @package Metodo\SchemaMarkupGenerator\Integration
 * @author  Michele Marri <plugins@metodo.dev>
 */
class YouTubeIntegration
{
    /**
     * YouTube Data API v3 endpoint
     */
    private const API_ENDPOINT = 'https://www.googleapis.com/youtube/v3/videos';

    /**
     * Option name for encrypted API key
     */
    private const API_KEY_OPTION = 'smg_youtube_api_key_encrypted';

    /**
     * Cache duration for video data (1 week)
     */
    private const CACHE_DURATION = WEEK_IN_SECONDS;

    /**
     * Encryption instance
     */
    private Encryption $encryption;

    /**
     * Cached API key (decrypted)
     */
    private ?string $cachedApiKey = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->encryption = new Encryption();
    }

    /**
     * Initialize integration
     */
    public function init(): void
    {
        // Register AJAX handlers for API key management
        add_action('wp_ajax_smg_save_youtube_api_key', [$this, 'handleSaveApiKey']);
        add_action('wp_ajax_smg_test_youtube_api_key', [$this, 'handleTestApiKey']);
        add_action('wp_ajax_smg_remove_youtube_api_key', [$this, 'handleRemoveApiKey']);
    }

    /**
     * Check if YouTube API is available (API key is set)
     */
    public function isAvailable(): bool
    {
        return !empty($this->getApiKey());
    }

    /**
     * Get the decrypted API key
     */
    public function getApiKey(): ?string
    {
        if ($this->cachedApiKey !== null) {
            return $this->cachedApiKey;
        }

        $encrypted = get_option(self::API_KEY_OPTION);

        if (empty($encrypted)) {
            return null;
        }

        $decrypted = $this->encryption->decrypt($encrypted);

        if ($decrypted === false) {
            return null;
        }

        $this->cachedApiKey = $decrypted;
        return $decrypted;
    }

    /**
     * Save API key (encrypted)
     *
     * @param string $apiKey The API key to save
     * @return bool Success
     */
    public function saveApiKey(string $apiKey): bool
    {
        if (empty($apiKey)) {
            return false;
        }

        $encrypted = $this->encryption->encrypt($apiKey);

        if ($encrypted === false) {
            return false;
        }

        $result = update_option(self::API_KEY_OPTION, $encrypted);
        
        // Clear cached key
        $this->cachedApiKey = null;

        return $result;
    }

    /**
     * Remove API key
     */
    public function removeApiKey(): bool
    {
        $this->cachedApiKey = null;
        return delete_option(self::API_KEY_OPTION);
    }

    /**
     * Check if API key is set (without decrypting)
     */
    public function hasApiKey(): bool
    {
        return !empty(get_option(self::API_KEY_OPTION));
    }

    /**
     * Get masked API key for display
     */
    public function getMaskedApiKey(): string
    {
        $apiKey = $this->getApiKey();

        if (empty($apiKey)) {
            return '';
        }

        return Encryption::mask($apiKey, 4);
    }

    /**
     * Get video duration from YouTube Data API
     *
     * @param string $videoId YouTube video ID (11 characters)
     * @return int Duration in seconds (0 if not found)
     */
    public function getVideoDuration(string $videoId): int
    {
        if (!$this->isAvailable()) {
            return 0;
        }

        // Clean video ID (remove any parameters)
        $videoId = $this->extractVideoId($videoId);

        if (empty($videoId)) {
            return 0;
        }

        // Check cache first
        $cacheKey = 'smg_yt_duration_' . $videoId;
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return (int) $cached;
        }

        // Fetch from API
        $duration = $this->fetchVideoDuration($videoId);

        // Cache result (even if 0, to avoid repeated failed requests)
        set_transient($cacheKey, $duration, self::CACHE_DURATION);

        return $duration;
    }

    /**
     * Fetch video duration from YouTube API
     *
     * @param string $videoId The video ID
     * @return int Duration in seconds
     */
    private function fetchVideoDuration(string $videoId): int
    {
        $apiKey = $this->getApiKey();

        if (empty($apiKey)) {
            return 0;
        }

        $url = add_query_arg([
            'id' => $videoId,
            'part' => 'contentDetails',
            'key' => $apiKey,
        ], self::API_ENDPOINT);

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return 0;
        }

        $statusCode = wp_remote_retrieve_response_code($response);

        if ($statusCode !== 200) {
            return 0;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['items'][0]['contentDetails']['duration'])) {
            return 0;
        }

        // Parse ISO 8601 duration (PT1H30M45S)
        return $this->parseISO8601Duration($data['items'][0]['contentDetails']['duration']);
    }

    /**
     * Parse ISO 8601 duration to seconds
     *
     * @param string $duration ISO 8601 duration (e.g., PT1H30M45S)
     * @return int Duration in seconds
     */
    private function parseISO8601Duration(string $duration): int
    {
        $interval = new \DateInterval($duration);

        return ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
    }

    /**
     * Extract video ID from various YouTube URL formats
     *
     * @param string $input Video ID or URL
     * @return string|null Video ID or null
     */
    public function extractVideoId(string $input): ?string
    {
        // Already a video ID (11 characters)
        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $input)) {
            return $input;
        }

        // youtube.com/watch?v=...
        if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/', $input, $matches)) {
            return $matches[1];
        }

        // youtu.be/...
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $input, $matches)) {
            return $matches[1];
        }

        // youtube.com/embed/...
        if (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/', $input, $matches)) {
            return $matches[1];
        }

        // youtube.com/live/...
        if (preg_match('/youtube\.com\/live\/([a-zA-Z0-9_-]{11})/', $input, $matches)) {
            return $matches[1];
        }

        // youtube-nocookie.com/embed/...
        if (preg_match('/youtube-nocookie\.com\/embed\/([a-zA-Z0-9_-]{11})/', $input, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get video details (duration, title, thumbnail)
     *
     * @param string $videoId Video ID
     * @return array|null Video details or null
     */
    public function getVideoDetails(string $videoId): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $videoId = $this->extractVideoId($videoId);

        if (empty($videoId)) {
            return null;
        }

        // Check cache
        $cacheKey = 'smg_yt_details_' . $videoId;
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $apiKey = $this->getApiKey();
        $url = add_query_arg([
            'id' => $videoId,
            'part' => 'contentDetails,snippet',
            'key' => $apiKey,
        ], self::API_ENDPOINT);

        $response = wp_remote_get($url, [
            'timeout' => 10,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data['items'][0])) {
            return null;
        }

        $item = $data['items'][0];
        $details = [
            'id' => $videoId,
            'title' => $item['snippet']['title'] ?? '',
            'description' => $item['snippet']['description'] ?? '',
            'duration_seconds' => $this->parseISO8601Duration($item['contentDetails']['duration'] ?? 'PT0S'),
            'duration_iso' => $item['contentDetails']['duration'] ?? 'PT0S',
            'thumbnail' => $item['snippet']['thumbnails']['high']['url'] ?? $item['snippet']['thumbnails']['default']['url'] ?? '',
            'channel' => $item['snippet']['channelTitle'] ?? '',
            'published_at' => $item['snippet']['publishedAt'] ?? '',
        ];

        // Cache for 1 week
        set_transient($cacheKey, $details, self::CACHE_DURATION);

        return $details;
    }

    /**
     * Test API key validity
     *
     * @param string $apiKey API key to test
     * @return array Test result with 'success' and 'message'
     */
    public function testApiKey(string $apiKey): array
    {
        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => __('API key is empty.', 'schema-markup-generator'),
            ];
        }

        // Test with a known public video (YouTube's own video)
        $testVideoId = 'jNQXAC9IVRw'; // "Me at the zoo" - first YouTube video

        $url = add_query_arg([
            'id' => $testVideoId,
            'part' => 'contentDetails',
            'key' => $apiKey,
        ], self::API_ENDPOINT);

        $response = wp_remote_get($url, [
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => __('Connection failed: ', 'schema-markup-generator') . $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($statusCode === 200 && !empty($data['items'])) {
            return [
                'success' => true,
                'message' => __('API key is valid! Video duration retrieval is working.', 'schema-markup-generator'),
            ];
        }

        if ($statusCode === 400) {
            return [
                'success' => false,
                'message' => __('Invalid API key format.', 'schema-markup-generator'),
            ];
        }

        if ($statusCode === 403) {
            $error = $data['error']['message'] ?? __('Access denied', 'schema-markup-generator');
            return [
                'success' => false,
                'message' => __('API access denied: ', 'schema-markup-generator') . $error,
            ];
        }

        return [
            'success' => false,
            'message' => sprintf(__('API error (HTTP %d)', 'schema-markup-generator'), $statusCode),
        ];
    }

    /**
     * AJAX handler: Save API key
     */
    public function handleSaveApiKey(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'schema-markup-generator')], 403);
        }

        if (!check_ajax_referer('smg_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token.', 'schema-markup-generator')], 403);
        }

        $apiKey = sanitize_text_field($_POST['api_key'] ?? '');

        if (empty($apiKey)) {
            wp_send_json_error(['message' => __('API key is required.', 'schema-markup-generator')]);
        }

        // Test the key first
        $testResult = $this->testApiKey($apiKey);

        if (!$testResult['success']) {
            wp_send_json_error([
                'message' => $testResult['message'],
            ]);
        }

        // Save encrypted
        if ($this->saveApiKey($apiKey)) {
            wp_send_json_success([
                'message' => __('API key saved and verified successfully!', 'schema-markup-generator'),
                'masked_key' => $this->getMaskedApiKey(),
            ]);
        }

        wp_send_json_error(['message' => __('Failed to save API key.', 'schema-markup-generator')]);
    }

    /**
     * AJAX handler: Test API key
     */
    public function handleTestApiKey(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'schema-markup-generator')], 403);
        }

        if (!check_ajax_referer('smg_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token.', 'schema-markup-generator')], 403);
        }

        $apiKey = sanitize_text_field($_POST['api_key'] ?? '');

        // If no key provided, test the saved one
        if (empty($apiKey)) {
            $apiKey = $this->getApiKey();
        }

        if (empty($apiKey)) {
            wp_send_json_error(['message' => __('No API key to test.', 'schema-markup-generator')]);
        }

        $result = $this->testApiKey($apiKey);

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        }

        wp_send_json_error(['message' => $result['message']]);
    }

    /**
     * AJAX handler: Remove API key
     */
    public function handleRemoveApiKey(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'schema-markup-generator')], 403);
        }

        if (!check_ajax_referer('smg_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token.', 'schema-markup-generator')], 403);
        }

        if ($this->removeApiKey()) {
            wp_send_json_success(['message' => __('API key removed.', 'schema-markup-generator')]);
        }

        wp_send_json_error(['message' => __('Failed to remove API key.', 'schema-markup-generator')]);
    }

    /**
     * Get quota usage info (if available from last request)
     */
    public function getQuotaInfo(): array
    {
        return [
            'daily_limit' => 10000,
            'cost_per_video' => 1, // 1 unit per video for contentDetails
            'note' => __('YouTube Data API v3 has a free quota of 10,000 units/day. Each video duration request costs 1 unit.', 'schema-markup-generator'),
        ];
    }
}
