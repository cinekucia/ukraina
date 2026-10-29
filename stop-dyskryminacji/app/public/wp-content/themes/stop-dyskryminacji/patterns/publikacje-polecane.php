<?php
/**
 * Title: Publikacje polecane
 * Slug: stop-dyskryminacji/publikacje-polecane
 * Categories: sd-sekcje
 * Description: Trzy najważniejsze publikacje projektu z odnośnikami do pobrania.
 * Keywords: publikacje, pobierz, PDF, EPUB
 *
 * Wskaźnik rozliczeniowy Zadania 1 to minimum 1 000 pobrań publikacji —
 * dlatego pobrania są widoczne już na stronie głównej, a nie dopiero
 * po wejściu w zakładkę.
 *
 * @package StopDyskryminacji
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"sd-section","align":"full","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull sd-section">
	<!-- wp:group {"align":"wide","className":"sd-section__inner","layout":{"type":"default"}} -->
	<div class="wp-block-group alignwide sd-section__inner">

		<!-- wp:group {"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap","verticalAlignment":"center"}} -->
		<div class="wp-block-group">
			<!-- wp:heading {"level":2} -->
			<h2 class="wp-block-heading">Publikacje projektu</h2>
			<!-- /wp:heading -->
			<!-- wp:paragraph -->
			<p><a href="/publikacje/">Zobacz wszystkie publikacje</a></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:paragraph {"className":"sd-lead"} -->
		<p class="sd-lead">Poznaj tematykę publikacji projektu. Pliki do pobrania pojawią się po zatwierdzeniu materiałów do publikacji.</p>
		<!-- /wp:paragraph -->

		<!-- wp:query {"queryId":50,"query":{"perPage":3,"pages":1,"offset":0,"postType":"publikacja","order":"asc","orderBy":"menu_order","sticky":"","inherit":false},"style":{"spacing":{"margin":{"top":"1.5rem"}}}} -->
		<div class="wp-block-query" style="margin-top:1.5rem">
			<!-- wp:post-template {"className":"sd-grid sd-grid--3","layout":{"type":"default"}} -->
				<!-- wp:group {"className":"sd-card sd-card--pub","layout":{"type":"default"}} -->
				<div class="wp-block-group sd-card sd-card--pub">
					<!-- wp:post-featured-image {"isLink":true,"className":"sd-card__thumb"} /-->
					<!-- wp:post-terms {"term":"typ_publikacji","className":"sd-eyebrow"} /-->
					<!-- wp:post-title {"level":3,"isLink":true} /-->
					<!-- wp:post-excerpt {"excerptLength":20,"showMoreOnNewLine":false} /-->
					<!-- wp:stopdysk/publication-formats /-->
				</div>
				<!-- /wp:group -->
			<!-- /wp:post-template -->

			<!-- wp:query-no-results -->
				<!-- wp:paragraph --><p>Publikacje pojawią się tutaj po zaimportowaniu treści.</p><!-- /wp:paragraph -->
			<!-- /wp:query-no-results -->
		</div>
		<!-- /wp:query -->

	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
