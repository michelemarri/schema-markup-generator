<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Schema\Types;

use flavor\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Article Schema
 *
 * Supports Article, BlogPosting, and NewsArticle types.
 *
 * @package flavor\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <info@metodo.dev>
 */
class ArticleSchema extends AbstractSchema
{
    private string $type = 'Article';

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return match ($this->type) {
            'BlogPosting' => __('Blog Post', 'schema-markup-generator'),
            'NewsArticle' => __('News Article', 'schema-markup-generator'),
            default => __('Article', 'schema-markup-generator'),
        };
    }

    public function getDescription(): string
    {
        return __('For blog posts, news articles, and editorial content. Helps search engines understand your article structure.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post);

        // Core properties
        $data['headline'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);
        $data['url'] = $this->getPostUrl($post);

        // Dates
        $data['datePublished'] = $this->formatDate($post->post_date_gmt);
        $data['dateModified'] = $this->formatDate($post->post_modified_gmt);

        // Author
        $data['author'] = $this->getAuthor($post);

        // Publisher
        $data['publisher'] = $this->getPublisher();

        // Image
        $image = $this->getFeaturedImage($post);
        if ($image) {
            $data['image'] = $image;
        }

        // Article body
        $content = wp_strip_all_tags($post->post_content);
        if (!empty($content)) {
            $data['articleBody'] = $content;
        }

        // Word count
        $data['wordCount'] = $this->getWordCount($post);

        // Main entity of page
        $data['mainEntityOfPage'] = [
            '@type' => 'WebPage',
            '@id' => $this->getPostUrl($post),
        ];

        // Categories as article section
        $categories = get_the_category($post->ID);
        if (!empty($categories)) {
            $data['articleSection'] = $categories[0]->name;
        }

        // Tags as keywords
        $tags = get_the_tags($post->ID);
        if ($tags && !is_wp_error($tags)) {
            $data['keywords'] = implode(', ', wp_list_pluck($tags, 'name'));
        }

        // Custom field mappings
        foreach ($mapping as $property => $fieldKey) {
            $value = $this->getMappedValue($post, $mapping, $property);
            if ($value !== null) {
                $data[$property] = $value;
            }
        }

        /**
         * Filter article schema data
         *
         * @param array   $data    The schema data
         * @param WP_Post $post    The post object
         * @param array   $mapping Field mappings
         */
        $data = apply_filters('smg_article_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    public function getRequiredProperties(): array
    {
        return ['headline', 'author', 'datePublished', 'image'];
    }

    public function getRecommendedProperties(): array
    {
        return ['description', 'dateModified', 'publisher', 'mainEntityOfPage'];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'headline' => [
                'type' => 'text',
                'description' => __('Article headline', 'schema-markup-generator'),
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Short description or excerpt', 'schema-markup-generator'),
                'auto' => 'post_excerpt',
            ],
            'image' => [
                'type' => 'image',
                'description' => __('Featured image', 'schema-markup-generator'),
                'auto' => 'featured_image',
            ],
            'author' => [
                'type' => 'person',
                'description' => __('Article author', 'schema-markup-generator'),
                'auto' => 'post_author',
            ],
            'datePublished' => [
                'type' => 'datetime',
                'description' => __('Publication date', 'schema-markup-generator'),
                'auto' => 'post_date',
            ],
            'dateModified' => [
                'type' => 'datetime',
                'description' => __('Last modified date', 'schema-markup-generator'),
                'auto' => 'post_modified',
            ],
            'articleSection' => [
                'type' => 'text',
                'description' => __('Section/category', 'schema-markup-generator'),
                'auto' => 'category',
            ],
            'keywords' => [
                'type' => 'text',
                'description' => __('Keywords/tags', 'schema-markup-generator'),
                'auto' => 'tags',
            ],
        ];
    }
}

