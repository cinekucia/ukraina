<?php
/**
 * Title: Najnowsze artykuły
 * Slug: stop-dyskryminacji/aktualnosci
 * Categories: sd-sekcje
 * Description: Trzy najnowsze artykuły i aktualności.
 * Keywords: artykuły, aktualności, baza wiedzy
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
			<h2 class="wp-block-heading">Aktualności</h2>
			<!-- /wp:heading -->
			<!-- wp:paragraph -->
			<p><a href="/aktualnosci/">Wszystkie artykuły</a></p>
			<!-- /wp:paragraph -->
		</div>
		<!-- /wp:group -->

		<!-- wp:query {"queryId":60,"query":{"perPage":3,"pages":1,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"style":{"spacing":{"margin":{"top":"1.5rem"}}}} -->
		<div class="wp-block-query" style="margin-top:1.5rem">
			<!-- wp:post-template {"className":"sd-grid sd-grid--3","layout":{"type":"default"}} -->
				<!-- wp:group {"className":"sd-card","layout":{"type":"default"}} -->
				<div class="wp-block-group sd-card">
					<!-- wp:post-featured-image {"isLink":true,"className":"sd-card__thumb"} /-->
					<!-- wp:post-terms {"term":"category","className":"sd-eyebrow"} /-->
					<!-- wp:post-title {"level":3,"isLink":true} /-->
					<!-- wp:post-excerpt {"excerptLength":20,"showMoreOnNewLine":false} /-->
					<!-- wp:group {"className":"sd-card__meta","layout":{"type":"flex","flexWrap":"wrap"}} -->
					<div class="wp-block-group sd-card__meta">
						<!-- wp:post-date {"format":"j F Y"} /-->
						<!-- wp:stopdysk/reading-time /-->
					</div>
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->
			<!-- /wp:post-template -->

			<!-- wp:query-no-results -->
				<!-- wp:paragraph --><p>Artykuły pojawią się tutaj po zaimportowaniu treści.</p><!-- /wp:paragraph -->
			<!-- /wp:query-no-results -->
		</div>
		<!-- /wp:query -->

	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
