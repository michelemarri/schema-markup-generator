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
 * @author  Michele Marri <info@metodo.dev>
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
        $data = $this->buildBase($post);

        // Core properties
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);

        // Dates
        $data['uploadDate'] = $this->formatDate($post->post_date_gmt);

        // Thumbnail
        $thumbnail = $this->getMappedValue($post, $mapping, 'thumbnailUrl');
        if ($thumbnail) {
            $data['thumbnailUrl'] = is_array($thumbnail) ? $thumbnail['url'] : $thumbnail;
        } else {
            $image = $this->getFeaturedImage($post);
            if ($image) {
                $data['thumbnailUrl'] = $image['url'];
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
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Video title. Required for video rich results in Google Search.', 'schema-markup-generator'),
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Video summary. Required. Max 2048 characters for Google.', 'schema-markup-generator'),
                'auto' => 'post_excerpt',
            ],
            'thumbnailUrl' => [
                'type' => 'image',
                'description' => __('Required. Preview image shown in search results. Min 120x120px recommended.', 'schema-markup-generator'),
            ],
            'contentUrl' => [
                'type' => 'url',
                'description' => __('Direct video file URL. Preferred if you host videos directly.', 'schema-markup-generator'),
            ],
            'embedUrl' => [
                'type' => 'url',
                'description' => __('Player embed URL (YouTube, Vimeo). Auto-detected from content if embedded.', 'schema-markup-generator'),
                'auto' => 'post_content',
            ],
            'duration' => [
                'type' => 'text',
                'description' => __('Video length (HH:MM:SS or seconds). Shown in video rich results.', 'schema-markup-generator'),
            ],
            'hasPart' => [
                'type' => 'repeater',
                'description' => __('Video Chapters. Creates clickable segments in Google Search and YouTube. Auto-extracted from timestamp patterns in content.', 'schema-markup-generator'),
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
                'auto' => 'tags',
            ],
            'inLanguage' => [
                'type' => 'text',
                'description' => __('Video language (e.g., "it", "en"). Auto-detected from WordPress settings.', 'schema-markup-generator'),
            ],
            'interactionCount' => [
                'type' => 'number',
                'description' => __('View count. Social proof signal in video results.', 'schema-markup-generator'),
            ],
            'transcript' => [
                'type' => 'textarea',
                'description' => __('Full video transcript. Improves accessibility and AI content understanding.', 'schema-markup-generator'),
            ],
            'isFamilyFriendly' => [
                'type' => 'boolean',
                'description' => __('Safe for all audiences. Affects content filtering in search.', 'schema-markup-generator'),
            ],
            'requiresSubscription' => [
                'type' => 'boolean',
                'description' => __('Premium/paid content. Sets isAccessibleForFree accordingly.', 'schema-markup-generator'),
            ],
        ];
    }
}

