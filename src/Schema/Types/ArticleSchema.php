<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Article Schema
 *
 * Supports Article, BlogPosting, and NewsArticle types.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
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
        $data = $this->buildBase($post, $mapping);

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

        // Image (with fallback to custom fallback image or site favicon)
        $image = $this->getImageWithFallback($post);
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
        return array_merge(self::getAdditionalTypeDefinition(), [
            'headline' => [
                'type' => 'text',
                'description' => __('Article title. Required for Google News and Discover eligibility.', 'schema-markup-generator'),
                'description_long' => __('The headline of the article. Headlines should not exceed 110 characters. For AMP stories, the headline must match the first-cover text. This is the primary text shown in search results and social shares.', 'schema-markup-generator'),
                'example' => __('10 Tips for Better SEO in 2025, Breaking: Major Tech Announcement Today', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/headline',
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Summary shown in search results. Keep under 160 characters for best display.', 'schema-markup-generator'),
                'description_long' => __('A short description of the article. The description should be a concise summary that helps users understand what the article is about before clicking. Google may use this in search snippets.', 'schema-markup-generator'),
                'example' => __('Learn the latest SEO techniques to improve your website ranking and drive more organic traffic.', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/description',
                'auto' => 'post_excerpt',
            ],
            'image' => [
                'type' => 'image',
                'description' => __('Required for rich results. Use high-quality images (min 1200px wide recommended).', 'schema-markup-generator'),
                'description_long' => __('The representative image of the article. For best results in Google Discover and search, provide images at least 1200 pixels wide with 16x9, 4x3, or 1x1 aspect ratio. Images must be crawlable and indexable.', 'schema-markup-generator'),
                'example' => __('https://example.com/images/article-hero.jpg', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/image',
                'auto' => 'featured_image',
            ],
            'author' => [
                'type' => 'person',
                'description' => __('Content creator. Important for E-E-A-T signals and author knowledge panels.', 'schema-markup-generator'),
                'description_long' => __('The author of the article. Including author information helps establish E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) and can lead to author knowledge panels in search results.', 'schema-markup-generator'),
                'example' => __('John Smith, Dr. Jane Doe', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/author',
                'auto' => 'post_author',
            ],
            'datePublished' => [
                'type' => 'datetime',
                'description' => __('First publication date. Affects freshness signals for time-sensitive content.', 'schema-markup-generator'),
                'description_long' => __('The date and time the article was originally published. Use ISO 8601 format. This date is used by Google to determine content freshness, especially important for news and time-sensitive topics.', 'schema-markup-generator'),
                'example' => __('2025-01-15T09:00:00+01:00', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/datePublished',
                'auto' => 'post_date',
            ],
            'dateModified' => [
                'type' => 'datetime',
                'description' => __('Last update date. Shows content freshness to search engines and users.', 'schema-markup-generator'),
                'description_long' => __('The date and time the article was most recently modified. Updating this date signals to search engines that the content has been refreshed, which can improve rankings for evergreen content.', 'schema-markup-generator'),
                'example' => __('2025-03-20T14:30:00+01:00', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/dateModified',
                'auto' => 'post_modified',
            ],
            'articleSection' => [
                'type' => 'text',
                'description' => __('Category or section. Helps search engines understand content organization.', 'schema-markup-generator'),
                'description_long' => __('Articles may belong to one or more "sections" in a publication, such as Technology, Opinion, Sports, etc. This helps search engines categorize and display your content appropriately.', 'schema-markup-generator'),
                'example' => __('Technology, Business, Health & Wellness', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/articleSection',
                'auto' => 'category',
            ],
            'keywords' => [
                'type' => 'text',
                'description' => __('Comma-separated keywords. Used by search engines and AI for topic matching.', 'schema-markup-generator'),
                'description_long' => __('Keywords or tags used to describe the article. These help search engines and AI systems understand the main topics covered. Use specific, relevant terms rather than broad categories.', 'schema-markup-generator'),
                'example' => __('SEO, digital marketing, Google algorithm, search optimization', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/keywords',
                'auto' => 'tags',
            ],
        ]);
    }
}

