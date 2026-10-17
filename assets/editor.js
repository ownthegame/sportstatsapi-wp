/* SportStatsAPI Widgets: the block editor. Plain wp.element, no build step.
   One edit component for every widget block; the preview is the real widget,
   drawn by the official embed script loaded into the editor's document. */
( function ( wp, cfg ) {
	'use strict';
	if ( ! wp || ! cfg ) {
		return;
	}
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var useRef = wp.element.useRef;
	var __ = wp.i18n.__;
	var be = wp.blockEditor;
	var c = wp.components;
	var D = 'live-scores-league-tables';

	var comps = {}; // competition key: {key, name, country, sport, free}, as searched

	// A team's name slug, as the embed script makes it: data-team takes it in every
	// widget (the table and scores highlight by name; codes work only server-side).
	function slug( t ) {
		return String( t || '' ).normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' ).toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /^-|-$/g, '' );
	}

	// Space between the controls of a panel.
	function row( child, key ) {
		return child ? el( 'div', { key: key, style: { marginBottom: '16px' } }, child ) : null;
	}

	function compLabel( x ) {
		return x.name + ( x.country ? ' · ' + x.country : '' ) + ( x.sport && x.sport.toLowerCase() !== 'soccer' ? ' · ' + x.sport : '' ) + ( x.free === false ? ' · ' + __( 'paid', D ) : '' );
	}

	// The data-* attributes, as the PHP render builds them (includes/widgets.php).
	function dataAttrs( slug, a ) {
		var w = cfg.widgets[ slug ], d = cfg.defaults;
		var out = { widget: w.kind, competition: a.competition, lang: a.lang || d.lang, theme: a.theme || d.theme, accent: a.accent || d.accent };
		if ( w.team !== 'none' ) out.team = a.team;
		w.options.forEach( function ( n ) { out[ n ] = a[ n ]; } );
		if ( a.font ) out.font = 'inherit';
		if ( a.radius !== '' ) out.radius = a.radius;
		if ( cfg.key ) out.key = cfg.key;
		Object.keys( out ).forEach( function ( k ) { if ( ! out[ k ] ) delete out[ k ]; } );
		return out;
	}

	function Preview( props ) {
		var ref = useRef();
		var sig = JSON.stringify( props.attrs );
		useEffect( function () {
			var box = ref.current;
			if ( ! box ) return;
			var doc = box.ownerDocument, win = doc.defaultView;
			var host = doc.createElement( 'div' );
			host.className = 'sportstats-widget';
			Object.keys( props.attrs ).forEach( function ( k ) { host.setAttribute( 'data-' + k, props.attrs[ k ] ); } );
			box.innerHTML = '';
			box.appendChild( host );
			if ( win.SportStatsWidgets ) {
				win.SportStatsWidgets.init();
			} else if ( ! doc.getElementById( 'sportstatsapi-embed-preview' ) ) {
				var s = doc.createElement( 'script' );
				s.id = 'sportstatsapi-embed-preview';
				s.src = cfg.embed;
				s.async = true;
				doc.head.appendChild( s ); // its first run picks up every widget in the document
			}
		}, [ sig ] );
		return el( 'div', { ref: ref, className: 'sportstatsapi-preview' } );
	}

	function CompetitionPicker( props ) {
		var a = props.attributes;
		var st = useState( [] ), list = st[ 0 ], setList = st[ 1 ];
		var timer = useRef();
		function search( q ) {
			clearTimeout( timer.current );
			timer.current = setTimeout( function () {
				wp.apiFetch( { path: '/sportstatsapi/v1/competitions?search=' + encodeURIComponent( q || '' ) } ).then( function ( r ) {
					r.forEach( function ( x ) { comps[ x.key ] = x; } );
					setList( r );
				}, function () { setList( [] ); } );
			}, q ? 250 : 0 );
		}
		var options = list.map( function ( x ) { return { value: x.key, label: compLabel( x ) }; } );
		if ( a.competition && ! options.some( function ( o ) { return o.value === a.competition; } ) ) {
			options.unshift( { value: a.competition, label: a.competitionName || a.competition } );
		}
		return el( c.ComboboxControl, {
			label: __( 'Competition', D ),
			help: __( 'Type a league, cup or country.', D ),
			value: a.competition,
			options: options,
			onFilterValueChange: search,
			onChange: function ( v ) {
				props.setAttributes( { competition: v || '', competitionName: v && comps[ v ] ? compLabel( comps[ v ] ) : '', team: '', teamName: '' } );
			},
			__nextHasNoMarginBottom: true,
		} );
	}

	function TeamPicker( props ) {
		var a = props.attributes;
		var st = useState( null ), teams = st[ 0 ], setTeams = st[ 1 ];
		useEffect( function () {
			setTeams( null );
			if ( ! a.competition ) return;
			wp.apiFetch( { path: '/sportstatsapi/v1/teams?competition=' + encodeURIComponent( a.competition ) } ).then( setTeams, function () { setTeams( [] ); } );
		}, [ a.competition ] );
		if ( ! a.competition ) return null;
		var opts = [ { value: '', label: props.required ? __( 'Choose a team', D ) : __( 'No team', D ) } ];
		( teams || [] ).forEach( function ( t ) { opts.push( { value: slug( t.name ), label: t.name } ); } );
		if ( a.team && ! ( teams || [] ).some( function ( t ) { return slug( t.name ) === a.team; } ) ) {
			opts.push( { value: a.team, label: a.teamName || a.team } );
		}
		return el( c.SelectControl, {
			label: __( 'Team', D ),
			help: teams === null ? __( 'Loading teams…', D ) : ( props.required ? '' : __( 'Optional: highlights or filters one team.', D ) ),
			value: a.team,
			options: opts,
			onChange: function ( v ) {
				var t = ( teams || [] ).filter( function ( x ) { return slug( x.name ) === v; } )[ 0 ];
				props.setAttributes( { team: v, teamName: t ? t.name : '' } );
			},
			__nextHasNoMarginBottom: true,
		} );
	}

	function optionControl( name, a, set ) {
		var sel = function ( label, opts ) {
			return el( c.SelectControl, { key: name, label: label, value: a[ name ], options: opts, onChange: function ( v ) { var o = {}; o[ name ] = v; set( o ); }, __nextHasNoMarginBottom: true } );
		};
		var txt = function ( label, type, help ) {
			return el( c.TextControl, { key: name, label: label, type: type, help: help, value: a[ name ], onChange: function ( v ) { var o = {}; o[ name ] = v; set( o ); }, __nextHasNoMarginBottom: true } );
		};
		switch ( name ) {
			case 'size': return sel( __( 'Size', D ), [ { value: '', label: __( 'Default', D ) }, { value: 'compact', label: __( 'Compact', D ) }, { value: 'standard', label: __( 'Standard', D ) }, { value: 'full', label: __( 'Full', D ) } ] );
			case 'view': return sel( __( 'View', D ), [ { value: '', label: __( 'By round', D ) }, { value: 'date', label: __( 'By date', D ) } ] );
			case 'rows': return txt( __( 'Rows', D ), 'number', __( 'Empty: the whole table.', D ) );
			case 'round': return txt( __( 'Round', D ), 'number', __( 'Empty: the current round.', D ) );
			case 'country': return txt( __( 'TV country', D ), 'text', __( 'Shows TV channels in this country, e.g. United Kingdom.', D ) );
		}
		return null;
	}

	function makeEdit( slug ) {
		var w = cfg.widgets[ slug ];
		return function Edit( props ) {
			var a = props.attributes, set = props.setAttributes;
			var blockProps = be.useBlockProps();
			var paidKey = /^wk_live_/.test( cfg.key || '' );
			var comp = comps[ a.competition ];
			var notices = [];
			var connect = ! cfg.key ? el( c.Notice, { status: 'info', isDismissible: false },
				__( 'Connect to publish: visitors see this widget once SportStatsAPI is connected.', D ) + ' ',
				el( 'a', { href: cfg.settings, target: '_blank', rel: 'noopener' }, __( 'Settings → SportStatsAPI', D ) ) ) : null;
			if ( ! paidKey && ( ! w.free || ( comp && comp.free === false ) ) ) {
				notices.push( ! w.free ? __( 'This widget needs a paid SportStatsAPI plan: with a free key it shows “Widget not available”.', D ) : __( 'This competition needs a paid plan key (Settings → SportStatsAPI).', D ) );
			}
			if ( w.team === 'required' && ! a.team ) {
				notices.push( __( 'Choose a team in the block settings.', D ) );
			}
			var inspector = el( be.InspectorControls, null,
				el( c.PanelBody, { title: __( 'Widget', D ) },
					row( el( CompetitionPicker, props ), 'c' ),
					row( w.team !== 'none' && a.competition ? el( TeamPicker, { attributes: a, setAttributes: set, required: w.team === 'required' } ) : null, 't' ),
					w.options.map( function ( n ) { return row( optionControl( n, a, set ), n ); } )
				),
				el( c.PanelBody, { title: __( 'Look', D ), initialOpen: false },
					row( el( c.SelectControl, { label: __( 'Theme', D ), value: a.theme, options: [ { value: '', label: __( 'Site default', D ) }, { value: 'auto', label: __( 'Auto', D ) }, { value: 'light', label: __( 'Light', D ) }, { value: 'dark', label: __( 'Dark', D ) } ], onChange: function ( v ) { set( { theme: v } ); }, __nextHasNoMarginBottom: true } ), 'th' ),
					row( el( c.SelectControl, { label: __( 'Language', D ), value: a.lang, options: [ { value: '', label: __( 'Site default', D ) }, { value: 'en', label: 'English' }, { value: 'de', label: 'Deutsch' }, { value: 'es', label: 'Español' }, { value: 'it', label: 'Italiano' }, { value: 'fr', label: 'Français' } ], onChange: function ( v ) { set( { lang: v } ); }, __nextHasNoMarginBottom: true } ), 'la' ),
					row( el( c.TextControl, { label: __( 'Accent colour', D ), help: __( '#RRGGBB. Applies with a paid key.', D ), value: a.accent, onChange: function ( v ) { set( { accent: v } ); }, __nextHasNoMarginBottom: true } ), 'ac' ),
					el( c.ToggleControl, { label: __( 'Use the page’s font', D ), checked: !! a.font, onChange: function ( v ) { set( { font: v } ); }, __nextHasNoMarginBottom: true } )
				)
			);
			var body = ! a.competition
				? el( c.Placeholder, { icon: 'chart-area', label: wp.blocks.getBlockType( props.name ).title, instructions: __( 'Choose a competition.', D ) }, el( 'div', { style: { width: '100%', maxWidth: '420px' } }, el( CompetitionPicker, props ) ) )
				: el( 'div', null,
					connect,
					notices.map( function ( t, i ) { return el( c.Notice, { key: i, status: 'warning', isDismissible: false }, t ); } ),
					el( Preview, { attrs: dataAttrs( slug, a ) } ) );
			return el( 'div', blockProps, inspector, body );
		};
	}

	Object.keys( cfg.widgets ).forEach( function ( slug ) {
		wp.blocks.registerBlockType( 'sportstatsapi/' + slug, { edit: makeEdit( slug ), save: function () { return null; } } );
	} );
}( window.wp, window.sportstatsapiEditor ) );
