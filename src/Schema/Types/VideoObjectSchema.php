<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * VideoObject Schema
 *
 * For video content.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
 */
class VideoObjectSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'VideoObject';
    }

    public function getLabel(): string
    {
        return __('Video', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For video content. Enables video rich results with thumbnails, duration, and upload date.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post, $mapping);

        // Core properties
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);

        // Dates
        $data['uploadDate'] = $this->formatDate($post->post_date_gmt);

        // Thumbnail (with fallback to custom fallback image or site favicon)
        $thumbnail = $this->getMappedValue($post, $mapping, 'thumbnailUrl');
        if ($thumbnail) {
            $data['thumbnailUrl'] = is_array($thumbnail) ? $thumbnail['url'] : $thumbnail;
        } else {
            $imageWithFallback = $this->getImageWithFallback($post);
            if ($imageWithFallback) {
                $data['thumbnailUrl'] = $imageWithFallback['url'];
            }
        }

        // If still no thumbnail, try to extract from video embed (YouTube/Vimeo)
        if (empty($data['thumbnailUrl'])) {
            $embedUrl = $data['embedUrl'] ?? $this->extractEmbedUrl($post);
            if ($embedUrl) {
                $videoThumbnail = $this->getVideoThumbnailFromEmbed($embedUrl);
                if ($videoThumbnail) {
                    $data['thumbnailUrl'] = $videoThumbnail;
                }
            }
        }

        // Content URL (video file)
        $contentUrl = $this->getMappedValue($post, $mapping, 'contentUrl');
        if ($contentUrl) {
            $data['contentUrl'] = $contentUrl;
        }

        // Embed URL
        $embedUrl = $this->getMappedValue($post, $mapping, 'embedUrl');
        if ($embedUrl) {
            $data['embedUrl'] = $embedUrl;
        } else {
            // Try to extract from content
            $embedUrl = $this->extractEmbedUrl($post);
            if ($embedUrl) {
                $data['embedUrl'] = $embedUrl;
            }
        }

        // Duration (ISO 8601)
        $duration = $this->getMappedValue($post, $mapping, 'duration');
        if ($duration) {
            $data['duration'] = $this->formatDuration($duration);
        } else {
            // Try to auto-fetch duration from YouTube API if embedUrl is YouTube
            $autoFetchedDuration = $this->autoFetchYouTubeDuration($data['embedUrl'] ?? null);
            if ($autoFetchedDuration) {
                $data['duration'] = $autoFetchedDuration;
            }
        }

        // Publisher/creator
        $data['publisher'] = $this->getPublisher();

        // Interaction statistics
        $interactionCount = $this->getMappedValue($post, $mapping, 'interactionCount');
        if ($interactionCount) {
            $data['interactionStatistic'] = [
                '@type' => 'InteractionCounter',
                'interactionType' => ['@type' => 'WatchAction'],
                'userInteractionCount' => (int) $interactionCount,
            ];
        }

        // Transcript
        $transcript = $this->getMappedValue($post, $mapping, 'transcript');
        if ($transcript) {
            $data['transcript'] = $transcript;
        } else {
            // Try to auto-extract transcript from content
            $autoExtractedTranscript = $this->extractTranscriptFromContent($post->post_content);
            if ($autoExtractedTranscript) {
                $data['transcript'] = $autoExtractedTranscript;
            }
        }

        // Is family friendly
        $isFamilyFriendly = $this->getMappedValue($post, $mapping, 'isFamilyFriendly');
        if ($isFamilyFriendly !== null) {
            $data['isFamilyFriendly'] = (bool) $isFamilyFriendly;
        }

        // Keywords
        $keywords = $this->getMappedValue($post, $mapping, 'keywords');
        if ($keywords) {
            $data['keywords'] = is_array($keywords) ? implode(', ', $keywords) : $keywords;
        } else {
            // Fallback to post tags
            $tags = get_the_tags($post->ID);
            if ($tags && !is_wp_error($tags)) {
                $data['keywords'] = implode(', ', wp_list_pluck($tags, 'name'));
            }
        }

        // Language
        $inLanguage = $this->getMappedValue($post, $mapping, 'inLanguage');
        if ($inLanguage) {
            $data['inLanguage'] = $inLanguage;
        } else {
            $data['inLanguage'] = get_bloginfo('language');
        }

        // Video Chapters (hasPart with Clip)
        $chapters = $this->getMappedValue($post, $mapping, 'hasPart');
        if (is_array($chapters) && !empty($chapters)) {
            $data['hasPart'] = $this->buildVideoChapters($chapters, $data['embedUrl'] ?? null);
        } else {
            // Try to auto-extract chapters from content
            $extractedChapters = $this->extractChaptersFromContent($post->post_content, $data['embedUrl'] ?? null);
            if (!empty($extractedChapters)) {
                $data['hasPart'] = $extractedChapters;
            }
        }

        // Requires subscription (for premium content)
        $requiresSubscription = $this->getMappedValue($post, $mapping, 'requiresSubscription');
        if ($requiresSubscription !== null) {
            $data['isAccessibleForFree'] = !((bool) $requiresSubscription);
        }

        /**
         * Filter video schema data
         */
        $data = apply_filters('smg_video_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Format duration to ISO 8601
     */
    private function formatDuration(mixed $duration): string
    {
        if (is_string($duration) && str_starts_with($duration, 'PT')) {
            return $duration;
        }

        // Handle HH:MM:SS format
        if (is_string($duration) && preg_match('/^(\d+):(\d+):(\d+)$/', $duration, $matches)) {
            $hours = (int) $matches[1];
            $minutes = (int) $matches[2];
            $seconds = (int) $matches[3];

            $iso = 'PT';
            if ($hours > 0) {
                $iso .= "{$hours}H";
            }
            if ($minutes > 0) {
                $iso .= "{$minutes}M";
            }
            if ($seconds > 0) {
                $iso .= "{$seconds}S";
            }
            return $iso;
        }

        // Handle MM:SS format
        if (is_string($duration) && preg_match('/^(\d+):(\d+)$/', $duration, $matches)) {
            $minutes = (int) $matches[1];
            $seconds = (int) $matches[2];
            return "PT{$minutes}M{$seconds}S";
        }

        // Assume seconds if numeric
        if (is_numeric($duration)) {
            $totalSeconds = (int) $duration;
            $hours = floor($totalSeconds / 3600);
            $minutes = floor(($totalSeconds % 3600) / 60);
            $seconds = $totalSeconds % 60;

            $iso = 'PT';
            if ($hours > 0) {
                $iso .= "{$hours}H";
            }
            if ($minutes > 0) {
                $iso .= "{$minutes}M";
            }
            if ($seconds > 0) {
                $iso .= "{$seconds}S";
            }
            return $iso;
        }

        return "PT{$duration}";
    }

    /**
     * Extract embed URL from post content
     */
    private function extractEmbedUrl(WP_Post $post): ?string
    {
        $content = $post->post_content;

        // YouTube
        if (preg_match('/(?:youtube\.com\/(?:embed\/|watch\?v=)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $content, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        // Vimeo
        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $content, $matches)) {
            return 'https://player.vimeo.com/video/' . $matches[1];
        }

        // WordPress embed blocks
        if (preg_match('/<!-- wp:embed {"url":"([^"]+)"/', $content, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get video thumbnail from embed URL (YouTube or Vimeo)
     *
     * For YouTube: First tries the YouTube API (if configured), then falls back
     * to predictable YouTube thumbnail URLs.
     * For Vimeo: Uses the oEmbed API (no authentication required).
     *
     * @param string $embedUrl The embed URL
     * @return string|null Thumbnail URL or null
     */
    private function getVideoThumbnailFromEmbed(string $embedUrl): ?string
    {
        // Try YouTube first
        $youtubeThumbnail = $this->getYouTubeThumbnail($embedUrl);
        if ($youtubeThumbnail) {
            return $youtubeThumbnail;
        }

        // Try Vimeo
        $vimeoThumbnail = $this->getVimeoThumbnail($embedUrl);
        if ($vimeoThumbnail) {
            return $vimeoThumbnail;
        }

        return null;
    }

    /**
     * Get YouTube thumbnail from embed URL
     *
     * @param string $embedUrl The embed URL
     * @return string|null Thumbnail URL or null
     */
    private function getYouTubeThumbnail(string $embedUrl): ?string
    {
        // Extract YouTube video ID from various URL formats
        $videoId = null;

        if (preg_match('/youtube(?:-nocookie)?\.com\/embed\/([a-zA-Z0-9_-]{11})/', $embedUrl, $matches)) {
            $videoId = $matches[1];
        } elseif (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/', $embedUrl, $matches)) {
            $videoId = $matches[1];
        } elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $embedUrl, $matches)) {
            $videoId = $matches[1];
        }

        if (!$videoId) {
            return null;
        }

        // Try YouTube API first (if available) for verified high-quality thumbnail
        if (class_exists(\Metodo\SchemaMarkupGenerator\Integration\YouTubeIntegration::class)) {
            $youtube = new \Metodo\SchemaMarkupGenerator\Integration\YouTubeIntegration();

            if ($youtube->isAvailable()) {
                $details = $youtube->getVideoDetails($videoId);

                if (!empty($details['thumbnail'])) {
                    return $details['thumbnail'];
                }
            }
        }

        // Fallback: Use predictable YouTube thumbnail URLs (no API needed)
        // YouTube provides thumbnails at these predictable URLs:
        // - maxresdefault.jpg (1280x720) - may not exist for all videos
        // - sddefault.jpg (640x480)
        // - hqdefault.jpg (480x360) - guaranteed to exist
        // - mqdefault.jpg (320x180)
        // - default.jpg (120x90)
        //
        // We use hqdefault.jpg as it's guaranteed to exist for all videos
        // and meets Google's minimum requirement of 120x120px
        return 'https://img.youtube.com/vi/' . $videoId . '/hqdefault.jpg';
    }

    /**
     * Get Vimeo thumbnail using oEmbed API (no authentication required)
     *
     * @param string $embedUrl The embed URL
     * @return string|null Thumbnail URL or null
     */
    private function getVimeoThumbnail(string $embedUrl): ?string
    {
        // Extract Vimeo video ID
        if (!preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $embedUrl, $matches)) {
            if (!preg_match('/player\.vimeo\.com\/video\/(\d+)/', $embedUrl, $matches)) {
                return null;
            }
        }

        $videoId = $matches[1];

        // Check cache first
        $cacheKey = 'smg_vimeo_thumb_' . $videoId;
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached ?: null;
        }

        // Use Vimeo oEmbed API (no authentication required)
        $oembedUrl = 'https://vimeo.com/api/oembed.json?url=' . urlencode('https://vimeo.com/' . $videoId);

        $response = wp_remote_get($oembedUrl, [
            'timeout' => 10,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            // Cache empty result to avoid repeated failed requests
            set_transient($cacheKey, '', HOUR_IN_SECONDS);
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($data['thumbnail_url'])) {
            // Get larger thumbnail by modifying the URL
            // Vimeo thumbnails can be resized by changing the dimensions in the URL
            $thumbnail = preg_replace('/_\d+x\d+/', '_1280x720', $data['thumbnail_url']);

            // Cache for 1 week
            set_transient($cacheKey, $thumbnail, WEEK_IN_SECONDS);

            return $thumbnail;
        }

        set_transient($cacheKey, '', HOUR_IN_SECONDS);
        return null;
    }

    /**
     * Build video chapters from mapped data
     */
    private function buildVideoChapters(array $chapters, ?string $videoUrl): array
    {
        $result = [];
        $position = 1;
        
        foreach ($chapters as $chapter) {
            $chapterData = [
                '@type' => 'Clip',
                'position' => $position++,
            ];
            
            if (is_array($chapter)) {
                if (!empty($chapter['name']) || !empty($chapter['title'])) {
                    $chapterData['name'] = $chapter['name'] ?? $chapter['title'];
                }
                if (!empty($chapter['startOffset']) || !empty($chapter['time'])) {
                    $offset = $chapter['startOffset'] ?? $chapter['time'];
                    $chapterData['startOffset'] = $this->parseTimeToSeconds($offset);
                }
                if (!empty($chapter['endOffset'])) {
                    $chapterData['endOffset'] = $this->parseTimeToSeconds($chapter['endOffset']);
                }
                if (!empty($chapter['url'])) {
                    $chapterData['url'] = $chapter['url'];
                } elseif ($videoUrl && strpos($videoUrl, 'youtube') !== false && isset($chapterData['startOffset'])) {
                    // Auto-generate YouTube timestamp URL
                    $baseUrl = str_replace('/embed/', '/watch?v=', $videoUrl);
                    $chapterData['url'] = $baseUrl . '&t=' . $chapterData['startOffset'];
                }
            } else {
                // Simple string format: "0:00 Chapter Name"
                if (preg_match('/^(?:(\d+):)?(\d+):(\d+)\s+(.+)$/', $chapter, $match)) {
                    $hours = !empty($match[1]) ? (int) $match[1] : 0;
                    $chapterData['startOffset'] = ($hours * 3600) + ((int) $match[2] * 60) + (int) $match[3];
                    $chapterData['name'] = trim($match[4]);
                } else {
                    $chapterData['name'] = $chapter;
                }
            }
            
            $result[] = $chapterData;
        }
        
        return $result;
    }

    /**
     * Extract video chapters from content
     * 
     * Looks for timestamp patterns like:
     * - 0:00 Introduction
     * - 1:30 - Getting Started
     * - 00:05:30 Advanced Topics
     */
    private function extractChaptersFromContent(string $content, ?string $videoUrl): array
    {
        $chapters = [];
        
        // Pattern to match chapter timestamps
        $pattern = '/(?:^|\n)\s*(?:(\d{1,2}):)?(\d{1,2}):(\d{2})\s*[-–—:]?\s*(.+?)(?=\n|$)/m';
        
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            $position = 1;
            
            foreach ($matches as $match) {
                $hours = !empty($match[1]) ? (int) $match[1] : 0;
                $minutes = (int) $match[2];
                $seconds = (int) $match[3];
                $title = trim($match[4]);
                
                // Skip if title is too short or looks like a timestamp
                if (strlen($title) < 3 || preg_match('/^\d+:\d+/', $title)) {
                    continue;
                }
                
                $startOffset = ($hours * 3600) + ($minutes * 60) + $seconds;
                
                $chapter = [
                    '@type' => 'Clip',
                    'name' => $title,
                    'startOffset' => $startOffset,
                    'position' => $position++,
                ];
                
                // Add URL with timestamp if it's YouTube
                if ($videoUrl && strpos($videoUrl, 'youtube') !== false) {
                    $baseUrl = str_replace('/embed/', '/watch?v=', $videoUrl);
                    $chapter['url'] = $baseUrl . '&t=' . $startOffset;
                }
                
                $chapters[] = $chapter;
            }
        }
        
        return $chapters;
    }

    /**
     * Parse time string to seconds
     */
    private function parseTimeToSeconds(mixed $time): int
    {
        if (is_numeric($time)) {
            return (int) $time;
        }
        
        // Parse HH:MM:SS or MM:SS format
        if (preg_match('/^(?:(\d+):)?(\d+):(\d+)$/', $time, $match)) {
            $hours = !empty($match[1]) ? (int) $match[1] : 0;
            return ($hours * 3600) + ((int) $match[2] * 60) + (int) $match[3];
        }
        
        return 0;
    }

    /**
     * Auto-fetch duration from YouTube API if embedUrl is a YouTube video
     *
     * @param string|null $embedUrl The embed URL
     * @return string|null ISO 8601 duration or null
     */
    private function autoFetchYouTubeDuration(?string $embedUrl): ?string
    {
        if (empty($embedUrl)) {
            return null;
        }

        // Extract YouTube video ID from embed URL
        if (!preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/', $embedUrl, $matches)) {
            return null;
        }

        $videoId = $matches[1];

        // Check if YouTube integration is available
        if (!class_exists(\Metodo\SchemaMarkupGenerator\Integration\YouTubeIntegration::class)) {
            return null;
        }

        $youtube = new \Metodo\SchemaMarkupGenerator\Integration\YouTubeIntegration();
        
        if (!$youtube->isAvailable()) {
            return null;
        }

        $duration = $youtube->getVideoDuration($videoId);
        
        if ($duration > 0) {
            return $this->formatDuration($duration);
        }

        return null;
    }

    /**
     * Extract video transcript from post content
     *
     * Looks for common transcript patterns:
     * - Headings with "Transcript", "Transcription", "Trascrizione"
     * - Content with timestamp patterns [00:00:00]
     * - Div/section with transcript class
     *
     * @param string $content The post content
     * @return string|null Cleaned transcript text or null
     */
    private function extractTranscriptFromContent(string $content): ?string
    {
        // Maximum transcript length (characters)
        $maxLength = 5000;

        // Pattern 1: Look for heading with transcript keywords
        $headingPattern = '/<h[2-6][^>]*>.*?(?:Video\s+)?(?:Transcript(?:ion)?|Trascrizione|Full\s+Text).*?<\/h[2-6]>/is';

        if (preg_match($headingPattern, $content, $headingMatch, PREG_OFFSET_CAPTURE)) {
            $startPos = $headingMatch[0][1] + strlen($headingMatch[0][0]);
            $remainingContent = substr($content, $startPos);
            
            // Find next heading to determine end of transcript section
            if (preg_match('/<h[2-6][^>]*>/i', $remainingContent, $nextHeading, PREG_OFFSET_CAPTURE)) {
                $transcriptHtml = substr($remainingContent, 0, $nextHeading[0][1]);
            } else {
                $transcriptHtml = $remainingContent;
            }

            $transcript = $this->cleanTranscriptText($transcriptHtml);
            
            if (!empty($transcript) && strlen($transcript) > 50) {
                return $this->truncateTranscript($transcript, $maxLength);
            }
        }

        // Pattern 2: Look for content with timestamp patterns [HH:MM:SS] or [HH:MM:SS.ms]
        // Fixed lookahead to match full timestamp format
        $timestampPattern = '/\[(\d{2}:\d{2}:\d{2}(?:\.\d{2})?)\]\s*(?:-\s*(?:Speaker\s*\d+|[A-Za-z]+)\s*)?(.+?)(?=\[\d{2}:\d{2}:\d{2}|\z)/s';
        
        if (preg_match_all($timestampPattern, $content, $matches, PREG_SET_ORDER)) {
            if (count($matches) >= 3) {
                $transcriptParts = [];
                foreach ($matches as $match) {
                    $text = trim($match[2]);
                    if (!empty($text)) {
                        $transcriptParts[] = $text;
                    }
                }
                
                if (!empty($transcriptParts)) {
                    $transcript = implode(' ', $transcriptParts);
                    $transcript = $this->cleanTranscriptText($transcript);
                    
                    if (strlen($transcript) > 50) {
                        return $this->truncateTranscript($transcript, $maxLength);
                    }
                }
            }
        }

        // Pattern 3: Look for div/section/details with transcript class
        // Matches: lesson-transcription, transcript, transcription, video-transcript
        $classPattern = '/<(?:div|section|details)[^>]*class=["\'][^"\']*(?:lesson-transcription|transcript(?:ion)?|video-transcript)[^"\']*["\'][^>]*>(.*?)<\/(?:div|section|details)>/is';
        
        if (preg_match($classPattern, $content, $matches)) {
            $transcript = $this->cleanTranscriptText($matches[1]);
            
            if (!empty($transcript) && strlen($transcript) > 50) {
                return $this->truncateTranscript($transcript, $maxLength);
            }
        }

        return null;
    }

    /**
     * Clean transcript text by removing HTML, timestamps, and speaker labels
     *
     * @param string $text Raw transcript text
     * @return string Cleaned text
     */
    private function cleanTranscriptText(string $text): string
    {
        // Remove HTML tags
        $text = wp_strip_all_tags($text);
        
        // Remove timestamp patterns: [00:00:01.20] or [00:00:01]
        $text = preg_replace('/\[\d{2}:\d{2}:\d{2}(?:\.\d{2})?\]/', '', $text);
        
        // Remove speaker labels: "- Speaker 1", "Speaker 1:", etc.
        $text = preg_replace('/(?:^|\n)\s*-?\s*Speaker\s*\d+\s*:?\s*/i', ' ', $text);
        
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * Truncate transcript to maximum length at word boundary
     *
     * @param string $transcript The transcript text
     * @param int $maxLength Maximum length
     * @return string Truncated transcript
     */
    private function truncateTranscript(string $transcript, int $maxLength): string
    {
        if (strlen($transcript) <= $maxLength) {
            return $transcript;
        }

        $truncated = substr($transcript, 0, $maxLength);
        $lastSpace = strrpos($truncated, ' ');
        
        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }

        return $truncated . '...';
    }

    public function getRequiredProperties(): array
    {
        return ['name', 'description', 'thumbnailUrl', 'uploadDate'];
    }

    public function getRecommendedProperties(): array
    {
        return ['contentUrl', 'embedUrl', 'duration', 'publisher', 'hasPart', 'keywords'];
    }

    public function getPropertyDefinitions(): array
    {
        return array_merge(self::getAdditionalTypeDefinition(), [
            'name' => [
                'type' => 'text',
                'description' => __('Video title. Required for video rich results in Google Search.', 'schema-markup-generator'),
                'description_long' => __('The title of the video. This is required for video rich results and is the primary text shown in video search results and carousels. Be descriptive and include key topics covered.', 'schema-markup-generator'),
                'example' => __('Complete Python Tutorial for Beginners, How to Bake Sourdough Bread, Product Demo: New Features in v2.0', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/name',
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Video summary. Required. Max 2048 characters for Google.', 'schema-markup-generator'),
                'description_long' => __('A description of the video content. Required for video rich results. Google recommends keeping it under 2048 characters. Include key topics, what viewers will learn, and any notable features.', 'schema-markup-generator'),
                'example' => __('In this comprehensive tutorial, you\'ll learn Python programming from scratch. We cover variables, data types, functions, and build a real project together.', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/description',
                'auto' => 'post_excerpt',
            ],
            'thumbnailUrl' => [
                'type' => 'image',
                'description' => __('Required. Preview image shown in search results. Min 120x120px recommended.', 'schema-markup-generator'),
                'description_long' => __('A URL pointing to the video thumbnail image. Required for video rich results. Google recommends images at least 120x120 pixels. For best results, use 1280x720 pixels (HD) or larger.', 'schema-markup-generator'),
                'example' => __('https://example.com/videos/thumbnails/python-tutorial.jpg', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/thumbnailUrl',
            ],
            'contentUrl' => [
                'type' => 'url',
                'description' => __('Direct video file URL. Preferred if you host videos directly.', 'schema-markup-generator'),
                'description_long' => __('A URL pointing to the actual video file. Use this if you host videos directly on your server. The file must be accessible to Googlebot for indexing.', 'schema-markup-generator'),
                'example' => __('https://example.com/videos/python-tutorial.mp4', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/contentUrl',
            ],
            'embedUrl' => [
                'type' => 'url',
                'description' => __('Player embed URL (YouTube, Vimeo). Auto-detected from content if embedded.', 'schema-markup-generator'),
                'description_long' => __('A URL pointing to a player for the video. This is typically a YouTube or Vimeo embed URL. If you embed videos in your content, this is automatically detected.', 'schema-markup-generator'),
                'example' => __('https://www.youtube.com/embed/dQw4w9WgXcQ, https://player.vimeo.com/video/123456789', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/embedUrl',
                'auto' => 'post_content',
            ],
            'duration' => [
                'type' => 'text',
                'description' => __('Video length (HH:MM:SS or seconds). Shown in video rich results.', 'schema-markup-generator'),
                'description_long' => __('The duration of the video. You can enter it as HH:MM:SS, MM:SS, or total seconds - it will be automatically converted to ISO 8601 format (PT1H30M for 1 hour 30 minutes).', 'schema-markup-generator'),
                'example' => __('1:30:00 (1h 30m), 45:30 (45m 30s), 3600 (seconds)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/duration',
            ],
            'hasPart' => [
                'type' => 'repeater',
                'description' => __('Video Chapters. Creates clickable segments in Google Search and YouTube. Auto-extracted from timestamp patterns in content.', 'schema-markup-generator'),
                'description_long' => __('Video chapters or segments. These create key moments in Google Search that users can click to jump to specific parts of the video. Automatically extracted from timestamp patterns like "0:00 Introduction" in your content.', 'schema-markup-generator'),
                'example' => __('0:00 Introduction, 2:30 Getting Started, 10:00 Advanced Topics, 25:00 Conclusion', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/hasPart',
                'auto' => 'post_content',
                'auto_description' => __('Auto-extracted from patterns like "0:00 Introduction", "5:30 Main Topic"', 'schema-markup-generator'),
                'fields' => [
                    'time' => ['type' => 'text', 'description' => __('Start time (MM:SS or HH:MM:SS)', 'schema-markup-generator')],
                    'name' => ['type' => 'text', 'description' => __('Chapter title', 'schema-markup-generator')],
                ],
            ],
            'keywords' => [
                'type' => 'text',
                'description' => __('Video keywords (comma-separated). Helps with video SEO and AI indexing.', 'schema-markup-generator'),
                'description_long' => __('Keywords describing the video content. These help with video SEO and AI indexing. Include both broad topics and specific terms users might search for.', 'schema-markup-generator'),
                'example' => __('python tutorial, programming, coding for beginners, learn python, web development', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/keywords',
                'auto' => 'tags',
            ],
            'inLanguage' => [
                'type' => 'text',
                'description' => __('Video language (e.g., "it", "en"). Auto-detected from WordPress settings.', 'schema-markup-generator'),
                'description_long' => __('The language spoken in the video. Use ISO 639-1 codes (e.g., "en" for English). This helps Google show your video to users searching in that language.', 'schema-markup-generator'),
                'example' => __('en, it, es, de, fr', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/inLanguage',
            ],
            'interactionCount' => [
                'type' => 'number',
                'description' => __('View count. Social proof signal in video results.', 'schema-markup-generator'),
                'description_long' => __('The number of times the video has been viewed. This is a social proof signal that can influence user decisions in video search results.', 'schema-markup-generator'),
                'example' => __('15420, 1250000, 89500', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/interactionCount',
            ],
            'transcript' => [
                'type' => 'textarea',
                'description' => __('Full video transcript. Improves accessibility and AI content understanding.', 'schema-markup-generator'),
                'description_long' => __('The full text transcript of the video. This significantly improves accessibility, SEO, and helps AI systems understand your video content for better matching with search queries.', 'schema-markup-generator'),
                'example' => __('Welcome to this tutorial on Python programming. Today we\'ll cover the basics of variables and data types...', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/transcript',
            ],
            'isFamilyFriendly' => [
                'type' => 'boolean',
                'description' => __('Safe for all audiences. Affects content filtering in search.', 'schema-markup-generator'),
                'description_long' => __('Indicates whether the video is appropriate for all audiences, including children. This affects how the video appears in filtered search results and can impact visibility.', 'schema-markup-generator'),
                'example' => __('true, false', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/isFamilyFriendly',
            ],
            'requiresSubscription' => [
                'type' => 'boolean',
                'description' => __('Premium/paid content. Sets isAccessibleForFree accordingly.', 'schema-markup-generator'),
                'description_long' => __('Indicates whether the video requires a paid subscription to access. This sets the isAccessibleForFree property and helps Google understand your content model.', 'schema-markup-generator'),
                'example' => __('true (paid), false (free)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/requiresSubscription',
            ],
        ]);
    }
}

