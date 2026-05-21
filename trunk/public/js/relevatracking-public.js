(function () {
	var rlzFired = false;
	var trackerUrl = (typeof relevanzURL !== "undefined") ? relevanzURL : "";
	var trackerUrlAnonymous = (typeof relevanzAnonymousURL !== "undefined") ? relevanzAnonymousURL : "";

	function injectTarget() {
		return document.body || document.head || document.documentElement;
	}

	function fire(url) {
		if (rlzFired || !url) { return; }
		var target = injectTarget();
		if (!target) {
			// DOM not ready yet — retry once DOMContentLoaded fires.
			if (document.addEventListener) {
				document.addEventListener("DOMContentLoaded", function () { fire(url); }, false);
			}
			return;
		}
		rlzFired = true;
		var s = document.createElement("script");
		s.async = true;
		s.src = url;
		target.appendChild(s);
		try {
			window.dispatchEvent(new CustomEvent("relevanz:tags_fired"));
		} catch (e) { /* older browsers without CustomEvent constructor */ }
	}

	function fireIfReady() {
		if (rlzFired) { return; }
		if (window.relevanzAppForcePixel === true
		    || window.relevanzRetargetingForcePixel === true) {
			fire(trackerUrl);
		}
	}

	// 1) try immediately
	fireIfReady();

	// 2) listen to CMP events (best-effort — handlers no-op if event never fires)
	if (window.addEventListener) {
		window.addEventListener("CookieConfiguration_Update", fireIfReady, false);
		window.addEventListener("cmp:consentGiven",          fireIfReady, false);
		window.addEventListener("borlabs-cookie-consent-saved", fireIfReady, false);
	}

	// 3) polling fallback (~9 seconds: 30 × 300 ms)
	var tries = 0, max = 30, ivMs = 300;
	var iv = setInterval(function () {
		if (rlzFired) { clearInterval(iv); return; }
		tries++;
		fireIfReady();
		if (tries >= max) {
			clearInterval(iv);
			// 4) anonymous fallback — only set on the order-success page.
			if (window.relevanzDisableAnonymous !== true) {
				fire(trackerUrlAnonymous);
			}
		}
	}, ivMs);
})();
