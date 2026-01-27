/**
 * Tests Search - Поиск тестов на странице категорий
 *
 * @package TestSystem
 * @version 1.0
 */

(function() {
    'use strict';

    const searchBtn = document.getElementById('tests-search-btn');
    const searchInput = document.getElementById('tests-search-input');

    if (searchBtn && searchInput) {
        searchBtn.addEventListener('click', function() {
            performSearch();
        });

        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });

        function performSearch() {
            const query = searchInput.value.trim();
            const url = new URL(window.location.href);

            if (query) {
                url.searchParams.set('search', query);
            } else {
                url.searchParams.delete('search');
            }

            url.searchParams.delete('page');
            window.location.href = url.toString();
        }
    }

    console.log('Tests search module initialized');

})();
