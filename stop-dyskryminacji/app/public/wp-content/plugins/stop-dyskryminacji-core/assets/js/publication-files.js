/**
 * Panel „Pliki do pobrania" w edytorze publikacji.
 *
 * Zapisuje identyfikatory plików z biblioteki mediów w polach _sdc_pdf_id,
 * _sdc_epub_id i _sdc_docx_id. Z nich blok „Pobieranie publikacji" buduje
 * przyciski na stronie publikacji, a GA4 liczy pobrania.
 *
 * Napisane bez JSX i bez etapu budowania — tak jak blocks.js.
 */
( function ( wp ) {
	'use strict';

	var el               = wp.element.createElement;
	var __               = wp.i18n.__;
	var sprintf          = wp.i18n.sprintf;
	var useSelect        = wp.data.useSelect;
	var useDispatch      = wp.data.useDispatch;
	var Button           = wp.components.Button;
	var TextControl      = wp.components.TextControl;
	var MediaUpload      = wp.blockEditor.MediaUpload;
	var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
	var Panel            = ( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
		( wp.editPost && wp.editPost.PluginDocumentSettingPanel );

	var FORMATS = [
		{
			meta: '_sdc_pdf_id',
			label: __( 'PDF', 'stop-dyskryminacji-core' ),
			types: [ 'application/pdf' ]
		},
		{
			meta: '_sdc_epub_id',
			label: __( 'EPUB (e-book)', 'stop-dyskryminacji-core' ),
			types: [ 'application/epub+zip' ]
		},
		{
			meta: '_sdc_docx_id',
			label: __( 'Wersja edytowalna DOCX', 'stop-dyskryminacji-core' ),
			types: [ 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ]
		}
	];

	/**
	 * Rozmiar pliku w postaci czytelnej dla redaktora.
	 *
	 * @param {number} bytes Rozmiar w bajtach.
	 * @return {string} Np. „1,2 MB".
	 */
	function humanSize( bytes ) {
		if ( ! bytes ) {
			return '';
		}
		if ( bytes < 1024 * 1024 ) {
			return Math.max( 1, Math.round( bytes / 1024 ) ) + ' KB';
		}
		return ( bytes / 1024 / 1024 ).toFixed( 1 ).replace( '.', ',' ) + ' MB';
	}

	/**
	 * Jeden format: nazwa wybranego pliku i przyciski wyboru oraz usunięcia.
	 *
	 * @param {Object} props format, value (ID załącznika), onChange.
	 * @return {Object} Element.
	 */
	function FileRow( props ) {
		var format = props.format;
		var id     = parseInt( props.value, 10 ) || 0;

		var state = useSelect( function ( select ) {
			if ( ! id ) {
				return { media: null, done: true };
			}
			var core = select( 'core' );
			return {
				media: core.getMedia( id, { context: 'view' } ),
				done: core.hasFinishedResolution( 'getMedia', [ id, { context: 'view' } ] )
			};
		}, [ id ] );

		// Plik wybrany przed chwilą w oknie mediów — do czasu wczytania danych z REST API.
		var pickedState = wp.element.useState( null );
		var picked      = pickedState[ 0 ];
		var setPicked   = pickedState[ 1 ];

		var notices = useDispatch( 'core/notices' );
		var media   = state.media;
		var status;

		if ( ! id ) {
			status = __( 'Nie wybrano pliku.', 'stop-dyskryminacji-core' );
		} else if ( media ) {
			var name = ( media.source_url || '' ).split( '/' ).pop();
			var size = humanSize( media.media_details && media.media_details.filesize );
			status = size ? name + ' · ' + size : name;
		} else if ( picked && picked.id === id ) {
			status = picked.size ? picked.name + ' · ' + humanSize( picked.size ) : picked.name;
		} else if ( state.done ) {
			status = __( 'Pliku nie ma już w bibliotece mediów — wybierz go ponownie.', 'stop-dyskryminacji-core' );
		} else {
			status = __( 'Wczytywanie…', 'stop-dyskryminacji-core' );
		}

		function onSelect( chosen ) {
			var mime = chosen && ( chosen.mime || chosen.mime_type );
			if ( ! chosen || ! chosen.id ) {
				return;
			}
			if ( mime && format.types.indexOf( mime ) === -1 ) {
				notices.createErrorNotice(
					sprintf(
						/* translators: %s: nazwa formatu, np. PDF */
						__( 'Wybrany plik nie jest w formacie %s — nie został przypisany.', 'stop-dyskryminacji-core' ),
						format.label
					),
					{ type: 'snackbar' }
				);
				return;
			}
			setPicked( {
				id: chosen.id,
				name: chosen.filename || ( chosen.url || '' ).split( '/' ).pop(),
				size: chosen.filesizeInBytes || 0
			} );
			props.onChange( chosen.id );
		}

		return el(
			'div',
			{ className: 'sdc-publication-file', style: { marginBottom: '16px' } },
			el( 'strong', { style: { display: 'block' } }, format.label ),
			el( 'p', { style: { margin: '4px 0 8px', wordBreak: 'break-all' } }, status ),
			el(
				MediaUploadCheck,
				{
					fallback: el( 'p', null, __( 'Brak uprawnień do wgrywania plików.', 'stop-dyskryminacji-core' ) )
				},
				el( MediaUpload, {
					allowedTypes: format.types,
					value: id || undefined,
					title: sprintf(
						/* translators: %s: nazwa formatu */
						__( 'Plik do pobrania: %s', 'stop-dyskryminacji-core' ),
						format.label
					),
					onSelect: onSelect,
					render: function ( upload ) {
						return el(
							'div',
							{ style: { display: 'flex', gap: '8px', flexWrap: 'wrap' } },
							el(
								Button,
								{ variant: 'secondary', onClick: upload.open },
								sprintf(
									/* translators: %s: nazwa formatu */
									id ? __( 'Zmień plik: %s', 'stop-dyskryminacji-core' ) : __( 'Wybierz plik: %s', 'stop-dyskryminacji-core' ),
									format.label
								)
							),
							id ? el(
								Button,
								{
									variant: 'tertiary',
									isDestructive: true,
									onClick: function () {
										props.onChange( 0 );
									}
								},
								sprintf(
									/* translators: %s: nazwa formatu */
									__( 'Usuń plik: %s', 'stop-dyskryminacji-core' ),
									format.label
								)
							) : null
						);
					}
				} )
			)
		);
	}

	function PublicationFilesPanel() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		}, [] );
		var meta = useSelect( function ( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;

		if ( 'publikacja' !== postType || ! Panel ) {
			return null;
		}

		return el(
			Panel,
			{ name: 'sdc-publication-files', title: __( 'Pliki do pobrania', 'stop-dyskryminacji-core' ) },
			el(
				'p',
				{ className: 'description', style: { marginTop: 0 } },
				__( 'Pliki pojawią się na stronie publikacji jako przyciski pobierania. Możesz je wgrać tutaj albo wybrać z biblioteki mediów. Zmiany zapisuje przycisk „Zapisz".', 'stop-dyskryminacji-core' )
			),
			FORMATS.map( function ( format ) {
				return el( FileRow, {
					key: format.meta,
					format: format,
					value: meta[ format.meta ],
					onChange: function ( id ) {
						var change = {};
						change[ format.meta ] = id;
						editPost( { meta: change } );
					}
				} );
			} ),
			el( TextControl, {
				label: __( 'Kod w nazwach plików', 'stop-dyskryminacji-core' ),
				help: __( 'Np. P4. Pliki, których nazwa zaczyna się od tego kodu (P4_kompendium.pdf), trafią tu same po wgraniu w Media → Dodaj nowy plik. Bez kodu działa nazwa pliku taka jak adres albo tytuł publikacji.', 'stop-dyskryminacji-core' ),
				value: meta._sdc_file_code || '',
				autoComplete: 'off',
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				onChange: function ( value ) {
					editPost( { meta: { _sdc_file_code: value } } );
				}
			} ),
			el(
				'p',
				{ style: { marginTop: '12px' } },
				el(
					'a',
					{ href: 'edit.php?post_type=publikacja&page=sdc-pliki-publikacji' },
					__( 'Zestawienie plików wszystkich publikacji', 'stop-dyskryminacji-core' )
				)
			)
		);
	}

	wp.plugins.registerPlugin( 'sdc-publication-files', {
		render: PublicationFilesPanel,
		icon: 'media-document'
	} );

	/*
	 * Kolor serii — z projektu „logo i layout publikacji". Na stronie kolor
	 * niesie pasek na karcie publikacji, nadtytuł i ramka okładki.
	 */
	var SERIES = [
		{ value: 'morski', label: __( 'Morski (01 Kodeks)', 'stop-dyskryminacji-core' ), hex: '#0B5563' },
		{ value: 'ceglany', label: __( 'Ceglany (02 Poradnik)', 'stop-dyskryminacji-core' ), hex: '#9A3412' },
		{ value: 'ochra', label: __( 'Ochra', 'stop-dyskryminacji-core' ), hex: '#8A5A00' },
		{ value: 'zielony', label: __( 'Zielony (04 Kompendium)', 'stop-dyskryminacji-core' ), hex: '#2F6B3A' }
	];
	var BY_TYPE = { kodeks: 'morski', poradnik: 'ceglany', kompendium: 'zielony' };

	function PublicationSeriesPanel() {
		var data = useSelect( function ( select ) {
			var editor = select( 'core/editor' );
			var ids    = editor.getEditedPostAttribute( 'typ_publikacji' ) || [];
			var terms  = select( 'core' ).getEntityRecords( 'taxonomy', 'typ_publikacji', { per_page: -1 } ) || [];
			return {
				postType: editor.getCurrentPostType(),
				meta: editor.getEditedPostAttribute( 'meta' ) || {},
				slugs: terms.filter( function ( t ) { return ids.indexOf( t.id ) !== -1; } ).map( function ( t ) { return t.slug; } )
			};
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;

		if ( 'publikacja' !== data.postType || ! Panel ) {
			return null;
		}

		var chosen = data.meta._sdc_series_color || '';
		var auto   = 'morski';
		[ 'kompendium', 'poradnik', 'kodeks' ].some( function ( needle ) {
			var hit = data.slugs.some( function ( s ) { return s.indexOf( needle ) !== -1; } );
			if ( hit ) {
				auto = BY_TYPE[ needle ];
			}
			return hit;
		} );
		var current = SERIES.filter( function ( s ) { return s.value === ( chosen || auto ); } )[ 0 ];

		return el(
			Panel,
			{ name: 'sdc-publication-series', title: __( 'Kolor serii', 'stop-dyskryminacji-core' ) },
			el( wp.components.SelectControl, {
				label: __( 'Kolor publikacji na stronie', 'stop-dyskryminacji-core' ),
				value: chosen,
				options: [ { value: '', label: __( 'Według typu publikacji', 'stop-dyskryminacji-core' ) } ].concat(
					SERIES.map( function ( s ) { return { value: s.value, label: s.label }; } )
				),
				help: __( 'Według typu: Kodeks — morski, poradniki — ceglany, kompendium — zielony.', 'stop-dyskryminacji-core' ),
				__nextHasNoMarginBottom: true,
				__next40pxDefaultSize: true,
				onChange: function ( value ) {
					editPost( { meta: { _sdc_series_color: value } } );
				}
			} ),
			el(
				'p',
				{ style: { display: 'flex', alignItems: 'center', gap: '8px', marginTop: '12px' } },
				el( 'span', { 'aria-hidden': 'true', style: { width: '20px', height: '20px', borderRadius: '4px', background: current.hex, flex: '0 0 auto' } } ),
				sprintf(
					/* translators: %s: nazwa koloru */
					__( 'Teraz: %s', 'stop-dyskryminacji-core' ),
					current.label
				)
			)
		);
	}

	wp.plugins.registerPlugin( 'sdc-publication-series', {
		render: PublicationSeriesPanel,
		icon: 'art'
	} );
}( window.wp ) );
