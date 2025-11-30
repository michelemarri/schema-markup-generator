<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs;

use Metodo\SchemaMarkupGenerator\Schema\SchemaFactory;
use WP_Query;

/**
 * Pages Tab
 *
 * Configure schema mappings for individual pages.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <info@metodo.dev>
 */
class PagesTab extends AbstractTab
{
    private SchemaFactory $schemaFactory;

    /**
     * Number of pages per pagination page
     */
    private const PER_PAGE = 20;

    public function getSettingsGroup(): string
    {
        return 'smg_pages';
    }

    public function getRegisteredOptions(): array
    {
        return [
            'smg_page_mappings' => [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizePageMappings'],
                'default' => [],
            ],
        ];
    }

    /**
     * Sanitize page mappings
     */
    public function sanitizePageMappings(?array $input): array
    {
        if ($input === null) {
            return [];
        }

        // Get existing mappings to merge (for pagination support)
        $existing = get_option('smg_page_mappings', []);
        $sanitized = is_array($existing) ? $existing : [];

        foreach ($input as $pageId => $schemaType) {
            $pageId = absint($pageId);
            if ($pageId === 0) {
                continue;
            }

            $schemaType = sanitize_text_field($schemaType);

            // Remove empty mappings, keep valid ones
            if (empty($schemaType)) {
                unset($sanitized[$pageId]);
            } else {
                $sanitized[$pageId] = $schemaType;
            }
        }

        return $sanitized;
    }

    /**
     * Suggested schemas based on page slug patterns
     */
    private const SLUG_SUGGESTIONS = [
        'home' => 'WebPage',
        'about' => 'AboutPage',
        'about-us' => 'AboutPage',
        'chi-siamo' => 'AboutPage',
        'contact' => 'ContactPage',
        'contacts' => 'ContactPage',
        'contatti' => 'ContactPage',
        'contattaci' => 'ContactPage',
        'pricing' => 'WebPage',
        'prezzi' => 'WebPage',
        'piani' => 'WebPage',
        'plans' => 'WebPage',
        'faq' => 'FAQPage',
        'domande-frequenti' => 'FAQPage',
        'services' => 'WebPage',
        'servizi' => 'WebPage',
        'products' => 'CollectionPage',
        'prodotti' => 'CollectionPage',
        'catalog' => 'CollectionPage',
        'catalogo' => 'CollectionPage',
        'shop' => 'CollectionPage',
        'team' => 'AboutPage',
        'privacy' => 'WebPage',
        'privacy-policy' => 'WebPage',
        'terms' => 'WebPage',
        'terms-of-service' => 'WebPage',
        'cookie-policy' => 'WebPage',
        'blog' => 'CollectionPage',
        'news' => 'CollectionPage',
        'portfolio' => 'CollectionPage',
        'gallery' => 'CollectionPage',
        'galleria' => 'CollectionPage',
        'search' => 'SearchResultsPage',
        'ricerca' => 'SearchResultsPage',
        'checkout' => 'CheckoutPage',
        'cart' => 'WebPage',
        'carrello' => 'WebPage',
    ];

    public function __construct()
    {
        $this->schemaFactory = new SchemaFactory();
    }

    public function getTitle(): string
    {
        return __('Pages', 'schema-markup-generator');
    }

    public function getIcon(): string
    {
        return 'dashicons-admin-page';
    }

