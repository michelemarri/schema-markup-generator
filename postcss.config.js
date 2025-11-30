/**
 * Schema Markup Generator - PostCSS Configuration
 * 
 * @package flavor\SchemaMarkupGenerator
 */

module.exports = {
  plugins: {
    'postcss-import': {},
    'autoprefixer': {},
    ...(process.env.NODE_ENV === 'production' ? { 'cssnano': { preset: 'default' } } : {}),
  },
};

