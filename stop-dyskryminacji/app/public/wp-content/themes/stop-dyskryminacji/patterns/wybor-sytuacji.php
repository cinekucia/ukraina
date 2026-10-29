<?php
/**
 * Title: Wybór sytuacji („Co najlepiej opisuje Twoją sytuację?")
 * Slug: stop-dyskryminacji/wybor-sytuacji
 * Categories: sd-sekcje
 * Description: Cztery kafelki prowadzące użytkownika od jego sytuacji do właściwej ścieżki.
 * Keywords: wybór, sytuacja, ścieżka, selector
 *
 * Wzorzec z Worker.gov: użytkownik nie musi znać prawa. Zamiast kategorii
 * prawnych wybiera zdanie, które opisuje to, co go spotkało. Dopiero docelowa
 * strona tłumaczy, jak to się nazywa w przepisach i co z tego wynika.
 *
 * @package StopDyskryminacji
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"sd-section sd-section--surface","align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull sd-section sd-section--surface">
	<!-- wp:group {"align":"wide","className":"sd-section__inner","layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide sd-section__inner">

		<!-- wp:heading {"level":2} -->
		<h2 class="wp-block-heading">Co najlepiej opisuje Twoją sytuację?</h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"sd-lead"} -->
		<p class="sd-lead">Wybierz najbliższą odpowiedź. Pokażemy Ci, co mówi prawo i jakie masz możliwości.</p>
		<!-- /wp:paragraph -->

		<!-- wp:html -->
		<div class="sd-grid sd-grid--4" style="margin-top:1.5rem">

			<a class="sd-choice" href="/sytuacje/nierowne-traktowanie/">
				<span class="sd-choice__icon" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M12 3v18M7 7H3l2.5 5a2.5 2.5 0 0 0 5 0L8 7zM21 7h-4l2.5 5a2.5 2.5 0 0 1-5 0L17 7z"/><path d="M5 21h14"/></svg>
				</span>
				<span class="sd-choice__text">
					Potraktowano mnie gorzej niż innych
					<span class="sd-choice__hint">wynagrodzenie, awans, rekrutacja, zwolnienie</span>
				</span>
			</a>

			<a class="sd-choice" href="/sytuacje/mobbing-i-molestowanie/">
				<span class="sd-choice__icon" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M12 2 4 5v6c0 5 3.4 9.1 8 11 4.6-1.9 8-6 8-11V5z"/><path d="M12 8v4M12 16h.01"/></svg>
				</span>
				<span class="sd-choice__text">
					Doświadczam mobbingu lub molestowania
					<span class="sd-choice__hint">uporczywe nękanie, poniżanie, zachowania seksualne</span>
				</span>
			</a>

			<a class="sd-choice" href="/dla-swiadka/">
				<span class="sd-choice__icon" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7"/><circle cx="12" cy="12" r="3"/></svg>
				</span>
				<span class="sd-choice__text">
					Widziałem/am, jak ktoś był dyskryminowany
					<span class="sd-choice__hint">jak zareagować i wesprzeć osobę pokrzywdzoną</span>
				</span>
			</a>

			<a class="sd-choice" href="/czy-to-dyskryminacja/">
				<span class="sd-choice__icon" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M9.2 9a2.9 2.9 0 0 1 5.6 1c0 2-2.8 2.5-2.8 4"/><path d="M12 17h.01"/></svg>
				</span>
				<span class="sd-choice__text">
					Nie wiem, czy to była dyskryminacja
					<span class="sd-choice__hint">kilka pytań, które pomogą to ocenić</span>
				</span>
			</a>

		</div>
		<!-- /wp:html -->

	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
