/**
 * Warstwa interakcji motywu.
 *
 * Zasada: strona działa bez tego pliku. Skrypt tylko ulepsza — nawigacja,
 * spis treści i kopiowanie odnośnika mają działające odpowiedniki w HTML.
 * Dzięki temu awaria JS nie odcina nikogo od treści ani od modułu zgłoszeniowego.
 */
( function () {
	'use strict';

	var t = window.sdStrings || {};

	/*
	 * Menu mobilne obsługuje sam blok core/navigation (Interactivity API).
	 * Motyw tylko przesuwa próg przełączenia w CSS — nie dublujemy tu tej logiki,
	 * bo druga implementacja rozjechałaby się z zachowaniem bloku i z jego ARIA.
	 */

	/* ---- „Na tej stronie" ---------------------------------------------- */

	/**
	 * Buduje spis treści z nagłówków H2 artykułu.
	 * Wzorzec zaczerpnięty z serwisu Acas: długi poradnik od razu pokazuje
	 * swoją strukturę, zamiast zmuszać do przewijania w ciemno.
	 */
	function initToc() {
		var host = document.querySelector( '[data-sd-toc]' );
		if ( ! host ) {
			return;
		}
		var scope = document.querySelector( host.getAttribute( 'data-sd-toc' ) || '.sd-article__body' );
		if ( ! scope ) {
			host.remove();
			return;
		}

		var headings = scope.querySelectorAll( 'h2' );
		if ( headings.length < 3 ) {
			host.remove();   // przy dwóch sekcjach spis treści tylko przeszkadza
			return;
		}

		var list = document.createElement( 'ol' );
		Array.prototype.forEach.call( headings, function ( h, i ) {
			if ( ! h.id ) {
				h.id = 'sekcja-' + ( i + 1 );
			}
			var li = document.createElement( 'li' );
			var a  = document.createElement( 'a' );
			a.href = '#' + h.id;
			a.textContent = h.textContent.trim();
			li.appendChild( a );
			list.appendChild( li );
		} );

		var heading = document.createElement( 'h2' );
		heading.textContent = t.onThis || 'Na tej stronie';
		host.appendChild( heading );
		host.appendChild( list );
		host.hidden = false;
	}

	/* ---- Udostępnianie -------------------------------------------------- */

	/**
	 * Przycisk „Kopiuj odnośnik". Obok niego w HTML stoją zwykłe odnośniki
	 * do serwisów społecznościowych, więc funkcja udostępniania działa
	 * także przy wyłączonym JS.
	 */
	function initShare() {
		document.querySelectorAll( '[data-sd-copy]' ).forEach( function ( btn ) {
			btn.hidden = false;
			btn.addEventListener( 'click', function () {
				var url = btn.getAttribute( 'data-sd-copy' ) || window.location.href;
				var done = function () {
					var live = document.getElementById( 'sd-live' );
					if ( live ) {
						live.textContent = t.copied || 'Skopiowano adres strony';
					}
					var label = btn.querySelector( '[data-sd-copy-label]' );
					if ( label ) {
						var original = label.textContent;
						label.textContent = t.copied || 'Skopiowano';
						setTimeout( function () { label.textContent = original; }, 2500 );
					}
				};

				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( url ).then( done );
				} else {
					var f = document.createElement( 'input' );
					f.value = url;
					document.body.appendChild( f );
					f.select();
					try { document.execCommand( 'copy' ); done(); } catch ( e ) {}
					f.remove();
				}
			} );
		} );
	}

	/* ---- Oznaczenie bieżącej pozycji menu ------------------------------- */

	function markCurrent() {
		var here = window.location.pathname.replace( /\/+$/, '' );
		document.querySelectorAll( '.sd-nav a[href]' ).forEach( function ( a ) {
			try {
				var p = new URL( a.href, window.location.origin ).pathname.replace( /\/+$/, '' );
				if ( p && p === here ) {
					a.setAttribute( 'aria-current', 'page' );
				}
			} catch ( e ) {}
		} );
	}

	/* ---- Cel odnośnika „Przejdź do treści głównej" ----------------------- */

	/**
	 * Element <main> nie przyjmuje fokusu, więc część przeglądarek po użyciu
	 * odnośnika pomijającego przesuwa tylko punkt startowy klawisza Tab, ale
	 * nie sam fokus — czytnik ekranu czyta wtedy dalej od góry strony.
	 * tabindex="-1" sprawia, że fokus faktycznie ląduje na treści (WCAG 2.4.1).
	 * Bez JavaScriptu odnośnik nadal działa, tylko mniej dokładnie.
	 */
	function initSkipTarget() {
		var main = document.getElementById( 'sd-main' );
		if ( main && ! main.hasAttribute( 'tabindex' ) ) {
			main.setAttribute( 'tabindex', '-1' );
		}
	}

	function boot() {
		initSkipTarget();
		initToc();
		initShare();
		markCurrent();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
