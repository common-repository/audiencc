jQuery(document).ready(function (){
    jQuery('#wpau_domain').blur(function () {
        var data = {
            action: 'check_availability',
            subdomain: jQuery('#wpau_domain').val()
        };

        jQuery.post(ajaxurl, data, function(response) {
            jQuery('#wpau_domain_msg').html(response);
        });
    })

    jQuery('#wp-audiencc-create').click(function () {
        if (jQuery('#password').val() == '' || jQuery('#password').val() != jQuery('#confirm-password').val()) {
            alert ("Passwords doesn't match");
            return false;
        }

        if (jQuery('#wpau_email').val() == '') {
            alert ('Email is blank');
            return false;
        }

        if (jQuery('#wpau_subdomain').val() == '') {
            alert ('Subdomain is blank');
            return false;
        }

        if (jQuery('#wpau_domain_msg').html() != ' -  (Available)') {
            alert ('Subdomain is already taken, use a different one');
            return false;
        }

        if (!jQuery('#terms').is(':checked')) {
            alert ('You should accept our terms to create an account.');
            return false;
        }

    });
});