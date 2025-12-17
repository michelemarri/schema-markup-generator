<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Integration\YouTubeIntegration;
use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Recipe Schema
 *
 * For recipes with ingredients and instructions.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
 */
class RecipeSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'Recipe';
    }

    public function getLabel(): string
    {
        return __('Recipe', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For recipes with ingredients and instructions. Enables recipe rich results with images, ratings, and cook time.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post, $mapping);

        // Core properties
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);
        $data['url'] = $this->getPostUrl($post);

        // Author
        $data['author'] = $this->getAuthor($post);

        // Dates
        $data['datePublished'] = $this->formatDate($post->post_date_gmt);

        // Image (with fallback to custom fallback image or site favicon)
        $image = $this->getImageWithFallback($post);
        if ($image) {
            $data['image'] = $image['url'];
        }

        // Times
        $prepTime = $this->getMappedValue($post, $mapping, 'prepTime');
        if ($prepTime) {
            $data['prepTime'] = $this->formatDuration($prepTime);
        }

        $cookTime = $this->getMappedValue($post, $mapping, 'cookTime');
        if ($cookTime) {
            $data['cookTime'] = $this->formatDuration($cookTime);
        }

        $totalTime = $this->getMappedValue($post, $mapping, 'totalTime');
        if ($totalTime) {
            $data['totalTime'] = $this->formatDuration($totalTime);
        }

        // Yield/servings
        $yield = $this->getMappedValue($post, $mapping, 'recipeYield');
        if ($yield) {
            $data['recipeYield'] = $yield;
        }

        // Category and cuisine
        $category = $this->getMappedValue($post, $mapping, 'recipeCategory');
        if ($category) {
            $data['recipeCategory'] = $category;
        }

        $cuisine = $this->getMappedValue($post, $mapping, 'recipeCuisine');
        if ($cuisine) {
            $data['recipeCuisine'] = $cuisine;
        }

        // Ingredients
        $ingredients = $this->getMappedValue($post, $mapping, 'recipeIngredient');
        if (is_array($ingredients) && !empty($ingredients)) {
            $data['recipeIngredient'] = $this->flattenIngredients($ingredients);
        }

        // Instructions
        $instructions = $this->getMappedValue($post, $mapping, 'recipeInstructions');
        if (is_array($instructions) && !empty($instructions)) {
            $data['recipeInstructions'] = $this->buildInstructions($instructions);
        }

        // Nutrition
        $nutrition = $this->buildNutrition($post, $mapping);
        if (!empty($nutrition)) {
            $data['nutrition'] = $nutrition;
        }

        // Rating
        $rating = $this->getMappedValue($post, $mapping, 'ratingValue');
        if ($rating) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (float) $rating,
                'ratingCount' => (int) ($this->getMappedValue($post, $mapping, 'ratingCount') ?: 1),
            ];
        }

        // Keywords
        $keywords = $this->getMappedValue($post, $mapping, 'keywords');
        if ($keywords) {
            $data['keywords'] = is_array($keywords) ? implode(', ', $keywords) : $keywords;
        }

        // Video (if recipe contains video tutorial) - auto-extract from content
        $video = $this->buildVideo($post, $mapping);
        if ($video) {
            $data['video'] = $video;
        }

        /**
         * Filter recipe schema data
         */
        $data = apply_filters('smg_recipe_schema_data', $data, $post, $mapping);

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
     * Flatten ingredients array to strings
     */
    private function flattenIngredients(array $ingredients): array
    {
        $result = [];

        foreach ($ingredients as $ingredient) {
            if (is_array($ingredient)) {
                // Handle structured ingredient data
                $parts = [];
                if (!empty($ingredient['quantity'])) {
                    $parts[] = $ingredient['quantity'];
                }
                if (!empty($ingredient['unit'])) {
                    $parts[] = $ingredient['unit'];
                }
                if (!empty($ingredient['name'])) {
                    $parts[] = $ingredient['name'];
                }
                if (!empty($parts)) {
                    $result[] = implode(' ', $parts);
                } elseif (!empty($ingredient['ingredient'])) {
                    $result[] = $ingredient['ingredient'];
                }
            } else {
                $result[] = (string) $ingredient;
            }
        }

        return $result;
    }

    /**
     * Build instructions array
     */
    private function buildInstructions(array $instructions): array
    {
        $result = [];

        foreach ($instructions as $index => $instruction) {
            if (is_array($instruction)) {
                $step = [
                    '@type' => 'HowToStep',
                    'position' => $index + 1,
                ];

                if (!empty($instruction['name'])) {
                    $step['name'] = $instruction['name'];
                }
                if (!empty($instruction['text'])) {
                    $step['text'] = $instruction['text'];
                } elseif (!empty($instruction['instruction'])) {
                    $step['text'] = $instruction['instruction'];
                }
                if (!empty($instruction['image'])) {
                    $step['image'] = $instruction['image'];
                }

                $result[] = $step;
            } else {
                $result[] = [
                    '@type' => 'HowToStep',
                    'position' => $index + 1,
                    'text' => (string) $instruction,
                ];
            }
        }

        return $result;
    }

    /**
     * Build nutrition data
     */
    private function buildNutrition(WP_Post $post, array $mapping): array
    {
        $nutrition = ['@type' => 'NutritionInformation'];
        $hasData = false;

        $fields = [
            'calories' => 'calories',
            'carbohydrateContent' => 'carbohydrateContent',
            'proteinContent' => 'proteinContent',
            'fatContent' => 'fatContent',
            'fiberContent' => 'fiberContent',
            'sugarContent' => 'sugarContent',
            'sodiumContent' => 'sodiumContent',
        ];

        foreach ($fields as $field => $property) {
            $value = $this->getMappedValue($post, $mapping, $field);
            if ($value) {
                $nutrition[$property] = $value;
                $hasData = true;
            }
        }

        return $hasData ? $nutrition : [];
    }

    public function getRequiredProperties(): array
    {
        return ['name', 'image', 'recipeIngredient', 'recipeInstructions'];
    }

    public function getRecommendedProperties(): array
    {
        return ['author', 'prepTime', 'cookTime', 'totalTime', 'recipeYield', 'nutrition', 'aggregateRating'];
    }

    public function getPropertyDefinitions(): array
    {
        return array_merge(self::getAdditionalTypeDefinition(), [
            'name' => [
                'type' => 'text',
                'required' => true,
                'description' => __('Recipe title. Shown prominently in recipe rich results.', 'schema-markup-generator'),
                'description_long' => __('The name of the dish. This is the primary text shown in recipe rich results. Be descriptive but concise - include key details like "Easy", "Homemade", or cooking method.', 'schema-markup-generator'),
                'example' => __('Classic Italian Carbonara, Easy 30-Minute Chicken Stir-Fry, Grandma\'s Apple Pie', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/name',
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('What makes this recipe special. Displayed in search results.', 'schema-markup-generator'),
                'description_long' => __('A short summary of the recipe. Describe what makes it special, the flavor profile, or why someone should try it. This appears in search snippets.', 'schema-markup-generator'),
                'example' => __('A creamy, authentic Roman pasta dish made with eggs, pecorino cheese, guanciale, and black pepper. Ready in just 20 minutes!', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/description',
                'auto' => 'post_excerpt',
            ],
            'prepTime' => [
                'type' => 'number',
                'description' => __('Prep time in minutes. Displayed separately in recipe cards.', 'schema-markup-generator'),
                'description_long' => __('The time required to prepare the ingredients before cooking begins. Enter the number in minutes - it will be converted to ISO 8601 duration format automatically.', 'schema-markup-generator'),
                'example' => __('15, 30, 45 (minutes)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/prepTime',
            ],
            'cookTime' => [
                'type' => 'number',
                'description' => __('Cooking time in minutes. Shown in recipe rich results.', 'schema-markup-generator'),
                'description_long' => __('The active cooking time. This is the time spent actually cooking, baking, or grilling. Does not include prep time or waiting time.', 'schema-markup-generator'),
                'example' => __('20, 45, 90 (minutes)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/cookTime',
            ],
            'totalTime' => [
                'type' => 'number',
                'description' => __('Total time in minutes. Primary time shown in search results.', 'schema-markup-generator'),
                'description_long' => __('The total time from start to finish, including prep, cooking, resting, and any other time. This is the primary time displayed in recipe rich results.', 'schema-markup-generator'),
                'example' => __('35, 60, 120 (minutes)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/totalTime',
            ],
            'recipeYield' => [
                'type' => 'text',
                'description' => __('Number of servings (e.g., "4 portions"). Helps with meal planning searches.', 'schema-markup-generator'),
                'description_long' => __('The quantity produced by the recipe. Can be the number of servings or the number of items (e.g., "24 cookies"). Be specific for meal planning searches.', 'schema-markup-generator'),
                'example' => __('4 servings, 6 portions, 12 cupcakes, 1 loaf', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/recipeYield',
            ],
            'recipeCategory' => [
                'type' => 'text',
                'description' => __('Meal type (Breakfast, Lunch, Dessert, etc.). Used for filtered searches.', 'schema-markup-generator'),
                'description_long' => __('The type of meal or course. Users often filter by category when searching for recipes, so accurate categorization improves discoverability.', 'schema-markup-generator'),
                'example' => __('Dinner, Breakfast, Dessert, Appetizer, Side Dish, Snack', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/recipeCategory',
            ],
            'recipeCuisine' => [
                'type' => 'text',
                'description' => __('Cuisine style (Italian, Mexican, etc.). Enables cuisine-specific discovery.', 'schema-markup-generator'),
                'description_long' => __('The cuisine or cultural origin of the recipe. This helps users searching for specific cuisines find your recipe.', 'schema-markup-generator'),
                'example' => __('Italian, Mexican, Japanese, Mediterranean, American, Indian', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/recipeCuisine',
            ],
            'recipeIngredient' => [
                'type' => 'repeater',
                'required' => true,
                'description' => __('Required. Complete ingredient list. Core content for recipe rich results.', 'schema-markup-generator'),
                'description_long' => __('The list of ingredients needed. Each ingredient should be a single text string including quantity, unit, and ingredient name. Be specific about brands or varieties when relevant.', 'schema-markup-generator'),
                'example' => __('200g spaghetti, 4 large eggs, 100g pecorino romano cheese (grated), 150g guanciale', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/recipeIngredient',
            ],
            'recipeInstructions' => [
                'type' => 'repeater',
                'required' => true,
                'description' => __('Required. Step-by-step cooking instructions. Shown in recipe rich results.', 'schema-markup-generator'),
                'description_long' => __('Step-by-step instructions for preparing the recipe. Each step should be clear and actionable. Google may display these as expandable steps in rich results.', 'schema-markup-generator'),
                'example' => __('Bring a large pot of salted water to boil. Cook the spaghetti according to package directions until al dente.', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/recipeInstructions',
            ],
            'calories' => [
                'type' => 'text',
                'description' => __('Calories per serving (e.g., "250 calories"). Shown for nutrition-conscious users.', 'schema-markup-generator'),
                'description_long' => __('The number of calories per serving. Include the unit "calories" or "kcal". This information is increasingly important as users search for healthier options.', 'schema-markup-generator'),
                'example' => __('450 calories, 320 kcal, 580 calories per serving', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/calories',
            ],
            'ratingValue' => [
                'type' => 'number',
                'description' => __('Average rating (1-5). Star rating in search results significantly boosts CTR.', 'schema-markup-generator'),
                'description_long' => __('The average rating from user reviews. Star ratings in recipe search results have one of the highest impacts on click-through rates.', 'schema-markup-generator'),
                'example' => __('4.7, 4.2, 5.0', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/ratingValue',
            ],
            'ratingCount' => [
                'type' => 'number',
                'description' => __('Number of ratings. Adds social proof alongside star display.', 'schema-markup-generator'),
                'description_long' => __('The total number of user ratings. Higher numbers provide stronger social proof and increase user confidence in the recipe.', 'schema-markup-generator'),
                'example' => __('234, 1589, 47', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/ratingCount',
            ],
            'keywords' => [
                'type' => 'text',
                'description' => __('Recipe tags (comma-separated). Helps with dietary/style searches (vegan, quick, etc.).', 'schema-markup-generator'),
                'description_long' => __('Keywords describing the recipe characteristics, dietary considerations, or cooking methods. Helps match with specific user searches like "vegan dinner" or "gluten-free dessert".', 'schema-markup-generator'),
                'example' => __('quick dinner, vegetarian, gluten-free, meal prep, comfort food, healthy', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/keywords',
            ],
            'videoUrl' => [
                'type' => 'url',
                'description' => __('Recipe video URL. Auto-extracted from YouTube/Vimeo embeds.', 'schema-markup-generator'),
                'description_long' => __('URL of a video showing how to prepare this recipe. If you embed YouTube or Vimeo videos in your content, this is automatically extracted.', 'schema-markup-generator'),
                'example' => __('https://www.youtube.com/watch?v=...', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/video',
                'auto' => 'post_content',
                'auto_description' => __('Auto-extracted from embedded YouTube/Vimeo videos with transcript', 'schema-markup-generator'),
            ],
        ]);
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
}

