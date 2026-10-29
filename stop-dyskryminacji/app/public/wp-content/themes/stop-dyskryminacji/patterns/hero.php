<?php
/**
 * Title: Nagłówek strony głównej
 * Slug: stop-dyskryminacji/hero
 * Categories: sd-sekcje
 * Description: Jedno zdanie opisujące sytuację użytkownika i jedno wezwanie do działania.
 * Keywords: hero, nagłówek, strona główna, pomoc
 *
 * Wzorzec oparty na układzie Discriminatie.nl: nad zgięciem strony jest tylko
 * pytanie o sytuację odbiorcy i jeden duży przycisk. Wszystko inne schodzi niżej.
 *
 * @package StopDyskryminacji
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"sd-section sd-hero","align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull sd-section sd-hero">
	<!-- wp:group {"align":"wide","className":"sd-section__inner","layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide sd-section__inner">

		<!-- wp:heading {"level":1,"style":{"typography":{"lineHeight":"1.1"}}} -->
		<h1 class="wp-block-heading" style="line-height:1.1">Czy spotkała Cię dyskryminacja w pracy?</h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"sd-lead"} -->
		<p class="sd-lead">Pomożemy Ci zrozumieć, co się stało, i pokażemy, co możesz zrobić dalej. Nie musisz znać przepisów ani nazywać tego „dyskryminacją”.</p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons {"className":"sd-hero__actions","style":{"spacing":{"margin":{"top":"2rem"}}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
		<div class="wp-block-buttons sd-hero__actions" style="margin-top:2rem">
			<!-- wp:button {"className":"is-style-fill"} -->
			<div class="wp-block-button is-style-fill"><a class="wp-block-button__link wp-element-button" href="/potrzebuje-pomocy/" style="font-size:1.25rem;padding:1.0625rem 2rem">Potrzebuję pomocy</a></div>
			<!-- /wp:button -->

			<!-- wp:button {"className":"is-style-outline"} -->
			<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/czy-to-dyskryminacja/">Sprawdź, czy to dyskryminacja</a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->

		<?php /* Sygnały zaufania. Osoba w kryzysie potrzebuje ich, zanim kliknie. */ ?>
		<!-- wp:html -->
		<ul class="sd-assurance">
			<li>
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"/></svg>
				Bezpłatnie
			</li>
			<li>
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"/></svg>
				Poufnie — bez podawania nazwiska
			</li>
			<li>
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"/></svg>
				Bez zobowiązań
			</li>
			<li>
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"/></svg>
				Odpowiadamy w 5 dni roboczych
			</li>
		</ul>
		<!-- /wp:html -->

	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
