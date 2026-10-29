(function () {
	'use strict';
	var cfg = window.sdLocalStatsConfig || {}, started = false;
	var visitorKey = 'sd_stats_browser_v1', sessionKey = 'sd_stats_session_v1';
	function consent() { return typeof window.sdHasAnalyticsConsent === 'function' && window.sdHasAnalyticsConsent(); }
	function random(length) {
		if (!window.crypto || !window.crypto.getRandomValues) { throw new Error('No secure random source'); }
		var bytes = new Uint8Array(length / 2); window.crypto.getRandomValues(bytes);
		return Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
	}
	function identifiers() {
		function read(key) { try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) { return null; } }
		var now = Date.now(), visitor = read(visitorKey), session = read(sessionKey);
		if (!visitor || !/^[a-f0-9]{64}$/.test(visitor.id) || !Number.isFinite(visitor.expires) || visitor.expires <= now) { visitor = {id: random(64), expires: now + 30 * 86400000}; localStorage.setItem(visitorKey, JSON.stringify(visitor)); }
		if (!session || !/^[a-f0-9]{32}$/.test(session.id) || !Number.isFinite(session.updated) || session.updated + 30 * 60000 <= now || session.visitor !== visitor.id) { session = {id: random(32), visitor: visitor.id}; }
		session.updated = now; localStorage.setItem(sessionKey, JSON.stringify(session));
		return {visitor:visitor.id, session:session.id};
	}
	function source() {
		var utm = new URL(window.location.href).searchParams.get('utm_source') || '', host = '';
		utm = utm.toLowerCase();
		if (['facebook','fb'].indexOf(utm) !== -1) { return 'facebook'; }
		if (['instagram','ig'].indexOf(utm) !== -1) { return 'instagram'; }
		if (['linkedin','li'].indexOf(utm) !== -1) { return 'linkedin'; }
		if (['email','newsletter','mailing'].indexOf(utm) !== -1) { return 'email'; }
		try { host = new URL(document.referrer).hostname.toLowerCase(); } catch (e) {}
		if (!host || host === location.hostname) { return 'direct'; }
		if (/(^|\.)(facebook\.com|fb\.com|fb\.me)$/.test(host)) { return 'facebook'; }
		if (/(^|\.)instagram\.com$/.test(host)) { return 'instagram'; }
		if (/(^|\.)linkedin\.com$/.test(host)) { return 'linkedin'; }
		return 'referral';
	}
	function send(kind, target) {
		if (!consent() || navigator.globalPrivacyControl || navigator.doNotTrack === '1' || !cfg.endpoint) { return; }
		try {
			var ids = identifiers();
			var body = JSON.stringify({context:cfg.context,kind:kind,target:target,visitor:ids.visitor,session:ids.session,event:random(32),consent:'granted',source:source()});
			// No form values, link text, query strings, referrer URLs or IP dimensions.
			fetch(cfg.endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:body,keepalive:true}).catch(function () {});
		} catch (e) { /* A storage/privacy failure must never break navigation. */ }
	}
	function classify(link) {
		var raw = link.getAttribute('href') || '', url;
		if (/^mailto:/i.test(raw)) { return ['contact','contact:email']; }
		if (/^tel:/i.test(raw)) { return ['contact','contact:phone']; }
		try { url = new URL(raw, location.href); } catch (e) { return null; }
		if (!/^https?:$/.test(url.protocol)) { return null; }
		// Matching known download URLs ignores unrelated tracking parameters.
		if (url.origin === location.origin && url.searchParams.get('action') === 'sdc_editorial_asset') {
			var attachment = url.searchParams.get('attachment');
			var match = Object.keys(cfg.downloads || {}).find(function (key) { var asset = new URL(key); return asset.searchParams.get('attachment') === attachment; });
			return match ? ['download',cfg.downloads[match]] : null;
		}
		if (/\.(pdf|epub|docx)$/i.test(url.pathname)) { return ['download','other:file']; }
		var profile = url.pathname.replace(/\/+$/,'').toLowerCase();
		if (/(^|\.)facebook\.com$/.test(url.hostname) && profile === '/proukraina.org') { return ['social','social:facebook']; }
		if (/(^|\.)instagram\.com$/.test(url.hostname) && profile === '/stowarzyszenie_proukraina') { return ['social','social:instagram']; }
		if (/(^|\.)linkedin\.com$/.test(url.hostname) && profile === '/company/stowarzyszenie-pro-ukraina') { return ['social','social:linkedin']; }
		if (url.origin === location.origin) { return ['click',(cfg.paths || {})[url.pathname] || 'other:internal']; }
		return ['click','other:external'];
	}
	window.sdInitLocalStats = function () {
		if (started || !consent() || navigator.globalPrivacyControl || navigator.doNotTrack === '1') { return; }
		started = true; send('view','');
	};
	window.sdRevokeLocalStats = function () {
		started = false;
		try { localStorage.removeItem(visitorKey); localStorage.removeItem(sessionKey); } catch (e) {}
	};
	document.addEventListener('click',function (event) {
		if (!started || event.defaultPrevented) { return; }
		var link = event.target.closest && event.target.closest('a[href]');
		if (!link || link.closest('#wpadminbar, .sd-consent, form') || link.hasAttribute('data-sd-consent-reset')) { return; }
		var action = classify(link); if (action) { send(action[0],action[1]); }
	});
	window.addEventListener('pageshow',function (event) { if (event.persisted && started) { send('view',''); } });
	window.addEventListener('storage',function (event) { if ((event.key === 'sd_consent_v3' || event.key === null) && !consent()) { window.sdRevokeLocalStats(); } });
}());
