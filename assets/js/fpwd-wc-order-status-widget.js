jQuery(function($) {
    $('.fpwd-wc-order-status-widget__form form').on('submit', function(event) {
        event.preventDefault();

        const form = $(this);
        const orderNumber = form.find('input[name=fpwd-wc-order-status-widget-order-number]').val();
        const email = form.find('input[name=fpwd-wc-order-status-widget-user-email').val();
        const widgetId = form.find('input[name=fpwd-wc-order-status-widget-widget-id').val();

        form.find('.fpwd-wc-order-status-widget__result').html('Fetching result, please wait...');
        form.find('button').attr('disabled', 'disabled');

        $.post('/wp-admin/admin-ajax.php', {
            action: 'fpwd-wc-order-status-widget-ajax',
            orderNumber,
            email,
            widgetId
        }).success(function(html) {
            form.find('.fpwd-wc-order-status-widget__result').html(html);
            form.find('button').removeAttr('disabled');
        }).error(function() {
            form.find('.fpwd-wc-order-status-widget__result').html('Something went wrong. Please try again later.');
            form.find('button').removeAttr('disabled');
        });
    });
});