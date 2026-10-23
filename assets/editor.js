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

	// The SportStatsAPI icon (assets/icon.svg), drawn for the inserter.
	var icon = { src: el( 'svg', { viewBox: '0 0 24 24', xmlns: 'http://www.w3.org/2000/svg' },
		el( 'rect', { width: 24, height: 24, rx: 5, fill: '#2340F5' } ),
		el( 'path', { d: 'M7 17v-5M12 17V7M17 17v-8', stroke: '#FFFFFF', strokeWidth: 2.4, strokeLinecap: 'round', fill: 'none' } ) ) };
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
		return x.name + ( x.country ? ' · ' + x.country : '' ) + ( x.sport && x.sport.toLowerCase() !== 'soccer' ? ' · ' + x.sport : '' ) + ( x.free === false ? ' · ' + __( 'paid', 'sportstatsapi-live-scores' ) : '' );
	}

	// The data-* attributes, as the PHP render builds them (includes/widgets.php).
	function dataAttrs( slug, a ) {
		var w = cfg.widgets[ slug ], d = cfg.defaults;
		var out = { widget: w.kind, competition: a.competition, lang: a.lang || d.lang, theme: a.theme || d.theme, accent: a.accent || d.accent };
		if ( w.team !== 'none' ) out.team = a.team;
		w.options.forEach( function ( n ) { out[ n ] = n === 'langs' ? ( a.langs ? 'true' : '' ) : a[ n ]; } );
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
		return el( 'div', { ref: ref, className: 'sportstatsapi-preview', style: { pointerEvents: 'none' } } );
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
			label: __( 'Competition', 'sportstatsapi-live-scores' ),
			help: __( 'Type a league, cup or country.', 'sportstatsapi-live-scores' ),
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
		var opts = [ { value: '', label: props.required ? __( 'Choose a team', 'sportstatsapi-live-scores' ) : __( 'No team', 'sportstatsapi-live-scores' ) } ];
		( teams || [] ).forEach( function ( t ) { opts.push( { value: slug( t.name ), label: t.name } ); } );
		if ( a.team && ! ( teams || [] ).some( function ( t ) { return slug( t.name ) === a.team; } ) ) {
			opts.push( { value: a.team, label: a.teamName || a.team } );
		}
		return el( c.SelectControl, {
			label: __( 'Team', 'sportstatsapi-live-scores' ),
			help: teams === null ? __( 'Loading teams…', 'sportstatsapi-live-scores' ) : ( props.required ? '' : __( 'Optional: highlights or filters one team.', 'sportstatsapi-live-scores' ) ),
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
			case 'size': return sel( __( 'Size', 'sportstatsapi-live-scores' ), [ { value: '', label: __( 'Default', 'sportstatsapi-live-scores' ) }, { value: 'compact', label: __( 'Compact', 'sportstatsapi-live-scores' ) }, { value: 'standard', label: __( 'Standard', 'sportstatsapi-live-scores' ) }, { value: 'full', label: __( 'Full', 'sportstatsapi-live-scores' ) } ] );
			case 'view': return sel( __( 'View', 'sportstatsapi-live-scores' ), [ { value: '', label: __( 'By round', 'sportstatsapi-live-scores' ) }, { value: 'date', label: __( 'By date', 'sportstatsapi-live-scores' ) } ] );
			case 'rows': return txt( __( 'Rows', 'sportstatsapi-live-scores' ), 'number', __( 'Empty: the default.', 'sportstatsapi-live-scores' ) );
			case 'round': return txt( __( 'Round', 'sportstatsapi-live-scores' ), 'number', __( 'Empty: the current round.', 'sportstatsapi-live-scores' ) );
			case 'stat': return sel( __( 'Statistic', 'sportstatsapi-live-scores' ), [ { value: '', label: __( 'Goals', 'sportstatsapi-live-scores' ) }, { value: 'assists', label: __( 'Assists', 'sportstatsapi-live-scores' ) }, { value: 'cards', label: __( 'Cards', 'sportstatsapi-live-scores' ) }, { value: 'rating', label: __( 'Rating', 'sportstatsapi-live-scores' ) } ] );
			case 'style': return sel( __( 'Style', 'sportstatsapi-live-scores' ), [ { value: '', label: __( 'List of facts', 'sportstatsapi-live-scores' ) }, { value: 'one', label: __( 'One big fact', 'sportstatsapi-live-scores' ) } ] );
			case 'langs': return el( c.ToggleControl, { key: name, label: __( 'Language buttons', 'sportstatsapi-live-scores' ), checked: !! a.langs, onChange: function ( v ) { set( { langs: v } ); }, __nextHasNoMarginBottom: true } );
			case 'match': return txt( __( 'Match', 'sportstatsapi-live-scores' ), 'text', __( 'A match code or its page path on sportstatsapi.com. Empty: the team’s live, next or last match.', 'sportstatsapi-live-scores' ) );
			case 'teams': return txt( __( 'Teams', 'sportstatsapi-live-scores' ), 'text', __( 'Team names, comma separated. Empty: the leading teams.', 'sportstatsapi-live-scores' ) );
			case 'leagues': return txt( __( 'More competitions', 'sportstatsapi-live-scores' ), 'text', __( 'Competition keys, comma separated (paid plan).', 'sportstatsapi-live-scores' ) );
			case 'player': return txt( __( 'Player', 'sportstatsapi-live-scores' ), 'text', __( 'The player’s name as the website writes it, e.g. joao-pedro.', 'sportstatsapi-live-scores' ) );
			case 'tournament': return txt( __( 'Tournament', 'sportstatsapi-live-scores' ), 'text', __( 'A tournament for the live tab, e.g. us-open.', 'sportstatsapi-live-scores' ) );
			case 'country': return txt( __( 'TV country', 'sportstatsapi-live-scores' ), 'text', __( 'Shows TV channels in this country, e.g. United Kingdom.', 'sportstatsapi-live-scores' ) );
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
				__( 'Connect SportStatsAPI to see this widget: the preview and your visitors need the site’s widget key.', 'sportstatsapi-live-scores' ) + ' ',
				el( 'a', { href: cfg.settings, target: '_blank', rel: 'noopener' }, __( 'Settings → SportStatsAPI', 'sportstatsapi-live-scores' ) ) )
				: /^ssw_free_/.test( cfg.key ) && ! cfg.credit ? el( c.Notice, { status: 'warning', isDismissible: false },
					__( 'Visitors do not see this widget yet: agree to the “Data by SportStatsAPI” link in Settings → SportStatsAPI.', 'sportstatsapi-live-scores' ) + ' ',
					el( 'a', { href: cfg.settings, target: '_blank', rel: 'noopener' }, __( 'Settings → SportStatsAPI', 'sportstatsapi-live-scores' ) ) ) : null;
			if ( ! paidKey && ( ! w.free || ( comp && comp.free === false ) ) ) {
				notices.push( ! w.free ? __( 'This widget needs a paid SportStatsAPI plan: with a free key it shows “Widget not available”.', 'sportstatsapi-live-scores' ) : __( 'This competition needs a paid plan key (Settings → SportStatsAPI).', 'sportstatsapi-live-scores' ) );
			}
			if ( w.team === 'required' && ! a.team ) {
				notices.push( __( 'Choose a team in the block settings.', 'sportstatsapi-live-scores' ) );
			}
			if ( w.options.indexOf( 'match' ) >= 0 && ! a.team && ! a.match ) {
				notices.push( __( 'Choose a team or enter a match in the block settings.', 'sportstatsapi-live-scores' ) );
			}
			if ( w.options.indexOf( 'player' ) >= 0 && slug === 'player' && ! a.player ) {
				notices.push( __( 'Enter a player in the block settings.', 'sportstatsapi-live-scores' ) );
			}
			var inspector = el( be.InspectorControls, null,
				el( c.PanelBody, { title: __( 'Widget', 'sportstatsapi-live-scores' ) },
					row( el( CompetitionPicker, props ), 'c' ),
					row( w.team !== 'none' && a.competition ? el( TeamPicker, { attributes: a, setAttributes: set, required: w.team === 'required' } ) : null, 't' ),
					w.options.map( function ( n ) { return row( optionControl( n, a, set ), n ); } )
				),
				el( c.PanelBody, { title: __( 'Look', 'sportstatsapi-live-scores' ), initialOpen: false },
					row( el( c.SelectControl, { label: __( 'Theme', 'sportstatsapi-live-scores' ), value: a.theme, options: [ { value: '', label: __( 'Site default', 'sportstatsapi-live-scores' ) }, { value: 'auto', label: __( 'Auto', 'sportstatsapi-live-scores' ) }, { value: 'light', label: __( 'Light', 'sportstatsapi-live-scores' ) }, { value: 'dark', label: __( 'Dark', 'sportstatsapi-live-scores' ) } ], onChange: function ( v ) { set( { theme: v } ); }, __nextHasNoMarginBottom: true } ), 'th' ),
					row( el( c.SelectControl, { label: __( 'Language', 'sportstatsapi-live-scores' ), value: a.lang, options: [ { value: '', label: __( 'Site default', 'sportstatsapi-live-scores' ) }, { value: 'en', label: 'English' }, { value: 'de', label: 'Deutsch' }, { value: 'es', label: 'Español' }, { value: 'it', label: 'Italiano' }, { value: 'fr', label: 'Français' } ], onChange: function ( v ) { set( { lang: v } ); }, __nextHasNoMarginBottom: true } ), 'la' ),
					row( el( c.TextControl, { label: __( 'Accent colour', 'sportstatsapi-live-scores' ), help: __( '#RRGGBB. Applies with a paid key.', 'sportstatsapi-live-scores' ), value: a.accent, onChange: function ( v ) { set( { accent: v } ); }, __nextHasNoMarginBottom: true } ), 'ac' ),
					el( c.ToggleControl, { label: __( 'Use the page’s font', 'sportstatsapi-live-scores' ), checked: !! a.font, onChange: function ( v ) { set( { font: v } ); }, __nextHasNoMarginBottom: true } )
				)
			);
			var body = ! a.competition
				? el( c.Placeholder, { icon: icon.src, label: wp.blocks.getBlockType( props.name ).title, instructions: __( 'Choose a competition.', 'sportstatsapi-live-scores' ) }, el( 'div', { style: { width: '100%', maxWidth: '420px' } }, el( CompetitionPicker, props ) ) )
				: el( 'div', null,
					connect,
					notices.map( function ( t, i ) { return el( c.Notice, { key: i, status: 'warning', isDismissible: false }, t ); } ),
					// No key, no data (the API answers KEY_REQUIRED): the notice stands in for the preview.
					cfg.key ? el( Preview, { attrs: dataAttrs( slug, a ) } ) : null );
			return el( 'div', blockProps, inspector, body );
		};
	}

	if ( wp.blocks.updateCategory ) {
		wp.blocks.updateCategory( 'sportstatsapi', { icon: icon.src } );
	}
	Object.keys( cfg.widgets ).forEach( function ( slug ) {
		wp.blocks.registerBlockType( 'sportstatsapi/' + slug, { icon: icon, edit: makeEdit( slug ), save: function () { return null; } } );
	} );
}( window.wp, window.sportstatsapiEditor ) );
