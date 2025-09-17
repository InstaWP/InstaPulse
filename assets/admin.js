jQuery(document).ready(function($) {

    $('#instapulse-clear-data').on('click', function() {
        if (confirm('Are you sure you want to clear all InstaPulse data? This action cannot be undone.')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'instapulse_clear_data',
                    nonce: instapulse_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Data cleared - page will reload to show empty state
                        location.reload();
                    } else {
                        alert('Error clearing data. Please try again.');
                    }
                },
                error: function() {
                    alert('Error clearing data. Please try again.');
                }
            });
        }
    });

    setInterval(function() {
        $('.instapulse-stat-value').each(function() {
            var $this = $(this);
            if ($this.text().includes('ms')) {
                var currentTime = parseFloat($this.text());
                if (currentTime > 1000) {
                    $this.addClass('instapulse-slow');
                } else if (currentTime > 500) {
                    $this.addClass('instapulse-medium');
                } else {
                    $this.addClass('instapulse-fast');
                }
            }
        });
    }, 1000);

    $('.instapulse-table tbody tr').hover(
        function() {
            $(this).addClass('instapulse-hover');
        },
        function() {
            $(this).removeClass('instapulse-hover');
        }
    );

});