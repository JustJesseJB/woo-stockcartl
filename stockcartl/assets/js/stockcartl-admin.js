/**
 * StockCartl Admin JavaScript
 */
(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        initDeleteEntryButtons();
    });

    /**
     * Initialize delete entry buttons
     */
    function initDeleteEntryButtons() {
        $('.stockcartl-delete-entry').on('click', function(e) {
            e.preventDefault();
            
            var entryId = $(this).data('id');
            var row = $(this).closest('tr');
            
            if (confirm(stockcartl_admin.i18n.confirm_delete)) {
                deleteWaitlistEntry(entryId, row);
            }
        });
    }
    
    /**
     * Delete waitlist entry via AJAX
     * 
     * @param {number} entryId The entry ID
     * @param {jQuery} row The table row element
     */
    function deleteWaitlistEntry(entryId, row) {
        $.ajax({
            url: stockcartl_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'stockcartl_delete_entry',
                entry_id: entryId,
                nonce: stockcartl_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Fade out and remove row
                    row.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Show message if table is now empty
                        var table = $('.stockcartl-waitlist-table');
                        if (table.find('tbody tr').length === 0) {
                            table.after('<div class="stockcartl-no-entries"><p>' + stockcartl_admin.i18n.entry_deleted + '</p></div>');
                            table.hide();
                        }
                    });
                } else {
                    alert(response.data.message || stockcartl_admin.i18n.error);
                }
            },
            error: function() {
                alert(stockcartl_admin.i18n.error);
            }
        });
    }
    
    /**
     * Load waitlist entries for a product
     * 
     * @param {number} productId The product ID
     * @param {number} variationId The variation ID (optional)
     * @param {jQuery} container The container element
     */
    function loadWaitlistEntries(productId, variationId, container) {
        container.html('<p class="stockcartl-loading">' + stockcartl_admin.i18n.loading + '</p>');
        
        $.ajax({
            url: stockcartl_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'stockcartl_load_waitlist',
                product_id: productId,
                variation_id: variationId,
                nonce: stockcartl_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    var entries = response.data.entries;
                    
                    if (entries.length > 0) {
                        var tableHtml = '<table class="widefat stockcartl-entries-table">';
                        tableHtml += '<thead><tr>';
                        tableHtml += '<th>' + stockcartl_admin.i18n.email + '</th>';
                        tableHtml += '<th>' + stockcartl_admin.i18n.type + '</th>';
                        tableHtml += '<th>' + stockcartl_admin.i18n.position + '</th>';
                        tableHtml += '<th>' + stockcartl_admin.i18n.date_added + '</th>';
                        tableHtml += '<th>' + stockcartl_admin.i18n.actions + '</th>';
                        tableHtml += '</tr></thead><tbody>';
                        
                        $.each(entries, function(index, entry) {
                            var typeClass = 'stockcartl-type-' + entry.type;
                            var typeLabel = entry.type === 'deposit' ? 
                                stockcartl_admin.i18n.deposit : 
                                stockcartl_admin.i18n.free;
                                
                            tableHtml += '<tr>';
                            tableHtml += '<td>' + entry.email + '</td>';
                            tableHtml += '<td><span class="stockcartl-type-badge ' + typeClass + '">' + typeLabel + '</span></td>';
                            tableHtml += '<td>' + entry.position + '</td>';
                            tableHtml += '<td>' + entry.date_added + '</td>';
                            tableHtml += '<td><a href="#" class="stockcartl-delete-entry" data-id="' + entry.id + '">' + stockcartl_admin.i18n.delete + '</a></td>';
                            tableHtml += '</tr>';
                        });
                        
                        tableHtml += '</tbody></table>';
                        tableHtml += '<p class="stockcartl-export-link"><a href="' + stockcartl_admin.ajax_url + '?action=stockcartl_export_waitlist&product_id=' + productId + '&nonce=' + stockcartl_admin.nonce + '" class="button">' + stockcartl_admin.i18n.export_csv + '</a></p>';
                        
                        container.html(tableHtml);
                        
                        // Initialize delete buttons
                        initDeleteEntryButtons();
                    } else {
                        container.html('<p>' + stockcartl_admin.i18n.no_entries + '</p>');
                    }
                } else {
                    container.html('<p class="stockcartl-error">' + (response.data.message || stockcartl_admin.i18n.error) + '</p>');
                }
            },
            error: function() {
                container.html('<p class="stockcartl-error">' + stockcartl_admin.i18n.error + '</p>');
            }
        });
    }
    
    // Expose functions to global scope
    window.stockcartl = window.stockcartl || {};
    window.stockcartl.loadWaitlistEntries = loadWaitlistEntries;
    
})(jQuery);