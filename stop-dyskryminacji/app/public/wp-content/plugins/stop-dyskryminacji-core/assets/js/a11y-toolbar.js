/**
 * Panel ułatwień dostępu.
 *
 * Pływający przycisk otwiera okno z ustawieniami: wielkość tekstu, dwa tryby
 * wysokiego kontrastu, wyróżnienie odnośników, czytelna czcionka i odstępy,
 * tryb czytania, linijka i maska do czytania, duży kursor, zatrzymanie animacji
 * i czytanie na głos (syntezator mowy przeglądarki — bez zewnętrznej usługi).
 *
 * Ustawienia trafiają do localStorage „sd-a11y" dopiero po wyborze użytkownika
 * i nie są nigdzie wysyłane. Klasy na <html> nakłada też skrypt w <head>
 * (includes/a11y-toolbar.php), żeby kolejne podstrony nie migały.
 */
( function () {
	'use strict';

	var KEY = 'sd-a11y';
	var cfg = window.sdcA11y || {};
	var root = document.documentElement;

	var T = {
		pl: {
			title: 'Ułatwienia dostępu', close: 'Zamknij panel', textSize: 'Wielkość tekstu',
			smaller: 'Zmniejsz tekst', bigger: 'Powiększ tekst',
			contrastBw: 'Kontrast czarno-biały', contrastYb: 'Żółty na czarnym', links: 'Wyróżnij odnośniki',
			dys: 'Czytelna czcionka i odstępy', reading: 'Tryb czytania', guide: 'Linijka do czytania',
			mask: 'Maska czytania', cursor: 'Duży kursor', motion: 'Zatrzymaj animacje',
			tts: 'Czytanie na głos', ttsPage: 'Czytaj stronę', ttsSel: 'Czytaj zaznaczenie', ttsPause: 'Pauza',
			ttsResume: 'Wznów', ttsStop: 'Zatrzymaj', ttsHint: 'Zaznacz fragment tekstu, żeby przeczytać tylko jego. Odczyt głosowy jest dodatkową pomocą; nie zastępuje czytnika ekranu.',
			ttsNoVoice: 'Przeglądarka nie ma głosu w tym języku — tekst przeczyta inny głos.',
			ttsReading: 'Czytanie…', ttsDone: 'Koniec czytania.', reset: 'Przywróć ustawienia',
			resetDone: 'Przywrócono ustawienia domyślne.', statement: 'Deklaracja dostępności', easy: 'Tekst w prostym języku',
			note: 'Ustawienia działają tylko w tej przeglądarce. Zakres sprawdzonej dostępności opisujemy w deklaracji dostępności.'
		},
		uk: {
			title: 'Налаштування доступності', close: 'Закрити панель', textSize: 'Розмір тексту',
			smaller: 'Зменшити текст', bigger: 'Збільшити текст',
			contrastBw: 'Чорно-білий контраст', contrastYb: 'Жовтий на чорному', links: 'Виділити посилання',
			dys: 'Зручний шрифт та інтервали', reading: 'Режим читання', guide: 'Лінійка для читання',
			mask: 'Маска для читання', cursor: 'Великий курсор', motion: 'Зупинити анімації',
			tts: 'Читання вголос', ttsPage: 'Читати сторінку', ttsSel: 'Читати виділене', ttsPause: 'Пауза',
			ttsResume: 'Продовжити', ttsStop: 'Зупинити', ttsHint: 'Виділіть фрагмент тексту, щоб прочитати лише його. Озвучення є додатковою допомогою; воно не замінює програму зчитування з екрана.',
			ttsNoVoice: 'У браузері немає голосу цією мовою — текст прочитає інший голос.',
			ttsReading: 'Читання…', ttsDone: 'Читання завершено.', reset: 'Скинути налаштування',
			resetDone: 'Налаштування скинуто.', statement: 'Декларація доступності', easy: '',
			note: 'Налаштування діють лише в цьому браузері. Обсяг перевірки доступності описано в декларації доступності.'
		},
		en: {
			title: 'Accessibility', close: 'Close panel', textSize: 'Text size',
			smaller: 'Decrease text', bigger: 'Increase text',
			contrastBw: 'Black and white contrast', contrastYb: 'Yellow on black', links: 'Highlight links',
			dys: 'Readable font and spacing', reading: 'Reading mode', guide: 'Reading guide',
			mask: 'Reading mask', cursor: 'Large cursor', motion: 'Stop animations',
			tts: 'Read aloud', ttsPage: 'Read page', ttsSel: 'Read selection', ttsPause: 'Pause',
			ttsResume: 'Resume', ttsStop: 'Stop', ttsHint: 'Select some text to read only that part. Speech playback is an extra aid; it does not replace a screen reader.',
			ttsNoVoice: 'Your browser has no voice in this language — another voice will read the text.',
			ttsReading: 'Reading…', ttsDone: 'Finished reading.', reset: 'Reset settings',
			resetDone: 'Default settings restored.', statement: 'Accessibility statement', easy: '',
			note: 'Settings apply only in this browser. The accessibility statement describes the scope of accessibility checks.'
		}
	};
	var lang = T[ cfg.lang ] ? cfg.lang : 'pl';
	var t = T[ lang ];
	var SPEECH_LANG = { pl: 'pl-PL', uk: 'uk-UA', en: 'en-GB' }[ lang ];
	var SIZES = [ 100, 115, 130, 145, 160 ];

	var ICON = {
		person: '<circle cx="12" cy="4.5" r="2"/><path d="M4 8.5c2.6.9 5.3 1.3 8 1.3s5.4-.4 8-1.3M12 9.8v4.7m0 0-3 7m3-7 3 7"/>',
		contrastBw: '<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 0 18z" fill="currentColor" stroke="none"/>',
		contrastYb: '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 16 12 7l4 9M9.6 13h4.8"/>',
		links: '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1.2 1.2M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.2-1.2"/>',
		dys: '<path d="M3 18 7.5 6 12 18M4.7 14h5.6M15 18V9.5M15 13a3 3 0 0 1 6 0v5"/>',
		reading: '<path d="M4 5h16M4 10h16M4 15h10M4 20h7"/>',
		guide: '<path d="M2 12h20"/><path d="M5 8h14M5 16h14" opacity=".45"/>',
		mask: '<rect x="2" y="3" width="20" height="18" rx="2"/><path d="M2 9.5h20M2 14.5h20"/>',
		cursor: '<path d="M5 3l13 8-6 1.5L9 19z"/>',
		motion: '<rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/>',
		speak: '<path d="M4 9h4l5-4v14l-5-4H4z"/><path d="M16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12"/>',
		reset: '<path d="M4 12a8 8 0 1 0 2.4-5.7M4 4v4.5h4.5"/>',
		close: '<path d="M6 6l12 12M18 6 6 18"/>'
	};
	function svg( name ) {
		return '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' + ICON[ name ] + '</svg>';
	}

	var OPTIONS = [
		{ key: 'contrast', value: 'bw', label: t.contrastBw, icon: 'contrastBw' },
		{ key: 'contrast', value: 'yb', label: t.contrastYb, icon: 'contrastYb' },
		{ key: 'links', label: t.links, icon: 'links' },
		{ key: 'dys', label: t.dys, icon: 'dys' },
		{ key: 'reading', label: t.reading, icon: 'reading' },
		{ key: 'guide', label: t.guide, icon: 'guide' },
		{ key: 'mask', label: t.mask, icon: 'mask' },
		{ key: 'cursor', label: t.cursor, icon: 'cursor' },
		{ key: 'motion', label: t.motion, icon: 'motion' }
	];

	/* ---------- Stan i zapis ---------- */

	function load() {
		try {
			var s = JSON.parse( localStorage.getItem( KEY ) || '{}' );
			return s && typeof s === 'object' ? s : {};
		} catch ( e ) {
			return {};
		}
	}
	var state = load();

	function save() {
		try {
			var any = Object.keys( state ).some( function ( k ) { return !! state[ k ]; } );
			if ( any ) {
				localStorage.setItem( KEY, JSON.stringify( state ) );
			} else {
				localStorage.removeItem( KEY );
			}
		} catch ( e ) { /* tryb prywatny — ustawienia tylko do końca wizyty */ }
	}

	function apply() {
		var keep = [];
		root.className.split( /\s+/ ).forEach( function ( c ) {
			if ( c && c.indexOf( 'sd-a11y-' ) !== 0 ) {
				keep.push( c );
			}
		} );
		var text = parseInt( state.text, 10 ) || 0;
		if ( text > 0 && text < SIZES.length ) {
			keep.push( 'sd-a11y-text-' + text );
		}
		if ( state.contrast === 'bw' || state.contrast === 'yb' ) {
			keep.push( 'sd-a11y-contrast-' + state.contrast );
		}
		[ 'links', 'dys', 'reading', 'cursor', 'motion' ].forEach( function ( k ) {
			if ( state[ k ] ) {
				keep.push( 'sd-a11y-' + k );
			}
		} );
		root.className = keep.join( ' ' );
		setGuide( !! state.guide, !! state.mask );
	}

	/* ---------- Linijka i maska do czytania ---------- */

	var guideEl = null, maskTop = null, maskBottom = null, lastY = window.innerHeight / 3;

	function place( y ) {
		lastY = y;
		var band = Math.max( 48, parseFloat( getComputedStyle( root ).fontSize ) * 3 );
		if ( guideEl ) {
			guideEl.style.transform = 'translateY(' + Math.round( y - 2 ) + 'px)';
		}
		if ( maskTop ) {
			maskTop.style.height = Math.max( 0, Math.round( y - band / 2 ) ) + 'px';
			maskBottom.style.top = Math.round( y + band / 2 ) + 'px';
		}
	}
	function onPointer( e ) {
		place( e.clientY );
	}
	function onFocus( e ) {
		if ( e.target && e.target.getBoundingClientRect && ! panel.contains( e.target ) ) {
			var r = e.target.getBoundingClientRect();
			place( r.top + r.height / 2 );
		}
	}
	function make( cls ) {
		var d = document.createElement( 'div' );
		d.className = cls;
		d.setAttribute( 'aria-hidden', 'true' );
		document.body.appendChild( d );
		return d;
	}
	function setGuide( guide, mask ) {
		if ( guide && ! guideEl ) {
			guideEl = make( 'sd-a11y-guide-line' );
		} else if ( ! guide && guideEl ) {
			guideEl.remove();
			guideEl = null;
		}
		if ( mask && ! maskTop ) {
			maskTop = make( 'sd-a11y-mask-part sd-a11y-mask-top' );
			maskBottom = make( 'sd-a11y-mask-part sd-a11y-mask-bottom' );
		} else if ( ! mask && maskTop ) {
			maskTop.remove();
			maskBottom.remove();
			maskTop = maskBottom = null;
		}
		var on = guide || mask;
		document[ on ? 'addEventListener' : 'removeEventListener' ]( 'pointermove', onPointer, { passive: true } );
		document[ on ? 'addEventListener' : 'removeEventListener' ]( 'focusin', onFocus );
		if ( on ) {
			place( lastY );
		}
	}

	/* ---------- Interfejs ---------- */

	var toggle = document.createElement( 'button' );
	toggle.type = 'button';
	toggle.className = 'sd-a11y-toggle';
	toggle.setAttribute( 'aria-expanded', 'false' );
	toggle.setAttribute( 'aria-controls', 'sd-a11y-panel' );
	toggle.setAttribute( 'aria-label', t.title );
	toggle.innerHTML = svg( 'person' );

	var panel = document.createElement( 'div' );
	panel.id = 'sd-a11y-panel';
	panel.className = 'sd-a11y-panel';
	panel.setAttribute( 'role', 'dialog' );
	panel.setAttribute( 'aria-modal', 'false' );
	panel.setAttribute( 'aria-labelledby', 'sd-a11y-title' );
	panel.hidden = true;

	var html = '<div class="sd-a11y-panel__head"><h2 id="sd-a11y-title" tabindex="-1">' + t.title + '</h2>'
		+ '<button type="button" class="sd-a11y-close" data-act="close" aria-label="' + t.close + '">' + svg( 'close' ) + '</button></div>'
		+ '<div class="sd-a11y-size" role="group" aria-labelledby="sd-a11y-size-label">'
		+ '<span id="sd-a11y-size-label" class="sd-a11y-label">' + t.textSize + '</span>'
		+ '<button type="button" data-act="smaller" aria-label="' + t.smaller + '"><span aria-hidden="true">A−</span></button>'
		+ '<output class="sd-a11y-size__value" aria-live="polite"></output>'
		+ '<button type="button" data-act="bigger" aria-label="' + t.bigger + '"><span aria-hidden="true">A+</span></button></div>'
		+ '<div class="sd-a11y-grid">';
	OPTIONS.forEach( function ( o, i ) {
		html += '<button type="button" class="sd-a11y-opt" data-opt="' + i + '" aria-pressed="false">' + svg( o.icon ) + '<span>' + o.label + '</span></button>';
	} );
	html += '</div>';

	var synth = window.speechSynthesis;
	var canSpeak = !! ( synth && window.SpeechSynthesisUtterance );
	if ( canSpeak ) {
		html += '<div class="sd-a11y-tts" role="group" aria-labelledby="sd-a11y-tts-label">'
			+ '<span id="sd-a11y-tts-label" class="sd-a11y-label">' + svg( 'speak' ) + t.tts + '</span>'
			+ '<div class="sd-a11y-tts__buttons">'
			+ '<button type="button" data-act="read">' + t.ttsPage + '</button>'
			+ '<button type="button" data-act="pause" disabled>' + t.ttsPause + '</button>'
			+ '<button type="button" data-act="stop" disabled>' + t.ttsStop + '</button></div>'
			+ '<p class="sd-a11y-hint">' + t.ttsHint + '</p>'
			+ '<p class="sd-a11y-hint sd-a11y-tts__voice" hidden>' + t.ttsNoVoice + '</p></div>';
	}

	html += '<div class="sd-a11y-foot">'
		+ '<button type="button" class="sd-a11y-reset" data-act="reset">' + svg( 'reset' ) + t.reset + '</button>';
	var links = [];
	if ( cfg.statement ) {
		links.push( '<a href="' + cfg.statement + '">' + t.statement + '</a>' );
	}
	if ( cfg.easy && t.easy ) {
		links.push( '<a href="' + cfg.easy + '">' + t.easy + '</a>' );
	}
	if ( links.length ) {
		html += '<p class="sd-a11y-panel__links">' + links.join( '<span aria-hidden="true"> · </span>' ) + '</p>';
	}
	html += '<p class="sd-a11y-note">' + t.note + '</p>'
		+ '<p class="sd-sr-only" role="status" aria-live="polite" data-status></p></div>';
	panel.innerHTML = html;

	// Keep the control in the header flow so it cannot obscure page content at narrow widths.
	var header = document.querySelector( '.sd-header__inner' );
	if ( header ) {
		toggle.classList.add( 'sd-a11y-toggle--inline' );
		header.insertBefore( toggle, header.querySelector( '.sd-nav' ) );
	}

	// Zaraz po odnośniku „Przejdź do treści" — przycisk jest jednym z pierwszych
	// elementów w kolejności klawiatury, a nie ostatnim po stopce.
	var skip = document.querySelector( '.sd-skip-link, a[href="#sd-main"]' );
	if ( skip && skip.parentNode ) {
		skip.parentNode.insertBefore( panel, skip.nextSibling );
		if ( ! header ) { skip.parentNode.insertBefore( toggle, panel ); }
	} else {
		document.body.insertBefore( panel, document.body.firstChild );
		if ( ! header ) { document.body.insertBefore( toggle, panel ); }
	}

	var statusEl = panel.querySelector( '[data-status]' );
	function announce( msg ) {
		statusEl.textContent = '';
		window.setTimeout( function () { statusEl.textContent = msg; }, 50 );
	}

	function render() {
		var text = parseInt( state.text, 10 ) || 0;
		panel.querySelector( '.sd-a11y-size__value' ).textContent = SIZES[ text ] + '%';
		panel.querySelector( '[data-act="smaller"]' ).disabled = text <= 0;
		panel.querySelector( '[data-act="bigger"]' ).disabled = text >= SIZES.length - 1;
		OPTIONS.forEach( function ( o, i ) {
			var on = o.value ? state[ o.key ] === o.value : !! state[ o.key ];
			panel.querySelector( '[data-opt="' + i + '"]' ).setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );
	}

	function open() {
		panel.hidden = false;
		toggle.setAttribute( 'aria-expanded', 'true' );
		render();
		panel.querySelector( '#sd-a11y-title' ).focus();
	}
	function close( returnFocus ) {
		panel.hidden = true;
		toggle.setAttribute( 'aria-expanded', 'false' );
		if ( returnFocus ) {
			toggle.focus();
		}
	}

	toggle.addEventListener( 'click', function () {
		if ( panel.hidden ) {
			open();
		} else {
			close( true );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && ! panel.hidden ) {
			e.preventDefault();
			e.stopPropagation();
			close( true );
		}
	} );
	// Nonmodal: let Tab leave naturally, then remove the overlay without moving focus.
	document.addEventListener( 'focusin', function ( e ) {
		if ( ! panel.hidden && ! panel.contains( e.target ) && ! toggle.contains( e.target ) ) {
			close( false );
		}
	} );
	document.addEventListener( 'click', function ( e ) {
		if ( ! panel.hidden && ! panel.contains( e.target ) && ! toggle.contains( e.target ) ) {
			close( false );
		}
	} );

	// Zaznaczenie zapamiętane przed kliknięciem — klik w przycisk może je wyczyścić.
	var pendingSelection = '';
	panel.addEventListener( 'mousedown', function ( e ) {
		if ( e.target.closest && e.target.closest( '[data-act="read"]' ) ) {
			pendingSelection = String( window.getSelection ? window.getSelection() : '' ).trim();
		}
	} );

	panel.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( 'button' ) : null;
		if ( ! btn || ! panel.contains( btn ) ) {
			return;
		}
		var act = btn.getAttribute( 'data-act' );
		var text = parseInt( state.text, 10 ) || 0;

		if ( btn.hasAttribute( 'data-opt' ) ) {
			var o = OPTIONS[ parseInt( btn.getAttribute( 'data-opt' ), 10 ) ];
			if ( o.value ) {
				state[ o.key ] = state[ o.key ] === o.value ? '' : o.value;   // kontrasty wykluczają się
			} else {
				state[ o.key ] = ! state[ o.key ];
			}
		} else if ( act === 'smaller' && text > 0 ) {
			state.text = text - 1;
			announce( t.textSize + ': ' + SIZES[ state.text ] + '%' );
		} else if ( act === 'bigger' && text < SIZES.length - 1 ) {
			state.text = text + 1;
			announce( t.textSize + ': ' + SIZES[ state.text ] + '%' );
		} else if ( act === 'reset' ) {
			state = {};
			stopSpeech();
			announce( t.resetDone );
		} else if ( act === 'close' ) {
			close( true );
			return;
		} else if ( act === 'read' ) {
			startSpeech();
			return;
		} else if ( act === 'pause' ) {
			pauseSpeech();
			return;
		} else if ( act === 'stop' ) {
			stopSpeech();
			return;
		} else {
			return;
		}
		save();
		apply();
		render();
	} );

	/* ---------- Czytanie na głos ---------- */

	var BLOCKS = 'h1,h2,h3,h4,h5,h6,p,li,dt,dd,th,td,figcaption,blockquote,summary,label,legend';
	var reading = null, paused = false, voice = null, voiceChecked = false;

	function findVoice() {
		if ( ! canSpeak ) {
			return;
		}
		var prefix = SPEECH_LANG.slice( 0, 2 ).toLowerCase();
		var list = synth.getVoices() || [];
		voice = null;
		for ( var i = 0; i < list.length; i++ ) {
			if ( ( list[ i ].lang || '' ).toLowerCase().replace( '_', '-' ).indexOf( prefix ) === 0 ) {
				voice = list[ i ];
				break;
			}
		}
		if ( list.length ) {
			voiceChecked = true;
			var note = panel.querySelector( '.sd-a11y-tts__voice' );
			if ( note ) {
				note.hidden = !! voice;
			}
		}
	}
	if ( canSpeak ) {
		findVoice();
		if ( typeof synth.addEventListener === 'function' ) {
			synth.addEventListener( 'voiceschanged', findVoice );
		}
	}

	function visible( el ) {
		// Bez okruszków, menu i przycisków udostępniania — czytanie zaczyna się od tytułu.
		if ( el.closest( '[aria-hidden="true"], [hidden], .sd-sr-only, .screen-reader-text, script, style, noscript, nav, .sd-breadcrumbs, .sd-share, .sd-filters' ) ) {
			return false;
		}
		var r = el.getBoundingClientRect();
		return r.width > 0 && r.height > 0;
	}

	/** Bloki treści głównej, bez zagnieżdżonych duplikatów (np. <li> z <p> w środku). */
	function pageBlocks() {
		var main = document.querySelector( 'main' ) || document.body;
		var all = Array.prototype.slice.call( main.querySelectorAll( BLOCKS ) );
		return all.filter( function ( el ) {
			return visible( el ) && el.textContent.trim().length > 1 && ! el.querySelector( BLOCKS );
		} );
	}

	/** Dłuższe teksty dzielone na zdania — syntezatory potrafią urwać długą wypowiedź. */
	function chunks( text ) {
		var parts = text.replace( /\s+/g, ' ' ).trim().match( /[^.!?…]+[.!?…]*\s*/g ) || [ text ];
		var out = [], cur = '';
		parts.forEach( function ( p ) {
			if ( ( cur + p ).length > 220 && cur ) {
				out.push( cur );
				cur = p;
			} else {
				cur += p;
			}
		} );
		if ( cur.trim() ) {
			out.push( cur );
		}
		return out;
	}

	function setTtsButtons( active ) {
		var pause = panel.querySelector( '[data-act="pause"]' );
		pause.disabled = ! active;
		pause.textContent = paused ? t.ttsResume : t.ttsPause;
		panel.querySelector( '[data-act="stop"]' ).disabled = ! active;
	}

	function mark( el ) {
		var prev = document.querySelector( '.sd-a11y-speaking' );
		if ( prev ) {
			prev.classList.remove( 'sd-a11y-speaking' );
		}
		if ( el ) {
			el.classList.add( 'sd-a11y-speaking' );
			var r = el.getBoundingClientRect();
			if ( r.top < 0 || r.bottom > window.innerHeight ) {
				el.scrollIntoView( { block: 'center', behavior: root.classList.contains( 'sd-a11y-motion' ) ? 'auto' : 'smooth' } );
			}
		}
	}

	function startSpeech() {
		if ( ! canSpeak ) {
			return;
		}
		if ( ! voiceChecked ) {
			findVoice();
		}
		stopSpeech();
		var selected = pendingSelection || String( window.getSelection ? window.getSelection() : '' ).trim();
		pendingSelection = '';
		var queue = [];
		if ( selected ) {
			chunks( selected ).forEach( function ( c ) { queue.push( { text: c, el: null } ); } );
		} else {
			pageBlocks().forEach( function ( el ) {
				chunks( el.textContent ).forEach( function ( c ) { queue.push( { text: c, el: el } ); } );
			} );
		}
		if ( ! queue.length ) {
			return;
		}
		reading = queue;
		paused = false;
		setTtsButtons( true );
		announce( t.ttsReading );
		queue.forEach( function ( item, i ) {
			var u = new window.SpeechSynthesisUtterance( item.text );
			u.lang = SPEECH_LANG;
			if ( voice ) {
				u.voice = voice;
			}
			u.onstart = function () { mark( item.el ); };
			if ( i === queue.length - 1 ) {
				u.onend = function () {
					if ( reading === queue ) {
						finishSpeech( true );
					}
				};
			}
			synth.speak( u );
		} );
	}

	function pauseSpeech() {
		if ( ! reading ) {
			return;
		}
		if ( paused ) {
			synth.resume();
			paused = false;
		} else {
			synth.pause();
			paused = true;
		}
		setTtsButtons( true );
	}

	function finishSpeech( done ) {
		reading = null;
		paused = false;
		mark( null );
		if ( panel.querySelector( '[data-act="pause"]' ) ) {
			setTtsButtons( false );
		}
		if ( done ) {
			announce( t.ttsDone );
		}
	}

	function stopSpeech() {
		if ( canSpeak && ( reading || synth.speaking ) ) {
			reading = null;
			synth.cancel();
		}
		finishSpeech( false );
	}
	window.addEventListener( 'pagehide', function () {
		if ( canSpeak ) {
			synth.cancel();
		}
	} );

	apply();
	render();
}() );
