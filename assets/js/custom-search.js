/**
 * WebHero Custom Search JavaScript
 * 
 * Handles AJAX search, pagination, and default results loading
 */
(function($) {
    'use strict';

    // Initialize the search functionality when the document is ready
    $(document).ready(function() {
        var menuSearchBar = $('.menu-searchbar');
        var menuSearchIcon = $('.menu-search-icon');
        const currentUrl = window.location.href;
        
        menuSearchBar.on('keydown', function(e){
            if (e.key === 'Enter') {
                var searchItem = menuSearchBar.val().trim();
                
                if (currentUrl.includes('ms/')) {
                    window.location.href='/ms/search/?q=' + encodeURIComponent(searchItem);
                } else {
                    window.location.href='/search/?q=' + encodeURIComponent(searchItem);
                }
            }
        });

        menuSearchIcon.on('mousedown', function(){
            var searchItem = menuSearchBar.val().trim();
                
            if (currentUrl.includes('ms/')) {
                window.location.href='/ms/search/?q=' + encodeURIComponent(searchItem);
            } else {
                window.location.href='/search/?q=' + encodeURIComponent(searchItem);
            }
        });

        function getPageNumber(link) {
            var text = link.text().trim();
            var current = parseInt(link.closest('.pagination').find('.active').text()) || 1;
            if (text === '«') {
                return current > 1 ? current - 1 : 1;
            } else if (text === '»') {
                return current + 1;
            }
            return parseInt(text) || 1;
        }
        
        var searchForm = $('.custom-search-form');
        var searchButton = searchForm.find('button');
        var searchResults = $('.custom-search-results');
        var productResults = $('.product-results');
        var postResults = $('.post-results');
        var searchTitle = $('.search-title');

        // Clear search field functionality
        $('.search-input-wrapper input[type="text"]').on('input', function() {
            var $this = $(this);
            if ($this.val().length > 0) {
                $this.siblings('.clear-search').show();
            } else {
                $this.siblings('.clear-search').hide();
            }
        });
        $('.clear-search').on('click', function() {
            var $input = $(this).siblings('input[type="text"]');
            $input.val('').trigger('input').focus();
        });

        function performSearch(query, pagedProducts, pagedPosts, updateProducts, updatePosts, isNewSearch) {
            // Initialize debug mode from URL or cookie
            var debug_mode = window.location.search.indexOf('debug_search=1') !== -1 || document.cookie.indexOf('debug_search=1') !== -1;
            
            // Add debug button if not already present
            if (debug_mode && $('.debug-search-status').length === 0) {
                var debugStatusEl = $('<div class="debug-search-status" style="position:fixed; bottom:10px; right:10px; background:#f8f8f8; border:1px solid #ddd; padding:5px; z-index:9999;">Debug Mode: ON</div>');
                $('body').append(debugStatusEl);
            }
            searchResults.addClass('loading');
            searchButton.prop('disabled', true).addClass('loading');
            
            // Default to true if not specified (backwards compatibility)
            isNewSearch = (typeof isNewSearch !== 'undefined') ? isNewSearch : false;
            
            $.ajax({
                url: webhero_cs_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'webhero_cs_ajax_handler',
                    search_query: query,
                    paged_products: pagedProducts,
                    paged_posts: pagedPosts,
                    security: webhero_cs_params.nonce
                },
                success: function(response) {
					const currentUrl = window.location.href;
                    if (!response.success) {
                        alert(response.data.message);
                        searchResults.removeClass('loading');
                        searchButton.prop('disabled', false).removeClass('loading');
                        return;
                    }
                    
                    // Only reset everything for a brand new search, not for pagination
                    if (isNewSearch) {
                        $('.collection-results').hide();
                        productResults.hide();
                        postResults.hide();
                    }
                    
                    // Handle special Rolex result
                    if (response.data.special_rolex_result) {
                        // Set post results with special Rolex content
                        postResults.html(response.data.rolex_html);
                        postResults.show();
                        
                        // Update search title - always refresh reference first
                        var searchTitle = $('.search-title');
                        if (searchTitle.length > 0) {
                            if (currentUrl.includes('ms/')) {
                                searchTitle.text('Hasil Carian untuk: ' + query);
                            } else {
                                searchTitle.text('Search Results for: ' + query);
                            }
                        }
                        
                        searchResults.removeClass('loading');
                        searchButton.prop('disabled', false).removeClass('loading');
                        
                        // Update URL
                        var newUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
                        var params = [];
                        if (query) params.push('q=' + encodeURIComponent(query));
                        
                        // Preserve debug parameter if present
                        if (window.location.search.indexOf('debug_search=1') !== -1) {
                            params.push('debug_search=1');
                            
                            // Also save it as a cookie for 30 days
                            var date = new Date();
                            date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
                            document.cookie = "debug_search=1; expires=" + date.toUTCString() + "; path=/";
                        }
                        
                        if (params.length) newUrl += '?' + params.join('&');
                        window.history.pushState({path: newUrl}, '', newUrl);
                        return;
                    }
                    
                    // Normal search results handling for non-Rolex searches
                    // Track if we should hide watches section
                    var hideWatchesSection = false;
                    
                    // First, handle collection results - only hide watches if collections actually have results
                    if (response.data.collection_content && response.data.collection_content.has_results === true) {
                        var collectionResults = $('.collection-results');
                        
                        // If collection results section doesn't exist, create it
                        if (collectionResults.length === 0) {
                            collectionResults = $('<div class="collection-results"></div>');
                            
                            // Insert after search title
                            var searchTitle = $('.search-title');
                            if (searchTitle.length > 0) {
                                searchTitle.after(collectionResults);
                            } else {
                                // Fallback if search title not found
                                searchResults.prepend(collectionResults);
                            }
                            
                            // Add section title
                            var titleElement = $('<h2 class="section-title"></h2>');
                            if (currentUrl.includes('ms/')) {
                                titleElement.text('Koleksi');
                            } else {
                                titleElement.text('Collections');
                            }
                            collectionResults.append(titleElement);
                            
                            // Add description
                            var descElement = $('<p class="search-description"></p>');
                            if (currentUrl.includes('ms/')) {
                                descElement.text('Terokai koleksi kami berdasarkan carian anda');
                            } else {
                                descElement.text('Explore our collections based on your search');
                            }
                            collectionResults.append(descElement);
                            
                            // Add loading indicator
                            var loadingElement = $('<div class="loading-indicator" style="display: none;">Loading...</div>');
                            collectionResults.append(loadingElement);
                            
                            // Add collection container
                            var collectionContainer = $('<div class="collection-container"></div>');
                            collectionResults.append(collectionContainer);
                        }
                        
                        collectionResults.find('.collection-container').html(response.data.collection_content.html);
                        collectionResults.show();
                        
                        // Hide watches section when collections are present
                        if (response.data.collection_content.html) {
                            hideWatchesSection = true;
                        }
                    }
                    
                    if (updateProducts && response.data.product_content) {
                        if (response.data.product_content.has_results) {
                            productResults.find('.products-container').html(response.data.product_content.html);
                            productResults.find('.product-pagination').html(response.data.product_pagination);
                            
                            // Only hide watches if we have collections with actual results
                            if (hideWatchesSection) {
                                productResults.hide();
                            } else {
                                productResults.show();
                            }
                        } else {
                            // No product results
                            productResults.hide();
                        }
                    }
                    
                    if (updatePosts) {
                        // First, ensure the post results section has the correct structure
                        if (postResults.find('.articles-container').length === 0) {
                            // Create a new div for post results section
                            postResults.empty();
                            
                            // Add section title
                            var titleElement = document.createElement('h2');
                            titleElement.className = 'section-title';
                            titleElement.textContent = 'Articles';
                            postResults.append(titleElement);
                            
                            // Add description
                            var descElement = document.createElement('p');
                            descElement.className = 'search-description';
                            descElement.textContent = 'Explore articles related to your search';
                            postResults.append(descElement);
                            
                            // Add loading indicator
                            var loadingElement = document.createElement('div');
                            loadingElement.className = 'loading-indicator';
                            loadingElement.style.display = 'none';
                            loadingElement.textContent = 'Loading...';
                            postResults.append(loadingElement);
                            
                            // Add articles container
                            var articlesContainer = document.createElement('div');
                            articlesContainer.className = 'articles-container';
                            postResults.append(articlesContainer);
                            
                            // Add pagination container
                            var paginationElement = document.createElement('div');
                            paginationElement.className = 'post-pagination';
                            postResults.append(paginationElement);
                        }
                        
                        if (response.data.post_content.has_results) {
                            postResults.find('.articles-container').html(response.data.post_content.html);
                            postResults.find('.post-pagination').html(response.data.post_pagination);
                            postResults.show();
                        }
                    }
                    // Always refresh the searchTitle reference before using it
                    var searchTitle = $('.search-title');
                    if (searchTitle.length > 0) {
                        if (query && query.trim() !== '') {
                            if (currentUrl.includes('ms/')) {
                                searchTitle.text('Hasil Carian untuk: ' + query);
                            } else {
                                searchTitle.text('Search Results for: ' + query);
                            }
                        } else {
                            if (currentUrl.includes('ms/')) {
                                searchTitle.text('Semua Jam Tangan dan Artikel');
                            } else {
                                searchTitle.text('All Watches and Articles');
                            }
                        }
                    }
                    searchResults.removeClass('loading');
                    searchButton.prop('disabled', false).removeClass('loading');
                    
                    var newUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
                    var params = [];
                    if (query) params.push('q=' + encodeURIComponent(query));
                    if (pagedProducts > 1) params.push('paged_products=' + pagedProducts);
                    if (pagedPosts > 1) params.push('paged_posts=' + pagedPosts);
                    
                    // Preserve debug parameter if present
                    if (window.location.search.indexOf('debug_search=1') !== -1) {
                        params.push('debug_search=1');
                        
                        // Also save it as a cookie for 30 days
                        var date = new Date();
                        date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
                        document.cookie = "debug_search=1; expires=" + date.toUTCString() + "; path=/";
                    }
                    
                    if (params.length) newUrl += '?' + params.join('&');
                    window.history.pushState({path: newUrl}, '', newUrl);
                },
                error: function(xhr, status, error) {
                    searchResults.removeClass('loading');
                    searchButton.prop('disabled', false).removeClass('loading');
                    console.error('AJAX Error:', status, error);
                    alert('An error occurred. Please try again.');
                }
            });
        }

        searchForm.on('submit', function(e) {
            e.preventDefault();
            var query = $(this).find('input[name="q"]').val();
            // This is a new search, so we'll pass true for isNewSearch
            performSearch(query, 1, 1, true, true, true);
        });

        $(document).on('click', '.product-pagination a, .post-pagination a', function(e) {
            e.preventDefault();
            var link = $(this);
            var query = searchForm.find('input[name="q"]').val();
            var isProduct = link.closest('.product-pagination').length;
            
            var pagedProducts = isProduct ? getPageNumber(link) : (productResults.find('.product-pagination .active').text() || 1);
            var pagedPosts = !isProduct ? getPageNumber(link) : (postResults.find('.post-pagination .active').text() || 1);
            
            // This is pagination, not a new search, so pass false for isNewSearch
            performSearch(query, pagedProducts, pagedPosts, isProduct, !isProduct, false);
        });
    });
})(jQuery);
