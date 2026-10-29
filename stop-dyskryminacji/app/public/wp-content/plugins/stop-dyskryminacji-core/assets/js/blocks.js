/**
 * Rejestracja bloków po stronie edytora.
 *
 * Napisane bez JSX i bez etapu budowania — plik działa w przeglądarce takim,
 * jakim leży w repozytorium. Dzięki temu Zamawiający może poprawić blok bez
 * instalowania node_modules, a przekazane repozytorium nie wymaga odtwarzania
 * środowiska narzędziowego.
 *
 * Podgląd zapewnia wp.serverSideRender: edytor pokazuje dokładnie to, co
 * wygeneruje PHP, więc nie trzeba utrzymywać drugiej implementacji w JS.
 */
( function ( blocks, element, serverSideRender, blockEditor, components, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	var SSR           = serverSideRender;
	var useBlockProps = blockEditor.useBlockProps;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody     = components.PanelBody;
	var SelectControl = components.SelectControl;
	var Notice        = components.Notice;

	/**
	 * Wspólna funkcja edycji: ramka bloku + podgląd z serwera.
	 *
	 * @param {string} name   Nazwa bloku.
	 * @param {Object} props  Właściwości bloku.
	 * @param {Array}  extras Dodatkowe elementy (np. panel ustawień).
	 * @return {Object} Element.
	 */
	function preview( name, props, extras ) {
		var children = ( extras || [] ).concat( [
			el( SSR, { key: 'ssr', block: name, attributes: props.attributes } )
		] );
		return el( 'div', useBlockProps(), children );
	}

	/**
	 * Blok bez własnych ustawień.
	 *
	 * @param {string} name Nazwa bloku.
	 */
	function registerSimple( name ) {
		blocks.registerBlockType( name, {
			edit: function ( props ) {
				return preview( name, props );
			},
			save: function () {
				return null;   // blok dynamiczny — treść powstaje w PHP
			}
		} );
	}

	[
		'stopdysk/help-form',
		'stopdysk/self-assessment',
		'stopdysk/publication-downloads',
		'stopdysk/publication-formats',
		'stopdysk/publication-filters',
		'stopdysk/institution-finder',
		'stopdysk/help-cta',
		'stopdysk/language-switcher',
		'stopdysk/share',
		'stopdysk/breadcrumbs',
		'stopdysk/reading-time',
		'stopdysk/last-updated',
		'stopdysk/sitemap',
		'stopdysk/brand'
	].forEach( registerSimple );

	/**
	 * Zestaw znaków — jedyny blok z ustawieniem i z ostrzeżeniem dla redaktora.
	 */
	blocks.registerBlockType( 'stopdysk/eu-lockup', {
		edit: function ( props ) {
			var extras = [
				el(
					Notice,
					{
						key: 'warn',
						status: 'warning',
						isDismissible: false
					},
					__( 'Oznakowanie wymagane przez zasady promocji projektu. Usunięcie go z szablonu oznacza pomniejszenie dofinansowania.', 'stop-dyskryminacji-core' )
				),
				el(
					InspectorControls,
					{ key: 'inspector' },
					el(
						PanelBody,
						{ title: __( 'Umiejscowienie', 'stop-dyskryminacji-core' ) },
						el( SelectControl, {
							label: __( 'Wariant', 'stop-dyskryminacji-core' ),
							value: props.attributes.context,
							options: [
								{ label: __( 'Pasek na górze strony (obowiązkowy)', 'stop-dyskryminacji-core' ), value: 'header' },
								{ label: __( 'Stopka', 'stop-dyskryminacji-core' ), value: 'footer' },
								{ label: __( 'W treści, obok informacji o dofinansowaniu', 'stop-dyskryminacji-core' ), value: 'inline' }
							],
							onChange: function ( value ) {
								props.setAttributes( { context: value } );
							}
						} )
					)
				)
			];
			return preview( 'stopdysk/eu-lockup', props, extras );
		},
		save: function () {
			return null;
		}
	} );

}(
	window.wp.blocks,
	window.wp.element,
	window.wp.serverSideRender,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n
) );
