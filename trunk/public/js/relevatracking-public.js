var relevanzRetargetingForcePixelInterval = window.setInterval(function () {
            if (typeof relevanzAppForcePixel !== "undefined" && relevanzAppForcePixel === true) {
                window.clearInterval(relevanzRetargetingForcePixelInterval);
                var script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = relevanzURL;
                document.body.appendChild(script);
            }
        }, 500);
