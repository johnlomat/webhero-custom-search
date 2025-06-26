/**
 * WebHero Custom Search JavaScript
 * 
 * Handles AJAX search, pagination, and default results loading
 */
(function($) {
    'use strict';

    // Simple helper to check and remove pagination when not needed
    function checkAndRemovePagination() {
        // Count actual articles
        var articleCount = $('.articles-grid article').length;
        console.log('Article count:', articleCount, 'Pagination elements:', $('.post-pagination').length);
        
        // If 6 or fewer posts, remove pagination
        if (articleCount <= 6) {
            console.log('âš ï¸ Removing pagination - only', articleCount, 'posts');
            $('.post-pagination').remove();
        }
    }

    // Initialize the search functionality when the document is ready
    $(document).ready(function() {
        'use strict';
        
        // Log initial page state
        console.log('-------- PAGE LOAD --------');
        console.log('Articles on page:', $('.articles-grid article').length);
        console.log('Pagination elements:', $('.post-pagination').length);
        
        // Run initial pagination check on page load
        setTimeout(function() {
            console.log('CHECKING PAGINATION - should be hidden if â‰¤ 6 posts');
            var articleCount = $('.articles-grid article').length;
            var hasPagination = $('.post-pagination').length > 0;
            
            // Simple check - if 6 or fewer posts, hide pagination
            if (articleCount <= 6 && hasPagination) {
                console.log('âš ï¸ Found pagination with 6 or fewer posts - removing it');
                $('.post-pagination').remove();
            } else if (articleCount > 6 && !hasPagination) {
                // CRITICAL FIX: If we have more than 6 posts but NO pagination, we need to fetch it
                console.log('âš ï¸ MISSING PAGINATION: ' + articleCount + ' posts but no pagination!');
                
                // Get current search query from URL
                var searchQuery = new URLSearchParams(window.location.search).get('q') || '';
                console.log('Current search query:', searchQuery);
                
                // Always try to fetch pagination if missing, even for empty search
                // Make AJAX call to get pagination HTML
                $.ajax({
                        url: wh_custom_search.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'webhero_cs_ajax_handler',
                            search_query: searchQuery,
                            paged_posts: 1,
                            get_pagination_only: true,
                            security: wh_custom_search.nonce
                        },
                        success: function(response) {
                            if (response.success && response.data.post_content && response.data.post_content.pagination) {
                                console.log('âœ“ Retrieved missing pagination');
                                // Find the right container to append pagination to
                                var articlesContainer = $('.articles-container');
                                if (articlesContainer.length > 0) {
                                    console.log('Adding pagination after articles-container');
                                    articlesContainer.after('<div class="post-pagination">' + response.data.post_content.pagination + '</div>');
                                } else {
                                    // Alternative: try post-results
                                    var postResults = $('.post-results');
                                    if (postResults.length > 0) {
                                        console.log('Adding pagination to post-results container');
                                        postResults.append('<div class="post-pagination">' + response.data.post_content.pagination + '</div>');
                                    } else {
                                        console.log('Could not find container to add pagination!');
                                    }
                                }
                                
                                // Extra logging to debug where pagination was added
                                setTimeout(function() {
                                    console.log('Pagination elements after adding:', $('.post-pagination').length);
                                }, 100);
                            }
                        }
                    });
                }
            } else {
                console.log('âœ“ Pagination status correct - ' + articleCount + ' articles, pagination: ' + (hasPagination ? 'YES' : 'NO'));
            }
        }, 500);

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
            if (text === 'Â«') {
                return current > 1 ? current - 1 : 1;
            } else if (text === 'Â»') {
                return current + 1;
            }
            return parseInt(text) || 1;
        }
        
        var searchForm = $('.custom-search-form');
        var searchButton = searchForm.find('button');
        var searchResults = $('.custom-search-results');
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

        function performSearch(query, pagedPosts, updatePosts, isNewSearch) {
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
                    paged_posts: pagedPosts,
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
                            '<button class="close-debug" style="position:absolute; top:5px; right:5px;">Ã—</button>' +
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
                                    
                                    // Add container for posts
                                    postResults.append('<div id="search-posts-container"></div>');
                                    // Update reference to the newly created container
                                    postContainer = $('#search-posts-container');
                                }
                            }
                            
                            // Update post contents and show section
                            if (postResults && postResults.length > 0) {
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
                                
                                console.log('-------- AJAX SEARCH RESULTS --------');
                                
                                // Always remove existing pagination first to prevent duplicates
                                console.log('Removing any existing pagination:', $('.post-pagination').length, 'elements found');
                                $('.post-pagination').remove();
                                
                                // Check post count for pagination decision
                                var postCount = 0;
                                if (response.data.post_content && response.data.post_content.post_count) {
                                    postCount = parseInt(response.data.post_content.post_count);
                                    console.log('API post_count:', postCount);
                                } else {
                                    console.log('WARNING: post_count missing from API response');
                                    
                                    // Fallback to counting DOM elements if API doesn't provide count
                                    var domArticles = $('.articles-grid article').length;
                                    if (domArticles > 0) {
                                        console.log('Using DOM article count as fallback:', domArticles);
                                        postCount = domArticles;
                                    }
                                }
                                
                                console.log('Has pagination HTML?', response.data.post_content.pagination ? 'YES' : 'NO');
                                
                                // Only add pagination if more than 6 posts
                                if (postCount > 6 && response.data.post_content.pagination) {
                                    console.log('âœ“ Adding pagination for', postCount, 'posts');
                                    postResults.append('<div class="post-pagination">' + response.data.post_content.pagination + '</div>');
                                } else {
                                    console.log('âš ï¸ NOT adding pagination - post count:', postCount);
                                }
                                
                                // Final check - trust the API post count primarily, but verify DOM count too
                                var domArticleCount = $('.articles-grid article').length;
                                console.log('Final DOM article count:', domArticleCount);
                                
                                // CRITICAL: We should primarily trust the API post_count from server
                                // Only remove pagination as a fallback if we have a serious count mismatch
                                if (postCount > 6 && domArticleCount <= 3) {
                                    console.log('âš ï¸ WARNING: Significant article count mismatch - API:', postCount, 'vs DOM:', domArticleCount);
                                }
                                
                                // Safety check - if API says 6 or fewer posts, remove pagination
                                if (postCount <= 6 && $('.post-pagination').length > 0) {
                                    console.log('âš ï¸ Removing pagination - API post count is', postCount);
                                    $('.post-pagination').remove();
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
                    if (pagedPosts > 1) params.push('paged_posts=' + pagedPosts);
                    
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
            performSearch(query, 1, true, true);
        });

        $(document).on('click', '.post-pagination a', function(e) {
            e.preventDefault();
            var link = $(this);
            var query = searchForm.find('input[name="q"]').val();
            var pagedPosts = getPageNumber(link);
            
            // This is pagination, not a new search, so pass false for isNewSearch
            performSearch(query, pagedPosts, true, false);
        });
    });
