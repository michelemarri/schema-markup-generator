<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Review Schema
 *
 * For reviews and ratings of products, services, or other items.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
 */
class ReviewSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'Review';
    }

    public function getLabel(): string
    {
        return __('Review', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For reviews and ratings of products, services, or businesses. Enables review rich results with star ratings.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post, $mapping);

        // Core properties
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['reviewBody'] = $this->getPostDescription($post);
        $data['url'] = $this->getPostUrl($post);

        // Author
        $data['author'] = $this->getAuthor($post);

        // Date
        $data['datePublished'] = $this->formatDate($post->post_date_gmt);

        // Publisher
        $data['publisher'] = $this->getPublisher();

        // Rating
        $ratingValue = $this->getMappedValue($post, $mapping, 'ratingValue');
        if ($ratingValue) {
            $data['reviewRating'] = [
                '@type' => 'Rating',
                'ratingValue' => (float) $ratingValue,
                'bestRating' => (float) ($this->getMappedValue($post, $mapping, 'bestRating') ?: 5),
                'worstRating' => (float) ($this->getMappedValue($post, $mapping, 'worstRating') ?: 1),
            ];
        }

        // Item reviewed
        $itemReviewed = $this->buildItemReviewed($post, $mapping);
        if (!empty($itemReviewed)) {
            $data['itemReviewed'] = $itemReviewed;
        }

        // Pros and cons
        $positiveNotes = $this->getMappedValue($post, $mapping, 'positiveNotes');
        if (is_array($positiveNotes) && !empty($positiveNotes)) {
            $data['positiveNotes'] = [
                '@type' => 'ItemList',
                'itemListElement' => $this->buildListItems($positiveNotes),
            ];
        }

        $negativeNotes = $this->getMappedValue($post, $mapping, 'negativeNotes');
        if (is_array($negativeNotes) && !empty($negativeNotes)) {
            $data['negativeNotes'] = [
                '@type' => 'ItemList',
                'itemListElement' => $this->buildListItems($negativeNotes),
            ];
        }

        /**
         * Filter review schema data
         */
        $data = apply_filters('smg_review_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Build item reviewed data
     */
    private function buildItemReviewed(WP_Post $post, array $mapping): array
    {
        $itemType = $this->getMappedValue($post, $mapping, 'itemReviewedType') ?: 'Thing';
        $itemName = $this->getMappedValue($post, $mapping, 'itemReviewedName');

        if (!$itemName) {
            return [];
        }

        $item = [
            '@type' => $itemType,
            'name' => $itemName,
        ];

        // Image
        $itemImage = $this->getMappedValue($post, $mapping, 'itemReviewedImage');
        if ($itemImage) {
            $item['image'] = is_array($itemImage) ? $itemImage['url'] : $itemImage;
        }

        // URL
        $itemUrl = $this->getMappedValue($post, $mapping, 'itemReviewedUrl');
        if ($itemUrl) {
            $item['url'] = $itemUrl;
        }

        // Additional properties based on type
        switch ($itemType) {
            case 'Product':
                $brand = $this->getMappedValue($post, $mapping, 'itemReviewedBrand');
                if ($brand) {
                    $item['brand'] = ['@type' => 'Brand', 'name' => $brand];
                }
                break;

            case 'LocalBusiness':
            case 'Organization':
                $address = $this->getMappedValue($post, $mapping, 'itemReviewedAddress');
                if ($address) {
                    $item['address'] = $address;
                }
                break;

            case 'Movie':
            case 'Book':
                $director = $this->getMappedValue($post, $mapping, 'itemReviewedDirector');
                if ($director) {
                    $item['director'] = ['@type' => 'Person', 'name' => $director];
                }
                break;
        }

        return $item;
    }

    /**
     * Build list items for pros/cons
     */
    private function buildListItems(array $items): array
    {
        $listItems = [];
        $position = 1;

        foreach ($items as $item) {
            $listItems[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'name' => is_array($item) ? ($item['name'] ?? $item[0] ?? '') : $item,
            ];
            $position++;
        }

        return $listItems;
    }

    public function getRequiredProperties(): array
    {
        return ['itemReviewed', 'reviewRating', 'author'];
    }

    public function getRecommendedProperties(): array
    {
        return ['name', 'reviewBody', 'datePublished', 'publisher'];
    }

    public function getPropertyDefinitions(): array
    {
        return array_merge(self::getAdditionalTypeDefinition(), [
            'name' => [
                'type' => 'text',
                'description' => __('Review headline. Displayed as the review title in search results.', 'schema-markup-generator'),
                'description_long' => __('The headline or title of your review. This is displayed prominently in review rich results. Make it descriptive and include the product/service name.', 'schema-markup-generator'),
                'example' => __('Sony WH-1000XM5 Review: The Best Noise-Canceling Headphones, Honest Review of Acme Software', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/name',
                'auto' => 'post_title',
            ],
            'reviewBody' => [
                'type' => 'text',
                'description' => __('Full review text. Snippet may appear in review rich results.', 'schema-markup-generator'),
                'description_long' => __('The full text of the review. Google may display snippets from this in review rich results. Include detailed observations, experiences, and analysis.', 'schema-markup-generator'),
                'example' => __('After using these headphones daily for 3 months, I can confidently say they offer exceptional noise cancellation and comfort...', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/reviewBody',
                'auto' => 'post_content',
            ],
            'ratingValue' => [
                'type' => 'number',
                'description' => __('Your rating score. Required for star display in search results.', 'schema-markup-generator'),
                'description_long' => __('The rating you give the item being reviewed. This is displayed as stars in search results. The value must be between worstRating and bestRating.', 'schema-markup-generator'),
                'example' => __('4.5, 8, 9.5', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/ratingValue',
            ],
            'bestRating' => [
                'type' => 'number',
                'description' => __('Maximum possible score (usually 5 or 10). Needed to display rating correctly.', 'schema-markup-generator'),
                'description_long' => __('The highest possible value in your rating scale. Common values are 5 or 10. Required for Google to display the rating correctly.', 'schema-markup-generator'),
                'example' => __('5, 10, 100', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/bestRating',
            ],
            'worstRating' => [
                'type' => 'number',
                'description' => __('Minimum possible score (usually 1). Defaults to 1 if not set.', 'schema-markup-generator'),
                'description_long' => __('The lowest possible value in your rating scale. Usually 1, but can be 0 for some rating systems. Defaults to 1 if not specified.', 'schema-markup-generator'),
                'example' => __('1, 0', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/worstRating',
            ],
            'itemReviewedType' => [
                'type' => 'select',
                'description' => __('What you are reviewing. Affects which rich result type is shown.', 'schema-markup-generator'),
                'description_long' => __('The type of item being reviewed. This determines which rich result format is used and what additional properties are relevant. Choose the most specific type that applies.', 'schema-markup-generator'),
                'example' => __('Product for physical goods, LocalBusiness for restaurants/stores, Movie for films', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/itemReviewed',
                'options' => ['Product', 'LocalBusiness', 'Organization', 'Movie', 'Book', 'Restaurant', 'SoftwareApplication'],
            ],
            'itemReviewedName' => [
                'type' => 'text',
                'description' => __('Name of product/business being reviewed. Required for rich results.', 'schema-markup-generator'),
                'description_long' => __('The name of the product, service, or business being reviewed. This is required for review rich results and should match the official name.', 'schema-markup-generator'),
                'example' => __('Sony WH-1000XM5, Acme Restaurant, The Great Gatsby (book)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/name',
            ],
            'positiveNotes' => [
                'type' => 'repeater',
                'description' => __('Pros list. May be shown as expandable pros section in search results.', 'schema-markup-generator'),
                'description_long' => __('A list of positive aspects or pros of the item being reviewed. Google may display these as an expandable list in review rich results, providing a quick summary of what\'s good.', 'schema-markup-generator'),
                'example' => __('Excellent noise cancellation, 30-hour battery life, Comfortable for long wear, Premium build quality', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/positiveNotes',
            ],
            'negativeNotes' => [
                'type' => 'repeater',
                'description' => __('Cons list. Shown alongside pros for balanced review display.', 'schema-markup-generator'),
                'description_long' => __('A list of negative aspects or cons. Shown alongside pros for a balanced review presentation. Being honest about drawbacks builds trust with readers.', 'schema-markup-generator'),
                'example' => __('Premium price, No 3.5mm audio jack, Touch controls can be finicky', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/negativeNotes',
            ],
        ]);
    }
}

