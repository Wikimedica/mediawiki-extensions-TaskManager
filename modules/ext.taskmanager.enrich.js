/**
 * Client-side link enrichment for TaskManager.
 *
 * Scans rendered page content for internal links, asks action=taskmanager-cards
 * which ones match the configured TaskManagerLinkPatterns, and swaps each match
 * for the rendered card HTML the API returns. Runs on every wikipage.content
 * event, so it picks up dynamically-loaded content (Flow topics, lazy lists,
 * etc.) without any per-surface integration.
 */
( function () {
	'use strict';

	function collectCandidates( $content ) {
		var titles = [];
		var seen = {};
		var elements = {};

		$content.find( 'a[title]' ).each( function () {
			var $a = $( this );

			// Skip links inside an already-enriched card: the template author
			// can opt out by wrapping the card in .taskmanager-card.
			if ( $a.closest( '.taskmanager-card' ).length ) { return; }

			// Skip external links, red links, anchors, and non-wiki hrefs.
			if ( $a.hasClass( 'external' ) || $a.hasClass( 'new' ) ) { return; }
			var href = $a.attr( 'href' ) || '';
			if ( !href || href.charAt( 0 ) === '#' ) { return; }

			var title = $a.attr( 'title' );
			if ( !title ) { return; }

			// Respect the author's explicit choice when the link text differs
			// from the page name (e.g. [[Page|Custom text]], pipe trick, or
			// subpage syntax). Enriching would replace the intentional label
			// with the generic card, which is rarely what the author wants.
			if ( $.trim( $a.text() ) !== title ) { return; }

			if ( !seen[ title ] ) {
				seen[ title ] = true;
				titles.push( title );
				elements[ title ] = [];
			}
			elements[ title ].push( $a );
		} );

		return { titles: titles, elements: elements };
	}

	function enrich( $content ) {
		var found = collectCandidates( $content );
		if ( !found.titles.length ) { return; }

		new mw.Api().post( {
			action: 'taskmanager-cards',
			titles: found.titles,
			// Parse context so the template's own link to the task page does
			// not get rendered as a self-link (no href, .mw-selflink).
			page: mw.config.get( 'wgPageName' ),
			formatversion: 2
		} ).done( function ( resp ) {
			var cards = ( resp && resp.cards ) || {};
			Object.keys( cards ).forEach( function ( title ) {
				var instances = found.elements[ title ] || [];
				var html = cards[ title ];
				if ( !html ) { return; }
				instances.forEach( function ( $a ) {
					$a.replaceWith( html );
				} );
			} );
		} );
	}

	mw.hook( 'wikipage.content' ).add( enrich );
}() );
