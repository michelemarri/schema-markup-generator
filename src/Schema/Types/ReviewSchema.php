<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Schema\Types;

use flavor\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Review Schema
 *
 * For reviews and ratings of products, services, or other items.
 *
 * @package flavor\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <info@metodo.dev>
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
        $data = $this->buildBase($post);

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
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Review title', 'schema-markup-generator'),
                'auto' => 'post_title',
            ],
            'reviewBody' => [
                'type' => 'text',
                'description' => __('Review content', 'schema-markup-generator'),
                'auto' => 'post_content',
            ],
            'ratingValue' => [
                'type' => 'number',
                'description' => __('Rating value', 'schema-markup-generator'),
            ],
            'bestRating' => [
                'type' => 'number',
                'description' => __('Best possible rating', 'schema-markup-generator'),
            ],
            'worstRating' => [
                'type' => 'number',
                'description' => __('Worst possible rating', 'schema-markup-generator'),
            ],
            'itemReviewedType' => [
                'type' => 'select',
                'description' => __('Type of item reviewed', 'schema-markup-generator'),
                'options' => ['Product', 'LocalBusiness', 'Organization', 'Movie', 'Book', 'Restaurant', 'SoftwareApplication'],
            ],
            'itemReviewedName' => [
                'type' => 'text',
                'description' => __('Name of item reviewed', 'schema-markup-generator'),
            ],
            'positiveNotes' => [
                'type' => 'repeater',
                'description' => __('Pros/positive points', 'schema-markup-generator'),
            ],
            'negativeNotes' => [
                'type' => 'repeater',
                'description' => __('Cons/negative points', 'schema-markup-generator'),
            ],
        ];
    }
}

