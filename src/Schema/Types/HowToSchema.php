<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Integration\YouTubeIntegration;
use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * HowTo Schema
 *
 * For step-by-step guides and tutorials.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
 */
class HowToSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'HowTo';
    }

    public function getLabel(): string
    {
        return __('How-To Guide', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For step-by-step instructions and tutorials. Enables how-to rich results with steps.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post, $mapping);

        // Core properties
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);

        // Image (with fallback to custom fallback image or site favicon)
        $image = $this->getImageWithFallback($post);
        if ($image) {
            $data['image'] = $image;
        }

        // Total time
        $totalTime = $this->getMappedValue($post, $mapping, 'totalTime');
        if ($totalTime) {
            $data['totalTime'] = $this->formatDuration($totalTime);
        } else {
            // Try to extract time from content
            $extractedTime = $this->extractTimeFromContent($post);
            if ($extractedTime) {
                $data['totalTime'] = $extractedTime;
            }
        }

        // Estimated cost
        $estimatedCost = $this->getMappedValue($post, $mapping, 'estimatedCost');
        if ($estimatedCost) {
            $data['estimatedCost'] = [
                '@type' => 'MonetaryAmount',
                'currency' => $this->getMappedValue($post, $mapping, 'currency') ?: $this->getSiteCurrency(),
                'value' => (float) $estimatedCost,
            ];
        }

        // Supplies - only include if explicitly mapped with valid data
        $supplies = $this->getMappedValue($post, $mapping, 'supply');
        if (is_array($supplies) && !empty($supplies)) {
            $validSupplies = $this->filterValidItems($supplies);
            if (!empty($validSupplies)) {
                $data['supply'] = $this->buildSupplies($validSupplies);
            }
        }

        // Tools - only include if explicitly mapped with valid data
        $tools = $this->getMappedValue($post, $mapping, 'tool');
        if (is_array($tools) && !empty($tools)) {
            $validTools = $this->filterValidItems($tools);
            if (!empty($validTools)) {
                $data['tool'] = $this->buildTools($validTools);
            }
        }

        // Steps
        $steps = $this->getMappedValue($post, $mapping, 'steps');
        if (is_array($steps) && !empty($steps)) {
            $data['step'] = $this->buildSteps($steps);
        } else {
            // Try to extract steps from content
            $data['step'] = $this->extractStepsFromContent($post);
        }

        // Video (if HowTo contains video tutorial) - auto-extract from content
        $video = $this->buildVideo($post, $mapping);
        if ($video) {
            $data['video'] = $video;
        }

        /**
         * Filter HowTo schema data
         */
        $data = apply_filters('smg_howto_schema_data', $data, $post, $mapping);

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

        // Assume minutes if just a number
        if (is_numeric($duration)) {
            $hours = floor((int) $duration / 60);
            $minutes = (int) $duration % 60;

            if ($hours > 0) {
                return "PT{$hours}H{$minutes}M";
            }
            return "PT{$minutes}M";
        }

        return "PT{$duration}";
    }

    /**
     * Extract time duration from post content
     * 
     * Looks for patterns like:
     * - "tempo: 30 minuti", "time: 2 hours"
     * - "richiede 30 minuti", "takes 2 hours"  
     * - "durata: 1 ora", "duration: 45 minutes"
     * - "circa 30 minuti", "about 2 hours"
     */
    private function extractTimeFromContent(WP_Post $post): ?string
    {
        $content = $post->post_content;
        
        // Pattern to match time mentions in Italian and English
        $patterns = [
            // Italian patterns
            '/(?:tempo|durata|richiede|necessita|circa|in)\s*[:\-]?\s*(\d+)\s*(ore?|hours?|minuti?|minutes?|min)/iu',
            '/(\d+)\s*(ore?|hours?)\s*(?:e\s*)?(\d+)?\s*(minuti?|minutes?|min)?/iu',
            // English patterns  
            '/(?:time|duration|takes|requires|about|approximately)\s*[:\-]?\s*(\d+)\s*(hours?|minutes?|mins?|hrs?)/iu',
            '/(\d+)\s*(hours?|hrs?)\s*(?:and\s*)?(\d+)?\s*(minutes?|mins?)?/iu',
            // Generic number + time unit
            '/(\d+)\s*-\s*(\d+)\s*(ore?|hours?|minuti?|minutes?|min)/iu', // ranges like "20-30 minuti"
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $match)) {
                return $this->parseTimeMatch($match);
            }
        }

        return null;
    }

    /**
     * Parse regex match and convert to ISO 8601 duration
     */
    private function parseTimeMatch(array $match): ?string
    {
        $hours = 0;
        $minutes = 0;

        // Normalize unit names
        $unit1 = strtolower($match[2] ?? '');
        $value1 = (int) ($match[1] ?? 0);
        
        // Check if it's hours or minutes
        if (preg_match('/^(ore?|hours?|hrs?)$/i', $unit1)) {
            $hours = $value1;
            // Check for additional minutes
            if (!empty($match[3]) && is_numeric($match[3])) {
                $minutes = (int) $match[3];
            }
        } elseif (preg_match('/^(minuti?|minutes?|mins?)$/i', $unit1)) {
            $minutes = $value1;
        }

        // Handle range patterns (use average)
        if (!empty($match[2]) && is_numeric($match[1]) && is_numeric($match[2]) && !empty($match[3])) {
            $avgValue = ((int) $match[1] + (int) $match[2]) / 2;
            $unit = strtolower($match[3]);
            
            if (preg_match('/^(ore?|hours?|hrs?)$/i', $unit)) {
                $hours = (int) $avgValue;
                $minutes = (int) (($avgValue - $hours) * 60);
            } else {
                $minutes = (int) $avgValue;
            }
        }

        // Build ISO 8601 duration
        if ($hours === 0 && $minutes === 0) {
            return null;
        }

        $duration = 'PT';
        if ($hours > 0) {
            $duration .= "{$hours}H";
        }
        if ($minutes > 0) {
            $duration .= "{$minutes}M";
        }

        return $duration;
    }

    /**
     * Build supplies array
     */
    private function buildSupplies(array $supplies): array
    {
        $items = [];

        foreach ($supplies as $supply) {
            if (is_array($supply)) {
                $items[] = [
                    '@type' => 'HowToSupply',
                    'name' => $supply['name'] ?? $supply[0] ?? '',
                ];
            } else {
                $items[] = [
                    '@type' => 'HowToSupply',
                    'name' => $supply,
                ];
            }
        }

        return $items;
    }

    /**
     * Build tools array
     */
    private function buildTools(array $tools): array
    {
        $items = [];

        foreach ($tools as $tool) {
            if (is_array($tool)) {
                $items[] = [
                    '@type' => 'HowToTool',
                    'name' => $tool['name'] ?? $tool[0] ?? '',
                ];
            } else {
                $items[] = [
                    '@type' => 'HowToTool',
                    'name' => $tool,
                ];
            }
        }

        return $items;
    }

    /**
     * Filter and validate items for supply/tool arrays
     * 
     * Removes invalid values like:
     * - Pure numbers or IDs
     * - ACF field names (field_*)
     * - HTML content
     * - Default/empty values
     * - Values that are too short or too long
     */
    private function filterValidItems(array $items): array
    {
        $validItems = [];

        foreach ($items as $item) {
            $name = is_array($item) ? ($item['name'] ?? $item[0] ?? '') : $item;
            
            if (!is_string($name)) {
                continue;
            }

            $name = trim($name);

            // Skip empty values
            if (empty($name)) {
                continue;
            }

            // Skip pure numeric values (likely IDs)
            if (is_numeric($name)) {
                continue;
            }

            // Skip values that look like timestamps or IDs (e.g., "1761735360:2")
            if (preg_match('/^\d+:\d+$/', $name)) {
                continue;
            }

            // Skip ACF field names (field_*)
            if (preg_match('/^field_[a-f0-9]+$/i', $name)) {
                continue;
            }

            // Skip values that are just "default"
            if (strtolower($name) === 'default') {
                continue;
            }

            // Skip values containing HTML tags (likely content, not item names)
            if (preg_match('/<[^>]+>/', $name)) {
                continue;
            }

            // Skip values that are too short (< 2 chars) - likely garbage
            if (mb_strlen($name) < 2) {
                continue;
            }

            // Skip values that are too long (> 200 chars) - likely content, not item names
            if (mb_strlen($name) > 200) {
                continue;
            }

            // Skip values that look like descriptions (contain multiple sentences)
            if (substr_count($name, '.') > 2 || substr_count($name, ',') > 3) {
                continue;
            }

            $validItems[] = is_array($item) ? $item : $name;
        }

        return $validItems;
    }

    /**
     * Build steps array
     */
    private function buildSteps(array $steps): array
    {
        $items = [];
        $position = 1;

        foreach ($steps as $step) {
            if (is_array($step)) {
                $stepData = [
                    '@type' => 'HowToStep',
                    'position' => $position,
                    'name' => $step['name'] ?? $step['title'] ?? '',
                    'text' => $step['text'] ?? $step['description'] ?? '',
                ];

                if (!empty($step['image'])) {
                    $stepData['image'] = $step['image'];
                }

                if (!empty($step['url'])) {
                    $stepData['url'] = $step['url'];
                }

                $items[] = $stepData;
            } else {
                $items[] = [
                    '@type' => 'HowToStep',
                    'position' => $position,
                    'text' => $step,
                ];
            }

            $position++;
        }

        return $items;
    }

    /**
     * Extract steps from post content
     * 
     * Intelligently parses content to extract HowTo steps from:
     * 1. Gutenberg blocks (wp:list, wp:heading sections)
     * 2. Ordered lists (<ol>)
     * 3. Headings with numbered patterns (Step 1, Passo 1, 1., etc.)
     * 4. Any heading structure (H2/H3/H4) with content
     */
    private function extractStepsFromContent(WP_Post $post): array
    {
        $content = $post->post_content;
        $steps = [];

        // Strategy 1: Try to extract from Gutenberg ordered list blocks
        $steps = $this->extractFromGutenbergBlocks($content);
        if (!empty($steps)) {
            return $steps;
        }

        // Strategy 2: Try ordered lists (<ol>) specifically
        $steps = $this->extractFromOrderedLists($content);
        if (!empty($steps)) {
            return $steps;
        }

        // Strategy 3: Try headings with step patterns (Step 1, Passo 1, 1., etc.)
        $steps = $this->extractFromNumberedHeadings($content);
        if (!empty($steps)) {
            return $steps;
        }

        // Strategy 4: Fallback to any H2/H3/H4 headings with content
        $steps = $this->extractFromHeadingSections($content);

        return $steps;
    }

    /**
     * Extract steps from Gutenberg blocks
     */
    private function extractFromGutenbergBlocks(string $content): array
    {
        $steps = [];
        $position = 1;

        // Check if content has Gutenberg blocks
        if (!has_blocks($content)) {
            return [];
        }

        $blocks = parse_blocks($content);

        foreach ($blocks as $block) {
            // Look for ordered list blocks
            if ($block['blockName'] === 'core/list' && 
                isset($block['attrs']['ordered']) && 
                $block['attrs']['ordered'] === true) {
                
                $listSteps = $this->parseListBlockItems($block['innerHTML']);
                foreach ($listSteps as $stepText) {
                    $steps[] = [
                        '@type' => 'HowToStep',
                        'position' => $position++,
                        'text' => $stepText,
                    ];
                }
            }

            // Look for heading + content patterns that look like steps
            if (in_array($block['blockName'], ['core/heading', 'core/group'])) {
                $stepData = $this->parseHeadingBlock($block, $blocks);
                if ($stepData) {
                    $stepData['position'] = $position++;
                    $steps[] = $stepData;
                }
            }
        }

        return $steps;
    }

    /**
     * Parse list block items
     */
    private function parseListBlockItems(string $html): array
    {
        $items = [];
        
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $matches)) {
            foreach ($matches[1] as $match) {
                $text = wp_strip_all_tags($match);
                $text = trim($text);
                if (!empty($text)) {
                    $items[] = $text;
                }
            }
        }

        return $items;
    }

    /**
     * Parse heading block for step data
     */
    private function parseHeadingBlock(array $block, array $allBlocks): ?array
    {
        $heading = wp_strip_all_tags($block['innerHTML'] ?? '');
        $heading = trim($heading);

        // Check if heading looks like a step
        if (!$this->looksLikeStepHeading($heading)) {
            return null;
        }

        // Clean the heading to get the step name
        $name = $this->cleanStepHeading($heading);

        return [
            '@type' => 'HowToStep',
            'name' => $name,
            'text' => $name, // Will be enhanced if we find following content
        ];
    }

    /**
     * Extract steps from ordered lists (<ol>)
     */
    private function extractFromOrderedLists(string $content): array
    {
        $steps = [];
        $position = 1;

        // Find all ordered lists
        if (preg_match_all('/<ol[^>]*>(.*?)<\/ol>/is', $content, $olMatches)) {
            foreach ($olMatches[1] as $olContent) {
                // Extract list items
                if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $olContent, $liMatches)) {
                    foreach ($liMatches[1] as $liContent) {
                        $text = wp_strip_all_tags($liContent);
                        $text = trim($text);
                        
                        if (!empty($text)) {
                            // Check if the list item contains a strong/bold element (likely a step name)
                            $name = null;
                            if (preg_match('/<(?:strong|b)[^>]*>(.*?)<\/(?:strong|b)>/is', $liContent, $strongMatch)) {
                                $name = wp_strip_all_tags($strongMatch[1]);
                                // Remove the name from the full text to get the description
                                $textWithoutName = str_replace($strongMatch[0], '', $liContent);
                                $text = trim(wp_strip_all_tags($textWithoutName));
                            }

                            $stepData = [
                                '@type' => 'HowToStep',
                                'position' => $position++,
                                'text' => !empty($text) ? $text : ($name ?? ''),
                            ];

                            if ($name && $name !== $stepData['text']) {
                                $stepData['name'] = $name;
                            }

                            // Check for images in the list item
                            if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $liContent, $imgMatch)) {
                                $stepData['image'] = $imgMatch[1];
                            }

                            $steps[] = $stepData;
                        }
                    }
                }
            }
        }

        return $steps;
    }

    /**
     * Extract steps from numbered headings (Step 1, Passo 1, 1., etc.)
     */
    private function extractFromNumberedHeadings(string $content): array
    {
        $steps = [];
        $position = 1;

        // Pattern to match headings with step-like patterns
        // Matches: Step 1, Passo 1, Fase 1, 1., 1), #1, etc.
        $stepPattern = '/
            <h([2-4])[^>]*>
            \s*
            (?:
                (?:step|passo|fase|punto|fase)\s*[:\-]?\s*(\d+)[:\-.\)]?\s*(.*)
                |
                (\d+)\s*[:\-.\)]\s*(.*)
                |
                \#(\d+)[:\-.\)]?\s*(.*)
            )
            <\/h\1>
            \s*
            (.*?)
            (?=<h[2-4]|$)
        /isx';

        if (preg_match_all($stepPattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                // Determine which capture group matched
                $name = '';
                $followingContent = trim(wp_strip_all_tags($match[8] ?? ''));

                if (!empty($match[3])) {
                    // "Step X: Title" format
                    $name = trim(wp_strip_all_tags($match[3]));
                } elseif (!empty($match[5])) {
                    // "1. Title" format
                    $name = trim(wp_strip_all_tags($match[5]));
                } elseif (!empty($match[7])) {
                    // "#1 Title" format
                    $name = trim(wp_strip_all_tags($match[7]));
                }

                if (empty($name) && empty($followingContent)) {
                    continue;
                }

                $stepData = [
                    '@type' => 'HowToStep',
                    'position' => $position++,
                ];

                if (!empty($name)) {
                    $stepData['name'] = $name;
                }

                $stepData['text'] = !empty($followingContent) ? $followingContent : $name;

                // Check for images in the section
                $sectionContent = $match[8] ?? '';
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $sectionContent, $imgMatch)) {
                    $stepData['image'] = $imgMatch[1];
                }

                $steps[] = $stepData;
            }
        }

        return $steps;
    }

    /**
     * Extract steps from any H2/H3/H4 heading sections (fallback)
     * 
     * Treats each heading as a step in sequence, regardless of numbering.
     * This is useful for educational content where headings represent logical sections.
     */
    private function extractFromHeadingSections(string $content): array
    {
        $steps = [];
        $position = 1;

        // First, try to render blocks if it's Gutenberg content
        if (has_blocks($content)) {
            $content = do_blocks($content);
        }

        // Apply content filters to ensure shortcodes are processed
        $content = apply_filters('the_content', $content);

        // Match any heading followed by content until the next heading of same or higher level
        // Using a more robust pattern that handles nested content
        $pattern = '/<h([2-4])[^>]*>(.*?)<\/h\1>(.*?)(?=<h[2-4][^>]*>|$)/is';
        
        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $headingLevel = (int) $match[1];
                $name = wp_strip_all_tags($match[2]);
                $name = html_entity_decode(trim($name), ENT_QUOTES, 'UTF-8');
                
                // Get the content following this heading
                $sectionContent = $match[3];
                $text = $this->extractStepText($sectionContent);

                // Skip if name is empty or looks like a generic section
                if (empty($name) || $this->isGenericHeading($name)) {
                    continue;
                }

                // Skip very short headings (likely navigation or UI elements)
                if (mb_strlen($name) < 3) {
                    continue;
                }

                $stepData = [
                    '@type' => 'HowToStep',
                    'position' => $position++,
                    'name' => $name,
                ];

                // Add text if available, otherwise use the name as text (required by schema.org)
                if (!empty($text)) {
                    // Limit text to reasonable length (500 chars)
                    $stepData['text'] = mb_strlen($text) > 500 
                        ? mb_substr($text, 0, 497) . '...' 
                        : $text;
                } else {
                    $stepData['text'] = $name;
                }

                // Check for images in the section
                if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $sectionContent, $imgMatch)) {
                    $stepData['image'] = $imgMatch[1];
                }

                $steps[] = $stepData;
            }
        }

        return $steps;
    }

    /**
     * Extract clean text from step section content
     */
    private function extractStepText(string $html): string
    {
        // Remove script and style tags completely
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        
        // Strip all HTML tags
        $text = wp_strip_all_tags($html);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }

    /**
     * Check if heading looks like a step heading
     */
    private function looksLikeStepHeading(string $heading): bool
    {
        $patterns = [
            '/^(?:step|passo|fase|punto)\s*\d/i',
            '/^\d+\s*[:\-.\)]/i',
            '/^\#\d+/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $heading)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clean step heading to extract the actual title
     */
    private function cleanStepHeading(string $heading): string
    {
        // Remove step prefixes
        $cleaned = preg_replace('/^(?:step|passo|fase|punto)\s*\d+\s*[:\-.\)]?\s*/i', '', $heading);
        $cleaned = preg_replace('/^\d+\s*[:\-.\)]\s*/i', '', $cleaned);
        $cleaned = preg_replace('/^\#\d+\s*[:\-.\)]?\s*/i', '', $cleaned);

        return trim($cleaned) ?: $heading;
    }

    /**
     * Check if heading is a generic section heading (not a step)
     */
    private function isGenericHeading(string $heading): bool
    {
        $genericHeadings = [
            'introduction', 'introduzione',
            'conclusion', 'conclusione', 'conclusioni',
            'summary', 'sommario', 'riepilogo',
            'overview', 'panoramica',
            'prerequisites', 'prerequisiti', 'requisiti',
            'materials', 'materiali',
            'tools', 'strumenti', 'attrezzi',
            'supplies', 'forniture',
            'tips', 'consigli', 'suggerimenti',
            'notes', 'note',
            'warnings', 'avvertenze', 'attenzione',
            'faq', 'domande frequenti',
            'related', 'correlati',
        ];

        $headingLower = strtolower(trim($heading));

        foreach ($genericHeadings as $generic) {
            if ($headingLower === $generic || str_starts_with($headingLower, $generic . ':')) {
                return true;
            }
        }

        return false;
    }

    public function getRequiredProperties(): array
    {
        return ['name', 'step'];
    }

    public function getRecommendedProperties(): array
    {
        return ['description', 'image', 'totalTime', 'supply', 'tool'];
    }

    /**
     * Build video data if present in content
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
            $video['duration'] = $this->formatVideoDuration((int) $videoDuration * 60);
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

        $video['uploadDate'] = $this->formatDate($post->post_date);

        return $video;
    }

    /**
     * Extract video from post content (YouTube, Vimeo)
     */
    private function extractVideoFromContent(WP_Post $post): ?array
    {
        $content = $post->post_content;

        // Try YouTube
        $youtubeData = $this->extractYouTubeVideo($content);
        if ($youtubeData) {
            return $this->buildVideoFromEmbed($youtubeData, $post);
        }

        // Try Vimeo
        $vimeoData = $this->extractVimeoVideo($content);
        if ($vimeoData) {
            return $this->buildVideoFromEmbed($vimeoData, $post);
        }

        return null;
    }

    /**
     * Extract YouTube video data from content
     */
    private function extractYouTubeVideo(string $content): ?array
    {
        $patterns = [
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            '/(?:https?:\/\/)?youtu\.be\/([a-zA-Z0-9_-]{11})/',
            '/(?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            '/<iframe[^>]+src=["\'](?:https?:\/\/)?(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})[^"\']*["\'][^>]*>/',
            '/<!-- wp:embed {"url":"https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})[^"]*","type":"video","providerNameSlug":"youtube"/',
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
            '/(?:https?:\/\/)?(?:www\.)?vimeo\.com\/(\d+)/',
            '/(?:https?:\/\/)?player\.vimeo\.com\/video\/(\d+)/',
            '/<iframe[^>]+src=["\'](?:https?:\/\/)?player\.vimeo\.com\/video\/(\d+)[^"\']*["\'][^>]*>/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $videoId = $matches[1];
                return [
                    'platform' => 'vimeo',
                    'id' => $videoId,
                    'embedUrl' => 'https://player.vimeo.com/video/' . $videoId,
                    'contentUrl' => 'https://vimeo.com/' . $videoId,
                    'thumbnailUrl' => null,
                ];
            }
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
            'description' => $this->getPostDescription($post, 2048),
            'uploadDate' => $this->formatDate($post->post_date),
        ];

        if (!empty($embedData['embedUrl'])) {
            $video['embedUrl'] = $embedData['embedUrl'];
        }

        if (!empty($embedData['contentUrl'])) {
            $video['contentUrl'] = $embedData['contentUrl'];
        }

        // Thumbnail
        if (!empty($embedData['thumbnailUrl'])) {
            $video['thumbnailUrl'] = $embedData['thumbnailUrl'];
        } else {
            $image = $this->getFeaturedImage($post);
            if ($image) {
                $video['thumbnailUrl'] = $image['url'];
            }
        }

        // Duration from YouTube API
        if (!empty($embedData['platform']) && $embedData['platform'] === 'youtube' && !empty($embedData['id'])) {
            $youtubeDuration = $this->getYouTubeVideoDuration($embedData['id']);
            if ($youtubeDuration > 0) {
                $video['duration'] = $this->formatVideoDuration($youtubeDuration);
            }
        }

        // Try oEmbed for additional data
        $oEmbedData = $this->fetchOEmbedData($embedData);
        if ($oEmbedData) {
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

        // Extract transcript from content
        $transcript = $this->extractTranscriptFromContent($post->post_content);
        if ($transcript) {
            $video['transcript'] = $transcript;
        }

        return $video;
    }

    /**
     * Get YouTube video duration using YouTube Data API v3
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
     * Get YouTube Integration instance
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
     * Extract video transcript from post content
     */
    private function extractTranscriptFromContent(string $content): ?string
    {
        $maxLength = 5000;

        // Pattern 1: Heading with transcript keywords
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

        // Pattern 2: Timestamp patterns [HH:MM:SS]
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

        // Pattern 3: CSS class for transcript
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
     * Clean transcript text
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
     * Truncate transcript to maximum length
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

    public function getPropertyDefinitions(): array
    {
        return array_merge(self::getAdditionalTypeDefinition(), [
            'name' => [
                'type' => 'text',
                'required' => true,
                'description' => __('Guide title. Displayed as the main heading in how-to rich results.', 'schema-markup-generator'),
                'description_long' => __('The title of your how-to guide. Should clearly describe what the user will learn to do. Start with action verbs like "How to", "Build", "Create", "Make", etc.', 'schema-markup-generator'),
                'example' => __('How to Change a Flat Tire, Build a Raised Garden Bed, Create a Budget Spreadsheet', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/name',
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('What this guide helps you accomplish. Shown in search result snippets.', 'schema-markup-generator'),
                'description_long' => __('A brief summary of what this guide teaches. Include the skill level required and what the reader will be able to do after completing it.', 'schema-markup-generator'),
                'example' => __('Learn how to change a flat tire in under 15 minutes with this beginner-friendly guide. No special tools required.', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/description',
                'auto' => 'post_excerpt',
            ],
            'totalTime' => [
                'type' => 'text',
                'description' => __('Total time to complete (in minutes). Shown in rich results to set user expectations.', 'schema-markup-generator'),
                'description_long' => __('The total time needed to complete all steps. This is prominently displayed in how-to rich results and helps users decide if they have time to follow the guide.', 'schema-markup-generator'),
                'example' => __('30 (minutes), 60, 120', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/totalTime',
                'auto' => 'post_content',
                'auto_description' => __('Auto-extracted if content mentions duration (e.g. "takes 30 minutes", "tempo: 2 ore")', 'schema-markup-generator'),
            ],
            'estimatedCost' => [
                'type' => 'number',
                'description' => __('Approximate cost. Helps users decide before clicking through.', 'schema-markup-generator'),
                'description_long' => __('The estimated cost to complete the project. This can include materials, supplies, or any expenses. Helps users evaluate if the project fits their budget.', 'schema-markup-generator'),
                'example' => __('25.00, 150.00, 0 (for free projects)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/estimatedCost',
            ],
            'supply' => [
                'type' => 'repeater',
                'description' => __('Materials needed. Displayed as a list in how-to rich results.', 'schema-markup-generator'),
                'description_long' => __('Materials or supplies that are consumed during the process (things you use up). Google may display these as a checklist in how-to rich results.', 'schema-markup-generator'),
                'example' => __('2 cups flour, Wood screws (16 pieces), Sandpaper (120 grit)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/supply',
            ],
            'tool' => [
                'type' => 'repeater',
                'description' => __('Tools/equipment required. Shown alongside supplies in rich results.', 'schema-markup-generator'),
                'description_long' => __('Tools or equipment needed to complete the task. Unlike supplies, tools are not consumed. Include any specialized equipment users might need to acquire.', 'schema-markup-generator'),
                'example' => __('Phillips screwdriver, Electric drill, Measuring tape, Safety goggles', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/tool',
            ],
            'steps' => [
                'type' => 'repeater',
                'required' => true,
                'description' => __('Step-by-step instructions. Core content for how-to rich results display.', 'schema-markup-generator'),
                'description_long' => __('The sequence of steps to complete the task. Each step should be clear and actionable. Google displays these as expandable accordion items in how-to rich results.', 'schema-markup-generator'),
                'example' => __('Step 1: Gather your materials... Step 2: Prepare the workspace... Step 3: Begin assembly...', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/step',
                'auto' => 'post_content',
                'auto_description' => __('Auto-extracted from content (ordered lists, numbered headings, or H2/H3/H4 sections)', 'schema-markup-generator'),
                'fields' => [
                    'name' => ['type' => 'text', 'description' => __('Brief step title. Shown as step heading.', 'schema-markup-generator')],
                    'text' => ['type' => 'textarea', 'description' => __('Detailed instructions for this step.', 'schema-markup-generator')],
                ],
            ],
            'videoUrl' => [
                'type' => 'url',
                'description' => __('Tutorial video URL. Auto-extracted from YouTube/Vimeo embeds.', 'schema-markup-generator'),
                'description_long' => __('URL of a video tutorial for this how-to guide. If you embed YouTube or Vimeo videos in your content, this is automatically extracted.', 'schema-markup-generator'),
                'example' => __('https://www.youtube.com/watch?v=...', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/video',
                'auto' => 'post_content',
                'auto_description' => __('Auto-extracted from embedded YouTube/Vimeo videos with transcript', 'schema-markup-generator'),
            ],
        ]);
    }
}

