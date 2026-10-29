(function () {
	'use strict';
	var cfg = window.sdAnalyticsConfig || {}, initialised = false;
	function allowed() { return !!cfg.id && cfg.enabled !== false && typeof window.sdHasAnalyticsConsent === 'function' && window.sdHasAnalyticsConsent(); }
	function event(name, params) { if (allowed() && window.gtag) { window.gtag('event', name, params || {}); } }
	window.sdInitAnalytics = function () {
		if (!allowed()) { return; }
		window['ga-disable-' + cfg.id] = false;
		if (initialised) { window.gtag('consent','update',{analytics_storage:'granted'}); return; }
		initialised = true;
		window.dataLayer = window.dataLayer || [];
		window.gtag = function () { window.dataLayer.push(arguments); };
		// Basic consent mode: the library is absent before consent; advertising stays denied.
		window.gtag('consent','default',{analytics_storage:'denied',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied'});
		window.gtag('consent','update',{analytics_storage:'granted'});
		window.gtag('js',new Date());
		window.gtag('config',cfg.id,{send_page_view:false,page_location:cfg.pageUrl,page_referrer:'',page_title:cfg.pageTitle,
			allow_google_signals:false,allow_ad_personalization_signals:false,cookie_expires:31536000,cookie_update:false,cookie_flags:'SameSite=Lax;Secure'});
		if (!cfg.isHelp && !cfg.isThankyou) { event('page_view',{page_location:cfg.pageUrl,page_referrer:'',page_title:cfg.pageTitle}); }
		try {
			var seen = JSON.parse(localStorage.getItem('sd_seen_ga4_v1') || 'null');
			if (!seen || seen.id !== cfg.id || !Number.isFinite(seen.at) || seen.at + 365 * 86400000 <= Date.now()) {
				event('uzytkownik_unikalny',{}); localStorage.setItem('sd_seen_ga4_v1',JSON.stringify({id:cfg.id,at:Date.now()}));
			}
		} catch (e) {}
		// Only a one-time server-issued receipt can mark a successful submission.
		if (document.querySelector('[data-sd-submitted]')) { event('wyslanie_zgloszenia',{}); }
		var script = document.createElement('script'); script.async = true; script.id = 'sdc-ga4-tag'; script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(cfg.id); document.head.appendChild(script);
	};
	window.sdRevokeAnalytics = function () {
		if (cfg.id) { window['ga-disable-' + cfg.id] = true; }
		try { localStorage.removeItem('sd_seen_ga4_v1'); localStorage.removeItem('sd_seen_v1'); } catch (e) {}
		document.cookie.split(';').forEach(function (entry) {
			var name = entry.split('=')[0].trim(); if (name !== '_ga' && name.indexOf('_ga_') !== 0) { return; }
			document.cookie = name + '=; Max-Age=0; Path=/; SameSite=Lax; Secure';
			var parts = location.hostname.split('.'); while (parts.length > 1) { document.cookie = name + '=; Max-Age=0; Path=/; Domain=.' + parts.join('.') + '; SameSite=Lax; Secure'; parts.shift(); }
		});
		// Do not send a consent-denied ping. The disable flag stops collection by the already loaded library.
	};
	document.addEventListener('click', function (e) {
		if (!allowed() || e.defaultPrevented) { return; }
		var link = e.target.closest && e.target.closest('a[href]'); if (!link || link.closest('form,#wpadminbar')) { return; }
		var url; try { url = new URL(link.getAttribute('href'),location.href); } catch (err) { return; }
		var asset = (cfg.downloads || {})[url.origin + url.pathname + url.search];
		if (!asset && url.origin === location.origin && url.searchParams.get('action') === 'sdc_editorial_asset') { asset = (cfg.assets || {})[url.searchParams.get('attachment')]; }
		if (asset) { event('pobranie_publikacji',{file_name:asset.file, file_extension:asset.format, publication_code:asset.code, transport_type:'beacon'}); }
	});
}());
