<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Integration\YouTubeIntegration;
use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * LearningResource Schema
 *
 * For educational resources like lessons, tutorials, quizzes.
 * Optimized for LLM understanding and SEO.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
 */
class LearningResourceSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'LearningResource';
    }

    public function getLabel(): string
    {
        return __('Learning Resource', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For educational resources like lessons, tutorials, and quizzes. Ideal for course content with LLM and AI search optimization.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post, $mapping);

        // Entity identification - critical for LLM understanding and cross-referencing
        $postUrl = $this->getPostUrl($post);
        $data['@id'] = $postUrl . '#learningresource';
        $data['url'] = $postUrl;
        $data['mainEntityOfPage'] = [
            '@type' => 'WebPage',
            '@id' => $postUrl,
        ];

        // Core properties
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);

        // Image
        $image = $this->getFeaturedImage($post);
        if ($image) {
            $data['image'] = $image;
        }

        // Video (if lesson contains video) - build early to use for type detection and duration
        $video = $this->buildVideo($post, $mapping);
        if ($video) {
            $data['video'] = $video;
        }

        // Detect content characteristics for auto-population
        $contentAnalysis = $this->analyzeContentCharacteristics($post);

        // Learning Resource Type (Lesson, Video, Quiz, Tutorial, etc.)
        // Auto-detected from content if not explicitly mapped
        $resourceType = $this->getMappedValue($post, $mapping, 'learningResourceType');
        if ($resourceType) {
            $data['learningResourceType'] = $resourceType;
        } else {
            $data['learningResourceType'] = $this->detectLearningResourceType($post, $video, $contentAnalysis);
        }

        // isPartOf - Link to parent Course (crucial for hierarchy)
        $parentCourse = $this->buildParentCourse($post, $mapping);
        if ($parentCourse) {
            $data['isPartOf'] = $parentCourse;
        }

        // Teaches - What this resource teaches (very important for LLMs)
        $teaches = $this->getMappedValue($post, $mapping, 'teaches');
        if ($teaches) {
            $data['teaches'] = $this->sanitizeSkillsList($teaches);
        }

        // Assesses - Skills/competencies assessed
        $assesses = $this->getMappedValue($post, $mapping, 'assesses');
        if ($assesses) {
            $data['assesses'] = $this->sanitizeSkillsList($assesses);
        }

        // Educational Level
        $educationalLevel = $this->getMappedValue($post, $mapping, 'educationalLevel');
        if ($educationalLevel) {
            $data['educationalLevel'] = $educationalLevel;
        }

        // Educational Use
        $educationalUse = $this->getMappedValue($post, $mapping, 'educationalUse');
        if ($educationalUse) {
            $data['educationalUse'] = $educationalUse;
        }

        // Time Required (ISO 8601 duration)
        // Auto-calculated from reading time + video duration if not mapped
        $timeRequired = $this->getMappedValue($post, $mapping, 'timeRequired');
        if ($timeRequired) {
            $data['timeRequired'] = $this->formatDuration($timeRequired);
        } else {
            $autoTimeRequired = $this->calculateAutoTimeRequired($post, $video);
            if ($autoTimeRequired > 0) {
                $data['timeRequired'] = $this->formatDuration($autoTimeRequired);
            }
        }

        // Interactivity Type - Auto-detected from content if not explicitly mapped
        $interactivityType = $this->getMappedValue($post, $mapping, 'interactivityType');
        if ($interactivityType) {
            $data['interactivityType'] = $interactivityType;
        } else {
            $data['interactivityType'] = $this->detectInteractivityType($video, $contentAnalysis);
        }

        // Language
        $inLanguage = $this->getMappedValue($post, $mapping, 'inLanguage');
        if ($inLanguage) {
            $data['inLanguage'] = $inLanguage;
        } else {
            $data['inLanguage'] = get_bloginfo('language');
        }

        // Author/Creator
        $author = $this->getMappedValue($post, $mapping, 'author');
        if ($author) {
            $data['author'] = [
                '@type' => 'Person',
                'name' => is_array($author) ? ($author['name'] ?? '') : $author,
            ];
        } else {
            $data['author'] = $this->getAuthor($post);
        }

        // Provider (usually the organization/school)
        $provider = $this->getMappedValue($post, $mapping, 'provider');
        if ($provider) {
            $data['provider'] = [
                '@type' => 'Organization',
                'name' => is_array($provider) ? ($provider['name'] ?? '') : $provider,
                'sameAs' => is_array($provider) ? ($provider['url'] ?? home_url('/')) : home_url('/'),
            ];
        } else {
            $data['provider'] = $this->getPublisher();
        }

        // Dates
        $data['dateCreated'] = $this->formatDate($post->post_date);
        $data['dateModified'] = $this->formatDate($post->post_modified);

        // Content URL (if it's a downloadable resource)
        $contentUrl = $this->getMappedValue($post, $mapping, 'contentUrl');
        if ($contentUrl) {
            $data['contentUrl'] = $contentUrl;
        }

        // Encoding Format (MIME type)
        $encodingFormat = $this->getMappedValue($post, $mapping, 'encodingFormat');
        if ($encodingFormat) {
            $data['encodingFormat'] = $encodingFormat;
        }

        // Keywords (useful for LLMs)
        $keywords = $this->getMappedValue($post, $mapping, 'keywords');
        if ($keywords) {
            $data['keywords'] = is_array($keywords) ? implode(', ', $keywords) : $keywords;
        } else {
            // Try to get from post tags
            $tags = get_the_tags($post->ID);
            if ($tags && !is_wp_error($tags)) {
                $data['keywords'] = implode(', ', wp_list_pluck($tags, 'name'));
            }
        }

        // About - Main topics (helps LLMs understand context)
        $about = $this->getMappedValue($post, $mapping, 'about');
        if ($about) {
            $data['about'] = $this->buildAboutTopics($about);
        }

        // Audience
        $audience = $this->getMappedValue($post, $mapping, 'audience');
        if ($audience) {
            $data['audience'] = [
                '@type' => 'EducationalAudience',
                'educationalRole' => is_array($audience) ? ($audience['role'] ?? $audience) : $audience,
            ];
        }

        // Position in course (lesson number)
        $position = $this->buildPosition($post, $mapping);
        if ($position !== null) {
            $data['position'] = $position;
        }

        // Learning Outcome
        $learningOutcome = $this->getMappedValue($post, $mapping, 'competencyRequired');
        if ($learningOutcome) {
            $data['competencyRequired'] = $learningOutcome;
        }

        /**
         * Filter learning resource schema data
         */
        $data = apply_filters('smg_learning_resource_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Build parent course reference
     * 
     * Returns a complete Course object with all required properties for Google validation:
     * - @type, @id, name, url (identification)
     * - description (required by Google)
     * - provider (required by Google)
     * - hasCourseInstance with offers (required by Google)
     */
    private function buildParentCourse(WP_Post $post, array $mapping): ?array
    {
        // Check for mapped course
        $course = $this->getMappedValue($post, $mapping, 'isPartOf');

        $courseData = null;

        if ($course) {
            if (is_array($course)) {
                $courseUrl = $course['url'] ?? '';
                $courseData = [
                    '@type' => 'Course',
                    '@id' => $courseUrl . '#course',
                    'name' => $course['name'] ?? '',
                    'url' => $courseUrl,
                    'description' => $course['description'] ?? ($course['name'] ?? ''),
                ];
            } elseif (is_numeric($course)) {
                // If it's a post ID
                $coursePost = get_post((int) $course);
                if ($coursePost) {
                    $courseUrl = get_permalink($coursePost);
                    $courseData = [
                        '@type' => 'Course',
                        '@id' => $courseUrl . '#course',
                        'name' => html_entity_decode(get_the_title($coursePost), ENT_QUOTES, 'UTF-8'),
                        'url' => $courseUrl,
                        'description' => $this->getPostDescription($coursePost),
                    ];
                }
            } else {
                // If it's just a name - minimal data
                $courseData = [
                    '@type' => 'Course',
                    'name' => $course,
                    'description' => $course,
                ];
            }
        }

        // Try to get parent post if hierarchical
        if (!$courseData && $post->post_parent) {
            $parent = get_post($post->post_parent);
            if ($parent) {
                $parentUrl = get_permalink($parent);
                $courseData = [
                    '@type' => 'Course',
                    '@id' => $parentUrl . '#course',
                    'name' => html_entity_decode(get_the_title($parent), ENT_QUOTES, 'UTF-8'),
                    'url' => $parentUrl,
                    'description' => $this->getPostDescription($parent),
                ];
            }
        }

        /**
         * Filter to get parent course from integrations (e.g., MemberPress Courses, LearnDash)
         *
         * @param array|null $parentCourse Current parent course data
         * @param WP_Post    $post         The lesson post
         * @param array      $mapping      Field mapping configuration
         */
        if (!$courseData) {
            $courseData = apply_filters('smg_learning_resource_parent_course', null, $post, $mapping);
        }

        // Ensure all required properties are present for Google validation
        if ($courseData) {
            $courseData = $this->ensureCourseRequiredProperties($courseData);
        }

        return $courseData;
    }

    /**
     * Ensure course has all required properties for Google validation
     * 
     * Google requires: description, provider, offers, hasCourseInstance
     */
    private function ensureCourseRequiredProperties(array $courseData): array
    {
        // Ensure description exists
        if (empty($courseData['description'])) {
            $courseData['description'] = $courseData['name'] ?? '';
        }

        // Add provider if not present (required by Google)
        if (empty($courseData['provider'])) {
            $courseData['provider'] = $this->getPublisher();
        }

        // Add hasCourseInstance with offers if not present (required by Google)
        if (empty($courseData['hasCourseInstance'])) {
            $courseData['hasCourseInstance'] = [
                '@type' => 'CourseInstance',
                'courseMode' => 'online',
                'offers' => [
                    '@type' => 'Offer',
                    'price' => 0,
                    'priceCurrency' => $this->getSiteCurrency(),
                    'availability' => 'https://schema.org/InStock',
                ],
            ];
        }

        return $courseData;
    }

    /**
     * Build position/order within course
     *
     * Priority:
     * 1. Mapped value from field mapping
     * 2. Auto-detected from integrations (e.g., MemberPress Courses)
     */
    private function buildPosition(WP_Post $post, array $mapping): ?int
    {
        // Check for mapped position
        $position = $this->getMappedValue($post, $mapping, 'position');

        if ($position !== null && $position !== '') {
            return (int) $position;
        }

        /**
         * Filter to get lesson position from integrations (e.g., MemberPress Courses, LearnDash)
         *
         * @param int|null $position Current position (may be null)
         * @param WP_Post  $post     The lesson post
         * @param array    $mapping  Field mapping configuration
         */
        return apply_filters('smg_learning_resource_position', null, $post, $mapping);
    }

    /**
     * Build video data if present
     * 
     * First checks for mapped video URL, then auto-extracts from content
     */
    private function buildVideo(WP_Post $post, array $mapping): ?array
    {
        $videoUrl = $this->getMappedValue($post, $mapping, 'videoUrl');

        // If no mapped video, try to extract from content
        if (!$videoUrl) {
            $extractedVideo = $this->extractVideoFromContent($post);
            if ($extractedVideo) {
                return $extractedVideo;
            }
            return null;
        }

        $video = [
            '@type' => 'VideoObject',
            'contentUrl' => $videoUrl,
            'name' => $this->getMappedValue($post, $mapping, 'videoName')
                ?: html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
        ];

        $videoDuration = $this->getMappedValue($post, $mapping, 'videoDuration');
        if ($videoDuration) {
            $video['duration'] = $this->formatDuration($videoDuration);
        }

        $videoThumbnail = $this->getMappedValue($post, $mapping, 'videoThumbnail');
        if ($videoThumbnail) {
            $video['thumbnailUrl'] = $videoThumbnail;
        } else {
            $image = $this->getFeaturedImage($post);
            if ($image) {
                $video['thumbnailUrl'] = $image['url'];
            }
        }

        $uploadDate = $this->getMappedValue($post, $mapping, 'videoUploadDate');
        if ($uploadDate) {
            $video['uploadDate'] = $uploadDate;
        } else {
            $video['uploadDate'] = $this->formatDate($post->post_date);
        }

        // Add video chapters if available
        $chapters = $this->getMappedValue($post, $mapping, 'videoChapters');
        if (is_array($chapters) && !empty($chapters)) {
            $video['hasPart'] = $this->buildVideoChapters($chapters);
        }

        return $video;
    }

    /**
     * Extract video from post content (YouTube, Vimeo, WordPress embeds)
     */
    private function extractVideoFromContent(WP_Post $post): ?array
    {
        $content = $post->post_content;
        
        // Try to extract YouTube video
        $youtubeData = $this->extractYouTubeVideo($content);
        if ($youtubeData) {
            return $this->buildVideoFromEmbed($youtubeData, $post);
        }

        // Try to extract Vimeo video
        $vimeoData = $this->extractVimeoVideo($content);
        if ($vimeoData) {
            return $this->buildVideoFromEmbed($vimeoData, $post);
        }

        // Try WordPress embed blocks
        $embedData = $this->extractWordPressEmbed($content);
        if ($embedData) {
            return $this->buildVideoFromEmbed($embedData, $post);
        }

        return null;
    }

    /**
     * Extract YouTube video data from content
     */
    private function extractYouTubeVideo(string $content): ?array
    {
        // Match various YouTube URL patterns
        $patterns = [
            // Standard YouTube URLs
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            // Short YouTube URLs
            '/(?:https?:\/\/)?youtu\.be\/([a-zA-Z0-9_-]{11})/',
            // YouTube embed URLs
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            // YouTube in iframe
            '/<iframe[^>]+src=["\'](?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})[^"\']*["\'][^>]*>/',
            // WordPress YouTube embed block
            '/<!-- wp:embed {"url":"https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})[^"]*","type":"video","providerNameSlug":"youtube"/',
            // WordPress core-embed/youtube block
            '/<!-- wp:core-embed\/youtube {"url":"https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $videoId = $matches[1];
                return [
                    'platform' => 'youtube',
                    'id' => $videoId,
                    'embedUrl' => 'https://www.youtube.com/embed/' . $videoId,
                    'contentUrl' => 'https://www.youtube.com/watch?v=' . $videoId,
                    'thumbnailUrl' => 'https://img.youtube.com/vi/' . $videoId . '/maxresdefault.jpg',
                ];
            }
        }

        return null;
    }

    /**
     * Extract Vimeo video data from content
     */
    private function extractVimeoVideo(string $content): ?array
    {
        $patterns = [
            // Standard Vimeo URLs
            '/(?:https?:\/\/)?(?:www\.)?vimeo\.com\/(\d+)/',
            // Vimeo player embed
            '/(?:https?:\/\/)?player\.vimeo\.com\/video\/(\d+)/',
            // Vimeo in iframe
            '/<iframe[^>]+src=["\'](?:https?:\/\/)?player\.vimeo\.com\/video\/(\d+)[^"\']*["\'][^>]*>/',
            // WordPress Vimeo embed block
            '/<!-- wp:embed {"url":"https?:\/\/(?:www\.)?vimeo\.com\/(\d+)[^"]*","type":"video","providerNameSlug":"vimeo"/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $videoId = $matches[1];
                return [
                    'platform' => 'vimeo',
                    'id' => $videoId,
                    'embedUrl' => 'https://player.vimeo.com/video/' . $videoId,
                    'contentUrl' => 'https://vimeo.com/' . $videoId,
                    'thumbnailUrl' => null, // Will be fetched via oEmbed
                ];
            }
        }

        return null;
    }

    /**
     * Extract WordPress embed block data
     */
    private function extractWordPressEmbed(string $content): ?array
    {
        // Match WordPress embed blocks for video providers
        if (preg_match('/<!-- wp:embed {"url":"([^"]+)"[^}]*"type":"video"/', $content, $matches)) {
            $url = $matches[1];
            
            // Determine platform from URL
            if (strpos($url, 'youtube') !== false || strpos($url, 'youtu.be') !== false) {
                return $this->extractYouTubeVideo($url);
            }
            if (strpos($url, 'vimeo') !== false) {
                return $this->extractVimeoVideo($url);
            }

            return [
                'platform' => 'other',
                'embedUrl' => $url,
                'contentUrl' => $url,
            ];
        }

        return null;
    }

    /**
     * Build VideoObject from extracted embed data
     */
    private function buildVideoFromEmbed(array $embedData, WP_Post $post): array
    {
        $video = [
            '@type' => 'VideoObject',
            'name' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
            'description' => $this->getPostDescription($post, 2048), // VideoObject limit per Google
            'uploadDate' => $this->formatDate($post->post_date),
        ];

        if (!empty($embedData['embedUrl'])) {
            $video['embedUrl'] = $embedData['embedUrl'];
        }

        if (!empty($embedData['contentUrl'])) {
            $video['contentUrl'] = $embedData['contentUrl'];
        }

        // Try to get thumbnail
        if (!empty($embedData['thumbnailUrl'])) {
            $video['thumbnailUrl'] = $embedData['thumbnailUrl'];
        } else {
            // Try featured image as fallback
            $image = $this->getFeaturedImage($post);
            if ($image) {
                $video['thumbnailUrl'] = $image['url'];
            }
        }

        // For YouTube videos, try YouTube Data API first (more accurate than oEmbed)
        if (!empty($embedData['platform']) && $embedData['platform'] === 'youtube' && !empty($embedData['id'])) {
            $youtubeDuration = $this->getYouTubeVideoDuration($embedData['id']);
            if ($youtubeDuration > 0) {
                $video['duration'] = $this->formatVideoDuration($youtubeDuration);
            }
        }

        // Try to fetch additional data via oEmbed (duration for Vimeo, author, etc.)
        $oEmbedData = $this->fetchOEmbedData($embedData);
        if ($oEmbedData) {
            // Only use oEmbed duration if we don't have one yet (YouTube API takes precedence)
            if (empty($video['duration']) && !empty($oEmbedData['duration'])) {
                $video['duration'] = $this->formatVideoDuration($oEmbedData['duration']);
            }
            if (!empty($oEmbedData['thumbnail_url']) && empty($video['thumbnailUrl'])) {
                $video['thumbnailUrl'] = $oEmbedData['thumbnail_url'];
            }
            if (!empty($oEmbedData['author_name'])) {
                $video['author'] = [
                    '@type' => 'Person',
                    'name' => $oEmbedData['author_name'],
                ];
            }
        }

        // Try to get chapters - priority order:
        // 1. Filter (from integrations like MemberPress Courses)
        // 2. Extract from content
        $chapters = apply_filters('smg_video_chapters', null, $post, $embedData);
        if (empty($chapters)) {
            $chapters = $this->extractChaptersFromContent($post->post_content, $embedData, $post);
        }
        if (!empty($chapters)) {
            $video['hasPart'] = $chapters;
        }

        // Try to get transcript - priority order:
        // 1. Meta field 'lesson_transcription' (MemberPress Courses)
        // 2. ACF field 'lesson_transcription'
        // 3. Extract from post content (heading/timestamp/class patterns)
        $transcript = $this->getTranscript($post);
        if ($transcript) {
            $video['transcript'] = $transcript;
        }

        return $video;
    }

    /**
     * Get video transcript from various sources
     *
     * Priority:
     * 1. Meta field 'lesson_transcription' (MemberPress Courses)
     * 2. ACF field 'lesson_transcription' or 'transcript'
     * 3. Extract from post content (heading/timestamp/class patterns)
     *
     * @param WP_Post $post The post object
     * @return string|null Cleaned transcript text or null
     */
    private function getTranscript(WP_Post $post): ?string
    {
        $maxLength = 5000;

        // 1. Check meta field 'lesson_transcription' (MemberPress Courses stores it here)
        $metaTranscript = get_post_meta($post->ID, 'lesson_transcription', true);
        if (!empty($metaTranscript)) {
            $cleaned = $this->cleanTranscriptText($metaTranscript);
            if (!empty($cleaned) && strlen($cleaned) > 50) {
                return $this->truncateTranscript($cleaned, $maxLength);
            }
        }

        // 2. Check ACF fields if available
        if (function_exists('get_field')) {
            $acfFields = ['lesson_transcription', 'transcript', 'video_transcript', 'transcription'];
            foreach ($acfFields as $fieldName) {
                $acfValue = get_field($fieldName, $post->ID);
                if (!empty($acfValue) && is_string($acfValue)) {
                    $cleaned = $this->cleanTranscriptText($acfValue);
                    if (!empty($cleaned) && strlen($cleaned) > 50) {
                        return $this->truncateTranscript($cleaned, $maxLength);
                    }
                }
            }
        }

        // 3. Fallback: extract from post content
        return $this->extractTranscriptFromContent($post->post_content);
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
        $maxLength = 5000;

        // Pattern 1: Look for heading with transcript keywords
        // Matches: <h2>Video Transcription</h2>, <h3>Transcript</h3>, <h4>Trascrizione</h4>, etc.
        $headingPattern = '/<h[2-6][^>]*>.*?(?:Video\s+)?(?:Transcript(?:ion)?|Trascrizione|Full\s+Text).*?<\/h[2-6]>/is';

        if (preg_match($headingPattern, $content, $headingMatch, PREG_OFFSET_CAPTURE)) {
            $startPos = $headingMatch[0][1] + strlen($headingMatch[0][0]);
            $remainingContent = substr($content, $startPos);
            
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
     */
    private function cleanTranscriptText(string $text): string
    {
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\[\d{2}:\d{2}:\d{2}(?:\.\d{2})?\]/', '', $text);
        $text = preg_replace('/(?:^|\n)\s*-?\s*Speaker\s*\d+\s*:?\s*/i', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Truncate transcript to maximum length at word boundary
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

    /**
     * Get YouTube video duration using YouTube Data API v3
     *
     * Uses the YouTubeIntegration if API key is configured.
     * Results are cached for performance.
     *
     * @param string $videoId YouTube video ID
     * @return int Duration in seconds (0 if not available)
     */
    private function getYouTubeVideoDuration(string $videoId): int
    {
        $youtubeIntegration = $this->getYouTubeIntegration();

        if (!$youtubeIntegration || !$youtubeIntegration->isAvailable()) {
            return 0;
        }

        return $youtubeIntegration->getVideoDuration($videoId);
    }

    /**
     * Get YouTube Integration instance (lazy loaded)
     *
     * @return YouTubeIntegration|null
     */
    private function getYouTubeIntegration(): ?YouTubeIntegration
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new YouTubeIntegration();
        }

        return $instance;
    }

    /**
     * Fetch oEmbed data for video metadata
     */
    private function fetchOEmbedData(array $embedData): ?array
    {
        if (empty($embedData['contentUrl'])) {
            return null;
        }

        // Use WordPress oEmbed to get data
        $oEmbed = _wp_oembed_get_object();
        $provider = $oEmbed->get_provider($embedData['contentUrl']);
        
        if (!$provider) {
            return null;
        }

        $data = $oEmbed->fetch($provider, $embedData['contentUrl']);
        
        if (!$data) {
            return null;
        }

        return (array) $data;
    }

    /**
     * Format video duration from seconds to ISO 8601
     */
    private function formatVideoDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $duration = 'PT';
        if ($hours > 0) {
            $duration .= "{$hours}H";
        }
        if ($minutes > 0) {
            $duration .= "{$minutes}M";
        }
        if ($secs > 0) {
            $duration .= "{$secs}S";
        }

        return $duration;
    }

    /**
     * Extract video chapters from content
     * 
     * Looks for timestamp patterns in content like:
     * - 0:00 Introduction
     * - 1:30 - Getting Started
     * - 00:05:30 Advanced Topics
     *
     * @param string       $content   Post content
     * @param array        $embedData Embed data with platform info
     * @param WP_Post|null $post      The post object for URL generation
     * @return array Array of chapter Clip objects
     */
    private function extractChaptersFromContent(string $content, array $embedData, ?WP_Post $post = null): array
    {
        $chapters = [];
        
        // Pattern to match chapter timestamps
        // Matches: 0:00, 1:30, 00:05:30, etc. followed by chapter title
        $pattern = '/(?:^|\n|<br\s*\/?>\s*|<li[^>]*>)\s*(?:(\d{1,2}):)?(\d{1,2}):(\d{2})\s*[-–—:]?\s*([^\n<]+)/mi';
        
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            // Only include if we find at least 2 chapters (to avoid false positives)
            if (count($matches) < 2) {
                return [];
            }

            $position = 1;
            
            foreach ($matches as $match) {
                $hours = !empty($match[1]) ? (int) $match[1] : 0;
                $minutes = (int) $match[2];
                $seconds = (int) $match[3];
                $title = trim(strip_tags($match[4]));
                
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
                
                // Generate chapter URL - priority order:
                // 1. Page URL with #t=offset (for embedded videos on learning pages)
                // 2. YouTube URL with &t=offset (for YouTube embeds)
                // 3. No URL
                if ($post) {
                    // Use page URL with hash fragment for timestamp
                    $chapter['url'] = get_permalink($post) . '#t=' . $startOffset;
                } elseif (!empty($embedData['platform']) && $embedData['platform'] === 'youtube' && !empty($embedData['contentUrl'])) {
                    $chapter['url'] = $embedData['contentUrl'] . '&t=' . $startOffset;
                }
                
                $chapters[] = $chapter;
            }
        }
        
        return $chapters;
    }

    /**
     * Build video chapters from mapped data
     */
    private function buildVideoChapters(array $chapters): array
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
     * Convert skills/competencies to array format
     * 
     * Values are already sanitized by getMappedValue().
     * This method handles comma-separated strings and filters invalid values.
     */
    private function sanitizeSkillsList(mixed $value): array
    {
        // Null or empty - already filtered by getMappedValue
        if ($value === null || $value === '') {
            return [];
        }

        $items = [];

        if (is_string($value)) {
            // Split by comma if contains commas
            if (strpos($value, ',') !== false) {
                $items = array_map('trim', explode(',', $value));
            } else {
                $items = [$value];
            }
        } elseif (is_array($value)) {
            // Flatten array to strings
            foreach ($value as $item) {
                if (is_string($item) && !empty(trim($item))) {
                    $items[] = trim($item);
                }
            }
        }

        // Filter out any remaining invalid values
        return array_values(array_filter($items, function ($item) {
            if (empty($item) || mb_strlen($item) < 3) {
                return false;
            }
            // Skip ACF field names and timestamps that might slip through
            if (preg_match('/^field_[a-f0-9]+$/i', $item) || preg_match('/^\d+:\d+$/', $item)) {
                return false;
            }
            return true;
        }));
    }

    /**
     * Build about topics
     */
    private function buildAboutTopics(mixed $about): array
    {
        if (!is_array($about)) {
            return [
                '@type' => 'Thing',
                'name' => $about,
            ];
        }

        $topics = [];
        foreach ($about as $topic) {
            if (is_array($topic)) {
                $topics[] = [
                    '@type' => $topic['type'] ?? 'Thing',
                    'name' => $topic['name'] ?? '',
                ];
            } else {
                $topics[] = [
                    '@type' => 'Thing',
                    'name' => $topic,
                ];
            }
        }

        return count($topics) === 1 ? $topics[0] : $topics;
    }

    /**
     * Format duration to ISO 8601
     */
    private function formatDuration(mixed $duration): string
    {
        if (is_string($duration) && str_starts_with($duration, 'P')) {
            return $duration;
        }

        // Assume minutes if numeric
        if (is_numeric($duration)) {
            $minutes = (int) $duration;
            if ($minutes >= 60) {
                $hours = floor($minutes / 60);
                $mins = $minutes % 60;
                return $mins > 0 ? "PT{$hours}H{$mins}M" : "PT{$hours}H";
            }
            return "PT{$minutes}M";
        }

        return "PT{$duration}";
    }

    /**
     * Calculate time required automatically from content
     *
     * Combines:
     * - Reading time: estimated at 200 words per minute (average web reading speed)
     * - Video duration: extracted from embedded videos
     *
     * @param WP_Post    $post  The post object
     * @param array|null $video Video data if available
     * @return int Total time in minutes (0 if cannot be calculated)
     */
    private function calculateAutoTimeRequired(WP_Post $post, ?array $video): int
    {
        $totalMinutes = 0;

        // 1. Calculate reading time from text content
        // Strip HTML, shortcodes, and blocks to get plain text
        $content = $post->post_content;
        $content = strip_shortcodes($content);
        $content = wp_strip_all_tags($content);
        $content = preg_replace('/\s+/', ' ', $content); // Normalize whitespace

        $wordCount = str_word_count($content);

        // Average reading speed: 200 words per minute (web content)
        // Use 200 wpm as it accounts for comprehension, not just scanning
        if ($wordCount > 0) {
            $readingMinutes = (int) ceil($wordCount / 200);
            $totalMinutes += $readingMinutes;
        }

        // 2. Add video duration if present
        if ($video && !empty($video['duration'])) {
            $videoSeconds = $this->parseIsoDurationToSeconds($video['duration']);
            if ($videoSeconds > 0) {
                $videoMinutes = (int) ceil($videoSeconds / 60);
                $totalMinutes += $videoMinutes;
            }
        }

        /**
         * Filter auto-calculated time required
         *
         * @param int     $totalMinutes Calculated time in minutes
         * @param WP_Post $post         The post object
         * @param array   $breakdown    Breakdown of time calculation
         */
        return (int) apply_filters('smg_learning_resource_auto_time_required', $totalMinutes, $post, [
            'word_count' => $wordCount ?? 0,
            'reading_minutes' => $readingMinutes ?? 0,
            'video_minutes' => $videoMinutes ?? 0,
        ]);
    }

    /**
     * Parse ISO 8601 duration to seconds
     *
     * @param string $duration ISO 8601 duration (e.g., PT1H30M45S)
     * @return int Duration in seconds
     */
    private function parseIsoDurationToSeconds(string $duration): int
    {
        if (!str_starts_with($duration, 'P')) {
            return 0;
        }

        $seconds = 0;

        // Match hours, minutes, seconds
        if (preg_match('/(\d+)H/', $duration, $matches)) {
            $seconds += (int) $matches[1] * 3600;
        }
        if (preg_match('/(\d+)M/', $duration, $matches)) {
            $seconds += (int) $matches[1] * 60;
        }
        if (preg_match('/(\d+)S/', $duration, $matches)) {
            $seconds += (int) $matches[1];
        }

        return $seconds;
    }

    /**
     * Analyze content characteristics for auto-detection
     *
     * Examines the post content to determine:
     * - Has quiz elements (forms, quiz blocks, shortcodes)
     * - Has step-by-step tutorial structure
     * - Has interactive elements
     * - Content complexity (word count, heading structure)
     *
     * @param WP_Post $post The post to analyze
     * @return array Content analysis results
     */
    private function analyzeContentCharacteristics(WP_Post $post): array
    {
        $content = $post->post_content;

        return [
            'has_quiz' => $this->detectQuizContent($content),
            'has_tutorial_structure' => $this->detectTutorialStructure($content),
            'has_interactive_elements' => $this->detectInteractiveElements($content),
            'word_count' => str_word_count(wp_strip_all_tags($content)),
            'heading_count' => preg_match_all('/<h[2-4][^>]*>/i', $content),
            'list_count' => preg_match_all('/<[ou]l[^>]*>/i', $content),
            'code_block_count' => preg_match_all('/```|<pre[^>]*>|<code[^>]*>|<!-- wp:code/i', $content),
        ];
    }

    /**
     * Detect quiz/assessment content
     *
     * Looks for:
     * - Quiz plugin shortcodes (Quiz Master, LearnDash, etc.)
     * - Form plugins (Gravity Forms, WPForms, Formidable)
     * - Quiz Gutenberg blocks
     * - MemberPress Courses quiz elements
     *
     * @param string $content Post content
     * @return bool True if quiz elements detected
     */
    private function detectQuizContent(string $content): bool
    {
        // Quiz plugin shortcodes
        $quizPatterns = [
            // Quiz plugins
            '/\[quiz[^\]]*\]/i',
            '/\[qmn_quiz[^\]]*\]/i',
            '/\[ld_quiz[^\]]*\]/i',
            '/\[watu[^\]]*\]/i',
            '/\[qsm[^\]]*\]/i',
            '/\[question[^\]]*\]/i',
            // Form plugins that might be used for quizzes
            '/\[gravityform[^\]]*\]/i',
            '/\[wpforms[^\]]*\]/i',
            '/\[formidable[^\]]*\]/i',
            '/\[ninja_form[^\]]*\]/i',
            // Quiz blocks
            '/<!-- wp:quiz/i',
            '/<!-- wp:learndash\/ld-quiz/i',
            '/<!-- wp:wpforms/i',
            '/<!-- wp:gravityforms/i',
            // MemberPress specific
            '/<!-- wp:mpcs-quiz/i',
            '/\[mpcs-quiz[^\]]*\]/i',
        ];

        foreach ($quizPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect tutorial/step-by-step structure
     *
     * A tutorial typically has:
     * - Numbered headings (Step 1, Step 2, etc.)
     * - Ordered lists with instructions
     * - Multiple code blocks with explanations
     * - Sequential heading structure
     *
     * @param string $content Post content
     * @return bool True if tutorial structure detected
     */
    private function detectTutorialStructure(string $content): bool
    {
        $score = 0;

        // Check for step-based headings
        $stepPatterns = [
            '/step\s*[0-9]+/i',
            '/fase\s*[0-9]+/i',  // Italian
            '/passo\s*[0-9]+/i', // Italian
            '/parte\s*[0-9]+/i', // Italian
            '/part\s*[0-9]+/i',
            '/#[0-9]+[:\.\s]/i',
        ];

        foreach ($stepPatterns as $pattern) {
            if (preg_match_all($pattern, $content) >= 2) {
                $score += 2;
                break;
            }
        }

        // Check for "how to" style title/content
        if (preg_match('/how\s+to|come\s+fare|guida\s+a|tutorial/i', $content)) {
            $score += 1;
        }

        // Multiple ordered lists suggest step-by-step content
        $orderedListCount = preg_match_all('/<ol[^>]*>/i', $content);
        if ($orderedListCount >= 2) {
            $score += 1;
        }

        // Multiple code blocks suggest technical tutorial
        $codeBlockCount = preg_match_all('/```|<pre[^>]*>|<!-- wp:code/i', $content);
        if ($codeBlockCount >= 3) {
            $score += 1;
        }

        // Sequential numbered headings (h2 or h3 with numbers)
        if (preg_match_all('/<h[23][^>]*>\s*[0-9]+[\.\)]/i', $content) >= 3) {
            $score += 2;
        }

        return $score >= 3;
    }

    /**
     * Detect interactive elements in content
     *
     * @param string $content Post content
     * @return bool True if interactive elements detected
     */
    private function detectInteractiveElements(string $content): bool
    {
        $interactivePatterns = [
            // Forms
            '/<form[^>]*>/i',
            // Interactive blocks
            '/<!-- wp:button/i',
            '/<!-- wp:file/i',
            // Accordion/tabs (common in lessons)
            '/<!-- wp:accordion/i',
            '/<!-- wp:tabs/i',
            // Downloadable resources
            '/\[download[^\]]*\]/i',
            '/\[file[^\]]*\]/i',
            // Interactive elements
            '/data-interactive/i',
            '/class="[^"]*interactive/i',
        ];

        foreach ($interactivePatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect learning resource type from content analysis
     *
     * Priority order:
     * 1. Quiz - If quiz/assessment elements detected
     * 2. Video - If video is the primary content (>80% video duration vs reading)
     * 3. Exercise - If has interactive elements with code blocks
     * 4. Tutorial - If has step-by-step structure
     * 5. Lecture - If video exists but with substantial text
     * 6. Reading - If primarily text with minimal interactive elements
     * 7. Lesson - Default fallback
     *
     * @param WP_Post    $post            The post
     * @param array|null $video           Video data if present
     * @param array      $contentAnalysis Content analysis results
     * @return string Learning resource type
     */
    private function detectLearningResourceType(WP_Post $post, ?array $video, array $contentAnalysis): string
    {
        // 1. Quiz takes priority - it's a specific content type
        if ($contentAnalysis['has_quiz']) {
            return 'Quiz';
        }

        $hasVideo = !empty($video);
        $wordCount = $contentAnalysis['word_count'];

        // Calculate video vs reading time ratio
        $videoDominant = false;
        if ($hasVideo && !empty($video['duration'])) {
            $videoSeconds = $this->parseIsoDurationToSeconds($video['duration']);
            $readingSeconds = ($wordCount / 200) * 60; // 200 words/min

            // Video is dominant if it's >80% of total time
            if ($videoSeconds > 0) {
                $totalSeconds = $videoSeconds + $readingSeconds;
                $videoDominant = ($videoSeconds / $totalSeconds) > 0.8;
            }
        }

        // 2. Video - Primarily video content
        if ($hasVideo && $videoDominant) {
            return 'Video';
        }

        // 3. Exercise - Interactive with code (programming exercises)
        if ($contentAnalysis['has_interactive_elements'] && $contentAnalysis['code_block_count'] >= 2) {
            return 'Exercise';
        }

        // 4. Tutorial - Step-by-step guides
        if ($contentAnalysis['has_tutorial_structure']) {
            return 'Tutorial';
        }

        // 5. Lecture - Video + substantial text (mixed content)
        if ($hasVideo && $wordCount > 300) {
            return 'Lecture';
        }

        // 6. Reading - Primarily text, well-structured with headings
        if (!$hasVideo && $wordCount > 500 && $contentAnalysis['heading_count'] >= 2) {
            return 'Reading';
        }

        // 7. Default: Lesson
        return 'Lesson';
    }

    /**
     * Detect interactivity type from content analysis
     *
     * - active: Learner actively participates (quizzes, exercises, forms)
     * - expositive: One-way content delivery (video, reading)
     * - mixed: Combination of both
     *
     * @param array|null $video           Video data if present
     * @param array      $contentAnalysis Content analysis results
     * @return string Interactivity type
     */
    private function detectInteractivityType(?array $video, array $contentAnalysis): string
    {
        $hasActiveElements = $contentAnalysis['has_quiz']
            || $contentAnalysis['has_interactive_elements']
            || $contentAnalysis['code_block_count'] >= 2;

        $hasExpositiveElements = !empty($video) || $contentAnalysis['word_count'] > 200;

        if ($hasActiveElements && $hasExpositiveElements) {
            return 'mixed';
        }

        if ($hasActiveElements) {
            return 'active';
        }

        return 'expositive';
    }

    public function getRequiredProperties(): array
    {
        return ['name', 'description', 'learningResourceType'];
    }

    public function getRecommendedProperties(): array
    {
        return ['isPartOf', 'teaches', 'timeRequired', 'educationalLevel', 'author', 'image'];
    }

    public function getPropertyDefinitions(): array
    {
        return array_merge(self::getAdditionalTypeDefinition(), [
            'name' => [
                'type' => 'text',
                'description' => __('Lesson/resource title. Displayed in educational content rich results.', 'schema-markup-generator'),
                'description_long' => __('The title of the learning resource. This is the primary identifier displayed in educational content search results. Be descriptive and include the main topic covered.', 'schema-markup-generator'),
                'example' => __('Introduction to Python Variables, Understanding Gamma Levels in Options Trading, SEO Fundamentals: On-Page Optimization', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/name',
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('What this lesson covers. Helps search engines and AI understand the content.', 'schema-markup-generator'),
                'description_long' => __('A description of what the learning resource covers and what students will learn. This is crucial for AI systems to understand and match the content with relevant user queries.', 'schema-markup-generator'),
                'example' => __('Learn the fundamentals of Python variables, including data types, naming conventions, and best practices. Perfect for beginners with no prior programming experience.', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/description',
                'auto' => 'post_excerpt',
            ],
            'learningResourceType' => [
                'type' => 'select',
                'description' => __('Content format. Helps categorize and match with user search intent.', 'schema-markup-generator'),
                'description_long' => __('The type or format of the learning resource. This helps search engines categorize your content and match it with users looking for specific types of educational materials.', 'schema-markup-generator'),
                'example' => __('Lesson for written content, Video for video lessons, Quiz for assessments, Tutorial for step-by-step guides', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/learningResourceType',
                'options' => ['Lesson', 'Video', 'Quiz', 'Tutorial', 'Exercise', 'Lecture', 'Reading', 'Assignment'],
                'auto' => 'content_analysis',
                'auto_integration' => 'Content Analysis',
                'auto_description' => __('Auto-detected: Video (embedded videos), Quiz (forms/assessments), Tutorial (step-by-step), Reading (text-heavy), Lecture (video+text)', 'schema-markup-generator'),
            ],
            'isPartOf' => [
                'type' => 'post',
                'description' => __('Parent course link. Critical for establishing content hierarchy for LLMs.', 'schema-markup-generator'),
                'description_long' => __('Links this resource to its parent course. This is critical for establishing content hierarchy, helping LLMs understand the learning path and context of this resource within a larger curriculum.', 'schema-markup-generator'),
                'example' => __('Select the parent course from the dropdown, or leave empty for standalone resources', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/isPartOf',
                'auto' => 'integration',
                'auto_integration' => 'MemberPress Courses',
                'auto_description' => __('Detected from lesson hierarchy (lesson → section → course)', 'schema-markup-generator'),
            ],
            'teaches' => [
                'type' => 'textarea',
                'description' => __('CRITICAL for AI: Skills/concepts taught (comma-separated). LLMs use this to match with user queries.', 'schema-markup-generator'),
                'description_long' => __('The skills, concepts, or competencies that this resource teaches. This is one of the most important properties for AI matching - LLMs use this to determine if your content answers user queries about learning specific topics.', 'schema-markup-generator'),
                'example' => __('Python variables, Data types in Python, Variable naming conventions, Type conversion, Constants', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/teaches',
            ],
            'assesses' => [
                'type' => 'textarea',
                'description' => __('Competencies evaluated (for quizzes/tests). Helps match assessment-seeking users.', 'schema-markup-generator'),
                'description_long' => __('For quizzes, tests, or exercises: the competencies or skills being assessed. Helps match users specifically looking to test their knowledge on certain topics.', 'schema-markup-generator'),
                'example' => __('Understanding of variable scope, Ability to choose appropriate data types, Debugging variable-related errors', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/assesses',
            ],
            'timeRequired' => [
                'type' => 'number',
                'description' => __('Completion time in minutes. Helps users plan their learning sessions.', 'schema-markup-generator'),
                'description_long' => __('The estimated time to complete this learning resource, in minutes. Helps learners plan their study sessions and set expectations.', 'schema-markup-generator'),
                'example' => __('15, 30, 45, 60 (minutes)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/timeRequired',
                'auto' => 'calculated',
                'auto_description' => __('Auto-calculated from text reading time (~200 words/min) + video duration', 'schema-markup-generator'),
            ],
            'educationalLevel' => [
                'type' => 'select',
                'description' => __('Difficulty level. Helps match content to appropriate learners.', 'schema-markup-generator'),
                'description_long' => __('The difficulty or complexity level of the content. Helps match learners with appropriately challenging material and filters search results by skill level.', 'schema-markup-generator'),
                'example' => __('Beginner for newcomers, Intermediate for some experience, Advanced for proficient learners', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/educationalLevel',
                'options' => ['Beginner', 'Intermediate', 'Advanced', 'Expert'],
            ],
            'educationalUse' => [
                'type' => 'select',
                'description' => __('How content is meant to be used. Improves educational content categorization.', 'schema-markup-generator'),
                'description_long' => __('The intended educational use of the resource. Helps categorize content by purpose: instruction for teaching, assessment for testing, self-study for independent learning.', 'schema-markup-generator'),
                'example' => __('instruction for teaching materials, assessment for tests, self-study for independent learning', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/educationalUse',
                'options' => ['instruction', 'assessment', 'professional development', 'self-study'],
            ],
            'interactivityType' => [
                'type' => 'select',
                'description' => __('Engagement style. Helps match user preferences for learning formats.', 'schema-markup-generator'),
                'description_long' => __('The predominant mode of learning. Active means learners interact (quizzes, exercises), expositive means one-way presentation (videos, readings), mixed combines both.', 'schema-markup-generator'),
                'example' => __('active for interactive content, expositive for presentations/readings, mixed for combination', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/interactivityType',
                'options' => ['active', 'expositive', 'mixed'],
                'auto' => 'content_analysis',
                'auto_integration' => 'Content Analysis',
                'auto_description' => __('Auto-detected: active (quizzes/forms), expositive (video/reading), mixed (both)', 'schema-markup-generator'),
            ],
            'author' => [
                'type' => 'text',
                'description' => __('Content creator. Builds E-E-A-T signals for educational content.', 'schema-markup-generator'),
                'description_long' => __('The author or creator of the learning resource. Including author credentials helps establish E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) for educational content.', 'schema-markup-generator'),
                'example' => __('Dr. Jane Smith, John Doe (Senior Developer), Prof. Maria García', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/author',
            ],
            'provider' => [
                'type' => 'text',
                'description' => __('Organization offering the content. Adds institutional credibility.', 'schema-markup-generator'),
                'description_long' => __('The organization or institution providing the learning resource. Institutional backing adds credibility to educational content.', 'schema-markup-generator'),
                'example' => __('Your Academy, Stanford Online, Coursera, Internal Training Department', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/provider',
            ],
            'position' => [
                'type' => 'number',
                'description' => __('Order within course. Helps LLMs understand learning sequence.', 'schema-markup-generator'),
                'description_long' => __('The position or order of this resource within its parent course. Helps LLMs understand the learning sequence and prerequisites.', 'schema-markup-generator'),
                'example' => __('1, 2, 3 (lesson number within course)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/position',
                'auto' => 'integration',
                'auto_integration' => 'MemberPress Courses',
                'auto_description' => __('Detected from lesson order within section', 'schema-markup-generator'),
            ],
            'keywords' => [
                'type' => 'text',
                'description' => __('Topic keywords (comma-separated). Used by search engines and AI for indexing.', 'schema-markup-generator'),
                'description_long' => __('Keywords describing the content. Include both technical terms and common search phrases. Helps with SEO and AI content matching.', 'schema-markup-generator'),
                'example' => __('python basics, programming fundamentals, beginner programming, coding tutorial, variables', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/keywords',
            ],
            'videoUrl' => [
                'type' => 'url',
                'description' => __('Video content URL. Auto-extracted from YouTube/Vimeo embeds if not set.', 'schema-markup-generator'),
                'description_long' => __('The URL of a video associated with this learning resource. If you embed YouTube or Vimeo videos in your content, this is automatically extracted.', 'schema-markup-generator'),
                'example' => __('https://www.youtube.com/watch?v=..., https://vimeo.com/...', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/video',
                'auto' => 'post_content',
                'auto_description' => __('Auto-extracted from embedded YouTube/Vimeo videos in content', 'schema-markup-generator'),
            ],
            'videoDuration' => [
                'type' => 'number',
                'description' => __('Video length in minutes. Auto-fetched via YouTube API or oEmbed when possible.', 'schema-markup-generator'),
                'description_long' => __('The duration of the video content in minutes. For YouTube videos, this is automatically fetched via YouTube Data API (when configured). For Vimeo and other platforms, oEmbed is used.', 'schema-markup-generator'),
                'example' => __('15, 30, 60 (minutes)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/duration',
                'auto' => 'youtube_api',
                'auto_description' => __('Auto-fetched via YouTube Data API when configured, oEmbed as fallback', 'schema-markup-generator'),
            ],
            'videoChapters' => [
                'type' => 'repeater',
                'description' => __('Video chapters/segments. Auto-extracted from timestamp patterns in content (e.g., "0:00 Introduction").', 'schema-markup-generator'),
                'description_long' => __('Chapters or segments within the video. Creates clickable key moments in search results. Automatically extracted from timestamp patterns like "0:00 Introduction" in your content.', 'schema-markup-generator'),
                'example' => __('0:00 Introduction, 5:30 Key Concepts, 15:00 Practical Examples, 25:00 Summary', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/hasPart',
                'auto' => 'post_content',
                'auto_description' => __('Auto-extracted from timestamp patterns like "0:00 Introduction", "1:30 Getting Started"', 'schema-markup-generator'),
                'fields' => [
                    'time' => ['type' => 'text', 'description' => __('Start time (MM:SS or HH:MM:SS)', 'schema-markup-generator')],
                    'name' => ['type' => 'text', 'description' => __('Chapter title', 'schema-markup-generator')],
                ],
            ],
        ]);
    }
}

