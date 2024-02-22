<?php

namespace DotOrg\FreeMySite\GuideSourcing;

class GuideParser {

	public static function parse( $markdown_content ) : array {
		// Define an array to store parsed chunks
		$chunks = [];

		// Define regular expressions for headings and sections
		$headingRegex = '/^(#{1,6})\s(.+)/m';

		// Match headings and their corresponding sections
		preg_match_all( $headingRegex, $markdown_content, $matches, PREG_OFFSET_CAPTURE );

		for ( $i = 0; $i < count( $matches[ 0 ] ); $i++ ) {
			$headingLevel   = strlen( $matches[ 1 ][ $i ][ 0 ] );
			$headingContent = trim( $matches[ 2 ][ $i ][ 0 ] );
			$sectionStart   = $matches[ 0 ][ $i ][ 1 ] + strlen( $matches[ 0 ][ $i ][ 0 ] );
			$sectionEnd     = ( $i < count( $matches[ 0 ] ) - 1 ) ? $matches[ 0 ][ $i + 1 ][ 1 ] : strlen( $markdown_content );
			$sectionContent = trim( substr( $markdown_content, $sectionStart, $sectionEnd - $sectionStart ) );

			$chunks[] = [
				'heading_level'   => $headingLevel,
				'heading_content' => $headingContent,
				'section_content' => $sectionContent
			];
		}

		return $chunks;
	}
}