    public function render(): void
    {
        $currentPage = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $pageMappings = get_option('smg_page_mappings', []);
        $schemaTypes = $this->getPageSchemaTypes();

        // Query top-level pages
        $query = new WP_Query([
            'post_type' => 'page',
            'post_parent' => 0,
            'posts_per_page' => self::PER_PAGE,
            'paged' => $currentPage,
            'orderby' => 'menu_order title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
        ]);

        $totalPages = $query->max_num_pages;
        $totalItems = $query->found_posts;

        // Get front page ID
        $frontPageId = (int) get_option('page_on_front');

        ?>
        <div class="mds-tab-panel" id="tab-pages">
            <?php $this->renderSection(
                __('Page Schema Mapping', 'schema-markup-generator'),
                __('Assign specific schema types to individual pages. Top-level pages are shown below. Child pages inherit from their parent unless overridden in the page editor.', 'schema-markup-generator')
            ); ?>

            <?php if ($query->have_posts()): ?>
                <div class="mds-pages-header">
                    <div class="mds-pages-count">
                        <?php
                        printf(
                            /* translators: %d: number of pages */
                            _n('%d top-level page', '%d top-level pages', $totalItems, 'schema-markup-generator'),
                            $totalItems
                        );
                        ?>
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <div class="mds-pages-pagination-info">
                            <?php
                            printf(
                                /* translators: 1: current page, 2: total pages */
                                __('Page %1$d of %2$d', 'schema-markup-generator'),
                                $currentPage,
                                $totalPages
                            );
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mds-pages-list">
                    <table class="mds-pages-table">
                        <thead>
                            <tr>
                                <th class="mds-col-page"><?php esc_html_e('Page', 'schema-markup-generator'); ?></th>
                                <th class="mds-col-slug"><?php esc_html_e('Slug', 'schema-markup-generator'); ?></th>
                                <th class="mds-col-status"><?php esc_html_e('Status', 'schema-markup-generator'); ?></th>
                                <th class="mds-col-schema"><?php esc_html_e('Schema Type', 'schema-markup-generator'); ?></th>
                                <th class="mds-col-suggestion"><?php esc_html_e('Suggested', 'schema-markup-generator'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($query->have_posts()): $query->the_post();
                                $pageId = get_the_ID();
                                $pageSlug = get_post_field('post_name', $pageId);
                                $pageStatus = get_post_status($pageId);
                                $currentSchema = $pageMappings[$pageId] ?? '';
                                $suggestedSchema = $this->getSuggestedSchema($pageId, $pageSlug);
                                $isFrontPage = ($pageId === $frontPageId);
                                $childCount = $this->getChildPageCount($pageId);
                            ?>
                                <tr class="mds-page-row <?php echo $isFrontPage ? 'mds-front-page' : ''; ?>">
                                    <td class="mds-col-page">
                                        <div class="mds-page-info">
                                            <?php if ($isFrontPage): ?>
                                                <span class="mds-badge mds-badge-primary" title="<?php esc_attr_e('Front Page', 'schema-markup-generator'); ?>">
                                                    <span class="dashicons dashicons-admin-home"></span>
                                                </span>
                                            <?php endif; ?>
                                            <strong>
                                                <a href="<?php echo esc_url(get_edit_post_link($pageId)); ?>" target="_blank">
                                                    <?php the_title(); ?>
                                                </a>
                                            </strong>
                                            <?php if ($childCount > 0): ?>
                                                <span class="mds-child-count" title="<?php esc_attr_e('Child pages', 'schema-markup-generator'); ?>">
                                                    +<?php echo esc_html((string) $childCount); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="mds-col-slug">
                                        <code>/<?php echo esc_html($pageSlug); ?></code>
                                    </td>
                                    <td class="mds-col-status">
                                        <?php echo $this->renderStatusBadge($pageStatus); ?>
                                    </td>
                                    <td class="mds-col-schema">
                                        <select name="smg_page_mappings[<?php echo esc_attr((string) $pageId); ?>]"
                                                class="mds-schema-select mds-page-schema-select"
                                                data-page-id="<?php echo esc_attr((string) $pageId); ?>"
                                                data-suggested="<?php echo esc_attr($suggestedSchema); ?>">
                                            <option value=""><?php esc_html_e('— No Schema —', 'schema-markup-generator'); ?></option>
                                            <?php foreach ($schemaTypes as $group => $types): ?>
                                                <optgroup label="<?php echo esc_attr($group); ?>">
                                                    <?php foreach ($types as $type => $label): ?>
                                                        <option value="<?php echo esc_attr($type); ?>"
                                                                <?php selected($currentSchema, $type); ?>>
                                                            <?php echo esc_html($label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td class="mds-col-suggestion">
                                        <?php if ($suggestedSchema && $suggestedSchema !== $currentSchema): ?>
                                            <button type="button" 
                                                    class="mds-apply-suggestion button button-small"
                                                    data-schema="<?php echo esc_attr($suggestedSchema); ?>"
                                                    data-page-id="<?php echo esc_attr((string) $pageId); ?>"
                                                    title="<?php esc_attr_e('Apply suggestion', 'schema-markup-generator'); ?>">
                                                <?php echo esc_html($suggestedSchema); ?>
                                                <span class="dashicons dashicons-yes"></span>
                                            </button>
                                        <?php elseif ($currentSchema): ?>
                                            <span class="mds-suggestion-applied">
                                                <span class="dashicons dashicons-yes-alt"></span>
                                            </span>
                                        <?php else: ?>
                                            <span class="mds-no-suggestion">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <?php $this->renderPagination($currentPage, $totalPages); ?>
                <?php endif; ?>

            <?php else: ?>
                <div class="mds-notice mds-notice-info">
                    <span class="dashicons dashicons-info"></span>
                    <?php esc_html_e('No top-level pages found. Create some pages to configure their schema markup.', 'schema-markup-generator'); ?>
                </div>
            <?php endif; ?>

            <?php wp_reset_postdata(); ?>

            <div class="mds-pages-help">
                <h4><?php esc_html_e('Schema Suggestions Guide', 'schema-markup-generator'); ?></h4>
                <div class="mds-help-grid">
                    <div class="mds-help-item">
                        <strong>AboutPage</strong>
                        <span><?php esc_html_e('About, Team, Chi siamo pages', 'schema-markup-generator'); ?></span>
                    </div>
                    <div class="mds-help-item">
                        <strong>ContactPage</strong>
                        <span><?php esc_html_e('Contact, Support pages', 'schema-markup-generator'); ?></span>
                    </div>
                    <div class="mds-help-item">
                        <strong>FAQPage</strong>
                        <span><?php esc_html_e('FAQ, Help, Domande frequenti pages', 'schema-markup-generator'); ?></span>
                    </div>
                    <div class="mds-help-item">
                        <strong>CollectionPage</strong>
                        <span><?php esc_html_e('Products, Portfolio, Gallery, Blog listing pages', 'schema-markup-generator'); ?></span>
                    </div>
                    <div class="mds-help-item">
                        <strong>WebPage</strong>
                        <span><?php esc_html_e('Generic pages, Privacy Policy, Terms', 'schema-markup-generator'); ?></span>
                    </div>
                    <div class="mds-help-item">
                        <strong>ItemPage</strong>
                        <span><?php esc_html_e('Single product/service pages', 'schema-markup-generator'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get schema types specifically suited for pages
     */
    private function getPageSchemaTypes(): array
    {
        return [
            __('Page Types', 'schema-markup-generator') => [
                'WebPage' => __('Web Page (generic)', 'schema-markup-generator'),
                'AboutPage' => __('About Page', 'schema-markup-generator'),
                'ContactPage' => __('Contact Page', 'schema-markup-generator'),
                'FAQPage' => __('FAQ Page', 'schema-markup-generator'),
                'CollectionPage' => __('Collection Page', 'schema-markup-generator'),
                'ItemPage' => __('Item Page', 'schema-markup-generator'),
                'CheckoutPage' => __('Checkout Page', 'schema-markup-generator'),
                'SearchResultsPage' => __('Search Results Page', 'schema-markup-generator'),
                'ProfilePage' => __('Profile Page', 'schema-markup-generator'),
                'QAPage' => __('Q&A Page', 'schema-markup-generator'),
                'RealEstateListing' => __('Real Estate Listing', 'schema-markup-generator'),
                'MedicalWebPage' => __('Medical Web Page', 'schema-markup-generator'),
            ],
            __('Content', 'schema-markup-generator') => [
                'Article' => __('Article', 'schema-markup-generator'),
                'BlogPosting' => __('Blog Post', 'schema-markup-generator'),
            ],
            __('Business', 'schema-markup-generator') => [
                'Organization' => __('Organization', 'schema-markup-generator'),
                'LocalBusiness' => __('Local Business', 'schema-markup-generator'),
                'Product' => __('Product', 'schema-markup-generator'),
            ],
            __('Instructional', 'schema-markup-generator') => [
                'HowTo' => __('How-To Guide', 'schema-markup-generator'),
                'Course' => __('Course', 'schema-markup-generator'),
                'LearningResource' => __('Learning Resource', 'schema-markup-generator'),
            ],
        ];
    }

    /**
     * Get suggested schema based on page slug or title
     */
    private function getSuggestedSchema(int $pageId, string $slug): string
    {
        // Check if it's the front page
        $frontPageId = (int) get_option('page_on_front');
        if ($pageId === $frontPageId) {
            return 'WebPage';
        }

        // Check slug patterns
        $slug = strtolower($slug);
        if (isset(self::SLUG_SUGGESTIONS[$slug])) {
            return self::SLUG_SUGGESTIONS[$slug];
        }

        // Partial match for common patterns
        foreach (self::SLUG_SUGGESTIONS as $pattern => $schema) {
            if (str_contains($slug, $pattern) || str_contains($pattern, $slug)) {
                return $schema;
            }
        }

        return '';
    }

    /**
     * Get child page count for a parent page
     */
    private function getChildPageCount(int $parentId): int
    {
        return (int) wp_count_posts('page')->publish > 0 
            ? count(get_pages(['parent' => $parentId, 'number' => 100])) 
            : 0;
    }

    /**
     * Render status badge
     */
    private function renderStatusBadge(string $status): string
    {
        $badges = [
            'publish' => ['label' => __('Published', 'schema-markup-generator'), 'class' => 'mds-badge-success'],
            'draft' => ['label' => __('Draft', 'schema-markup-generator'), 'class' => 'mds-badge-warning'],
            'pending' => ['label' => __('Pending', 'schema-markup-generator'), 'class' => 'mds-badge-warning'],
            'private' => ['label' => __('Private', 'schema-markup-generator'), 'class' => 'mds-badge-secondary'],
        ];

        $badge = $badges[$status] ?? ['label' => ucfirst($status), 'class' => 'mds-badge-secondary'];

        return sprintf(
            '<span class="mds-badge %s">%s</span>',
            esc_attr($badge['class']),
            esc_html($badge['label'])
        );
    }

    /**
     * Render pagination
     */
    private function renderPagination(int $currentPage, int $totalPages): void
    {
        $baseUrl = admin_url('options-general.php?page=schema-markup-generator&tab=pages');

        ?>
        <div class="mds-pagination">
            <div class="mds-pagination-links">
                <?php if ($currentPage > 1): ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', 1, $baseUrl)); ?>" 
                       class="mds-pagination-link mds-pagination-first"
                       title="<?php esc_attr_e('First page', 'schema-markup-generator'); ?>">
                        <span class="dashicons dashicons-controls-skipback"></span>
                    </a>
                    <a href="<?php echo esc_url(add_query_arg('paged', $currentPage - 1, $baseUrl)); ?>" 
                       class="mds-pagination-link mds-pagination-prev"
                       title="<?php esc_attr_e('Previous page', 'schema-markup-generator'); ?>">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </a>
                <?php else: ?>
                    <span class="mds-pagination-link mds-pagination-first disabled">
                        <span class="dashicons dashicons-controls-skipback"></span>
                    </span>
                    <span class="mds-pagination-link mds-pagination-prev disabled">
                        <span class="dashicons dashicons-arrow-left-alt2"></span>
                    </span>
                <?php endif; ?>

                <span class="mds-pagination-current">
                    <input type="number" 
                           class="mds-pagination-input" 
                           min="1" 
                           max="<?php echo esc_attr((string) $totalPages); ?>" 
                           value="<?php echo esc_attr((string) $currentPage); ?>"
                           data-base-url="<?php echo esc_attr($baseUrl); ?>">
                    <span class="mds-pagination-total">
                        <?php
                        printf(
                            /* translators: %d: total pages */
                            __('of %d', 'schema-markup-generator'),
                            $totalPages
                        );
                        ?>
                    </span>
                </span>

                <?php if ($currentPage < $totalPages): ?>
                    <a href="<?php echo esc_url(add_query_arg('paged', $currentPage + 1, $baseUrl)); ?>" 
                       class="mds-pagination-link mds-pagination-next"
                       title="<?php esc_attr_e('Next page', 'schema-markup-generator'); ?>">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </a>
                    <a href="<?php echo esc_url(add_query_arg('paged', $totalPages, $baseUrl)); ?>" 
                       class="mds-pagination-link mds-pagination-last"
                       title="<?php esc_attr_e('Last page', 'schema-markup-generator'); ?>">
                        <span class="dashicons dashicons-controls-skipforward"></span>
                    </a>
                <?php else: ?>
                    <span class="mds-pagination-link mds-pagination-next disabled">
                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                    </span>
                    <span class="mds-pagination-link mds-pagination-last disabled">
                        <span class="dashicons dashicons-controls-skipforward"></span>
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

