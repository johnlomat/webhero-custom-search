# WebHero Custom Search

A WordPress plugin that implements an enhanced custom search functionality with advanced relevance-based scoring, real-time AJAX search, and a modern user interface. Specifically optimized for Rolex product and article searches.

## Features

- Custom search form with real-time AJAX search functionality
- Advanced relevance-based scoring system that prioritizes exact matches, partial matches, and content matches
- Intelligent product title partial matching for model numbers and reference codes
- Smart linking of matching products to their collection categories
- Flexible pattern matching for model references (e.g., "m126" matches "M 1 2 6", "M-126", etc.)
- Combined search for WooCommerce products, WordPress posts, and category collections
- Content extraction and filtering that prioritizes visible content
- Word boundary matching for short search terms to reduce false positives
- Minimum search term length requirements with exceptions for model numbers
- Debug mode for troubleshooting search behavior and relevance scoring
- Clean and responsive search interface with fallback images
- SEO-friendly URL structure for search pages

## Usage

1. Install the plugin by uploading the files to your WordPress plugins directory
2. Activate the plugin through the WordPress admin panel
3. Use the shortcode `[custom_search]` to display the search form
4. Use the shortcode `[custom_search_results]` to display search results
5. For debugging, add `?debug=true` to the URL to see relevance scoring details

## Technical Details

### Relevance-Based Search Algorithm

- **Article Scoring System**:
  - Exact title match: 10 points
  - Title starts with term: 8 points
  - Title contains term: 5 points (with word boundary for short terms)
  - Content contains term: 3 points 
  - Articles are sorted by score and then by post ID (newer first)

- **Collection Category Scoring**:
  - Exact name match: 10 points
  - Name starts with term: 8 points
  - Name contains term: 5 points
  - Products in category match search term: Up to 7 points
  - Description contains term: 3 points

- **Product Title Matching**:
  - Flexible pattern matching for model references
  - Smart SQL construction with both LIKE and REGEXP patterns
  - Links matching products back to their parent collections
  - Special handling for short search terms like model numbers

### Advanced Features

- **Smart Content Extraction**: Extracts visible content from `<p>`, heading tags (`h1`-`h6`), and `<figcaption>` tags
- **Content Cleaning**: Strips shortcodes, HTML tags, and comments for better search accuracy
- **Debug Mode**: Outputs detailed match reasons and SQL debugging when enabled
- **Consistent Experience**: Same relevance logic for both AJAX and standard WordPress searches
- **Fallback Images**: Proper handling of missing images with placeholder graphics
- **Multilingual Support**: Adapts "Read More" text based on URL context (e.g., `/ms/` path)

## Requirements

- WordPress 5.0+
- WooCommerce 6.0+ (for product search functionality)
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.3+ with regex support enabled