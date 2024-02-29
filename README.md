# Matomo UniWueTracking Plugin

## Description

This plugin exposes a custom controller action that provides dynamically generated JS Tracking Code based on the provided location.

The generated tracker code submits the visit to the catch-all site as well as the site that matches the user's location best (if any).

## Embedding Code 

Requirements:
- jQuery

Code:
```javascript
<!-- Matomo Tracking -->
<script type="text/javascript">
    $.ajax({
        data: {
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