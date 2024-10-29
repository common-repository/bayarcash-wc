( function( $ ) {
    const identification_fields = function() {
        if ( $( 'tr.recurring-totals' ).length < 1 ) {
            $( 'div#bayarcash-identification-fields' ).hide();
        } else {
            $( 'div#bayarcash-identification-fields' ).show();
        }
    };

    /* custom_order_button_text */
    $( 'form.checkout' ).on(
        'change',
        'input[name^=payment_method]',
        function() {
            $( document.body ).trigger( 'update_checkout' );
            identification_fields();
        }
    );

    $( document ).ready( identification_fields );

} )( jQuery );