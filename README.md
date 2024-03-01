# Matomo UniWueTracking Plugin

## Description

This plugin exposes a custom controller action that provides dynamically generated JS Tracking Code based on the provided location.

The generated tracker code submits the visit to the catch-all site as well as the site that matches the user's location best (if any).

## Shibboleth notice

This plugin's controller action endpoint needs to be publicly available, i.e. bypass the Shibboleth protection.
This protection is defined in Ansible Repository -> Matomo Role -> defaults -> matomo_location_shibboleth.
It is important to update this variable when changing the plugin's name, controller action or the ajax data attribute order below.

## Embedding Code 

Requirements:
- jQuery

Code:
```javascript
<!-- Matomo Tracking -->
<script type="text/javascript">
    $.ajax({
        data: {
            // order is important for Shibboleth bypassing!
            "module": "UniWueTracking",
            "action": "getTrackingScript",
            "location": window.location.href
        },
        url: "<Base-URL of the Matomo Installation>",
        dataType: "html",
        success: function(script) {
            $('body').append(script);
        } 
    });
</script>
<!-- End Matomo Tracking -->
```