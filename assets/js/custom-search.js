/**
 * WebHero Custom Search JavaScript
 * 
 * Handles AJAX search and relevance-based results loading
 */
(function($) {
    'use strict';

    // Initialize the search functionality when the document is ready
    $(document).ready(function() {
        'use strict';
        
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
        
        var searchForm = $('.custom-search-form');
        var searchButton = searchForm.find('button');
        var searchResults = $('.custom-search-results');
        var postResults = $('.post-results');
        var searchTitle = $('.search-title');
        
        // Hide article titles and description on page load for 'rolex' search
        $(function() {
            // Extract search query from URL
            var urlParams = new URLSearchParams(window.location.search);
            var searchQuery = urlParams.get('q');
            
            // If search term is 'rolex', hide article section title and description
            if (searchQuery && searchQuery.toLowerCase() === 'rolex' && postResults.length > 0) {
                postResults.find('.section-title').hide();
                postResults.find('.search-description').hide();
            }
        });

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

        function performSearch(query, updatePosts, isNewSearch) {
            // Initialize debug mode from URL or cookie
            var debug_mode = window.location.search.indexOf('debug=true') !== -1 || window.location.search.indexOf('debug_search=1') !== -1 || document.cookie.indexOf('debug_search=1') !== -1;
            
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
                    security: webhero_cs_params.nonce,
                    debug: window.location.search.indexOf('debug=true') !== -1 ? 'true' : 'false'
                },
                success: function(response) {
                    const currentUrl = window.location.href;
                    
                    // Handle debug mode output
                    if (window.location.search.indexOf('debug=true') !== -1) {
                        // Remove any existing debug panel
                        $('#webhero-debug-panel').remove();
                        
                        // Create a debug panel to show information
                        var debugPanel = $('<div id="webhero-debug-panel" style="position:fixed; right:20px; bottom:20px; width:400px; height:400px; background:#fff; border:1px solid #ddd; padding:10px; overflow:auto; z-index:9999; box-shadow: 0 0 10px rgba(0,0,0,0.1);">' +
                            '<h3>Search Debug Information</h3>' +
                            '<div class="debug-content"></div>' +
                            '<button class="close-debug" style="position:absolute; top:5px; right:5px;">×</button>' +
                        '</div>');
                        
                        // Add debug panel to the page
                        $('body').append(debugPanel);
                        
                        // Add event handler to close button
                        $('.close-debug').on('click', function() {
                            $('#webhero-debug-panel').hide();
                        });
                        
                        // Add debug information to the panel
                        var debugContent = $('#webhero-debug-panel .debug-content');
                        debugContent.append('<p><strong>Search Query:</strong> ' + query + '</p>');
                        
                        // Add collection debug info
                        if (response.data.collection_content && response.data.collection_content.debug_info) {
                            debugContent.append('<h4>Collection Results:</h4>');
                            var collectionInfo = $('<ul></ul>');
                            $.each(response.data.collection_content.debug_info, function(i, info) {
                                collectionInfo.append('<li>' + info + '</li>');
                            });
                            debugContent.append(collectionInfo);
                        }
                        
                        // Add post debug info
                        if (response.data.post_content && response.data.post_content.debug_info) {
                            debugContent.append('<h4>Article Results:</h4>');
                            var postInfo = $('<ul></ul>');
                            $.each(response.data.post_content.debug_info, function(i, info) {
                                postInfo.append('<li>' + info + '</li>');
                            });
                            debugContent.append(postInfo);
                        }
                        
                        // Log debug info to console as well
                        console.log('Search Debug Information:', response.data);
                    }
                    if (!response.success) {
                        alert(response.data.message);
                        searchResults.removeClass('loading');
                        searchButton.prop('disabled', false).removeClass('loading');
                        return;
                    }
                    
                    // Only reset everything for a brand new search, not for pagination
                    if (isNewSearch) {
                        $('.collection-results').hide();
                        // Find post results element safely
                        var $postResults = $('.post-results');
                        if ($postResults.length > 0) {
                            $postResults.hide();
                        }
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
                        
                        // Preserve debug parameters if present
                        if (window.location.search.indexOf('debug=true') !== -1) {
                            params.push('debug=true');
                        }
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
                    
                    // First, handle collection results
                    if (response.data.collection_content) {
                        // Hide collection section if no positive scores were found
                        if (response.data.collection_content.has_results !== true) {
                            // Only try to hide if element exists
                            var $collectionResults = $('.collection-results');
                            if ($collectionResults.length > 0) {
                                $collectionResults.hide();
                            }
                        } else {
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
                                
                                // Create a container for the collection items
                                collectionResults.append('<div class="collection-items-container"></div>');
                            }
                            
                            // Clear any existing collection containers first
                            var originalCollectionContainer = collectionResults.find('.collection-container');
                            if (originalCollectionContainer.length > 0) {
                                // Remove the original collection container (from initial page load)
                                originalCollectionContainer.remove();
                            }
                            
                            // Update collection contents and make visible
                            var collectionItemsContainer = collectionResults.find('.collection-items-container');
                            if (collectionItemsContainer.length === 0) {
                                collectionResults.append('<div class="collection-items-container"></div>');
                                collectionItemsContainer = collectionResults.find('.collection-items-container');
                            } else {
                                // Empty the existing collection container before adding new content
                                collectionItemsContainer.empty();
                            }
                            
                            // Insert the HTML content from the AJAX response
                            collectionItemsContainer.html(response.data.collection_content.html);
                            collectionResults.show();
                        }
                    }
                    
                    // Handle post results
                    if (response.data.post_content) {
                        // Get a reference to post results container if it exists
                        var postResults = $('.post-results');
                        var postContainer = $('#search-posts-container');
                        
                        // Check if there are article results with positive scores
                        if (response.data.post_content.has_results === true) {
                            // If post results section doesn't exist, create it
                            if (postResults.length === 0) {
                                // Create post results section
                                postResults = $('<div class="post-results"></div>');
                                
                                // Make sure searchResults exists before appending
                                if (searchResults && searchResults.length > 0) {
                                    searchResults.append(postResults);
                                    
                                    // Only show article title and description if search term is NOT 'rolex'
                                    if (query.toLowerCase() !== 'rolex') {
                                        // Add title and structure
                                        var postTitle = $('<h2 class="section-title"></h2>');
                                        if (currentUrl.includes('ms/')) {
                                            postTitle.text('Artikel');
                                        } else {
                                            postTitle.text('Articles');
                                        }
                                        postResults.append(postTitle);
                                        
                                        // Add description
                                        var postDesc = $('<p class="search-description"></p>');
                                        if (currentUrl.includes('ms/')) {
                                            postDesc.text('Terokai artikel yang berkaitan dengan carian anda');
                                        } else {
                                            postDesc.text('Explore articles related to your search');
                                        }
                                        postResults.append(postDesc);
                                    }
                                    
                                    // Add container for posts
                                    postResults.append('<div id="search-posts-container"></div>');
                                    // Update reference to the newly created container
                                    postContainer = $('#search-posts-container');
                                }
                            }
                            
                            // Update post contents and show section
                            if (postResults && postResults.length > 0) {
                                // For 'rolex' search term, hide section-title and search-description
                                if (query.toLowerCase() === 'rolex') {
                                    postResults.find('.section-title').hide();
                                    postResults.find('.search-description').hide();
                                } else {
                                    // Make sure they're visible for other search terms
                                    postResults.find('.section-title').show();
                                    postResults.find('.search-description').show();
                                }
                                
                                // Clear any existing articles container first
                                var originalArticlesContainer = postResults.find('.articles-container');
                                if (originalArticlesContainer.length > 0) {
                                    // Remove the original articles container (from initial page load)
                                    originalArticlesContainer.remove();
                                }
                                
                                // If container doesn't exist, create it
                                if (postContainer.length === 0) {
                                    postResults.append('<div id="search-posts-container"></div>');
                                    postContainer = $('#search-posts-container');
                                } else {
                                    // Empty the existing container before adding new content
                                    postContainer.empty();
                                }
                                
                                // Only update if we have a valid container
                                if (postContainer.length > 0) {
                                    postContainer.html(response.data.post_content.html);
                                } 
                                postResults.show();
                            }
                        } else {
                            // Hide post results if no positive scores
                            if (postResults && postResults.length > 0) {
                                postResults.hide();
                            }
                        }
                    }
                    
                    // Get fresh reference to search title
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
                                searchTitle.text('Semua Koleksi dan Artikel');
                            } else {
                                searchTitle.text('All Collections and Articles');
                            }
                        }
                    }
                    searchResults.removeClass('loading');
                    searchButton.prop('disabled', false).removeClass('loading');
                    
                    var newUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
                    var params = [];
                    if (query) params.push('q=' + encodeURIComponent(query));
                    // Pagination parameter removed
                    
                    // Preserve debug parameters if present
                    if (window.location.search.indexOf('debug=true') !== -1) {
                        params.push('debug=true');
                    }
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
            performSearch(query, true, true);
        });
    });
})(jQuery);
