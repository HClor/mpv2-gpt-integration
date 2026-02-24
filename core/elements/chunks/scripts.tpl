{* Чанк: scripts - JavaScript для siteExtra
   Вызывается из: siteExtra.tpl
   Содержит: MinifyX JS, обработка AjaxForm
*}
{'MinifyX.javascript' | placeholder}
<script>
    $(document).ready(function() {
        // Removing AjaxForm success message
        if (typeof(AjaxForm) != 'undefined') {
            AjaxForm.Message.success = function() {};
        }
    });
    
    // Show AjaxForm success message in modal
    $(document).on('af_complete', function(event, response) {
        var form = response.form;
        if (response.success) {
            var msgText = $('<span>').text(response.message).html();
            $.fancybox.close();
            $.fancybox.open('<div class="popup" id="popup-call"><div class="popup-title">' + msgText + '</div></div>');
        }
    });
</script>
