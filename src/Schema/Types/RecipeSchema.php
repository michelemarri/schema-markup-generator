<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Recipe Schema
 *
 * For recipes with ingredients and instructions.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <info@metodo.dev>
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
        $data = $this->buildBase($post);

        // Core properties
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);
        $data['url'] = $this->getPostUrl($post);

        // Author
        $data['author'] = $this->getAuthor($post);

        // Dates
        $data['datePublished'] = $this->formatDate($post->post_date_gmt);

        // Image
        $image = $this->getFeaturedImage($post);
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
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Recipe title. Shown prominently in recipe rich results.', 'schema-markup-generator'),
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('What makes this recipe special. Displayed in search results.', 'schema-markup-generator'),
                'auto' => 'post_excerpt',
            ],
            'prepTime' => [
                'type' => 'number',
                'description' => __('Prep time in minutes. Displayed separately in recipe cards.', 'schema-markup-generator'),
            ],
            'cookTime' => [
                'type' => 'number',
                'description' => __('Cooking time in minutes. Shown in recipe rich results.', 'schema-markup-generator'),
            ],
            'totalTime' => [
                'type' => 'number',
                'description' => __('Total time in minutes. Primary time shown in search results.', 'schema-markup-generator'),
            ],
            'recipeYield' => [
                'type' => 'text',
                'description' => __('Number of servings (e.g., "4 portions"). Helps with meal planning searches.', 'schema-markup-generator'),
            ],
            'recipeCategory' => [
                'type' => 'text',
                'description' => __('Meal type (Breakfast, Lunch, Dessert, etc.). Used for filtered searches.', 'schema-markup-generator'),
            ],
            'recipeCuisine' => [
                'type' => 'text',
                'description' => __('Cuisine style (Italian, Mexican, etc.). Enables cuisine-specific discovery.', 'schema-markup-generator'),
            ],
            'recipeIngredient' => [
                'type' => 'repeater',
                'description' => __('Required. Complete ingredient list. Core content for recipe rich results.', 'schema-markup-generator'),
            ],
            'recipeInstructions' => [
                'type' => 'repeater',
                'description' => __('Required. Step-by-step cooking instructions. Shown in recipe rich results.', 'schema-markup-generator'),
            ],
            'calories' => [
                'type' => 'text',
                'description' => __('Calories per serving (e.g., "250 calories"). Shown for nutrition-conscious users.', 'schema-markup-generator'),
            ],
            'ratingValue' => [
                'type' => 'number',
                'description' => __('Average rating (1-5). Star rating in search results significantly boosts CTR.', 'schema-markup-generator'),
            ],
            'ratingCount' => [
                'type' => 'number',
                'description' => __('Number of ratings. Adds social proof alongside star display.', 'schema-markup-generator'),
            ],
            'keywords' => [
                'type' => 'text',
                'description' => __('Recipe tags (comma-separated). Helps with dietary/style searches (vegan, quick, etc.).', 'schema-markup-generator'),
            ],
        ];
    }
}

