<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Schema\Types;

use flavor\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * VideoObject Schema
 *
 * For video content.
 *
 * @package flavor\SchemaMarkupGenerator\Schema\Types
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

    public function getRequiredProperties(): array
    {
        return ['name', 'description', 'thumbnailUrl', 'uploadDate'];
    }

    public function getRecommendedProperties(): array
    {
        return ['contentUrl', 'embedUrl', 'duration', 'publisher'];
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
            ],
            'duration' => [
                'type' => 'text',
                'description' => __('Video length (HH:MM:SS or seconds). Shown in video rich results.', 'schema-markup-generator'),
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
        ];
    }
}

