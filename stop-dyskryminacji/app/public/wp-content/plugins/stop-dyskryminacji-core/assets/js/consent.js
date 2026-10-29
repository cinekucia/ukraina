(function () {
	'use strict';
	var cfg = window.sdAnalyticsConfig || {}, key = 'sd_consent_v3';
	var version = '2026-10-26:' + (cfg.id || 'local'), lifetime = 180 * 86400000;
	var banner = document.getElementById('sd-consent');
	if (!banner) { return; }
	function read() {
		try {
			var state = JSON.parse(localStorage.getItem(key) || 'null');
			if (state && state.version === version && (state.choice === 'granted' || state.choice === 'denied') && Number.isFinite(state.at) && state.at <= Date.now() && state.at + lifetime > Date.now()) { return state.choice; }
			// Preserve an existing refusal, never extend an old permission to Google.
			if (!state && (localStorage.getItem('sd_consent_v2') === 'denied' || localStorage.getItem('sd_consent_v1') === 'denied')) { write('denied'); return 'denied'; }
		} catch (e) {}
		return null;
	}
	function write(choice) { try { localStorage.setItem(key, JSON.stringify({choice:choice, at:Date.now(), version:version})); localStorage.removeItem('sd_consent_v2'); localStorage.removeItem('sd_consent_v1'); } catch (e) {} }
	window.sdHasAnalyticsConsent = function () { return read() === 'granted' && !navigator.globalPrivacyControl && navigator.doNotTrack !== '1'; };
	function stop() {
		window.sdAnalyticsLoaded = false;
		if (window.sdRevokeAnalytics) { window.sdRevokeAnalytics(); }
		if (window.sdRevokeLocalStats) { window.sdRevokeLocalStats(); }
	}
	function load() {
		if (!window.sdHasAnalyticsConsent()) { stop(); return; }
		window.sdAnalyticsLoaded = true;
		if (window.sdInitAnalytics) { window.sdInitAnalytics(); }
		if (window.sdInitLocalStats) { window.sdInitLocalStats(); }
	}
	function show() { banner.hidden = false; document.documentElement.style.scrollPaddingBottom = banner.offsetHeight + 'px'; }
	function hide(keyboard) {
		var focused = banner.contains(document.activeElement); banner.hidden = true; document.documentElement.style.scrollPaddingBottom = '';
		if (focused && keyboard) { var skip = document.querySelector('.sd-skip-link'); if (skip) { skip.focus(); } }
	}
	function sync() { var choice = read(); if (choice === 'granted') { hide(false); load(); } else { stop(); if (choice === 'denied') { hide(false); } else { show(); } } }
	banner.addEventListener('click', function (event) {
		var button = event.target.closest('[data-sd-consent]'); if (!button) { return; }
		var choice = button.getAttribute('data-sd-consent'); if (choice !== 'granted' && choice !== 'denied') { return; }
		write(choice); hide(event.detail === 0); if (choice === 'granted') { load(); } else { stop(); }
	});
	document.addEventListener('click', function (event) {
		if (!event.target.closest('[data-sd-consent-reset]')) { return; }
		event.preventDefault(); write(''); stop(); show(); var first = banner.querySelector('button'); if (first) { first.focus(); }
	});
	window.addEventListener('storage', function (event) { if (event.key === key || event.key === null) { sync(); } });
	window.addEventListener('resize', function () { if (!banner.hidden) { show(); } });
	window.addEventListener('pageshow', sync);
	document.addEventListener('visibilitychange', function () { if (!document.hidden) { sync(); } });
	sync();
}());
